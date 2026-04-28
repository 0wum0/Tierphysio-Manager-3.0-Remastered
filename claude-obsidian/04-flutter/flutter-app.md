# Flutter App

## Beschreibung
Android/Windows Client mit direkter Nutzung der Mobile API.

## Zweck
Client-Regeln, Integrationsgrenzen und Spiegelpflicht zur Web-App festhalten.

## Relevante Dateien im Repo
- `flutter_app/lib/services/api_service.dart`
- `flutter_app/lib/core/router.dart`
- `flutter_app/lib/core/terminology.dart`
- `flutter_app/lib/screens/*`

## Datenfluss
UI Screen → `ApiService` → `/api/mobile/*` → JSON → State/UI Rendering.

## Wichtige Regeln
- API-Domain ist hart auf `https://app.therapano.de` gesetzt.
- Login darf keine Domain-Auswahl mehr einführen.
- Flutter muss Web-Fachlogik spiegeln.

## Risiken
- Clientseitige Sonderpfade erzeugen Backend-Inkonsistenz.
- Nicht versionierte API-Feldänderungen führen zu Laufzeitfehlern.

## TODOs
- Contract-Tests zwischen `API_REFERENCE.md` und `ApiService` ergänzen.

## Verlinkungen
- [[02-api/mobile-api]]
- [[00-start/CRITICAL-RULES]]
- [[05-portal/owner-portal]]
