# Sprint A – Status

**Zuletzt aktualisiert:** 2026-04-28  
**Branch:** `claude/therapano-sprint-a-qaLiQ`  
**PR:** https://github.com/0wum0/Tierphysio-Manager-3.0-Remastered/pull/47

## Fertig

- ✅ **L1.1** Fortschritts-System (war bereits erledigt vor diesem Sprint)
- ✅ **L1.2** Smart Erinnerungen — `SmartReminderService`, Cron `/portal/cron/smart-erinnerungen`, Migration 008
- ✅ **L1.9** Besitzer Dashboard — „Letzte Aktivität"-Widget aus `portal_check_notifications`
- ✅ **L2.3** Early Tester / Founder System — `is_founder`, `founder_since`, Migration 003, Toggle in Tenant-Detail
- ✅ **L2.4** Tenant Übersicht — Trial-Ende, Billing-Datum, Founder-Badge, letzter Login in Tenant-Liste
- ✅ **L2.6** Audit Log — `/admin/audit-log`, `ActivityLogRepository`, Logging in TenantController
- ✅ **L2.8** Cronjob-Monitoring — `/admin/cron-monitoring`, Status-Cards, Logs-Tabelle
- ✅ **Bugfix** MySQL 1103 im Dispatcher — `?token=` → `&token=` in `PraxisCronController::runNow()`

## Offen

- Keine bekannten offenen Tasks aus Sprint A

## Kritische Stolpersteine

Siehe `claude-obsidian/bugs/dispatcher-mysql-1103.md`

---

# Brain-Vollaudit — 2026-07-01

**Branch:** `claude/therapano-theratap-comparison-qinkwt`

## Fertig

- ✅ Auslöser: Wettbewerbsvergleich TheraPano vs. theratap.de angefragt, User meldete "Obsidian ist nicht mehr aktuell"
- ✅ 10 Feature-Status in `07-features/*.md` gegen echten Code verifiziert und korrigiert (siehe `00-start/open-items.md` → "Vollaudit 2026-07-01")
- ✅ `07-features/README.md` Statusmatrix aktualisiert
- ✅ `12-roadmap/roadmap.md` um 6 neue P1/P2-Punkte aus dem Audit ergänzt

## Offen

- Vergleich TheraPano vs. theratap.de wird im Anschluss auf Basis der korrigierten Doku neu geschrieben
- Weitere Feature-Seiten mit generischem TODO ("Fachlichen Soll-/Ist-Vergleich ergänzen") sind noch nicht vollständig auditiert — nur die 10 im Wettbewerbsvergleich relevanten Features wurden geprüft
