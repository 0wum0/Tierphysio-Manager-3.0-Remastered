<?php

declare(strict_types=1);

namespace App\Services;

/**
 * ReceiptParserService
 *
 * Extrahiert strukturierte Daten (Datum, Brutto, Netto, MwSt-Satz,
 * Lieferant, Rechnungsnummer) aus Ausgabenbelegen.
 *
 * Strategie für PDFs:
 *  1) Wenn `smalot/pdfparser` installiert ist → benutzen (robust, CMap-aware).
 *  2) Sonst: `pdftotext` via shell_exec versuchen.
 *  3) Sonst: nativer Mini-Parser (FlateDecode + Tj-Operatoren).
 *
 * Strategie für Bilder:
 *  1) Tesseract via shell_exec wenn installiert.
 *  2) Sonst: leeres Ergebnis — Datei wird trotzdem vom Controller gespeichert.
 *
 * Alle Methoden sind **fehler-resistent**: bei Problemen wird null
 * zurückgegeben und der Fehler via error_log geloggt, sodass der Nutzer
 * manuell ausfüllen kann.
 */
class ReceiptParserService
{
    /**
     * @return array{
     *   ok: bool,
     *   mime: string,
     *   text: string,
     *   text_source: string,
     *   date: ?string,
     *   amount_gross: ?float,
     *   amount_net: ?float,
     *   tax_rate: ?float,
     *   supplier: ?string,
     *   invoice_number: ?string,
     *   description: ?string,
     *   hint: ?string
     * }
     */
    public function parse(string $filePath, string $mime): array
    {
        $result = [
            'ok'             => false,
            'mime'           => $mime,
            'text'           => '',
            'text_source'    => '',
            'date'           => null,
            'amount_gross'   => null,
            'amount_net'     => null,
            'tax_rate'       => null,
            'supplier'       => null,
            'invoice_number' => null,
            'description'    => null,
            'hint'           => null,
        ];

        if (!is_file($filePath)) {
            error_log('[ReceiptParser] File nicht gefunden: ' . $filePath);
            $result['hint'] = 'Datei konnte nicht gelesen werden.';
            return $result;
        }

        /* Text holen */
        $text   = '';
        $source = '';
        try {
            if (str_starts_with($mime, 'image/')) {
                $text   = $this->ocrImage($filePath);
                $source = $text !== '' ? 'tesseract' : 'image-no-ocr';
            } elseif ($mime === 'application/pdf') {
                [$text, $source] = $this->extractPdfTextWithSource($filePath);
            } else {
                $result['hint'] = 'Dateityp wird nicht unterstützt: ' . $mime;
                return $result;
            }
        } catch (\Throwable $e) {
            error_log('[ReceiptParser parse] ' . $e->getMessage());
            $result['hint'] = 'Beleg konnte nicht analysiert werden: ' . $e->getMessage();
            return $result;
        }

        $result['text']        = mb_substr($text, 0, 8000);
        $result['text_source'] = $source;

        if ($text === '') {
            /* Für Bilder ohne OCR: dem User einen freundlichen Hinweis
             * zurückgeben statt „Fehler". ok=false damit JS Fallback-Meldung
             * zeigt, aber mit spezifischem hint. */
            $result['hint'] = str_starts_with($mime, 'image/')
                ? 'Bild wurde gespeichert. Automatische Texterkennung ist auf diesem Server nicht verfügbar — bitte die Felder manuell ausfüllen.'
                : 'Im PDF wurde kein lesbarer Text gefunden (evtl. gescanntes Bild-PDF). Bitte manuell ausfüllen.';
            error_log('[ReceiptParser] Kein Text extrahiert aus ' . $filePath . ' (source=' . $source . ')');
            return $result;
        }

        $result['ok'] = true;

        /* Extraktions-Pipeline */
        $result['tax_rate']       = $this->extractTaxRate($text);
        $amounts                  = $this->extractAmounts($text, $result['tax_rate']);
        $result['amount_gross']   = $amounts['gross'];
        $result['amount_net']     = $amounts['net'];
        $result['date']           = $this->extractDate($text);
        $result['invoice_number'] = $this->extractInvoiceNumber($text);
        $result['supplier']       = $this->extractSupplier($text);
        $result['description']    = $this->extractDescription($text, $result['supplier']);

        /* Minimal-Erfolgsbedingung: wenn wir NICHTS rauskriegen, als
         * Hinweis markieren. Trotzdem ok=true lassen, damit der User
         * sieht, dass Text gelesen wurde. */
        $anyField = $result['date'] || $result['amount_gross'] || $result['amount_net']
                 || $result['supplier'] || $result['invoice_number'];
        if (!$anyField) {
            $result['hint'] = 'Belegtext wurde gelesen, aber keine Felder konnten sicher erkannt werden. Bitte manuell ausfüllen.';
        }

        return $result;
    }

