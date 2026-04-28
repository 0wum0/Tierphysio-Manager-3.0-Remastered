# CRITICAL RULES (Pflichtdatei)

## Beschreibung
Höchste Priorität für alle AI-Agenten im Repository.

## Zweck
Systemgrenzen schützen: Domain, Tenant-Isolation, API-Stabilität, Flutter/Web-Konsistenz.

## Relevante Dateien im Repo
- `flutter_app/lib/services/api_service.dart`
- `app/Core/Database.php`
- `app/Controllers/MobileApiController.php`
- `app/Routes/web.php`
- `saas-platform/app/Routes/web.php`
- `docs/windsurf_prompt_cron_migration.md`

## Datenfluss
- Flutter → `https://app.therapano.de/api/mobile/*`
- Owner/Portal-Logik → Plugin/Portal-Routen
- SaaS Admin/License/Billing → `saas-platform`

## Wichtige Regeln
- Domain-, Tenant- und API-Regeln sind nicht verhandelbar.
- Flutter/Web-Parität fachlich sicherstellen.
- Debugging-Reihenfolge einhalten.

## Domain-Regeln (fix)
- App + API Domain: `https://app.therapano.de`
- Besitzerportal Domain: `https://portal.therapano.de`

**NIEMALS:**
- Domains ändern.
- Tenant-spezifische Subdomains einführen.
- Domain-Auswahl im Login einbauen.

## Tenant-Regeln (fix)
- Multi-Tenant Prefix ist `t_<tenant>_`.
- Keine globalen Praxisdaten vermischen.
- Tenant-Prefix vor DB-Operationen korrekt setzen.

## API-Regeln (fix)
- API-Responses niemals breaking ändern.
- Bestehende Endpoint-Pfade nicht stillschweigend ändern.
- Mobile API bleibt rückwärtskompatibel.

## Flutter-Regeln (fix)
- Flutter muss Web-Verhalten spiegeln (fachlich, nicht Pixel-1:1).
- Backend ist Source of Truth.
- Keine clientseitige Sonderlogik, die Web domänenseitig widerspricht.

## Debugging-Regeln
1. Bei HTTP 500 zuerst Backend-Logik und Logs prüfen.
2. Danach Route/Controller-Mapping prüfen.
3. Danach Payload/Headers (Auth, Content-Type, X-Requested-With) prüfen.
4. Erst zuletzt Flutter/UI verdächtigen.

## NIEMALS-TUN Liste
- Auth-/Tenant-/Domain-Logik ohne expliziten Auftrag ändern.
- API-Schema brechen (Felder entfernen/umbenennen ohne Migration).
- DB-Schema blind ändern.
- Produktiv-Cron-Tokens hardcoden.
- `vendor/` oder `dist/` editieren.
- Widersprüchliche Architekturannahmen dokumentieren.

## Risiken
- Kleinste Domain/Tenant-Fehler verursachen Datenlecks.
- API-Breaks blockieren Flutter und Portal gleichzeitig.

## TODOs
- Endgültige Produktion-Domainbelegung regelmäßig gegen Deployment-Config verifizieren.
- Fehlerklassifizierung für Mobile API standardisieren.

## Verlinkungen
- [[01-architecture/multi-tenant-and-domains]]
- [[02-api/mobile-api]]
- [[04-flutter/flutter-app]]
- [[15-agent-rules/update-brain]]
