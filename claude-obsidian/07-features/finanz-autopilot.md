# Finanz Autopilot

## Beschreibung
Feature-Dokumentation für Finanz Autopilot.

## Zweck
Gemeinsames Verständnis für Implementierung, Grenzen und nächste Schritte.

## Relevante Dateien im Repo
- `app/Controllers/ReminderDunningController.php`
- `app/Routes/web.php (reminder/dunning endpoints)`
- `flutter_app/lib/screens/invoices/invoice_detail_screen.dart`

## Datenfluss
Client/Web/Portal → Route/Controller/Plugin → Repository/Service → UI/Response.

## Wichtige Regeln
- API-Verträge dürfen nicht breaking geändert werden.
- Tenant-Isolation bleibt erhalten.
- Status: **implemented (vollwertiges mehrstufiges Mahnwesen), Eskalation nutzergesteuert statt cron-automatisch**.

## Audit-Befund (2026-07-01, gegen echten Code verifiziert — korrigiert 2026-07-01 Teil 4)
`ReminderDunningController` implementiert ein **komplettes deutsches Mahnverfahren** — nicht nur ein
einfaches Erinnerungsformular:
- Zahlungserinnerung → **1. Mahnung → 2. Mahnung → Letzte Mahnung** (`dunningLevelLabel()`), Level wird
  automatisch fortlaufend hochgezählt (`getNextDunningLevel()`) — kein manuelles Nummerieren nötig
- Konfigurierbare Mahngebühr je Stufe (`dunning_default_fee`, Settings), automatische Fristen (`+14 days` Standard)
- Eigene PDF pro Mahnstufe (`generateDunningPdf()`, nutzt dasselbe Branding wie [[07-features/invoice-branding]])
- Automatischer E-Mail-Versand bei Erstellung, erneuter Versand jederzeit möglich
- **Überfälligkeits-Warnsystem**: `alertJson()` / `getOverdueAlertInvoices()` — Endpoint für Dashboard-Widget, das offene/überfällige Rechnungen proaktiv anzeigt (auch in der Mobile API verfügbar, siehe `MobileApiController.php`)
- **Pro Tenant nutzbar** — jede Praxis kann dieses Mahnwesen für ihre eigenen Tierhalter-Rechnungen einsetzen; strukturell identisch zum Mahnwesen, das `SaasInvoiceController` intern für unsere eigenen Tenant-Abo-Rechnungen nutzt (siehe [[06-saas/saas-admin-erweiterte-funktionen]])
- **Bewusst nutzergesteuert statt cron-automatisch**: Die Eskalation zur nächsten Mahnstufe erfolgt per Klick im Rechnungs-Modal, nicht automatisch nach Fristablauf — das ist in der Praxis meist gewollt (rechtliche/kulanzbedingte Entscheidung vor jeder Mahnstufe), kein technisches Defizit.

## Risiken
- Keine automatische Cron-Eskalation heißt: Wenn niemand das Überfälligkeits-Dashboard prüft, bleibt eine fällige Mahnstufe unbegrenzt liegen.

## TODOs
- Optional: Cron-/Pixel-getriggerte Erinnerung (siehe [[07-features/cron-pixel-system]]) an Praxis-Mitarbeiter, wenn überfällige Rechnungen X Tage unbearbeitet sind.

## Ergänzung (2026-07-01, Vollaudit)
Der Finanz-Bereich ist größer als diese Seite bisher abbildete: [[07-features/tax-export-pro]]
(DATEV/ZIP-Export, Kassenbuch, Steuerbericht) und [[07-features/gobd-audit-log]] (unveränderliches
Rechnungs-Änderungsprotokoll) sind eigenständige, implementierte Module im selben Fachbereich.

## Verlinkungen
- [[02-api/mobile-api]]
- [[03-web/web-app]]
- [[04-flutter/flutter-app]]
- [[11-decisions/decision-log]]
