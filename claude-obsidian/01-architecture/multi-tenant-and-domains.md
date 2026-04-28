# Multi-Tenant & Domains

## Beschreibung
Verbindliche Betriebsregeln für Tenant-Isolation und Domain-Topologie.

## Zweck
Schutz vor Datenvermischung und inkonsistenten Login-/Routing-Flows.

## Relevante Dateien im Repo
- `app/Core/Database.php`
- `app/Controllers/MobileApiController.php`
- `migrations/043_add_tenant_domain.sql`
- `saas-platform/migrations/043_add_tenant_domain.sql`
- `flutter_app/lib/services/api_service.dart`

## Datenfluss
- Tenant-Prefix wird auf DB-Ebene gesetzt (`setPrefix`, `prefix`).
- Mobile API löst Tenant-Kontext aus Token/E-Mail/SaaS-Tenant-Daten auf.
- Flutter nutzt fixe Ziel-Domain (`app.therapano.de`).

## Wichtige Regeln
- Prefix-Schema: `t_<tenant>_`.
- App/API Domain bleibt `https://app.therapano.de`.
- Portal-Domain bleibt `https://portal.therapano.de`.
- Keine Tenant-Subdomains als Ersatzmodell.

## Risiken
- Prefix-Fehler -> falsche Mandantendaten.
- Domain-Abweichungen -> Login/API-Ausfälle.

## TODOs
- Dokumentierte Tenant-Discovery-Flows (Web vs Mobile) vergleichen.
- Testfälle für Mehrtenant-Kollisionen als Checkliste ergänzen.

## Verlinkungen
- [[00-start/CRITICAL-RULES]]
- [[02-api/mobile-api]]
- [[06-saas/tenant-provisioning]]
