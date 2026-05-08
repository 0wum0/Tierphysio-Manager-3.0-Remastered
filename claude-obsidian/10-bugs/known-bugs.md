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

## Verlinkungen
- [[15-agent-rules/update-brain]]
- [[11-decisions/decision-log]]
