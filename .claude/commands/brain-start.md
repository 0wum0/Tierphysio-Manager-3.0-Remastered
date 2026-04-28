Lade den vollständigen Projektkontext für diese Session. Führe folgende Schritte **in dieser Reihenfolge** aus:

1. Lies `/home/user/Tierphysio-Manager-3.0-Remastered/CLAUDE.md` vollständig.

2. Prüfe ob Dateien in `claude-obsidian/00-start/` existieren:
   - Falls ja: lies **alle** Dateien dort und verarbeite sie als Kontext.
   - Falls nein: notiere das kurz.

3. Lies den aktuellen Branch und die letzten 5 Commits:
   ```
   git branch --show-current
   git log --oneline -5
   git status --short
   ```

4. Gib danach eine kompakte Zusammenfassung aus (kein Markdown-Overhead, direkt zum Punkt):

   **Architektur** (2–3 Sätze aus CLAUDE.md)
   
   **Aktueller Branch & offene Änderungen** (was ist uncommitted, was ist ahead of main)
   
   **Aktiver Sprint / offene Tasks** (aus claude-obsidian/00-start falls vorhanden, sonst "keine Notizen gefunden")
   
   **Bekannte Stolpersteine** (kritische Konventionen die beachtet werden müssen — z.B. &token= vs ?token=, prefix-System, self-healing-Pattern)

Halte die Zusammenfassung unter 300 Wörter. Sie soll als Warm-up für eine neue Claude-Session dienen.
