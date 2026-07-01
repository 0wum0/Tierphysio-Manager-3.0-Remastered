# TheraPano Veterinary Anatomy Engine

> **STATUS-KORREKTUR (2026-07-01, Vollaudit gegen echten Code):**
> Diese Datei beschrieb Phase 2 (Three.js/3D) bisher fälschlich als "blockiert bis 3D-Modelle vorhanden".
> **Das ist falsch — Phase 2 ist bereits produktiv implementiert.** Es existiert ein eigenständiges,
> voll funktionsfähiges **3D-Schmerzanalyse-Feature** (separater Tab im Patient-Modal, nicht Teil des
> 2D-SVG-Befundbogens):
> - Echte GLB-3D-Modelle: `public/assets/3D/Hund.glb`, `katze.glb`, `Pferd.glb` (Three.js r160, GLTFLoader/DRACOLoader)
> - Viewer: `public/assets/js/anatomy-3d-viewer.js` (987 Zeilen) mit OrbitControls (Rotation/Zoom/Pan),
>   Raycasting auf klickbare Muskelregionen: **27 Regionen Hund, 24 Katze, 34 Pferd**, je mit
>   anatomischer Bezeichnung (z.B. „M. longissimus dorsi"), Seite (links/rechts/mittig/beidseitig)
> - Klick auf Region öffnet Schmerzformular: NRS 0–10, Schmerzart (10 Typen: Druckschmerz,
>   Bewegungsschmerz, Ruheschmerz, Verspannung, Verhärtung, Triggerpunkt, Schwellung, Wärme,
>   Schonhaltung, Unklar), Notizfeld
> - Backend: `app/Controllers/PainPoint3dController.php` + `PainPoint3dRepository.php`,
>   Tabelle `patient_3d_pain_points` (Migration `063_3d_pain_points.sql`), UPSERT je
>   `(patient_id, animal_type, muscle_group_id, side)`, CSRF-geschützt
> - Tab-Einbindung: `templates/partials/patient-modal-global.twig` Zeile 531 ff. (Tab "3D Schmerzanalyse"),
>   lazy-init beim Tab-Wechsel (Zeile 1295, 3733–3804)
> - **Einschränkung:** reines Web-Feature, keine Flutter-Spiegelung; läuft unabhängig/parallel zum
>   2D-SVG-Befundbogen ([[07-features/3d-befundmodell-schmerzskala]]) — keine Datensynchronisation zwischen beiden.
>
> Der Rest dieser Datei (ursprünglicher Phasenplan) bleibt als historischer Kontext stehen, ist aber
> für Phase 2 überholt — die Umsetzung ist bereits erfolgt, nicht mehr "geplant".

## Vision
Professionelles, layerbasiertes, veterinärmedizinisches Befundsystem für Hund, Katze und Pferd.
Ziel: Klinische Qualität vergleichbar mit easyVet, Provet Cloud, VisionVet — nicht Spielzeuggrafik.

---

## Phasenplan

### Phase 1 — SVG Layer Engine (implementierbar ohne externe Assets)
**Status: Bereit zur Implementierung**

Multi-layer SVG mit semantischen Körperzonen, umschaltbaren Layer-Gruppen und vollständiger
Kompatibilität zur bestehenden Markierungs-/NRS-Logik.

**Was diese Phase liefert:**
- Semantisch segmentierte Körperzonen (Kopf, Hals, Schulter, Thorax, LWS, Becken, Gliedmaßen, Gelenke)
- Layer-Toggle UI: Kontur / Muskelgruppen / Skelett-Overlay / Gelenk-Highlights
- Hover-Tooltips mit Zonenbezeichnungen
- Klickbare Regionen mit `data-region` Attributen für spätere KI-Auswertung
- Strukturell identisch zu Phase 2 (Austausch von SVG gegen GLTF ohne JS-Logik-Änderung)

**Wichtig:** Professionelle Qualität der Tiersilhouetten **ohne veterinärmedizinischen Illustrator**
ist nicht erreichbar. Phase 1 liefert korrekte Architektur + semantische Zonen +
professionelles UI — die finalen Assets kommen in Phase 1.5 (Illustrator/Lizenz).

### Phase 1.5 — Asset-Professionalisierung (Illustrator / Lizenz erforderlich)
**Status: Externes Asset-Sourcing notwendig**

Optionen für professionelle Veterinär-SVG-Assets:
- **Eigene Erstellung:** Veterinärmedizinischer Illustrator (Empfehlung: Freelancer mit Erfahrung
  in medizinischer Illustration, ~€500–2000 pro Tier)
- **Lizenzierung:** Shutterstock Medical / Adobe Stock (oft nur nicht-interaktiv)
- **Open Source:** Wikimedia Veterinary Anatomy SVGs (freie Nachnutzung, Qualität variiert)
- **Blender Self-Build:** Aufwand ~40–80h pro Tier, erfordert Blender-Kenntnisse

Bis Phase 1.5 abgeschlossen ist: Phase-1-SVG-Layer-System als Platzhalter mit
klar segmentierten Körperzonen und professionellem UI.

### Phase 2 — Three.js 3D Engine (erfordert GLTF/GLB Assets)
**Status: ✅ IMPLEMENTIERT UND PRODUKTIV** (siehe Status-Korrektur oben, verifiziert 2026-07-01)

**Realitäts-Check Three.js:**
- Three.js selbst: Open Source, CDN/NPM, kein Hindernis ✅
- GLTF/GLB Tiermodelle: **NICHT generierbar aus Code** ❌
- Müssen erstellt werden in: Blender, Maya, ZBrush
- Oder lizenziert von: TurboSquid, CGTrader, Sketchfab (€50–500 pro Modell)
- Veterinary-spezifische 3D-Anatomie: sehr selten, meist teuer oder nicht interaktiv

**Was Three.js liefern wird (wenn Modelle vorhanden):**
- Echtzeit-3D-Ansicht (Rotation, Zoom)
- Layer-Toggle: Haut / Muskulatur / Skelett / Nerven / Gelenke
- Raycasting für Regionenauswahl (ersetzt SVG-Click-Handler)
- Lichtsteuerung für medizinische Darstellung
- OrbitControls für 360°-Ansicht

**Technologie-Stack Phase 2:**
```
Three.js r168+ (ESM)
GLTFLoader
OrbitControls
Raycaster für Zonenselektion
Draco-Kompression für GLB-Assets
```

**Asset-Dateistruktur (geplant):**
```
public/assets/3d/
  dog/
    dog-base.glb           # Vollkörper-Silhouette
    dog-muscles.glb        # Muskelgruppen-Layer
    dog-skeleton.glb       # Skelett-Layer
    dog-joints.glb         # Gelenk-Highlights
  cat/
    cat-base.glb
    cat-muscles.glb
    ...
  horse/
    horse-base.glb
    horse-muscles.glb
    ...
```

### Phase 3 — AI + Advanced Analytics (Zukünftig)
- Heatmap-Overlay basierend auf Befunddaten
- Verlaufsvergleich Befund N vs. N-1
- ROM-Messungen (Range of Motion)
- Gangbildanalyse mit Video-Overlay
- TherapyCare AI (Schmerzvorhersage aus Befundverlauf)
- TrainingCare AI (Trainingsempfehlungen)
- Vorher/Nachher-Vergleich

---

## Architektur: SVG Layer Engine (Phase 1)

### Datenmodell

#### SILHOUETTES-Objekt (neue Struktur)
```javascript
const SILHOUETTES = {
    dog: {
        viewBox: '0 0 500 300',
        layers: {
            outline:   '<g id="layer-outline">...</g>',
            regions:   '<g id="layer-regions">...</g>',   // klickbare Zonen
            muscles:   '<g id="layer-muscles">...</g>',
            skeleton:  '<g id="layer-skeleton">...</g>',
            joints:    '<g id="layer-joints">...</g>',
        }
    },
    cat: { ... },
    horse: { ... },
};
```

#### Body Regions (semantisch, mit `data-region`)
| Region-Key | Deutsch | Tier |
|---|---|---|
| `kopf` | Kopf / Schädel | Alle |
| `hals` | Hals / HWS | Alle |
| `schulter` | Schulterblatt / Schultergelenk | Alle |
| `thorax` | Brustkorb / Rippenbereich | Alle |
| `bwl` | Brustwirbelsäule | Alle |
| `lws` | Lendenwirbelsäule | Alle |
| `isg` | Iliosakralgelenk | Alle |
| `becken` | Becken / Kruppe | Alle |
| `schwanz` | Schwanz / Rutenbereich | Alle |
| `vl-schulter` | Vordberbein Schulter | Alle |
| `vl-ellbogen` | Vorderbein Ellbogen/Karpalgelenk | Alle |
| `vl-pfote` | Vorderpfote / Vorderhuf | Alle |
| `hl-hufte` | Hüfte / Hüftgelenk | Alle |
| `hl-knie` | Kniegelenk / Sprunggelenk | Alle |
| `hl-pfote` | Hinterpfote / Hinterhuf | Alle |
| `bauch` | Bauch / Abdomen | Alle |

#### Region-SVG Struktur (Template)
```xml
<g id="layer-regions" class="anatomy-layer" data-layer="regions">
    <path
        data-region="kopf"
        data-label="Kopf / Schädel"
        class="anatomy-region"
        d="M..."
        fill="transparent"
        stroke="transparent"
        stroke-width="8"
    />
    <path data-region="hals" data-label="Hals" class="anatomy-region" .../>
    ...
</g>
```

Regionen sind transparent — hovern und klicken wird über CSS `.anatomy-region:hover { fill: rgba(37,99,235,.15) }` sichtbar.

#### State-Erweiterung (Phase 1)
```javascript
state = {
    species:       'dog' | 'cat' | 'horse',
    markers:       Array<{id, x, y, color, note, region?, createdAt}>,
    drawings:      Array<{color, points: [x,y][]}>,
    tool:          'marker' | 'draw' | 'erase',
    color:         hex string,
    nrs:           0–10 | null,
    activeRegion:  string | null,   // NEU: zuletzt geklickte Region
    visibleLayers: Set<string>,     // NEU: sichtbare Layer
}
```

#### Marker-Erweiterung
Jeder neue Marker speichert zusätzlich die geklickte Region:
```javascript
{ id, x, y, color, note, region: 'lws', createdAt }
```
→ ermöglicht spätere statistische Auswertung ("In 80% der Befunde: LWS betroffen")

### Layer-Toggle UI

```html
<div class="anatomy-layer-toggle">
    <button class="layer-btn active" data-layer="outline">Kontur</button>
    <button class="layer-btn active" data-layer="regions">Zonen</button>
    <button class="layer-btn" data-layer="muscles">Muskulatur</button>
    <button class="layer-btn" data-layer="skeleton">Skelett</button>
    <button class="layer-btn" data-layer="joints">Gelenke</button>
</div>
```

### Rendering-Architektur (Phase 1 SVG)

```
anatomy-stage
├── svg.anatomy-silhouette      z-index: 1  pointer-events: none
│   ├── g#layer-outline         Körperkontour, immer sichtbar
│   ├── g#layer-muscles         Muskelgruppen-Overlay (toggle)
│   ├── g#layer-skeleton        Skelett-Linien (toggle)
│   └── g#layer-joints          Gelenk-Kreise (toggle)
├── svg.anatomy-regions         z-index: 2  pointer-events: all
│   └── g#layer-regions         Klickbare transparente Zonen
└── svg.anatomy-overlay         z-index: 3  pointer-events: all
    └── Marker + Zeichnungen (bestehend, unverändert)
```

### Rendering-Architektur (Phase 2 Three.js)

```
#anatomy-3d-canvas              Three.js WebGL Canvas
├── Scene
│   ├── AmbientLight
│   ├── DirectionalLight
│   ├── Mesh: base-model        GLTF base (Silhouette)
│   ├── Mesh: muscles           GLTF Muskulatur (toggle)
│   ├── Mesh: skeleton          GLTF Skelett (toggle)
│   └── Mesh: joints            GLTF Gelenke (toggle)
└── Raycaster → onClick → region-name → state.activeRegion
```

SVG-Overlay für Marker bleibt auch in Phase 2 erhalten — wird über Canvas gelegt.

---

## Speicherstrategie (Datenbank)

### Bestehend (bleibt unverändert)
```
befundbogen_felder (KV-Store):
  anatomy_species   = dog|cat|horse
  anatomy_markers   = JSON Array
  anatomy_drawings  = JSON Array
  schmerz_nrs       = 0-10
```

### Erweiterung Phase 1 (neue Felder im KV-Store)
```
anatomy_active_layers = JSON Array ['outline','regions']
anatomy_markers       = JSON (erweitert mit region-Key)
anatomy_view_mode     = 'svg' | '3d'   (vorbereitung Phase 2)
```

### Migration
Keine DB-Migration nötig — KV-Store ist schema-agnostisch.
Neue Keys werden beim Speichern automatisch hinzugefügt.

---

## Timeline-Integration

Befundbögen erscheinen bereits in der Patienten-Timeline.
Phase 1: Region-Summary im Timeline-Eintrag ("LWS, ISG, Schulter betroffen")
Phase 2: 3D-Thumbnail im Timeline-Eintrag (Screenshot des 3D-Modells)
Phase 3: Verlaufsdiagramm über mehrere Befunde hinweg

---

## Tenant-Integration

- Alle Befunddaten sind tenant-prefixed via `t_{id}_befundbogen_felder`
- 3D-Assets liegen in `public/assets/3d/` (tenant-neutral, read-only)
- Kein tenant-spezifischer 3D-Content geplant

---

## Implementierungs-Priorität

| Priorität | Was | Abhängigkeiten |
|---|---|---|
| 1 | SVG Layer Architecture refactoring | Keine |
| 2 | Semantische Körperzonen (`data-region`) | #1 |
| 3 | Layer-Toggle UI | #1 |
| 4 | Region-Key im Marker speichern | #2 |
| 5 | Professionelle SVG-Assets | Illustrator/Lizenz |
| 6 | Three.js Integration | 3D-Assets |
| 7 | GLTF Layer-Loading | #6 + 3D-Assets |
| 8 | Raycasting für 3D-Zonen | #7 |
| 9 | Heatmap, AI, Verlauf | #2, #4, Datenbasis |

---

## Dateien (Phase 1)

| Datei | Änderung |
|---|---|
| `public/assets/js/befund-anatomy.js` | SILHOUETTES-Struktur → layer-basiert, `visibleLayers` im State |
| `public/assets/css/befund-anatomy.css` | `.anatomy-layer`, `.anatomy-region`, Layer-Toggle Styles |
| `templates/partials/patient-modal-global.twig` | Unverändert (Layer-Toggle ist im JS) |

## Dateien (Phase 2, geplant)

| Datei | Neu |
|---|---|
| `public/assets/js/befund-anatomy-3d.js` | Three.js Engine |
| `public/assets/3d/{species}/*.glb` | GLTF 3D-Assets |
| `public/assets/css/befund-anatomy-3d.css` | 3D-Canvas Styles |
