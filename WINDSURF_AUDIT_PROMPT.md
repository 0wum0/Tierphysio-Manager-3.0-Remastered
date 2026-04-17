# Windsurf Mega-Prompt: System-Audit & Self-Healing
# TheraPano / Tierphysio Manager 3.0

Kopiere alles ab "---" und füge es als Prompt in Windsurf ein.

---

## KONTEXT: Multi-Tenant SaaS für Tierphysio-Praxen

Du arbeitest an einem PHP 8.3 Multi-Tenant SaaS-System mit folgendem Aufbau:

### Zwei separate PHP-Apps im selben Repo:

**1. Praxis-App (`/app/` + `/templates/` + `/public/`)**
- Namespace: `App\`
- Jeder Tenant hat eigene Tabellen mit Prefix `t_{slug}_` (z.B. `t_praxis_wenzel_`)
- Prefix wird gesetzt via `$db->setPrefix()` und gelesen via `$db->prefix('table')` oder `$this->t()` in Repositories
- `$db->storagePath('subdir')` erstellt und liefert Tenant-Storage unter `/storage/tenants/{prefix}/`

**2. SaaS-Platform (`/saas-platform/`)**
- Namespace: `Saas\`
- KEINE `prefix()`-Methode in der Database-Klasse (anderes Database-Objekt!)
- Prefix immer als `string $prefix`-Konstruktor-Parameter an Services übergeben
- Eigene globale Tabellen ohne Prefix: `tenants`, `plans`, `subscriptions`, `payments`, `license_tokens`, `saas_admins`, `saas_logs`, etc.

### Prefix-Format & Regeln:
- Format: `t_{slug}_` mit abschließendem Unterstrich (z.B. `t_praxis_wenzel_`)
- Wird gesetzt in: `Application.php::bootstrap()` → liest aus Session → SaaS-DB → Schema-Detection
- Normalisierung in: `Application.php::normalizeTenantPrefix()` — setzt `t_`-Präfix, entfernt doppelte Unterstriche, stellt trailing `_` sicher
- Validation: Prefix darf nur EINMAL `t_` enthalten; mehrfaches `t_` → korrupt → aus Session löschen & neu auflösen

### Token-Regeln:
- Cron-Tokens NIEMALS hardcoded — immer aus `{prefix}settings`-Tabelle lesen (Key-Value)
- Tokens sind 64-Zeichen Hex, generiert via `bin2hex(random_bytes(32))`
- Existierende Tokens: `cron_dispatcher_token`, `birthday_cron_token`, `calendar_cron_secret`, `google_sync_cron_secret`, `tcp_cron_token`, `cron_secret`

---

## AUFGABE: Vollständiger System-Audit & Self-Healing

Führe folgende Prüfungen durch. Bei jedem Fund: sofort beheben und kurz dokumentieren was du geändert hast.

---

### AUDIT-BLOCK 1: Praxis-App Repository-Prefix-Compliance

**Prüfe alle Dateien in `/app/Repositories/`:**

Für jede Repository-Datei:
1. Erbt sie von der Repository-Basisklasse?
2. Werden ALLE Tabellenreferenzen über `$this->t()` oder `$this->db->prefix('...')` aufgelöst — NIEMALS rohe Tabellennamen wie `settings`, `users`, `patients` direkt im SQL?
3. Gibt es `SELECT * FROM settings` oder ähnliche rohe Tabellennamen in Query-Strings?

**Fixregel:** Jeden rohen Tabellennamen `tablename` → `{$this->t()}` ersetzen, wenn er zum Tenant gehört.

**Bekannte Tenant-Tabellen (alle brauchen Prefix):**
`users`, `patients`, `owners`, `appointments`, `invoices`, `invoice_items`, `invoice_reminders`, `dunning_letters`, `settings`, `homework`, `homework_exercises`, `homework_plans`, `homework_assignments`, `treatment_types`, `expenses`, `befundboegen`, `befundbogen_fields`, `befundbogen_antworten`, `user_preferences`, `cron_job_log`, `cron_job_queue`, `cron_dispatcher_queue`, `google_tokens`, `intake_forms`, `vet_reports`, `nachrichten`, `tcp_reminders`

---

### AUDIT-BLOCK 2: Praxis-App Controller & Services

**Prüfe alle Dateien in `/app/Controllers/` und `/app/Services/`:**

1. Gibt es direkte `$db->query("SELECT ... FROM users ...")` ohne Prefix?
2. Gibt es `$db->fetch("... FROM settings ...")` ohne `$db->prefix('settings')`?
3. Gibt es irgendwo hardcodierte Cron-Tokens als String-Konstante oder `.env`-Wert?
4. Gibt es `var_dump()`, `print_r()`, `echo` für Debugging?

**Spezifisch CronController (`/app/Controllers/CronController.php`):**
- Prüfe ob `ensureCronToken()` für ALLE 6 Token-Keys aufgerufen wird
- Prüfe ob `ensureAllCronTokens()` alle bekannten Token-Keys enthält: `cron_dispatcher_token`, `birthday_cron_token`, `calendar_cron_secret`, `google_sync_cron_secret`, `tcp_cron_token`, `cron_secret`
- Falls ein Token-Key fehlt: hinzufügen

---

### AUDIT-BLOCK 3: Migrations-SQL Prefix-Prüfung

**Prüfe alle Dateien in `/migrations/` (48 Dateien):**

1. Gibt es `CREATE TABLE` ohne `CREATE TABLE IF NOT EXISTS`? → `IF NOT EXISTS` hinzufügen
2. Gibt es `INSERT INTO settings` (raw) statt `INSERT INTO {prefix}settings`? 
   → In Migrations ist das KORREKT, weil MigrationService die Prefix-Injection erledigt. Prüfe ob MigrationService.php alle Patterns abdeckt.
3. Prüfe `/app/Services/MigrationService.php`: Deckt `applyPrefixToSql()` folgende Patterns ab?
   - `CREATE TABLE IF NOT EXISTS \`tablename\``
   - `INSERT INTO \`tablename\``
   - `INSERT IGNORE INTO \`tablename\``
   - `UPDATE \`tablename\``
   - `ALTER TABLE \`tablename\``
   - `DROP TABLE IF EXISTS \`tablename\``
   - `REFERENCES \`tablename\``
   - `JOIN \`tablename\``
   - `FROM \`tablename\``

   Falls ein Pattern fehlt: in die Regex-Liste ergänzen.

