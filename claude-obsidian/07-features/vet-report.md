# Tierarztbericht (Vet Report)

## Übersicht

Plugin unter `plugins/vet-report/` — generiert professionelle PDF-Tierarztberichte aus der Patientenakte.

## Berichtstypen

### 1. Auto-Bericht (VetReportService)
- Generiert automatisch aus Patientendaten + Timeline + Terminen
- Route: `GET /patienten/{id}/tierarztbericht`
- Controller: `VetReportController::generate()`
- Service: `plugins/vet-report/VetReportService.php`
- Kein Editor — rein automatisiert

### 2. Manueller Bericht (CustomVetReportPdfService)
- Freitext-Bericht mit Rich-Text-Editor
- Route: `POST /patienten/{id}/tierarztbericht/custom`
- Controller: `VetReportController::createCustom()`
- PDF-Service: `app/Services/CustomVetReportPdfService.php`

## Editor (seit Mai 2026)

**Quill v2 Rich-Text-Editor** — ersetzt einfache `<textarea>`.

### Funktionen
- Überschriften H1/H2/H3
- Fett, Kursiv, Unterstrichen, Durchgestrichen
- Textfarben, Hintergrundfarben
- Aufzählungen (ungeordnet + geordnet)
- Einrückungen
- Blockzitate
- Code-Blöcke
- Linkausrichtung
- Links

### Medizinische Schnellvorlagen
Buttons über dem Editor:
- **Anschreiben** — klassisches kollegiales Anschreiben
- **Befundbericht** — strukturierter Befund mit Anamnese, Diagnose, Therapie
- **Rücküberweisung** — Überweisungsschreiben an Tierarzt
- **Verlaufsbericht** — Physiotherapie-Verlauf
- **Hundetraining** — Trainingsbericht für Hundeschulen

### Initialisierung
- Lazy-loaded via `loadQuill()` (gemeinsamer Quill-Loader)
- `window.cvrQuill` — globale Instanz
- Init in `openPdCustomVetReportModal()` wenn Modal geöffnet wird
- Reset bei erneutem Öffnen: `cvrQuill.setContents([])`

### Mobile
- Toolbar scrollbar (overflow-x: auto auf kleinen Screens)
- Editor max-height: 45vh auf Mobile

## PDF-Rendering

### Library
**TCPDF** (nicht Dompdf)

### HTML → PDF Flow
1. Quill generiert `root.innerHTML` (HTML-String)
2. JS schreibt HTML in `<input type="hidden" name="content" id="cvr-content">`
3. Controller empfängt `content` via `$this->post('content', '')`
4. `sanitizeRichText()` in Controller → XSS-Schutz vor DB-Speicherung
5. `CustomVetReportPdfService::generate()` aufgerufen
6. `looksLikeHtml()` erkennt ob HTML oder Plain-Text
7. Bei HTML: `sanitizeHtml()` → `injectPdfCss()` → `writeHTML()`
8. Bei Plain-Text: zeilenweise `MultiCell()` (Backward-Compat)

### Erlaubte HTML-Tags (PDF)
`p, h1, h2, h3, strong, em, b, i, u, s, ul, ol, li, blockquote, br, table, tr, td, th, pre, hr, span, div, a`

### Verbotene Tags (werden entfernt)
`script, style, iframe, object, embed, form, input, button, select, meta, link, base`
Zusätzlich: alle `on*`-Attribute, `javascript:` und `data:` URIs

### Quill-spezifische Konvertierungen
| Quill-Klasse | PDF-Äquivalent |
|---|---|
| `ql-indent-N` | `style="margin-left:Nem"` |
| `ql-align-center` | `style="text-align:center"` |
| `ql-align-right` | `style="text-align:right"` |
| `ql-align-justify` | `style="text-align:justify"` |
| `ql-syntax` (pre) | `<pre>` |

### CSS im PDF
Injiziert über `injectPdfCss()`:
- Headings mit proportionaler Skalierung zum `pdf_font_size`-Setting
- H2 mit Border-Bottom (Trennlinie)
- Blockquote mit grünem linken Rahmen (#8B9E8B)
- Tabellen mit Rahmen
- Pre-Blocks in Courier
- Listeneinrückung 14pt

## Sicherheit
- **XSS-Sanitizer** im Controller (`sanitizeRichText()`) — vor DB-Persistierung
- **PDF-Sanitizer** in Service (`sanitizeHtml()`) — vor PDF-Rendering
- Doppelte Absicherung: Controller + Service

## Datenbankstruktur

Tabelle: `{prefix}vet_reports`
```sql
id INT AUTO_INCREMENT
patient_id INT
created_by INT (FK users)
filename VARCHAR
type ENUM('auto','custom')
title VARCHAR(255)
content TEXT  -- speichert HTML-String für 'custom'-Berichte
recipient VARCHAR(500)
created_at TIMESTAMP
```

## Weitere Routen

| Method | Route | Action |
|---|---|---|
| GET | `/patienten/{id}/tierarztbericht` | Auto-PDF generieren |
| POST | `/patienten/{id}/tierarztbericht/custom` | Manuellen Bericht erstellen |
| GET | `/patienten/{id}/tierarztbericht/verlauf` | Berichtsliste (JSON) |
| GET | `/patienten/{id}/tierarztbericht/{rid}/download` | PDF herunterladen |
| DELETE | `/patienten/{id}/tierarztbericht/{rid}` | Bericht löschen |
| POST | `/patienten/{id}/tierarztbericht/{rid}/email` | Per E-Mail versenden |

## Bekannte Limits
- TCPDF `writeHTML()` unterstützt nur Subset von CSS (kein flexbox, kein grid)
- Bei automatischem Seitenumbruch innerhalb von `writeHTML()` wird die Sidebar nicht erneut gezeichnet (pre-existing, non-critical)
- Textfarben werden in writeHTML gerendert, aber Quill speichert sie als inline style → funktioniert korrekt

## Geänderte Dateien (Mai 2026)
- `app/Services/CustomVetReportPdfService.php` — looksLikeHtml, sanitizeHtml, injectPdfCss, writeHTML
- `plugins/vet-report/VetReportController.php` — sanitizeRichText() Helper
- `templates/partials/patient-modal-global.twig` — Quill-Editor, CSS, Templates, Form-Submit
