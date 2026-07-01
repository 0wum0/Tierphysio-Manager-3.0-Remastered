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
- Status: **implemented (Trainingsplan-System + echte KI seit 2026-07-01)** — siehe [[07-features/ai-integration]].

## Update (2026-07-01, Teil 5): Echte KI-Funktionalität ergänzt
`TrainingPlanController::aiRecommendations()` nutzt den neuen [[07-features/ai-integration|zentralen
AiService]] (Grok/Gemini), um aus Mastery-Level und Erfolgsquote je Übung eine echte
KI-Trainingsempfehlung zu generieren — sichtbar als Karte "KI-Trainingsempfehlung" auf der
Zuweisungs-Detailseite, gated durch `ki_assistance` UND `dogschool_training_plans`.

## Historischer Audit-Befund (2026-07-01, vor der KI-Ergänzung)
`TrainingPlanController.php` (431 Zeilen) implementiert ein vollständiges CRUD-System für
Trainingspläne, Übungs-Katalog, Plan-Zuweisungen an Hunde, Fortschritts-Erfassung und
Hausaufgaben (Feature-Gates: `dogschool_training_plans`, `dogschool_exercises`,
`dogschool_progress`, `dogschool_homework`). Das war real und produktiv, aber ohne KI-Komponente
("AI" im Namen war reines Branding).

## Risiken
- Ohne konfigurierten Grok-/Gemini-Provider im SaaS-Admin bleibt der Button funktionslos
  (klare Fehlermeldung `ai_not_configured`, kein Crash).

## TODOs
- Siehe offene Punkte in [[07-features/ai-integration]] (Rate-Limiting, Caching, Audit-Log).

## Verlinkungen
- [[02-api/mobile-api]]
- [[03-web/web-app]]
- [[04-flutter/flutter-app]]
- [[11-decisions/decision-log]]
