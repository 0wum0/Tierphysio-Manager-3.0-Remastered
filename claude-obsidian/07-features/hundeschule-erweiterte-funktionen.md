# Hundeschule — Erweiterte Business-Funktionen

## Beschreibung
Vier zusätzliche, substantielle Hundeschul-Module, die bisher nicht in [[07-features/hundeschulen-support]]
bzw. [[07-features/kurs-system-hundeschulen]] erfasst waren.

## Status
**implemented** (verifiziert 2026-07-01, Tiefenaudit)

## Anwesenheitsverwaltung
`app/Controllers/AttendanceController.php` — Anwesenheits-Matrix pro Kurs-Session mit Status
present/absent/excused/late/left_early/no_show + Notizfeld pro Teilnehmer. Vergangene Sessions
werden automatisch auf "abgeschlossen" gesetzt.

## Trainerverwaltung
`app/Controllers/TrainerController.php` — Trainerprofile mit Bio, Spezialisierungen, Avatar, Farbe,
plus eigenständiges Verfügbarkeitssystem (Wochentag/Uhrzeit), getrennt von der normalen User-Auth.

## Trainings-Reports (Business-Analytics)
`app/Controllers/DogschoolReportController.php` — aggregiertes Reporting: Kursauslastung,
Anwesenheitsquote in %, Umsatz aus Enrollments + Paketverkäufen, Lead-Konversionsrate.

## Hundeschul-Rechnungen
`app/Controllers/DogschoolInvoiceController.php` — eigenständiges Rechnungsmodul für Hundeschulen,
verzahnt mit dem zentralen Invoice-Service; automatische Rechnungserstellung aus
Kurs-Enrollments und Paketverkäufen mit Hundeschul-Terminologie.

## Eigenständiges Kalender-Plugin
`plugins/calendar/CalendarController.php` — separates Modul (nicht zu verwechseln mit
[[07-features/google-calendar-sync]], das nur das bidirektionale Sync-Backend ist): eigene
Warteliste-Ansicht, Stats-Dashboard, Reminder-Service über das Cron-Pixel-System.

## Wichtige Regeln
- API-Verträge dürfen nicht breaking geändert werden.
- Tenant-Isolation bleibt erhalten.

## TODOs
- Jedes der vier Module ggf. auf eigene Detailseite ausgliedern, falls fachlicher Vertiefungsbedarf entsteht.

## Verlinkungen
- [[07-features/hundeschulen-support]]
- [[07-features/kurs-system-hundeschulen]]
- [[07-features/cron-pixel-system]]
