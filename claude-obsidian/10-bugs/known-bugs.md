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
- Erste konkrete Bugliste aus Issues/Commit-Historie nachziehen.

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

### Offene TODOs
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

### Offene TODOs
- Server-seitige NRS-Anzeige in `show.twig` noch nicht visuell (nur Zahlwert) — könnte
  mit gleichem CSS-Pattern als read-only Scale dargestellt werden

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

### Offene Punkte
- Kein Video-Preview-Support (mp4 als Download-Karte dargestellt)
- Kein server-seitiges Image-Resize/Thumbnail

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

## Verlinkungen
- [[15-agent-rules/update-brain]]
- [[11-decisions/decision-log]]
