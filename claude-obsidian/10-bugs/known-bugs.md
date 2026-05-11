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

## Verlinkungen
- [[15-agent-rules/update-brain]]
- [[11-decisions/decision-log]]
