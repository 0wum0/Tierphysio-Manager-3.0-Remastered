# Windsurf Prompt: Cronjobs aus Praxis-App entfernen und in SaaS zentralisieren

Kopiere den folgenden Prompt 1:1 in Windsurf:

```text
Du arbeitest in einem Monorepo mit zwei Bereichen:
- Praxis-App (app.therapano.de): Root-Verzeichnis
- SaaS-Platform (Admin): Unterordner `saas-platform/`

## Ziel (verbindlich)
Alle praxisbezogenen Cronjobs dürfen in der Praxis-App **nicht mehr sichtbar und nicht mehr administrierbar** sein. 
Die komplette Cronjob-Verwaltung für Praxen soll in der SaaS-Platform stattfinden, damit ich die Jobs ausschließlich über mein Hosting-Panel ausführen kann.

Wichtig: Es geht um die **UI/Verwaltung/Anzeige** in der Praxis-App. Die eigentlichen ausführbaren Endpunkte können bestehen bleiben, aber nur als technische Trigger-Ziele ohne Admin-Oberfläche in der Praxis-App.

---

## Reale Fundstellen im Code (müssen berücksichtigt werden)
Praxis-App zeigt Cronjobs aktuell u. a. hier:
- `templates/settings/index.twig` (Cronjob-Tab + Cron-URLs + Tokens + Hinweise)
- `templates/settings/cronjobs.twig` (eigene Cronjob-Übersicht)
- `storage/themes/smart-tierphysio/layout.twig` (Navigationseintrag `/admin/cronjobs`)
- `app/Controllers/CronAdminController.php` (Admin-Controller für Cronjobs)
- `app/Routes/web.php` (Routen `/admin/cronjobs*`)

Cron-Endpunkte in der Praxis-App (sollen weiterhin technisch erreichbar sein, aber nicht im Praxis-UI beworben):
- `/cron/geburtstag`
- `/kalender/cron/erinnerungen`
- `/google-kalender/cron`
- `/tcp/cron/erinnerungen`
- `/api/holiday-cron`

SaaS hat bereits Cron-nahe Admin-UI als Referenz:
- `saas-platform/templates/admin/payment-settings/index.twig`

---

## Umsetzungsauftrag (Schritt für Schritt)

### 1) Praxis-App bereinigen (UI/Navigation/Routen)
1. Entferne in der Praxis-App jede sichtbare Verlinkung auf Cronjob-Verwaltung:
   - Navigationseintrag zu `/admin/cronjobs` entfernen.
2. Entferne den Cronjob-Tab und alle Cronjob-Abschnitte aus `templates/settings/index.twig`:
   - keine Token-Felder, keine Cron-URLs, keine „copy command“-Snippets mehr.
3. Deaktiviere/entferne die Praxis-Adminseite `templates/settings/cronjobs.twig`.
4. Entferne die Admin-Routen `/admin/cronjobs`, `/admin/cronjobs/{key}/trigger`, `/admin/cronjobs/log` aus `app/Routes/web.php`.
5. Entferne oder stilllege `CronAdminController`, so dass er nicht mehr von der Praxis-App erreichbar ist.

### 2) SaaS-Platform als zentrale Cron-Verwaltung ausbauen
1. Erstelle in `saas-platform` eine neue Admin-Seite „Praxis Cronjobs“ (z. B. `/admin/praxis-cronjobs`), sichtbar nur für SaaS-Admins.
2. Diese Seite muss pro Cronjob enthalten:
   - Name/Zweck
   - Empfohlener Hosting-Panel-Ausführungsplan (cron expression)
   - Vollständiger Trigger-Endpoint (zur Praxis-App)
   - Hinweis auf erforderliches Token
   - „Copy“-Buttons für URL/Befehl
3. Zeige dort einen **kompletten Hosting-Panel-fähigen Befehl** je Job (z. B. curl/wget), damit ich ihn direkt in meinem Panel eintragen kann.
4. Lege die Konfiguration strukturiert ab (z. B. SaaS-Settings/DB), nicht hartcodiert in Twig.
5. Falls sinnvoll: Füge eine „Run now“-Testfunktion hinzu, die den Endpoint serverseitig anstößt und Ergebnis/loggt.

### 3) Sicherheit und Robustheit
1. Tokens niemals im Frontend unmaskiert für nicht berechtigte Rollen anzeigen.
2. Eingaben validieren (URL, Intervalle, Token-Länge).
3. Fehlerzustände sauber behandeln (Timeout, 401/403, 500) und im SaaS-UI verständlich anzeigen.
4. Keine Breaking Changes für bestehende Cron-Endpunkte in der Praxis-App.

### 4) Migration/Kompatibilität
1. Bestehende Cron-Tokens aus der Praxis-App sollen (wenn vorhanden) in die neue SaaS-Verwaltung übernommen oder referenziert werden.
2. Hinterlasse in der Praxis-App **keine sichtbaren Cronjob-Hinweise** mehr.
3. Dokumentiere klar, wie bestehende Hosting-Panel-Cronjobs auf die neuen SaaS-Vorgaben umgestellt werden.

---

## Akzeptanzkriterien (Definition of Done)
- In app.therapano.de existiert **kein** sichtbarer Menüpunkt/Tab/Seite „Cronjobs“ mehr.
- Praxis-Routen `/admin/cronjobs*` sind entfernt/deaktiviert.
- In der SaaS-Platform gibt es eine zentrale Admin-Seite für Praxis-Cronjobs mit kopierbaren Befehlen.
- Ich kann alle nötigen Cronjobs ausschließlich über mein Hosting-Panel konfigurieren, basierend auf der SaaS-Seite.
- Bestehende ausführende Praxis-Cron-Endpunkte funktionieren weiterhin.
- Es gibt eine kurze technische Doku + ggf. Migration Notes.

---

## Technische Lieferform (wichtig)
1. Setze die Änderungen direkt im Code um.
2. Gib danach eine strukturierte Änderungsliste aus:
   - geänderte Dateien
   - kurze Begründung pro Datei
3. Führe vorhandene Tests/Linter aus und zeige Ergebnisse.
4. Wenn Tests fehlen: mindestens Smoke-Check der relevanten Routen/Views dokumentieren.
5. Erstelle am Ende eine kompakte Rollout-Checkliste für Produktion.

Arbeite präzise, ohne unnötige Refactors außerhalb dieses Scopes.
```
