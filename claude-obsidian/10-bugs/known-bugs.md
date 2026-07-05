# Known Bugs & Unsicherheiten

## Beschreibung
Sammelstelle für bekannte Probleme, Beobachtungen und offene Verifikationen.

## Zweck
Verhindert, dass bekannte Fehler als "neu" erneut analysiert werden.

## Relevante Dateien im Repo
- `docs/windsurf_prompt_cron_migration.md`
- `app/Controllers/MobileApiController.php`
- `flutter_app/lib/services/api_service.dart`

## Datenfluss
Bug gefunden → in Datei dokumentieren → Fix referenzieren → Status aktualisieren.

## Wichtige Regeln
- Kein Bug-Eintrag ohne reproduzierbaren Kontext.
- Status verwenden: `open`, `investigating`, `fixed`, `needs-verify`.

## Risiken
- Veraltete Bug-Notizen erzeugen falsche Prioritäten.

## TODOs
- Diese Datei ist inzwischen mit konkreten Bugs befuellt. Neue offene Bugs oben oder nach Prioritaet ergaenzen; erledigte Eintraege auf `fixed` setzen.

---

## Bug: Besitzerportal — Dokumente erneut 404 (Multi-Anhang-JSON) + Hausaufgaben-Nav 403 (Juli 2026)
**Status:** `fixed`
**Auslöser:** Nutzer meldete nach dem vorherigen Dokumente-404-Fix erneut 404 bei Dokumenten
im Portal, zusätzlich 403 beim Klick auf "Hausaufgaben" im Header.
**Dateien:**
- `plugins/owner-portal/OwnerPortalController.php` (`petDetail()`, neu: `TimelineMediaService`)
- `plugins/owner-portal/templates/owner_pet_detail.twig` (Dokumente/Fotos-Tab)
- `plugins/owner-portal/templates/portal_layout.twig` (Hausaufgaben-Nav-Gating)
- `app/Controllers/BefundbogenController.php` (`requirePortalAuth()`)

### Bug A — Dokumente 404 (Regression zum vorherigen Fix, andere Ursache)
Der vorherige Fix (`petAttachment()`-Route, siehe Eintrag weiter unten) löste den 404 für
Timeline-Einträge mit GENAU EINEM Anhang. Einträge mit MEHREREN Anhängen speichern
`attachment` aber als **JSON-Array-String** (`PatientController::addTimelineEntry()`,
`attachment[]`-Mehrfach-Upload → `json_encode($savedFilenames)`), z.B.
`["feedback_a.jpg","feedback_b.pdf"]` — kein einzelner Dateiname. `owner_pet_detail.twig`
gab diesen String unverändert in den Anhang-Link, `petAttachment()` bekam per `basename()`
den kompletten JSON-String als "Dateinamen" → existiert nie auf der Platte → 404.

Die Praxis-Ansicht (`templates/patients/show.twig`) hat dieses Problem nie, weil sie
`entry.media` nutzt — eine bereits vorhandene, aber vom Portal nie genutzte Normalisierung
(`App\Services\TimelineMediaService::normalizeAttachmentToMedia()`), die sowohl
Einzeldatei-Strings als auch JSON-Arrays korrekt in eine Liste einzelner Dateinamen auflöst.

**Fix:** `TimelineMediaService` in `OwnerPortalController` eingebunden. `petDetail()`
reichert jeden `documents`/`photos`-Timeline-Eintrag um `files` an (normalisierte Liste via
`normalizeAttachmentToMedia($entry['attachment'], $petId)`). Templates iterieren jetzt über
`entry.files` (ein `<a>`/`<img>` pro Datei, mit Zähler `(1/2)` bei mehreren) und verlinken
`file.filename` — NICHT `entry.attachment` direkt und NICHT `file.web_url` (letzteres ist die
staff-authentifizierte `/patient-timeline/...`-Route der Praxis-App, für Portal-Besitzer
genauso ungeeignet wie der ursprüngliche `/uploads/`-Pfad).
Isoliert getestet: Einzeldatei-String, JSON-Array mit 2 Dateien, `null`/leer — alle korrekt
aufgelöst bzw. leere Liste ohne Fehler.

### Bug B — Hausaufgaben-Nav-Link liefert 403
`OwnerPortalController::homeworkOverview()` bricht mit `abort(403)` ab, wenn
`settings.portal_show_homework = '0'` (Feature vom Praxis-Admin deaktiviert). Der
Nav-Link "Hausaufgaben" in `portal_layout.twig` war aber **immer** sichtbar, unabhängig vom
Setting — anders als z.B. "Kurse" oder "Befundbögen", die korrekt gegen ein Flag gegated sind.
Das Flag `show_homework_nav` wurde zwar von mehreren Controllern korrekt berechnet
(`isHomeworkEnabled()`) und ans Template übergeben, aber im Layout nie tatsächlich
ausgewertet — reines Wiring-Versehen.

Zusätzlich: `BefundbogenController::requirePortalAuth()` (Befundbögen-Seiten) lieferte
`'show_homework_nav' => true` **hart codiert**, unabhängig vom echten Setting — hätte den
Link auf Befundbogen-Seiten immer gezeigt, selbst wenn deaktiviert.

**Fix:** `portal_layout.twig`: Nav-Link jetzt `{% if show_homework_nav ?? true %}`
(Fallback `true` für Seiten, die die Variable nicht setzen — ändert dort nichts am
bisherigen, immer-sichtbaren Verhalten). `BefundbogenController`: hartes `true` durch
echten Read aus `settings.portal_show_homework` ersetzt (identisches Muster wie
`OwnerPortalController::isHomeworkEnabled()`).

### Verifikation
- PHP-Lint sauber auf beiden geänderten PHP-Dateien.
- Twig-Parse-Check (echte Twig-Engine) für `owner_pet_detail.twig` + `portal_layout.twig`
  erfolgreich.
- `TimelineMediaService::normalizeAttachmentToMedia()` isoliert mit Einzeldatei/JSON-Array/
  leer getestet — durchweg korrektes Ergebnis.

---

## Bug: SaaS-Admin Feedback/Support — Bild-Anhang öffnet in neuem Tab + erfordert Praxis-Login (Juli 2026)
**Status:** `fixed`
**Auslöser:** Nutzer meldete: Öffnet man im SaaS-Admin einen Support-Vorgang (egal ob von
Tiertherapeut oder Hundetrainer/Hundeschule eingereicht) mit Bildanhang, öffnet sich das Bild
in einem neuen Tab UND verlangt einen Login als genau der Praxis/Hundeschule, die den Vorgang
eingereicht hat — der SaaS-Admin hat aber gar keine Session in der Praxis-App.
**Dateien:**
- `saas-platform/app/Controllers/FeedbackController.php` (neu: `attachment()`)
- `saas-platform/app/Routes/web.php` (neue Route)
- `saas-platform/templates/admin/feedback/show.twig` (Modal statt neuer Tab)
- `saas-platform/templates/admin/feedback/index.twig` (📎-Indikator in der Liste)

### Ursache
`show.twig` verlinkte den Anhang direkt auf
`https://app.therapano.de/{{ item.attachment_path }}` mit `target="_blank"`.
`attachment_path` hat die Form `storage/feedback/{filename}` — die Route dafür in der
**Praxis-App** (`app/Routes/web.php`, `/storage/feedback/{file}`) ist mit `['auth']`
geschützt, also Praxis-**Mitarbeiter**-Login (`user_id`-Session der Praxis-App) erforderlich.
`saas-platform` ist eine komplett separate Anwendung mit eigener Session (`Saas\Core\Session`,
eigener Login) — ein SaaS-Admin hat dort naturgemäß nie eine aktive Sitzung. Deshalb landete
der Klick auf der Login-Seite der Praxis-App, und der Admin musste sich tatsächlich mit den
Zugangsdaten genau des Tenants anmelden, der den Vorgang eingereicht hatte, um das Bild zu sehen.

### Fix
Neue authentifizierte Route direkt im SaaS-Admin: `GET /admin/feedback/{id}/anhang` →
`FeedbackController::attachment()`. `saas-platform` liegt als Sibling-Ordner im selben Repo/Server
wie die Praxis-App und kann daher **direkt über das Dateisystem** auf `storage/feedback/`
zugreifen — exakt das bereits etablierte Muster aus `TenantHealthService::checkStorage()`
(`dirname(__DIR__, 3) . '/storage'`). Kein HTTP-Sprung zur Praxis-App mehr nötig, keine
fremde Session-Anforderung. Datei wird mit `basename()` + `realpath()`-Containment-Check
sicher aufgelöst (Pfad-Traversal-Schutz, isoliert getestet: gültige Datei → 200,
`../secret/config.php` → 404, leerer Dateiname → 404).

`show.twig`: Anhang öffnet jetzt in einem Bootstrap-Modal direkt auf der Seite (Bild als
Thumbnail zum Vergrößern anklickbar, PDF im `<iframe>`) statt in einem neuen Tab. „In neuem
Tab öffnen" bleibt als optionaler Zusatz-Button im Modal-Footer erhalten (funktioniert jetzt
korrekt, weil same-origin + authentifiziert).

`index.twig`: 📎-Symbol vor der Nachricht in der Liste, wenn ein Anhang vorhanden ist —
Admin sieht auf einen Blick, welche Vorgänge ein Bild enthalten.

### Verifikation
- PHP-Lint sauber auf allen geänderten PHP-Dateien.
- Twig-Parse-Check (echte Twig-Engine aus vendor/) für beide Templates erfolgreich.
- Pfadauflösung + Containment-Logik isoliert mit echten Testdateien geprüft (gültige Datei,
  Traversal-Versuch, fehlende Datei, leerer Dateiname — alle korrekt behandelt).
- `split('.')|last|lower`-Ausdruck zur Bild/PDF-Unterscheidung mit echten Twig-Filtern
  gegen reale `attachment_path`-Werte getestet (.jpg/.PDF/.pdf → korrekt erkannt).

---

## Bug: Besitzerportal — Dokumente/Fotos in Tierdetailansicht liefern 404 (Juli 2026)
**Status:** `fixed`
**Dateien:**
- `plugins/owner-portal/templates/owner_pet_detail.twig` (Dokumente-Tab + Fotos-Tab)
- `plugins/owner-portal/OwnerPortalController.php` (neu: `petAttachment()`)
- `plugins/owner-portal/ServiceProvider.php` (neue Route)

### Symptom
Im Besitzerportal (`/portal/tiere/{id}`) lieferte das Öffnen eines Dokuments oder Fotos im
"Dokumente"/"Fotos"-Tab HTTP 404.

### Ursache
`owner_pet_detail.twig` verlinkte Timeline-Anhänge direkt auf `/uploads/{{ entry.attachment }}`
— einen öffentlichen Webroot-Pfad. Die Dateien liegen dort aber nie: Uploads laufen über
`PatientController::uploadAttachment()` und werden tenant-isoliert unter
`tenant_storage_path('patients/{id}/timeline/{filename}')` gespeichert (siehe
`storage/tenants/{prefix}/patients/{id}/timeline/`), nicht unter `public/uploads/`.
Der Link war daher strukturell nie erreichbar — kein Pfad-Tippfehler, sondern zwei
grundverschiedene Speicherorte, die nie verbunden wurden.

