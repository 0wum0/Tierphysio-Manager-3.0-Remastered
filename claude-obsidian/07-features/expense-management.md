# Ausgabenverwaltung (mit OCR-Belegerfassung)

## Beschreibung
Strukturierte Ausgabenerfassung mit automatischer Beleg-Texterkennung (OCR) — vorher komplett undokumentiert.

## Status
**implemented** (verifiziert 2026-07-01, Tiefenaudit)

## Relevante Dateien im Repo
- `app/Controllers/ExpenseController.php`
- `app/Services/ReceiptParserService.php` (OCR/Text-Parsing)

## Funktionsumfang
- Felder: Datum, Beschreibung, Kategorie (Praxisbedarf, Miete, Fortbildung, Marketing, Bürobedarf, Software, Fahrtkosten, Versicherungen, Steuern, Sonstiges), Lieferant, Nettobetrag, Steuersatz (19/7/0 %), automatische Bruttoberechnung, Notizen
- **Belegfoto-Upload**: PDF/JPG/PNG/WEBP, max. 10 MB, tenant-gescoped gespeichert
- **OCR-Vorschau** (`previewReceipt()`): Beleg wird vor dem Speichern geparst — Datum, Betrag, Steuersatz, Lieferant werden automatisch extrahiert und dem Formular vorgeschlagen
- Ausgaben fließen direkt in den Steuerexport ein (siehe [[07-features/tax-export-pro]]), inkl. Beleg-Referenzierung im ZIP-Export

## Wichtige Regeln
- API-Verträge dürfen nicht breaking geändert werden.
- Tenant-Isolation bleibt erhalten.
- OCR-Ergebnis ist ein Vorschlag, keine automatische Übernahme ohne Nutzerbestätigung — Fehlinterpretation muss korrigierbar bleiben.

## TODOs
- Fachlichen Soll-/Ist-Vergleich ergänzen.
- E2E-Flow dokumentieren.

## Verlinkungen
- [[07-features/tax-export-pro]]