4. Prüfe ob die Whitelist der NICHT zu prefixenden globalen Tabellen korrekt ist:
   `tenants`, `plans`, `saas_admins`, `saas_logs`, `failed_jobs`, `migrations_global`
   → Falls eine Tenant-Tabelle fälschlicherweise in der Whitelist steht: entfernen

---

### AUDIT-BLOCK 4: SaaS-Platform Service Prefix-Korrektheit

**Prüfe alle Dateien in `/saas-platform/app/Services/`:**

1. Wird in keinem SaaS-Service `$db->prefix()` oder `$db->setPrefix()` aufgerufen? (Diese Methoden existieren nicht in der SaaS-Database-Klasse)
2. Wird der Tenant-Prefix überall als `string $prefix`-Konstruktor-Parameter empfangen?
3. Werden Tenant-Tabellen korrekt als `"{$this->prefix}tablename"` im SQL aufgebaut?

**Spezifisch SelfHealingService (`/saas-platform/app/Services/SelfHealingService.php`):**
- Prüfe ob `healSettings()` ALLE kritischen Settings als Defaults enthält:
  ```
  timezone, language, currency, date_format, time_format,
  invoice_prefix, invoice_start, reminder_days,
  mail_from_name, mail_from_address,
  birthday_mail_enabled, birthday_mail_subject
  ```
- Prüfe ob `healStorageDirs()` ALLE Tenant-Storage-Subdirectories anlegt:
  ```
  patients/, uploads/, vet-reports/, intake/, invoices/, exports/
  ```
- Falls Healing-Einträge fehlen: ergänzen

**Spezifisch TenantHealthService (`/saas-platform/app/Services/TenantHealthService.php`):**
- Prüfe ob der Health-Check ALLE 6 Cron-Token-Keys prüft:
  ```
  cron_dispatcher_token, birthday_cron_token, calendar_cron_secret,
  google_sync_cron_secret, tcp_cron_token, cron_secret
  ```
- Falls ein Token-Key fehlt: in den Health-Check ergänzen

---

### AUDIT-BLOCK 5: SaaS-Platform Controller

**Prüfe alle Dateien in `/saas-platform/app/Controllers/`:**

1. Gibt es direkte SQL-Queries mit rohen nicht-globalen Tabellennamen ohne Prefix?
2. Werden Tenant-Operationen korrekt an Services delegiert (nicht inline im Controller)?
3. Hat jede Controller-Methode `requireAuth()` oder `requireSaasAuth()` am Anfang?
4. Gibt es `$_GET` oder `$_POST` direkt (außer in Controllern)?

---

### AUDIT-BLOCK 6: Bootstrap & Prefix-Auflösung

**Prüfe `/app/Core/Application.php`:**

