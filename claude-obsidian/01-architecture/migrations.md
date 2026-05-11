# Migrations-Architektur

## Beschreibung
Vollständige Dokumentation des Migrations-Systems für Tenant-Datenbanken in TheraPano.

## Relevante Dateien im Repo
- `migrations/` — Tenant-Migrations SQL-Dateien (nummeriert 001–NNN)
- `app/Services/MigrationService.php` — Migration-Runner für Praxis-App (Tenant-Kontext)
- `saas-platform/app/Services/MigrationService.php` — Migration-Runner für SaaS (alle Tenants)
- `saas-platform/app/Controllers/DataMigrationController.php` — Admin-UI + Batch-Rollout
- `saas-platform/provisioning/tenant_schema.sql` — Basis-Schema für neue Tenants

---

## Wie Tenant-Versionen gespeichert werden

```
{prefix}migrations Tabelle (z.B. t_abc123_migrations)
  id          INT (auto)
  version     INT UNIQUE
  applied_at  DATETIME
```

Der aktuelle Stand = `SELECT MAX(version) FROM {prefix}migrations`.

**NICHT** über `settings.db_version` (das ist der alte App-seitige `App\Services\MigrationService` — wird in der Praxis-App für Self-Healing benutzt, NICHT vom SaaS Migration System).

---

## Wie Migrationen ausgerollt werden

### Automatisch (SaaS Migration System)
```
SaaS Admin → /admin/tenants/{id} → "Migrieren"
→ DataMigrationController::migrateSingle()
→ MigrationService::migrateTenant($prefix)
→ Vergleich: MAX(version) < getLatestVersion()
→ Führt alle version > current aus
→ Schreibt jede angewandte Version in {prefix}migrations
```

### Manuell (Repair)
```
SaaS Admin → /admin/tenants/{id} → "Reparieren"
→ DataMigrationController::repairDatabase()
→ MigrationService::forceSyncTenant($prefix)
→ Löscht {prefix}migrations Einträge + setzt db_version=0
→ Führt ALLE Migrationen neu aus (idempotent)
```

### Batch alle Tenants
```
SaaS Admin → /admin/migration → "Alle Tenants migrieren"
→ Iteriert über alle Tenants
→ migrateTenant() pro Tenant
→ Fehler per Tenant isoliert (bricht nicht alle ab)
```

---

## !! KRITISCHE MIGRATION-REGEL !!

### FALSCH (niemals so!):
```
Alter Tenant ist auf v61.
Bug in Logik braucht neue DB-Spalte.
→ Migration 054 bearbeiten (bereits angewandt!)
→ v054 wird nie für diesen Tenant ausgeführt → Bug bleibt!
```

### RICHTIG:
```
Alter Tenant ist auf v61.
Bug in Logik braucht neue DB-Spalte.
→ Neue Migration 062 erstellen
→ 062 hat version > 61 → wird ausgeführt
→ Tenant bekommt die neue Spalte
```

**REGEL: Niemals bestehende Migrationen bearbeiten wenn Tenants bereits auf einer höheren Version sind.**

**STATTDESSEN:** Neue Repair-Migration mit der nächst höheren Versionsnummer erstellen.

---

## Aktueller höchster Migrations-Stand (Stand Mai 2026)

| Stand | Wert |
|---|---|
| Höchste Migration im Repo | **062** |
| Alle produktiven Tenants | v61 |
| v054 erstellt | Mai 2026 — cron_dispatcher_log Erweiterung (für neue Tenants < v54) |
| v055 erstellt | Mai 2026 — Repair für Tenants >= v54 (WIRKUNGSLOS — Tenants auf v61!) |
| v062 erstellt | Mai 2026 — Korrekte Repair für alle Tenants auf v61 (62 > 61 = true ✓) |

**LERNEFFEKT:** v055 war ebenfalls wirkungslos weil `55 > 61 = false`.
Die neue Repair-Migration muss IMMER `version > aktueller-Tenant-Stand` haben.

---

## Idempotenz-Anforderungen

Jede Migration MUSS mehrfach ausführbar sein ohne zu crashen.

