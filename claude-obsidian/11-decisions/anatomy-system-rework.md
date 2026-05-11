# Entscheidung: Anatomy System Rework

**Datum:** Mai 2026
**Status:** Beschlossen
**Betrifft:** `public/assets/js/befund-anatomy.js`, `public/assets/css/befund-anatomy.css`

---

## Problem

### Warum der bisherige SVG-Ansatz ungeeignet war

Der initiale Ansatz (Commits vor d8f4f67) verwendete schematische SVG-Primitive:
- Körper: einfache `<ellipse>` Formen
- Beine: `<path>` Geraden mit abgerundeten Enden
- Keine semantische Segmentierung der Körperzonen
- Keine Layer-Struktur
- Keine `data-region` Attribute für Körperstellen

**Symptome:**
- Katze sah aus wie ein generisches Tier, nicht wie eine Katze
- Proportionen entsprechen keiner realen Tieranatomie
- Für veterinärmedizinischen Einsatz nicht geeignet
- Kein Unterschied zwischen Körperzonen für Befundmarkierungen

**Ursache:**
Programmatisch generierte SVG-Primitive können keine echte Anatomie abbilden.
Professionelle veterinärmedizinische Darstellungen erfordern entweder:
a) Hand-gezeichnete SVG-Pfade von einem veterinärmedizinischen Illustrator
b) 3D-Modelle aus Blender/ZBrush mit anatomisch korrekter Geometrie

---

## Entscheidung

### Gewählt: Zweistufige Strategie

#### Phase 1: SVG Layer Engine mit semantischen Zonen

**Begründung:**
- Implementierbar ohne externe Assets
- Liefert die Architektur für Phase 2
- Professionelles UI schon in Phase 1 erreichbar
- Semantische Körperzonen für KI-Auswertung vorbereiten
- Übereinstimmung mit Branchenstandard: easyVet, Provet Cloud, VisionVet nutzen alle SVG-basierte Body Maps

**Was Phase 1 bringt:**
- SILHOUETTES-Objekt wird layerbasiert (`outline`, `regions`, `muscles`, `skeleton`, `joints`)
- Klickbare transparente Zonen mit `data-region` Attributen
- Layer-Toggle UI (Kontur / Zonen / Muskulatur / Skelett / Gelenke)
- Marker speichern die geklickte Region für spätere Statistiken
- Architektur ist direkt kompatibel zu Phase 2 (GLTF/Three.js)

**Was Phase 1 NICHT liefert:**
- Fotorealistische Tierdarstellung (benötigt Illustrator)
- 3D-Rotation / Zoom
- Echte anatomische Korrektheit der Silhouetten

#### Phase 2: Three.js + GLTF/GLB

**Blockierende Abhängigkeit: Externe 3D-Assets**

Three.js allein reicht nicht. Es benötigt:
- `.glb` Dateien pro Tier pro Layer (Haut, Muskulatur, Skelett, Gelenke)
- Diese Dateien können NICHT aus Code generiert werden
- Müssen erstellt werden von:
  - Blender-3D-Künstler mit veterinärmedizinischem Hintergrund
  - Oder lizenziert von: TurboSquid, CGTrader (€50–500 pro Modell)
  - Oder Open-Source-Quellen (selten in Veterinärqualität)

**Entscheidung Phase 2-Timing:**
- Three.js Code-Integration erst wenn 3D-Assets vorhanden
- Kein "Three.js ohne Modelle" bauen — ergibt leere Szene, wäre nutzlos
- Phase 1 SVG-Layer-System bleibt dauerhaft als Fallback + Mobile-Lösung

---

## Abgelehnte Alternativen

### Canvas-basiertes Rendering
- Abgelehnt: Keine semantische Zonierung möglich, keine CSS-Interaktivität
- SVG ist für interaktive Körperkarten überlegen

### Externe Anatomy-SVG-Libraries
- z.B. "Human Anatomy Illustrator" Bibliotheken existieren für Humanmedizin
- Für Veterinärmedizin (Hund/Katze/Pferd) gibt es keine etablierten Open-Source-Bibliotheken
- Kommerzielle Veterinär-SVG-Atlanten: kostenpflichtig, Lizenzprüfung erforderlich

### Pixel-Bilder (PNG/JPEG)
- Abgelehnt: Nicht skalierbar, keine Interaktivität, nicht SVG-kompatibel mit bestehendem System

### Sofortiger Three.js-Wechsel
- Abgelehnt: Blockiert auf 3D-Assets, die noch nicht existieren
- Würde monatelange Entwicklungspause bedeuten

---

## Auswirkungen auf bestehenden Code

| Bereich | Auswirkung |
|---|---|
| Bestehende Befunddaten (DB) | Keine — KV-Store ist schema-agnostisch |
| NRS-Schmerzskala | Keine — bleibt unverändert |
| Marker-/Zeichnungslogik | Erweiterung (region-Key im Marker) — rückwärtskompatibel |
| patient-modal-global.twig | Keine Änderung notwendig |
| BefundbogenController.php | Keine Änderung notwendig |
| befund-anatomy.css | Erweiterung um Layer-Styles |
| befund-anatomy.js | SILHOUETTES-Objekt-Refactoring |

---

## Nächste Schritte

1. **Sofort (Phase 1):** SVG Layer Engine implementieren
   - SILHOUETTES-Objekt layerbasiert refactoren
   - `data-region` Attribute auf alle Körperzonen
   - Layer-Toggle UI in Toolbar
   - `state.activeRegion` + `state.visibleLayers` im State
   - CSS für Layer-Visibility + Region-Hover
   - Marker erweitern um `region`-Key

2. **Mittelfristig (Phase 1.5):** Professionelle Assets besorgen
   - Veterinärmedizinischen Illustrator beauftragen ODER
   - Lizenzen für veterinärmedizinische SVG-Atlanten prüfen

3. **Langfristig (Phase 2):** Three.js Engine
   - Erst wenn 3D-Assets vorhanden
   - `befund-anatomy-3d.js` als separates Modul
   - SVG bleibt als Fallback (mobile, low-end devices)
