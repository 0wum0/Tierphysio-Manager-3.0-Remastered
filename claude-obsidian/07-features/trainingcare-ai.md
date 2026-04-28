# TrainingCare AI (Hundeschulen)

## Beschreibung
Feature-Dokumentation für TrainingCare AI (Hundeschulen).

## Zweck
Gemeinsames Verständnis für Implementierung, Grenzen und nächste Schritte.

## Relevante Dateien im Repo
- `templates/dogschool/training/plans_index.twig`
- `app/Controllers/TrainingPlanController.php`
- `app/Repositories/TrainingPlanRepository.php`

## Datenfluss
Client/Web/Portal → Route/Controller/Plugin → Repository/Service → UI/Response.

## Wichtige Regeln
- API-Verträge dürfen nicht breaking geändert werden.
- Tenant-Isolation bleibt erhalten.
- Status: **planned/unknown in codebase**.

## Risiken
- Teilimplementierungen können zu falschen Erwartungen führen.

## TODOs
- Fachlichen Soll-/Ist-Vergleich ergänzen.
- E2E-Flow dokumentieren.

## Verlinkungen
- [[02-api/mobile-api]]
- [[03-web/web-app]]
- [[04-flutter/flutter-app]]
- [[11-decisions/decision-log]]
