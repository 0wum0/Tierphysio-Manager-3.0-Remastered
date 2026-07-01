# Steuerexport (DatevExportController + TaxExportPro-Plugin)

## Beschreibung
Vollständiges Steuerexport-System, das Rechnungen UND Ausgaben gemeinsam so aufbereitet, dass der
Export direkt an Steuerberater/Steuerfachmann übergeben oder in Steuersoftware importiert werden
kann. Zwei Bausteine, die zusammenspielen (nicht redundant, sondern ergänzend):

## Status
**implemented** (verifiziert 2026-07-01, Tiefenaudit — deutlich umfangreicher als ursprünglich dokumentiert)

## Relevante Dateien im Repo
- `app/Controllers/DatevExportController.php` + `app/Services/DatevExportService.php` — Kernsystem, Feature-Gate `dogschool_datev_export`
- `plugins/tax-export-pro/TaxExportController.php` + `TaxExportService.php` + `TaxExportRepository.php` — erweiterte UI mit Jahres-/Monatsfilter
- `app/Controllers/ExpenseController.php` — Ausgabenerfassung (siehe [[07-features/expense-management]])
- `plugins/tax-export-pro/GobdAuditService.php` (siehe [[07-features/gobd-audit-log]])

## Exportformate (beide Bausteine zusammen)
- **CSV "Steuerberater-Format"**: Belegdatum, Belegnummer, Buchungstext, Brutto/Netto/MwSt-Beträge, Soll-/Haben-Konto, Kunde/Lieferant, Zahlart — Einnahmen als positive, Ausgaben als negative Zeilen (Prefix `A-{id}`)
- **DATEV-Buchungsstapel Format 7 / EXTF v510**: mit Berater-/Mandantennummer, Wirtschaftsjahr, 27 DATEV-Standardspalten — direkt importierbar in DATEV Unternehmen Online, Lexware, BuchhaltungsButler
- **Kassenbuch**: gefiltert auf Barzahlungen (`payment_method = 'bar'`, `status = 'paid'`)
- **ZIP-Gesamtexport**: `rechnungen/` (alle Rechnungs-PDFs, aus DB regeneriert), `export/einnahmen.csv`, `rechnungsjournal.csv`, `datev_buchungsstapel.csv`, `steuerbericht.pdf` (kompakter PDF-Report), `belege/` (alle Ausgaben-Belege als PDF/Bild, benannt `A-{id}__Original.pdf`), `MANIFEST.txt` mit SHA-256-Hashes, README
- **Kontenrahmen SKR03** hinterlegt: Erlöskonten 8400/8300/8200, Vorsteuerkonten 1576/1571/1570, Aufwandskonten je Kategorie (4210 Miete, 4600 Marketing, 4940 Software, …)

## GoBD-Konformität
`finalize()` macht Rechnungen unveränderlich (`finalized_at`), `cancel()` storniert per Gegenbuchung
statt Löschung, alle Änderungen werden im Audit-Log protokolliert (siehe [[07-features/gobd-audit-log]]).

## Wichtige Regeln
- API-Verträge dürfen nicht breaking geändert werden.
- Tenant-Isolation bleibt erhalten.
- Beide Export-Bausteine (Kern + Plugin) nutzen dieselbe Datenbasis — Änderungen an Rechnungs-/Expense-Schema betreffen beide.

## TODOs
- Fachlich klären, ob Kern-`DatevExportController` und Plugin-`TaxExportController` langfristig zusammengeführt werden sollen (aktuell zwei UIs für überlappenden Zweck).

## Verlinkungen
- [[07-features/gobd-audit-log]]
- [[07-features/expense-management]]
- [[07-features/invoice-branding]]
- [[07-features/finanz-autopilot]]
