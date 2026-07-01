# GoBD-Audit-Log (Steuerkonformität)

## Beschreibung
Unveränderliches Änderungsprotokoll für Rechnungen, Pflichtfeature für steuerliche GoBD-Konformität (Grundsätze zur ordnungsmäßigen Führung und Aufbewahrung von Büchern).

## Status
**implemented** (verifiziert 2026-07-01, Vollaudit)

## Relevante Dateien im Repo
- `plugins/tax-export-pro/GobdAuditService.php`
- `plugins/tax-export-pro/migrations/002_gobd_compliance.sql`
- `plugins/tax-export-pro/templates/audit-log.twig`

## Funktionsumfang
Protokolliert unveränderlich: Rechnungserstellung, Statuswechsel, PDF-Download, E-Mail-Versand. Dient als Nachweis bei Steuerprüfungen.

## Wichtige Regeln
- API-Verträge dürfen nicht breaking geändert werden.
- Tenant-Isolation bleibt erhalten.

## TODOs
- Fachlichen Soll-/Ist-Vergleich ergänzen.
- E2E-Flow dokumentieren.

## Verlinkungen
- [[07-features/finanz-autopilot]]
- [[08-billing/billing-and-stripe]]
