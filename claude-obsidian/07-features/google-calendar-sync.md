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
