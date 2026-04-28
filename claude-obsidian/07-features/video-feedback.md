# Video Feedback

## Beschreibung
Feature-Dokumentation für Video Feedback.

## Zweck
Gemeinsames Verständnis für Implementierung, Grenzen und nächste Schritte.

## Relevante Dateien im Repo
- `plugins/therapy-care-pro/migrations/002_progress_media.sql`
- `public/themes/smart-tierphysio/scripts/optional/smartVideoPlayer.js`
- `flutter_app/lib/widgets/media_viewer.dart`

## Datenfluss
Client/Web/Portal → Route/Controller/Plugin → Repository/Service → UI/Response.

## Wichtige Regeln
- API-Verträge dürfen nicht breaking geändert werden.
- Tenant-Isolation bleibt erhalten.
- Status: **implemented (media timeline/progress), verify UX scope**.

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
