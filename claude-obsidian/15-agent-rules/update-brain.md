# Update Brain Pflichtprozess (nicht optional)

## Beschreibung
Verbindlicher Ablauf, der nach **jeder Codeänderung** ausgeführt werden muss.

## Zweck
Dauerhaftes, korrektes Projektgedächtnis ohne Wissensverlust.

## Relevante Dateien im Repo
- Alle Dateien unter `claude-obsidian/`
- Geänderte Quell-Dateien im jeweiligen Commit

## Datenfluss
Code-Änderung → Klassifikation → passende Brain-Datei aktualisieren → Konsistenzcheck.

## Pflicht-Checkliste für jeden Agent
1. Prüfen:
   - Wurde Code geändert?
   - Wurde ein Feature ergänzt?
   - Wurde ein Bug gefixt?
   - Wurde eine Entscheidung getroffen?
2. Wenn JA:
   - Passende Obsidian-Datei aktualisieren.
   - Neue Erkenntnisse ergänzen.
   - TODOs aktualisieren.
   - Veraltete Infos entfernen/markieren.
3. Wenn neues Feature:
   - Neue Datei unter `07-features/` erstellen.
4. Wenn Bugfix:
   - Unter `10-bugs/` dokumentieren.
5. Wenn Architekturänderung:
   - Unter `01-architecture/` aktualisieren.

## Wichtige Regeln
- Dieser Prozess ist **Pflicht**.
- Kein Commit ohne Brain-Abgleich.
- Keine widersprüchlichen Informationen in verschiedenen Brain-Dateien.

## Risiken
- Ausgelassene Brain-Updates führen zu falschen künftigen Änderungen.

## TODOs
- Optional: pre-commit Hook ergänzen, der Brain-Hinweis erzwingt.

## Verlinkungen
- [[15-agent-rules/agents]]
- [[10-bugs/known-bugs]]
- [[11-decisions/decision-log]]
- [[01-architecture/system-landscape]]
