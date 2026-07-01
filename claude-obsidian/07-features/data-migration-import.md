# Daten-Migrationsassistent (Wechsel von Konkurrenzsoftware)

## Beschreibung
SaaS-seitiger Import-Assistent, mit dem neue Tenants ihre Daten aus einer anderen Software per
SQL-Dump übernehmen können — starkes Vertriebsargument beim Praxis-Wechsel. Bisher nicht dokumentiert.

## Status
**implemented** (verifiziert 2026-07-01, Tiefenaudit)

## Relevante Dateien im Repo
- `saas-platform/app/Controllers/DataMigrationController.php`

## Funktionsumfang
- SQL-Dump-Upload mit zwei Modi: **Smart-Modus** (prefixiert Tabellennamen automatisch für den neuen Tenant), **Raw-Modus** (minimal, unverändert)
- Fehlerbehandlung mit Duplikat-Ignoranz (bricht Import nicht bei einzelnen Konflikten ab)
- Automatischer Aufbau der `migrations`-Tabelle + Praxisdaten-Setup nach Import
- Post-Import: automatische Admin-User-Erstellung
- Batch-Migration für Google-Plugin und weitere Plugin-Migrationen über alle Tenants

## Wichtige Regeln
- API-Verträge dürfen nicht breaking geändert werden.
- Tenant-Isolation bleibt erhalten — Import muss zwingend in den Ziel-Tenant-Prefix geschrieben werden, nie global.
- SQL-Dump-Upload ist sicherheitskritisch (potenzielle SQL-Injection-/Path-Traversal-Fläche) — Validierung/Sandboxing dokumentieren.

## TODOs
- Fachlichen Soll-/Ist-Vergleich ergänzen.
- Sicherheitsreview des Upload-/Import-Pfads explizit dokumentieren.

## Verlinkungen
- [[06-saas/tenant-provisioning]]
