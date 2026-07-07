# Push Notification System

## Status: Implementiert (Branch: claude/therapano-notification-system-l14zx1)

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
