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
- Status: **implemented (Trainingsplan-System), AI-Anteil: not_found**.

## Audit-Befund (2026-07-01, gegen echten Code verifiziert)
`TrainingPlanController.php` (431 Zeilen) implementiert ein vollständiges CRUD-System für
Trainingspläne, Übungs-Katalog, Plan-Zuweisungen an Hunde, Fortschritts-Erfassung und
Hausaufgaben (Feature-Gates: `dogschool_training_plans`, `dogschool_exercises`,
`dogschool_progress`, `dogschool_homework`). Das ist real und produktiv.
Eine **KI-Komponente existiert nicht** — keine Trainingsempfehlungen, kein LLM-Call, keine
automatische Plan-Generierung. "AI" im Namen ist Branding ohne technisches Gegenstück.

## Risiken
- Marketing/Vertrieb darf "TrainingCare AI" nicht als KI-Feature bewerben, solange kein KI-Backend existiert.

## TODOs
- Entweder echte KI-Funktionalität bauen (z.B. automatische Trainingsempfehlungen aus Fortschrittsdaten)
  oder Feature-Namen auf den tatsächlichen Funktionsumfang ("Trainingsplan-System") korrigieren.

## Verlinkungen
- [[02-api/mobile-api]]
- [[03-web/web-app]]
- [[04-flutter/flutter-app]]
- [[11-decisions/decision-log]]
