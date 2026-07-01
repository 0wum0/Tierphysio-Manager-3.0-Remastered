# Rechnungsdesign / Invoice-Branding

## Beschreibung
Vollständig individualisierbares Rechnungslayout — Praxen können Rechnungen mit eigenem Logo,
Farben, Schriftart und Texten gestalten. Vorher komplett undokumentiert.

## Status
**implemented** (verifiziert 2026-07-01, Tiefenaudit)

## Relevante Dateien im Repo
- `app/Controllers/SettingsController.php` (Zeile 151–348) — Speicherung aller Branding-Einstellungen
- `app/Services/PdfService.php` (TCPDF-basiert) — Rendering

## Funktionsumfang
- **Logo-Upload**: `SettingsController::uploadLogo()` — tenant-scoped, erscheint in der PDF-Sidebar
- **Farben**: `pdf_primary_color` (Sidebar), `pdf_accent_color` (Akzentlinien), separate Farben für Firmenname, Firmeninfo, Empfänger, Tabellenkopf, Tabellenzellen, Summen-Label, Gesamtbetrag, Fußzeile
- **Typografie**: `pdf_font` (Helvetica/Times/Courier/…), `pdf_font_size`
- **Individuelle Bilder pro Dokumenttyp**: separates Bild für Rechnung, "Vielen Dank"-Text, Quittung, Barzahlung, Erinnerung, Mahnung
- **Firmendaten im PDF**: Anschrift, Telefon, E-Mail, Website, IBAN/BIC, Steuernummer/USt-ID
- **Rechnungsnummernkreis**: `invoice_prefix` + `invoice_start_number` (freie Durchnummerierung)
- **Zahlungskonditionen**: frei konfigurierbarer `payment_terms`-Text
- **Anzeigeoptionen**: Logo/Patient/Chip/IBAN/Steuernummer/Website ein-/ausblendbar, Wasserzeichen (`pdf_watermark`, z.B. "ENTWURF")
- **Freitexte**: Intro-, Schluss- und Fußzeilentext frei editierbar

## Wichtige Regeln
- API-Verträge dürfen nicht breaking geändert werden.
- Tenant-Isolation bleibt erhalten.
- Logo-/Bild-Uploads müssen MIME-validiert und tenant-gescoped bleiben (kein Cross-Tenant-Zugriff auf Branding-Assets).

## TODOs
- Fachlichen Soll-/Ist-Vergleich ergänzen.
- E2E-Flow dokumentieren.

## Verlinkungen
- [[07-features/tax-export-pro]]
- [[07-features/gobd-audit-log]]
