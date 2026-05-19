# Hausaufgaben-Plugin (homework)

## Beschreibung
Das Hausaufgaben-Plugin ermöglicht Therapeuten und Trainern, strukturierte Trainings-/Therapiepläne
für ihre Patienten zu erstellen und über das Besitzer-Portal zu teilen.

## Status
- Plugin-Slug: `homework`
- Aktiv in `plugins/enabled.json`: ✅ JA
- Feature-Key: `homework`
- **Default: AKTIV für alle Tenants (Basic, Pro, Ultra)** — seit Migration 062 (Mai 2026)

## Feature-Gating

| Eigenschaft | Wert |
|---|---|
| `feature_key` | `homework` |
| `required_plan` | `basic` (seit v062 — vorher `pro`) |
| `global_enabled` | `1` |
| Deaktivierbar | ✅ Ja — über `tenants.features_override` im SaaS-Admin |

### Default-Aktiv-Logik (seit Mai 2026)
- `required_plan = 'basic'` → alle Pläne haben Zugriff
- Alle `plans` Einträge haben `homework` in ihrer `features` JSON-Liste
- Feature-Cache wird bei Migration 062 gelöscht → Re-Sync beim nächsten Request
- Wenn kein expliziter Override gesetzt → homework = **aktiv**
- Wenn `features_override: {"homework": false}` gesetzt → **deaktiviert** (Admins können das im SaaS-Admin)

## Relevante Dateien

| Datei | Zweck |
|---|---|
| `plugins/homework/manifest.json` | Plugin-Metadaten, slug, service_provider |
| `plugins/homework/ServiceProvider.php` | Routes, Patient-Detail-Tab, DI-Wiring |
| `plugins/homework/templates/patient-tab.twig` | Twig-Template für Patienten-Modal-Tab |
| `app/Controllers/HomeworkController.php` | REST-API-Controller |
| `app/Repositories/HomeworkRepository.php` | DB-Zugriff (prefixed tables) |
| `saas-platform/migrations/063_homework_default_active.sql` | Macht homework default-aktiv für alle Tenants |

## Routes (Plugin-seitig registriert)

```
GET  /api/homework/templates                   → HomeworkController::getTemplates()
GET  /api/patients/{patient_id}/homework       → HomeworkController::getPatientHomework()
POST /api/patients/{patient_id}/homework       → HomeworkController::createPatientHomework()
DELETE /api/patients/{patient_id}/homework/{id}→ HomeworkController::deletePatientHomework()
```

Feature-Gate: `/api/homework` → `homework` (in `FeatureRouteMap.php`)

## Patient-Modal-Integration
Das Plugin fügt einen "Hausaufgaben"-Tab in die Patienten-Modal-Akte ein via `patientDetailTabs`-Hook.

## Hundeschule (Trainer-Tenants)
- `dogschool_training_plans` Feature-Key deckt ähnliche Funktionalität für Hundeschulen ab
- `homework` ist auch für Trainer-Tenants aktiv (falls sie Praxis-Patienten haben)
- `dogschool_homework` Tabelle existiert über `DogschoolSchemaService` (separates Schema)

## Self-Healing für bestehende Tenants

Migration 063 löscht `_features_cache` in der Settings-Tabelle jedes Tenants.
Beim nächsten Request wird der Cache aus der SaaS-DB neu aufgebaut, dabei greift:
- `required_plan = 'basic'` → homework ist für alle Pläne freigegeben
- Alle Pläne haben `homework` in ihrer Feature-Liste

## Bootstrap Modal Stacking — Wichtiger Hinweis (Mai 2026)

`admin_homework.twig` öffnet bei der Plan-Erstellung **verschachtelte Modals**:
- `modal-create-plan` (Eltern-Modal)
- `modal-template-select` / `modal-library-picker` (Sub-Modale)

**Bootstrap 5 Regel**: Beim `.hide()` eines Sub-Modals entfernt Bootstrap `body.modal-open` und das Backdrop,
auch wenn ein anderes Modal noch offen ist. Daher:
- **Immer `getOrCreateInstance(el)`** statt `new bootstrap.Modal(el)` verwenden
- Nach dem `hidden.bs.modal`-Event jedes Sub-Modals: prüfen ob Eltern-Modal noch `.show` hat → ggf. `body.modal-open` + Backdrop wiederherstellen
- `cleanupStaleModalState()` räumt NUR auf wenn kein Modal mehr `.show` hat

## Änderungshistorie

| Datum | Änderung |
|---|---|
| Mai 2026 | homework von `pro` auf `basic` Plan gehoben — Migration 063 |
| Mai 2026 | homework in alle Plan-Feature-Listen aufgenommen (v063) |
| Mai 2026 | Bestehende Tenants werden via Cache-Invalidierung automatisch geheilt |
| Mai 2026 | Bootstrap Modal Stacking Bug behoben — Seite nach Vorlagenauswahl nicht mehr blockiert |

## Verlinkungen
- [[01-architecture/migrations]]
- [[06-saas/feature-matrix]]
- [[15-agent-rules/agents]]
