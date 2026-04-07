<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class ReminderDunningRepository
{
    public function __construct(private readonly Database $db) {}

    private function t(string $table): string
    {
        return $this->db->prefix($table);
    }

    /* ══════════════════════════════════════════════════════════
       REMINDERS
    ══════════════════════════════════════════════════════════ */

    public function getRemindersForInvoice(int $invoiceId): array
    {
        return $this->db->fetchAll(
            'SELECT r.*, u.name AS created_by_name
             FROM `' . $this->t('invoice_reminders') . '` r
             LEFT JOIN `' . $this->t('users') . '` u ON u.id = r.created_by
             WHERE r.invoice_id = ?
             ORDER BY r.created_at DESC',
            [$invoiceId]
        );
    }

    public function findReminderById(int $id): ?array
    {
        $row = $this->db->fetch(
            'SELECT r.*, i.invoice_number, i.total_gross, i.issue_date, i.due_date AS invoice_due_date,
                    o.first_name, o.last_name, o.email AS owner_email, o.street AS owner_street,
                    o.zip AS owner_zip, o.city AS owner_city,
                    p.name AS patient_name, p.species AS patient_species
             FROM `' . $this->t('invoice_reminders') . '` r
             JOIN `' . $this->t('invoices') . '` i ON i.id = r.invoice_id
             LEFT JOIN `' . $this->t('owners') . '` o ON o.id = i.owner_id
             LEFT JOIN `' . $this->t('patients') . '` p ON p.id = i.patient_id
             WHERE r.id = ? LIMIT 1',
            [$id]
        );
        return $row ?: null;
    }

    public function createReminder(array $data): int
    {
        $this->db->execute(
            'INSERT INTO `' . $this->t('invoice_reminders') . '` (invoice_id, due_date, fee, notes, created_by)
             VALUES (?, ?, ?, ?, ?)',
            [
                (int)$data['invoice_id'],
                $data['due_date'] ?? null,
                (float)($data['fee'] ?? 0),
                $data['notes'] ?? null,
                isset($data['created_by']) ? (int)$data['created_by'] : null,
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function markReminderSent(int $id, string $sentTo): void
    {
        $this->db->execute(
            'UPDATE `' . $this->t('invoice_reminders') . '` SET sent_at = NOW(), sent_to = ?, pdf_generated = 1 WHERE id = ?',
            [$sentTo, $id]
        );
    }

    public function markReminderPdfGenerated(int $id): void
    {
        $this->db->execute('UPDATE `' . $this->t('invoice_reminders') . '` SET pdf_generated = 1 WHERE id = ?', [$id]);
    }

    public function deleteReminder(int $id): void
    {
        $this->db->execute('DELETE FROM `' . $this->t('invoice_reminders') . '` WHERE id = ?', [$id]);
    }

    public function getAllReminders(string $search = '', string $status = ''): array
    {
        $ir = $this->t('invoice_reminders'); $inv = $this->t('invoices'); $ip = $this->t('invoice_positions');
        $own = $this->t('owners'); $pat = $this->t('patients');
        $sql = 'SELECT r.*,
                       i.invoice_number,
                       i.due_date AS invoice_due_date,
                       i.issue_date AS invoice_issue_date,
                       COALESCE(NULLIF(i.total_gross, 0), (SELECT SUM(ip.total) FROM `' . $ip . '` ip WHERE ip.invoice_id = i.id)) AS total_gross,
                       i.status AS invoice_status,
                       DATEDIFF(CURDATE(), COALESCE(i.due_date, i.issue_date)) AS days_overdue,
                       o.first_name, o.last_name, o.email AS owner_email,
                       p.name AS patient_name
                FROM `' . $ir . '` r
                JOIN `' . $inv . '` i ON i.id = r.invoice_id
                LEFT JOIN `' . $own . '` o ON o.id = i.owner_id
                LEFT JOIN `' . $pat . '` p ON p.id = i.patient_id
                WHERE 1=1';
        $params = [];

        if ($search) {
            $sql .= ' AND (i.invoice_number LIKE ? OR o.first_name LIKE ? OR o.last_name LIKE ?)';
            $like = "%{$search}%";
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        if ($status === 'sent') {
            $sql .= ' AND r.sent_at IS NOT NULL';
        } elseif ($status === 'unsent') {
            $sql .= ' AND r.sent_at IS NULL';
        }

        $sql .= ' ORDER BY r.created_at DESC';
        return $this->db->fetchAll($sql, $params);
    }

    /* ══════════════════════════════════════════════════════════
       DUNNINGS
    ══════════════════════════════════════════════════════════ */

    public function getDunningsForInvoice(int $invoiceId): array
    {
        return $this->db->fetchAll(
            'SELECT d.*, u.name AS created_by_name
             FROM `' . $this->t('invoice_dunnings') . '` d
             LEFT JOIN `' . $this->t('users') . '` u ON u.id = d.created_by
             WHERE d.invoice_id = ?
             ORDER BY d.level ASC, d.created_at DESC',
            [$invoiceId]
        );
    }

    public function findDunningById(int $id): ?array
    {
        $row = $this->db->fetch(
            'SELECT d.*, i.invoice_number, i.total_gross, i.issue_date, i.due_date AS invoice_due_date,
                    o.first_name, o.last_name, o.email AS owner_email, o.street AS owner_street,
                    o.zip AS owner_zip, o.city AS owner_city,
                    p.name AS patient_name, p.species AS patient_species
             FROM `' . $this->t('invoice_dunnings') . '` d
             JOIN `' . $this->t('invoices') . '` i ON i.id = d.invoice_id
             LEFT JOIN `' . $this->t('owners') . '` o ON o.id = i.owner_id
             LEFT JOIN `' . $this->t('patients') . '` p ON p.id = i.patient_id
             WHERE d.id = ? LIMIT 1',
            [$id]
        );
        return $row ?: null;
    }

    public function getNextDunningLevel(int $invoiceId): int
    {
        $max = $this->db->fetch(
            'SELECT MAX(level) AS max_level FROM `' . $this->t('invoice_dunnings') . '` WHERE invoice_id = ?',
            [$invoiceId]
        );
        return min(3, ((int)($max['max_level'] ?? 0)) + 1);
    }

    public function createDunning(array $data): int
    {
        $this->db->execute(
            'INSERT INTO `' . $this->t('invoice_dunnings') . '` (invoice_id, level, due_date, fee, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                (int)$data['invoice_id'],
                (int)($data['level'] ?? 1),
                $data['due_date'] ?? null,
                (float)($data['fee'] ?? 5.00),
                $data['notes'] ?? null,
                isset($data['created_by']) ? (int)$data['created_by'] : null,
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function markDunningSent(int $id, string $sentTo): void
    {
        $this->db->execute(
            'UPDATE `' . $this->t('invoice_dunnings') . '` SET sent_at = NOW(), sent_to = ?, pdf_generated = 1 WHERE id = ?',
            [$sentTo, $id]
        );
    }

    public function markDunningPdfGenerated(int $id): void
    {
        $this->db->execute('UPDATE `' . $this->t('invoice_dunnings') . '` SET pdf_generated = 1 WHERE id = ?', [$id]);
    }

    public function deleteDunning(int $id): void
    {
        $this->db->execute('DELETE FROM `' . $this->t('invoice_dunnings') . '` WHERE id = ?', [$id]);
    }

    public function getAllDunnings(string $search = '', string $status = ''): array
    {
        $id2 = $this->t('invoice_dunnings'); $inv2 = $this->t('invoices'); $ip2 = $this->t('invoice_positions');
        $own2 = $this->t('owners'); $pat2 = $this->t('patients');
        $sql = 'SELECT d.*,
                       i.invoice_number,
                       i.due_date AS invoice_due_date,
                       i.issue_date AS invoice_issue_date,
                       COALESCE(NULLIF(i.total_gross, 0), (SELECT SUM(ip.total) FROM `' . $ip2 . '` ip WHERE ip.invoice_id = i.id)) AS total_gross,
                       i.status AS invoice_status,
                       DATEDIFF(CURDATE(), COALESCE(i.due_date, i.issue_date)) AS days_overdue,
                       o.first_name, o.last_name, o.email AS owner_email,
                       p.name AS patient_name
                FROM `' . $id2 . '` d
                JOIN `' . $inv2 . '` i ON i.id = d.invoice_id
                LEFT JOIN `' . $own2 . '` o ON o.id = i.owner_id
                LEFT JOIN `' . $pat2 . '` p ON p.id = i.patient_id
                WHERE 1=1';
        $params = [];

        if ($search) {
            $sql .= ' AND (i.invoice_number LIKE ? OR o.first_name LIKE ? OR o.last_name LIKE ?)';
            $like = "%{$search}%";
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        if ($status === 'sent') {
            $sql .= ' AND d.sent_at IS NOT NULL';
        } elseif ($status === 'unsent') {
            $sql .= ' AND d.sent_at IS NULL';
        }

        $sql .= ' ORDER BY d.created_at DESC';
        return $this->db->fetchAll($sql, $params);
    }

    public function countRemindersForInvoice(int $invoiceId): int
    {
        return (int)$this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->t('invoice_reminders') . '` WHERE invoice_id = ?',
            [$invoiceId]
        );
    }

    public function countDunningsForInvoice(int $invoiceId): int
    {
        return (int)$this->db->fetchColumn(
            'SELECT COUNT(*) FROM `' . $this->t('invoice_dunnings') . '` WHERE invoice_id = ?',
            [$invoiceId]
        );
    }

    public function getOverdueAlertInvoices(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT i.id, i.invoice_number, i.issue_date, i.due_date, i.status,
                    COALESCE(NULLIF(i.total_gross, 0), (SELECT SUM(ip.total) FROM `{$this->t('invoice_positions')}` ip WHERE ip.invoice_id = i.id)) AS total_gross,
                    CONCAT(o.first_name, ' ', o.last_name) AS owner_name,
                    o.email AS owner_email,
                    p.name AS patient_name,
                    DATEDIFF(CURDATE(), COALESCE(i.due_date, i.issue_date)) AS overdue_days,
                    (SELECT COUNT(*) FROM `{$this->t('invoice_reminders')}` r WHERE r.invoice_id = i.id) AS reminder_count,
                    (SELECT COUNT(*) FROM `{$this->t('invoice_dunnings')}` d WHERE d.invoice_id = i.id) AS dunning_count
             FROM `{$this->t('invoices')}` i
             LEFT JOIN `{$this->t('owners')}` o ON o.id = i.owner_id
             LEFT JOIN `{$this->t('patients')}` p ON p.id = i.patient_id
             WHERE i.status IN ('open', 'overdue')
               AND i.issue_date <= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
             ORDER BY overdue_days DESC
             LIMIT 50"
        );
        return $rows ?: [];
    }

    /* ══════════════════════════════════════════════════════════
       HELPERS
    ══════════════════════════════════════════════════════════ */

    public function ensureTables(): void
    {
        try {
            $this->db->execute(
                'CREATE TABLE IF NOT EXISTS `' . $this->t('invoice_reminders') . '` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `invoice_id` INT UNSIGNED NOT NULL,
                    `sent_at` DATETIME NULL,
                    `sent_to` VARCHAR(255) NULL,
                    `due_date` DATE NULL,
                    `fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    `notes` TEXT NULL,
                    `pdf_generated` TINYINT(1) NOT NULL DEFAULT 0,
                    `created_by` INT UNSIGNED NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`), INDEX `idx_ir_invoice` (`invoice_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
                []
            );
            $this->db->execute(
                'CREATE TABLE IF NOT EXISTS `' . $this->t('invoice_dunnings') . '` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `invoice_id` INT UNSIGNED NOT NULL,
                    `level` TINYINT NOT NULL DEFAULT 1,
                    `sent_at` DATETIME NULL,
                    `sent_to` VARCHAR(255) NULL,
                    `due_date` DATE NULL,
                    `fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    `notes` TEXT NULL,
                    `pdf_generated` TINYINT(1) NOT NULL DEFAULT 0,
                    `created_by` INT UNSIGNED NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`), INDEX `idx_id_invoice` (`invoice_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
                []
            );
        } catch (\Throwable) {}
    }
}
