<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class ReminderDunningRepository
{
    public function __construct(private readonly Database $db) {}

    /* ══════════════════════════════════════════════════════════
       REMINDERS
    ══════════════════════════════════════════════════════════ */

    public function getRemindersForInvoice(int $invoiceId): array
    {
        return $this->db->fetchAll(
            'SELECT r.*, u.name AS created_by_name
             FROM invoice_reminders r
             LEFT JOIN users u ON u.id = r.created_by
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
             FROM invoice_reminders r
             JOIN invoices i ON i.id = r.invoice_id
             LEFT JOIN owners o ON o.id = i.owner_id
             LEFT JOIN patients p ON p.id = i.patient_id
             WHERE r.id = ? LIMIT 1',
            [$id]
        );
        return $row ?: null;
    }

    public function createReminder(array $data): int
    {
        $this->db->execute(
            'INSERT INTO invoice_reminders (invoice_id, due_date, fee, notes, created_by)
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
            'UPDATE invoice_reminders SET sent_at = NOW(), sent_to = ?, pdf_generated = 1 WHERE id = ?',
            [$sentTo, $id]
        );
    }

    public function markReminderPdfGenerated(int $id): void
    {
        $this->db->execute('UPDATE invoice_reminders SET pdf_generated = 1 WHERE id = ?', [$id]);
    }

    public function deleteReminder(int $id): void
    {
        $this->db->execute('DELETE FROM invoice_reminders WHERE id = ?', [$id]);
    }

    public function getAllReminders(string $search = '', string $status = ''): array
    {
        $sql = 'SELECT r.*, i.invoice_number, i.total_gross, i.status AS invoice_status,
                       o.first_name, o.last_name, o.email AS owner_email,
                       p.name AS patient_name
                FROM invoice_reminders r
                JOIN invoices i ON i.id = r.invoice_id
                LEFT JOIN owners o ON o.id = i.owner_id
                LEFT JOIN patients p ON p.id = i.patient_id
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
             FROM invoice_dunnings d
             LEFT JOIN users u ON u.id = d.created_by
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
             FROM invoice_dunnings d
             JOIN invoices i ON i.id = d.invoice_id
             LEFT JOIN owners o ON o.id = i.owner_id
             LEFT JOIN patients p ON p.id = i.patient_id
             WHERE d.id = ? LIMIT 1',
            [$id]
        );
        return $row ?: null;
    }

    public function getNextDunningLevel(int $invoiceId): int
    {
        $max = $this->db->fetch(
            'SELECT MAX(level) AS max_level FROM invoice_dunnings WHERE invoice_id = ?',
            [$invoiceId]
        );
        return min(3, ((int)($max['max_level'] ?? 0)) + 1);
    }

    public function createDunning(array $data): int
    {
        $this->db->execute(
            'INSERT INTO invoice_dunnings (invoice_id, level, due_date, fee, notes, created_by)
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
            'UPDATE invoice_dunnings SET sent_at = NOW(), sent_to = ?, pdf_generated = 1 WHERE id = ?',
            [$sentTo, $id]
        );
    }

    public function markDunningPdfGenerated(int $id): void
    {
        $this->db->execute('UPDATE invoice_dunnings SET pdf_generated = 1 WHERE id = ?', [$id]);
    }

    public function deleteDunning(int $id): void
    {
        $this->db->execute('DELETE FROM invoice_dunnings WHERE id = ?', [$id]);
    }

    public function getAllDunnings(string $search = '', string $status = ''): array
    {
        $sql = 'SELECT d.*, i.invoice_number, i.total_gross, i.status AS invoice_status,
                       o.first_name, o.last_name, o.email AS owner_email,
                       p.name AS patient_name
                FROM invoice_dunnings d
                JOIN invoices i ON i.id = d.invoice_id
                LEFT JOIN owners o ON o.id = i.owner_id
                LEFT JOIN patients p ON p.id = i.patient_id
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

    /* ══════════════════════════════════════════════════════════
       HELPERS
    ══════════════════════════════════════════════════════════ */

    public function ensureTables(): void
    {
        try {
            $this->db->execute(
                'CREATE TABLE IF NOT EXISTS `invoice_reminders` (
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
                'CREATE TABLE IF NOT EXISTS `invoice_dunnings` (
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
