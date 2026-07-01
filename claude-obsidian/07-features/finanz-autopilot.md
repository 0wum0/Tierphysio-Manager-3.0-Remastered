# Finanz Autopilot

## Beschreibung
Feature-Dokumentation für Finanz Autopilot.

## Zweck
Gemeinsames Verständnis für Implementierung, Grenzen und nächste Schritte.

## Relevante Dateien im Repo
- `app/Controllers/ReminderDunningController.php`
- `app/Routes/web.php (reminder/dunning endpoints)`
- `flutter_app/lib/screens/invoices/invoice_detail_screen.dart`

## Datenfluss
Client/Web/Portal → Route/Controller/Plugin → Repository/Service → UI/Response.

## Wichtige Regeln
- API-Verträge dürfen nicht breaking geändert werden.
- Tenant-Isolation bleibt erhalten.
- Status: **partial** — manuelle Erinnerungen/Mahnungen vorhanden, kein echter Autopilot.

## Audit-Befund (2026-07-01, gegen echten Code verifiziert)
`ReminderDunningController::reminderStore()` erstellt Erinnerungen/Mahnungen nur über einen
manuellen POST-Trigger (User-Klick). `PraxisCronController` listet die vorhandenen Cron-Jobs auf
(Geburtstagsmails, Kalender-Erinnerungen, Google-Sync) — **kein automatisiertes Mahnwesen mit
Fristen-Eskalation** (z.B. Erinnerung → 1. Mahnung → 2. Mahnung nach X Tagen automatisch). Der
Name "Autopilot" ist irreführend, solange keine automatische Cron-gesteuerte Eskalation existiert.

## Risiken
- Teilimplementierungen können zu falschen Erwartungen führen.

## TODOs
- Cron-basierte automatische Mahnstufen-Eskalation bauen, oder Feature intern als
  "Erinnerungs-/Mahnungs-Verwaltung (manuell)" statt "Autopilot" führen.

## Verlinkungen
- [[02-api/mobile-api]]
- [[03-web/web-app]]
- [[04-flutter/flutter-app]]
- [[11-decisions/decision-log]]
