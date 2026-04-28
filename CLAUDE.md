# Claude Code Projektregeln für TheraPano

## Pflichtkontext
Vor jeder Arbeit muss Claude zuerst lesen:

- claude-obsidian/00-start/START-HERE.md
- claude-obsidian/00-start/CRITICAL-RULES.md
- claude-obsidian/01-architecture/domains.md
- claude-obsidian/01-architecture/tenant-system.md
- claude-obsidian/15-agent-rules/update-brain.md
- claude-obsidian/15-agent-rules/git-pr-rules.md

## Feste Regeln
- Web/App/API: https://app.therapano.de
- Besitzerportal: https://portal.therapano.de
- Keine Tenant-Subdomains
- Keine Domain-Auswahl im Flutter Login
- Tenant-Trennung über DB-Präfixe
- Web-App ist Referenz
- API Responses nicht brechen
- Bestehende Funktionen nicht kaputt machen

## Nach jeder Änderung
Claude muss prüfen, ob /claude-obsidian aktualisiert werden muss.

Wenn Code, Architektur, API, Bugfix, Feature, Tenant-Logik, Billing, Portal, Flutter oder Roadmap betroffen sind:
- passende Markdown-Datei unter /claude-obsidian aktualisieren
- TODOs anpassen
- neue Erkenntnisse dokumentieren
- danach git status prüfen
- committen
- Pull Request erstellen, wenn möglich
