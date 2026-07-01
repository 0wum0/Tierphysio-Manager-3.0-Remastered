# Online-Booking (öffentliches Buchungsportal)

## Beschreibung
Öffentliche Terminanfrage ohne Login mit Spam-/Missbrauchsschutz und Admin-Freigabe zu Lead-Konvertierung.

## Status
**implemented** (verifiziert 2026-07-01, Vollaudit) — bisher nur am Rande in [[07-features/kurs-system-hundeschulen]] erwähnt.

## Relevante Dateien im Repo
- `app/Controllers/OnlineBookingController.php`

## Funktionsumfang
- Öffentliche Route ohne Auth
- Schutzmechanismen: Honeypot-Feld, Rate-Limiting (5 Anfragen/Stunde), IP/User-Agent-Logging
- Admin-Freigabe-Workflow: Anfrage → Konvertierung zu Lead (siehe `dogschool_leads` in [[06-saas/feature-mapping]])

## Wichtige Regeln
- API-Verträge dürfen nicht breaking geändert werden.
- Tenant-Isolation bleibt erhalten.
- Rate-Limiting/Honeypot nicht versehentlich entfernen — einzige Spam-Barriere auf einer nicht-authentifizierten Route.

## TODOs
- Fachlichen Soll-/Ist-Vergleich ergänzen.
- E2E-Flow dokumentieren.

## Verlinkungen
- [[07-features/kurs-system-hundeschulen]]
- [[07-features/patient-intake]]
