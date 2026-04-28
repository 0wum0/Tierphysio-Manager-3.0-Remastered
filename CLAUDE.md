# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Two Independent Applications

This repo contains **two separate PHP applications** that share one MySQL database:

| App | Root | Entry point | Namespace | URL |
|---|---|---|---|---|
| **Tenant App** (Praxissoftware) | `/` | `public/index.php` | `App\` | `app.therapano.de` |
| **SaaS Admin** (Lizenzverwaltung) | `saas-platform/` | `saas-platform/public/index.php` | `Saas\` | `admin.therapano.de` |

Each has its own `composer.json`, `.env`, `vendor/`, and `templates/`. They do **not** share code — only the database.

## Setup Commands

```bash
# Tenant app
composer install
cp .env.example .env   # then fill in DB_* and set INSTALLED=true

# SaaS admin (separate)
cd saas-platform
composer install
cp .env.example .env
```

PHP 8.3+ required. No build step, no asset bundler. Static assets are committed to `public/`.

There are no automated tests. PHPStan and CodeSniffer are available via `vendor/bin/`.

## Database: Multi-Tenant Prefix System

All tenant tables live in the **same MySQL database** with a `t_{slug}_` prefix. Examples:
- `t_tierphysio_wenzel_` → prefix for tenant "tierphysio-wenzel"
- `t_tierphysio_wenzel_patients`, `t_tierphysio_wenzel_settings`, etc.

**Prefix resolution order** (`Application::bootstrap()`):
1. `$_SESSION['tenant_table_prefix']` (staff login)
2. `$_SESSION['portal_tenant_prefix']` (owner portal login)
3. SaaS DB lookup by `user_email` → `tenants.db_name`
4. Auto-detect from `information_schema` (single-tenant fallback)

`Database::setPrefix($prefix)` affects all subsequent `Database::prefix($table)` calls. **Cron jobs** resolve the prefix from `?tid=slug` via `CronController::prefixFromTid()`.

Tables **without** prefix (global, in same DB): `cron_dispatcher_log`, `cron_job_log`.

## Three Migration Types

| Directory | Applied to | Runner |
|---|---|---|
| `migrations/*.sql` | Tenant DB, tenant tables (`{PREFIX}` replaced) | `App\Services\MigrationService` auto-runs on each request |
| `saas-platform/migrations/*.sql` | Tenant DBs, global tables (no prefix) | SaaS admin `/admin/updates` |
| `saas-platform/saas-migrations/*.sql` | SaaS admin DB only | SaaS admin `/admin/updates` |

Plugin migrations live in `plugins/{name}/migrations/*.sql` and are auto-run by each plugin's `ServiceProvider::runMigrations()` on every request. Always use `CREATE TABLE IF NOT EXISTS` and `ADD COLUMN IF NOT EXISTS`.

## Plugin System

Enabled plugins are listed in `plugins/enabled.json`. Each plugin has a `ServiceProvider.php` with:
- `register(PluginManager $pm)` — load files, run migrations, register nav items, add Twig paths
- `registerRoutes(Router $router)` — declare routes
- `dashboardWidget(array $ctx): array` — optional dashboard card

Plugin templates use a namespace: `'@owner-portal/dashboard.twig'` maps to `plugins/owner-portal/templates/dashboard.twig`.

## Routing & DI

Routes are registered in `app/Routes/web.php` (and per-plugin). Route format:
```php
$router->get('/path/{id}', [Controller::class, 'method'], ['auth']); // middleware: 'auth', 'guest', or []
```

The container (`App\Core\Container`) does constructor auto-wiring via reflection. Register singletons with `$container->singleton(ClassName::class, fn() => new ClassName(...))`.

## Self-Healing Pattern

Code in this codebase **never crashes the app** on DB schema issues:
- Wrap new DB queries in `try/catch` with a sensible fallback return value
- Use `Database::tableExists()` or `Database::columnExists()` before queries that depend on new columns
- Log errors with `error_log('[ClassName] ...')` — do not surface them to users
- Plugin migrations: errors are silently caught; the app continues running

## Cron Architecture

The **dispatcher** (`GET /cron/dispatcher?tid=...&token=...`) runs every 10 minutes and calls each sub-job via internal cURL. Each job has its own token stored in `{prefix}settings`.

Token self-healing: `CronController::ensureCronToken($key)` generates and stores a token if missing. When adding a new job:
1. Add it to `$jobs` in `dispatcher()`
2. Add its token key to `$tokenKeys` in `executeJob()` and to `$keys` in `ensureAllCronTokens()`
3. Add it to `PraxisCronController` token maps (`runNow`, `getToken`, `updateToken`)

**Critical**: When appending `token=` to a URL that already has `?tid=...`, use `&token=` (not `?token=`) — a second `?` corrupts the `tid` value and generates an oversized table prefix causing MySQL error 1103.

## Feature Gates

Per-tenant feature toggles are stored in `{prefix}feature_flags`. Access via:
```php
$gate = $container->get(\App\Services\FeatureGateService::class);
$gate->isEnabled('therapy_care_pro');
```
In Twig: `{% if features.therapy_care_pro %}`. Core features are always enabled; add-on features (dogschool, owner portal, tax export, etc.) are gated per plan.

## Tenant Storage

File uploads are isolated per tenant:
```php
tenant_storage_path('uploads/photos')
// → storage/tenants/t_praxis_wenzel_/uploads/photos/
```
Use the `tenant_storage_path()` helper — never hardcode `storage/` paths.

## SaaS Admin Specifics

The SaaS platform manages tenants, subscriptions, and the license API (`/api/license/*`). Key concepts:
- `tenants.db_name` stores the table prefix (e.g. `t_tierphysio_wenzel_`)
- `tenants.tid` is the URL-safe slug used for cron `?tid=` parameter
- Founders/early-tester pricing: `is_founder`, `founder_since`, `grandfathered_price` on subscriptions
- Audit log: `ActivityLogRepository::log($action, $actor, $entity, $id, $detail)`
