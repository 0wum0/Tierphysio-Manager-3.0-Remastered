# Bulk-Mail / Massen-Kommunikation

## Beschreibung
Massen-E-Mails an gefilterte Besitzerlisten plus eigenständiges Feiertags-Mailing-System.

## Status
**implemented** (verifiziert 2026-07-01, Vollaudit)

## Relevante Dateien im Repo
- `plugins/bulk-mail/BulkMailController.php`
- `plugins/bulk-mail/HolidayController.php`
- `plugins/bulk-mail/HolidayMailService.php`

## Funktionsumfang
- Serienmails an gefilterte Besitzerlisten (Feature-Key `bulk_mail`, siehe [[06-saas/feature-mapping]])
- Eigenständiges Feiertags-Mailing: automatische Berechnung deutscher Feiertage inkl. beweglicher Feiertage (Ostern etc.), Versand gesteuert per "days_before"-Vorlauf

## Wichtige Regeln
- API-Verträge dürfen nicht breaking geändert werden.
- Tenant-Isolation bleibt erhalten.

## TODOs
- Fachlichen Soll-/Ist-Vergleich ergänzen.
- E2E-Flow dokumentieren.

## Verlinkungen
- [[07-features/mailbox-plugin]]
- [[07-features/marketing-automation]]
