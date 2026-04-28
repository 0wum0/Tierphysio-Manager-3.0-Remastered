# Mobile API

## Beschreibung
Zentrale API für Flutter und mobile Clients unter `/api/mobile/*`.

## Zweck
Endpoint- und Stabilitätsreferenz ohne Breaking Changes.

## Relevante Dateien im Repo
- `app/Routes/web.php`
- `app/Controllers/MobileApiController.php`
- `flutter_app/API_REFERENCE.md`
- `flutter_app/lib/services/api_service.dart`

## Datenfluss
Flutter `ApiService` → `https://app.therapano.de/api/mobile/*` → `MobileApiController` → Repositories/Services → tenant-spezifische Tabellen.

## Wichtige Regeln
- Responses müssen rückwärtskompatibel bleiben.
- Auth via Bearer-Token.
- JSON-only Antworten (keine HTML-Leaks bei Fehlern).

## Risiken
- Route-Änderungen brechen Flutter sofort.
- Tenant-Auflösung im API-Controller ist sicherheitskritisch.

## TODOs
- Endpoint-Katalog in Teilbereiche splitten (Core, TCP, Mailbox, Portal-Admin).
- Fehlercodes standardisieren (z. B. `feature_disabled`).

## Verlinkungen
- [[04-flutter/flutter-app]]
- [[07-features/therapycare-ai]]
- [[08-billing/billing-and-stripe]]
