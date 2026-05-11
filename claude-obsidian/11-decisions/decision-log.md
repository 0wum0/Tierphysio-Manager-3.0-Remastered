# Decision Log

## Beschreibung
Architektur- und Produktentscheidungen mit Datum, Kontext, Konsequenz.

## Zweck
Nachvollziehbarkeit für spätere Refactors und Agent-Übergaben.

## Relevante Dateien im Repo
- `flutter_app/lib/services/api_service.dart`
- `app/Core/Database.php`
- `saas-platform/app/Routes/web.php`

## Datenfluss
Entscheidung treffen → Eintrag erstellen → betroffene Bereiche verlinken.

## Wichtige Regeln
- Jede irreversible Entscheidung dokumentieren.
- Eintragformat: Datum, Entscheidung, Begründung, Impact, Rollback-Option.

## Risiken
- Ohne Decision Log wiederholen Teams alte Fehler.

## TODOs
- Historische Schlüsselentscheidungen rückwirkend eintragen.

---

## Entscheidung: Migrations-Versionsregel (Mai 2026)

**Datum:** Mai 2026  
**Kontext:** Agent (Windsurf/Cascade) hat Fix für `cron_dispatcher_log` Spalten in Migration v054 geschrieben. Produktive Tenants waren bereits auf v61. Migration v054 wurde nie ausgeführt → Bugfix hatte keine Wirkung.

**Entscheidung:**
> Niemals bestehende Migrationen (Versionsnummer < aktueller Tenant-Stand) bearbeiten.
> Stattdessen immer neue Repair-Migration mit nächst höherer Versionsnummer erstellen.

**Begründung:**
- `MigrationService::migrateTenant()` vergleicht `MAX(version)` mit `getLatestVersion()`
- Nur Migrationen mit `version > MAX(version)` werden ausgeführt
- Wenn Tenant auf v61 und Fix in v54 → v54 wird nie ausgeführt → Fix bleibt wirkungslos

**Konsequenz:**
- v054 bleibt für neue Tenants (< v54) korrekt
- v055 Repair-Migration zieht denselben Fix für Tenants >= v54 idempotent nach
- Neue Regel in AGENTS.md und agents.md dokumentiert

**Rollback:** Nicht nötig — rein additive Migrationen, keine Datenverlust-Gefahr.

**Impact:** Alle Agenten, alle zukünftigen Migrationen.

**Technisches Detail:**
- Versionstracking: `{prefix}migrations.MAX(version)` (NICHT `settings.db_version`)
- Fehler 1060 (Duplicate column) + 1061 (Duplicate key) werden vom MigrationService toleriert → Migrationen sind idempotent ohne `IF NOT EXISTS` bei `ADD COLUMN`
- `ADD COLUMN IF NOT EXISTS` ist MySQL 8.0.3+ only → NICHT universell einsetzbar

---

## Verlinkungen
- [[00-start/CRITICAL-RULES]]
- [[01-architecture/migrations]]
- [[12-roadmap/roadmap]]
