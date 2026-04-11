<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\InvoiceRepository;
use App\Repositories\SettingsRepository;

/**
 * GoBD-konformer Storno-Service
 *
 * INVARIANTEN (dürfen NIE verletzt werden):
 *   - Original bleibt unverändert erhalten (kein Hard-Delete, kein Überschreiben)
 *   - Storno nur über Gegenbeleg (invoice_type='cancellation')
 *   - Stornogrund ist Pflicht
 *   - Bereits stornierte Rechnungen dürfen nicht erneut storniert werden
 *   - Storno-Belege dürfen niemals storniert werden
 *   - Alle Schritte laufen in einer DB-Transaktion mit Rollback
 */
class InvoiceCancellationService
{
    /** Statuses die storniert werden dürfen */
    private const CANCELLABLE_STATUSES = ['open', 'paid', 'overdue'];

    public function __construct(
        private readonly Database           $db,
        private readonly InvoiceRepository  $invoiceRepo,
        private readonly SettingsRepository $settingsRepo
    ) {}

    /**
     * Storniert eine Rechnung GoBD-konform.
     *
     * @param  int    $invoiceId  ID der zu stornierenden Rechnung
     * @param  string $reason     Pflicht-Stornogrund
     * @param  int    $userId     ID des ausführenden Benutzers
     * @return array  ['ok' => true, 'cancellation_id' => int, 'cancellation_number' => string]
     * @throws \RuntimeException bei Validierungsfehler oder DB-Fehler
     */
    public function cancel(int $invoiceId, string $reason, int $userId): array
    {
        // ── 1. Validierung (vor Transaktion) ─────────────────────────────────

        $reason = trim($reason);
        if ($reason === '') {
            throw new \RuntimeException('Stornogrund darf nicht leer sein.');
        }

        $original = $this->invoiceRepo->findById($invoiceId);
        if (!$original) {
            throw new \RuntimeException('Rechnung nicht gefunden.');
        }

        if (($original['invoice_type'] ?? 'normal') === 'cancellation') {
            throw new \RuntimeException('Ein Storno-Beleg kann nicht erneut storniert werden.');
        }

        if ($original['status'] === 'cancelled') {
            throw new \RuntimeException('Rechnung wurde bereits storniert.');
        }

        if (!empty($original['cancellation_invoice_id'])) {
            throw new \RuntimeException('Rechnung hat bereits einen Storno-Beleg (ID ' . $original['cancellation_invoice_id'] . ').');
        }

        if (!in_array($original['status'], self::CANCELLABLE_STATUSES, true)) {
            throw new \RuntimeException(
                'Rechnungen im Status "' . $original['status'] . '" können nicht storniert werden. '
                . 'Nur Rechnungen mit Status Offen, Bezahlt oder Überfällig sind stornierbar.'
            );
        }

        // ── 2. DB-Transaktion ─────────────────────────────────────────────────
        $cancellationId     = 0;
        $cancellationNumber = '';

        try {
            $this->db->beginTransaction();

            // Race-Condition-Schutz: Rechnung exklusiv sperren
            $locked = $this->db->fetch(
                "SELECT id, status, cancellation_invoice_id, invoice_type FROM `{$this->t('invoices')}` WHERE id = ? FOR UPDATE",
                [$invoiceId]
            );

            // Nach dem Lock nochmals prüfen (könnte sich in Parallelrequest geändert haben)
            if (!$locked) {
                throw new \RuntimeException('Rechnung nicht gefunden (Datenbankfehler).');
            }
            if ($locked['status'] === 'cancelled' || !empty($locked['cancellation_invoice_id'])) {
                throw new \RuntimeException('Rechnung wurde in der Zwischenzeit bereits storniert.');
            }
            if (($locked['invoice_type'] ?? 'normal') === 'cancellation') {
                throw new \RuntimeException('Storno-Belege können nicht storniert werden.');
            }

            // ── 3. Stornorechnungsnummer erzeugen ─────────────────────────────
            $cancellationNumber = $this->generateCancellationNumber($original['invoice_number']);

            // ── 4. Positionen des Originals laden ─────────────────────────────
            $originalPositions = $this->invoiceRepo->getPositions($invoiceId);

            // ── 5. Stornorechnung anlegen ─────────────────────────────────────
            $now = date('Y-m-d H:i:s');
            $cancellationData = [
                'invoice_number'      => $cancellationNumber,
                'invoice_type'        => 'cancellation',
                'owner_id'            => $original['owner_id'],
                'patient_id'          => $original['patient_id'] ?? null,
                'status'              => 'cancellation',
                'issue_date'          => date('Y-m-d'),
                'due_date'            => null,
                'total_net'           => -abs((float)$original['total_net']),
                'total_tax'           => -abs((float)$original['total_tax']),
                'total_gross'         => -abs((float)$original['total_gross']),
                'notes'               => 'Stornorechnung zu ' . $original['invoice_number'],
                'payment_terms'       => '',
                'payment_method'      => $original['payment_method'] ?? 'rechnung',
                'diagnosis'           => null,
                'cancels_invoice_id'  => $invoiceId,
                'cancellation_reason' => $reason,
                'cancelled_at'        => $now,
                'cancelled_by'        => $userId,
                'created_at'          => $now,
                'updated_at'          => $now,
            ];

            // Nur vorhandene Spalten verwenden
            $columns     = implode(', ', array_map(fn($k) => "`$k`", array_keys($cancellationData)));
            $placeholders = implode(', ', array_fill(0, count($cancellationData), '?'));
            $this->db->execute(
                "INSERT INTO `{$this->t('invoices')}` ($columns) VALUES ($placeholders)",
                array_values($cancellationData)
            );
            $cancellationId = (int)$this->db->lastInsertId();

            // ── 6. Positionen negativ gespiegelt übertragen ────────────────────
            foreach ($originalPositions as $i => $pos) {
                $qty       = (float)$pos['quantity'];
                $unitPrice = -(float)$pos['unit_price'];  // negieren
                $total     = -(float)$pos['total'];        // negieren

                $this->db->execute(
                    "INSERT INTO `{$this->t('invoice_positions')}`
                        (invoice_id, description, quantity, unit_price, tax_rate, total, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?, ?)",
                    [
                        $cancellationId,
                        $pos['description'],
                        $qty,
                        $unitPrice,
                        $pos['tax_rate'],
                        $total,
                        $i + 1,
                    ]
                );
            }

            // ── 7. Original als storniert markieren ───────────────────────────
            $this->db->execute(
                "UPDATE `{$this->t('invoices')}`
                 SET status = 'cancelled',
                     cancellation_invoice_id = ?,
                     cancelled_at = ?,
                     cancelled_by = ?,
                     cancellation_reason = ?,
                     updated_at = ?
                 WHERE id = ?",
                [$cancellationId, $now, $userId, $reason, $now, $invoiceId]
            );

            // ── 8. Audit-Logs ──────────────────────────────────────────────────
            $this->writeAuditLog($invoiceId, $original['invoice_number'], 'cancelled', $userId, [
                'reason'                  => $reason,
                'cancellation_invoice_id' => $cancellationId,
                'cancellation_number'     => $cancellationNumber,
            ]);
            $this->writeAuditLog($cancellationId, $cancellationNumber, 'cancellation_created', $userId, [
                'cancels_invoice_id'     => $invoiceId,
                'cancels_invoice_number' => $original['invoice_number'],
                'reason'                 => $reason,
                'total_gross'            => -abs((float)$original['total_gross']),
            ]);

            // ── 9. Commit ─────────────────────────────────────────────────────
            $this->db->commit();

        } catch (\Throwable $e) {
            try { $this->db->rollback(); } catch (\Throwable) {}
            throw new \RuntimeException('Stornierung fehlgeschlagen: ' . $e->getMessage(), 0, $e);
        }

        return [
            'ok'                  => true,
            'cancellation_id'     => $cancellationId,
            'cancellation_number' => $cancellationNumber,
        ];
    }

