# 3D-Befundmodell & NRS-Schmerzskala

## Feature-Übersicht
Interaktives SVG-Anatomiemodell mit Schmerzskala im Befundbogen.

## Technischer Stack
- **Frontend:** Vanilla JS (`befund-anatomy.js`), CSS (`befund-anatomy.css`)
- **Template:** `templates/befunde/form.twig`
- **Kein** neues Backend — nutzt bestehende `schmerz_nrs`-Spalte via `collectFelder()`

## Flow

### Öffnen (aus Patient-Modal)
1. Patient-Modal → Tab „Befundbögen" → „Befundung starten"
2. Tierauswahl-Modal öffnet sich (Hund / Katze / Pferd)
3. Klick auf Tier → JS-Handler ruft `window.location.href = '/patienten/{id}/befunde/neu?species={key}'` auf
4. Controller `BefundbogenController::create()` liest `?species=` Parameter, gibt `anatomy_species` an `form.twig`
5. Seite rendert SVG-Silhouette server-seitig + lädt `befund-anatomy.js`

### Initialisierung (befund-anatomy.js)
- IIFE mit `window.__befundAnatomyBooted`-Guard (verhindert Doppel-Init)
- `boot()` → `initAnatomy(ROOT)` bei DOMContentLoaded
- Liest State aus Hidden-Inputs (`anatomy_species`, `anatomy_markers`, `anatomy_drawings`) + `schmerz_nrs`
- Rendert: Toolbar → Stage → Legend → NRS-Scale → Marker-List

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
