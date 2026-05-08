# Domains (fix)

## Beschreibung
Verbindliche Domain-Topologie für TheraPano. Darf unter keinen Umständen geändert werden.

## Relevante Dateien im Repo
- `app/Routes/web.php`
- `saas-platform/app/Routes/web.php`
- `flutter_app/lib/services/api_service.dart`
- `flutter_app/lib/core/router.dart`

## Domain-Zuweisung

| App | Domain | Zweck |
|---|---|---|
| Praxis-App + API | `https://app.therapano.de` | Praxis-Login, Web-UI, REST-API |
| Besitzer-Portal | `https://portal.therapano.de` | Tierbesitzer-Zugang |
| SaaS-Admin | `https://app.therapano.de/admin` | Betreiber-Verwaltung |
| Mobile API | `https://app.therapano.de/api/mobile/*` | Flutter-Client |

## Absolute Verbote

- **Keine** Tenant-spezifischen Subdomains (z. B. `praxis-x.therapano.de`)
- **Keine** Domain-Auswahl im Flutter-Login
- **Keine** Änderung der Domain-Konstanten ohne expliziten Auftrag
- **Keine** neuen Domains ohne Absprache mit Betreiber

## Warum keine Subdomains?
- Zertifikat-Komplexität und Wildcard-Pflege
- Tenant-Discovery läuft über DB-Präfixe, nicht DNS
- Flutter-App hat eine feste Backend-URL (kein DNS-Lookup pro Tenant)

## Risiken
- Domain-Abweichungen → CORS-Fehler, Cookie-Isolation, Auth-Loops
- Subdomains → Datenleck-Risiko durch falsche Tenant-Zuordnung

## TODOs
- SSL-Zertifikat-Erneuerungsdatum dokumentieren

## Verlinkungen
- [[00-start/CRITICAL-RULES]]
- [[01-architecture/multi-tenant-and-domains]]
- [[01-architecture/tenant-system]]
