# Google Kalender Sync

## Status
Aktiv — Plugin unter `/plugins/google-calendar-sync/`

## Architektur

| Komponente | Datei |
|---|---|
| Repository | `GoogleCalendarRepository.php` |
| API-Wrapper | `GoogleApiService.php` |
| Sync-Logik | `GoogleSyncService.php` |
| Controller | `GoogleCalendarController.php` |
| ServiceProvider | `ServiceProvider.php` |
| Templates | `templates/admin_index.twig` |

### DB-Tabellen (Prefix beachten!)
- `google_calendar_connections` — OAuth-Verbindung pro Tenant
- `google_calendar_sync_map` — Mapping appointment ↔ Google Event
- `google_calendar_sync_log` — Sync-Log (alle Aktionen)
- `google_calendar_imported_events` — Google-Termine als Staging-Tabelle
- `appointments.google_event_id` — Spalte für direkte Verknüpfung (Migration 003)

## Sync-Flow

### Push (Praxis → Google)
1. `appointmentCreated/Updated/Deleted` Hook → `syncCreated/Updated/Deleted()`
2. `bulkSyncAll()` — Cron-gesteuert, synct Termine der letzten 30 Tage ohne Mapping
3. Idempotenz: vor INSERT Mapping-Tabelle prüfen; `createSyncEntry()` nutzt `ON DUPLICATE KEY UPDATE`

### Pull (Google → Praxis)
1. `pullFromGoogle()` — nutzt incrementelles `syncToken`, bei Ablauf automatischer Full-Resync
2. Kreisschutz: Events mit `extendedProperties.private.tierphysio_source = 'tierphysio-manager'` werden übersprungen
3. Importierte Events → `google_calendar_imported_events` + `appointments` Tabelle
4. Cancelled Events → `appointments.status = 'cancelled'`

### Cron-Endpunkt
`GET /google-kalender/cron?token=SECRET`
- Token aus `storage/config/google.php` → `cron_secret` oder Env `GOOGLE_SYNC_CRON_SECRET`
- Intern per `X-Internal-Cron: true` Header (kein Token nötig)
- In FeatureRouteMap als `null` eingetragen → kein Feature-Gate

## Automatische Patientenzuordnung (seit Mai 2026)

`GoogleSyncService::matchPatientOwnerFromText()` analysiert Titel + Beschreibung:

1. **Token-Split**: Trennzeichen `,/-.| Leerzeichen`
2. **Patient-Match**: `LOWER(name) IN (tokens)` auf `patients`-Tabelle
3. **Fallback Patient**: Substring-Suche gegen alle Patienten (max 500)
4. **Owner-Match**: `LOWER(CONCAT(first_name,' ',last_name)) IN (tokens)` auf `owners`
5. **Fallback Owner**: Substring-Suche gegen alle Besitzer (max 500)
6. **Ableitungsregel**: Owner bekannt + genau 1 Patient des Owners → Patient wird gesetzt
7. **Mehrdeutigkeit**: Bei >1 Treffer → keine Zuordnung (null), kein false positive

Beispiele:
- `„Chanel Physio"` → Patient „Chanel" wird gefunden
- `„Termin Eileen Wenzel / Chanel"` → Owner „Eileen Wenzel" + Patient „Chanel"
- `„Bella Training"` → Patient „Bella" (falls eindeutig)
- `„Max Training"` → kein Match wenn mehrere Patienten „Max" existieren

## Bekannte Bugs / Fixes

### BEHOBEN Mai 2026 (2. Runde): HTTP 200 ohne echten Sync — Tierphysio Wenzel

**Symptom:** Cron-Monitor zeigte HTTP 200 / OK, aber Google-Termine erschienen nicht im Praxis-Kalender.

**Root Causes (5 Bugs):**

#### Bug 1 — KRITISCH: Kein Tenant-Prefix in `cron()` (GoogleCalendarController)
`GoogleCalendarController::cron()` las `?tid=` nicht und rief `$db->setPrefix()` nie auf.
Alle DB-Zugriffe (getConnection, pullFromGoogle, bulkSyncAll) liefen ohne Tenant-Prefix
→ falsche oder leere Tabellen → kein Google-Konto gefunden → Sync lief für niemanden.

**Fix:** Prefix-Setzung am Anfang von `cron()`, vor jedem DB-Zugriff:
```php
$db = Application::getInstance()->getContainer()->get(Database::class);
$db->setPrefix($prefix);
```

#### Bug 2: `pullFromGoogle()` prüfte `shouldSync()` nicht
`bulkSyncAll()` prüfte `shouldSync()` (sync_enabled + auto_sync + access_token).
`pullFromGoogle()` prüfte nur `!$connection || empty($connection['access_token'])`.
→ Pull lief auch wenn `sync_enabled=0` oder `auto_sync=0`.