### Was der MigrationService automatisch toleriert:
| MySQL-Fehlercode | Bedeutung | Verhalten |
|---|---|---|
| 1050 | Table already exists | ignoriert |
| 1060 | Duplicate column name | ignoriert |
| 1061 | Duplicate key name | ignoriert |
| 1062 | Duplicate entry | ignoriert |
| 1091 | Can't DROP key, not exists | ignoriert |
| 1146 | Table doesn't exist (ALTER auf fehlende Tabelle) | ignoriert |

### Was in SQL idempotent geschrieben werden sollte:
```sql
-- Tabellen: immer IF NOT EXISTS
CREATE TABLE IF NOT EXISTS `my_table` (...);

-- Spalten: OHNE IF NOT EXISTS (MySQL 8.0+ only!) — Fehler 1060 wird toleriert
ALTER TABLE `my_table` ADD COLUMN `new_col` VARCHAR(100) NULL;

-- Indexes: OHNE IF NOT EXISTS — Fehler 1061 wird toleriert
ALTER TABLE `my_table` ADD INDEX `idx_col` (`col`);

-- Daten: INSERT IGNORE oder ON DUPLICATE KEY UPDATE
INSERT IGNORE INTO `settings` (`key`, `value`) VALUES ('my_key', 'value');
```

### ACHTUNG: `ADD COLUMN IF NOT EXISTS` ist MySQL 8.0.3+ only!
**Nicht universell kompatibel!** Stattdessen normales `ADD COLUMN` nutzen —
der MigrationService toleriert Fehler 1060 (Duplicate column) automatisch.

---

## Repair-Migration Pattern

Wenn ein Fix fälschlich in eine alte Migration geschrieben wurde, oder Tenants bereits auf höherer Version sind:

```sql
-- Migration 062: Repair XYZ — fehlende Strukturen nachziehen
--
-- HINTERGRUND: Fix wurde in v054 geschrieben, aber Tenants sind auf v61+.
-- Diese Repair-Migration (v062) zieht die Änderungen idempotent nach.
-- MigrationService toleriert 1060 (Duplicate column) + 1061 (Duplicate key).

ALTER TABLE `affected_table`
    ADD COLUMN `new_column` VARCHAR(100) NULL DEFAULT NULL;

ALTER TABLE `affected_table`
    ADD INDEX `idx_new_column` (`new_column`);
```

---

## Tenant-Schema für neue Tenants

`saas-platform/provisioning/tenant_schema.sql` enthält das komplette Basis-Schema.

**Stand-Kommentar ist NICHT automatisch aktuell!** Nach neuen Core-Tabellen-Migrationen:
1. `tenant_schema.sql` entsprechend ergänzen (nur `CREATE TABLE IF NOT EXISTS`)
2. Danach IMMER eine neue Migration anlegen (für bestehende Tenants)

---

## Self-Healing im MigrationService

`ensureTenantBaseSchema()` in `saas-platform/app/Services/MigrationService.php`:
- Wird VOR jeder `migrateTenant()` Ausführung aufgerufen
- Führt alle `CREATE TABLE IF NOT EXISTS` aus `provisioning/tenant_schema.sql` aus
- Nur sichere Statements: CREATE TABLE, ALTER TABLE, CREATE INDEX, INSERT IGNORE
- Keine DROP/TRUNCATE/DELETE → keine Datenverlust-Gefahr

---

## Bekannte Stolperfallen

### v054 wirkungslos für v61-Tenants (Mai 2026)
**Problem:** Repair-Fix für `cron_dispatcher_log` in v054 geschrieben.
Tenants auf v61+ haben v54 bereits tracked → wird nie ausgeführt.
**Fix:** Migration v055 erstellt, die denselben Fix idempotent nachzieht.

### `ADD COLUMN IF NOT EXISTS` Kompatibilität
MySQL < 8.0.3 kennt `ALTER TABLE ... ADD COLUMN IF NOT EXISTS` nicht.
**Immer** nur normales `ADD COLUMN` nutzen — Fehler 1060 wird toleriert.

---

## Verlinkungen
- [[00-start/CRITICAL-RULES]]
- [[01-architecture/tenant-system]]
- [[15-agent-rules/agents]]
- [[10-bugs/known-bugs]]
