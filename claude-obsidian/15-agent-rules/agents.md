# Agent Rules (Claude, Codex, Cursor, Windsurf)

## Beschreibung
Gemeinsamer Regelkatalog für alle AI-Agents.

## Zweck
Konsistente Änderungen ohne Breaking Changes oder Gedächtnisverlust.

## Relevante Dateien im Repo
- `AGENTS.md`
- `claude-obsidian/00-start/CRITICAL-RULES.md`
- `claude-obsidian/15-agent-rules/update-brain.md`

## Datenfluss
Task erhalten → Brain lesen → Änderung umsetzen → Brain aktualisieren → committen.

## Wichtige Regeln
- Keine Breaking Changes an API/Domain/Auth/Tenant.
- Brain immer zuerst nutzen.
- Brain nach jeder Änderung verpflichtend aktualisieren.
- Keine Annahmen ohne Quellbezug.

## !! MIGRATION-PFLICHT-REGEL !!

**Niemals alte Migrationen bearbeiten wenn Tenants bereits auf höherem Stand sind.**

### Warum:
- `MigrationService` führt nur Migrationen aus, deren `version > MAX({prefix}migrations.version)`
- Tenant auf v61 → v054 wird NIE ausgeführt → Fix bleibt wirkungslos
- Dies ist ein stiller Fehler — kein Crash, kein Log, einfach wirkungslos

### Korrekte Vorgehensweise:
1. Prüfe: Was ist der höchste Migrations-Stand im Repo? (`ls migrations/ | tail`)
2. Prüfe: Was ist der höchste Stand der Tenants? (SaaS Admin → Versions-Check)
3. Wenn Fix für `version <= Tenant-Stand` nötig: **neue Repair-Migration** erstellen
4. Neue Migration: nächst höhere Nummer (z.B. Repo hat 054, erstelle 055)
5. Repair-Migration muss idempotent sein (normales ADD COLUMN — Fehler 1060 toleriert)
6. KEIN `ADD COLUMN IF NOT EXISTS` — das ist MySQL 8.0.3+ only!

### Self-Healing Pattern:
```sql
-- Idempotent: MigrationService toleriert 1060 (Duplicate column) + 1061 (Duplicate key)
ALTER TABLE `my_table` ADD COLUMN `new_col` VARCHAR(100) NULL DEFAULT NULL;
ALTER TABLE `my_table` ADD INDEX `idx_new_col` (`new_col`);
-- Tabellen: immer IF NOT EXISTS (universell kompatibel)
CREATE TABLE IF NOT EXISTS `my_table` (...);
-- Daten: INSERT IGNORE oder ON DUPLICATE KEY UPDATE
INSERT IGNORE INTO `settings` (`key`, `value`) VALUES ('key', 'val');
```

→ Details: [[01-architecture/migrations]]

## Agent-spezifisch
- **Claude**: vor Ausführung immer [[00-start/CRITICAL-RULES]] prüfen.
- **Codex**: nach Commit zwingend Brain-Diff prüfen.
- **Cursor**: keine Schnellfixes ohne Doku-Update.
- **Windsurf**: Workflows nur mit Brain-Referenz starten.

## Risiken
- Unterschiedliche Agent-Standards führen zu Architekturdrift.

## TODOs
- Qualitäts-Gates pro Agentenmodus ergänzen.

## Verlinkungen
- [[15-agent-rules/update-brain]]
- [[00-start/CRITICAL-RULES]]
