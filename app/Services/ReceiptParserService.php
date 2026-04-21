<?php

declare(strict_types=1);

namespace App\Services;

/**
 * ReceiptParserService
 *
 * Extrahiert strukturierte Daten (Datum, Brutto, Netto, MwSt-Satz,
 * Lieferant, Rechnungsnummer) aus Ausgabenbelegen.
 *
 * Unterstützt:
 *   • PDF — eigener leichter Text-Extractor (keine Vendor-Dependencies).
 *     Funktioniert für alle nicht-verschlüsselten PDFs mit textuellen
 *     Inhalten (FlateDecode-komprimiert oder unkomprimiert).
 *   • Bilder (JPEG, PNG) — versucht Tesseract-OCR per shell_exec wenn
 *     verfügbar, sonst liefert es nur die Datei ohne Extraktion zurück.
 *
 * Wichtig: Alle Methoden sind **fehler-resistent**. Bei unlesbaren
 * Belegen werden einfach die Rohwerte (date/null etc.) zurückgegeben
 * — der Nutzer füllt dann manuell aus.
 */
class ReceiptParserService
{
    /**
     * Parsed einen Beleg und liefert ein Array mit allen gefundenen Feldern.
     *
     * @return array{
     *   ok: bool,
     *   mime: string,
     *   text: string,
     *   date: ?string,          // YYYY-MM-DD
     *   amount_gross: ?float,
     *   amount_net: ?float,
     *   tax_rate: ?float,
     *   supplier: ?string,
     *   invoice_number: ?string,
     *   description: ?string
     * }
     */
    public function parse(string $filePath, string $mime): array
    {
        $result = [
            'ok'             => false,
            'mime'           => $mime,
            'text'           => '',
            'date'           => null,
            'amount_gross'   => null,
            'amount_net'     => null,
            'tax_rate'       => null,
            'supplier'       => null,
            'invoice_number' => null,
            'description'    => null,
        ];

        $text = '';
        try {
            if (str_starts_with($mime, 'image/')) {
                $text = $this->ocrImage($filePath);
            } elseif ($mime === 'application/pdf') {
                $text = $this->extractPdfText($filePath);
            }
        } catch (\Throwable $e) {
            error_log('[ReceiptParserService parse] ' . $e->getMessage());
        }

        if ($text === '') {
            return $result;
        }

        $result['ok']   = true;
        $result['text'] = $text;

        /* Reihenfolge: erst Steuer-Satz, dann Brutto, dann Netto — so können
         * wir bei fehlendem Netto den Wert aus Brutto × (1 / (1 + rate)) herleiten. */
        $result['tax_rate']       = $this->extractTaxRate($text);
        $amounts                  = $this->extractAmounts($text, $result['tax_rate']);
        $result['amount_gross']   = $amounts['gross'];
        $result['amount_net']     = $amounts['net'];
        $result['date']           = $this->extractDate($text);
        $result['invoice_number'] = $this->extractInvoiceNumber($text);
        $result['supplier']       = $this->extractSupplier($text);
        $result['description']    = $this->extractDescription($text, $result['supplier']);

        return $result;
    }

    /* ═══════════════════════ PDF ═══════════════════════ */

    /**
     * Leichter PDF-Text-Extractor. Gibt den Textinhalt zurück — oder
     * einen leeren String wenn nichts extrahiert werden konnte.
     *
     * Strategie:
     *  1) Content-Streams finden (zwischen `stream` und `endstream`).
     *  2) Bei FlateDecode dekomprimieren (gzuncompress).
     *  3) Aus dem Text-Operator `Tj` / `TJ` die `(...)`-Literale extrahieren.
     *  4) Escape-Sequenzen (\n \r \( \) \\ \oktal) auflösen.
     */
    public function extractPdfText(string $pdfPath): string
    {
        if (!is_file($pdfPath)) return '';
        $raw = @file_get_contents($pdfPath);
        if ($raw === false || $raw === '') return '';

        /* Fallback: wenn pdftotext verfügbar ist, ist es meistens robuster */
        $external = $this->tryPdftotext($pdfPath);
        if ($external !== '') return $external;

        $streams = [];
        $offset  = 0;
        while (($s = strpos($raw, 'stream', $offset)) !== false) {
            $e = strpos($raw, 'endstream', $s);
            if ($e === false) break;
            /* Nach „stream" folgt optional \r\n */
            $start = $s + 6;
            if (isset($raw[$start]) && $raw[$start] === "\r") $start++;
            if (isset($raw[$start]) && $raw[$start] === "\n") $start++;
            $streams[] = substr($raw, $start, $e - $start);
            $offset   = $e + 9;
        }

        $text = '';
        foreach ($streams as $stream) {
            $decoded = @gzuncompress($stream);
            if ($decoded === false) {
                /* Stream war nicht FlateDecode-kodiert → Rohtext nehmen */
                $decoded = $stream;
            }
            /* Text-Operatoren: (text)Tj  oder  [(text1)(text2)]TJ */
            if (preg_match_all('/\(((?:\\\\.|[^\\\\\)])*)\)\s*T[jJ]/s', $decoded, $m)) {
                foreach ($m[1] as $chunk) {
                    $text .= $this->decodePdfLiteral($chunk) . ' ';
                }
            }
            /* Array-Form: [(foo)(bar)] TJ */
            if (preg_match_all('/\[((?:\([^)]*\)\s*-?\d*\s*)+)\]\s*TJ/s', $decoded, $m2)) {
                foreach ($m2[1] as $arr) {
                    if (preg_match_all('/\(((?:\\\\.|[^\\\\\)])*)\)/s', $arr, $m3)) {
                        foreach ($m3[1] as $chunk) {
                            $text .= $this->decodePdfLiteral($chunk);
                        }
                        $text .= ' ';
                    }
                }
            }
        }

        /* Text aufräumen: Mehrfach-Leerzeichen, Zeilenumbrüche nach Satzende */
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/ *\n+ */', "\n", $text);
        return trim((string)$text);
    }

