# UI-Settings & Notification-Center

## Beschreibung
Zwei kleinere, aber echte Features ohne bisherige Doku-Entsprechung: persönliches UI-Layout und ein serverseitiges Benachrichtigungs-Center.

## Status
**implemented** (verifiziert 2026-07-01, Vollaudit)

## Relevante Dateien im Repo
- `app/Controllers/UiSettingsController.php` — persistiert individuelles UI-Layout pro Nutzer als JSON via `UserPreferencesRepository`
- `app/Controllers/NotificationController.php` — aggregiert Systembenachrichtigungen (z.B. Patienten-Geburtstage) serverseitig für die Header-Glocke

## Wichtige Regeln
- API-Verträge dürfen nicht breaking geändert werden.
- Tenant-Isolation bleibt erhalten.

## TODOs
- Fachlichen Soll-/Ist-Vergleich ergänzen.
- E2E-Flow dokumentieren.

## Verlinkungen
- [[07-features/README]]
