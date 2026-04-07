<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Repository;

class InvoiceRepository extends Repository
{
    protected string $table = 'invoices';

    public function __construct(Database $db)
    {
        parent::__construct($db);
    }

    /**
     * Automatically adds payment_method + paid_at columns if they don't exist yet.
     * Safe to call multiple times — uses IF NOT EXISTS.
     */
    public function ensurePaymentMethodColumns(): void
    {
        /* Add payment_method column if missing */
        try {
            $this->db->execute(
                "ALTER TABLE `{$this->t('invoices')}`
                    ADD COLUMN IF NOT EXISTS `payment_method` ENUM('rechnung','bar') NOT NULL DEFAULT 'rechnung',
                    ADD COLUMN IF NOT EXISTS `paid_at` DATETIME NULL"
            );
        } catch (\Throwable) {
            /* Column already exists or unsupported — safe to ignore */
        }

        /* Add diagnosis column if missing (migration 016) */
        try {
            $this->db->execute(
                "ALTER TABLE `{$this->t('invoices')}` ADD COLUMN IF NOT EXISTS `diagnosis` TEXT NULL"
            );
        } catch (\Throwable) {}

        /* Add index only if it doesn't exist yet (MySQL-compatible check) */
        try {
            $exists = $this->db->fetchColumn(
                "SELECT COUNT(*) FROM information_schema.STATISTICS
                 WHERE table_schema = DATABASE()
                   AND table_name = '{$this->t('invoices')}'
                   AND index_name = 'idx_payment_method'"
            );
            if ((int)$exists === 0) {
                $this->db->execute(
                    "ALTER TABLE `{$this->t('invoices')}` ADD INDEX `idx_payment_method` (`payment_method`)"
                );
            }
        } catch (\Throwable) {
            /* Index creation failed — non-critical, ignore */
        }
    }

