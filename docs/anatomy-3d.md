# 3D-Schmerzanalyse: Modelle und Landmarken

## Ursache und Korrektur

Die ursprünglichen Regions-GLBs sind jeweils **ein einziges texturiertes Mesh**, keine benannten oder
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

Der Viewer hält geladene komprimierte Modelldateien im Byte-Cache.
Beim Wechsel werden GPU-Geometrien, Texturen und ImageBitmaps freigegeben.
Veraltete Requests können keine neue Tierart/Patientenansicht überschreiben.
Pixelratio ist auf 1,5 begrenzt; bei den Regionsmodellen entfallen Schattenpässe. Nur Änderungen lösen
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

## Zonen-Schalter und Erreichbarkeit

Der Zonen-Schalter ist ein echtes, initial aktiviertes Kontrollkästchen. Sein
Zustand und die Punktsichtbarkeit werden gemeinsam gesetzt. Ohne gespeicherten
Befund verschwinden Punkte bei ausgeschaltetem Haken vollständig, einschließlich
ihrer Trefferfläche. Mit Haken erscheinen sie deckend und behalten beim Zoomen
ungefähr 10 CSS-Pixel Durchmesser (Touch: 12), statt auf Mobilgeräten zu winzigen,
kaum sichtbaren Punkten zu schrumpfen.

Die Auswahl „Alle … Regionen auswählen“ enthält sämtliche hinterlegten Punkte
(47 Hund, 44 Katze, 52 Pferd), einschließlich verdeckter Punkte. Eine Auswahl
aktiviert die Zonen und richtet die Kamera auf die entsprechende Körperseite.
Die Oberfläche bleibt tiefengeprüft: rückseitige Punkte werden nicht durch den
Körper hindurch als vermeintlich vordere Punkte angezeigt. Der Browser-Test
schaltet den Haken aus/an, prüft die Touch-Punktgröße und öffnet jede hinterlegte
Region in beiden Viewern.

## Muskelflächen (6. Oktober 2026)

Der Hund startet jetzt in der Ansicht **Einzelmuskeln**. `Hund-Muskeln.glb`
enthält 262 getrennte Oberflächen; davon sind 250 Muskeloberflächen zu 225
benannten, auswählbaren Strukturen zusammengefasst. Mehrere Teilflächen einer
Quellstruktur teilen eine Befund-ID. Augen, Nase, Bänder, Sehnen und Faszien
werden nicht als zusätzliche Muskeln gezählt. Das ist der Umfang dieses
Quellmodells, keine Behauptung eines vollständigen veterinäranatomischen Atlas.
Einige Strukturen sind im Quellmodell über die Mittellinie zusammengefasst;
sie erhalten keine künstlich erfundene Links-/Rechts-Aufteilung.

Quelle: https://github.com/vittorione94/MusculoskeletalDog
Quellcommit: `2e87897e78cc99ecfcab930869a5b68c0fb1605a`.
V. La Barbera et al., *Motion Tracking with Muscles: Predictive Control of a
Parametric Musculoskeletal Canine Model* (2025). MIT-Lizenz liegt in beiden
Modellpaketen unter `licenses/MusculoskeletalDog-MIT.txt`. Die SKN-Oberflächen
werden in ihrer globalen Bind-Pose übernommen (Achsenwechsel Y/Z/X). Keine
Simulation, keine automatische anatomische Segmentierung, kein Übertragen der
Hundemuskeln auf andere Tierarten. Nomenklaturkorrekturen und Zusammenfassung
von Teilflächen sind im Konverter nachvollziehbar; Quellnamen bleiben erhalten.

Reproduktion (Python-Standardbibliothek und glTF Transform CLI 4.5.1):

```sh
python scripts/convert-canine-muscles.py /path/to/MusculoskeletalDog
npx --yes @gltf-transform/cli@4.5.1 meshopt public/assets/3D/Hund-Muskeln.glb /tmp/Hund-Muskeln.glb
npx --yes @gltf-transform/cli@4.5.1 simplify public/assets/3D/Hund-Skelett.glb /tmp/skeleton.glb --ratio 0.4 --error 0.001
npx --yes @gltf-transform/cli@4.5.1 meshopt /tmp/skeleton.glb /tmp/Hund-Skelett.glb
npx --yes @gltf-transform/cli@4.5.1 meshopt public/assets/3D/Hund-Haut.glb /tmp/Hund-Haut.glb
cp /tmp/Hund-Muskeln.glb /tmp/Hund-Skelett.glb /tmp/Hund-Haut.glb public/assets/3D/
cp public/assets/3D/Hund-Muskeln.glb public/assets/3D/Hund-Skelett.glb public/assets/3D/Hund-Haut.glb flutter_app/assets/3d/models/
cp public/assets/js/anatomy-dog-muscles.js flutter_app/assets/3d/anatomy-dog-muscles.js
```

