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

## Workflow (seit 2026-07-01, auf Anweisung des Repo-Owners geändert)

**Direktes Commiten auf `main` ist erlaubt und Standard.** Feature-Branches + PR-Review sind für
diesen Workflow nicht mehr verpflichtend. Agents committen Änderungen direkt auf `main` und pushen
sie dorthin (`git push origin main`).

Änderung dokumentiert in `claude-obsidian/00-start/open-items.md` (2026-07-01, Teil 5) auf
expliziten Wunsch des Repo-Owners — vorher galt "nie direkt auf main pushen".

## Absolute Verbote (weiterhin gültig)

- **Nie** Commits ohne beschreibende Message
- **Nie** `vendor/` oder `dist/` commiten
- **Nie** Force-Push auf `main` ohne explizite, gesonderte Anweisung im jeweiligen Auftrag
- Bei destruktiven/schwer umkehrbaren Aktionen (History umschreiben, Branches löschen) weiterhin vorher nachfragen

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

## Commit-Pflichten (ersetzt die frühere PR-Pflicht)

1. Auf `main` arbeiten (kein eigener Feature-Branch nötig)
2. Änderungen committen (mindestens 1 Commit, beschreibende Message)
3. Direkt pushen: `git push origin main`
4. Kein PR mehr nötig — Ausnahme: der Auftrag verlangt explizit einen PR (z.B. bei riskanten/
   experimentellen Änderungen, oder wenn eine andere Person das Review übernehmen soll)

## Agent-Abschlussbericht (Pflicht)

Nach jeder Arbeitssession:
```
Branch: main
Commit: abc1234 – "chore(brain): Fehlende Dateien erstellt"
```

## Brain-Update vor Commit

Kein Commit ohne vorherigen Brain-Abgleich (siehe [[15-agent-rules/update-brain]]).

## TODOs
- pre-commit Hook ergänzen, der Brain-Check erzwingt

## Verlinkungen
- [[15-agent-rules/update-brain]]
- [[15-agent-rules/agents]]
- [[00-start/CRITICAL-RULES]]
