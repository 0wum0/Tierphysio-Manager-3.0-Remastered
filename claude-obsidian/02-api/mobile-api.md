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

## Feature-Gating für Mobile API

### Architektur (Stand Mai 2026)
`FeatureRouteMap` mappt `/api/mobile` → Feature-Key `mobile_api`.
Der Router injiziert `feature:mobile_api` Middleware automatisch für alle `/api/mobile/*`-Routen.

### Ausnahmen (kein Feature-Gate)
- `/api/mobile/login`  → `null` in FeatureRouteMap — Pre-Auth, Tenant unbekannt
- `/api/mobile/logout` → `null` in FeatureRouteMap — Pre-Auth, immer erreichbar

### Warum Login ausgenommen ist
Mobile API ist stateless. Beim Login existiert noch keine Session und kein Bearer-Token,
mit dem der Tenant-Prefix aufgelöst werden könnte. `FeatureGateService` ohne Prefix gibt
`mobile_api = false` zurück → `feature_disabled`. Login muss IMMER erreichbar sein.

### Plan-Anforderung
`mobile_api` = `required_plan: pro` (in `saas_feature_flags`).
Pro-, Ultra-, Praxis-Pläne enthalten `mobile_api` in `plans.features` (Migration 051).

### Self-Heal
- `mobile_api` ist in `TOP_TIER_AUTO_FEATURES` → Top-Tier-Tenants haben Auto-Heal
- `requireFeature()` gibt early return wenn `db.getPrefix() === ''` — Bearer-Token-Requests ohne früh aufgelösten Prefix werden nicht geblockt

### Bekannte Bugs
Siehe `claude-obsidian/10-bugs/known-bugs.md` → "Bug: Mobile App Login feature_disabled"

## TODOs
- Endpoint-Katalog in Teilbereiche splitten (Core, TCP, Mailbox, Portal-Admin).
- Fehlercodes standardisieren (z. B. `feature_disabled`).
- Bearer-Token-basierte Tenant-Prefix-Auflösung im Bootstrap ergänzen (für sauberes Feature-Gating nach Login).

## Verlinkungen
- [[04-flutter/flutter-app]]
- [[07-features/therapycare-ai]]
- [[08-billing/billing-and-stripe]]
