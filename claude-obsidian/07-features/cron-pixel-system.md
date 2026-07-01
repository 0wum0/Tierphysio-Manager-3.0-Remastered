# Cron-Pixel-System

## Beschreibung
Ungewöhnliche, aber funktionierende Architektur-Entscheidung: Statt eines externen Cron-Schedulers
triggert ein 1x1-Pixel (wie ein E-Mail-Tracking-Pixel), das auf jeder Seite geladen wird, per
`fastcgi_finish_request()` Background-Jobs im Hintergrund — ohne dass der User auf die Antwort warten muss.

## Status
**implemented** (verifiziert 2026-07-01, Tiefenaudit)

## Relevante Dateien im Repo
- `app/Controllers/CronPixelController.php`

## Funktionsumfang
Triggert bei konfigurierbaren Intervallen: Geburtstags-Mails, Kalender-Erinnerungen,
TherapyCare-Reminders, Google-Calendar-Sync — alles ohne klassischen Server-Cronjob nötig
(nützlich z.B. auf Shared-Hosting ohne Cron-Zugriff).

## Wichtige Regeln
- API-Verträge dürfen nicht breaking geändert werden.
- Tenant-Isolation bleibt erhalten.
- Funktioniert nur zuverlässig bei ausreichendem Seiten-Traffic — bei sehr inaktiven Tenants greift der Pixel seltener.

## TODOs
- Fachlichen Soll-/Ist-Vergleich ergänzen.
- Fallback/klassischen Cron als Ergänzung für traffic-arme Tenants dokumentieren, falls vorhanden.

## Verlinkungen
- [[09-cron-mail/README]]
