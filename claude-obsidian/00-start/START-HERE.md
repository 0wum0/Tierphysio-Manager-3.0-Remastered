# START HERE – TheraPano Brain Einstieg

## Was ist dieses Brain?
Das `claude-obsidian/`-Verzeichnis ist das persistente Projektgedächtnis für alle AI-Agenten.
Jede Änderung am Code muss durch eine Aktualisierung hier gespiegelt werden.

## Pflicht-Lesereihenfolge (vor jeder Arbeit)

1. **[[00-start/CRITICAL-RULES]]** — Domain, Tenant, API-Grenzen (NIEMALS-Liste)
2. **[[01-architecture/system-landscape]]** — Wo gehört eine Änderung hin?
3. **[[01-architecture/multi-tenant-and-domains]]** — Tenant-Prefix-Regeln
4. **[[01-architecture/domains]]** — Domain-Topologie
5. **[[01-architecture/tenant-system]]** — Tenant-Isolation im Detail
6. **[[15-agent-rules/update-brain]]** — Brain nach Änderung aktualisieren
7. **[[15-agent-rules/git-pr-rules]]** — Git-Branch und PR-Regeln

## Fachbereich-Navigation

| Bereich | Pfad |
|---|---|
| Mobile API | `02-api/` |
| Web-App (Twig/PHP) | `03-web/` |
| Flutter App | `04-flutter/` |
| Besitzer-Portal | `05-portal/` |
| SaaS-Plattform | `06-saas/` |
| Features | `07-features/` |
| Billing | `08-billing/` |
| Cron & Mail | `09-cron-mail/` |
| Bugs & Fixes | `10-bugs/` |
| Entscheidungen | `11-decisions/` |
| Roadmap | `12-roadmap/` |

## Drei Haupt-Apps

| App | Root | Namespace | Zweck |
|---|---|---|---|
| Praxis-App | `/app/` + `/templates/` | `App\` | Tierphysio-Praxis täglich |
| SaaS-Plattform | `/saas-platform/` | `Saas\` | Admin: Tenants, Abos, Lizenzen |
| Flutter-App | `/flutter_app/` | — | Android + Windows Client |

## Wichtigste Konventionen auf einen Blick
- PHP 8.3, `declare(strict_types=1)` überall
- Tenant-Prefix: `t_{id}_tablename`
- API-Endpunkte: `/api/mobile/*` (Bearer-Token)
- Kein roher SQL in Controllern — Repository-Pattern
- `vendor/` und `dist/` sind read-only

## TODOs
- sprint-status.md nach jedem Sprint aktualisieren

## Verlinkungen
- [[00-start/CRITICAL-RULES]]
- [[15-agent-rules/update-brain]]
- [[15-agent-rules/git-pr-rules]]
- [[14-file-map/file-index]]
