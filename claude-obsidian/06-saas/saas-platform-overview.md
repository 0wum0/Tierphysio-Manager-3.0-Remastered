# SaaS Platform Overview

## Beschreibung
Admin-Plattform für Tenants, Pläne, Lizenz-API, Provisioning und SaaS-Abrechnung.

## Zweck
Trennung zur Praxis-App dokumentieren und SaaS-only Verantwortungen schützen.

## Relevante Dateien im Repo
- `saas-platform/app/Routes/web.php`
- `saas-platform/app/Services/TenantProvisioningService.php`
- `saas-platform/app/Services/PaymentService.php`
- `saas-platform/app/Repositories/TenantRepository.php`
- `saas-platform/cron/cron_runner.php`

## Datenfluss
SaaS Admin UI → SaaS Controller/Service → SaaS DB + Tenant-Provisioning + Lizenz/API + Zahlungsprozesse.

## Wichtige Regeln
- In `saas-platform` kein `$db->prefix()` verwenden.
- Tenant-Prefix wird dort als String-Parameter weitergereicht.

## Risiken
- SaaS/Praxis-Verantwortungen werden vermischt.
- Lizenz- oder Zahlungslogik unabsichtlich gebrochen.

## Tenant-seitige Routen (therapano.de)

Alle öffentlichen Routen für Kunden werden in `TenantAuthController` und `TenantAccountController` verwaltet.
In `saas-platform/app/Routes/web.php` registriert:

| Route | Methode | Controller |
|---|---|---|
| `/` | GET | TenantAuthController::landing |
| `/login` | GET/POST | TenantAuthController::loginForm/login |
| `/logout` | GET | TenantAuthController::logout |
| `/forgot-password` | GET/POST | TenantAuthController::forgotForm/forgotSubmit |
| `/reset-password` | GET/POST | TenantAuthController::resetForm/resetSubmit |
| `/account` | GET | TenantAccountController::index |
| `/account/update` | POST | TenantAccountController::update |
| `/account/change-password` | POST | TenantAccountController::changePassword |
| `/account/change-plan` | POST | TenantAccountController::changePlan |

## E-Mail System

- `MailService` liest SMTP-Kreds aus ENV: `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`
- Willkommens-E-Mail wird in `TenantProvisioningService::provision()` gesendet
- Mail-Fehler werden seit Branch `claude/saas-email-password-reset` per `error_log()` geloggt (vorher still geschluckt)
- DB-Spalten `reset_token` + `reset_token_expires_at` in `tenants`-Tabelle per Migration `002_tenant_auth_columns.sql`

## TODOs
- SaaS-Feature-Gating und Plan-Matrix separat dokumentieren.
- SMTP-Konfiguration auf Produktion verifizieren (MAIL_HOST etc. in .env)

## Verlinkungen
- [[06-saas/tenant-provisioning]]
- [[08-billing/billing-and-stripe]]
- [[01-architecture/multi-tenant-and-domains]]
