<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\InvoiceRepository;
use App\Repositories\SettingsRepository;

/**
 * DogschoolInvoiceService
 *
 * Brücke zwischen Hundeschul-Modulen (Kurse, Pakete) und dem bestehenden
 * Invoice-System. Erzeugt Rechnungen mit korrekten MwSt-Sätzen und verknüpft
 * sie bidirektional:
 *
 *   dogschool_enrollments.invoice_id      → invoices.id
 *   dogschool_package_balances.invoice_id → invoices.id
 *
 * Idempotent: Doppel-Rechnungen werden verhindert, existierende Verknüpfung
 * wird zurückgeliefert.
 *
 * Steuer-Logik (DE):
 *   - Standard: 19% MwSt (Dienstleistung Hundetraining, §3 UStG)
 *   - Kleinunternehmer (§19 UStG): 0% via settings.kleinunternehmer
 *   - Reduziert 7%: nur wenn Tenant explizit als anerkannte Bildungseinrichtung
 *     gekennzeichnet ist (settings.dogschool_reduced_tax = 1) und §4 Nr. 21 UStG greift
 */
class DogschoolInvoiceService
{
    public function __construct(
        private readonly Database $db,
        private readonly InvoiceRepository $invoiceRepo,
        private readonly InvoiceService $invoiceService,
        private readonly SettingsRepository $settings,
        private readonly DogschoolSchemaService $schema,
    ) {}

    /**
     * Rechnung für eine Kurs-Einschreibung erstellen.
     *
     * @return int|null Invoice-ID oder null bei Fehler
     */
    public function createForEnrollment(int $enrollmentId, ?int $userId = null): ?int
    {
        $this->schema->ensure();

        $enrollment = $this->db->safeFetch(
            "SELECT e.*, c.name AS course_name, c.price_cents, c.tax_rate, c.type AS course_type,
                    c.start_date, c.duration_minutes, c.num_sessions,
                    p.name AS patient_name,
                    o.first_name AS owner_first_name, o.last_name AS owner_last_name
               FROM `{$this->db->prefix('dogschool_enrollments')}` e
               LEFT JOIN `{$this->db->prefix('dogschool_courses')}` c ON c.id = e.course_id
               LEFT JOIN `{$this->db->prefix('patients')}` p ON p.id = e.patient_id
               LEFT JOIN `{$this->db->prefix('owners')}`   o ON o.id = e.owner_id
              WHERE e.id = ? LIMIT 1",
            [$enrollmentId]
        );
        if (!$enrollment) {
            return null;
        }

        /* Idempotenz — bereits verrechnet? */
        if (!empty($enrollment['invoice_id'])) {
            return (int)$enrollment['invoice_id'];
        }

        $taxRate  = $this->resolveTaxRate((float)($enrollment['tax_rate'] ?? 19.0));
        $priceNet = $this->centsToEurNet(
            (int)($enrollment['price_cents'] ?? $enrollment['course_price_cents'] ?? 0),
            $taxRate
        );
        if ($priceNet <= 0) {
            /* Kostenloser Kurs — keine Rechnung */
            return null;
        }

        $sessions = (int)($enrollment['num_sessions'] ?? 1);
        $desc = sprintf(
            'Kursgebühr: %s%s (%d Einheit%s) – Teilnehmer: %s',
            $enrollment['course_name'] ?? 'Hundekurs',
            !empty($enrollment['start_date']) ? ', Start ' . date('d.m.Y', strtotime($enrollment['start_date'])) : '',
            $sessions,
            $sessions === 1 ? '' : 'en',
            $enrollment['patient_name'] ?? 'Hund'
        );

        $invoiceId = (int)$this->invoiceService->create(
            [
                'invoice_number' => $this->invoiceService->generateInvoiceNumber(),
                'patient_id'     => (int)$enrollment['patient_id'] ?: null,
                'owner_id'       => (int)$enrollment['owner_id'],
                'status'         => 'open',
                'issue_date'     => date('Y-m-d'),
                'due_date'       => date('Y-m-d', strtotime('+14 days')),
                'notes'          => 'Automatisch aus Kurs-Einschreibung #' . $enrollmentId,
                'payment_method' => 'rechnung',
                'payment_terms'  => $this->settings->get('default_payment_terms', 'Zahlbar innerhalb von 14 Tagen.'),
            ],
            [
                [
                    'description' => $desc,
                    'quantity'    => 1.0,
                    'unit_price'  => round($priceNet, 2),
                    'tax_rate'    => $taxRate,
                    'total'       => round($priceNet, 2),
                ],
            ]
        );

        /* Bidirektionale Verknüpfung */
        $this->db->safeExecute(
            "UPDATE `{$this->db->prefix('dogschool_enrollments')}`
                SET invoice_id = ?, updated_at = NOW()
              WHERE id = ?",
            [$invoiceId, $enrollmentId]
        );

        return $invoiceId;
    }

