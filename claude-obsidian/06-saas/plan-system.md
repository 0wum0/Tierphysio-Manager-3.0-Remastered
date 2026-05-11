# SaaS Plan-System — Architektur & Feature-Matrix

Stand: Mai 2026 — vollständig dokumentiert

---

## Überblick

Pläne (Abo-Tarife) werden in der `plans` Tabelle der SaaS-Platform gespeichert.
Features werden als JSON-Array in der Spalte `plans.features` gespeichert.

---

## Beteiligte Dateien

| Datei | Aufgabe |
|---|---|
| `saas-platform/app/Controllers/PlansController.php` | Admin-UI CRUD |
| `saas-platform/app/Repositories/PlanRepository.php` | DB-Zugriff, Kapazitätsprüfung |
| `saas-platform/app/Services/FeatureLabelService.php` | Zentrale Feature-Registry (48 Keys) |
| `saas-platform/app/Services/SubscriptionService.php` | `assignPlan()` mit Limit-Enforcement |
| `saas-platform/app/Controllers/RegistrationController.php` | Plan-Kapazitätsprüfung bei Registrierung |
| `saas-platform/templates/admin/plans/` | Admin-Formulare |
| `saas-platform/templates/register/plans.twig` | Öffentliche Preisseite |
| `saas-platform/migrations/066_plans_max_subscribers.sql` | `max_subscribers` Spalte |

---

## Feature-Registry — Quelle der Wahrheit

**`FeatureLabelService::MAP`** ist die einzige autoritative Quelle für Feature-Keys.

```php
FeatureLabelService::all()
// Gibt array<string, array{string, string, string}> zurück:
// key => [deutscherName, bootstrapIcon, gruppe]
```

### Gruppen (Stand Mai 2026)
| Gruppe | Anzahl Keys |
|---|---|
| Verwaltung | 7 |
| Dokumentation & Befunde | 6 (befunde, uploads, exports, templates, vet_report, therapy_care) |
| Kommunikation | 7 |
| Finanzen | 8 (inkl. dogschool_invoicing, dogschool_datev_export) |
| Portal & App | 2 (mobile_api, google_calendar_sync) |
| KI & Automatisierung | 1 (ki_assistance) |
| Hundeschule | 12 (dogschool_*) |
| Therapie & Training | 8 (dogschool_training_plans, dogschool_reports etc.) |

**Total: 48 Feature-Keys**

---

## Feature-Speicherung

```sql
plans.features = '["patients","owners","appointments","invoices","calendar"]'
```

- JSON-Array aus Feature-Keys (strings)
- Kein Whitelist-Filtering beim Speichern — alle Keys werden akzeptiert
- Unbekannte Keys fallen durch `FeatureLabelService::humanize()` lesbar
- Feature-Gating via `SubscriptionService::hasFeature($tenantId, $featureKey)`

---

## Plan-Tabelle Schema

```sql
CREATE TABLE plans (
  id              INT UNSIGNED AUTO_INCREMENT,
  slug            VARCHAR(50) NOT NULL UNIQUE,
  name            VARCHAR(100),
  description     TEXT,
  price_month     DECIMAL(10,2),
  price_year      DECIMAL(10,2),
  max_users       INT DEFAULT 5,         -- Benutzer PRO PRAXIS
  max_subscribers INT UNSIGNED DEFAULT 99999999, -- max. SaaS-Kunden (Migration 066)
  features        JSON,
  is_active       TINYINT(1),
  is_public       TINYINT(1),
  trial_days      INT DEFAULT 14,
  stripe_price_id VARCHAR(100),
  stripe_price_id_yearly VARCHAR(100),
  sort_order      INT,
  currency        CHAR(3) DEFAULT 'EUR'
)
```

**WICHTIG:** `max_users` ≠ `max_subscribers`
- `max_users`: Mitarbeiter/Benutzer innerhalb einer Praxis (Tenant-intern)
- `max_subscribers`: Maximale Anzahl SaaS-Kunden, die diesen Plan buchen dürfen

---

## Plan-Kapazitätslimit (max_subscribers)

### Regeln

| Wert | Bedeutung |
|---|---|
| `99999999` | Unbegrenzt |
| `1..99999998` | Hartes Limit |
| `< 1` | Wird auf 1 normiert |

### Zählung aktiver Subscribers

```sql
SELECT COUNT(*) FROM subscriptions
WHERE plan_id = ? AND status IN ('active','trialing','trial','past_due')
```

Ausgeschlossen: `cancelled`, `expired`, `suspended`

### Enforcement-Punkte

1. **Registrierung** (`RegistrationController::submit`) — blockiert mit Redirect
2. **Planwechsel** (`SubscriptionService::assignPlan`) — wirft `RuntimeException`
3. **Admin-Override** (`TenantController::update`) — `bypassCapacityCheck=true`

### Öffentliche Preisseite

- `isAtCapacity()` → `plan_capacity[planId] = true` → Button → "Momentan ausgebucht"
- Plan bleibt sichtbar, aber deaktiviert

---

## Admin-UI

### Plan bearbeiten (`/admin/plans/{id}/edit`)
- Vollständige Feature-Matrix aus `FeatureLabelService::all()`, gruppiert
- `Alle` / `Keine` Buttons zum schnellen Auswählen
- Kunden-Nutzungsanzeige mit Fortschrittsbalken
- `max_subscribers` Feld mit Hilfetext

### Neue Pläne erstellen (`/admin/plans/create`)
- Identische Feature-Matrix (48 Keys, gruppiert)
- `max_subscribers` im Konfigurationsblock (Standard: 99999999)

### Übersicht (`/admin/plans`)
- Spalte "Kunden-Limit" zeigt `cnt / limit` mit Fortschrittsbalken
- Feature-Badges zeigen deutsche Labels (nicht Keys)

---

## Migrationen

| Nr. | Datei | Inhalt |
|---|---|---|
| 066 | `saas-platform/migrations/066_plans_max_subscribers.sql` | `ADD COLUMN max_subscribers INT UNSIGNED DEFAULT 99999999` |

---

## Bekannte Einschränkungen

- Feature-Keys die in `plans.features` aber nicht in `FeatureLabelService` stehen → werden durch `humanize()` lesbar formatiert, aber ohne Icon
- Keine automatische Warnung wenn Ultra-Plan neue Features bekommt, die bestehende Pläne nicht haben
