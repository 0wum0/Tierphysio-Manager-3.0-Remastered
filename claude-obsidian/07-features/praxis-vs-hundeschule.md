# Praxis vs Hundeschule (dynamische Begriffe, gemeinsame Basis)

## Beschreibung
Feature-Dokumentation für Praxis vs Hundeschule (dynamische Begriffe, gemeinsame Basis).

## Zweck
Gemeinsames Verständnis für Implementierung, Grenzen und nächste Schritte.

## Relevante Dateien im Repo
- `flutter_app/lib/core/terminology.dart`
- `templates/layouts/base.twig (dogschool feature gating)`
- `app/Controllers/DogschoolDashboardController.php`

## Datenfluss
Client/Web/Portal → Route/Controller/Plugin → Repository/Service → UI/Response.

## Wichtige Regeln
- API-Verträge dürfen nicht breaking geändert werden.
- Tenant-Isolation bleibt erhalten.
- Status: **implemented basis + ongoing harmonization**.

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
