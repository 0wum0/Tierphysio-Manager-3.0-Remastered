# Cron & Mail

## Beschreibung
Übersicht zu zeitgesteuerten Jobs und Mail-Prozessen in Praxis und SaaS.

## Zweck
Sichere Wartung von Hintergrundjobs ohne Token-/Routingfehler.

## Relevante Dateien im Repo
- `app/Controllers/CronController.php`
- `app/Controllers/CronPixelController.php`
- `app/Services/BirthdayMailService.php`
- `app/Services/MailService.php`
- `saas-platform/cron/cron_runner.php`
- `docs/windsurf_prompt_cron_migration.md`

## Datenfluss
Cron Trigger → Controller/Service → DB/Queues/Templates → Mailversand/Statuslog.

## Wichtige Regeln
- Cron-Tokens niemals hardcoden.
- Tokens aus Settings/Tenant-Kontext laden.

## Risiken
- Falsche Cron-URL oder Token führt zu Ausfällen.
- Mailfehler bleiben unentdeckt ohne Monitoring.

## TODOs
- Zentrales Cron-Inventar mit Intervallen ergänzen.

## Verlinkungen
- [[00-start/CRITICAL-RULES]]
- [[10-bugs/known-bugs]]
