<?php

declare(strict_types=1);

namespace Plugins\TaxExportPro;

use App\Core\Database;

/**
 * GoBD-konformes Audit-Log für Rechnungen.
 *
 * Jede Mutation (Erstellen, Ändern, Löschen, Statuswechsel, PDF-Download,
 * E-Mail-Versand) wird unveränderlich protokolliert.
 * Die Tabelle invoice_audit_log darf NIEMALS UPDATE/DELETE erhalten.
 */
class GobdAuditService
{
    public function __construct(private readonly Database $db) {}

    public function log(
        int     $invoiceId,
        string  $invoiceNumber,
        string  $action,
        ?array  $oldValues  = null,
        ?array  $newValues  = null,
        ?int    $userId     = null
    ): void {
        try {
            $this->db->execute(
                "INSERT INTO invoice_audit_log
                    (invoice_id, invoice_number, action, old_values, new_values, user_id, ip_address, user_agent, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $invoiceId,
                    $invoiceNumber,
                    $action,
                    $oldValues  !== null ? json_encode($oldValues,  JSON_UNESCAPED_UNICODE) : null,
                    $newValues  !== null ? json_encode($newValues,  JSON_UNESCAPED_UNICODE) : null,
                    $userId,
                    $_SERVER['REMOTE_ADDR']     ?? '',
                    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                ]
            );
        } catch (\Throwable) {
            /* Table may not exist yet during migration — fail silently */
        }
    }

    /**
     * Prüft ob eine Rechnung finalisiert/unveränderlich ist.
     */
    public function isFinalized(int $invoiceId): bool
    {
        try {
            $val = $this->db->fetchColumn(
                "SELECT finalized_at FROM invoices WHERE id = ? LIMIT 1",
                [$invoiceId]
            );
            return !empty($val);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Finalisiert eine Rechnung (setzt finalized_at) und berechnet GoBD-Hash.
     * Nach Finalisierung kann die Rechnung nicht mehr editiert oder gelöscht werden.
     */
    public function finalize(int $invoiceId, string $invoiceNumber, ?int $userId = null): void
    {
        try {
            /* Build a canonical hash of the invoice + positions */
            $hash = $this->computeHash($invoiceId);

            $this->db->execute(
                "UPDATE invoices SET finalized_at = NOW(), gobd_hash = ? WHERE id = ? AND finalized_at IS NULL",
                [$hash, $invoiceId]
            );

            $this->log($invoiceId, $invoiceNumber, 'finalized', null, ['hash' => $hash], $userId);
        } catch (\Throwable) {}
    }

    /**
     * Stornierung: legt eine Storno-Rechnung mit negativen Beträgen an
     * und markiert die Originalrechnung als storniert.
     * Die Originalrechnung bleibt unveränderlich erhalten (GoBD §146 AO).
     */
    public function cancel(
        int    $originalId,
        string $originalNumber,
        ?int   $userId = null
    ): ?int {
        try {
            $original   = $this->db->fetch("SELECT * FROM invoices WHERE id = ?", [$originalId]);
            $positions  = $this->db->fetchAll(
                "SELECT * FROM invoice_positions WHERE invoice_id = ? ORDER BY sort_order ASC",
                [$originalId]
            );

            if (!$original) return null;

            /* Mark original as cancelled */
            $this->db->execute(
                "UPDATE invoices SET cancelled_at = NOW(), status = 'cancelled' WHERE id = ?",
                [$originalId]
            );
            $this->log($originalId, $originalNumber, 'cancelled', null, null, $userId);

            /* Create reversal invoice with negative amounts */
            $cancelNumber = 'STORNO-' . $originalNumber;
            $this->db->execute(
                "INSERT INTO invoices
                    (invoice_number, owner_id, patient_id, status, issue_date,
                     due_date, notes, payment_terms, payment_method,
                     total_net, total_tax, total_gross,
                     cancels_invoice_id, finalized_at, created_at, updated_at)
                 VALUES (?, ?, ?, 'cancelled', CURDATE(),
                     NULL, ?, '', ?,
                     ?, ?, ?,
                     ?, NOW(), NOW(), NOW())",
                [
                    $cancelNumber,
                    $original['owner_id'],
                    $original['patient_id'],
                    'Stornorechnung zu ' . $originalNumber,
                    $original['payment_method'] ?? 'rechnung',
                    -(float)$original['total_net'],
                    -(float)$original['total_tax'],
                    -(float)$original['total_gross'],
                    $originalId,
                ]
            );

            $cancelId = (int)$this->db->fetchColumn("SELECT LAST_INSERT_ID()");

            /* Copy positions with negated amounts */
            foreach ($positions as $i => $pos) {
                $this->db->execute(
                    "INSERT INTO invoice_positions
                        (invoice_id, description, quantity, unit_price, tax_rate, total, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [
                        $cancelId,
                        'STORNO: ' . $pos['description'],
                        $pos['quantity'],
                        -(float)$pos['unit_price'],
                        $pos['tax_rate'],
                        -(float)$pos['total'],
                        $i + 1,
                    ]
                );
            }

            $this->log($cancelId, $cancelNumber, 'created', null,
                ['cancels_invoice_id' => $originalId], $userId);

            return $cancelId;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Returns the full audit trail for one invoice.
     */
    public function getLog(int $invoiceId): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT * FROM invoice_audit_log WHERE invoice_id = ? ORDER BY created_at ASC",
                [$invoiceId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Returns recent audit log entries (all invoices).
     */
    public function getRecentLog(int $limit = 50): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT l.*, i.invoice_number
                 FROM invoice_audit_log l
                 LEFT JOIN invoices i ON l.invoice_id = i.id
                 ORDER BY l.created_at DESC LIMIT ?",
                [$limit]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    // ── Private ──────────────────────────────────────────────────────────────

    private function computeHash(int $invoiceId): string
    {
        try {
            $invoice   = $this->db->fetch("SELECT * FROM invoices WHERE id = ?", [$invoiceId]);
            $positions = $this->db->fetchAll(
                "SELECT * FROM invoice_positions WHERE invoice_id = ? ORDER BY sort_order ASC",
                [$invoiceId]
            );
            $canonical = json_encode(['invoice' => $invoice, 'positions' => $positions], JSON_UNESCAPED_UNICODE);
            return hash('sha256', $canonical);
        } catch (\Throwable) {
            return '';
        }
    }
}
