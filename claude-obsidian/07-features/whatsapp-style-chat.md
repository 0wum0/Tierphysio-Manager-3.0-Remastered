# WhatsApp-Style Chat

## Beschreibung
Feature-Dokumentation für WhatsApp-Style Chat.

## Zweck
Gemeinsames Verständnis für Implementierung, Grenzen und nächste Schritte.

## Relevante Dateien im Repo
- `app/Routes/web.php (Nachrichten-Endpunkte)`
- `plugins/owner-portal/MessagingRepository.php`
- `flutter_app/lib/screens/messages/messages_screen.dart`
- `flutter_app/lib/screens/messages/message_thread_screen.dart`

## Datenfluss
Client/Web/Portal → Route/Controller/Plugin → Repository/Service → UI/Response.

## Wichtige Regeln
- API-Verträge dürfen nicht breaking geändert werden.
- Tenant-Isolation bleibt erhalten.
- Status: **implemented (core), verify depth**.

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
