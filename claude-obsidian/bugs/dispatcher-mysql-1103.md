# Dispatcher: MySQL 1103 "Incorrect table name"

**Datum:** 2026-04-28  
**Branch:** `claude/therapano-sprint-a-qaLiQ`  
**Commit:** `dad6e1b`

## Was wurde geändert

`PraxisCronController::runNow()`: `?token=` → `&token=`.  
`CronController::prefixFromTid()`: Längenvalidierung ergänzt (Exception bei Prefix > 58 Zeichen).  
`PraxisCronController`: `smart_reminders` Token-Mapping in `runNow()`, `getToken()`, `updateToken()` ergänzt.

## Root Cause

`runNow()` baute die Cron-URL so:
```
?tid=praxis-slug   +   ?token=64hexchars   →  FALSCH
?tid=praxis-slug   +   &token=64hexchars   →  KORREKT
```

PHP parsete `$_GET['tid']` als `praxis-slug?token=64hexchars`. `prefixFromTid()` normalisierte das `?` zu `_` und erzeugte einen 97-Zeichen-Prefix → MySQL Error 1103 beim ersten `settings`-Zugriff.

## Wichtige Details / Stolpersteine

- **Immer `&token=` verwenden** wenn `?tid=` bereits in der URL ist — nie ein zweites `?`.
- `prefixFromTid()` wirft jetzt eine `InvalidArgumentException` mit klarer Meldung statt kryptischem MySQL 1103.
- MySQL 1103 zeigt den Tabellennamen **abgeschnitten auf 64 Zeichen** — der echte Name war länger.
- `cron_dispatcher_log` ist eine **globale Tabelle ohne Prefix** (erstellt durch saas-migration 041).