    private function tryPdftotext(string $pdfPath): string
    {
        if (!function_exists('shell_exec')) return '';
        /* pdftotext schreibt nach stdout wenn Outfile = `-` */
        $cmd = 'pdftotext -layout -enc UTF-8 ' . escapeshellarg($pdfPath) . ' - 2>nul';
        $out = @shell_exec($cmd);
        return is_string($out) ? trim($out) : '';
    }

    private function decodePdfLiteral(string $s): string
    {
        /* Oktal-Sequenzen \nnn */
        $s = preg_replace_callback('/\\\\([0-7]{1,3})/', fn($m) => chr((int)octdec($m[1])), $s) ?? $s;
        /* Standard-Escapes */
        $replacements = [
            '\\n'  => "\n",
            '\\r'  => "\r",
            '\\t'  => "\t",
            '\\b'  => "\x08",
            '\\f'  => "\x0C",
            '\\('  => '(',
            '\\)'  => ')',
            '\\\\' => '\\',
        ];
        $s = strtr($s, $replacements);
        /* UTF-16BE BOM erkennen und nach UTF-8 konvertieren */
        if (str_starts_with($s, "\xFE\xFF")) {
            $s = @mb_convert_encoding(substr($s, 2), 'UTF-8', 'UTF-16BE') ?: $s;
        }
        return $s;
    }

    /* ═══════════════════════ Bilder / OCR ═══════════════════════ */

    private function ocrImage(string $imgPath): string
    {
        if (!function_exists('shell_exec')) return '';
        /* Windows / Linux: tesseract muss im PATH sein. Wir unterdrücken
         * stderr und leiten die Ausgabe in stdout (option `-`). */
        $cmd = 'tesseract ' . escapeshellarg($imgPath) . ' - -l deu+eng 2>nul';
        $out = @shell_exec($cmd);
        return is_string($out) ? trim($out) : '';
    }

    /* ═══════════════════════ Extraktions-Heuristiken ═══════════════════════ */

