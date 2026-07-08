# Push Notification System

## Status: Implementiert + Großes Bugfix/Ausbau-Update (2026-07-08)

## Update 2026-07-08 — Push-System repariert & vervollständigt

### Warum kam nie die Browser-Permission-Abfrage?
1. **Web-App:** Der „Aktivieren"-Button lag unsichtbar im Glocken-Dropdown; kein automatisch sichtbarer Hinweis.
2. **Portal:** `Notification.requestPermission()` wurde OHNE User-Geste aufgerufen → moderne Browser unterdrücken das stillschweigend; kein Banner/Button vorhanden.
3. **SaaS-Admin:** hatte gar keinen Push-Client (kein PUSH_CONFIG, kein Service Worker).

### Fixes (Frontend)
- `public/assets/js/push-notifications.js`: **Floating-Permission-Prompt** — erscheint automatisch unten rechts, wenn `enableWebPush` && Permission `default` && nicht gesnoozed (localStorage `push_perm_snooze_until`, 3 Tage). Klick „Aktivieren" = User-Geste → echter Browser-Prompt → Subscribe + Geräteregistrierung. `cfg.serviceWorkerPath` optional.
- Portal-Layout: gestenloser requestPermission-Aufruf entfernt, push-notifications.css eingebunden.
- SaaS `base.twig`: PUSH_CONFIG + socket.io + Client eingebunden; Assets kopiert nach `saas-platform/public/{js,css,sw-push.js}`.

### Fixes (Backend / kritische Bugs)
- **Rolle 'admin' brach Geräteregistrierung**: `push_device_tokens.role` ist ENUM(therapeut,trainer,owner,saas_admin). PHP-JWT (app/Core/Application.php) normalisiert jetzt, push-thera Middleware ebenfalls (`normalizeRole`).
- **tenant_id-Ableitungen falsch/inkonsistent**: `session('tenant_id')` existiert nie (OwnerController, PatientController, InvoiceController → Push war tot); Regex `t_(\d+)_` scheitert an nicht-numerischen Slugs (HomeworkController, MobileApiController). Überall auf `PushNotificationService::currentTenantId()` = `abs(crc32(prefix))` umgestellt — identisch zum Browser-JWT.
- **resolveOwnerUserId** nutzt jetzt `owner_portal_users.id` (aktueller Prefix) statt `owners.mobile_user_id` mit falschem Prefix.
- **push-thera**: JWT-Claim `tenant_id: 0` (SaaS-Admin) wurde als „incomplete" abgelehnt (falsy-Check) → gefixt; Internal-Secret-Präzedenz beim Pairing: env gewinnt (Server verifiziert mit env); Socket.io-CORS nutzt jetzt corsService (DB-Origins) statt nur env.
- **SaaS**: `PushAdminNotificationService` liest jetzt auch `saas_settings` (Pairing-Fallback wie die App) + neue Methode `notifyTenantUser(prefix, userId, …)`; `saas-platform/app/Core/Application.php` baut `push_config`-Global (tenant_id 0, role saas_admin).

### Neue Events
| Event | Auslöser | Empfänger |
|---|---|---|
| `homework_completed` | Besitzer hakt Aufgabe im Portal ab | alle Therapeuten |
| `document_available` | Timeline-Eintrag mit Anhang (PatientController) | Besitzer |
| `new_package` | Paket angelegt (aktiv) / Paketkauf im Portal | alle Portal-Besitzer / Trainer |
| `new_training` | Kurs-Einschreibung im Portal | alle Trainer |
| `new_invoice` | Auto-Rechnung aus Kurs/Paket (DogschoolInvoiceService) | Besitzer |
| `feedback_reply` | SaaS-Admin antwortet auf Feedback | einreichender Praxis-User |
| `saas_feedback` | Praxis sendet Feedback | SaaS-Admins |
| `saas_new_registration` | Selbstregistrierung neue Praxis | SaaS-Admins |

### Neue Helfer
- `PushNotificationService::notifyAllOwners()` — alle aktiven Portal-Besitzer eines Tenants.
- `PushAdminNotificationService::notifyTenantUser()` — SaaS → einzelner Praxis-User via /internal/notify.

## Architektur

```
TheraPano PHP (Event-Hook)
    → PushNotificationService::dispatch()  [register_shutdown_function, non-blocking]
        → POST /internal/notify  (X-Internal-Secret)
                ↓
        push-thera Node.js  [repo: 0wum0/push-thera]
            ├── MySQL persist  (t_{id}_push_notifications)
            ├── Socket.io      → Live-Badge + Toast im Browser
            ├── FCM            → Android-App Push
            └── Web Push VAPID → Browser Push
```

