# Flutter Offline-Modus

## Beschreibung
Die Flutter-App funktioniert auch ohne Internetverbindung über eine lokale SQLite-Kopie —
bisher nicht dokumentiert.

## Status
**implemented** (verifiziert 2026-07-01, Tiefenaudit)

## Relevante Dateien im Repo
- `flutter_app/lib/services/offline_service.dart` (Zeile 12–31)

## Funktionsumfang
- Lokale SQLite-Datenhaltung mit 14-Tage-Cache-Limit
- Automatischer Sync bei Reconnect
- Bild-Auswahl via `image_picker`-Integration

## Bekannte Einschränkungen (verifiziert, nicht spekuliert)
- Kein QR-/Barcode-Scanner im Dependency-Baum (`pubspec.yaml`)
- Keine Push-Notifications implementiert (nur Platzhalter-Struktur vorbereitet)
- Eher "Read-Mostly"-Offline (Anzeige/Cache), kein vollständiger Offline-Schreib-Sync-Zyklus verifiziert

## Wichtige Regeln
- API-Verträge dürfen nicht breaking geändert werden.
- Tenant-Isolation bleibt erhalten.

## TODOs
- Fachlichen Soll-/Ist-Vergleich ergänzen: welche Schreibvorgänge sind offline möglich vs. nur Anzeige?
- E2E-Flow dokumentieren.

## Verlinkungen
- [[04-flutter/flutter-app]]