    public function getInvoiceStatsForPatients(array $patientIds): array
    {
        if (empty($patientIds)) return [];

        $ids = implode(',', array_map('intval', $patientIds));

        $rows = $this->db->fetchAll(
            "SELECT p.id AS patient_id,
                    SUM(CASE WHEN i.status IN ('open','overdue','draft') THEN 1 ELSE 0 END) AS open_count,
                    SUM(CASE WHEN i.status = 'paid' THEN 1 ELSE 0 END) AS paid_count
             FROM `{$this->t('patients')}` p
             LEFT JOIN `{$this->t('invoices')}` i ON (i.patient_id = p.id OR (i.patient_id IS NULL AND i.owner_id = p.owner_id))
             WHERE p.id IN ({$ids})
             GROUP BY p.id"
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['patient_id']] = [
                'open_count' => (int)$row['open_count'],
                'paid_count' => (int)$row['paid_count'],
            ];
        }
        return $map;
    }

    public function getInvoiceStatsByPatientId(int $patientId): array
    {
        /* Find owner_id for this patient */
        $ownerId = (int)$this->db->fetchColumn(
            "SELECT owner_id FROM `{$this->t('patients')}` WHERE id = ?",
            [$patientId]
        );

        /* Match by patient_id directly OR by owner_id (invoices linked only to owner) */
        $openCount = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->t('invoices')}`
             WHERE status IN ('open','overdue','draft')
               AND (patient_id = ? OR (patient_id IS NULL AND owner_id = ?))",
            [$patientId, $ownerId]
        );
        $paidCount = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->t('invoices')}`
             WHERE status = 'paid'
               AND (patient_id = ? OR (patient_id IS NULL AND owner_id = ?))",
            [$patientId, $ownerId]
        );
        return [
            'open_count' => $openCount,
            'paid_count' => $paidCount,
        ];
    }

    public function getPaginated(int $page, int $perPage, string $status = '', string $search = ''): array
    {
        $conditions = [];
        $params     = [];

        if (!empty($status)) {
            $conditions[] = "i.status = ?";
            $params[]     = $status;
        }

        if (!empty($search)) {
            $conditions[] = "(i.invoice_number LIKE ? OR CONCAT(o.first_name, ' ', o.last_name) LIKE ? OR p.name LIKE ?)";
            $params = array_merge($params, ["%{$search}%", "%{$search}%", "%{$search}%"]);
        }

        $where  = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $offset = ($page - 1) * $perPage;

        $inv = $this->t('invoices'); $own = $this->t('owners'); $pat = $this->t('patients');
        $total = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `{$inv}` i
             LEFT JOIN `{$own}` o ON i.owner_id = o.id
             LEFT JOIN `{$pat}` p ON i.patient_id = p.id
             {$where}",
            $params
        );

        $ip = $this->t('invoice_positions');
        $items = $this->db->fetchAll(
            "SELECT i.*,
                    COALESCE(NULLIF(i.total_gross, 0), (SELECT SUM(ip.total) FROM `{$ip}` ip WHERE ip.invoice_id = i.id)) AS total_gross,
                    CONCAT(o.first_name, ' ', o.last_name) AS owner_name,
                    p.name AS patient_name,
                    CASE WHEN i.status IN ('open','overdue') AND i.due_date < CURDATE()
                         THEN DATEDIFF(CURDATE(), i.due_date)
                         ELSE NULL END AS days_overdue
             FROM `{$inv}` i
             LEFT JOIN `{$own}` o ON i.owner_id = o.id
             LEFT JOIN `{$pat}` p ON i.patient_id = p.id
             {$where}
             ORDER BY i.created_at DESC
             LIMIT ? OFFSET ?",
            [...$params, $perPage, $offset]
        );

        return [
            'items'     => $items,
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => (int)ceil($total / $perPage),
            'has_next'  => ($page * $perPage) < $total,
            'has_prev'  => $page > 1,
        ];
    }

    public function getPositions(int $invoiceId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM `{$this->t('invoice_positions')}` WHERE invoice_id = ? ORDER BY sort_order ASC",
            [$invoiceId]
        );
    }

    public function deletePositions(int $invoiceId): void
    {
        $this->db->execute("DELETE FROM `{$this->t('invoice_positions')}` WHERE invoice_id = ?", [$invoiceId]);
    }

    public function addPosition(int $invoiceId, array $pos, int $sortOrder): void
    {
        $this->db->execute(
            "INSERT INTO `{$this->t('invoice_positions')}` (invoice_id, description, quantity, unit_price, tax_rate, total, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $invoiceId,
                $pos['description'],
                $pos['quantity'],
                $pos['unit_price'],
                $pos['tax_rate'],
                $pos['total'],
                $sortOrder,
            ]
        );
    }

    public function getStats(): array
    {
        $now   = date('Y-m-d');
        $week  = date('Y-m-d', strtotime('-7 days'));
        $month = date('Y-m-01');
        $year  = date('Y-01-01');
        $prevMonth = date('Y-m-d', strtotime('-1 month'));
        $prevYear  = date('Y-01-01', strtotime('-1 year'));

        $inv = $this->t('invoices');
        $revenueWeek = (float)$this->db->fetchColumn(
            "SELECT COALESCE(SUM(total_gross), 0) FROM `{$inv}` WHERE status = 'paid' AND issue_date >= ?",
            [$week]
        );

        $revenueMonth = (float)$this->db->fetchColumn(
            "SELECT COALESCE(SUM(total_gross), 0) FROM `{$inv}` WHERE status = 'paid' AND issue_date >= ?",
            [$month]
        );

        $revenueYear = (float)$this->db->fetchColumn(
            "SELECT COALESCE(SUM(total_gross), 0) FROM `{$inv}` WHERE status = 'paid' AND issue_date >= ?",
            [$year]
        );

        $ip2 = $this->t('invoice_positions');
        $revenueTotal = (float)$this->db->fetchColumn(
            "SELECT COALESCE(SUM(total_gross), 0) FROM `{$inv}` WHERE status = 'paid'"
        );

        $prevMonthRevenue = (float)$this->db->fetchColumn(
            "SELECT COALESCE(SUM(total_gross), 0) FROM `{$inv}` WHERE status = 'paid' AND issue_date >= ? AND issue_date < ?",
            [$prevMonth, $month]
        );

        $prevYearRevenue = (float)$this->db->fetchColumn(
            "SELECT COALESCE(SUM(total_gross), 0) FROM `{$inv}` WHERE status = 'paid' AND issue_date >= ? AND issue_date < ?",
            [$prevYear, $year]
        );

        $openCount = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `{$inv}` WHERE status = 'open'"
        );

        $overdueCount = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `{$inv}` WHERE status = 'overdue' OR (status = 'open' AND due_date < ?)",
            [$now]
        );

        $openAmount = (float)$this->db->fetchColumn(
            "SELECT COALESCE(SUM(COALESCE(NULLIF(i.total_gross,0),(SELECT SUM(ip.total) FROM `{$ip2}` ip WHERE ip.invoice_id=i.id))),0)
             FROM `{$inv}` i WHERE i.status = 'open'"
        );

        $overdueAmount = (float)$this->db->fetchColumn(
            "SELECT COALESCE(SUM(COALESCE(NULLIF(i.total_gross,0),(SELECT SUM(ip.total) FROM `{$ip2}` ip WHERE ip.invoice_id=i.id))),0)
             FROM `{$inv}` i WHERE i.status = 'overdue' OR (i.status = 'open' AND i.due_date < ?)",
            [$now]
        );

        $draftCount = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `{$inv}` WHERE status = 'draft'"
        );

        $paidCount = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM `{$inv}` WHERE status = 'paid'"
        );

        /* migration-006: split paid by payment_method */
        $paidInvoiceAmount = 0.0;
        $paidInvoiceCount  = 0;
        $cashAmount        = 0.0;
        $cashCount         = 0;
        try {
            $paidInvoiceAmount = (float)$this->db->fetchColumn(
                "SELECT COALESCE(SUM(total_gross), 0) FROM `{$inv}` WHERE status = 'paid' AND payment_method = 'rechnung'"
            );
            $paidInvoiceCount = (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM `{$inv}` WHERE status = 'paid' AND payment_method = 'rechnung'"
            );
            $cashAmount = (float)$this->db->fetchColumn(
                "SELECT COALESCE(SUM(total_gross), 0) FROM `{$inv}` WHERE status = 'paid' AND payment_method = 'bar'"
            );
            $cashCount = (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM `{$inv}` WHERE status = 'paid' AND payment_method = 'bar'"
            );
        } catch (\Throwable) {
            /* migration 006 not yet run — fall back to totals */
            $paidInvoiceAmount = $revenueTotal;
            $paidInvoiceCount  = $paidCount;
        }

        return [
            'revenue_week'         => $revenueWeek,
            'revenue_month'        => $revenueMonth,
            'revenue_year'         => $revenueYear,
            'revenue_total'        => $revenueTotal,
            'prev_month_revenue'   => $prevMonthRevenue,
            'prev_year_revenue'    => $prevYearRevenue,
            'open_count'           => $openCount,
            'overdue_count'        => $overdueCount,
            'open_amount'          => $openAmount,
            'overdue_amount'       => $overdueAmount,
            'draft_count'          => $draftCount,
            'paid_count'           => $paidCount,
            'paid_invoice_amount'  => $paidInvoiceAmount,
            'paid_invoice_count'   => $paidInvoiceCount,
            'cash_amount'          => $cashAmount,
            'cash_count'           => $cashCount,
            'month_change'         => $prevMonthRevenue > 0
                ? round((($revenueMonth - $prevMonthRevenue) / $prevMonthRevenue) * 100, 1)
                : 0,
            'year_change'          => $prevYearRevenue > 0
                ? round((($revenueYear - $prevYearRevenue) / $prevYearRevenue) * 100, 1)
                : 0,
        ];
    }

    public function getChartData(string $type): array
    {
        if ($type === 'monthly') {
            $rows = $this->db->fetchAll(
                "SELECT DATE_FORMAT(issue_date, '%Y-%m') AS period,
                        COALESCE(SUM(total_gross), 0) AS revenue
                 FROM `{$this->t('invoices')}`
                 WHERE status = 'paid' AND issue_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                 GROUP BY period
                 ORDER BY period ASC"
            );
        } else {
            $rows = $this->db->fetchAll(
                "SELECT DATE_FORMAT(issue_date, '%Y-%u') AS period,
                        COALESCE(SUM(total_gross), 0) AS revenue
                 FROM `{$this->t('invoices')}`
                 WHERE status = 'paid' AND issue_date >= DATE_SUB(NOW(), INTERVAL 12 WEEK)
                 GROUP BY period
                 ORDER BY period ASC"
            );
        }

        return [
            'labels' => array_column($rows, 'period'),
            'data'   => array_map('floatval', array_column($rows, 'revenue')),
        ];
    }

    public function getChartDataByStatus(string $type): array
    {
        $statuses = ['paid', 'open', 'overdue', 'draft'];

        if ($type === 'monthly') {
            $periods = [];
            for ($i = 11; $i >= 0; $i--) {
                $periods[] = date('Y-m', strtotime("-{$i} months"));
            }
            $rows = $this->db->fetchAll(
                "SELECT DATE_FORMAT(issue_date, '%Y-%m') AS period,
                        status,
                        COALESCE(SUM(total_gross), 0) AS amount
                 FROM `{$this->t('invoices')}`
                 WHERE status IN ('paid','open','overdue','draft')
                   AND issue_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                 GROUP BY period, status
                 ORDER BY period ASC"
            );
            $labels = array_map(function ($m) {
                $dt = \DateTime::createFromFormat('Y-m', $m);
                return $dt ? $dt->format('M y') : $m;
            }, $periods);
        } else {
            $periods = [];
            for ($i = 11; $i >= 0; $i--) {
                /* Use ISO year+week (Y-W) — must match DATE_FORMAT('%x-%v') in MySQL */
                $periods[] = date('o-W', strtotime("-{$i} weeks"));
            }
            $rows = $this->db->fetchAll(
                "SELECT DATE_FORMAT(issue_date, '%x-%v') AS period,
                        status,
                        COALESCE(SUM(total_gross), 0) AS amount
                 FROM `{$this->t('invoices')}`
                 WHERE status IN ('paid','open','overdue','draft')
                   AND issue_date >= DATE_SUB(NOW(), INTERVAL 12 WEEK)
                 GROUP BY period, status
                 ORDER BY period ASC"
            );
            $labels = array_map(function ($w) {
                [$year, $week] = explode('-', $w);
                return 'KW ' . ltrim($week, '0') . ' \'' . substr($year, 2);
            }, $periods);
        }

        /* Index by period+status */
        $indexed = [];
        foreach ($rows as $r) {
            $indexed[$r['period']][$r['status']] = (float)$r['amount'];
        }

        $series = [];
        foreach ($statuses as $st) {
            $data = [];
            foreach ($periods as $p) {
                $data[] = round($indexed[$p][$st] ?? 0, 2);
            }
            $series[$st] = $data;
        }

        return [
            'labels'  => $labels,
            'paid'    => $series['paid'],
            'open'    => $series['open'],
            'overdue' => $series['overdue'],
            'draft'   => $series['draft'],
        ];
    }

    public function getMonthlyChartData(): array
    {
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $months[] = date('Y-m', strtotime("-{$i} months"));
        }

        $rows = $this->db->fetchAll(
            "SELECT DATE_FORMAT(issue_date, '%Y-%m') AS month,
                    COALESCE(SUM(CASE WHEN status = 'paid' THEN total_gross ELSE 0 END), 0) AS paid,
                    COALESCE(SUM(CASE WHEN status IN ('open','overdue') THEN total_gross ELSE 0 END), 0) AS open
             FROM `{$this->t('invoices')}`
             WHERE issue_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
             GROUP BY month
             ORDER BY month ASC"
        );

        $indexed = [];
        foreach ($rows as $r) {
            $indexed[$r['month']] = $r;
        }

        $labels = [];
        $paid   = [];
        $open   = [];

        foreach ($months as $m) {
            $de = \DateTime::createFromFormat('Y-m', $m);
            $labels[] = $de ? $de->format('M y') : $m;
            $paid[]   = round((float)($indexed[$m]['paid'] ?? 0), 2);
            $open[]   = round((float)($indexed[$m]['open'] ?? 0), 2);
        }

        return ['labels' => $labels, 'paid' => $paid, 'open' => $open];
    }

    public function getNextInvoiceNumber(string $prefix = 'RE', int $startNumber = 1000): string
    {
        $lastNumber = $this->db->fetchColumn(
            "SELECT invoice_number FROM `{$this->t('invoices')}` ORDER BY id DESC LIMIT 1"
        );

        if (!$lastNumber) {
            return $prefix . '-' . str_pad((string)$startNumber, 4, '0', STR_PAD_LEFT);
        }

        preg_match('/(\d+)$/', (string)$lastNumber, $matches);
        $next = isset($matches[1]) ? (int)$matches[1] + 1 : $startNumber;
        return $prefix . '-' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
    }

    public function updateTotals(int $id, float $totalNet, float $totalTax, float $totalGross): void
    {
        $this->db->execute(
            "UPDATE `{$this->t('invoices')}` SET total_net = ?, total_tax = ?, total_gross = ? WHERE id = ?",
            [$totalNet, $totalTax, $totalGross, $id]
        );
    }

    public function markEmailSent(int $id): void
    {
        $this->db->execute(
            "UPDATE `{$this->t('invoices')}` SET email_sent_at = NOW() WHERE id = ?",
            [$id]
        );
    }

    public function markOverdueAutomatic(): void
    {
        $this->db->execute(
            "UPDATE `{$this->t('invoices')}`
             SET status = 'overdue'
             WHERE status = 'open'
               AND due_date IS NOT NULL
               AND due_date < CURDATE()"
        );
    }

    public function updateStatus(int $id, string $status, ?string $paidAt = null): void
    {
        if ($paidAt !== null) {
            $this->db->execute(
                "UPDATE `{$this->t('invoices')}` SET status = ?, paid_at = ?, updated_at = NOW() WHERE id = ?",
                [$status, $paidAt, $id]
            );
        } else {
            $this->db->execute(
                "UPDATE `{$this->t('invoices')}` SET status = ?, paid_at = NULL, updated_at = NOW() WHERE id = ?",
                [$status, $id]
            );
        }
    }

    /* ══════════════════════════════════════════════════════════
       ANALYTICS — Finance Charts
    ══════════════════════════════════════════════════════════ */

    /** Monthly revenue for the last N months (paid invoices only) */
    public function getRevenueByMonth(int $months = 24): array
    {
        $rows = $this->db->fetchAll(
            "SELECT DATE_FORMAT(issue_date,'%Y-%m') AS month,
                    SUM(total_gross) AS revenue,
                    COUNT(*) AS count
             FROM `{$this->t('invoices')}`
             WHERE status = 'paid'
               AND issue_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
             GROUP BY month
             ORDER BY month ASC",
            [$months]
        );
        $map = [];
        foreach ($rows as $r) { $map[$r['month']] = ['revenue' => (float)$r['revenue'], 'count' => (int)$r['count']]; }
        $labels = $revenue = $counts = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $key = date('Y-m', strtotime("-{$i} months"));
            $labels[]  = date('M Y', strtotime($key . '-01'));
            $revenue[] = $map[$key]['revenue'] ?? 0;
            $counts[]  = $map[$key]['count']   ?? 0;
        }
        return ['labels' => $labels, 'revenue' => $revenue, 'counts' => $counts];
    }

    /** Revenue by quarter for the last N years */
    public function getRevenueByQuarter(int $years = 3): array
    {
        $rows = $this->db->fetchAll(
            "SELECT YEAR(issue_date) AS yr, QUARTER(issue_date) AS qtr,
                    SUM(total_gross) AS revenue, COUNT(*) AS count
             FROM `{$this->t('invoices')}`
             WHERE status = 'paid'
               AND issue_date >= DATE_SUB(CURDATE(), INTERVAL ? YEAR)
             GROUP BY yr, qtr
             ORDER BY yr ASC, qtr ASC",
            [$years]
        );
        $labels = $revenue = [];
        foreach ($rows as $r) {
            $labels[]  = 'Q' . $r['qtr'] . ' ' . $r['yr'];
            $revenue[] = (float)$r['revenue'];
        }
        return ['labels' => $labels, 'revenue' => $revenue];
    }

    /** Revenue by year */
    public function getRevenueByYear(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT YEAR(issue_date) AS yr, SUM(total_gross) AS revenue, COUNT(*) AS count
             FROM `{$this->t('invoices')}` WHERE status = 'paid'
             GROUP BY yr ORDER BY yr ASC"
        );
        $labels = $revenue = $counts = [];
        foreach ($rows as $r) {
            $labels[]  = (string)$r['yr'];
            $revenue[] = (float)$r['revenue'];
            $counts[]  = (int)$r['count'];
        }
        return ['labels' => $labels, 'revenue' => $revenue, 'counts' => $counts];
    }

    /** Outstanding vs paid vs overdue totals for waterfall */
    public function getFinancialSummary(): array
    {
        $row = $this->db->fetch(
            "SELECT
                SUM(CASE WHEN status='paid'    THEN total_gross ELSE 0 END) AS paid,
                SUM(CASE WHEN status='open'    THEN total_gross ELSE 0 END) AS open,
                SUM(CASE WHEN status='overdue' THEN total_gross ELSE 0 END) AS overdue,
                SUM(CASE WHEN status='draft'   THEN total_gross ELSE 0 END) AS draft,
                COUNT(CASE WHEN status='paid'    THEN 1 END) AS paid_count,
                COUNT(CASE WHEN status='open'    THEN 1 END) AS open_count,
                COUNT(CASE WHEN status='overdue' THEN 1 END) AS overdue_count,
                AVG(CASE WHEN status='paid' THEN total_gross END) AS avg_invoice,
                MAX(CASE WHEN status='paid' THEN total_gross END) AS max_invoice,
                MIN(CASE WHEN status='paid' AND total_gross > 0 THEN total_gross END) AS min_invoice
             FROM `{$this->t('invoices')}`"
        );
        return $row ?: [];
    }

    /** Average days to payment per owner (only paid invoices with paid_at) */
    public function getOwnerPaymentSpeed(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT
                CONCAT(o.first_name, ' ', o.last_name) AS owner_name,
                o.id AS owner_id,
                COUNT(i.id) AS invoice_count,
                ROUND(AVG(DATEDIFF(i.paid_at, i.issue_date)), 1) AS avg_days,
                MIN(DATEDIFF(i.paid_at, i.issue_date)) AS min_days,
                MAX(DATEDIFF(i.paid_at, i.issue_date)) AS max_days,
                SUM(i.total_gross) AS total_paid,
                AVG(i.total_gross) AS avg_amount
             FROM `{$this->t('invoices')}` i
             JOIN `{$this->t('owners')}` o ON o.id = i.owner_id
             WHERE i.status = 'paid'
               AND i.paid_at IS NOT NULL
               AND i.issue_date IS NOT NULL
               AND DATEDIFF(i.paid_at, i.issue_date) >= 0
             GROUP BY o.id, o.first_name, o.last_name
             HAVING invoice_count >= 1
             ORDER BY avg_days ASC"
        );
        return $rows ?: [];
    }

    /** Owner payment totals — revenue per owner */
    public function getOwnerRevenue(int $limit = 15): array
    {
        $rows = $this->db->fetchAll(
            "SELECT
                CONCAT(o.first_name, ' ', o.last_name) AS owner_name,
                o.id AS owner_id,
                SUM(i.total_gross) AS total,
                COUNT(i.id) AS count,
                SUM(CASE WHEN i.status='paid' THEN i.total_gross ELSE 0 END) AS paid,
                SUM(CASE WHEN i.status IN ('open','overdue') THEN i.total_gross ELSE 0 END) AS outstanding
             FROM `{$this->t('invoices')}` i
             JOIN `{$this->t('owners')}` o ON o.id = i.owner_id
             GROUP BY o.id, o.first_name, o.last_name
             ORDER BY total DESC
             LIMIT ?",
            [$limit]
        );
        return $rows ?: [];
    }

    /** Overdue aging buckets: 0-30, 31-60, 61-90, 90+ days */
    public function getOverdueAging(): array
    {
        $row = $this->db->fetch(
            "SELECT
                SUM(CASE WHEN DATEDIFF(CURDATE(),due_date) BETWEEN 1  AND 30  THEN total_gross ELSE 0 END) AS d30,
                SUM(CASE WHEN DATEDIFF(CURDATE(),due_date) BETWEEN 31 AND 60  THEN total_gross ELSE 0 END) AS d60,
                SUM(CASE WHEN DATEDIFF(CURDATE(),due_date) BETWEEN 61 AND 90  THEN total_gross ELSE 0 END) AS d90,
                SUM(CASE WHEN DATEDIFF(CURDATE(),due_date) > 90               THEN total_gross ELSE 0 END) AS d90p,
                COUNT(CASE WHEN DATEDIFF(CURDATE(),due_date) BETWEEN 1  AND 30  THEN 1 END) AS c30,
                COUNT(CASE WHEN DATEDIFF(CURDATE(),due_date) BETWEEN 31 AND 60  THEN 1 END) AS c60,
                COUNT(CASE WHEN DATEDIFF(CURDATE(),due_date) BETWEEN 61 AND 90  THEN 1 END) AS c90,
                COUNT(CASE WHEN DATEDIFF(CURDATE(),due_date) > 90               THEN 1 END) AS c90p
             FROM `{$this->t('invoices')}`
             WHERE status IN ('open','overdue') AND due_date IS NOT NULL AND due_date < CURDATE()"
        );
        return $row ?: ['d30'=>0,'d60'=>0,'d90'=>0,'d90p'=>0,'c30'=>0,'c60'=>0,'c90'=>0,'c90p'=>0];
    }

    /** Payment method distribution */
    public function getPaymentMethodStats(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT
                COALESCE(NULLIF(payment_method,''), 'unbekannt') AS method,
                COUNT(*) AS count,
                SUM(total_gross) AS total
             FROM `{$this->t('invoices')}`
             WHERE status = 'paid'
             GROUP BY method
             ORDER BY total DESC"
        );
        return $rows ?: [];
    }

    /** Last 6 months paid revenue for linear regression forecast */
    public function getRevenueForForecast(int $months = 18): array
    {
        $rows = $this->db->fetchAll(
            "SELECT DATE_FORMAT(issue_date,'%Y-%m') AS month, SUM(total_gross) AS revenue
             FROM `{$this->t('invoices')}`
             WHERE status = 'paid'
               AND issue_date >= DATE_SUB(CURDATE(), INTERVAL ? MONTH)
             GROUP BY month
             ORDER BY month ASC",
            [$months]
        );
        $map = [];
        foreach ($rows as $r) { $map[$r['month']] = (float)$r['revenue']; }
        $result = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $key = date('Y-m', strtotime("-{$i} months"));
            $result[] = ['month' => $key, 'revenue' => $map[$key] ?? 0];
        }
        return $result;
    }

    /** Most active owners by invoice count + total volume */
    public function getOwnerActivity(int $limit = 15): array
    {
        $rows = $this->db->fetchAll(
            "SELECT
                CONCAT(o.first_name, ' ', o.last_name) AS owner_name,
                o.id AS owner_id,
                COUNT(i.id) AS invoice_count,
                SUM(i.total_gross) AS total_volume,
                SUM(CASE WHEN i.status='paid' THEN i.total_gross ELSE 0 END) AS paid_volume,
                SUM(CASE WHEN i.status IN ('open','overdue') THEN i.total_gross ELSE 0 END) AS outstanding_volume,
                MAX(i.issue_date) AS last_invoice_date,
                COUNT(CASE WHEN i.issue_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY) THEN 1 END) AS invoices_last_90d
             FROM `{$this->t('invoices')}` i
             JOIN `{$this->t('owners')}` o ON o.id = i.owner_id
             GROUP BY o.id, o.first_name, o.last_name
             ORDER BY invoice_count DESC, total_volume DESC
             LIMIT ?",
            [$limit]
        );
        return $rows ?: [];
    }

    /** Per-owner monthly revenue for last 12 months (top N owners by volume) */
    public function getOwnerMonthlyRevenue(int $topN = 5): array
    {
        $topOwners = $this->db->fetchAll(
            "SELECT o.id, CONCAT(o.first_name,' ',o.last_name) AS owner_name
             FROM `{$this->t('invoices')}` i
             JOIN `{$this->t('owners')}` o ON o.id = i.owner_id
             WHERE i.status = 'paid'
             GROUP BY o.id, o.first_name, o.last_name
             ORDER BY SUM(i.total_gross) DESC
             LIMIT ?",
            [$topN]
        );
        if (!$topOwners) return [];

        $result = [];
        foreach ($topOwners as $owner) {
            $rows = $this->db->fetchAll(
                "SELECT DATE_FORMAT(issue_date,'%Y-%m') AS month, SUM(total_gross) AS revenue
                 FROM `{$this->t('invoices')}`
                 WHERE status = 'paid' AND owner_id = ?
                   AND issue_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                 GROUP BY month ORDER BY month ASC",
                [$owner['id']]
            );
            $map = [];
            foreach ($rows as $r) { $map[$r['month']] = (float)$r['revenue']; }
            $monthly = [];
            for ($i = 11; $i >= 0; $i--) {
                $key = date('Y-m', strtotime("-{$i} months"));
                $monthly[] = $map[$key] ?? 0;
            }
            $result[] = [
                'owner_id'   => $owner['id'],
                'owner_name' => $owner['owner_name'],
                'monthly'    => $monthly,
            ];
        }
        return $result;
    }

    /** Top treatment positions by revenue */
    public function getTopPositions(int $limit = 10): array
    {
        $rows = $this->db->fetchAll(
            "SELECT
                ip.description,
                COUNT(*) AS count,
                SUM(ip.total) AS total,
                AVG(ip.unit_price) AS avg_price
             FROM `{$this->t('invoice_positions')}` ip
             JOIN `{$this->t('invoices')}` i ON i.id = ip.invoice_id
             WHERE i.status = 'paid'
             GROUP BY ip.description
             ORDER BY total DESC
             LIMIT ?",
            [$limit]
        );
        return $rows ?: [];
    }
}
