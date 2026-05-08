# 3D-Befundmodell & NRS-Schmerzskala

## Feature-Übersicht
Interaktives SVG-Anatomiemodell mit Schmerzskala im Befundbogen.

## Technischer Stack
- **Frontend:** Vanilla JS (`befund-anatomy.js`), CSS (`befund-anatomy.css`)
- **Template:** `templates/befunde/form.twig`
- **Kein** neues Backend — nutzt bestehende `schmerz_nrs`-Spalte via `collectFelder()`

## Flow

### Öffnen (aus Patient-Modal) — KEIN Seitenwechsel
1. Patient-Modal → Tab „Befundbögen" → „Befundung starten"
2. Tierauswahl-Modal öffnet sich (Hund / Katze / Pferd)
3. Klick auf Tier → `openAnatomyInModal(patientId, species)` — kein `window.location.href`
4. `#befund-anatomy`-HTML wird inline in `#pd-befunde-list` aufgebaut
5. CSS (`/assets/css/befund-anatomy.css`) + Stage-CSS + JS dynamisch geladen (einmalig)
6. Erstes Laden: `boot()` init automatisch; Folge-Opens: `window.befundAnatomyInit(root)` direkt
7. „Befund speichern" → AJAX POST → `BefundbogenController::store()` → JSON `{success, id}` → Liste neu laden

### Initialisierung (befund-anatomy.js)
- IIFE mit `window.__befundAnatomyBooted`-Guard (verhindert Doppel-Init beim normalen Seiten-Load)
- `boot()` → `initAnatomy(ROOT)` wenn `#befund-anatomy` im DOM vorhanden
- `window.befundAnatomyInit(root)` am Ende der IIFE: Re-Init im Modal ohne IIFE-Guard-Problem
- Liest State aus Hidden-Inputs (`anatomy_species`, `anatomy_markers`, `anatomy_drawings`) + `schmerz_nrs`
- Rendert: Toolbar → Stage → Legend → NRS-Scale → Marker-List

### Behobener Bug: TDZ-ReferenceError (Commit 5d810b0)
`let drawingPath = null` war nach dem try-Block (Zeile 340), wurde aber darin via
`renderStage()` → `renderOverlay()` bereits gelesen. JavaScript TDZ für `let` wirft
`ReferenceError: Cannot access 'drawingPath' before initialization` → catch-Block →
„Die interaktive Anatomie konnte nicht geladen werden."
**Fix:** `let drawingPath = null` vor den try-Block verschoben (Zeile 193).

### Schmerzskala (NRS 0–10)
- Container: `.anatomy-nrs-scale` in `#befund-anatomy`
- 11 Buttons (0–10), farbkodiert grün → rot via CSS Custom Property `--nrs-color`
- Gespeicherter Wert wird als `.active`-Button markiert
- Click: `state.nrs = i` + direktes Update von `input[name="schmerz_nrs"]`
- Speicherung: Form-Submit → PHP `collectFelder()` → `befundbogen_felder` KV-Store

## State-Objekt
```javascript
state = {
    species:  'dog' | 'cat' | 'horse',
    markers:  Array<{id, x, y, color, note, createdAt}>,
    drawings: Array<{color, points: [x,y][]}>,
    tool:     'marker' | 'draw' | 'erase',
    color:    hex string,
    nrs:      0–10 | null,
}
```

## Dateien
| Datei | Rolle |
|---|---|
| `templates/partials/patient-modal-global.twig` | Tierauswahl-Modal + Click-Handler |
| `templates/befunde/form.twig` | Form + Anatomy-Card inkl. `.anatomy-nrs-scale` |
| `public/assets/js/befund-anatomy.js` | Gesamte Interaktivität |
| `public/assets/css/befund-anatomy.css` | Styles für Stage, Toolbar, NRS, Marker |
| `app/Controllers/BefundbogenController.php` | `create()` + `collectFelder()` |
| `app/Repositories/BefundbogenRepository.php` | `saveFelder()` KV-Store |

## Offene TODOs
- `show.twig`: NRS-Wert noch als Plain-Zahl — visuelle Read-only-Scale wäre UX-Verbesserung
- Anatomy-Zeichnungen in PDF-Export noch nicht berücksichtigt
