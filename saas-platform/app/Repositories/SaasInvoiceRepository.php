<?php

declare(strict_types=1);

namespace Saas\Repositories;

use Saas\Core\Database;

class SaasInvoiceRepository
{
    public function __construct(private Database $db) {}

    // ── CRUD ─────────────────────────────────────────────────────────────────

    public function findById(int $id): array|false
    {
        return $this->db->fetch(
            "SELECT i.*, t.practice_name AS tenant_name, t.email AS tenant_email,
                    t.owner_name, t.address, t.city, t.zip, t.country
             FROM saas_invoices i
             JOIN tenants t ON t.id = i.tenant_id
             WHERE i.id = ?",
            [$id]
        );
    }

    public function create(array $data): string
    {
        $cols = implode(', ', array_map(fn($k) => "`{$k}`", array_keys($data)));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $this->db->insert(
            "INSERT INTO saas_invoices ({$cols}) VALUES ({$placeholders})",
            array_values($data)
        );
        return $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $set = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($data)));
        $this->db->execute(
            "UPDATE saas_invoices SET {$set} WHERE id = ?",
            [...array_values($data), $id]
        );
    }

    public function delete(int $id): void
    {
        $this->db->execute("DELETE FROM saas_invoices WHERE id = ?", [$id]);
    }

    // ── Positionen ────────────────────────────────────────────────────────────

    public function getPositions(int $invoiceId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM saas_invoice_positions WHERE invoice_id = ? ORDER BY sort_order ASC",
            [$invoiceId]
        );
    }

    public function deletePositions(int $invoiceId): void
    {
        $this->db->execute("DELETE FROM saas_invoice_positions WHERE invoice_id = ?", [$invoiceId]);
    }

    public function addPosition(int $invoiceId, array $pos, int $sortOrder): void
    {
        $this->db->execute(
            "INSERT INTO saas_invoice_positions (invoice_id, description, quantity, unit_price, tax_rate, total, sort_order)
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

    public function updateTotals(int $id, float $net, float $tax, float $gross): void
    {
        $this->db->execute(
            "UPDATE saas_invoices SET total_net = ?, total_tax = ?, total_gross = ? WHERE id = ?",
            [$net, $tax, $gross, $id]
        );
    }

    // ── Liste & Suche ─────────────────────────────────────────────────────────

    public function getPaginated(int $page, int $perPage, string $status = '', string $search = ''): array
    {
        $conditions = [];
        $params     = [];

        if ($status !== '') {
            $conditions[] = "i.status = ?";
            $params[]     = $status;
        }

        if ($search !== '') {
            $conditions[] = "(i.invoice_number LIKE ? OR t.practice_name LIKE ? OR t.owner_name LIKE ? OR t.email LIKE ?)";
            $params = array_merge($params, ["%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%"]);
        }

        $where  = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $offset = ($page - 1) * $perPage;

        $total = (int)$this->db->fetchColumn(
            "SELECT COUNT(*) FROM saas_invoices i JOIN tenants t ON t.id = i.tenant_id {$where}",
            $params
        );

        $items = $this->db->fetchAll(
            "SELECT i.*,
                    COALESCE(NULLIF(t.practice_name,''), t.owner_name) AS tenant_display,
                    t.email AS tenant_email,
                    CASE WHEN i.status IN ('open','overdue') AND i.due_date < CURDATE()
                         THEN DATEDIFF(CURDATE(), i.due_date) ELSE NULL END AS days_overdue
             FROM saas_invoices i
             JOIN tenants t ON t.id = i.tenant_id
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
            'last_page' => max(1, (int)ceil($total / $perPage)),
            'has_next'  => ($page * $perPage) < $total,
            'has_prev'  => $page > 1,
        ];
    }

    // ── Statistiken ───────────────────────────────────────────────────────────

    public function getStats(): array
    {
        $month = date('Y-m-01');
        $year  = date('Y-01-01');

        return [
            'revenue_month'  => (float)$this->db->fetchColumn(
                "SELECT COALESCE(SUM(total_gross),0) FROM saas_invoices WHERE status='paid' AND issue_date >= ?", [$month]
            ),
            'revenue_year'   => (float)$this->db->fetchColumn(
                "SELECT COALESCE(SUM(total_gross),0) FROM saas_invoices WHERE status='paid' AND issue_date >= ?", [$year]
            ),
            'revenue_total'  => (float)$this->db->fetchColumn(
                "SELECT COALESCE(SUM(total_gross),0) FROM saas_invoices WHERE status='paid'"
            ),
            'open_count'     => (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM saas_invoices WHERE status='open'"
            ),
            'open_amount'    => (float)$this->db->fetchColumn(
                "SELECT COALESCE(SUM(total_gross),0) FROM saas_invoices WHERE status='open'"
            ),
            'overdue_count'  => (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM saas_invoices WHERE status='overdue' OR (status='open' AND due_date < CURDATE())"
            ),
            'overdue_amount' => (float)$this->db->fetchColumn(
                "SELECT COALESCE(SUM(total_gross),0) FROM saas_invoices WHERE status='overdue' OR (status='open' AND due_date < CURDATE())"
            ),
            'draft_count'    => (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM saas_invoices WHERE status='draft'"
            ),
            'paid_count'     => (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM saas_invoices WHERE status='paid'"
            ),
        ];
    }

    public function getMonthlyChartData(): array
    {
        $months = [];
        for ($i = 11; $i >= 0; $i--) {
            $months[] = date('Y-m', strtotime("-{$i} months"));
        }

        $rows = $this->db->fetchAll(
            "SELECT DATE_FORMAT(issue_date,'%Y-%m') AS month,
                    COALESCE(SUM(CASE WHEN status='paid' THEN total_gross ELSE 0 END),0) AS paid,
                    COALESCE(SUM(CASE WHEN status IN ('open','overdue') THEN total_gross ELSE 0 END),0) AS open
             FROM saas_invoices
             WHERE issue_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
             GROUP BY month ORDER BY month ASC"
        );

        $indexed = [];
        foreach ($rows as $r) { $indexed[$r['month']] = $r; }

        $labels = $paid = $open = [];
        foreach ($months as $m) {
            $dt       = \DateTime::createFromFormat('Y-m', $m);
            $labels[] = $dt ? $dt->format('M y') : $m;
            $paid[]   = round((float)($indexed[$m]['paid'] ?? 0), 2);
            $open[]   = round((float)($indexed[$m]['open'] ?? 0), 2);
        }

        return ['labels' => $labels, 'paid' => $paid, 'open' => $open];
    }

    // ── Hilfsmethoden ─────────────────────────────────────────────────────────

    public function getNextInvoiceNumber(string $prefix = 'TP', int $startNumber = 1000): string
    {
        $last = $this->db->fetchColumn(
            "SELECT invoice_number FROM saas_invoices ORDER BY id DESC LIMIT 1"
        );

        if (!$last) {
            return $prefix . '-' . str_pad((string)$startNumber, 4, '0', STR_PAD_LEFT);
        }

        preg_match('/(\d+)$/', (string)$last, $m);
        $next = isset($m[1]) ? (int)$m[1] + 1 : $startNumber;
        return $prefix . '-' . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
    }

    public function markEmailSent(int $id): void
    {
        $this->db->execute("UPDATE saas_invoices SET email_sent_at = NOW() WHERE id = ?", [$id]);
    }

    public function markOverdueAutomatic(): void
    {
        $this->db->execute(
            "UPDATE saas_invoices SET status='overdue'
             WHERE status='open' AND due_date IS NOT NULL AND due_date < CURDATE()"
        );
    }

    public function updateStatus(int $id, string $status, ?string $paidAt = null): void
    {
        if ($paidAt !== null) {
            $this->db->execute(
                "UPDATE saas_invoices SET status=?, paid_at=?, updated_at=NOW() WHERE id=?",
                [$status, $paidAt, $id]
            );
        } else {
            $this->db->execute(
                "UPDATE saas_invoices SET status=?, paid_at=NULL, updated_at=NOW() WHERE id=?",
                [$status, $id]
            );
        }
    }

    // ── Steuerexport ─────────────────────────────────────────────────────────

    public function getForTaxExport(string $dateFrom, string $dateTo): array
    {
        return $this->db->fetchAll(
            "SELECT i.*,
                    COALESCE(NULLIF(t.practice_name,''), t.owner_name) AS tenant_display,
                    t.email AS tenant_email, t.address, t.city, t.zip, t.country
             FROM saas_invoices i
             JOIN tenants t ON t.id = i.tenant_id
             WHERE i.status IN ('paid','open','overdue')
               AND i.issue_date BETWEEN ? AND ?
             ORDER BY i.issue_date ASC, i.invoice_number ASC",
            [$dateFrom, $dateTo]
        );
    }

    public function getTaxSummary(string $dateFrom, string $dateTo): array
    {
        $rows = $this->db->fetchAll(
            "SELECT p.tax_rate,
                    SUM(p.total) AS total_gross,
                    SUM(p.total / (1 + p.tax_rate/100)) AS total_net,
                    SUM(p.total - p.total / (1 + p.tax_rate/100)) AS total_tax,
                    COUNT(DISTINCT i.id) AS invoice_count
             FROM saas_invoice_positions p
             JOIN saas_invoices i ON i.id = p.invoice_id
             WHERE i.status = 'paid'
               AND i.issue_date BETWEEN ? AND ?
             GROUP BY p.tax_rate
             ORDER BY p.tax_rate DESC",
            [$dateFrom, $dateTo]
        );
        return $rows ?: [];
    }
}
