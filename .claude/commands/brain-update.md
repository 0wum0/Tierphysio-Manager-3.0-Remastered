Aktualisiere die claude-obsidian Wissensbasis nach einer Codeänderung. Führe folgende Schritte aus:

## Schritt 1 – Geänderte Dateien ermitteln

Führe aus:
```
git status --short
git diff --stat HEAD
git log --oneline -3
```

Analysiere welche **Bereiche** betroffen sind (z.B. "SaaS-Admin", "Cron-System", "Owner-Portal-Plugin", "Migrations", "Templates").

## Schritt 2 – Passende Notiz-Datei(en) bestimmen

Die Notizen liegen in `claude-obsidian/`. Struktur:

```
claude-obsidian/
  00-start/          ← Einstiegspunkt: Sprint-Status, offene Tasks, Stolpersteine
  architecture/      ← Architektur-Entscheidungen, Muster, Konventionen
  bugs/              ← Gefixte Bugs mit Root-Cause und Fix-Beschreibung
  features/          ← Neue Features: was wurde gebaut, warum so
  migrations/        ← Neue Migrations: welche Tabellen/Spalten wurden ergänzt
```

Ordne jede Änderung dem richtigen Ordner zu:
- Bugfix → `bugs/`
- Neues Feature → `features/`
- Neue Migration → `migrations/`
- Architektur-Entscheidung → `architecture/`
- Sprint-Status / offene Punkte → `00-start/`

## Schritt 3 – Notizen schreiben oder aktualisieren

Für jede relevante Kategorie: prüfe ob bereits eine passende `.md`-Datei existiert (z.B. `bugs/dispatcher-mysql-1103.md`) und aktualisiere sie — oder lege eine neue an.

**Format einer Notiz:**
```markdown
# [Titel]

**Datum:** YYYY-MM-DD  
**Branch:** [branch-name]  
**Commit:** [short hash]

## Was wurde geändert
[1–3 Sätze]

## Warum / Root Cause
[Nur bei Bugfixes: was war die Ursache]

## Wichtige Details / Stolpersteine
[Konventionen, Fallstricke, die man nicht vergessen darf]
```

Halte jede Notiz unter 150 Wörter. Kein Copy-Paste von Code — nur das konzeptuelle Verständnis.

## Schritt 4 – 00-start aktualisieren

Aktualisiere `claude-obsidian/00-start/sprint-status.md` mit:
- Was heute fertig wurde (1 Zeile pro Task)
- Was noch offen ist
- Welche PRs erstellt wurden (URL + Titel)

## Schritt 5 – Neue Dateien committen

```
git add claude-obsidian/
git status
```

Frage den User ob die Notizen committed werden sollen, oder tue es direkt wenn der User `/brain-update commit` aufruft.
