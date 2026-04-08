# TheraPano – Desktop App

Flutter-Desktop-App für **Windows**, **Linux** und **macOS** – basierend auf dem TheraPano Tierphysio-Manager.

## Features gegenüber der Mobile-App

| Feature | Desktop | Mobile |
|---------|---------|--------|
| Permanente Sidebar | ✅ immer sichtbar | ❌ Bottom-Nav |
| Keyboard-Shortcuts | ✅ Strg+1–9, Strg+F | ❌ |
| Fenstergröße anpassbar | ✅ min. 960×600 | ❌ |
| Live-Uhr mit Datum | ✅ in der Top-Bar | ✅ |
| Alle Nav-Punkte sichtbar | ✅ keine „Mehr"-Schaltfläche | ❌ |

## Keyboard-Shortcuts

| Shortcut | Aktion |
|----------|--------|
| `Strg+1` | Dashboard |
| `Strg+2` | Patienten |
| `Strg+3` | Tierhalter |
| `Strg+4` | Rechnungen |
| `Strg+5` | Kalender |
| `Strg+6` | Nachrichten |
| `Strg+7` | Warteliste |
| `Strg+8` | Mahnungen |
| `Strg+9` | Anmeldungen |
| `Strg+F` oder `Strg+K` | Globale Suche |
| `Strg+,` | Einstellungen |
| `Strg+\` | Sidebar ein-/ausklappen |

## Voraussetzungen

### Linux
```bash
sudo apt-get install libsecret-1-dev libjsoncpp-dev libgtk-3-dev
```

### Windows
Visual Studio 2019+ mit Desktop-Entwicklungs-Workload

### macOS
Xcode 14+

## Setup

```bash
cd flutter_desktop
flutter pub get
flutter run -d linux    # oder -d windows / -d macos
```

## Build

```bash
flutter build linux --release
flutter build windows --release
flutter build macos --release
```

## Unterschiede zur Mobile-App

- Kein `flutter_local_notifications` (In-App-Badges stattdessen)
- Kein `image_picker` → `file_picker` für Datei-Uploads
- Kein `video_player` (vereinfacht)
- `window_manager` für Fensterverwaltung
- `update_service.dart` öffnet GitHub-Releases statt APK zu installieren
