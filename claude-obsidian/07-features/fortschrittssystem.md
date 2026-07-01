# Fortschrittssystem

## Beschreibung
Feature-Dokumentation für Fortschrittssystem.

## Zweck
Gemeinsames Verständnis für Implementierung, Grenzen und nächste Schritte.

## Relevante Dateien im Repo
- `plugins/therapy-care-pro/TherapyCareRepository.php`
- `plugins/therapy-care-pro/templates/progress_index.twig`
- `flutter_app/lib/screens/tcp/tcp_progress_screen.dart`

## Datenfluss
Client/Web/Portal → Route/Controller/Plugin → Repository/Service → UI/Response.

## Wichtige Regeln
- API-Verträge dürfen nicht breaking geändert werden.
- Tenant-Isolation bleibt erhalten.
- Status: **implemented** (via TherapyCarePro, verifiziert 2026-07-01).

## Audit-Befund (2026-07-01, gegen echten Code verifiziert)
`migrations/001_therapy_care_pro.sql` definiert 6 automatisch angelegte Fortschritts-Kategorien
(je 1–10 Skala): Gangbild, Beweglichkeit, Schmerzreaktion, Muskelspannung, Belastbarkeit,
Allgemeinzustand. `TherapyCareRepository::createProgressEntry()` speichert Score+Notes pro
Datum, `getProgressEntriesForPatient()` lädt den Verlauf, `TherapyCareController::buildChartData()`
baut die Chart-Daten. Vollständig und produktiv nutzbar.

## Risiken
- Teilimplementierungen können zu falschen Erwartungen führen.

## TODOs
- E2E-Flow dokumentieren.

## Verlinkungen
- [[02-api/mobile-api]]
- [[03-web/web-app]]
- [[04-flutter/flutter-app]]
- [[11-decisions/decision-log]]
