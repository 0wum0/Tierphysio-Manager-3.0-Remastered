# File Index (Kern-Dateien)

## Beschreibung
Index der wichtigsten Dateien/Ordner und ihres Einsatzzwecks.

## Zweck
Schnelles Navigieren im Repo ohne blinde Volltextsuchen.

## Relevante Dateien im Repo
- `app/`, `templates/`, `plugins/`, `saas-platform/`, `flutter_app/lib/`

## Datenfluss
Anforderung → Bereich finden → Primärdateien prüfen → Änderungen planen.

## Wichtige Regeln
- Erst Primärdateien lesen, dann tiefer gehen.
- Keine Änderungen außerhalb der betroffenen Domäne.

## Risiken
- Falsche Startdatei führt zu Nebenwirkungen.

## TODOs
- Index bei neuen Top-Level-Modulen erweitern.

## Index

### Backend / Praxis
- `app/Routes/web.php` – zentrale Routen inkl. Mobile API.
- `app/Controllers/MobileApiController.php` – Mobile API Kernlogik.
- `app/Core/Database.php` – Prefix/DB/Safe-Methoden.
- `app/Repositories/*` – Datenzugriff pro Fachdomäne.

### Web / Templates
- `templates/layouts/base.twig` – Navigation/Feature-Gating.
- `templates/settings/index.twig` – Settings inkl. Mail/Cron-UI.
- `templates/dogschool/*` – Hundeschulmodule (Kurse, Leads, Training).

### Plugins
- `plugins/owner-portal/*` – Besitzerportal + Messaging + Booking.
- `plugins/therapy-care-pro/*` – Fortschritt, Reports, Reminder Queue.
- `plugins/mailbox/*` – Mailbox API/UI.
- `plugins/bulk-mail/*` – Sammelmail/Holiday-Flows.

### SaaS
- `saas-platform/app/Routes/web.php` – SaaS Admin/API/Webhooks.
- `saas-platform/app/Services/PaymentService.php` – Billing/Stripe/PayPal.
- `saas-platform/app/Services/TenantProvisioningService.php` – Tenant-Lifecycle.
- `saas-platform/provisioning/tenant_schema.sql` – Tenant-Schema.

### Flutter
- `flutter_app/lib/services/api_service.dart` – API-Verträge + Base URL.
- `flutter_app/lib/core/router.dart` – Navigation.
- `flutter_app/lib/screens/*` – Feature-Screens.
- `flutter_app/lib/core/terminology.dart` – Praxis/Hundeschule Begriffssystem.

### Migrationen
- `migrations/*` – Praxis-Migrationen.
- `saas-platform/migrations/*` – SaaS-Migrationen.

## Verlinkungen
- [[01-architecture/system-landscape]]
- [[02-api/mobile-api]]
- [[06-saas/saas-platform-overview]]
