# Consent-Management (Einwilligungen)

## Beschreibung
Verwaltung versionierter Einwilligungsformulare (DSGVO-relevant), primär für Hundeschulbetrieb.

## Status
**implemented** (verifiziert 2026-07-01, Vollaudit)

## Relevante Dateien im Repo
- `app/Controllers/ConsentController.php`

## Funktionsumfang
- Versionierte Einwilligungsformulare mit Pflicht-Flag
- Teilnahme-Einwilligungen für Hundeschul-Kurse (Feature-Key `dogschool_consents`, siehe [[06-saas/feature-mapping]])

## Wichtige Regeln
- API-Verträge dürfen nicht breaking geändert werden.
- Tenant-Isolation bleibt erhalten.
- Versionierung darf bestehende Einwilligungen nicht rückwirkend verändern (DSGVO-Nachweispflicht).

## TODOs
- Fachlichen Soll-/Ist-Vergleich ergänzen.
- E2E-Flow dokumentieren.

## Verlinkungen
- [[06-saas/feature-mapping]]