## Neue Dateien (TheraPano)

| Datei | Beschreibung |
|---|---|
| `app/Services/PushNotificationService.php` | Haupt-Service — dispatcht Events an push-thera |
| `saas-platform/app/Services/PushAdminNotificationService.php` | SaaS-Variante (kein App\-Import, kein DB) |
| `migrations/078_push_notification_system.sql` | Per-Tenant push_notifications Tabelle + portal_check_notifications Spalten |
| `public/assets/js/push-notifications.js` | Frontend: Socket.io, Toast, Notification Center, Web Push |
| `public/assets/css/push-notifications.css` | Styles: Toast, Badge, Dropdown |
| `public/sw-push.js` | Web Push Service Worker |
| `templates/components/_notification_center.twig` | Glocken-Button + Dropdown |

## Geänderte Dateien (TheraPano)

| Datei | Änderung |
|---|---|
| `app/Core/Config.php` | push.server_url, push.internal_secret aus $_ENV |
| `saas-platform/app/Core/Config.php` | Gleich |
| `app/Core/View.php` | push_config Twig-Global für eingeloggte User |
| `templates/layouts/base.twig` | CSS, window.PUSH_CONFIG, Socket.io CDN, push-notifications.js |
| `app/Controllers/InvoiceController.php` | Hook: new_invoice, invoice_paid |
| `app/Controllers/HomeworkController.php` | Hook: new_homework |
| `app/Controllers/OwnerController.php` | Hook: new_owner_registered |
| `app/Controllers/PatientController.php` | Hook: new_patient |
| `app/Controllers/MobileApiController.php` | Hooks: appointment_booked, appointment_changed, appointment_cancelled |
| `saas-platform/app/Controllers/TenantController.php` | Hook: saas_new_practice |

## Event-Typen

| Event | Zielgruppe | Kanal |
|---|---|---|
| `new_invoice` | Besitzer | user:{id} |
| `invoice_paid` | Besitzer + Therapeuten | user + role |
| `new_homework` | Besitzer | user:{id} |
| `new_owner_registered` | Alle Therapeuten/Trainer | role:{tenant}:{role} |
| `new_patient` | Alle Therapeuten/Trainer | role:{tenant}:{role} |
| `appointment_booked` | Besitzer | user:{id} |
| `appointment_booked_staff` | Alle Therapeuten | role:{tenant}:{role} |
| `appointment_changed` | Besitzer | user:{id} |
| `appointment_cancelled` | Besitzer | user:{id} |
| `appointment_cancelled_staff` | Alle Therapeuten | role:{tenant}:{role} |
| `saas_new_practice` | SaaS-Admins | admin |

## Konfiguration (.env TheraPano)

```env
PUSH_SERVER_URL=https://push.therapano.de
PUSH_INTERNAL_SECRET=your_shared_secret
VAPID_PUBLIC_KEY=your_vapid_public_key
ENABLE_WEB_PUSH=true
```

## push-thera Server

Repo: `0wum0/push-thera`
Branch: `claude/therapano-notification-system-l14zx1`
PR: https://github.com/0wum0/push-thera/pull/1

Stack: Node.js + Express + Socket.io + firebase-admin + web-push + mysql2

Konfiguration: `.env.example` im Repo

Reminder Worker: `node src/workers/reminderWorker.js` (24h/2h/30min vor Termin, Duplikatschutz)

## Datenbankstruktur

Globale Tabellen (push-thera):
- `push_device_tokens` — FCM/WebPush Tokens
- `push_notification_preferences` — User-Einstellungen pro Typ
- `push_notification_deliveries` — Delivery-Tracking
- `push_appointment_reminders_sent` — Duplikatschutz für Erinnerungen

Pro-Tenant (TheraPano + push-thera):
- `t_{id}_push_notifications` — Notification-Verlauf

## Sicherheit

- Kein Patientenname, keine sensiblen Daten im Push-Text
- JWT required für alle Client-Endpoints und Socket.io-Verbindungen
- X-Internal-Secret für Server-zu-Server-Aufrufe
- Tenant-Isolation auf jeder DB-Query und Socket-Room
- Alle PHP-Hooks in try/catch — Push-Fehler brechen nie den Hauptrequest

## Pull Requests

- TheraPano: https://github.com/0wum0/Tierphysio-Manager-3.0-Remastered/pull/68
- push-thera: https://github.com/0wum0/push-thera/pull/1
