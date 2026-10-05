# 3D-Schmerzanalyse: Modelle und Landmarken

## Ursache und Korrektur

Die GLBs sind jeweils **ein einziges texturiertes Mesh**, keine benannten oder
segmentierten Einzelmuskeln. Der Hund schaut nach +Z; Katze und Pferd nach -Z.
Die vorherigen Hundekoordinaten verwendeten die falsche Längsrichtung. Dazu
projizierte der Viewer Punkte radial vom Körpermittelpunkt auf irgendeinen
ersten Oberflächentreffer. Besonders Beinpunkte landeten dadurch am Rumpf oder
am Hals. Große unsichtbare Boxen waren auch durch die gegenüberliegende Seite
anklickbar. Ein Touch verwendete zudem den Hover-Zustand eines früheren Events.

`anatomy-landmarks.js` enthält jetzt explizite Oberflächenpunkte pro Modell.
Koordinaten beziehen sich auf eine um den Boundingbox-Mittelpunkt zentrierte
Szene, deren längste Achse zwei Einheiten misst. Anatomisch links ist beim Hund
+X, bei Katze/Pferd -X; beim gedrehten Pferdekopf zählt die lokale Oberfläche.
Das Pferd hat versetzte Beine: linke/rechte Gliedmaßen teilen **nicht** dieselben
Y/Z-Werte. Die Geometrie wurde je Körperregion in Seiten-, Frontal- und
Achsenansichten kontrolliert. Keine Projektion der Punkte im laufenden Viewer.

Vorhandene IDs bleiben erhalten. Fachbegriffe erscheinen in Tooltip, Formular,
Befundliste und bei neuen Speicherungen im übertragenen Gruppenlabel. Ergänzt
sind u. a. M. masseter, M. temporalis, M. splenius, M. brachiocephalicus,
M. trapezius, M. latissimus dorsi, M. obliquus externus abdominis und
M. quadriceps femoris, jeweils links/rechts. Dorsale Lendenpunkte heißen nicht
mehr M. iliopsoas; beim Pferd wird kein M. deltoideus behauptet.

**Geltungsbereich:** Die Punkte kennzeichnen Muskel-/Körperregionen. Die
vorliegenden texturierten Oberflächen bilden weder Muskelgrenzen noch tiefe
Muskelschichten verlässlich ab. Die Kalibrierung ist eine technische und visuelle
Zuordnung, keine veterinäranatomische Zertifizierung. Vor klinischer Freigabe
sollte eine Tierphysiotherapeutin die regionalen Zuordnungen am Modell abnehmen.
Für einzeln segmentierte Muskeln ist ein entsprechend aufgebautes Fachmodell
nötig. Frühere Befunde werden nicht automatisch anatomisch umgedeutet oder
umgeschrieben; aufgrund der früher falschen Anzeige sind sie fachlich zu prüfen.

Quellen für die Nomenklatur/regionale Einordnung:

- WAVA, Nomina Anatomica Veterinaria, 6. Auflage: https://www.wava-amav.org/index.html
- University of Minnesota, Dog and Cat Anatomy, External Thorax:
  https://open.lib.umn.edu/dogcatanatomylabguide/chapter/part-2-external-thorax/
- University of Minnesota, Neck:
  https://pressbooks.umn.edu/dogcatanatomylabguide/chapter/part-1-neck/
- University of Minnesota, Large Animal Anatomy, Pelvic Limb:
  https://pressbooks.umn.edu/largeanimalanatomy/chapter/pelvic-limb/

## Übertragungsgröße und Speicher

Dezimale MB; gemessene Dateigrößen, keine Behauptung über reale Netzwerklatenz:

| Modell | Vorher | Jetzt | Reduktion | Vertices jetzt | Dreiecke jetzt |
|---|---:|---:|---:|---:|---:|
| Hund | 57,43 MB | 2,75 MB | 95,2 % | 132.331 | 228.558 |
| Katze | 62,68 MB | 2,80 MB | 95,5 % | 136.562 | 232.601 |
| Pferd | 58,97 MB | 2,56 MB | 95,7 % | 137.416 | 236.198 |

Alle Texturen maximal 2048 px (vorher bis 8192 px), Meshopt-Kompression,
Mesh-Vereinfachung mit maximal 0,001 relativer Fehlertoleranz. Materialien und
Texturkoordinaten bleiben erhalten. Web und Flutter enthalten dieselben Bytes.
Der Decoder ist lokal gebündelt, inkl. MIT-Lizenz. Kein CDN zur Laufzeit.
`wasm-unsafe-eval` in der CSP erlaubt ausschließlich die WebAssembly-Compilation
für den Decoder; JavaScript-`unsafe-eval` wird nicht freigegeben.

Wichtig: Quantisierte GLBs tragen Dekodierungstransformationen an ihren Nodes.
Der Viewer normalisiert deshalb eine **übergeordnete Gruppe**, statt die
GLB-Transformationen zu überschreiben. Beibehalten, auch bei künftigen Assets.

Der Viewer hält nur die drei kleinen komprimierten Dateien im Byte-Cache.
Beim Wechsel werden GPU-Geometrien, Texturen und ImageBitmaps freigegeben.
Veraltete Requests können keine neue Tierart/Patientenansicht überschreiben.
Pixelratio ist auf 1,5 begrenzt; Schattenpässe entfallen. Nur Änderungen lösen
Rendering und Hover-Auswertung aus. ResizeObserver deckt Modal/Vollbild ab.

## Reproduktion und Tests

Originale: Commit `8b94133abe9151738384b3ab107788ec5f508a56`,
`public/assets/3D/{Hund,katze,Pferd}.glb`.

```sh
scripts/optimize-anatomy-models.sh /path/to/originals
node --check public/assets/js/anatomy-3d-viewer.js
node --check flutter_app/assets/3d/anatomy-3d-viewer.js
node tests/anatomy-viewer.cjs
```

Für den Browser-Test: Node 20+ und `playwright` mit Chromium installieren;
optional `CHROMIUM_EXECUTABLE=/path/to/chromium` setzen.
`ANATOMY_SCREENSHOTS=/existing/output/directory` speichert Desktop-/Mobilbilder.
Der Testserver verwendet nur simulierte API-Daten und die echte CSP.
Prüfungen: Tierarten, beide Seiten, Pfoten/Hufe, Touch ohne Hover, Speichern und
Neuladen abweichender Bestandsseiten, HTTP-Fehler, schnelle Tierartenwechsel,
Größenänderungen sowie Aufräumen beim Schließen. Beide Viewer werden geprüft.

Bei neuen Modellen: Landmarken **neu kalibrieren**, Web-/App-Daten abgleichen,
Browser-Test ausführen und Modell-/Modul-Versionsparameter erhöhen.
Die Oberfläche als Ganzes nach einem Modelltausch zu skalieren reicht nicht.

## Auslieferung

Änderungen gemeinsam ausliefern: Viewer, Landmarken, Decoder, komprimierte GLBs,
Twig-Versionsparameter und CSP. Die Webanwendung benötigt keine DB-Migration.
Für die eingebettete Flutter-Ansicht ist ein neuer App-Build erforderlich.
Die Tests hier prüfen WebView-Inhalte im Browser; kein physischer Android-/iOS-
Gerätetest und kein produktiver Datenbanktest wurden dadurch ersetzt.
