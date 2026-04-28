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

## TODOs
- SaaS-Feature-Gating und Plan-Matrix separat dokumentieren.

## Verlinkungen
- [[06-saas/tenant-provisioning]]
- [[08-billing/billing-and-stripe]]
- [[01-architecture/multi-tenant-and-domains]]
