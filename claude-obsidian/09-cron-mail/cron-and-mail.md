# Cron & Mail

## Beschreibung
Übersicht zu zeitgesteuerten Jobs und Mail-Prozessen in Praxis und SaaS.

## Zweck
Sichere Wartung von Hintergrundjobs ohne Token-/Routingfehler.

## Relevante Dateien im Repo
- `app/Controllers/CronController.php` — Dispatcher + Birthday Cron
- `app/Controllers/CronPixelController.php`
- `app/Services/BirthdayMailService.php`
- `app/Services/MailService.php`
- `app/Services/FeatureRouteMap.php` — Cron-Pfade müssen als `null` eingetragen sein!
- `app/Services/FeatureGateService.php` — `requireFeature()` bypassed via X-Internal-Cron
- `app/Middleware/FeatureMiddleware.php` — bypassed via X-Internal-Cron
- `plugins/calendar/CalendarController.php` — `cronReminders()`
- `plugins/therapy-care-pro/TherapyCareController.php` — `cronReminders()`
- `saas-platform/cron/cron_runner.php`
- `saas-platform/app/Controllers/PraxisCronController.php`

## Cron-Architektur

```
SaaS cron_runner.php (PHP CLI, jede Stunde)
  → dispatchTenants()
    → HTTP GET https://app.therapano.de/cron/dispatcher?tid={tid}
      Header: X-Internal-Cron: true
      → FeatureRouteMap: /cron → null (kein Gate)
      → CronController::dispatcher()
        → executeJob('calendar_reminders') → /kalender/cron/erinnerungen?tid=&token=
        → executeJob('tcp_reminders')      → /tcp/cron/erinnerungen?tid=&token=
        → executeJob('birthday')           → /cron/geburtstag?tid=&token=
        → executeJob('google_sync')        → /google-kalender/cron?tid=&token=
        → executeJob('smart_reminders')    → /portal/cron/smart-erinnerungen?tid=&token=
        → executeJob('holiday_greetings')  → /api/holiday-cron?tid=&token=
```

## Cron-Job Inventar

| Job-Key | Endpunkt | Intervall | Token-Key |
|---------|----------|-----------|-----------|
| `birthday` | `/cron/geburtstag` | täglich 08:00 | `birthday_cron_token` |
| `calendar_reminders` | `/kalender/cron/erinnerungen` | alle 15 Min | `calendar_cron_secret` |
| `tcp_reminders` | `/tcp/cron/erinnerungen` | alle 15 Min | `tcp_cron_token` |
| `google_sync` | `/google-kalender/cron` | stündlich | `google_sync_cron_secret` |
| `smart_reminders` | `/portal/cron/smart-erinnerungen` | täglich 09:00 | `portal_smart_reminder_token` |
| `holiday_greetings` | `/api/holiday-cron` | täglich 08:00 | `cron_secret` |
| `dispatcher` | `/cron/dispatcher` | alle 10 Min | `cron_dispatcher_token` |
| Kurse | `/kurse/cron/erinnerungen` | stündlich | `course_reminder_token` |

## Wichtige Regeln
- Cron-Tokens niemals hardcoden — immer aus Settings/Tenant-Kontext laden.
- **FeatureRouteMap**: Jeder neue Cron-Pfad MUSS als `null` in `FeatureRouteMap::MAP` eingetragen werden, sonst greift das übergeordnete Präfix-Gate.
- **Tenant-Prefix vor DB-Zugriff**: In jedem Cron-Controller `setPrefix()` aus `?tid=` BEVOR `settingsRepo` oder `repo` aufgerufen wird.
- **`?token=` vs `&token=`**: Wenn `?tid=` bereits in der URL ist, `&token=` verwenden — nie `?token=` (würde `?` doppeln, `tid` wird korrumpiert).
- **X-Internal-Cron Header**: Alle internen Cron-Aufrufe via `executeJob()` senden `X-Internal-Cron: true` → bypassed FeatureMiddleware und requireFeature().
- Cron-Endpunkte werden mit `[]` (kein Middleware-Array) in den Routes registriert — TOKEN-Prüfung erfolgt im Controller selbst.

## Bekannte Stolperfallen / Fixed Bugs

### HTTP 302 bei `/kalender/cron/erinnerungen` und `/tcp/cron/erinnerungen` (Fix 2026-05-11)
**Ursache:** FeatureRouteMap mappte `/kalender` → `calendar` und `/tcp` → `therapy_care`.
Cron-Requests ohne Session → Feature disabled → `header('Location: /dashboard')` → 302.
**Fix:** Alle Cron-Pfade explizit als `null` in FeatureRouteMap + X-Internal-Cron Bypass in FeatureMiddleware + FeatureGateService.
→ Details: [[10-bugs/known-bugs#Cron HTTP 302]]

## Risiken
- Falsche Cron-URL oder Token führt zu Ausfällen.
- Fehlender `null`-Eintrag in FeatureRouteMap → 302 Redirect bei neuen Cron-Pfaden.
- Mailfehler bleiben unentdeckt ohne Monitoring.
- Tenant-Prefix nicht gesetzt → Cron verarbeitet leere Queue (falscher Tenant).

## TODOs
- Cron-Status in SaaS-Dashboard weiter ausbauen (letzte Ausführungszeit je Tenant).

## Verlinkungen
- [[00-start/CRITICAL-RULES]]
- [[10-bugs/known-bugs]]
