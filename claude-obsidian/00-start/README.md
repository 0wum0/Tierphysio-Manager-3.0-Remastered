# TheraPano Brain – Start

## Beschreibung
Zentraler Einstieg in das projektweite, agentenübergreifende Gedächtnis für Claude, Codex, Cursor und Windsurf.

## Zweck
- Einheitliche Orientierung vor jeder Änderung.
- Reduktion von Fehlannahmen durch verbindliche Referenzen.
- Stabiler Übergabepunkt zwischen Agenten.

## Relevante Dateien im Repo
- `AGENTS.md`
- `app/Routes/web.php`
- `saas-platform/app/Routes/web.php`
- `flutter_app/lib/services/api_service.dart`
- `docs/windsurf_prompt_cron_migration.md`

## Datenfluss
1. Agent startet hier.
2. Liest [[00-start/CRITICAL-RULES]].
3. Navigiert in Fachbereich (z. B. [[02-api/mobile-api]], [[06-saas/saas-platform-overview]]).
4. Führt Änderung aus.
5. Aktualisiert Brain nach [[15-agent-rules/update-brain]].

## Wichtige Regeln
- Dieses Brain darf nur in `/claude-obsidian` verändert werden.
- Fakten mit Quellenpfad im Repo verankern.
- Unbekannte Punkte als TODO markieren, nicht raten.

## Risiken
- Veraltete Doku führt zu falschen Refactors.
- Unmarkierte Annahmen erzeugen widersprüchliche Agent-Änderungen.

## TODOs
- Mapping pro Controller vertiefen.
- API-Beispiele mit echten Response-Feldern ergänzen.

## Verlinkungen
- [[00-start/CRITICAL-RULES]]
- [[01-architecture/system-landscape]]
- [[14-file-map/file-index]]
- [[15-agent-rules/update-brain]]
