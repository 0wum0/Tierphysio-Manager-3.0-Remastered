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

## Feature-Gating (mobile_api) — Finale Architektur (Mai 2026)

**HARTE REGEL:** `/api/mobile` steht in `FeatureRouteMap` als `null` → kein Auto-Gate durch Router.

**Warum:** Mobile API Requests sind stateless (kein PHP-Session). Beim Bootstrap ist `db->getPrefix() = ''`.  
Jede Gate-Prüfung durch `FeatureMiddleware` zu diesem Zeitpunkt würde immer fehlschlagen  
(Cache leer, kein Tenant-Kontext). Das gilt insbesondere für `/login` — dieser Endpoint nutzt  
`postPublic()` (kein Authorization-Header), so dass ein Header-basierter Bypass hier nie greifen kann.

**Die einzig korrekte Stelle für den `mobile_api` Gate-Check** ist im `MobileApiController`  
selbst, **nach** der Tenant-Prefix-Auflösung:

```php
// In login() nach prefix-Auflösung + Credential-Check:
$gate = Application::getInstance()->getContainer()->get(FeatureGateService::class);
if (!$gate->isEnabled('mobile_api')) {
    $this->error('feature_disabled', 403);
}

// In requireAuth() nach prefix-Auflösung + User-Verifikation:
// (identisches Muster)
```

Zu diesem Zeitpunkt ist `db->getPrefix()` auf den Tenant gesetzt → Gate liest korrekten Cache/SaaS.

**Tenants ohne mobile_api:** 403 `feature_disabled` beim Login und bei allen Requests.  
**Tenants mit mobile_api (pro/ultra/praxis):** werden korrekt durchgelassen.

### Android vs Windows (Bug-Historie)
- Windows schien zu funktionieren weil ein **alter gespeicherter Token** vorhanden war → Bearer-Header → früherer Bypass griff (für alle Requests außer Login)
- Android (Frischinstall/kein Token) → `/login` ohne Bearer → kein Bypass → feature_disabled
- Finaler Fix: `FeatureRouteMap` `/api/mobile => null`, kein Bypass-Workaround nötig

## TODOs
- Endpoint-Katalog in Teilbereiche splitten (Core, TCP, Mailbox, Portal-Admin).

## Verlinkungen
- [[04-flutter/flutter-app]]
- [[07-features/therapycare-ai]]
- [[08-billing/billing-and-stripe]]
