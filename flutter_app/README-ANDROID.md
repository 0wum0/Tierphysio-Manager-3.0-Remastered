# TheraPano Android 1.3.0

Die Flutter-App verwendet die vorhandene REST-API unter `https://app.therapano.de`.
Es handelt sich um die bestehende Praxis-App mit überarbeiteter Oberfläche,
nicht um eine separate Demo mit erfundenen Patientendaten.

## Oberfläche und Module

- Einheitliches helles und dunkles Design mit lokal gebündelter Inter-Schrift.
- Responsive Anmeldung, Dashboard und Navigation für Handy und Tablet.
- Termine und Schnellaktionen direkt auf der Startseite; Finanzübersicht bleibt erhalten.
- Alle vorhandenen Navigationseinträge sind über „Mehr“ durchsuchbar.
- Patienten, Tierhalter, Kalender, Rechnungen, Mahnungen, Nachrichten, Befunde,
  Hausaufgaben, Warteliste, Portal-Verwaltung, Therapy Care und die vorhandenen
  Hundeschul-Funktionen bleiben an die bestehenden API-Endpunkte angebunden.
- Der vorhandene 3D-Viewer und die Tiermodelle sind weiterhin offline gebündelt.
  Dieser Designstand ersetzt oder erweitert deren anatomische Inhalte nicht.

## Reproduzierbarer Build

Voraussetzungen: Flutter **3.47.6**, Java **17**, Android-SDK. Die benötigten
Android-/NDK-Versionen werden von Flutter und Gradle festgelegt.

```bash
flutter pub get --enforce-lockfile
flutter analyze --no-fatal-infos --no-fatal-warnings
flutter test
THERAPANO_PREVIEW=true flutter build apk --release --no-pub --no-tree-shake-icons
```

Ergebnis: `build/app/outputs/flutter-apk/app-release.apk`.
Die Option `--no-tree-shake-icons` erhält die dynamisch gewählten Symbole der
bestehenden Anwendung.

## Signierung

Ohne Produktionsschlüssel wird ausdrücklich eine Vorschau gebaut:

- Anzeigename: **TheraPano Vorschau**
- Paket: `de.tierphysio.manager.preview`
- Release-Modus mit Android-Entwicklungssignatur
- Parallel zur bisherigen App installierbar; ersetzt deren Daten nicht.
- Der automatische Produktions-Updater ist für dieses Paket deaktiviert.

Für eine reguläre Aktualisierung der bestehenden App wird ihr ursprünglicher
Signierschlüssel benötigt. Dazu `android/key.properties` konfigurieren und ohne
`THERAPANO_PREVIEW=true` bauen. Signierschlüssel und Kennwörter nie einchecken.
Der GitHub-Workflow verwendet Produktionssignierung nur bei vollständigen
`ANDROID_KEYSTORE_BASE64`, `ANDROID_STORE_PASSWORD`, `ANDROID_KEY_ALIAS` und
`ANDROID_KEY_PASSWORD` Secrets; sonst erzeugt er die separate Vorschau.

## Prüfung

Die Widget-Tests decken unter anderem Anmeldung und Fehlermeldungen,
Doppelklickschutz, Modulsuche für beide Praxisarten, verzögerte Suchanfragen,
Theme-Wechsel mit Erhalt des Formularzustands sowie Seitenwechsel über den
echten GoRouter-Shell-Navigator ab. Layouts werden ab 320 logischen Pixeln
Breite, auf Tablets und mit bis zu 180 % Textskalierung geprüft.

Dashboard-Tests verwenden ausdrücklich Testdaten. Erfolgreiche Widget-Tests
ersetzen keinen End-to-End-Test mit einem echten Praxis-Konto, Kamerazugriff,
Benachrichtigungen und physischem Android-Gerät.

Optional lassen sich bei den Widget-Tests PNGs für die Layoutprüfung erzeugen:

```bash
THERAPANO_SCREENSHOT_DIR=/tmp/therapano-preview flutter test
```