    /**
     * Lädt eine Rechnung mit ihrer eventuellen Gegenbuchung.
     */
    public function loadWithRelated(int $invoiceId): array
    {
        $invoice = $this->invoiceRepo->findById($invoiceId);
        if (!$invoice) {
            return [];
        }

        $related = null;

        if (!empty($invoice['cancellation_invoice_id'])) {
            // Ist Original → lade Stornobeleg
            $related = $this->invoiceRepo->findById((int)$invoice['cancellation_invoice_id']);
        } elseif (!empty($invoice['cancels_invoice_id'])) {
            // Ist Stornobeleg → lade Original
            $related = $this->invoiceRepo->findById((int)$invoice['cancels_invoice_id']);
        }

        return [
            'invoice' => $invoice,
            'related' => $related,
        ];
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function t(string $table): string
    {
        return $this->db->prefix($table);
    }

    private function generateCancellationNumber(string $originalNumber): string
    {
        // Format: STORNO-<OriginalNr>
        // Wenn die Nummer bereits mit STORNO- beginnt, weiteres Präfix anhängen
        if (str_starts_with($originalNumber, 'STORNO-')) {
            return 'STORNO-' . $originalNumber;
        }

        $base   = 'STORNO-' . $originalNumber;
        $exists = $this->db->fetchColumn(
            "SELECT COUNT(*) FROM `{$this->t('invoices')}` WHERE invoice_number = ?",
            [$base]
        );

        if ((int)$exists === 0) {
            return $base;
        }

        // Suffix bei Kollision (sehr selten)
        $suffix = 1;
        do {
            $candidate = $base . '-' . $suffix;
            $exists = (int)$this->db->fetchColumn(
                "SELECT COUNT(*) FROM `{$this->t('invoices')}` WHERE invoice_number = ?",
                [$candidate]
            );
            $suffix++;
        } while ($exists > 0);

        return $candidate;
    }

    private function writeAuditLog(int $invoiceId, string $invoiceNumber, string $action, int $userId, array $meta): void
    {
        try {
            $this->db->execute(
                "INSERT INTO `{$this->t('invoice_audit_log')}`
                    (invoice_id, invoice_number, action, old_values, new_values, user_id, ip_address, user_agent, created_at)
                 VALUES (?, ?, ?, NULL, ?, ?, ?, ?, NOW())",
                [
                    $invoiceId,
                    $invoiceNumber,
                    $action,
                    json_encode($meta, JSON_UNESCAPED_UNICODE),
                    $userId,
                    $_SERVER['REMOTE_ADDR']   ?? '',
                    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                ]
            );
        } catch (\Throwable) {
            // Audit-Log darf den Haupt-Workflow nicht blockieren
        }
    }
}