Keine `join`-/`flatten`-Optimierung verwenden: benannte Oberflächen und die
`muscleId`-Metadaten müssen getrennt erhalten bleiben. Das komprimierte zusätzliche
Muskelmodell misst mit den ergänzten Faser-UVs rund 2,04 MB. Die kleine
Fasertextur wird lokal erzeugt und benötigt keinen Download. Materialien
werden pro Muskeloberfläche geklont, damit ein Befund nicht alle Muskeln färbt.
Schmerzfarben verwenden dieselbe Skala wie das Formular, mit konstanter leichter
Eigenleuchtwirkung ohne zusätzlichen Bloom-Renderpass. Unbefundete Muskeln sind
in natürlichem Rot dargestellt; ein expliziter NRS-0-Befund ist grün und bleibt löschbar.

Direkte Auswahl erfolgt über den ersten sichtbaren Oberflächentreffer. Die
Auswahlliste enthält auch verdeckte Muskeln und stellt die ausgewählte Struktur
frei. Der Schalter „Auswahl freistellen“ zeigt den Körperkontext wieder an;
Schließen des freigestellten Formulars stellt die Gesamtansicht wieder her.
Zonenpunkte werden nur im bisherigen Regionsmodell verwendet.

**Katze und Pferd haben weiterhin keine segmentierten Muskelmodelle.** Ihre
44 bzw. 52 Regionen erhalten eine Flächenfärbung durch Zuordnung vorhandener
Oberflächenvertices zum jeweils nächsten kalibrierten Punkt. Grenzen werden
interpoliert. Das ist eine regionale Näherung, keine präzise Muskelkontur; der
Viewer weist darauf sichtbar hin. Hier wurden keine weiteren Muskelpunkte oder
tiefen Muskelschichten erfunden. Auch das bisherige Hundemodell ist als
Regionsansicht über den Schalter erreichbar.

Neue Hundebefunde nutzen IDs mit `dog_mesh_`. Alte IDs werden nicht umgedeutet.
Ein Klick auf einen vorhandenen Befund schaltet bei Bedarf automatisch zum
passenden Modell. Datenbank und API bleiben kompatibel. Vorschau färbt ohne
Speichern, Abbrechen stellt gespeicherte Farben wieder her, Entfernen setzt
zurück auf unbefundet. Beide Pakete enthalten denselben Katalog und dasselbe GLB;
ein neuer Flutter-Build ist erforderlich.

Erweiterte Browserprüfungen: Zuordnung aller 225 Strukturen zu 250 Meshes,
direkter Oberflächen-Raycast, unabhängige Gegenseite, Freistellen, NRS 0,
Farben nach Speichern/Neuladen/Löschen/Abbrechen, regionale Farbattribute,
Mobilformular sowie bestehende Regions-/API-/Race-/CSP-Regressionstests.

## Katze/Pferd: technische Vorbereitung und fehlende Assets

`anatomy-models.js` registriert segmentierte Modelle getrennt nach Tierart.
Modellpfad, Katalog und anatomische Linksrichtung sind je Tierart konfigurierbar.
Flächenauswahl, Farbgebung, Freistellen, Befundliste und Modellwechsel sind damit
nicht mehr auf Hund-IDs festgelegt. Vor dem Anzeigen wird geprüft, ob jede
Katalog-ID zu mindestens einer tatsächlichen Mesh-Oberfläche gehört und ob
Tierart-Präfix, Seitenangabe und Fokuskoordinaten gültig sind. Falsche oder
unvollständige Zuordnungen zeigen einen Ladefehler statt falsch beschrifteter
Muskeln. Das prüft die technische Konsistenz, nicht die anatomische Richtigkeit.

Der Browser-Test registriert ausschließlich in seinem eigenen Seitenkontext je
zwei **synthetische Boxen** für Katze und Pferd. Damit werden Tierart-Routing,
getrennte Farben, Freistellen und die Ablehnung eines fremden Tierart-Katalogs
geprüft. Diese Testkörper werden nicht ausgeliefert und sind keine Tiermodelle.

Es sind weiterhin **keine segmentierten Katzen-/Pferde-Assets eingebaut**.
Recherche und Zugriffstest am 6. Oktober 2026:

- Katze: [Feline Ecorche Dec, westerly](https://sketchfab.com/3d-models/feline-ecorche-dec-4c08824eb4914ff4b6e71b4dc2ba080e).
  Die öffentliche Sketchfab-v3-Metadaten-API nennt CC BY 4.0 und Downloadbarkeit.
  Der offizielle Download-Endpunkt antwortet ohne Anmeldung mit HTTP 401.
  Der Autor bezeichnet es als grobes Übungsmodell und nennt ausgelassene tiefe
  Muskeln. Deshalb sind nach einem autorisierten Download sowohl Mesh-Aufteilung
  als auch Nomenklatur und Eignung zu prüfen; vollständige Abdeckung ist nicht
  zugesagt.
- Pferd: [Horse Ecorche, Johnson Martin](https://superhivemarket.com/products/horse-ecorche).
  Die Produktseite beschreibt getrennte, beschriftete Muskelgruppen und Knochen.
  Kaufmodell; keine Datei heruntergeladen und keine Lizenz erworben. Vor einem
  Einsatz müssen insbesondere die Rechte zur Auslieferung der 3D-Dateien im Web,
  in Apps und in diesem öffentlichen Repository feststehen.

Nächster sachlicher Schritt ist daher die Bereitstellung der Katzen-Quelldatei,
danach die Prüfung/Zuordnung und Integration; entsprechend anschließend beim
Pferd. Kein deformiertes Hundemodell und keine erfundenen zusätzlichen Punkte
werden als Katzen-/Pferdeanatomie freigeschaltet.


## Hund: Gewebeschichten und Materialien

Das Forschungsmodell enthält nun zusätzlich das zugehörige Skelett und die
äußere Haut. Alle Schichten stammen aus demselben oben gepinnten MIT-Quellstand.
Die 160 verwendeten Skelett-Geometrien (ohne doppelte Augen) werden für einen
Renderaufruf zusammengeführt. Das ist keine Zählung anatomischer Knochen.
STL-Punkte werden über die lokale Geom-Position und die Body-Bind-Pose aus
`dog_skin.skn` ausgerichtet. Die XML-Ausgangspose der Simulation ist leicht
abweichend und wird deshalb nicht als Bind-Pose verwendet. Der Konverter prüft
die erwarteten Einheitsrotationen und unveränderte Mesh-Skalierung ausdrücklich.

| Datei | Übertragung | Ladezeitpunkt |
|---|---:|---|
| Hund-Muskeln.glb | 2,04 MB | Öffnen der Einzelmuskelansicht |
| Hund-Skelett.glb | 0,51 MB | Anschließend, standardmäßig sichtbar |
| Hund-Haut.glb | 1,02 MB | Erst beim Einschalten der Haut |

Zusätzliche Schichten werden unter denselben normalisierten Modellknoten
gehängt. Keine eigene Skalierung/Zentrierung, keine Änderung der 225 Befund-IDs
oder Fokuskoordinaten. Der Kameraausschnitt berücksichtigt zusätzliche
Ausdehnungen. Haut verwendet die Original-UVs und den eingebetteten 1024-px-Atlas.
Die Hautansicht blendet tiefere Geometrien aus, um Überschneidungen zu vermeiden;
nach dem Ausschalten werden die gewählten Schichten wieder sichtbar. Die
Freistellung eines Muskels hat Vorrang und wird beim Schließen zurückgenommen.

Unter **Gewebeschichten** lassen sich Haut, vorhandene Faszien, Muskeln,
Sehnen/Bänder und Skelett schalten. Die Faszien-Deckkraft ist einstellbar.
Gemeint sind ausschließlich die vorhandenen thorakolumbalen, aponeurotischen
und faszienassoziierten Quellflächen; kein vollständiges Fasziennetz wurde
hinzuerfunden. Auch der Schalter beschreibt diese Einschränkung.

`anatomy-materials.js` ergänzt rotbraune Muskeln, helles Bindegewebe und Knochen,
feine Farb-/Rauheits-/Reliefstruktur sowie dunkle Augen/Nase. Die kleinen
prozeduralen Texturen sind deterministisch. Ihre Ausrichtung folgt einer
Hauptachsenprojektion je Mesh: **optische Textur, keine anatomisch vermessenen
Faserverläufe**. Schmerzfarben ersetzen den Grundfarbton, erhalten aber Relief,
Textur und Beleuchtung. Die Eigenleuchtwirkung ist reduziert. Neutral bedeutet
Naturfarbe, während ein gespeicherter NRS 0 weiterhin grün ist.

Hemisphärenlicht, warmes Hauptlicht und ein einzelner 1024-px-Schattenpass geben
räumliche Orientierung. Rendering bleibt bedarfsgesteuert; kein Bloom und kein
zusätzlicher Netzwerkdienst. Ein Ladefehler einer Zusatzschicht lässt die
Muskelansicht benutzbar und erlaubt erneutes Einschalten. Modellwechsel und
Schließen brechen Requests ab; verspätet dekodierte Schichten werden entsorgt.

Browserprüfungen ergänzen bedarfsweises Hautladen, gemeinsame Transformation,
Skelettansicht, Schicht-/Deckkraftwechsel, Wiederherstellung nach Freistellung,
Ladefehler/Wiederholung und Wechsel der Tierart während eines Hautrequests.
Web und eingebettetes App-Bundle werden geprüft, einschließlich Handybreite.
Screenshots prüfen zusätzlich Hautatlas, Skelett, Gesamtkörper und Mobilansicht.

**Qualitätsgrenze:** Das ist ein besser dargestelltes Forschungsmodell, kein
fotorealistischer oder klinisch zertifizierter Anatomieatlas. Die grobe
Quellgeometrie und einzelne Überschneidungen zwischen Knochen und Muskeln bleiben
sichtbar. Vollständige, präzise Faszien und realistische Faserverläufe benötigen
entsprechende fachlich geprüfte Quelldaten; Materialeffekte können sie nicht
ersetzen. Katze und Pferd behalten den oben dokumentierten bisherigen Stand.
