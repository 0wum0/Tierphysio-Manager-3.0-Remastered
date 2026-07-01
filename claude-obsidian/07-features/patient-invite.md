# Patient-Invite (Einladungslinks)

## Beschreibung
Self-Service-Onboarding für Tierbesitzer: Einladungslink → Besitzer füllt Formular aus → sofortige automatische Anlage als aktiver Patient/Besitzer, ohne manuelle Freigabe.

## Status
**implemented** (verifiziert 2026-07-01, Vollaudit)

## Relevante Dateien im Repo
- `plugins/patient-invite/InviteController.php`
- `plugins/patient-invite/InviteMailService.php`
- `plugins/patient-invite/InviteRepository.php`
- 4 zugehörige Migrationen

## Funktionsumfang
- Versand des Einladungslinks per E-Mail/WhatsApp
- Formular für Besitzer, danach **sofortige** automatische Anlage (kein Freigabe-Workflow)
- Unterscheidet sich von [[07-features/patient-intake]] genau durch die fehlende Prüf-/Freigabepflicht

## Wichtige Regeln
- API-Verträge dürfen nicht breaking geändert werden.
- Tenant-Isolation bleibt erhalten.
- Einladungslinks müssen zeitlich befristet/einmalig verwendbar sein — Missbrauchsrisiko bei fehlender Freigabe.

## TODOs
- Fachlichen Soll-/Ist-Vergleich ergänzen.
- E2E-Flow dokumentieren.

## Verlinkungen
- [[07-features/patient-intake]]
