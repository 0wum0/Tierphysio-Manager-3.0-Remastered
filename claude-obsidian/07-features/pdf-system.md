# PDF-System

## Library

**TCPDF** — einzige PDF-Library im Projekt (kein Dompdf, kein mPDF).

## PDF-Services

| Service | Datei | Zweck |
|---|---|---|
| `VetReportService` | `plugins/vet-report/VetReportService.php` | Auto-Tierarztbericht (aus Patientenakte) |
| `CustomVetReportPdfService` | `app/Services/CustomVetReportPdfService.php` | Manueller Tierarztbericht (Rich-Text) |
| `PdfService` | `app/Services/PdfService.php` | Allgemeiner PDF-Service (Rechnungen etc.) |

## Layout-Konstanten (A4)

```
sidebarW  = 42 mm   (farbige linke Sidebar)
contentX  = 50 mm   (Content-Startposition X)
contentW  = 145 mm  (Content-Breite)
rightEdge = 195 mm  (rechter Rand)
pageH     = 297 mm  (A4)
```

## Settings für PDF

| Setting-Key | Typ | Beschreibung |
|---|---|---|
| `pdf_primary_color` | hex | Sidebar-/Headerfarbe |
| `pdf_font` | string | helvetica/times/courier/dejavusans |
| `pdf_font_size` | float | 6–16pt, default 9 |
| `pdf_show_website` | bool | Website im Header anzeigen |
| `pdf_show_qr` | bool | GiroCode/EPC-QR-Code auf Rechnung (default '1') |
| `company_logo` | path | Logo-Datei in uploads/ |
| `company_name` | string | Praxisname |
| `company_street` | string | |
| `company_zip` | string | |
| `company_city` | string | |
| `company_phone` | string | |
| `company_email` | string | |
| `company_website` | string | |

## Rich-Text HTML → PDF (seit Mai 2026)

### Flow
1. Quill-Editor → `root.innerHTML` (HTML)
2. Controller: `sanitizeRichText()` (XSS-Strip)
3. Service: `looksLikeHtml()` → HTML-Pfad oder Plain-Text-Fallback
4. Service: `sanitizeHtml()` → Quill-Klassen konvertieren, gefährliche Tags entfernen
5. Service: `injectPdfCss()` → `<style>`-Block für TCPDF
6. `$pdf->writeHTML(...)` mit gesetzten Margins

### TCPDF writeHTML Regeln
- `SetMargins($contentX, 0, 210 - $rightEdge)` VOR `writeHTML()`
- `SetAutoPageBreak(true, 22)` VOR `writeHTML()`
- `SetMargins(0, 0, 0)` NACH `writeHTML()` (restore)
- `SetAutoPageBreak(false)` NACH `writeHTML()`

### Unterstützte CSS-Properties (TCPDF Subset)
- `font-size`, `font-weight`, `font-style`
- `color`, `background-color`
- `text-align`
- `margin`, `padding`
- `border`, `border-left`
- `text-decoration`

### Nicht unterstützt
- Flexbox, Grid
- CSS-Variablen (--bs-*)
- `border-radius`
- Pseudo-Elemente (`:before`, `:after`)

## Sidebar-Mechanismus

```php
$drawSidebar = function() use ($pdf, $sidebarColor, ...) {
    // Zeichnet farbige Sidebar + Logo + Dokumenttyp-Label
};
$drawSidebar(); // erste Seite
// Bei manuellem AddPage() immer $drawSidebar() aufrufen!
// Bei writeHTML() Auto-Breaks: Sidebar wird NICHT automatisch gezeichnet
```

## GiroCode / EPC-QR-Code auf Rechnungen (seit Juli 2026)

Rechnungs-PDFs (`PdfService::generateInvoicePdf`) enthalten im Fußbereich rechts einen
**GiroCode** (EPC-QR-Code, EPC069-12 Version 002). Der Kunde scannt ihn in seiner
Online-Banking-App → Empfänger, IBAN, Betrag und Verwendungszweck werden automatisch
übernommen.

- **Gilt für Praxis UND Hundeschule** — beide teilen sich `invoices`-Tabelle und die Route
  `/rechnungen/{id}/pdf` (InvoiceController::downloadPdf), also denselben PdfService. Ein
  einziger Eingriff deckt beide ab.
- Nur auf der **Rechnung** (nicht auf Quittung/Storno — Quittung ist bereits bar bezahlt,
  Storno hat keinen zu zahlenden Betrag).
- Rendering via TCPDF `write2DBarcode($payload, 'QRCODE,M', ...)` (Fehlerkorrektur M).

### Payload-Aufbau (`buildGiroCodePayload`)
12 LF-getrennte Felder:
```
BCD            Service Tag
002            Version (BIC optional)
1              Zeichensatz UTF-8
SCT            SEPA Credit Transfer
<BIC>          bank_bic (optional, ungültige werden verworfen)
<Name>         company_name (Empfänger/Kontoinhaber), max 70
<IBAN>         bank_iban (Leerzeichen entfernt, formatvalidiert)
EUR123.45      total_gross (Punkt als Dezimaltrenner)
               Zweck-Code (leer)
               Strukturierte Referenz (leer)
Rechnung RENR  Verwendungszweck (unstrukturiert), max 140
               Info Empfänger→Auftraggeber (leer)
```
Gibt `null` zurück (→ kein QR-Code) wenn IBAN fehlt/ungültig, Betrag < 0,01 € oder
Name leer ist. Abschaltbar per Setting `pdf_show_qr = 0`.

## Sicherheit

- Logo-Pfad: `realpath()` + `strpos($realPath, $uploadsDir) === 0` (Path-Traversal-Schutz)
- Foto-Pfad: `realpath()` + Containment-Check
- HTML-Content: Doppel-Sanitizer (Controller + Service)
- Header-Filename: `preg_replace('/[\r\n"]/', '', $filename)` (CRLF-Schutz)