**Fix:** `pullFromGoogle()` prüft jetzt explizit `sync_enabled` und `auto_sync` mit klarem Rückgabegrund.

#### Bug 3: Cron-Antwort enthielt keine Tenant-/Account-Infos
`cron()` antwortete immer `{"ok": true}` ohne Tenant, Google-Account, Calendar-ID, Push/Pull-Zahlen.
→ Monitor sah nur HTTP 200, keine Aussage ob wirklich verarbeitet.

**Fix:** Detaillierte JSON-Antwort:
```json
{"ok": true, "tid": "tierphysio-wenzel", "google_email": "...", "calendar_id": "primary",
 "push": {"success": 0, "skipped": 5, "failed": 0},
 "pull": {"imported": 3, "updated": 1, "deleted": 0}}
```
+ Klar getrenntes `skipped` mit `reason` (no_connection, sync_disabled, auto_sync_disabled, no_access_token).

#### Bug 4: `executeJob()` loggte nur HTTP-Code, keine Sync-Zahlen
`dispatcherLog` hatte nur `job_key`, `status`, `message`.
→ Aus dem Log konnte man nicht sehen wie viele Termine tatsächlich verarbeitet wurden.

**Fix:**
- `executeJob()` parst jetzt die JSON-Antwort und baut eine Detail-Message: `tid=X google=Y push[synced=0 skipped=5] pull[imported=3]`
- `dispatcherLog` erhält neue Felder: `tid`, `http_code`, `response_excerpt`
- Migration 054: `cron_dispatcher_log` um `tid`, `http_code`, `response_excerpt` erweitert

#### Bug 5 — KRITISCH: `last_run` Prüfung ohne `tid`-Filter (Cross-Tenant-Scheduling)
Der Dispatcher prüfte für jeden Job: `SELECT created_at FROM cron_dispatcher_log WHERE job_key = ?`
Wenn Tenant A google_sync ausführte, glaubte der Dispatcher für Tenant B: "Job kürzlich gelaufen → skip".
→ Bei Praxis mit mehreren Tenants: zweiter Tenant wurde dauerhaft übersprungen.

**Fix:** `WHERE job_key = ? AND status IN ('success','partial_error') AND (? = '' OR tid = ?)`

### TOKEN_REVOKED Logging verbessert
Wenn Refresh Token abgelaufen: Log enthält jetzt `[RECONNECT REQUIRED]` + Google-Email + error_log.
Cron-Monitor sieht dann `reason=reconnect_required` statt stillem Fehler.

### BEHOBEN Mai 2026: Duplicate Key `uq_appin`
**Ursache:** `google_calendar_sync_map` hatte auf manchen Tenants den Unique-Key
unter dem alten Namen `uq_appin` statt `uq_appointment_connection`. `createSyncEntry()`
nutzte `INSERT IGNORE` — bei wiederholtem Sync (z.B. nach `sync_status='failed'`)
wurde der INSERT erneut versucht → `SQLSTATE[23000]: Duplicate entry '1020-1001'`.

**Fix:**
- Migration `004_fix_sync_map_constraint.sql`: normalisiert Constraint-Name
- `createSyncEntry()` verwendet jetzt `INSERT ... ON DUPLICATE KEY UPDATE` statt `INSERT IGNORE`
- Bei ODKU-Trigger (lastInsertId=0) wird die vorhandene ID nachgeladen

### BEHOBEN Mai 2026: bulkSyncAll success-Counter falsch
**Ursache:** `$success++` wurde immer erhöht, auch wenn ein vorhandener `sync_status='synced'`
Eintrag gefunden und übersprungen wurde.

**Fix:** `$skipped++` wenn bestehender gültiger Sync-Eintrag vorhanden, `$success++` nur nach
tatsächlichem `syncCreated()`-Aufruf.

### BEHOBEN Mai 2026: last_sync zu alt
**Ursache:** `getLastSuccessfulSync()` suchte nur in `action IN ('create','update','delete')` —
Pull-Aktionen wurden ignoriert, obwohl der Pull erfolgreich war.

**Fix:** `action IN ('create','update','delete','pull')`

## Tenant-Sicherheit
- `$db->prefix()` wird für alle Tabellen verwendet → Tenant-Isolation über DB-Prefix
- Jede Praxis hat eigene `google_calendar_connections`-Zeile → eigenes Google-Konto
- Kein Cross-Tenant-Datenzugriff möglich

## Offene TODOs
- UI: Matched patient_id/owner_id in der Admin-Ansicht anzeigen
- Recurring Events: noch nicht unterstützt (werden als Einzeltermine importiert)
- Timezone: aktuell wird PHP-Systemzeit verwendet, explizites Europe/Berlin könnte fehlen