Die Praxis-Ansicht (`templates/patients/show.twig`) hat für dieselben Anhänge bereits eine
korrekte, authentifizierte Route: `/patienten/{id}/dokumente/{file}` →
`PatientController::downloadDocument()`. Diese Route verlangt aber Staff-Login
(`['auth']`-Middleware, `user_id`-Session) — für Besitzerportal-Nutzer (eigene
`owner_portal_user_id`-Session) nicht nutzbar, hätte nur Login-Redirect statt 404 ergeben.

### Fix
Neue Portal-eigene Route `GET /portal/tiere/{id}/anhang/{file}` →
`OwnerPortalController::petAttachment()`, nach demselben bereits etablierten Muster wie
`petPhoto()` (Profilbild-Auslieferung):
1. `requireOwnerAuth()` — Portal-Session-Pflicht.
2. `getPetByIdAndOwner()` — Ownership-Check: das Tier muss dem eingeloggten Besitzer gehören
   (verhindert, dass Besitzer A Anhänge von Besitzer B's Tier erraten/laden kann).
3. `basename($file)` — Path-Traversal-Schutz.
4. Kandidatenpfade `timeline/` → `docs/` → `patients/{id}/` (spiegelt
   `PatientController::downloadDocument()`-Fallback-Kette für Alt-Uploads).
5. Inline-Anzeige für Bild/Video/PDF, sonst Download — gleiche Logik wie `downloadDocument()`.

Templates auf die neue Route umgestellt (Dokumente-Download-Link, Fotos-Grid `<a>` + `<img src>`).

### Verifikation
- PHP-Lint sauber auf beiden geänderten PHP-Dateien.
- Routen- und Methodennamen konsistent mit bestehendem `petPhoto()`-Muster geprüft.
- Kein Zugriff auf fremde Tiere möglich (403 bei Ownership-Mismatch, wie bei `petPhoto()`).

---

## Bug: Toast-Benachrichtigung "Termin in Kürze" für bereits vergangene Termine (Juli 2026)
**Status:** `fixed`
**Auslöser:** Nutzer meldete: direkt nach Login erscheinen Toast-Benachrichtigungen für Termine,
die bereits in der Vergangenheit liegen. Es dürfen nur aktuelle/anstehende Termine angezeigt werden.
**Datei:** `app/Core/Database.php` (`connect()`, neu: `mysqlTimezoneOffset()`)

### Symptom
`NotificationController::index()` zeigt Termine der nächsten 60 Minuten als Toast
(`WHERE a.start_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 60 MINUTE)`). Diese Abfrage
ist auf den ersten Blick korrekt — trotzdem erschienen Toasts für Termine, die de facto
schon vorbei waren.

### Root Cause
PHP setzt global `date_default_timezone_set('Europe/Berlin')` (`app/Core/Application.php`).
Termin-`start_at`-Werte werden als naive Berlin-Wandzeit gespeichert (z.B. "14:00" meint
14:00 Uhr Berlin, ohne Zeitzonen-Info in der Spalte). Die PDO-Verbindung
(`Database::connect()`) setzte aber **nie** die MySQL-Session-Zeitzone — MySQLs `NOW()`
lief daher mit der Server-/Instanz-Standardzone (auf Shared-Hosting häufig UTC).

Ist die DB-Zeitzone gegenüber Berlin versetzt (UTC = −1h/−2h je nach Sommer-/Winterzeit),
driftet **jeder** `NOW()`-Vergleich gegen `start_at`. Konkret: MySQLs `NOW()` liegt der
echten Berliner Uhrzeit hinterher → das Fenster `[NOW(), NOW()+60min]` besteht aus
"zu alten" Werten → ein Termin, der real schon vor 1-2 Stunden stattfand, fällt noch in
dieses verzögerte Fenster und wird fälschlich als "in Kürze" angezeigt.

**Dieselbe Ursache betraf vermutlich auch die Termin-Erinnerungs-Inkonsistenzen aus dem
vorherigen Ticket** (`AppointmentRepository::findPendingReminders()` nutzt ebenfalls
`NOW()`-Vergleiche gegen `start_at`) — zusätzlich zu den dort bereits behobenen
Doppelversand-/Reset-Bugs.

### Fix
`Database::connect()` setzt nach jedem Verbindungsaufbau explizit
`SET time_zone = '+02:00'` (bzw. `+01:00` im Winter) — berechnet DST-sicher aus
`DateTimeZone('Europe/Berlin')->getOffset()` zum aktuellen Zeitpunkt. Numerischer Offset
statt benanntem `'Europe/Berlin'`, weil benannte Zeitzonen in MySQL geladene
`mysql.time_zone_name`-Tabellen voraussetzen, die auf Shared-Hosting oft fehlen.

**Root-Cause-Fix statt Symptom-Patch:** Da `Database` als Singleton pro Request injiziert
wird, korrigiert diese EINE Änderung automatisch ALLE `NOW()`-Vergleiche der gesamten
Anwendung — `NotificationController`, `AppointmentRepository::findPendingReminders()`,
`InvoiceRepository`-Fälligkeitsprüfungen, `CURRENT_TIMESTAMP`-Spalten (`created_at` etc.)
— ohne jede einzelne Stelle einzeln patchen zu müssen.

### Verifikation
- DST-Berechnung isoliert getestet: Winter (15.01.) → `+01:00`, Sommer (02.07.) → `+02:00`,
  Umstellungstag (29.03., 00:30 vs. 02:30) → korrekter Sprung `+01:00`→`+02:00`.
- PHP-Lint sauber.
- Kein lokaler MySQL-Server verfügbar für Live-Integrationstest — `SET time_zone` ist ein
  Standard-Session-Variable-Befehl ohne besondere Privilegien-Anforderung, sollte in
  Produktion vor dem nächsten Login-Test geprüft werden (Toast sollte für einen Termin,
  der vor >5 Minuten begann, nicht mehr erscheinen).

---

## Bug: Termin-Erinnerungen ohne Google-Sync — Flutter/Mobile-Termine falscher Default + kein Reset bei Verschiebung (Juli 2026)
**Status:** `fixed`
**Auslöser:** Support-Ticket zeigte Cron-Log-Zeile "Google Kalender Sync … skipped … reason=no_connection"
für Tenant `nella-delago-171164`; Nutzer wollte sichergestellt haben, dass normale (nicht
google-synchronisierte) Kalendertermine trotzdem korrekt und pünktlich erinnert werden.
**Dateien:**
- `app/Controllers/MobileApiController.php` (`appointmentCreate`, `waitlistSchedule`, `appointmentUpdate`)
- `saas-platform/cron/cron_runner.php` (Self-Heal-ALTER für `reminder_minutes`)
- `saas-platform/app/Controllers/PraxisCronController.php` + `saas-platform/templates/admin/cron-monitoring.twig`

### Architektur-Check (kein Bug)
`google_sync` und `calendar_reminders` sind vollständig unabhängige Cron-Jobs
(`CronController::dispatcher()`, Zeile ~292): jeder läuft in eigenem try/catch, mit eigenem
`isDue()`-Check pro `job_key` UND `tid`. `status=skipped/reason=no_connection` bei Google-Sync
ist für Tenants ohne Google-Verbindung erwartetes Verhalten (siehe bestehender Bug-Eintrag
"Google Sync no_connection als SUCCESS") und blockiert `calendar_reminders` NICHT.

### Gefundene echte Bugs (3)

**Bug 1 — Mobile-API/Flutter: falscher Erinnerungs-Default (60 statt 1440 Minuten).**
`MobileApiController::appointmentCreate()` und `::waitlistSchedule()` (INSERT) nutzten
`(int)($data['reminder_minutes'] ?? 60)` — 1 Stunde vorher. Der Web-Kalender
(`AppointmentRepository::create()`) nutzt `?? 1440` — 24 Stunden vorher, ebenso Migration
`002_update_default_reminder.sql`. Ein per Flutter-App/Mobile-API angelegter Termin ohne
explizit gesetztes `reminder_minutes` bekam dadurch nur 1h statt 24h Vorlauf — Verstoß gegen
die CRITICAL-RULES Flutter/Web-Paritätsregel. Erklärt plausibel frühere „keine 24h-Erinnerung"-
Fälle, wenn der Termin über die App angelegt wurde.
Fix: Default auf `1440` vereinheitlicht.

**Bug 2 — Mobile-API: `appointmentUpdate()` setzte `reminder_sent` bei Verschiebung nie zurück.**
Gleiches Muster wie der bereits gefixte Web-Kalender-Bug (siehe „Termin-Erinnerungen inkonsistent"
weiter unten), hier aber im Mobile-API-Pfad übersehen. Ein per App verschobener Termin, dessen
Erinnerung schon raus war, bekam keine neue Erinnerung für die neue Zeit.
Fix: `reminder_sent = IF(? IS NULL OR start_at <=> ?, reminder_sent, 0)` in der SET-Liste VOR
`start_at = COALESCE(...)` (MySQL wertet SET links-nach-rechts aus).

**Bug 3 — SaaS Self-Heal-Migration mit falschem Default.**
`saas-platform/cron/cron_runner.php` legte die Spalte `reminder_minutes` bei fehlender Spalte
mit `DEFAULT 60` an (Widerspruch zu Migration 002 / SaaS-Migration `UpdateCalendarAppointments.php`,
beide `1440`). Betrifft nur Tenant-DBs, bei denen dieser Self-Heal-Pfad die Spalte zuerst anlegt.
Fix: Default auf `1440` korrigiert.

### Zusätzlich: Monitoring-Dashboard konnte Tenant-Frage nicht beantworten
`/admin/cron-monitoring` zeigte "letzter Lauf je Job" GLOBAL über alle Tenants — bei vielen
Tenants sagt das nichts darüber aus, ob `calendar_reminders` für einen SPEZIFISCHEN Tenant
(z.B. `nella-delago-171164`) tatsächlich lief. Exakt die Situation aus dem Support-Ticket:
sichtbar war nur der globale Google-Sync-Eintrag, keine tenant-scoped Aussage zu Erinnerungen.
Fix: `?tid=`-Filter ergänzt (Controller + Formular im Template), `tid`-Spalte in der Log-Tabelle,
Klick auf Tenant-ID filtert direkt. Admins können jetzt gezielt prüfen: „lief
`calendar_reminders` für Tenant X, wann, mit welchem Ergebnis?"

### Verifikation
- PHP-Lint sauber auf allen geänderten Dateien.
- Dispatcher-Unabhängigkeit im Code verifiziert (separate try/catch + tid-gefilterter isDue-Check).
- `/admin/cron-monitoring?tid=nella-delago-171164` zeigt jetzt ausschließlich Logs dieses Tenants.

---

## Bug: Feedback-FAB überdeckte Chat-Send-Button — FIXED (Mai 2026)

### Symptom
Der Feedback-FAB (fixed bottom-right) überdeckte Senden-Buttons auf Seiten mit festem unterem Eingabebereich.

### Fix
CSS-Regel: `body[data-chat-bar="1"] #feedback-fab { bottom: 5rem; }`.
Seiten mit Chat-Eingabebereich setzen `data-chat-bar="1"` am `<body>`.
Status: `fixed` — FAB-Positionierungsregel in `claude-obsidian/07-features/feedback-system.md` dokumentiert.

---

## Bug: Portal-Admin Hausaufgaben — Seite nach Vorlagenauswahl nicht mehr klickbar — FIXED (Mai 2026)

### Symptom
Nach Klick auf eine Vorlage in `/portal-admin/Tiere/{id}/Hausaufgaben` blockierte die Seite vollständig.
Kein Element mehr klickbar. Erst nach Seitenreload war die Seite wieder bedienbar.

### Ursache (Root Cause)
Bootstrap 5 Modal Stacking Bug:
- `modal-create-plan` öffnet sich (Backdrop + `body.modal-open` gesetzt)
- `openTemplateSelector()` → `new bootstrap.Modal(el).show()` öffnet `modal-template-select` als Sub-Modal
- Problem 1: `new bootstrap.Modal(el)` erstellt eine neue Instanz ohne die vorherige freizugeben → Instanz-Leak
- Problem 2 (Hauptursache): Beim `.hide()` des Sub-Modals (`modal-template-select`) ruft Bootstrap intern `_resetAdjustments()` auf und entfernt `body.modal-open` sowie das `.modal-backdrop` — weil Bootstrap das hiding-Modal für das einzige offene Modal hält
- Ergebnis: `modal-create-plan` hat `show`-Klasse und `display:block`, aber kein Backdrop und kein `body.modal-open` mehr → unsichtbares Overlay blockiert alle Klicks

### Betroffene Dateien
- `plugins/owner-portal/templates/admin_homework.twig`
- `plugins/owner-portal/templates/admin_homework_edit.twig`

### Fix (Mai 2026)
1. `new bootstrap.Modal(el)` → `bootstrap.Modal.getOrCreateInstance(el)` überall (kein Instanz-Leak)
2. `hidden.bs.modal`-Listener auf `modal-template-select` und `modal-library-picker`:
   - Prüft ob `modal-create-plan` noch `.show` hat
   - Wenn ja: `body.modal-open` wiederherstellen + neues `.modal-backdrop.fade.show` einfügen
3. `cleanupStaleModalState()` Safety-Funktion: entfernt verwaiste Backdrops/`modal-open` NUR wenn kein Modal mehr `.show` hat
4. Gleiches Muster auf `admin_homework_edit.twig` für Konsistenz

### Status
`fixed` — Commit: fix: prevent homework template selection from blocking portal admin

---

## Bug: Flutter Android — `feature_disabled` 403 beim Login — FIXED (Mai 2026)

**Status:** `fixed`  
**Commits:** `fix: repair mobile api feature gate access` + `fix: align android mobile api login with windows app`

### Ursache Phase 1 (beide Plattformen)
`FeatureRouteMap` mappte `/api/mobile` → `mobile_api`. Router fügte `feature:mobile_api`-Middleware für alle `/api/mobile/*` Routen hinzu. Stateless Requests → prefix='' → Gate-Check schlug immer fehl → 403.

### Ursache Phase 2 (Android-spezifisch)
**Phase-1-Fix war unvollständig**: `FeatureMiddleware`-Bypass griff nur bei `Authorization: Bearer`-Header.  
**`/login` nutzt `postPublic()`** → sendet **keinen Authorization-Header**.

| Szenario | Authorization Header | Bypass greift? |
|---|---|---|
| Windows (gespeicherter Token) | Bearer {token} | ✅ Ja — alle Requests außer Login |
| Android Erst-Login | keiner | ❌ Nein → feature_disabled |
| Android nach Login (`/me`, etc.) | Bearer {token} | ✅ Ja |

Windows hatte nach dem Phase-1-Fix einen **gespeicherten alten Token** → Auto-Login mit Bearer-Header → Bypass griff → schien zu funktionieren.  
Android hatte **keinen Token** (Frischinstall oder Cache gelöscht) → `/login` ohne Bearer → Bypass greift nicht → feature_disabled.

### Finaler Fix (Phase 2)
**`FeatureRouteMap.php`**: `/api/mobile` → `null` (kein Auto-Gate mehr).  
Der `MobileApiController` hat eigene inline Gate-Checks in `login()` und `requireAuth()` nach Tenant-Prefix-Auflösung — das ist die einzig korrekte Stelle für diesen Check.

**`FeatureMiddleware.php`**: Bearer-Bypass-Workaround entfernt + `Database`-Dependency entfernt.

### ARCHITEKTUR-REGEL (dauerhaft gültig)
```
FeatureRouteMap: /api/mobile => null
                  ↓
MobileApiController::login()        → inline mobile_api check (nach prefix-Auflösung)
MobileApiController::requireAuth()  → inline mobile_api check (nach prefix-Auflösung)
```
Stateless API-Routes (Bearer-only) dürfen NICHT im FeatureRouteMap stehen.  
Gate-Check muss im Controller nach Tenant-Auflösung stattfinden.

### Geänderte Dateien
- `app/Services/FeatureRouteMap.php` — `/api/mobile` → `null`
- `app/Middleware/FeatureMiddleware.php` — Bearer-bypass entfernt, DB-Dep. entfernt
- `app/Controllers/MobileApiController.php` — inline Gate-Checks in `login()` + `requireAuth()`
- `saas-platform/migrations/067_repair_mobile_api_feature_gate.sql` — Self-Healing
- `flutter_app/lib/screens/login_screen.dart` — Version v1.2.0
- `flutter_app/pubspec.yaml` — msix_version 1.2.0.0

---

## Bug: Hundeschulen-Dashboard Paket-Verkauf-Modal erwartet JSON, Controller redirectet HTML

**Status:** `fixed`  
**Datum:** 2026-05-12  
**Fix:** `PackageController::sell()` liefert bei AJAX JSON; Dashboard bekommt aktiven Paketkatalog.

### Symptom
`templates/dogschool/dashboard/index.twig` registriert `#ds-form-sell-package` mit `dsSubmitModal()`.
Der JavaScript-Handler sendet `fetch()` mit `X-Requested-With: XMLHttpRequest` und ruft danach
`await res.json()` auf.

`app/Controllers/PackageController.php::sell()` validiert und verkauft zwar serverseitig, antwortet
aber immer mit Flash + Redirect nach `/pakete`. Bei AJAX entsteht dadurch eine HTML-Response, die
im Frontend nicht als JSON geparst werden kann.

### Betroffene Dateien
- `templates/dogschool/dashboard/index.twig`
- `app/Controllers/PackageController.php`

### Fix
- `PackageController::sell()` hat jetzt einen `isAjax()`-Branch:
  - Validierungsfehler: JSON `{success:false,error:"..."}` mit HTTP 422
  - Erfolg: JSON `{success:true,id,redirect:"/pakete"}`
  - Fehler bei `createBalance()`: JSON `{success:false,error:"Paket-Verkauf fehlgeschlagen."}`
- `DogschoolDashboardController::index()` laedt aktive Pakete via `PackageRepository::listActive()`.
- `templates/dogschool/dashboard/index.twig` befuellt das Paket-Select und zeigt einen Hinweis, wenn noch keine aktiven Pakete existieren.

### Verifikation
1. Dashboard `/hundeschule` oeffnen.
2. "Paket verkaufen" absenden.
3. Bei Fehler erscheint Alert im Modal, kein `res.json()`-Console-Fehler.
4. Bei Erfolg schliesst das Modal und navigiert/reloadet kontrolliert.

---

## Bug: Google Kalender Sync HTTP 200 aber keine Termine synchronisiert — Tierphysio Wenzel
**Status:** `fixed` (Mai 2026)
**Commit:** `fix: repair google calendar tenant sync processing`
**Betroffene Dateien:**
- `plugins/google-calendar-sync/GoogleCalendarController.php`
- `plugins/google-calendar-sync/GoogleSyncService.php`
- `app/Controllers/CronController.php`
- `migrations/054_cron_dispatcher_log_tid.sql` (neu)

**Root Causes (5 Bugs):**
1. `GoogleCalendarController::cron()` setzte KEINEN Tenant-Prefix → DB-Zugriffe auf falsche Tabellen → kein Google-Konto gefunden → Sync für niemanden
2. `pullFromGoogle()` prüfte `shouldSync()` nicht → Pull auch wenn `sync_enabled=0`
3. Cron-Antwort enthielt keine Tenant/Account/Zahlen-Infos → Monitor sah nur HTTP 200
4. `executeJob()` loggte nur HTTP-Code, keine Pull/Push-Zahlen
5. `cron_dispatcher_log` last_run-Check ohne `tid`-Filter → Tenant A's Ausführung ließ Tenant B überspringen (Cross-Tenant Scheduling Bug)

**Verifikation Tierphysio Wenzel:**
- `tid=tierphysio-wenzel` im Dispatcher-Aufruf vorhanden ✓
- Prefix `t_tierphysio_wenzel_` wird jetzt gesetzt ✓
- Google-Konto in `t_tierphysio_wenzel_google_calendar_connections` gesucht ✓
- JSON-Antwort enthält jetzt `tid`, `google_email`, `calendar_id`, `push`, `pull` ✓

---

## Bug: Produktiv-Cron 401/403/500 — fehlende Tenant-Prefix + fehlende Token-Self-Healing (Mai 2026)
**Status:** `fixed` (Mai 2026)
**Commit:** `fix: add self healing cron token and reminder recovery`
**Betroffene Dateien:**
- `plugins/owner-portal/OwnerPortalAdminController.php` — `cronSmartReminders()`
- `plugins/bulk-mail/HolidayController.php` — `cron()`
- `app/Services/BirthdayMailService.php` — `alreadySentThisYear()` / `markSent()`
- `app/Controllers/CronController.php` — `executeJob()` Status-Klassifizierung
- `saas-platform/migrations/064_selfhealing_cron_tokens.sql` (neu)

**Root Cause 1 — Smart Erinnerungen HTTP 401:**
`cronSmartReminders()` setzte keinen Tenant-Prefix vor `settings->get('portal_smart_reminder_token')`.
Token-Lookup gegen falschen Tenant → Token = null → self-heal erzeugt neuen Token in falschem Tenant
→ Dispatcher sendet alten Token → IMMER 401. Fix: `$db->setPrefix($prefix)` vor jedem DB-Zugriff.

**Root Cause 2 — Feiertagsgrüße HTTP 403:**
`HolidayController::cron()` hatte: `if ($expected === '' || $key !== $expected) → 403`.
Leerer `cron_secret` → sofort 403, kein Selbstheilungsmechanismus. Kein `setPrefix($tid)` vorhanden.
Fix: Self-Healing Pattern implementiert (Token fehlt → generieren → speichern → weiterausführen).

**Root Cause 3 — Geburtstagsmail HTTP 500:**
`BirthdayMailService::alreadySentThisYear()` direkte Abfrage auf `birthday_emails_sent` ohne
`tableExists()`-Check → MySQL „Table not found" → Exception → unkontrollierter 500.
Fix: `tableExists()` + `ensureBirthdayEmailsSentTable()` + try/catch in beiden Methoden.

**Root Cause 4 — Google Sync `no_connection` als SUCCESS:**
`CronController::executeJob()` klassifizierte HTTP 200 + `status=skipped/reason=no_connection`
als `success=true` → Cron-Monitor zeigte "OK" obwohl Google-Verbindung fehlt.
Fix: Neue Status-Logik: `skipped`-reasons → `finalStatus='skipped'`, `success=false`.

---

## Bug: „Die interaktive Anatomie konnte nicht geladen werden" nach SVG-Layer-Refactor (Commit `dd6ed9b`)
**Status:** `fixed` (Commit `e0a01e2` → `main` als `96bb587`)

### Symptom
Nach Umstellung von flachen `SILHOUETTES` auf ein layerbasiertes `ANATOMY`-System mit
`Proxy`-Wrapper + `escapeAttr` wurde die Warnung wieder angezeigt, obwohl kein offensichtlicher
Syntaxfehler vorlag (`node --check` clean).

### Ursache
Der ursprüngliche globale `try/catch` in `initAnatomy` (Datei: `public/assets/js/befund-anatomy.js`,
vorher Zeile 184–195) hat **jeden** Fehler eines beliebigen Render-Schrittes (Toolbar, Stage,
Legend, NRS, MarkerList, syncHidden) abgefangen und pauschal die Warnung eingeblendet.
Der eigentliche Fehler wurde nur als `console.error('[Befund Anatomy] Initialisierung fehlgeschlagen:', e)`
geloggt — ohne Step-Name, ohne Stack, ohne `error.name/.message`. Das machte die konkrete
Ursache praktisch unsichtbar und degradierte selbst kleine Fehler in unkritischen Schritten
(z. B. NRS) zum vollständigen Abbruch der Anatomie.

### Fix
1. Jeder Render-Schritt ist jetzt in `safeRun(name, fn)` isoliert (eigenes `try/catch`).
2. Jeder Fehler wird mit `step`, `error.name`, `error.message`, vollständigem `stack` geloggt.
3. Die Warnung erscheint **nur noch**, wenn die Silhouette/Stage wirklich nicht gebaut
   werden konnte (`ROOT.querySelector('.anatomy-stage svg.anatomy-silhouette')` ist null).
4. Fehler in unkritischen Schritten (Legend/NRS/MarkerList) verhindern die Anatomie nicht mehr.

### Betroffene Datei/Funktion
- `public/assets/js/befund-anatomy.js` → `initAnatomy(ROOT)` (DOM-Build-Block)

### Verifikation
- F12 → Console: Bei weiteren Fehlern erscheint `[Befund Anatomy] Schritt "renderStage"
  fehlgeschlagen: ... \nStack: ...` mit exakter Fehlerursache.
- Hund/Katze/Pferd sollten jetzt zuverlässig laden; Silhouette-Check entscheidet über Warnung.

### Residuale Live-Verifikation
- Nach Test auf Live-Server: falls Warnung weiterhin erscheint → tatsächlichen
  `console.error`-Eintrag aus der Browser-Console in diese Datei kopieren.

---

## Bug: Befundbögen — Tierauswahl navigiert nicht zur Befundung

**Status:** `fixed`
**Datum:** 2026-05-08

### Ursache
Bootstrap 5 Stacked-Modal-Focus-Trap kann das Standard-`<a>`-Tag-Navigationsverhalten
unterdrücken. Die `.befund-species-card`-Links hatten **keinen expliziten JS-Click-Handler**
und verließen sich auf Browser-native `href`-Navigation, die im Kontext gestackter Modals
(Patient-Modal + Tierauswahl-Modal) unzuverlässig funktioniert.

### Geänderte Dateien
- `templates/partials/patient-modal-global.twig` — neuer event-delegierter Click-Handler
  für `.befund-species-card`: schließt Bootstrap-Modal sauber, dann `window.location.href`
- `templates/befunde/form.twig` — Container `.anatomy-nrs-scale` in Anatomie-Card ergänzt
- `public/assets/js/befund-anatomy.js` — `renderNrsScale()` implementiert (NRS 0–10,
  synct mit `input[name="schmerz_nrs"]`, liest gespeicherten Wert zurück)
- `public/assets/css/befund-anatomy.css` — CSS für `.anatomy-nrs-wrap`, `.anatomy-nrs-btn`,
  `.anatomy-nrs-labels` ergänzt

### Wie NRS jetzt initialisiert wird
1. `initAnatomy(ROOT)` wird beim DOMContentLoaded aufgerufen
2. `renderNrsScale()` liest `document.querySelector('input[name="schmerz_nrs"]').value`
3. Initialer Wert (falls vorhanden) wird als `.active`-Button markiert
4. Click → `state.nrs = i` + `nrsInput.value = String(i)` (kein Re-Render nötig)
5. Beim Form-Submit wird `schmerz_nrs` normal durch PHP/`collectFelder()` gespeichert

### Nachtrag 2026-05-12
- Server-seitige NRS-Anzeige ist jetzt visuell umgesetzt:
  - `templates/befunde/show.twig`
  - `templates/portal/befunde/show.twig`
  - `templates/portal-admin/befunde/show.twig`

---

## Bug: window.location.href-Fix war falsch — Seitenwechsel statt Modal-Flow

**Status:** `fixed`
**Datum:** 2026-05-08

### Ursache
Der erste Fix hat `window.location.href = '/patienten/{id}/befunde/neu?species={key}'` ergänzt,
um den Bootstrap Modal Focus-Trap zu umgehen. Das hat jedoch einen VOLLSEITENWECHSEL ausgelöst
anstatt die Anatomie im Patientenmodal zu belassen. Zusätzlich scheiterte die Anatomie-Initialisierung
auf der Formularseite mit "Die interaktive Anatomie konnte nicht geladen werden."

### Korrekter Fix (Commit 964aee8 auf main)

**templates/partials/patient-modal-global.twig:**
- `window.location.href`-Handler entfernt
- Neuer Handler: `openAnatomyInModal(patientId, species)` — kein Seitenwechsel
- `openAnatomyInModal()`: baut `#befund-anatomy`-HTML inline in `#pd-befunde-list`
- Lädt `/assets/css/befund-anatomy.css` + Stage-Sizing-CSS dynamisch (einmalig)
- Lädt `/assets/js/befund-anatomy.js` dynamisch; bei erstem Load: `boot()` init automatisch;
  bei Folge-Opens: `window.befundAnatomyInit(root)` direkt
- `saveBefundInline()`: AJAX POST zu `/patienten/{id}/befunde/speichern` mit `X-Requested-With`
- Nach Speichern: `loadBefundboegen()` neu laden

**public/assets/js/befund-anatomy.js:**
- `window.befundAnatomyInit = function(root) { initAnatomy(root); }` am Ende der IIFE
- Ermöglicht Re-Init auf neuem Container ohne IIFE-Guard-Problem

**app/Controllers/BefundbogenController.php:**
- `store()`: AJAX-Erkennung via `$this->isXhr()`
- Bei AJAX: JSON `{success: true, id: N}` zurückgeben statt redirect
- Auch im Fehlerfall: JSON `{success: false, error: ...}`

### Flow (korrekt)
1. Patientenmodal → Befundbögen → Befundung starten
2. Tierart wählen (Hund/Katze/Pferd)
3. Species-Modal schließt sich
4. Anatomie-HTML wird INLINE in `#pd-befunde-list` gerendert
5. JS+CSS werden dynamisch geladen (nur beim ersten Mal)
6. Anatomie mit Silhouette + NRS-Skala erscheint im Modal
7. "Befund speichern" → AJAX POST → JSON → Liste neu laden
8. Kein Seitenwechsel an keiner Stelle

---

## Bug: Chat-Medien werden nicht angezeigt (Bilder/Dokumente im Besitzerportal-Chat)

**Status:** `fixed` (Commits `363a935`, `871f072`, `21ae297`, `e253205`)
**Datum:** 2026-05-08 bis 2026-05-09

### Symptom
- Hochgeladene Bilder wurden im Chat nicht als Vorschau angezeigt
- Dokumente wurden nicht als Download-Links dargestellt
- Uploads schlugen mit "Dateityp nicht erlaubt" fehl für Bilder (JPG/PNG/GIF/WebP)

### Ursache (3 Teil-Bugs)

**1. Fehlende MIME-Types (Commit `363a935`)**
`ALLOWED_MIME` in `MessagingAdminController` und `MessagingOwnerController` enthielt nur
Dokument-Typen (PDF, Word, Excel, TXT, CSV). Bilder (`image/jpeg`, `image/png`, `image/gif`,
`image/webp`) fehlten → Upload-Validierung lehnte alle Bild-Uploads ab.

**2. Kein Image-Rendering im Template (Commit `871f072`)**
`admin_message_thread.twig` und `owner_message_thread.twig` zeigten **alle** Anhänge nur
als Download-Karten, ohne Extension-Check und ohne `<img>`-Tag. Kein `wa-attach-image`-CSS.

**3. Bilder öffneten sich in neuem Tab (Commit `21ae297` + `e253205`)**
`<a target="_blank">` öffnete Bilder in neuem Browser-Tab anstatt einer Lightbox.
Keine `openLightbox()`-Funktion vorhanden. Kein `data-lightbox="1"`-Attribut auf Links.
Admin-Drawer hatte keine Lightbox-Implementierung.

### Fixes

| Commit | Datei | Änderung |
|--------|-------|----------|
| `363a935` | `MessagingAdminController.php` | `image/jpeg`, `image/png`, `image/gif`, `image/webp` zu `ALLOWED_MIME` |
| `363a935` | `MessagingOwnerController.php` | Gleiche MIME-Ergänzung |
| `363a935` | `admin_message_thread.twig` | `accept`-Attribut im `<input type="file">` um Bilder erweitert |
| `363a935` | `owner_message_thread.twig` | Gleiche accept-Erweiterung |
| `871f072` | `admin_message_thread.twig` | Extension-Check + `<img>`-Tag + `.wa-attach-image` CSS |
| `871f072` | `owner_message_thread.twig` | Gleiche Rendering-Logik |
| `21ae297` | `admin_message_thread.twig` | `openLightbox()` Funktion + `data-lightbox="1"` |
| `21ae297` | `owner_message_thread.twig` | Gleiche Lightbox-Implementierung |
| `e253205` | `MessagingAdminController.php` | Drawer-Attachment-Rendering + `buildDrawerAttachment()` |
| `e253205` | `storage/themes/smart-tierphysio/layout.twig` | Drawer-Lightbox (`drawer-lightbox`-Overlay) |

### Betroffene Dateien
- `plugins/owner-portal/MessagingAdminController.php`
- `plugins/owner-portal/MessagingOwnerController.php`
- `plugins/owner-portal/templates/admin_message_thread.twig`
- `plugins/owner-portal/templates/owner_message_thread.twig`
- `storage/themes/smart-tierphysio/layout.twig`

### Verifikation
- Bild hochladen → erscheint als `<img>` mit Lightbox bei Klick
- Klick auf Bild → Fullscreen-Lightbox mit Download-Button und ESC-Close
- PDF hochladen → erscheint als Download-Karte mit Dateiname + Größe
- Admin-Drawer → Bild-Anhänge werden inline mit Lightbox angezeigt
- Tenant-Isolation: Dateien landen in `storage/tenants/{prefix}/portal-attachments/{threadId}/`

### Nachtrag 2026-05-12
- Video-Preview-Support umgesetzt:
  - MP4/WebM/MOV sind in `ALLOWED_MIME`
  - Download-Endpunkte liefern Videos inline aus
  - Admin-/Owner-Chat und Admin-Drawer rendern `<video controls>`
- Serverseitige Bildoptimierung umgesetzt:
  - JPEG/PNG/WebP Uploads laufen nach `move_uploaded_file()` durch `MediaOptimizerService`
  - Die Chat-Vorschau nutzt die optimierte Datei; separate Thumbnail-Dateien werden nicht angelegt

---

## Bug: Feature-Gating sperrt Steuerreport/Steuerexport trotz freigeschaltetem Feature

**Status:** `fixed`
**Datum:** 2026-05-11
**Tenant:** Tierphysio Wenzel (und alle Therapeut-Tenants mit `tax_export` Feature)

### Symptom
Tenant hat `tax_export` im SaaS-Admin freigeschaltet. `/steuerexport` zeigt trotzdem
„Feature nicht freigeschaltet" bzw. wird mit 403 blockiert.

### Ursache (2 Bugs)

**Bug 1 — Doppelter Array-Key in `FeatureRouteMap::MAP` (kritisch)**
`app/Services/FeatureRouteMap.php` enthielt `/steuerexport` **zweimal** als PHP-Array-Key:
- Eintrag 1 (korrekt): `'/steuerexport' => 'tax_export'` (für tax-export-pro Plugin)
- Eintrag 2 (falsch, überschreibt Eintrag 1): `'/steuerexport' => 'dogschool_datev_export'`

PHP-Arrays erlauben keine doppelten Schlüssel. Der zweite Eintrag überschreibt stillschweigend
den ersten. Die Route `/steuerexport` wurde dadurch immer gegen `dogschool_datev_export` geprüft —
ein Feature das für alle Therapeut-Tenants (practice_type ≠ 'trainer') durch den
`DOGSCHOOL_PREFIX`-Gate grundsätzlich `false` ist.

**Bug 2 — Route-Kollision in `web.php`**
Beide Controller (`TaxExportController` via ServiceProvider, `DatevExportController`) waren auf
`GET /steuerexport` registriert. Der Router führt je nach Reihenfolge nur einen aus — Konfusion
zwischen zwei völlig unterschiedlichen Features.

### Fix

| Datei | Änderung |
|-------|----------|
| `app/Services/FeatureRouteMap.php` | Doppelten `/steuerexport` → `dogschool_datev_export` Eintrag entfernt, neuen Eintrag `/hundeschule/steuerexport` → `dogschool_datev_export` ergänzt |
| `app/Routes/web.php` | Hundeschule DATEV-Export-Routen von `/steuerexport` auf `/hundeschule/steuerexport` umgezogen |
| `templates/dogschool/datev/index.twig` | Alle Form-Actions und GET-Actions auf `/hundeschule/steuerexport` aktualisiert |
| `templates/layouts/base.twig` | Sidebar-Navlink für `dogschool_datev_export` auf `/hundeschule/steuerexport` aktualisiert |
| `templates/dogschool/invoices/index.twig` | „→ Steuerexport"-Button auf `/hundeschule/steuerexport` aktualisiert |

### Feature-Gating nach Fix

| URL | Feature-Key | Gilt für |
|-----|-------------|---------|
| `/steuerexport` | `tax_export` | Therapeuten (tax-export-pro Plugin) |
| `/hundeschule/steuerexport` | `dogschool_datev_export` | Trainer-Tenants (Hundeschule) |

### Verifikation
1. Therapeut-Tenant mit `tax_export` aktiviert → `/steuerexport` öffnet ohne Fehlermeldung ✓
2. Therapeut-Tenant ohne `tax_export` → 403/Redirect zum Dashboard ✓
3. Trainer-Tenant mit `dogschool_datev_export` → `/hundeschule/steuerexport` öffnet ✓
4. Therapeut-Tenant auf `/hundeschule/steuerexport` → 403 (DOGSCHOOL_PREFIX-Gate) ✓
5. Keine anderen Tenants oder Features betroffen ✓

---

---

## Bug: Cron HTTP 302 — TherapyCare & Kalender-Erinnerungen schlagen fehl

**Status:** `fixed`
**Datum:** 2026-05-11

### Symptom
SaaS Cron-Dashboard zeigt für folgende Jobs `HTTP 302`:
- 💊 TherapyCare Erinnerungen (`/tcp/cron/erinnerungen`)
- 📅 Kalender-Erinnerungen (`/kalender/cron/erinnerungen`)

Jobs werden nicht ausgeführt. Reminder-Mails werden nicht versendet.

### Ursache (3 Schichten)

**Bug 1 — FeatureRouteMap greift auf Cron-Pfade (Haupt-Ursache)**
`app/Services/FeatureRouteMap.php` mappt URL-Präfixe auf Feature-Keys:
- `/kalender` → `calendar`
- `/tcp` → `therapy_care`

Der Router wendet dieses Auto-Gating auf ALLE Routen an, auch auf `[]`-Routen ohne Auth-Middleware.
Cron-Requests haben keine Session → Feature-Gate prüft `isEnabled()` → kein Cache/Prefix → `false` →
`FeatureGateService::requireFeature()` gibt `header('Location: /dashboard')` + exit → **HTTP 302**.

**Bug 2 — FeatureMiddleware hat kein Cron-Bypass**
`app/Middleware/FeatureMiddleware::handle()` reichte blind an `gate->requireFeature()` weiter,
ohne den `X-Internal-Cron: true` Header zu prüfen, den der Dispatcher setzt.

**Bug 3 — TCP Cron setzt Tenant-Prefix zu spät**
`TherapyCareController::cronReminders()` rief `$this->settingsRepo->all()` auf, BEVOR der
`?tid=` Parameter verarbeitet wurde. Dadurch wurde der falsche (leere) Tenant-Context gelesen.

### Fix

| Datei | Änderung |
|-------|----------|
| `app/Services/FeatureRouteMap.php` | Alle Cron-Endpunkte als `null` eingetragen (explizit kein Gate): `/cron`, `/kalender/cron`, `/tcp/cron`, `/google-kalender/cron`, `/portal/cron`, `/kurse/cron`, `/api/holiday-cron` |
| `app/Services/FeatureGateService.php` | `requireFeature()`: früher Return wenn `HTTP_X_INTERNAL_CRON = 'true'` |
| `app/Middleware/FeatureMiddleware.php` | `handle()`: bypass für `X-Internal-Cron: true` (Defense-in-Depth Layer 2) |
| `plugins/therapy-care-pro/TherapyCareController.php` | `cronReminders()`: Tenant-Prefix aus `?tid=` BEVOR erster DB-Zugriff (`settingsRepo->all()`) |

### Cron-Architektur nach Fix

```
SaaS cron_runner.php
  → HTTP GET /cron/dispatcher?tid=XYZ
    (X-Internal-Cron: true Header)
    → FeatureRouteMap: /cron → null (kein Gate)
    → CronController::dispatcher()
      → executeJob() via cURL mit X-Internal-Cron: true
        → /kalender/cron/erinnerungen?tid=XYZ&token=ABC
          → FeatureRouteMap: /kalender/cron → null (kein Gate)
          → CalendarController::cronReminders() ✅
        → /tcp/cron/erinnerungen?tid=XYZ&token=ABC
          → FeatureRouteMap: /tcp/cron → null (kein Gate)
          → TherapyCareController::cronReminders() ✅
```

### Bekannte Stolperfallen
- **`?token=` vs `&token=`**: Wenn die URL bereits `?tid=...` enthält, muss der Token mit `&token=` angehängt werden, nicht `?token=`. → Korrekt in `PraxisCronController::runNow()` (Zeile 256: `$url .= '&token=' . $token`)
- **Tenant-Prefix vor DB-Zugriff**: In jedem Cron-Controller MUSS `setPrefix()` aus `?tid=` erfolgen, bevor `settingsRepo` oder `repo` aufgerufen wird.
- **FeatureRouteMap null vs. kein Eintrag**: `null` in der Map → explizit kein Gate. Fehlt ein Eintrag und der Präfix matcht einen übergeordneten Eintrag, greift das übergeordnete Gate.

### Verifikation
1. Cron-Dashboard zeigt `HTTP 200` für TherapyCare Erinnerungen ✓
2. Cron-Dashboard zeigt `HTTP 200` für Kalender-Erinnerungen ✓
3. Dispatcher-Log (`cron_dispatcher_log`) zeigt `success` für `tcp_reminders` und `calendar_reminders` ✓
4. Keine doppelten Reminder ✓
5. Tenant-Kontext korrekt (richtige Queue geladen) ✓

---

---

## Bug: Google Kalender Sync bricht mit Duplicate Key `uq_appin` ab

**Status:** `fixed`
**Datum:** 2026-05-11

### Symptom
- „Letzter Sync" im Dashboard sehr alt
- Sync-Log zeigt: `SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '1020-1001' for key 'uq_appin'`
- Neue Termine wurden nicht zu Google synchronisiert

### Ursache (3 Bugs)

**Bug 1 — Alter Constraint-Name `uq_appin`**
Auf manchen Tenants wurde `google_calendar_sync_map` mit dem kurzen Schlüsselnamen `uq_appin`
statt `uq_appointment_connection` erstellt. `ON DUPLICATE KEY UPDATE` referenziert immer den
Constraint auf `(appointment_id, connection_id)`. Wenn der Name nicht stimmt, ignoriert MySQL
den `ON DUPLICATE KEY`-Pfad → normaler INSERT → Duplicate Key Exception.

**Bug 2 — INSERT IGNORE statt ON DUPLICATE KEY UPDATE**
`createSyncEntry()` nutzte `INSERT IGNORE` — bei einem vorhandenen Eintrag wurde der INSERT
stillschweigend ignoriert, `lastInsertId()` gab `0` zurück. Bei erneutem `syncCreated()`-Aufruf
(z.B. nach `sync_status='failed'` mit leerem `google_event_id`) wurde erneut `INSERT` versucht
→ Exception da Unique-Violation.

**Bug 3 — bulkSyncAll success-Counter falsch**
`$success++` wurde auch erhöht wenn ein vorhandener gültiger Sync-Eintrag gefunden und
übersprungen wurde → falscher Zählerstand im Sync-Report.

### Fix

| Datei | Änderung |
|-------|----------|
| `plugins/google-calendar-sync/migrations/004_fix_sync_map_constraint.sql` | Neue Migration: DROP KEY `uq_appin`, DROP KEY `uq_appointment_connection`, ADD UNIQUE KEY `uq_appointment_connection` |
| `GoogleCalendarRepository.php` → `createSyncEntry()` | Umgestellt von `INSERT IGNORE` auf `INSERT ... ON DUPLICATE KEY UPDATE` mit allen relevanten Feldern. Bei ODKU-Trigger (lastInsertId=0) wird die vorhandene Row-ID nachgeladen. |
| `GoogleCalendarRepository.php` → `getLastSuccessfulSync()` | `action IN ('create','update','delete','pull')` — Pull-Aktionen werden jetzt als erfolgreicher Sync gewertet |
| `GoogleSyncService.php` → `bulkSyncAll()` | `$skipped++` für vorhandene gültige Einträge, `$success++` nur nach tatsächlichem `syncCreated()`-Aufruf |
| `GoogleSyncService.php` → `upsertAppointmentFromGoogle()` | Patient/Owner jetzt per `matchPatientOwnerFromText()` aus Titel/Beschreibung abgeleitet |
| `GoogleSyncService.php` → `matchPatientOwnerFromText()` | Neue Methode: Token-Split, exakter DB-Match + Substring-Fallback für Patient und Owner; Ableitungsregel Owner→Patient; Mehrdeutigkeit → null |

### Verifikation
1. Sync läuft ohne SQL-Exception durch ✓
2. Letzter Sync wird korrekt aktualisiert (auch nach Pull) ✓
3. Kein doppelter Termin in Google ✓
4. Google-Termin mit Tiername → Patient wird zugeordnet ✓
5. Google-Termin mit Besitzername → Owner wird zugeordnet ✓
6. Mehrdeutige Namen → keine falsche Zuordnung ✓

---

---

## Bug: therapano.de/impressum liefert HTTP 404

**Status:** `fixed`
**Datum:** 2026-05-11

### Symptom
- `https://therapano.de/impressum` → HTTP 404
- Google OAuth Trust & Safety Prüfung schlägt fehl
- Footer-Links und Landing-Page-Links im Consent Screen fehlerhaft

### Ursache (2 Bugs)

**Bug 1 — Kein `impressum`-Datensatz in `legal_documents`**
`migration/001_initial_schema.sql` seeded `datenschutz`, `agb`, `av-vertrag` —
aber **kein `impressum`**. `LegalController::view()` rief `findBySlug('impressum')` auf →
`false` → `$this->notFound()` → HTTP 404.

**Bug 2 — Kein Placeholder-Fallback**
`LegalController::view()` hatte keine Fallback-Logik für nicht vorhandene Slugs.
Ein fehlender DB-Eintrag führte direkt zu einem harten 404 statt einer Hilfsseite.

### Was NICHT das Problem war
- Routes in `platform.php` existierten bereits korrekt (`/legal/{slug}`, `/impressum`, `/datenschutz`)
- `legal/view.twig` existierte bereits
- `LegalController` hatte keine `requireAuth()` → korrekt öffentlich

### Fix

| Datei | Änderung |
|-------|----------|
| `saas-platform/migrations/053_legal_impressum_seed.sql` | **Neu**: `INSERT IGNORE INTO legal_documents` für Slug `impressum` mit Standardtext |
| `saas-platform/app/Controllers/LegalController.php` → `view()` | DB-Zugriff in try/catch (DB evtl. nicht installiert); Placeholder statt 404 wenn Slug nicht in DB |
| `saas-platform/templates/layouts/public.twig` | `Impressum`-Link im Footer ergänzt |
| `claude-obsidian/06-saas/public-legal-pages.md` | **Neu**: Architektur-Doku der öffentlichen Legal-Seiten |

### Verifikation
1. `https://therapano.de/impressum` → HTTP 200 ✓
2. `https://therapano.de/datenschutz` → HTTP 200 ✓
3. `https://therapano.de/legal/datenschutz` → HTTP 200 ✓
4. Kein Login erforderlich ✓
5. Footer zeigt alle Legal-Links ✓
6. Bei fehlendem DB-Eintrag: Placeholder statt 404 ✓

---

## Bug: Migrationen wurden im falschen Ordner erstellt (Repo-Root statt saas-platform/)
**Status:** `fixed` (Mai 2026)
**Migration:** `saas-platform/migrations/063_homework_default_active.sql`

### Symptom
Migrationen, die unter `migrations/` (Repo-Root) abgelegt wurden, wurden vom SaaS
MigrationService nie ausgeführt — keine Fehlermeldung, stille Wirkungslosigkeit.

### Ursache
`MigrationService::getLatestVersion()` benutzt `$this->config->getRootPath() . '/migrations'`.
`getRootPath()` = `saas-platform/` (SAAS_ROOT aus `public/index.php`).
Der Root-`migrations/`-Ordner wird NIE gelesen.

### Fix
- Dokumentation (`claude-obsidian/01-architecture/migrations.md` + `agents.md`) korrigiert
- Harte Regel etabliert: SaaS-Migrationen IMMER unter `saas-platform/migrations/`

---

## Bug: Hausaufgaben-Plugin nicht standardmäßig aktiv (required_plan: pro statt basic)
**Status:** `fixed` (Mai 2026)
**Migration:** `saas-platform/migrations/063_homework_default_active.sql`

### Symptom
Tenants auf Basic-Plan sahen kein Hausaufgaben-Plugin, obwohl es Default-Aktiv sein sollte.

### Ursache
`saas_feature_flags.required_plan` für `homework` war `pro` → Basic-Tenants ohne Feature.
Außerdem fehlte `homework` in der `plans.features` JSON-Liste für Basic-Pläne.

### Fix
1. `INSERT INTO saas_feature_flags ... ON DUPLICATE KEY UPDATE required_plan = 'basic'`
2. `UPDATE plans SET features = JSON_ARRAY_APPEND(...)` für alle Pläne (idempotent)
3. `DELETE FROM settings WHERE key = '_features_cache'` per Tenant → Re-Sync erzwungen

---

---

## Bug: CSP font-src blockiert FullCalendar fcicons Base64-Font (Mai 2026)
**Status:** `fixed`
**Commit:** `fix: repair csp font assets and patient media routes`

### Symptom
Browser-Console: `Loading the font 'data:application/x-font-ttf;charset=utf-8;base64,...' violates CSP: font-src 'self' https://fonts.gstatic.com`

### Ursache
`smartapp.css` (FullCalendar-CSS, eingebettet im SmartAdmin Theme) enthält einen `@font-face`-Block für `fcicons` mit einer Base64-codierten TTF-Schrift als Inline-`data:`-URI.
`public/.htaccess` erlaubte in `font-src` nur `'self'` und `https://fonts.gstatic.com` — kein `data:`.

### Fix
`public/.htaccess` `font-src` um `data:` erweitert:
```
font-src 'self' https://fonts.gstatic.com data:
```
Nur `data:` für Fonts erlaubt (nicht `script-src`, kein `unsafe-eval`). CSP bleibt sonst unverändert.

### Geänderte Dateien
- `public/.htaccess` — `font-src` + `data:`

---

## Bug: `/patienten/{id}/dokumente/{file}` liefert 404 (Mai 2026)
**Status:** `fixed`
**Commit:** `fix: repair csp font assets and patient media routes`

### Symptom
`GET /patienten/14/dokumente/6ca47236adb81b83770328c35c591ec3.jpg 404`

### Ursache (Path Mismatch)
- `uploadDocument()` speichert in `patients/{id}/docs/`
- `downloadDocument()` suchte in `patients/{id}/timeline/` ← **falscher Pfad!**

Die Dateinamen in der DB zeigten auf `docs/`, der Controller las aber aus `timeline/` → immer 404.

### Fix
`downloadDocument()` sucht jetzt in folgender Reihenfolge:
1. `patients/{id}/docs/` ← primärer Upload-Pfad
2. `patients/{id}/timeline/` ← Legacy-Fallback (alte Uploads)
3. `patients/{id}/` ← weiterer Legacy-Fallback

Bei fehlender Bilddatei: Tatzen-Placeholder statt kaputtem Broken-Image-Icon.
Fehlende Datei wird per `error_log()` geloggt.

### Geänderte Dateien
- `app/Controllers/PatientController.php` — `downloadDocument()` neue Kandidaten-Liste + Placeholder-Fallback

---

## Bug: `/patienten/{id}/foto/{file}` liefert 404 für fehlende Fotos (Mai 2026)
**Status:** `fixed`
**Commit:** `fix: repair csp font assets and patient media routes`

### Symptom
`GET /patienten/1001/foto/invite_69ea4d5cba5ba.jpg 404`

### Ursache
`servePhoto()` in `PatientController` hatte bereits korrekte Kandidaten-Logik (inkl. `intake/` für `invite_`-Präfix), rief aber `$this->abort(404)` wenn keine Datei gefunden → kaputtes Broken-Image-Icon in der UI.

### Fix
Bei nicht gefundener Foto-Datei: `servePawPlaceholder()` → HTTP 200 + SVG Tatzen-Placeholder.
Fehlende Datei wird per `error_log()` geloggt.

### Neue Methode `servePawPlaceholder()`
- Liefert `/assets/img/placeholder-paw.svg` aus (wenn vorhanden), sonst inline SVG
- HTTP 200 + `Content-Type: image/svg+xml`
- Keine 404/Broken-Image in der UI

### Geänderte Dateien
- `app/Controllers/PatientController.php` — `servePhoto()` + neue `servePawPlaceholder()` Methode
- `public/assets/img/placeholder-paw.svg` — Neues Tatzen-Placeholder-SVG

---

## Bug: `/favicon.ico` liefert 404 (Mai 2026)
**Status:** `fixed`
**Commit:** `fix: repair csp font assets and patient media routes`

### Symptom
`GET https://app.therapano.de/favicon.ico 404`

### Ursache
Kein `favicon.ico` in `public/`. Kein `<link rel="icon">` in den HTML-Layouts. Browser fragt automatisch `/favicon.ico` → PHP-Router → keine Route → 404.

### Fix
1. `.htaccess` RewriteRule: `favicon.ico` → `R=301` auf `/themes/smart-tierphysio/img/favicon-32x32.png`
2. `templates/layouts/base.twig` — `<link rel="icon">` + `<link rel="apple-touch-icon">` Tags
3. `plugins/owner-portal/templates/portal_layout.twig` — gleiche Favicon-Links

### Geänderte Dateien
- `public/.htaccess` — RewriteRule `^favicon\.ico$`
- `templates/layouts/base.twig` — `<link rel="icon">` Tags
- `plugins/owner-portal/templates/portal_layout.twig` — `<link rel="icon">` Tags

---

## Bug: Fehlende Medien zeigen Browser-Broken-Image statt Platzhalter (Mai 2026)
**Status:** `fixed`
**Commit:** `fix: repair csp font assets and patient media routes`

### Problem
Fotos, Dokument-Vorschauen, Chat-Bilder, Timeline-Medien — wenn Datei fehlt: hässliches Browser-Broken-Image-Icon.

### Fix: Globaler JS Image-Fallback
`public/assets/js/app.js` + `portal_layout.twig`: `MutationObserver`-basierter globaler `onerror`-Handler für alle `<img>` Tags.

Bei `error` Event: `img.src` → `/assets/img/placeholder-paw.svg`.
- Deckt alle dynamisch geladenen Inhalte ab (Modal-Tabs, Chat, Timeline AJAX)
- Kein Copy-Paste in Templates
- `data-paw-fallback` Guard verhindert Loop bei fehlender Placeholder-Datei selbst

### Paw-Placeholder SVG
`public/assets/img/placeholder-paw.svg` — Modernes dunkles Tatzen-Design, passend zu TheraPano:
- Hintergrund `#1e293b` (bg-card)
- Tatzen-Pads `#334155` (bg-elevated)
- Akzent-Outline `#a78bfa` (color-accent)
- Label `#64748b` (text-muted): "Kein Bild"

### Geänderte Dateien
- `public/assets/js/app.js` — globaler Image-Fallback + MutationObserver
- `plugins/owner-portal/templates/portal_layout.twig` — gleicher Fallback-Block
- `public/assets/img/placeholder-paw.svg` — Neues SVG

---

---

## Bug: ApexCharts `v.toFixed is not a function` Crash im Dashboard (Mai 2026)
**Status:** `fixed`
**Commit:** `fix: stabilize dashboard charts and patient media fallbacks`

### Symptom
```
dashboard:5116 Uncaught (in promise) TypeError: v.toFixed is not a function
at formatter (dashboard:5116:157)
at apexcharts.min.js
```

### Ursache
Alle `formatter`-Funktionen in `templates/dashboard/index.twig` riefen `v.toFixed()` direkt auf:
```js
formatter: function(v) { return v.toFixed(2) + ' €'; }  // CRASH wenn v = null/undefined/NaN/"0"
```
ApexCharts übergibt `v` als raw-Wert — kann `null`, `undefined`, `NaN` oder ein String sein (z.B. wenn PHP-Daten als leere Arrays oder `null`-Felder kommen).

### Fix
Zentrales Safe-Formatter-System:
```js
function safeNum(v) { var n = Number(v); return Number.isFinite(n) ? n : 0; }
function fmtEur(v)  { var n = safeNum(v); return n.toFixed(2).replace('.',',') + ' €'; }
function fmtEurK(v) { var n = safeNum(v); return n >= 1000 ? (n/1000).toFixed(1)+'k €' : n.toFixed(0)+' €'; }
function fmtCnt(v)  { return Math.round(safeNum(v))+''; }
```
Alle `formatter`-Aufrufe in allen ~10 Charts auf `fmtEur`, `fmtEurK`, `fmtCnt` umgestellt.

### Regel (dauerhaft)
**Nie `.toFixed()` direkt auf ApexCharts-`v` aufrufen.**
Immer `safeNum(v)` vorschalten. `v` ist niemals garantiert numerisch.

---

## Performance: Dashboard DOMContentLoaded 428ms / Forced Reflow 1400ms (Mai 2026)
**Status:** `improved`
**Commit:** `fix: stabilize dashboard charts and patient media fallbacks`

### Symptom
```
[Violation] Forced reflow while executing JavaScript took 1400ms
[Violation] 'DOMContentLoaded' handler took 428ms
[Violation] Forced reflow while executing JavaScript took 407ms  (Rechnungen)
```

### Ursachen
1. **Charts synchron in IIFE**: Alle ~10 ApexCharts wurden sofort beim Script-Parse gebaut → blockieren DOMContentLoaded-Phase
2. **Bell-Animation Reflow**: `void svg.offsetWidth` (forced reflow) wurde synchron im Polling-Callback ausgeführt
3. **Intake-Poll sofort beim Seitenstart**: Erster fetch() feuerte ohne Delay, konkurrierte mit DOM-Aufbau

### Fixes
1. Alle Chart-Initialisierungen in `defer(fn)` gewrapped:
   ```js
   function defer(fn) {
       if (typeof requestIdleCallback !== 'undefined') {
           requestIdleCallback(fn, { timeout: 2000 });
       } else { setTimeout(fn, 0); }
   }
   ```
2. Bell-Animation-Reflow in `requestAnimationFrame()` verschoben
3. Erster Intake-Poll: `setTimeout(poll, 5000)` statt sofortigem Aufruf

### Geänderte Dateien
- `templates/dashboard/index.twig` — `defer()` für alle Charts + Safe Formatters
- `templates/layouts/base.twig` — Bell-rAF + 5s initaler Poll-Delay

---

## Bug: Patientenfoto 422 (Unprocessable Content) für `invite_*.jpg` (Mai 2026)
**Status:** `analysiert / teilweise mitigiert`
**Commit:** `fix: stabilize dashboard charts and patient media fallbacks`

### Symptom
```
GET https://app.therapano.de/patienten/1001/foto/invite_69ea4d5cba5ba.jpg 422 (Unprocessable Content)
```

### Analyse
- PHP-Code (`PatientController::servePhoto()`) liefert **kein** 422 — Quellcode-Analyse bestätigt: kein `http_response_code(422)` im Controller oder in Middleware für GET-Routen.
- 422 kommt von einem **externen WAF/ModSecurity** auf dem Produktivserver — der Dateiname `invite_*.jpg` oder der URL-Pfad triggert eine ModSec-Regel.
- PHP-Controller wurde bereits in der vorherigen Session korrekt implementiert: fehlende Datei → `servePawPlaceholder()` → HTTP 200 + SVG.
- Wenn WAF die Anfrage abfängt, erreicht PHP den Controller nie → `onerror` in `<img>` feuert bei 422 genauso wie bei 404.

### Mitigations
1. **Server-seitig**: `X-TheraPano-Media-Fallback: missing-file` Header in `servePawPlaceholder()` für Diagnose
2. **Client-seitig**: Globaler `MutationObserver`-Image-Fallback in `app.js` fängt 422 genauso ab wie 404 → Paw-Placeholder erscheint automatisch
3. **Server-WAF**: ModSecurity-Regel für `/patienten/*/foto/*.jpg` muss auf dem Produktivserver whitelistet werden — außerhalb dieses Repos

### Empfehlung für Server-Admin
ModSec-Ausnahme für:
```
SecRule REQUEST_URI "@beginsWith /patienten/" "id:9001,phase:1,allow,nolog"
```
oder spezifisch für die `foto`-Route.

---

## Bug: 3D-Schmerzanalyse — Muskelpunkte schweben in der Luft / stecken im Tier (Juli 2026)
**Status:** `fixed`
**Datei:** `public/assets/js/anatomy-3d-viewer.js`

### Symptom
Im 3D-Schmerzanalyse-Tab (Hund/Katze/Pferd) saßen die klickbaren Muskelpunkte
nicht auf der Modelloberfläche: Abstände waren zu groß, Punkte schwebten neben
dem Tier in der Luft oder verschwanden im Körperinneren.

### Ursache
`MUSCLE_GROUPS` definiert für jede Muskelgruppe eine feste `pos`-Koordinate im
angenommenen normalisierten Modell-Raum (z.B. Hund `X±0.360`). Die GLB-Modelle
werden zur Laufzeit auf eine 2-Einheiten-Boundingbox (`scale = 2/maxDim`) skaliert
und zentriert. Die tatsächliche Breite/Höhe des Tieres an einer bestimmten Stelle
weicht aber von den angenommenen Werten ab (unterschiedliche Proportionen je GLB),
sodass seitliche Marker neben dem Körper landeten und dorsale/ventrale Marker zu
weit innen/außen. Die Punkte wurden hart auf `def.pos` gesetzt — ohne Bezug zur
echten Geometrie.

### Fix (Oberflächen-Projektion per Raycasting)
Neue Methode `_projectToSurface(def, mb, modelMeshes)`:
- Berechnet aus `def.pos` eine radiale Richtung ausgehend von der Körperlängsachse
  (Wirbelsäule bei `x=0`, `y=vertikaler Mittelpunkt`, entlang `z`).
- Wirft einen Strahl von außerhalb der Boundingsphere zurück zur Achse durch den
  Anker; der erste Treffer auf dem echten Mesh ist die Außenhaut in dieser Richtung.
- Der Marker wird `0.015` Einheiten über die Oberfläche gehoben, damit er sichtbar
  auf dem Körper liegt.
- Fällt auf den rohen `def.pos` zurück, falls der Strahl das Mesh verfehlt.

`_buildHotspots()` snappt jetzt jeden Hotspot (Raycast-Box + sichtbarer Marker) auf
die projizierte Position und speichert sie als `entry.pos`. `_focusHotspot()` nutzt
die projizierte Position statt der rohen `def.pos`.

### Warum das robust ist
Die Marker sitzen immer auf der tatsächlich geladenen Mesh-Oberfläche — unabhängig
von den individuellen Proportionen jedes GLB. Die anatomischen Anker-Koordinaten in
`MUSCLE_GROUPS` bleiben unverändert (nur noch Richtungs-/Positionshinweis), sodass
Labels, Seiten, Regionen und die API (`muscle_group_id`, `side`) unverändert bleiben —
keine Breaking Changes an gespeicherten Schmerzpunkten.

### Verifikation
- Debug-Modus („🔲 Zonen") zeigt alle Marker jetzt direkt auf der Tieroberfläche.
- Hover/Klick trifft die Zonen dort, wo der Marker sichtbar ist.
- Gespeicherte Schmerzpunkte erscheinen farbig am korrekten Körperbereich.

---

## Bug: Portal — Trainer/Hundeschulen sehen Befundbögen + Logout beim Klick (Juli 2026)
**Status:** `fixed`
**Dateien:**
- `app/Controllers/BefundbogenController.php` (`requirePortalAuth`, `portalIndex/Show/Pdf`)
- `plugins/owner-portal/templates/portal_layout.twig` (Nav-Gating)
- `plugins/owner-portal/OwnerPortalController.php` (`petDetail` — Befunde nicht laden)

### Symptom
Im Besitzerportal von Trainer-/Hundeschul-Tenants wurden tiertherapeutische Inhalte
(„Befundbögen") weiterhin angezeigt. Klick auf den Befundbögen-Link loggte den Nutzer aus
(Redirect auf `/portal/login`).

### Ursache (2 unabhängige Bugs)
**Bug 1 — Logout (betraf alle Tenants):**
`BefundbogenController::requirePortalAuth()` fragte `owner_portal_users` und `owners` **ohne
Tenant-Prefix** ab (`FROM owner_portal_users u JOIN owners o ...`). Die echten Tabellen heißen
`t_{tenant}_owner_portal_users` etc. (siehe `OwnerPortalRepository`, das `$db->prefix()` nutzt).
`Database::query()` prefixt NICHT automatisch → Query trifft nicht existierende Tabelle →
PDOException → `catch` → `$user = null` → Session gelöscht → Redirect `/portal/login` = Logout.
Der Prefix ist für `/portal/*`-Requests korrekt gesetzt (`app/Core/Application.php` aus
`portal_tenant_prefix`), wurde aber im rohen SQL ignoriert. Zusätzlich nutzte die Unread-Query
die falsche Spalte `sender` statt `sender_type` und ebenfalls unprefixte Tabellen.

**Bug 2 — Sichtbarkeit:**
Der „Befundbögen"-Nav-Link in `portal_layout.twig` war nicht per `is_trainer_tenant` gated
(anders als „Kurse"). `BefundbogenController` lieferte zudem kein `is_trainer_tenant` an das
Layout. Der Pet-Detail-Tab „Befundbögen" erschien für Trainer, weil `OwnerPortalController::petDetail`
Befunde unabhängig vom Tenant-Typ lud.

### Fix
1. `requirePortalAuth()`: Tabellen mit `$db->prefix(...)` (owner_portal_users, owners,
   portal_messages, portal_threads), Unread-Query auf `sender_type = 'admin'` korrigiert,
   `is_trainer_tenant` (aus `settings.practice_type`) im Base-Array ergänzt. Neuer Helper
   `isTrainerTenant()` (spiegelt `OwnerPortalController::isTrainerTenant()`).
2. `portalIndex/portalShow/portalPdf`: bei Trainer-Tenant sauberer Redirect auf
   `/portal/dashboard` statt Logout/Anzeige (Server-seitiger Guard, auch bei Direkt-URL).
3. `portal_layout.twig`: Befundbögen-Nav nur `{% if not (is_trainer_tenant ?? false) %}`.
4. `OwnerPortalController::petDetail`: Befunde bei Trainer-Tenants gar nicht laden →
   Befundbögen-Tab verschwindet.

### Verifikation
- Therapeut-Tenant: `/portal/befunde` lädt jetzt korrekt (vorher Logout).
- Trainer-Tenant: kein Befundbögen-Nav, kein Befund-Tab; Direktaufruf `/portal/befunde`
  → Redirect Dashboard (kein Logout).

---

## Bug: Bilanz — Juni-Rechnungen erscheinen als Juli-Umsatz + Analyse widerspricht KPIs (Juli 2026)
**Status:** `fixed`
**Dateien:**
- `app/Controllers/InvoiceController.php` (`updateStatus`, `updateStatusInline`, neu: `resolvePaidAt()`)
- `app/Repositories/InvoiceRepository.php` (`getRevenueByMonth/Quarter/Year`, neu: `revenueDateExpr()`)
- `templates/invoices/show.twig` (Zahlungsdatum-Feld + Anzeige)
- `templates/invoices/index.twig` (Zahlungsdatum in Liste)

### Symptom (User-Report 2026-07-02)
Juni-Rechnungen wurden am 1. Juli ausgebucht und erschienen unter „Umsatz diese Woche"
bzw. als Juli-Umsatz. Frage: „Ist das trotzdem in den Juni gebucht?" — Nein, war es nicht.

### Analyse
**Umsatz-Zuordnung ist Zahlungsdatum-basiert (Ist-Besteuerung, korrekt):**
`getStats()` nutzt `DATE(COALESCE(paid_at, issue_date))`. Der Steuerexport
(`DatevExportService`, Zeile 338) ebenfalls. ABER:

**Problem 1 — Zahlungsdatum nicht setzbar (Web):** `updateStatus`/`updateStatusInline`
setzten `paid_at` hart auf `date('now')`. Wer eine Juni-Zahlung erst im Juli ausbucht,
bekam zwangsweise Juli als Umsatz-/Steuermonat. (Mobile-API akzeptierte `paid_at` bereits.)

**Problem 2 — Zahlungsdatum unsichtbar:** `paid_at` wurde nur bei Barzahlung angezeigt.
Niemand konnte sehen, welchem Monat eine Zahlung zugeordnet ist.

**Problem 3 — Inkonsistente Datumsbasis:** Analyse-Charts (`getRevenueByMonth/Quarter/Year`)
gruppierten nach `issue_date`, die KPI-Kacheln nach `paid_at` → „Bilanz stimmt nicht":
dieselbe Rechnung zählte im Chart zum Juni, in der KPI zum Juli.

### Fix
1. `resolvePaidAt()`: Status-Formulare akzeptieren optionales `paid_at` (YYYY-MM-DD).
   Rückdatierung erlaubt, Zukunft blockiert, ungültig → jetzt. Gespeichert als `12:00:00`.
2. `show.twig`: Datumsfeld „Zahlungseingang am" im Status-Panel (sichtbar bei Status
   „Bezahlt", default heute, `max=heute`); Anzeige „Bezahlt am X — Umsatz zählt zu MM/YYYY".
   Nachträgliche Korrektur: Status „Bezahlt" erneut speichern mit korrigiertem Datum.
3. `index.twig`: Zahlungsdatum (💶 TT.MM.JJJJ) unter dem Status-Dropdown bei bezahlten.
4. `revenueDateExpr()`: Analyse-Charts nutzen jetzt dieselbe Basis
   `DATE(COALESCE(paid_at, issue_date))` wie KPIs und Steuerexport (mit issue_date-Fallback
   wenn Migration 006 fehlt).

### Merkregel (dauerhaft)
**Umsatz-/Steuermonat = Zahlungsdatum (`paid_at`), nicht Rechnungsdatum.**
Jede neue Umsatz-Auswertung MUSS `revenueDateExpr()` bzw. `COALESCE(paid_at, issue_date)`
nutzen — nie `issue_date` allein für „paid"-Umsätze.

### Hinweis „Umsatz diese Woche"
Rollierendes Mo–So-Fenster (ISO-Woche) — überlappt Monatsgrenzen. Eine am 01.07.
gebuchte Zahlung erscheint in der Woche 29.06.–05.07. UND im Juli-Monatsumsatz.

### Nachtrag 2026-07-02 (Screenshot-Review /rechnungen): weitere Inkonsistenzen behoben
1. **Wochen-KPI-Untertitel log:** Kachel zeigte „letzte 7 Tage", Code rechnet aber
   „seit Montag" (ISO-Woche). Untertitel jetzt: „seit Montag (TT.MM.)" — erklärt auch,
   warum die Woche (Mo 29.06.–) mehr zeigen kann als der laufende Monat (ab 01.07.).
2. **Alle Charts auf Zahlungsdatum umgestellt** (`revenueDateExpr()`, Bezahlt-Serie):
   - `getMonthlyChartData()` — 12-Monats-Chart auf /rechnungen
   - `getChartDataByStatus()` — Dashboard-Charts (monatlich + wöchentlich)
   - `getChartData()` — Dashboard-API `/api/dashboard/chart`
   - `getRevenueForForecast()` — Finanz-Analyse Forecast
   - `getOwnerMonthlyRevenue()` — Top-Tierhalter-Monatsverlauf
   Offen/Überfällig/Entwurf-Serien bleiben bei `issue_date` (kein Zahlungsdatum vorhanden).
3. **Bezahlt-Split-Kacheln („Rechnung bezahlt" + „Barzahlung") summierten nicht auf
   den Gesamtumsatz** (Screenshot: 4.349,50 + 2.010 = 6.359,50 ≠ 6.641): Rechnungen mit
   `payment_method` NULL/'' fielen in keinen Bucket. Regel jetzt: bar = explizit 'bar',
   Rechnung = alles andere (`payment_method IS NULL OR <> 'bar'`).
4. `revenueDateExpr()` mit Request-Memo-Cache (ein Spalten-Probe-Query pro Request).

---

## Bug: Termin-Erinnerungen inkonsistent — keine / doppelt / falsche Uhrzeit (Juli 2026)
**Status:** `fixed`
**Dateien:**
- `plugins/calendar/AppointmentRepository.php` (`update()`, neu: `claimReminder()`, `releaseReminder()`)
- `plugins/calendar/ReminderService.php` (`processPending()` — Claim-before-Send)
- `plugins/google-calendar-sync/GoogleSyncService.php` (Pull-Update: reminder_sent-Reset)

### Symptom (Support-Ticket 2026-07-02)
Drei Termine, drei Verhaltensweisen: Termin A erhielt KEINE 24h-Erinnerung, Termin B
funktionierte, Termin C erhielt ZWEI Erinnerungen mit unterschiedlichen Uhrzeiten
(15:30 und 17:00 — echter Termin 17:00, vermutlich verschoben).

### Ursachen (3 strukturelle Bugs)

**Bug 1 — Mark-after-Send + parallele Auslöser → Doppelversand:**
Kalender-Erinnerungen werden von DREI Auslösern verarbeitet, die sich überlappen können:
1. SaaS-Dispatcher → `/kalender/cron/erinnerungen`
2. PraxisCron (SaaS) → gleicher Endpoint
3. **CronPixelController** — 1x1-GIF bei JEDEM Seitenaufruf, Drosselung 15min via
   `cron_job_log`-Lookup, der NICHT atomar ist (zwei gleichzeitige Seitenaufrufe
   passieren beide den `isDue()`-Check)

`ReminderService` markierte `reminder_sent=1` erst NACH dem (sekundenlangen) SMTP-Versand.
Parallele Läufe lasen dieselben offenen Erinnerungen → Doppel-Mails. Zusätzlich: Bricht
der Prozess nach SMTP-Versand aber vor dem Markieren ab (Timeout), wird beim nächsten
Lauf erneut versendet — hat sich der Termin zwischenzeitlich verschoben, zeigen die
beiden Mails unterschiedliche Uhrzeiten (exakt Symptom C).

**Bug 2 — Terminverschiebung setzte reminder_sent nie zurück:**
`AppointmentRepository::update()` (genutzt von apiUpdate UND apiReschedule/Drag&Drop)
schrieb `reminder_sent` nicht. Folgen:
- Erinnerung bereits raus, Termin danach verschoben → KEINE Erinnerung für die neue
  Zeit (Symptom A) bzw. Kunde kennt nur die alte Uhrzeit
- Erinnerung noch nicht raus → sie kommt später mit der neuen Zeit (Zufall, ob korrekt)

**Bug 3 — Google-Sync-Pull dito:** Verschiebung in Google Calendar → Pull schrieb
`start_at` neu, `reminder_sent` blieb 1 → keine korrigierte Erinnerung.

### Fix
1. **Claim-before-Send (atomar):** `claimReminder()` = `UPDATE ... SET reminder_sent=1
   WHERE id=? AND reminder_sent=0` → nur der Prozess mit rowCount=1 sendet. Bei
   Versandfehler `releaseReminder()` → Retry im nächsten Lauf solange Fenster offen.
   Prozessabbruch nach Claim = maximal EINE verlorene statt doppelter Mails.
2. **Reset bei Zeitänderung:** `update()` setzt `reminder_sent = IF(start_at <=> ?,
   reminder_sent, 0)` — steht in der SET-Liste VOR `start_at=?` (MySQL wertet SET
   links-nach-rechts aus, IF vergleicht also gegen den ALTEN Wert). Verschobene
   Termine bekommen automatisch eine neue Erinnerung mit korrekter Uhrzeit.
3. **Google-Sync-Pull:** gleicher IF-Reset im UPDATE.

### SQL-Pattern (merken!)
```sql
UPDATE appointments SET
  reminder_sent = IF(start_at <=> :neu, reminder_sent, 0),  -- MUSS vor start_at stehen!
  start_at = :neu, ...
```

### Verbleibende bekannte Lücke (by design)
Schlägt der Versand bis zum Terminbeginn dauerhaft fehl (`start_at > NOW()`-Filter),
gibt es keine Erinnerung — nach Terminbeginn wäre sie sinnlos. Fehler stehen im
error_log (`[ReminderService] FAILED ...`) und im Cron-Log.

---

## Verlinkungen
- [[15-agent-rules/update-brain]]
- [[11-decisions/decision-log]]
