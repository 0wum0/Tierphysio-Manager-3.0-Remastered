# Claude Obsidian Brain

## Beschreibung
Zentraler Wissensspeicher für Claude, Codex, Cursor und Windsurf.

## Zweck
Einheitliche, dauerhafte Projektdokumentation über alle Subsysteme hinweg.

## Relevante Dateien im Repo
- `claude-obsidian/**`
- `AGENTS.md`
- `app/Routes/web.php`
- `saas-platform/app/Routes/web.php`
- `flutter_app/lib/services/api_service.dart`

## Datenfluss
Agent startet hier → wechselt in Bereichsdokument → validiert mit Quell-Dateien → führt Änderung aus → aktualisiert Brain.

## Wichtige Regeln
- Brain immer vor Codeänderungen lesen.
- Brain nach Codeänderungen verpflichtend aktualisieren.
- Keine widersprüchlichen Aussagen zwischen Bereichsdokumenten.

## Risiken
- Veraltete Zentraleinstiegsseite lenkt Agenten in falsche Bereiche.

## TODOs
- Änderungs-Historie pro Woche ergänzen.

## Verlinkungen
- [[00-start/README]]
- [[00-start/CRITICAL-RULES]]
- [[01-architecture/system-landscape]]
- [[02-api/mobile-api]]
- [[03-web/web-app]]
- [[04-flutter/flutter-app]]
- [[05-portal/owner-portal]]
- [[06-saas/saas-platform-overview]]
- [[07-features/README]]
- [[08-billing/billing-and-stripe]]
- [[09-cron-mail/cron-and-mail]]
- [[10-bugs/known-bugs]]
- [[11-decisions/decision-log]]
- [[12-roadmap/roadmap]]
- [[13-prompts/agent-prompts]]
- [[14-file-map/file-index]]
- [[15-agent-rules/agents]]
- [[15-agent-rules/update-brain]]