    /* ═══════════════════════ PDF ═══════════════════════ */

    /** @return array{0:string,1:string} [text, source-label] */
    private function extractPdfTextWithSource(string $pdfPath): array
    {
        /* 1. smalot/pdfparser wenn vorhanden */
        if (class_exists(\Smalot\PdfParser\Parser::class)) {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf    = $parser->parseFile($pdfPath);
                $text   = $pdf->getText();
                $text   = trim((string)$text);
                if ($text !== '') {
                    return [$this->tidyText($text), 'smalot'];
                }
            } catch (\Throwable $e) {
                error_log('[ReceiptParser smalot] ' . $e->getMessage());
            }
        }

        /* 2. pdftotext CLI */
        $ext = $this->tryPdftotext($pdfPath);
        if ($ext !== '') {
            return [$this->tidyText($ext), 'pdftotext'];
        }

        /* 3. Eigener Mini-Parser */
        $native = $this->nativePdfExtract($pdfPath);
        if ($native !== '') {
            return [$this->tidyText($native), 'native'];
        }

        return ['', 'pdf-no-text'];
    }

    public function extractPdfText(string $pdfPath): string
    {
        [$t, ] = $this->extractPdfTextWithSource($pdfPath);
        return $t;
    }

    private function tryPdftotext(string $pdfPath): string
    {
        if (!function_exists('shell_exec')) return '';
        /* stderr auf verschiedenen OS unterdrücken */
        $null = (stripos(PHP_OS, 'WIN') === 0) ? '2>nul' : '2>/dev/null';
        $cmd  = 'pdftotext -layout -enc UTF-8 ' . escapeshellarg($pdfPath) . ' - ' . $null;
        $out  = @shell_exec($cmd);
        return is_string($out) ? trim($out) : '';
    }

    private function nativePdfExtract(string $pdfPath): string
    {
        $raw = @file_get_contents($pdfPath);
        if ($raw === false || $raw === '') return '';

        $streams = [];
        $offset  = 0;
        while (($s = strpos($raw, 'stream', $offset)) !== false) {
            $e = strpos($raw, 'endstream', $s);
            if ($e === false) break;
            $start = $s + 6;
            if (isset($raw[$start]) && $raw[$start] === "\r") $start++;
            if (isset($raw[$start]) && $raw[$start] === "\n") $start++;
            $streams[] = substr($raw, $start, $e - $start);
            $offset    = $e + 9;
        }

        $text = '';
        foreach ($streams as $stream) {
            $decoded = @gzuncompress($stream);
            if ($decoded === false) $decoded = $stream;

            if (preg_match_all('/\(((?:\\\\.|[^\\\\\)])*)\)\s*T[jJ]/s', $decoded, $m)) {
                foreach ($m[1] as $chunk) $text .= $this->decodePdfLiteral($chunk) . ' ';
            }
            if (preg_match_all('/\[((?:\([^)]*\)\s*-?\d*\s*)+)\]\s*TJ/s', $decoded, $m2)) {
                foreach ($m2[1] as $arr) {
                    if (preg_match_all('/\(((?:\\\\.|[^\\\\\)])*)\)/s', $arr, $m3)) {
                        foreach ($m3[1] as $chunk) $text .= $this->decodePdfLiteral($chunk);
                        $text .= ' ';
                    }
                }
            }
        }
        return trim($text);
    }

    private function tidyText(string $text): string
    {
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n+ */', "\n", $text) ?? $text;
        return trim($text);
    }

    private function decodePdfLiteral(string $s): string
    {
        $s = preg_replace_callback('/\\\\([0-7]{1,3})/', fn($m) => chr((int)octdec($m[1])), $s) ?? $s;
        $s = strtr($s, [
            '\\n'  => "\n", '\\r'  => "\r", '\\t'  => "\t",
            '\\b'  => "\x08", '\\f'  => "\x0C",
            '\\('  => '(',  '\\)'  => ')',  '\\\\' => '\\',
        ]);
        if (str_starts_with($s, "\xFE\xFF")) {
            $s = @mb_convert_encoding(substr($s, 2), 'UTF-8', 'UTF-16BE') ?: $s;
        }
        return $s;
    }

    /* ═══════════════════════ Bilder / OCR ═══════════════════════ */

    private function ocrImage(string $imgPath): string
    {
        if (!function_exists('shell_exec')) return '';
        $null = (stripos(PHP_OS, 'WIN') === 0) ? '2>nul' : '2>/dev/null';
        $cmd  = 'tesseract ' . escapeshellarg($imgPath) . ' - -l deu+eng ' . $null;
        $out  = @shell_exec($cmd);
        return is_string($out) ? trim($out) : '';
    }

    /* ═══════════════════════ Extraktions-Heuristiken ═══════════════════════ */

    private function extractDate(string $text): ?string
    {
        /* Priorität: Zeilen mit Schlüsselwörtern zuerst */
        $keywords = '(?:rechnungs-?datum|belegdatum|datum|rechnungsdate|leistungsdatum|date|invoice\s*date)';
        if (preg_match('/' . $keywords . '\s*[:\.]?\s*(\d{1,2})[\.\/\-](\d{1,2})[\.\/\-](\d{2,4})/iu', $text, $m)) {
            return $this->normalizeDate($m[1], $m[2], $m[3]);
        }
        if (preg_match('/(\d{1,2})[\.\/\-](\d{1,2})[\.\/\-](\d{2,4})/', $text, $m)) {
            return $this->normalizeDate($m[1], $m[2], $m[3]);
        }
        /* ISO-Format */
        if (preg_match('/(\d{4})-(\d{1,2})-(\d{1,2})/', $text, $m)) {
            return $this->normalizeDate($m[3], $m[2], $m[1]);
        }
        return null;
    }

    private function normalizeDate(string $d, string $m, string $y): ?string
    {
        $d = (int)$d; $m = (int)$m; $y = (int)$y;
        if ($y < 100) $y += ($y < 70) ? 2000 : 1900;
        if (!checkdate($m, $d, $y)) return null;
        return sprintf('%04d-%02d-%02d', $y, $m, $d);
    }

    private function extractTaxRate(string $text): ?float
    {
        if (preg_match('/(?:mwst\.?|ust\.?|umsatzsteuer|vat)\D*([0-9]{1,2}(?:[.,][0-9]{1,2})?)\s*%/iu', $text, $m)) {
            return (float)str_replace(',', '.', $m[1]);
        }
        /* Kurzform „19 %" / „7 %" */
        if (preg_match('/\b(19|7)[\s,\.]*%/u', $text, $m)) {
            return (float)$m[1];
        }
        return null;
    }

    /**
     * Robuste Amount-Extraktion:
     *  1. Keyword-basierte Suche nach Brutto/Netto.
     *  2. Wenn nur eins gefunden wurde → mit tax_rate das andere berechnen.
     *  3. Fallback: ALLE Beträge im Text sammeln, den größten als Brutto.
     *  4. Zusätzlich: Zeilen mit „EUR"/„€"-Marker bevorzugen.
     *
     * @return array{gross: ?float, net: ?float}
     */
    private function extractAmounts(string $text, ?float $taxRate): array
    {
        $gross = null;
        $net   = null;

        /* 1. Keyword-basierte Suche — absteigend nach Spezifität sortiert */
        $grossKeywords = [
            'gesamtbetrag', 'gesamtsumme', 'zu zahlen', 'zahlbetrag',
            'rechnungsbetrag', 'endbetrag', 'bruttobetrag', 'brutto',
            'gesamtsumme', 'gesamt', 'summe', 'total',
        ];
        $netKeywords = [
            'nettobetrag', 'netto', 'zwischensumme', 'subtotal',
            'nettopreis',
        ];

        foreach ($grossKeywords as $kw) {
            $amt = $this->findAmountNear($text, $kw);
            if ($amt !== null) { $gross = $amt; break; }
        }
        foreach ($netKeywords as $kw) {
            $amt = $this->findAmountNear($text, $kw);
            if ($amt !== null) { $net = $amt; break; }
        }

        /* 2. Ableitung aus tax_rate */
        if ($gross !== null && $net === null && $taxRate !== null && $taxRate > 0) {
            $net = round($gross / (1 + $taxRate / 100), 2);
        }
        if ($net !== null && $gross === null && $taxRate !== null && $taxRate > 0) {
            $gross = round($net * (1 + $taxRate / 100), 2);
        }

        /* 3. Fallback: Größte Zahl in der Nähe eines €/EUR-Markers */
        if ($gross === null) {
            $candidates = $this->findAllAmounts($text);
            if (!empty($candidates)) {
                $gross = max($candidates);
                if ($net === null && $taxRate !== null && $taxRate > 0) {
                    $net = round($gross / (1 + $taxRate / 100), 2);
                }
            }
        }

        /* Sanity check — keine negativen oder absurd großen Beträge */
        if ($gross !== null && ($gross <= 0 || $gross > 1_000_000)) $gross = null;
        if ($net   !== null && ($net   <= 0 || $net   > 1_000_000)) $net   = null;

        return ['gross' => $gross, 'net' => $net];
    }

    private function findAmountNear(string $text, string $keyword): ?float
    {
        /* Erlaubt optional: „EUR", „€", „:" zwischen Keyword und Betrag.
         * Bis zu 80 Zeichen Abstand, damit mehrzeilige Layouts klappen. */
        $pattern = '/' . preg_quote($keyword, '/')
                 . '[^0-9\-]{0,80}?([0-9]{1,3}(?:[.\s]?[0-9]{3})*[,.][0-9]{2})/iu';
        if (preg_match($pattern, $text, $m)) {
            return $this->normalizeAmount($m[1]);
        }
        /* Varianten ohne Dezimalstellen: „Summe 1500 EUR" */
        $pattern2 = '/' . preg_quote($keyword, '/')
                  . '[^0-9\-]{0,80}?([0-9]{1,6})\s*(?:€|EUR)/iu';
        if (preg_match($pattern2, $text, $m)) {
            return $this->normalizeAmount($m[1]);
        }
        return null;
    }

    /**
     * Findet alle plausibel aussehenden Geldbeträge im Text.
     * Beträge mit nachfolgendem €/EUR-Marker werden bevorzugt.
     * @return float[]
     */
    private function findAllAmounts(string $text): array
    {
        $results = [];
        /* Mit Währungs-Marker (€, EUR) — höhere Sicherheit */
        if (preg_match_all('/([0-9]{1,3}(?:[.\s]?[0-9]{3})*[,.][0-9]{2})\s*(?:€|EUR)/u', $text, $m)) {
            foreach ($m[1] as $s) {
                $v = $this->normalizeAmount($s);
                if ($v !== null && $v > 0) $results[] = $v;
            }
        }
        /* Ohne Marker — nur wenn vorher keine gefunden wurden */
        if (empty($results) && preg_match_all('/\b([0-9]{1,3}(?:[.\s]?[0-9]{3})*[,.][0-9]{2})\b/u', $text, $m)) {
            foreach ($m[1] as $s) {
                $v = $this->normalizeAmount($s);
                if ($v !== null && $v > 0) $results[] = $v;
            }
        }
        return $results;
    }

    private function normalizeAmount(string $raw): ?float
    {
        $s = trim($raw);
        $s = str_replace(' ', '', $s); /* Tausendertrenner = Leerzeichen */

        /* Wenn die letzten 3 Zeichen „,dd" oder „.dd" sind: das ist der Dezimaltrenner */
        if (preg_match('/[,.](\d{2})$/', $s)) {
            $dec   = substr($s, -3, 1);
            $other = $dec === ',' ? '.' : ',';
            $s     = str_replace($other, '', $s);
            $s     = str_replace($dec, '.', $s);
            $v     = (float)$s;
            return $v > 0 ? round($v, 2) : null;
        }

        /* Ganzzahl ohne Dezimalstellen (z.B. „1500") */
        if (ctype_digit($s)) {
            $v = (float)$s;
            return $v > 0 ? round($v, 2) : null;
        }

        return null;
    }

    private function extractInvoiceNumber(string $text): ?string
    {
        if (preg_match(
            '/(?:rechnungs-?(?:nr\.?|nummer)|beleg-?(?:nr\.?|nummer)|invoice\s*(?:no\.?|number)|re[-\s]?nr\.?)\s*[:#\.]?\s*([A-Z0-9][A-Z0-9\-\/_]{2,30})/iu',
            $text,
            $m
        )) {
            return trim($m[1]);
        }
        return null;
    }

    private function extractSupplier(string $text): ?string
    {
        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $text)),
            fn($l) => $l !== '' && !preg_match('/^[\d\s.,€:\-\/]+$/', $l)
        ));
        if (empty($lines)) return null;
        /* Überspringe häufige Header-Zeilen ohne Firmennamen-Charakter */
        foreach ($lines as $l) {
            if (mb_strlen($l) < 3) continue;
            if (preg_match('/^(rechnung|beleg|quittung|invoice|receipt)\b/iu', $l)) continue;
            return mb_substr($l, 0, 100);
        }
        return null;
    }

    private function extractDescription(string $text, ?string $supplier): ?string
    {
        if (preg_match('/(?:verwendungszweck|leistung|betreff|bezug|produkt|bezeichnung)\s*[:\.]\s*(.{3,120})/iu', $text, $m)) {
            return trim($m[1]);
        }
        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $text)),
            fn($l) => $l !== '' && mb_strlen($l) >= 5
        ));
        $skipped = 0;
        foreach ($lines as $line) {
            if ($supplier && str_contains($line, $supplier)) continue;
            if ($skipped++ < 1) continue;
            return mb_substr($line, 0, 120);
        }
        return null;
    }
}