Prüfe den Prefix-Auflösungs-Fallback-Chain:
1. Session-Cache (`tenant_table_prefix`) → korrekte Validierung?
2. Portal-Session (`portal_tenant_prefix`) → korrekte Validierung?
3. SaaS-DB-Lookup via `resolveTenantPrefix()` → korrekte Fehlerbehandlung wenn SaaS-DB nicht erreichbar?
4. Auto-Detection via `detectPrefixFromSchema()` → korrekte Behandlung wenn > 1 Tenant gefunden (darf NICHT einen zufälligen Tenant nehmen → muss leer zurückgeben)?

**KRITISCH:** Falls bei Schritt 4 (Auto-Detection) mehrere `t_*_users`-Tabellen gefunden werden, MUSS die Methode `''` zurückgeben (kein Prefix setzen), NICHT einen zufälligen Prefix setzen. Prüfe und fixe falls nötig.

---

### AUDIT-BLOCK 7: Self-Healing Vollständigkeit

**Erstelle falls nicht vorhanden: Self-Healing für Cron-Tokens in SelfHealingService**

Prüfe ob `SelfHealingService::healSettings()` auch fehlende Cron-Tokens generiert:
```php
// Cron-Tokens müssen vorhanden und nicht leer sein
$cronTokenKeys = [
    'cron_dispatcher_token',
    'birthday_cron_token', 
    'calendar_cron_secret',
    'google_sync_cron_secret',
    'tcp_cron_token',
    'cron_secret',
];
```

Falls `healSettings()` Cron-Tokens NICHT heilt:
- Ergänze eine `healCronTokens(string $prefix): array`-Methode in SelfHealingService
- Die Methode prüft jeden Token-Key in `{prefix}settings`
- Fehlende oder leere Tokens werden mit `bin2hex(random_bytes(32))` neu generiert und gespeichert
- Gibt zurück: `['healed' => [...], 'already_ok' => [...], 'failed' => [...]]`
- Ergänze den Aufruf in `healAll()`: Ergebnis von `healCronTokens()` in den Report integrieren

---

### AUDIT-BLOCK 8: Routen-Vollständigkeit

**Prüfe `/app/Routes/web.php` und `/saas-platform/app/Routes/web.php`:**

1. Gibt es Controller-Methoden die in den Controllern existieren aber KEINE Route haben?
2. Gibt es Routen die auf nicht-existente Controller-Methoden zeigen?
3. Sind alle `POST`-Routen mit CSRF-Schutz versehen (Middleware)?

**Spezifisch SaaS-Platform:**
- Prüfe ob folgende Routen vorhanden sind (falls nicht: ergänzen):
  ```
  GET  /admin/tenants/{id}/health-api   → TenantController::healthApi
  GET  /admin/tenants/{id}/activity     → TenantController::activityLog
  POST /admin/tenants/{id}/features     → TenantController::setFeature
  ```

---

### AUDIT-BLOCK 9: PHP-Standards Compliance

**Prüfe ALLE PHP-Dateien in `/app/` und `/saas-platform/app/`:**

1. Hat jede Datei `declare(strict_types=1)` als ERSTE Anweisung nach `<?php`?
2. Hat jede Klasse korrekte Typdeklarationen für alle Parameter, Properties und Rückgabewerte?
3. Wird `readonly` für injizierte Dependencies in Services verwendet?
4. Gibt es `array()` statt `[]`? → Auf `[]` umstellen
5. Gibt es `var_dump()`, `print_r()`, `echo` (außer in Views)? → Entfernen

---

### AUDIT-BLOCK 10: Sicherheits-Check

1. **SQL-Injection:** Gibt es irgendwo String-Interpolation im SQL statt `?`-Bindings?
   Erlaubt: `"SELECT * FROM \`{$table}\` WHERE id = ?"` (Tabellenname interpoliert, Wert gebunden)
   VERBOTEN: `"SELECT * FROM users WHERE id = {$id}"` (Wert interpoliert)

2. **CSRF:** Haben alle POST-Formulare in Twig-Templates `{{ csrf_field()|raw }}`?
   Prüfe alle Templates in `/templates/` und `/saas-platform/templates/`

3. **XSS:** Gibt es `|raw`-Filter in Twig auf Benutzerdaten? Falls ja: Kommentar hinzufügen warum das sicher ist

4. **Hardcoded Secrets:** Gibt es API-Keys, Passwörter oder Tokens als Strings im Code?

---

## SELBSTHEILUNG: Was automatisch erstellt werden soll

Nach dem Audit führe folgende Self-Healing-Maßnahmen durch:

### 1. SelfHealingService erweitern (falls `healCronTokens()` fehlt)
Datei: `/saas-platform/app/Services/SelfHealingService.php`
- Methode `healCronTokens(string $prefix): array` ergänzen
- In `healAll()` integrieren
- Tokens: 64-Zeichen Hex via `bin2hex(random_bytes(32))`

