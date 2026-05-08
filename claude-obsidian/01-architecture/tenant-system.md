# Tenant-System

## Beschreibung
Detailbeschreibung der Multi-Tenant-Isolation via Datenbank-Prefix in der Praxis-App.

## Relevante Dateien im Repo
- `app/Core/Database.php` — `setPrefix()`, `prefix()`, `storagePath()`
- `app/Core/Controller.php` — `requireAuth()`, Tenant-Bootstrap
- `saas-platform/app/Services/TenantProvisioningService.php`
- `migrations/` — Tenant-spezifische Migrations-SQL-Dateien

## Prefix-Schema

```
t_{tenant_id}_{tablename}
```

Beispiel: Tenant `abc123` → Tabelle `patients` → `t_abc123_patients`

## Praxis-App (`/app/`)

```php
// Prefix setzen (Controller-Bootstrap):
$this->db->setPrefix("t_{$tenantId}_");

// Prefix für Query:
$table = $this->db->prefix('patients');  // → "t_abc123_patients"
```

- `$db->setPrefix()` — setzt globalen Prefix für alle folgenden Calls
- `$db->prefix('table')` — gibt `{prefix}table` zurück
- `$db->storagePath('sub')` — gibt tenant-isolierten Storage-Pfad zurück

## SaaS-Platform (`/saas-platform/`)

```php
// KEIN $db->prefix() — Methode existiert dort nicht!
// Prefix als Constructor-Parameter übergeben:
class MyService {
    public function __construct(
        private readonly Database $db,
        private readonly string $prefix,
    ) {}

    public function getData(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM `{$this->prefix}settings` WHERE `key` = ?",
            ['my_key']
        );
    }
}
```

## Storage-Isolation

- Praxis-App: `$db->storagePath('uploads')` → `/storage/tenants/t_{id}_/uploads/`
- SaaS: Direkter Pfad-Aufbau: `STORAGE_PATH . '/tenants/' . $prefix . '/'`

## Tenant-Discovery im Flow

### Web (Browser)
1. Session enthält `tenant_id`
2. Controller-`requireAuth()` setzt Prefix vor DB-Zugriff
3. Alle Queries laufen gegen `t_{id}_*`

### Mobile API (Flutter)
1. Bearer-Token in `Authorization`-Header
2. `MobileApiController` löst Token → Tenant auf
3. Prefix wird für Request-Dauer gesetzt

## Kritische Fehlerquellen
- Prefix nicht gesetzt → Query auf falsche (leere) Tabellen
- SaaS `prefix()` aufgerufen → PHP-Fatal (Methode existiert nicht)
- `setPrefix()` nach erstem DB-Aufruf gesetzt → Race Condition

## Neue Tabellen (Praxis-App)
Immer `CREATE TABLE IF NOT EXISTS` mit Prefix-Platzhalter in der SQL-Datei.
`MigrationService` prefixt die SQL-Files automatisch.

## TODOs
- Tenant-Discovery-Flow für Web vs. Mobile als Sequenzdiagramm dokumentieren

## Verlinkungen
- [[00-start/CRITICAL-RULES]]
- [[01-architecture/domains]]
- [[01-architecture/multi-tenant-and-domains]]
- [[02-api/mobile-api]]
