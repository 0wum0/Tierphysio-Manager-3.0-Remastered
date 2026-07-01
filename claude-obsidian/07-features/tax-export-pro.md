# TaxExportPro (Steuerexport-Plugin)

## Beschreibung
Umfangreiches Steuerexport-Modul, geht deutlich über simplen CSV-Export hinaus.

## Status
**implemented** (verifiziert 2026-07-01, Vollaudit)

## Relevante Dateien im Repo
- `plugins/tax-export-pro/TaxExportController.php`
- `plugins/tax-export-pro/TaxExportService.php`
- `plugins/tax-export-pro/TaxExportRepository.php`

## Funktionsumfang
- ZIP-Archiv-Export für Steuerberater
- CSV/DATEV-Export
- Kassenbuch-Funktion
- PDF-Steuerbericht
- Eng verzahnt mit [[07-features/gobd-audit-log]] (Migration `002_gobd_compliance.sql` liegt im selben Plugin)

## Wichtige Regeln
- API-Verträge dürfen nicht breaking geändert werden.
- Tenant-Isolation bleibt erhalten.
- Überschneidet sich fachlich mit `dogschool_datev_export` (siehe [[06-saas/feature-mapping]]) — Abgrenzung prüfen.

## TODOs
- Fachlichen Soll-/Ist-Vergleich ergänzen.
- E2E-Flow dokumentieren.

## Verlinkungen
- [[07-features/gobd-audit-log]]
- [[07-features/finanz-autopilot]]