### 2. TenantHealthService vollständig machen
Datei: `/saas-platform/app/Services/TenantHealthService.php`
- Alle 6 Cron-Token-Keys müssen geprüft werden
- Health-Status: `ok` | `warning` | `error`
- Fehlender Token = `warning` (nicht `error`, weil auto-heilbar)

### 3. SelfHealingService Storage-Healing vervollständigen
Sicherstellen dass folgende Verzeichnisse für jeden Tenant existieren:
```
/storage/tenants/{prefix}/
/storage/tenants/{prefix}/patients/
/storage/tenants/{prefix}/uploads/
/storage/tenants/{prefix}/vet-reports/
/storage/tenants/{prefix}/intake/
/storage/tenants/{prefix}/invoices/
/storage/tenants/{prefix}/exports/
```

### 4. Migration-Idempotenz sicherstellen
Alle `CREATE TABLE` ohne `IF NOT EXISTS` → ergänzen.
Alle `DROP TABLE tablename` → zu `DROP TABLE IF EXISTS tablename` ändern.

---

## ABSOLUTE VERBOTE (niemals tun)

- `$db->prefix()` in `/saas-platform/` aufrufen
- Cron-Tokens hardcoded schreiben
- `declare(strict_types=1)` weglassen
- Rohen SQL mit String-interpolierten Benutzerwerten
- `var_dump()` oder `echo` für Debugging committen
- Dateien in `/vendor/` bearbeiten
- Dateien in `/dist/` bearbeiten
- Abo-/Subscription-Logik (`SubscriptionRepository`, `SubscriptionService`) ohne explizite Anweisung ändern
- Auf `main` pushen — immer auf Feature-Branch

---

## AUSGABE-FORMAT

Am Ende des Audits erstelle eine Zusammenfassung:

```
## AUDIT-ERGEBNIS

### Gefundene & behobene Probleme:
- [KRITISCH] Datei:Zeile - Was war falsch - Was wurde geändert
- [WARNUNG]  Datei:Zeile - Was war falsch - Was wurde geändert
- [INFO]     Datei:Zeile - Was war falsch - Was wurde geändert

### Self-Healing hinzugefügt:
- Methode / Datei - Was hinzugefügt wurde

### Alles korrekt (keine Änderung nötig):
- Block 1: Repository Prefix → OK
- Block 2: ...

### Offen (erfordert manuelle Prüfung):
- Was unklar ist und warum
```

---

## WICHTIGE DATEIEN (Referenz für den Audit)

```
Kern-System:
  /app/Core/Database.php              → setPrefix(), prefix(), storagePath()
  /app/Core/Repository.php            → t() Methode (Basis aller Repos)
  /app/Core/Application.php           → Bootstrap, Prefix-Auflösung, Normalisierung

Cron & Self-Healing:
  /app/Controllers/CronController.php → ensureCronToken(), ensureAllCronTokens()
  /saas-platform/app/Services/SelfHealingService.php
  /saas-platform/app/Services/TenantHealthService.php

Migrations:
  /app/Services/MigrationService.php  → applyPrefixToSql() Regex-Engine
  /migrations/                        → 48 Tenant-Migrations
  /saas-platform/migrations/          → 28 SaaS-Migrations

Repositories (alle Tenant-prefixed):
  /app/Repositories/BefundbogenRepository.php
  /app/Repositories/ExpenseRepository.php
  /app/Repositories/HomeworkRepository.php
  /app/Repositories/InvoiceRepository.php
  /app/Repositories/OwnerRepository.php
  /app/Repositories/PatientRepository.php
  /app/Repositories/ReminderDunningRepository.php
  /app/Repositories/SettingsRepository.php
  /app/Repositories/TreatmentTypeRepository.php
  /app/Repositories/UserPreferencesRepository.php
  /app/Repositories/UserRepository.php

SaaS Repositories (kein Prefix, globale Tabellen):
  /saas-platform/app/Repositories/TenantRepository.php
  /saas-platform/app/Repositories/SubscriptionRepository.php
  /saas-platform/app/Repositories/PlanRepository.php
  /saas-platform/app/Repositories/AdminRepository.php
  /saas-platform/app/Repositories/LicenseRepository.php
  /saas-platform/app/Repositories/SaasInvoiceRepository.php
  /saas-platform/app/Repositories/SettingsRepository.php
  /saas-platform/app/Repositories/ActivityLogRepository.php
  /saas-platform/app/Repositories/NotificationRepository.php
  /saas-platform/app/Repositories/LegalRepository.php

Routen:
  /app/Routes/web.php
  /app/Routes/api.php
  /saas-platform/app/Routes/web.php
```
