# Agent Rules (Claude, Codex, Cursor, Windsurf)

## Beschreibung
Gemeinsamer Regelkatalog für alle AI-Agents.

## Zweck
Konsistente Änderungen ohne Breaking Changes oder Gedächtnisverlust.

## Relevante Dateien im Repo
- `AGENTS.md`
- `claude-obsidian/00-start/CRITICAL-RULES.md`
- `claude-obsidian/15-agent-rules/update-brain.md`

## Datenfluss
Task erhalten → Brain lesen → Änderung umsetzen → Brain aktualisieren → committen.

## Wichtige Regeln
- Keine Breaking Changes an API/Domain/Auth/Tenant.
- Brain immer zuerst nutzen.
- Brain nach jeder Änderung verpflichtend aktualisieren.
- Keine Annahmen ohne Quellbezug.

## Agent-spezifisch
- **Claude**: vor Ausführung immer [[00-start/CRITICAL-RULES]] prüfen.
- **Codex**: nach Commit zwingend Brain-Diff prüfen.
- **Cursor**: keine Schnellfixes ohne Doku-Update.
- **Windsurf**: Workflows nur mit Brain-Referenz starten.

## Risiken
- Unterschiedliche Agent-Standards führen zu Architekturdrift.

## TODOs
- Qualitäts-Gates pro Agentenmodus ergänzen.

## Verlinkungen
- [[15-agent-rules/update-brain]]
- [[00-start/CRITICAL-RULES]]