    /**
     * Rechnung für einen Paket-Verkauf erstellen.
     */
    public function createForPackage(int $balanceId, ?int $userId = null): ?int
    {
        $this->schema->ensure();

        $balance = $this->db->safeFetch(
            "SELECT b.*, p.name AS package_name, p.price_cents, p.tax_rate, p.total_units,
                    o.first_name AS owner_first_name, o.last_name AS owner_last_name,
                    pt.name AS patient_name
               FROM `{$this->db->prefix('dogschool_package_balances')}` b
               LEFT JOIN `{$this->db->prefix('dogschool_packages')}` p ON p.id = b.package_id
               LEFT JOIN `{$this->db->prefix('owners')}`   o  ON o.id  = b.owner_id
               LEFT JOIN `{$this->db->prefix('patients')}` pt ON pt.id = b.patient_id
              WHERE b.id = ? LIMIT 1",
            [$balanceId]
        );
        if (!$balance) {
            return null;
        }
        if (!empty($balance['invoice_id'])) {
            return (int)$balance['invoice_id'];
        }

        $taxRate  = $this->resolveTaxRate((float)($balance['tax_rate'] ?? 19.0));
        $priceNet = $this->centsToEurNet((int)($balance['price_cents'] ?? 0), $taxRate);
        if ($priceNet <= 0) {
            return null;
        }

        $desc = sprintf(
            '%s – %d Einheiten%s',
            $balance['package_name'] ?? 'Paket',
            (int)($balance['units_total'] ?? 0),
            !empty($balance['patient_name']) ? ' (für ' . $balance['patient_name'] . ')' : ''
        );

        $invoiceId = (int)$this->invoiceService->create(
            [
                'invoice_number' => $this->invoiceService->generateInvoiceNumber(),
                'patient_id'     => (int)($balance['patient_id'] ?? 0) ?: null,
                'owner_id'       => (int)$balance['owner_id'],
                'status'         => 'open',
                'issue_date'     => date('Y-m-d'),
                'due_date'       => date('Y-m-d', strtotime('+14 days')),
                'notes'          => 'Automatisch aus Paket-Verkauf #' . $balanceId,
                'payment_method' => 'rechnung',
                'payment_terms'  => $this->settings->get('default_payment_terms', 'Zahlbar innerhalb von 14 Tagen.'),
            ],
            [
                [
                    'description' => $desc,
                    'quantity'    => 1.0,
                    'unit_price'  => round($priceNet, 2),
                    'tax_rate'    => $taxRate,
                    'total'       => round($priceNet, 2),
                ],
            ]
        );

        $this->db->safeExecute(
            "UPDATE `{$this->db->prefix('dogschool_package_balances')}`
                SET invoice_id = ?, updated_at = NOW()
              WHERE id = ?",
            [$invoiceId, $balanceId]
        );

        return $invoiceId;
    }

    /**
     * Listet alle Rechnungen die mit Hundeschul-Modulen verknüpft sind
     * (Enrollments + Packages). Für Dashboard/Steuerexport.
     */
    public function listDogschoolInvoices(string $from = '', string $to = '', string $status = ''): array
    {
        $invTab = $this->db->prefix('invoices');
        $conds  = [];
        $params = [];
        if ($from !== '') { $conds[] = 'i.issue_date >= ?'; $params[] = $from; }
        if ($to   !== '') { $conds[] = 'i.issue_date <= ?'; $params[] = $to; }
        if ($status !== '' && $status !== 'all') {
            $conds[] = 'i.status = ?';
            $params[] = $status;
        }
        /* Nur Rechnungen die aus Hundeschul-Quellen stammen (erkennbar am Notes-Muster) */
        $conds[] = "(i.notes LIKE 'Automatisch aus Kurs-Einschreibung%' OR i.notes LIKE 'Automatisch aus Paket-Verkauf%')";

        $where = 'WHERE ' . implode(' AND ', $conds);
        return $this->db->safeFetchAll(
            "SELECT i.*,
                    CONCAT(o.first_name, ' ', o.last_name) AS owner_name,
                    CASE
                        WHEN i.notes LIKE 'Automatisch aus Kurs-Einschreibung%' THEN 'course'
                        WHEN i.notes LIKE 'Automatisch aus Paket-Verkauf%'      THEN 'package'
                        ELSE 'other'
                    END AS source_type
               FROM `{$invTab}` i
               LEFT JOIN `{$this->db->prefix('owners')}` o ON o.id = i.owner_id
               {$where}
              ORDER BY i.issue_date DESC, i.id DESC",
            $params
        );
    }

