# TherapyCare AI (Praxen)

## Beschreibung
Feature-Dokumentation für TherapyCare AI (Praxen).

## Zweck
Gemeinsames Verständnis für Implementierung, Grenzen und nächste Schritte.

## Relevante Dateien im Repo
- `plugins/therapy-care-pro/TherapyCareController.php`
- `plugins/therapy-care-pro/TherapyCarePortalController.php`
- `app/Routes/web.php (tcp API routes)`
- `flutter_app/lib/screens/tcp/tcp_screen.dart`

## Datenfluss
Client/Web/Portal → Route/Controller/Plugin → Repository/Service → UI/Response.

## Wichtige Regeln
- API-Verträge dürfen nicht breaking geändert werden.
- Tenant-Isolation bleibt erhalten.
- Status: **partial naming present; verify AI scope**.

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
