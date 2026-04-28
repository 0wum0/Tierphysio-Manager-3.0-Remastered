# Decision Log

## Beschreibung
Architektur- und Produktentscheidungen mit Datum, Kontext, Konsequenz.

## Zweck
Nachvollziehbarkeit für spätere Refactors und Agent-Übergaben.

## Relevante Dateien im Repo
- `flutter_app/lib/services/api_service.dart`
- `app/Core/Database.php`
- `saas-platform/app/Routes/web.php`

## Datenfluss
Entscheidung treffen → Eintrag erstellen → betroffene Bereiche verlinken.

## Wichtige Regeln
- Jede irreversible Entscheidung dokumentieren.
- Eintragformat: Datum, Entscheidung, Begründung, Impact, Rollback-Option.

## Risiken
- Ohne Decision Log wiederholen Teams alte Fehler.

## TODOs
- Historische Schlüsselentscheidungen rückwirkend eintragen.

## Verlinkungen
- [[00-start/CRITICAL-RULES]]
- [[12-roadmap/roadmap]]