    /**
     * Kennzahlen für das Hundeschul-Dashboard — nur Rechnungen aus
     * Kurs-/Paket-Automation (erkennbar am Notes-Muster, gleiche Logik wie
     * {@see listDogschoolInvoices()}).
     *
     * Liefert robuste 0-Defaults bei jedem Fehler, damit ein defektes Invoice-
     * Schema das Dashboard nicht blockiert. Brutto-Ausdruck identisch zu
     * InvoiceRepository::getStats() — konsistent mit Praxis-KPIs.
     *
     * @return array{
     *   open_count:int, open_amount:float,
     *   overdue_count:int, overdue_amount:float,
     *   paid_count_month:int, paid_amount_month:float,
     *   paid_count_year:int,  paid_amount_year:float
     * }
     */
    public function getStats(): array
    {
        $zero = [
            'open_count' => 0, 'open_amount' => 0.0,
            'overdue_count' => 0, 'overdue_amount' => 0.0,
            'paid_count_month' => 0, 'paid_amount_month' => 0.0,
            'paid_count_year'  => 0, 'paid_amount_year'  => 0.0,
        ];

        try {
            $inv = $this->db->prefix('invoices');
            $ip  = $this->db->prefix('invoice_positions');

            /* Gleiche Brutto-Formel wie InvoiceRepository::getStats(): erst
             * denormalisiertes total_gross, sonst Summe der Positionen. */
            $gross = "COALESCE(
                NULLIF(i.total_gross, 0),
                (SELECT SUM(ip.total) FROM `{$ip}` ip WHERE ip.invoice_id = i.id),
                0
            )";

            /* Dogschool-Filter — identisches Muster wie listDogschoolInvoices() */
            $dsFilter = "(i.notes LIKE 'Automatisch aus Kurs-Einschreibung%'
                       OR i.notes LIKE 'Automatisch aus Paket-Verkauf%')";

            $monthStart = date('Y-m-01');
            $yearStart  = date('Y-01-01');
            $today      = date('Y-m-d');

            $openRow = $this->db->safeFetch(
                "SELECT COUNT(*) AS c, COALESCE(SUM({$gross}), 0) AS s
                   FROM `{$inv}` i
                  WHERE i.status = 'open' AND {$dsFilter}"
            ) ?: ['c' => 0, 's' => 0];

            /* Überfällig = status='overdue' ODER open+due_date abgelaufen —
             * gleiche Semantik wie Praxis-KPIs, damit Klick-Filter und Karte
             * dieselbe Menge ergeben. */
            $overdueRow = $this->db->safeFetch(
                "SELECT COUNT(*) AS c, COALESCE(SUM({$gross}), 0) AS s
                   FROM `{$inv}` i
                  WHERE {$dsFilter}
                    AND (i.status = 'overdue' OR (i.status = 'open' AND i.due_date < ?))",
                [$today]
            ) ?: ['c' => 0, 's' => 0];

            $paidMonthRow = $this->db->safeFetch(
                "SELECT COUNT(*) AS c, COALESCE(SUM({$gross}), 0) AS s
                   FROM `{$inv}` i
                  WHERE i.status = 'paid' AND {$dsFilter}
                    AND i.issue_date >= ?",
                [$monthStart]
            ) ?: ['c' => 0, 's' => 0];

            $paidYearRow = $this->db->safeFetch(
                "SELECT COUNT(*) AS c, COALESCE(SUM({$gross}), 0) AS s
                   FROM `{$inv}` i
                  WHERE i.status = 'paid' AND {$dsFilter}
                    AND i.issue_date >= ?",
                [$yearStart]
            ) ?: ['c' => 0, 's' => 0];

            return [
                'open_count'        => (int)($openRow['c'] ?? 0),
                'open_amount'       => (float)($openRow['s'] ?? 0),
                'overdue_count'     => (int)($overdueRow['c'] ?? 0),
                'overdue_amount'    => (float)($overdueRow['s'] ?? 0),
                'paid_count_month'  => (int)($paidMonthRow['c'] ?? 0),
                'paid_amount_month' => (float)($paidMonthRow['s'] ?? 0),
                'paid_count_year'   => (int)($paidYearRow['c'] ?? 0),
                'paid_amount_year'  => (float)($paidYearRow['s'] ?? 0),
            ];
        } catch (\Throwable) {
            return $zero;
        }
    }

    /**
     * Löst den effektiven Steuersatz auf, abhängig von Tenant-Einstellungen.
     *
     * Prioritäten (hoch → niedrig):
     *   1. Kleinunternehmer-Regelung (§19 UStG) → 0%
     *   2. Anerkannte Bildungseinrichtung (§4 Nr.21 UStG) → Override auf 7% erlaubt
     *   3. Produkt-Standard aus `tax_rate`-Spalte
     */
    private function resolveTaxRate(float $defaultRate): float
    {
        if ($this->settings->get('kleinunternehmer', '0') === '1') {
            return 0.0;
        }
        return $defaultRate;
    }

    /**
     * Preis ist als Brutto-Cent gespeichert (z.B. 18000 = 180€ inkl. 19%).
     * Ins Invoice-System gehört aber netto pro Position, tax_rate separat.
     */
    private function centsToEurNet(int $priceCents, float $taxRate): float
    {
        $gross = $priceCents / 100.0;
        if ($taxRate <= 0) {
            return round($gross, 2);
        }
        return round($gross / (1 + ($taxRate / 100)), 2);
    }
}
