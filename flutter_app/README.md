# Tierphysio Manager — Flutter App

Native Android (+ iOS) App für den Tierphysio Manager.

## Voraussetzungen

- [Flutter SDK](https://flutter.dev) ≥ 3.0.0
- Android Studio oder VS Code mit Flutter-Extension
- Ein laufendes Tierphysio Manager Backend (PHP)

## Setup

### 1. Dependencies installieren
```bash
cd flutter_app
flutter pub get
```

### 2. Server-URL konfigurieren
Die Standard-URL ist `https://ew.makeit.uno`. Beim Login kann die URL geändert werden.

### 3. Datenbank-Migration ausführen
Im PHP-Backend unter **Einstellungen → Updater** die Migration `017_mobile_api_tokens.sql` ausführen (oder manuell):
```sql
CREATE TABLE IF NOT EXISTS mobile_api_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    device_name VARCHAR(100) NOT NULL DEFAULT '',
    last_used DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NULL
);
```

### 4. App starten (Debug)
```bash
flutter run
```

### 5. APK bauen (Release)
```bash
flutter build apk --release
# APK liegt unter: build/app/outputs/flutter-apk/app-release.apk
```

## API-Endpoints (Backend)

Alle Endpoints unter `/api/mobile/` — Bearer Token Authentication:

| Methode | Endpoint | Beschreibung |
|---------|----------|--------------|
| POST | `/api/mobile/login` | Anmelden, Token erhalten |
| POST | `/api/mobile/logout` | Abmelden |
| GET | `/api/mobile/me` | Aktueller Nutzer |
| GET | `/api/mobile/dashboard` | Dashboard-Stats |
| GET/POST | `/api/mobile/patients` | Patienten Liste / Erstellen |
| GET/POST | `/api/mobile/patients/{id}` | Patient Detail / Bearbeiten |
| GET/POST | `/api/mobile/patients/{id}/timeline` | Akte anzeigen / Eintrag hinzufügen |
| GET/POST | `/api/mobile/owners` | Tierhalter Liste / Erstellen |
| GET/POST | `/api/mobile/owners/{id}` | Tierhalter Detail / Bearbeiten |
| GET/POST | `/api/mobile/invoices` | Rechnungen Liste / Erstellen |
| GET | `/api/mobile/invoices/{id}` | Rechnung Detail |
| POST | `/api/mobile/invoices/{id}/status` | Status ändern |
| GET/POST | `/api/mobile/appointments` | Termine Liste / Erstellen |
| POST | `/api/mobile/appointments/{id}` | Termin bearbeiten |
| POST | `/api/mobile/appointments/{id}/loeschen` | Termin löschen |
| GET | `/api/mobile/treatment-types` | Behandlungsarten |
| GET | `/api/mobile/settings` | Einstellungen |

## Projektstruktur

```
lib/
├── main.dart                    # App-Entry
├── core/
│   ├── router.dart              # Navigation (go_router)
│   └── theme.dart               # Material 3 Themes
├── services/
│   ├── api_service.dart         # HTTP-Client + alle API-Calls
│   └── auth_service.dart        # Login/Logout + Token-Storage
├── screens/
│   ├── login_screen.dart
│   ├── shell_screen.dart        # Bottom Nav + Rail (Tablet)
│   ├── dashboard_screen.dart
│   ├── patients/
│   │   ├── patients_screen.dart
│   │   ├── patient_detail_screen.dart
│   │   └── patient_form_screen.dart
│   ├── owners/
│   │   ├── owners_screen.dart
│   │   ├── owner_detail_screen.dart
│   │   └── owner_form_screen.dart
│   ├── invoices/
│   │   ├── invoices_screen.dart
│   │   ├── invoice_detail_screen.dart
│   │   └── invoice_form_screen.dart
│   └── calendar/
│       └── calendar_screen.dart
└── widgets/
    ├── search_bar_widget.dart
    └── species_icon.dart
```

## Features

- **Login** mit Server-URL, E-Mail und Passwort (Bearer Token, 90 Tage gültig)
- **Dashboard** mit Stats: Patienten, Umsatz, Rechnungen, Termine
- **Patienten** — Liste, Suche, Detail mit Akte, Erstellen/Bearbeiten
- **Tierhalter** — Liste, Suche, Detail mit Tierliste, Erstellen/Bearbeiten
- **Rechnungen** — Liste mit Filter, Detail, Erstellen mit Positionen, Status-Änderung
- **Kalender** — Monatsansicht, Termin-Liste, Neue Termine erstellen, Status/Löschen
- **Responsive** — Bottom Navigation (Smartphone), Navigation Rail (Tablet)
- **Dark Mode** — automatisch nach System-Einstellung
