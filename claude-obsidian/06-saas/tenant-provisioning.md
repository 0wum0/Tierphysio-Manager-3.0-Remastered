# Tenant Provisioning

## Beschreibung
Lifecycle für neue/aktualisierte Tenants inkl. Schema- und Feature-Initialisierung.

## Zweck
Fehlerfreie Tenant-Erstellung und kontrollierte Migrationen sichern.

## Relevante Dateien im Repo
- `saas-platform/provisioning/tenant_schema.sql`
- `saas-platform/app/Services/TenantProvisioningService.php`
- `saas-platform/app/Controllers/TenantController.php`
- `scripts/migrate_storage_to_tenant.php`

## Datenfluss
Tenant anlegen → DB/Schema provisionieren → Features/Status setzen → Praxis nutzbar.

## Wichtige Regeln
- Keine tenantübergreifenden Shared-Tabellen für Praxisdaten.
- Migrationszustand nachvollziehbar halten.

## Risiken
- Defektes Provisioning blockiert Aktivierung.
- Prefix-/Storage-Mismatch verursacht Datenverlust.

## TODOs
- Provisioning-Checkliste für Incident-Fälle ergänzen.

## Verlinkungen
- [[01-architecture/multi-tenant-and-domains]]
- [[10-bugs/known-bugs]]
