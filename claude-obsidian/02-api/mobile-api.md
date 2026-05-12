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

## Feature-Gating (mobile_api)

**Wichtige Architektur-Regel (Mai 2026):**

Mobile API Requests sind stateless (kein PHP-Session). Das bedeutet:
- Beim Bootstrap ist `db->getPrefix() = ''` → `FeatureGateService` hat keinen Tenant-Kontext
- `FeatureMiddleware` muss für prefixlose Bearer-token-Requests bypassen
- Die korrekte `mobile_api`-Prüfung erfolgt **inline in `requireAuth()` und `login()`** nach Prefix-Auflösung

**FeatureMiddleware Bypass-Bedingung:**
```php
if ($this->db->getPrefix() === ''
    && isset($_SERVER['HTTP_AUTHORIZATION'])
    && stripos($_SERVER['HTTP_AUTHORIZATION'], 'Bearer ') === 0
) {
    $next(); return; // Controller macht Gate-Check selbst
}
```

**Inline Gate-Check (in requireAuth() + login()):**
```php
$gate = Application::getInstance()->getContainer()->get(FeatureGateService::class);
if (!$gate->isEnabled('mobile_api')) {
    $this->error('feature_disabled', 403);
}
```

Zum Zeitpunkt des Checks ist `db->getPrefix()` auf den Tenant gesetzt → Gate kann korrekt aus Cache/SaaS lesen.

**Tenants ohne mobile_api:** erhalten 403 `feature_disabled` beim Login und bei allen Requests.  
**Tenants mit mobile_api:** werden korrekt durchgelassen.

## TODOs
- Endpoint-Katalog in Teilbereiche splitten (Core, TCP, Mailbox, Portal-Admin).

## Verlinkungen
- [[04-flutter/flutter-app]]
- [[07-features/therapycare-ai]]
- [[08-billing/billing-and-stripe]]