    private function extractDate(string $text): ?string
    {
        /* Priorität: Zeilen mit „Datum", „Rechnungsdatum", „Belegdatum" zuerst */
        $priority = '/(?:rechnungs-?datum|belegdatum|datum|date)\s*[:\.]?\s*(\d{1,2})[\.\/\-](\d{1,2})[\.\/\-](\d{2,4})/i';
        if (preg_match($priority, $text, $m)) {
            return $this->normalizeDate($m[1], $m[2], $m[3]);
        }
        /* Fallback: erstes Datum im Text */
        if (preg_match('/(\d{1,2})[\.\/\-](\d{1,2})[\.\/\-](\d{2,4})/', $text, $m)) {
            return $this->normalizeDate($m[1], $m[2], $m[3]);
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
        /* Expliziter MwSt/USt-Hinweis mit Satz */
        if (preg_match('/(?:mwst\.?|ust\.?|umsatzsteuer|vat)\D*([0-9]{1,2}(?:[.,][0-9]{1,2})?)\s*%/i', $text, $m)) {
            return (float)str_replace(',', '.', $m[1]);
        }
        /* Kurzform „19 %" / „7 %" irgendwo */
        if (preg_match('/\b(19|7)\s*%/', $text, $m)) {
            return (float)$m[1];
        }
        return null;
    }

    /**
     * Sucht Brutto- und Nettobetrag. Gibt beides zurück, falls möglich.
     * @return array{gross: ?float, net: ?float}
     */
    private function extractAmounts(string $text, ?float $taxRate): array
    {
        $gross = null;
        $net   = null;

        /* Brutto-Trigger-Wörter in absteigender Priorität */
        $grossKeywords = [
            'gesamtbetrag', 'gesamtsumme', 'zu zahlen', 'rechnungsbetrag',
            'endbetrag', 'bruttobetrag', 'brutto', 'gesamt', 'summe', 'total',
        ];
        $netKeywords = [
            'nettobetrag', 'netto', 'zwischensumme', 'subtotal',
        ];

        foreach ($grossKeywords as $kw) {
            $amt = $this->findAmountNear($text, $kw);
            if ($amt !== null) { $gross = $amt; break; }
        }
        foreach ($netKeywords as $kw) {
            $amt = $this->findAmountNear($text, $kw);
            if ($amt !== null) { $net = $amt; break; }
        }

        /* Wenn nur Brutto bekannt ist + Steuersatz → Netto berechnen */
        if ($gross !== null && $net === null && $taxRate !== null && $taxRate > 0) {
            $net = round($gross / (1 + $taxRate / 100), 2);
        }
        /* Wenn nur Netto bekannt ist + Steuersatz → Brutto berechnen */
        if ($net !== null && $gross === null && $taxRate !== null && $taxRate > 0) {
            $gross = round($net * (1 + $taxRate / 100), 2);
        }

        /* Absoluter Fallback: größte Zahl im Text mit Währungs-/Dezimal-Format */
        if ($gross === null) {
            if (preg_match_all('/([0-9]{1,6}(?:[.,][0-9]{3})*[.,][0-9]{2})\s*(?:€|EUR)?/u', $text, $m)) {
                $vals = array_map([$this, 'normalizeAmount'], $m[1]);
                $vals = array_filter($vals, fn($v) => $v !== null && $v > 0);
                if (!empty($vals)) {
                    /* Annahme: Brutto = höchster Betrag */
                    $gross = max($vals);
                    if ($net === null && $taxRate !== null && $taxRate > 0) {
                        $net = round($gross / (1 + $taxRate / 100), 2);
                    }
                }
            }
        }

        return ['gross' => $gross, 'net' => $net];
    }

    private function findAmountNear(string $text, string $keyword): ?float
    {
        /* Sucht Betrag in bis zu 60 Zeichen Umgebung nach dem Keyword */
        $pattern = '/' . preg_quote($keyword, '/') . '[^0-9\-]{0,60}?([0-9]{1,6}(?:[.,][0-9]{3})*[.,][0-9]{2})/iu';
        if (preg_match($pattern, $text, $m)) {
            return $this->normalizeAmount($m[1]);
        }
        return null;
    }

    private function normalizeAmount(string $raw): ?float
    {
        /* Entscheidung Komma vs Punkt als Dezimaltrenner:
         * wenn die letzten 3 Zeichen „,dd" oder „.dd" sind → Dezimaltrenner,
         * der andere wird als Tausendertrenner entfernt. */
        $s = trim($raw);
        if (preg_match('/[,.](\d{2})$/', $s)) {
            $dec    = substr($s, -3, 1);
            $other  = $dec === ',' ? '.' : ',';
            $s      = str_replace($other, '', $s);
            $s      = str_replace($dec, '.', $s);
            $v      = (float)$s;
            return $v > 0 ? round($v, 2) : null;
        }
        return null;
    }

    private function extractInvoiceNumber(string $text): ?string
    {
        if (preg_match('/(?:rechnungs-?(?:nr|nummer)|beleg-?(?:nr|nummer)|invoice\s*(?:no|number))\s*[:#\.]?\s*([A-Z0-9\-\/]{3,20})/i', $text, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    private function extractSupplier(string $text): ?string
    {
        /* Heuristik: erste nicht-leere Zeile des Belegs — meist Logo/Firmenname */
        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $text)),
            fn($l) => $l !== '' && !preg_match('/^[\d\s.,€:\-\/]+$/', $l)
        ));
        if (empty($lines)) return null;
        $first = $lines[0];
        /* Kürzen auf max 100 Zeichen, keine Mehrzeilen */
        $first = mb_substr($first, 0, 100);
        return $first !== '' ? $first : null;
    }

    private function extractDescription(string $text, ?string $supplier): ?string
    {
        /* Versuch: Zeile mit „Verwendungszweck" oder „Leistung" oder die
         * erste nicht-triviale Zeile nach dem Lieferanten. */
        if (preg_match('/(?:verwendungszweck|leistung|betreff|bezug|produkt)\s*[:\.]\s*(.{3,100})/i', $text, $m)) {
            return trim($m[1]);
        }
        /* Fallback: zweite sinnvolle Zeile (nach Lieferant) */
        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $text)),
            fn($l) => $l !== '' && mb_strlen($l) >= 5
        ));
        $skipped = 0;
        foreach ($lines as $line) {
            if ($supplier && str_contains($line, $supplier)) { continue; }
            if ($skipped++ < 1) { continue; } /* erste skip = Header-Zeile */
            return mb_substr($line, 0, 120);
        }
        return null;
    }
}
