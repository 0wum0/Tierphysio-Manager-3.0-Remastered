# Kurs-System für Hundeschulen

## Beschreibung
Feature-Dokumentation für Kurs-System für Hundeschulen.

## Zweck
Gemeinsames Verständnis für Implementierung, Grenzen und nächste Schritte.

## Relevante Dateien im Repo
- `app/Controllers/CourseController.php`
- `app/Repositories/CourseRepository.php`
- `templates/dogschool/courses/index.twig`
- `migrations/050_dogschool_courses.sql`

## Datenfluss
Client/Web/Portal → Route/Controller/Plugin → Repository/Service → UI/Response.

## Wichtige Regeln
- API-Verträge dürfen nicht breaking geändert werden.
- Tenant-Isolation bleibt erhalten.
- Status: **implemented** (verifiziert 2026-07-01).

## Audit-Befund (2026-07-01, gegen echten Code verifiziert)
`CourseController.php`, `LeadController.php`, `PackageController.php` existieren mit vollständigem
CRUD (index/show/create/update). `PackageController::expireOutdated()` verwaltet Paket-Guthaben
automatisch. Kurs-Enrollment (`enrollmentsForCourse()`) ist vorhanden. **Einschränkung:** Die
Buchung läuft intern/B2B über die Praxis-Oberfläche — keine öffentliche Endkunden-Webshop-Buchung
ohne Login gefunden.

## Risiken
- Teilimplementierungen können zu falschen Erwartungen führen.

## TODOs
- Öffentliche Online-Buchung für Endkunden (ohne Login) prüfen/planen, falls gewünscht.
- E2E-Flow dokumentieren.

## Verlinkungen
- [[02-api/mobile-api]]
- [[03-web/web-app]]
- [[04-flutter/flutter-app]]
- [[11-decisions/decision-log]]
