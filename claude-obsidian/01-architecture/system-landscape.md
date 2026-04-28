# System Landscape

## Beschreibung
Architekturüberblick über Praxis-App, SaaS-Plattform, Plugins, Portal und Flutter Clients.

## Zweck
Schnelle Zuordnung: Wo gehört eine Änderung hin?

## Relevante Dateien im Repo
- `app/Routes/web.php`
- `app/Core/Database.php`
- `plugins/*`
- `saas-platform/app/Routes/web.php`
- `flutter_app/lib/core/router.dart`

## Datenfluss
1. Benutzer nutzt Web (Praxis-App) oder Flutter.
2. Beide laufen gegen `app.therapano.de` (Web + Mobile API).
3. SaaS Admin steuert Tenants/Features/Lizenzen in `saas-platform`.
4. Plugins erweitern Praxis und Portal fachlich.

## Wichtige Regeln
- Praxis-App und SaaS-App sind getrennte Kontexte.
- Plugin-Funktionalität immer in Kontext der Feature-Flags betrachten.
- Keine tenantübergreifende Annahme treffen.

## Risiken
- Vermischung von SaaS- und Praxis-Verantwortlichkeiten.
- Falsch platzierte Änderungen in falscher App.

## TODOs
- C4-ähnliche Diagrammdatei hinzufügen.
- Request-Lifecycle (Middleware → Controller → Repo) dokumentieren.

## Verlinkungen
- [[01-architecture/multi-tenant-and-domains]]
- [[06-saas/saas-platform-overview]]
- [[05-portal/owner-portal]]
