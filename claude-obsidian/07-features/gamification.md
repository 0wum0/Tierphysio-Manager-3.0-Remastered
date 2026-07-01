# Gamification

## Beschreibung
Feature-Dokumentation für Gamification.

## Zweck
Gemeinsames Verständnis für Implementierung, Grenzen und nächste Schritte.

## Relevante Dateien im Repo
- `plugins/therapy-care-pro/templates/progress_story.twig`
- `templates/dogschool/training/assignment_show.twig`
- `flutter_app/lib/screens/tcp/tcp_progress_screen.dart`

## Datenfluss
Client/Web/Portal → Route/Controller/Plugin → Repository/Service → UI/Response.

## Wichtige Regeln
- API-Verträge dürfen nicht breaking geändert werden.
- Tenant-Isolation bleibt erhalten.
- Status: **partial** — Score-/Badge-Anzeige vorhanden, keine echten Gamification-Mechaniken.

## Audit-Befund (2026-07-01, gegen echten Code verifiziert)
`progress_story.twig` und `assignment_show.twig` enthalten Badge-CSS (`.tcp-score-badge`,
Farbstufen high/mid/low) und ein "Mastery-Level" (0-5) für Trainingsfortschritt, sowie
Fortschrittsbalken pro Score/Max-Wert. Das ist **reine numerische Fortschrittsanzeige**.
Es fehlen: Punkte-/XP-System, Level-Aufstiege, Streak-Counter, Achievements/Badges als
Belohnung, jeglicher Reward-Loop. Kein Punkte-Datenmodell in der DB gefunden.

## Risiken
- Teilimplementierungen können zu falschen Erwartungen führen.

## TODOs
- Fachlich entscheiden: Ausbau zu echter Gamification (Streaks, Achievements, Reward-Loop)
  oder Feature-Seite auf "Fortschritts-Badges" umbenennen, um Erwartungen nicht zu überziehen.

## Verlinkungen
- [[02-api/mobile-api]]
- [[03-web/web-app]]
- [[04-flutter/flutter-app]]
- [[11-decisions/decision-log]]
