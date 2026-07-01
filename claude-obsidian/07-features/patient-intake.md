# Patient-Intake (Öffentliches Anmeldeformular)

## Beschreibung
Öffentlicher Multi-Step-Wizard für Neuanmeldungen mit Admin-Freigabe-Workflow — Gegenstück zu [[07-features/patient-invite]], hier MIT Prüfpflicht.

## Status
**implemented** (verifiziert 2026-07-01, Vollaudit)

## Relevante Dateien im Repo
- `plugins/patient-intake/*` (Controller, Service, Repository, Templates)

## Funktionsumfang
- Multi-Step-Anmeldeformular (öffentlich, kein Login)
- Foto-Upload
- Admin-Eingangspostfach mit Freigabe-Workflow
- Dashboard-Widget + Header-Benachrichtigungen für neue Eingänge

## Wichtige Regeln
- API-Verträge dürfen nicht breaking geändert werden.
- Tenant-Isolation bleibt erhalten.
- Öffentliche Route ohne Auth — Spam-/Missbrauchsschutz prüfen (Vergleich mit [[07-features/online-booking]], das Honeypot + Rate-Limiting nutzt).

## TODOs
- Fachlichen Soll-/Ist-Vergleich ergänzen.
- E2E-Flow dokumentieren.

## Verlinkungen
- [[07-features/patient-invite]]
- [[07-features/online-booking]]
