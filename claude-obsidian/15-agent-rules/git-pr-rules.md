# Git & PR Regeln

## Beschreibung
Verbindliche Git-Workflow-Regeln für alle Agents und Entwickler in diesem Repository.

## Relevante Dateien im Repo
- `.windsurfrules`
- `CLAUDE.md`
- `AGENTS.md`

## Branch-Regeln

| Typ | Namensschema | Beispiel |
|---|---|---|
| Feature | `feature/beschreibung` | `feature/dogschool-invoicing` |
| AI-Agent | `claude/task-name-xxxx` | `claude/therapano-sprint-a-qaLiQ` |
| Bugfix | `fix/beschreibung` | `fix/calendar-save-500` |
| Chore/Docs | `chore/beschreibung` | `chore/windsurfrules-brain-refactor` |

## Absolute Verbote

- **Nie** direkt auf `main` pushen
- **Nie** `main` mergen ohne PR-Review
- **Nie** Commits ohne beschreibende Message
- **Nie** `vendor/` oder `dist/` commiten

## Commit-Format

```
type(scope): Kurzbeschreibung auf Deutsch

Optionaler Body mit mehr Details.
```

**Types:**
- `feat` — neues Feature
- `fix` — Bugfix
- `docs` — Dokumentation
- `chore` — Tooling, Config, Cleanup
- `refactor` — Code-Umstrukturierung ohne Feature-Change
- `test` — Tests

**Scopes** (Beispiele): `api`, `flutter`, `saas`, `portal`, `calendar`, `billing`, `obsidian`, `migration`

## PR-Pflichten

1. Branch von `main` ableiten
2. Änderungen committen (mindestens 1 Commit)
3. Branch pushen: `git push -u origin branch-name`
4. PR auf GitHub erstellen: `gh pr create --title "..." --body "..." --base main`
5. PR-Link im Abschlussbericht nennen

## Agent-Abschlussbericht (Pflicht)

Nach jeder Arbeitssession:
```
Branch: chore/example-branch
Commit: abc1234 – "chore(brain): Fehlende Dateien erstellt"
PR: https://github.com/0wum0/Tierphysio-Manager-3.0-Remastered/pull/XX
```

## Brain-Update vor Commit

Kein Commit ohne vorherigen Brain-Abgleich (siehe [[15-agent-rules/update-brain]]).

## TODOs
- pre-commit Hook ergänzen, der Brain-Check erzwingt

## Verlinkungen
- [[15-agent-rules/update-brain]]
- [[15-agent-rules/agents]]
- [[00-start/CRITICAL-RULES]]
