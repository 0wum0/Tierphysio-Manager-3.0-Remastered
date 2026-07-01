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
- Status: **partial** — Upload & Vorher/Nachher-Anzeige implementiert, kein Feedback-/Annotations-Modul.

## Audit-Befund (2026-07-01, gegen echten Code verifiziert)
`migrations/002_progress_media.sql` speichert `tcp_progress_media` mit `file_path`, `media_type`
(image/video/audio/other) und `phase_label` (vorher/nachher/verlauf) — **keine** Spalten für
Annotation, Zeitstempel-Kommentare oder Therapeut-ID. `progress_story.twig` rendert Videos nur
als Read-Only-Player (`<video>`-Tag mit Play-Icon-Overlay). `smartVideoPlayer.js` ist ein reiner
HTML5-Player (Play/Pause), keine Annotation-API. Die Flutter-Progress-Screen zeigt keine
Video-Feedback-Funktion. Therapeuten können also Vorher/Nachher-Videos hochladen und ansehen,
aber **kein zeitbasiertes Kommentar-/Feedback-System** nutzen.

## Risiken
- Teilimplementierungen können zu falschen Erwartungen führen.

## TODOs
- Falls "Video-Feedback" im Vertrieb als Kommentar-/Annotations-Feature beworben wird: entweder
  bauen (Timestamp-Kommentare, Zeichnen auf Video-Frame) oder Beschreibung auf "Video-Verlauf" korrigieren.

## Verlinkungen
- [[02-api/mobile-api]]
- [[03-web/web-app]]
- [[04-flutter/flutter-app]]
- [[11-decisions/decision-log]]
