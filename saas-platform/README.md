# Tierphysio SaaS – Verwaltungsplattform

Separate SaaS-Verwaltungsplattform für den Tierphysio Manager. Verwaltet Lizenzen, Abos und Praxis-Instanzen.

---

## Verzeichnisstruktur

```
saas-platform/
├── app/
│   ├── Controllers/        # HTTP-Controller (Auth, Dashboard, Tenant, Plans, Legal, Register, Installer, LicenseApi)
│   ├── Core/               # Framework (Application, Config, Database, Router, Container, View, Session, Controller)
│   ├── Repositories/       # Datenbankzugriff (Tenant, Plan, Subscription, License, Admin, Legal)
│   ├── Routes/             # Routen-Definitionen (web.php, installer.php)
│   └── Services/           # Geschäftslogik (LicenseService, MailService, TenantProvisioningService)
├── migrations/
│   └── 001_initial_schema.sql   # SaaS-Datenbankschema
├── provisioning/
│   └── tenant_schema.sql        # Schema für neue Praxis-Datenbanken
├── public/
│   ├── index.php           # Einstiegspunkt
│   └── .htaccess           # URL-Rewriting + Sicherheitsheader
├── storage/
│   ├── cache/              # Twig-Template-Cache
│   └── logs/               # Fehlerprotokolle
├── templates/
│   ├── admin/              # Admin-Panel-Templates (Dashboard, Tenants, Plans, Legal)
│   ├── auth/               # Login-Template
│   ├── errors/             # Fehlerseiten (403, 404)
│   ├── installer/          # Installations-Assistent
│   ├── layouts/            # Basis-Layouts (base.twig, public.twig)
│   ├── legal/              # Öffentliche Rechtsdokumente
│   └── register/           # Registrierungsfluss (Pläne, Formular, Erfolg)
├── .env.example            # Beispiel-Konfiguration
├── .gitignore
└── composer.json
```

---

## Installation

### 1. Composer-Abhängigkeiten installieren

```bash
cd saas-platform
composer install
```

### 2. Installations-Assistent aufrufen

Rufen Sie im Browser auf:
```
https://ihre-domain.de/install
```

Der Assistent führt Sie durch:
- Datenbankverbindung konfigurieren
- Datenbank & Schema erstellen
- Ersten Administrator anlegen
- `.env` automatisch schreiben

### 3. Nach der Installation

- Melden Sie sich unter `/admin` an
- Konfigurieren Sie ggf. die E-Mail-Einstellungen in `.env`
- Die Abo-Pläne sind bereits vorbefüllt (Basic, Pro, Praxis)

---

## Lizenz-Plugin (Praxissoftware)

Das Plugin `plugins/license-guard/` integriert die Lizenzprüfung nicht-invasiv in die Praxissoftware.

### Aktivierung

Das Plugin ist bereits in `plugins/enabled.json` eingetragen.

### Konfiguration

Nach dem Login in der Praxissoftware unter `/license-setup`:
- **SaaS-Plattform URL** – z. B. `https://saas.ihre-domain.de`
- **Tenant UUID** – steht in der Willkommens-E-Mail

### Funktionsweise

| Szenario | Verhalten |
|---|---|
| Online, Lizenz aktiv | Normalbetrieb, Token wird alle 24h erneuert |
| Offline < 30 Tage | Offline-Modus mit gecachten Daten |
| Offline > 30 Tage | Warnmeldung, Betrieb weiterhin möglich |
| Lizenz gesperrt/gekündigt | Warnmeldung wird angezeigt |

---

## API-Endpunkte (für Praxissoftware)

| Methode | Endpunkt | Beschreibung |
|---|---|---|
| `GET` | `/api/license/check?uuid={uuid}` | Schnelle Statusprüfung |
| `POST` | `/api/license/verify` | Token verifizieren (offline-sicher) |
| `POST` | `/api/license/token` | Neues Token ausstellen (API-Key erforderlich) |

---

## Datenbanken

| Datenbank | Beschreibung |
|---|---|
| `tierphysio_saas` | SaaS-Plattform (Tenants, Abos, Lizenzen, Admins) |
| `tierphysio_tenant_{slug}` | Pro Praxis eine eigene Datenbank |

---

## Sicherheit

- Alle Passwörter mit `password_hash` (bcrypt, cost 12)
- CSRF-Schutz auf allen POST-Formularen
- Lizenz-Token HMAC-SHA256 signiert
- Sessions: `httponly`, `samesite=Lax`, optional `secure`
- `.htaccess` blockiert direkten Zugriff auf `.env`, `.sql`, `.log`
- DSGVO-konform: EU-Hosting empfohlen, Zustimmung zu Datenschutz/AGB bei Registrierung

---

## Technologie-Stack

- **PHP** 8.3+
- **Twig** 3.x (Templates)
- **PDO** (Datenbankzugriff)
- **PHPMailer** (E-Mail)
- **Ramsey UUID** (Tenant-UUIDs)
- **vlucas/phpdotenv** (Umgebungsvariablen)
- **Bootstrap 5.3** (UI)
