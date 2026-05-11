<?php

declare(strict_types=1);

namespace Saas\Services;

use Saas\Core\Database;
use Saas\Repositories\SaasInvoiceRepository;
use Saas\Repositories\TenantRepository;
use Saas\Repositories\NotificationRepository;

/**
 * SaasInvoiceBillingService
 *
 * Centralises all SaaS invoice business logic:
 *  - Auto-create invoices from Stripe webhook events (idempotent)
 *  - Create credit notes / storno
 *  - Dunning (Mahnwesen) escalation
 *  - DATEV CSV export
 *  - Self-healing: reconcile missing invoices from payments table
 */
class SaasInvoiceBillingService
{
    private array $settings = [];

    public function __construct(
        private readonly Database               $db,
        private readonly SaasInvoiceRepository  $invoiceRepo,
        private readonly TenantRepository       $tenantRepo,
        private readonly NotificationRepository $notifRepo,
    ) {
        try {
            $rows = $this->db->fetchAll("SELECT `key`, `value` FROM saas_settings");
            foreach ($rows as $r) {
                $this->settings[$r['key']] = $r['value'];
            }
        } catch (\Throwable) {}
    }

    // ── Stripe Auto-Invoice ───────────────────────────────────────────────

    /**
     * Create a SaaS invoice from a Stripe invoice.paid webhook event.
     * Idempotent: duplicate stripe_invoice_id is silently ignored.
     */
    public function createFromStripePayment(array $stripeInvoice): ?int
    {
        if (($this->settings['saas_invoice_auto_create'] ?? '1') !== '1') {
            return null;
        }

        $stripeInvoiceId = $stripeInvoice['id'] ?? null;
        if (!$stripeInvoiceId) return null;

        // Idempotency check via webhook event log
        if ($this->webhookEventProcessed($stripeInvoiceId . ':invoice.paid')) {
            return $this->getInvoiceIdByStripeId($stripeInvoiceId);
        }

        $customerId = $stripeInvoice['customer'] ?? null;
        $amountPaid = ($stripeInvoice['amount_paid'] ?? 0) / 100;
        $subId      = $stripeInvoice['subscription'] ?? null;
        $currency   = strtoupper($stripeInvoice['currency'] ?? 'EUR');

        $tenant = null;
        if ($customerId) {
            $tenant = $this->db->fetch("SELECT * FROM tenants WHERE stripe_customer_id = ?", [$customerId]);
        }
        if (!$tenant && !empty($stripeInvoice['metadata']['tenant_id'])) {
            $tenant = $this->db->fetch("SELECT * FROM tenants WHERE id = ?", [(int)$stripeInvoice['metadata']['tenant_id']]);
        }
        if (!$tenant) {
            error_log("[SaasInvoiceBillingService] Cannot resolve tenant for Stripe invoice {$stripeInvoiceId}");
            return null;
        }

        $vatRate     = (float)($this->settings['saas_invoice_vat_rate'] ?? 19);
        $grossAmount = round($amountPaid, 2);
        $netAmount   = round($grossAmount / (1 + $vatRate / 100), 2);
        $taxAmount   = round($grossAmount - $netAmount, 2);

        $periodStart = null;
        $periodEnd   = null;
        if (!empty($stripeInvoice['period_start'])) {
            $periodStart = date('Y-m-d', (int)$stripeInvoice['period_start']);
        }
        if (!empty($stripeInvoice['period_end'])) {
            $periodEnd = date('Y-m-d', (int)$stripeInvoice['period_end']);
        }

        $planLabel  = $stripeInvoice['lines']['data'][0]['description'] ?? ($tenant['plan'] ?? 'TheraPano Abo');
        $description = $planLabel;
        if ($periodStart && $periodEnd) {
            $description .= sprintf(
                ' (%s – %s)',
                date('d.m.Y', strtotime($periodStart)),
                date('d.m.Y', strtotime($periodEnd))
            );
        }

        $invoiceNumber = $this->invoiceRepo->getNextInvoiceNumber(
            $this->settings['saas_invoice_prefix'] ?? 'TP',
            (int)($this->settings['saas_invoice_start_number'] ?? 1000)
        );

        $data = [
            'tenant_id'               => (int)$tenant['id'],
            'invoice_number'          => $invoiceNumber,
            'status'                  => 'paid',
            'type'                    => 'invoice',
            'payment_method'          => 'stripe',
            'issue_date'              => date('Y-m-d'),
            'due_date'                => null,
            'total_net'               => $netAmount,
            'total_tax'               => $taxAmount,
            'total_gross'             => $grossAmount,
            'notes'                   => 'Automatisch erstellt via Stripe-Webhook',
            'payment_terms'           => $this->settings['saas_invoice_payment_terms'] ?? 'Zahlbar sofort ohne Abzug.',
            'paid_at'                 => date('Y-m-d H:i:s'),
            'stripe_invoice_id'       => $stripeInvoiceId,
            'stripe_subscription_id'  => $subId,
            'billing_period_start'    => $periodStart,
            'billing_period_end'      => $periodEnd,
            'invoice_type_label'      => 'Stripe-Zahlung',
            'created_at'              => date('Y-m-d H:i:s'),
            'updated_at'              => date('Y-m-d H:i:s'),
        ];

        try {
            $invoiceId = (int)$this->invoiceRepo->create($data);

            $this->invoiceRepo->addPosition($invoiceId, [
                'description' => $description,
                'quantity'    => 1.0,
                'unit_price'  => $netAmount,
                'tax_rate'    => $vatRate,
                'total'       => $grossAmount,
            ], 1);

            $this->markWebhookProcessed($stripeInvoiceId . ':invoice.paid', $invoiceId);
            $this->notifRepo->create('payment', 'SaaS-Rechnung erstellt', "Tenant #{$tenant['id']}: {$invoiceNumber} über {$grossAmount} €");

            return $invoiceId;
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), '1062')) {
                return $this->getInvoiceIdByStripeId($stripeInvoiceId);
            }
            error_log("[SaasInvoiceBillingService] create error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create invoice for subscription start (checkout.session.completed).
     * Handles trial: if in trial, issue draft/trial invoice.
     */
    public function createFromSubscriptionStart(array $tenant, string $planName, float $grossAmount, bool $isTrial = false, ?string $stripeSubId = null): int
    {
        $vatRate    = (float)($this->settings['saas_invoice_vat_rate'] ?? 19);
        $netAmount  = round($grossAmount / (1 + $vatRate / 100), 2);
        $taxAmount  = round($grossAmount - $netAmount, 2);
        $status     = $isTrial ? 'draft' : 'open';
        $label      = $isTrial ? 'Testphase' : 'Neues Abo';
        $desc       = $planName . ($isTrial ? ' (14 Tage Testphase – kostenfrei)' : ' – Erstabbuchung');

        $invoiceNumber = $this->invoiceRepo->getNextInvoiceNumber(
            $this->settings['saas_invoice_prefix'] ?? 'TP',
            (int)($this->settings['saas_invoice_start_number'] ?? 1000)
        );

        $data = [
            'tenant_id'              => (int)$tenant['id'],
            'invoice_number'         => $invoiceNumber,
            'status'                 => $status,
            'type'                   => 'invoice',
            'payment_method'         => 'stripe',
            'issue_date'             => date('Y-m-d'),
            'due_date'               => $isTrial ? null : date('Y-m-d', strtotime('+14 days')),
            'total_net'              => $isTrial ? 0.0 : $netAmount,
            'total_tax'              => $isTrial ? 0.0 : $taxAmount,
            'total_gross'            => $isTrial ? 0.0 : $grossAmount,
            'notes'                  => $isTrial ? 'Testphase: keine Zahlung erforderlich' : 'Abo gestartet',
            'payment_terms'          => $this->settings['saas_invoice_payment_terms'] ?? 'Zahlbar sofort ohne Abzug.',
            'stripe_subscription_id' => $stripeSubId,
            'invoice_type_label'     => $label,
            'created_at'             => date('Y-m-d H:i:s'),
            'updated_at'             => date('Y-m-d H:i:s'),
        ];

        $invoiceId = (int)$this->invoiceRepo->create($data);

        $this->invoiceRepo->addPosition($invoiceId, [
            'description' => $desc,
            'quantity'    => 1.0,
            'unit_price'  => $isTrial ? 0.0 : $netAmount,
            'tax_rate'    => $vatRate,
            'total'       => $isTrial ? 0.0 : $grossAmount,
        ], 1);

        return $invoiceId;
    }

    // ── Storno / Gutschrift ───────────────────────────────────────────────

    /**
     * Create a credit note (Gutschrift/Storno) for an existing invoice.
     * GoBD-compliant: original stays untouched, new negative credit note created.
     */
    public function createCreditNote(int $originalInvoiceId, string $reason, float $overrideAmount = 0.0): int
    {
        $original  = $this->invoiceRepo->findById($originalInvoiceId);
        if (!$original) {
            throw new \InvalidArgumentException("Invoice #{$originalInvoiceId} not found");
        }

        $grossAmount = $overrideAmount > 0.0 ? -abs($overrideAmount) : -(float)$original['total_gross'];
        $netAmount   = $overrideAmount > 0.0 ? -round($overrideAmount / 1.19, 2) : -(float)$original['total_net'];
        $taxAmount   = round($grossAmount - $netAmount, 2);

        $invoiceNumber = $this->invoiceRepo->getNextInvoiceNumber(
            ($this->settings['saas_invoice_prefix'] ?? 'TP') . 'G',
            1
        );

        $data = [
            'tenant_id'      => (int)$original['tenant_id'],
            'invoice_number' => $invoiceNumber,
            'status'         => 'paid',
            'type'           => 'credit_note',
            'credit_note_for'=> $originalInvoiceId,
            'storno_reason'  => $reason,
            'payment_method' => (string)($original['payment_method'] ?? 'rechnung'),
            'issue_date'     => date('Y-m-d'),
            'total_net'      => $netAmount,
            'total_tax'      => $taxAmount,
            'total_gross'    => $grossAmount,
            'notes'          => "Gutschrift zu Rechnung {$original['invoice_number']}: {$reason}",
            'payment_terms'  => '',
            'invoice_type_label' => 'Gutschrift',
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ];

        $originalPositions = $this->invoiceRepo->getPositions($originalInvoiceId);
        $creditNoteId      = (int)$this->invoiceRepo->create($data);

        foreach ($originalPositions as $i => $pos) {
            $this->invoiceRepo->addPosition($creditNoteId, [
                'description' => 'Gutschrift: ' . $pos['description'],
                'quantity'    => -(float)$pos['quantity'],
                'unit_price'  => (float)$pos['unit_price'],
                'tax_rate'    => (float)$pos['tax_rate'],
                'total'       => -(float)$pos['total'],
            ], $i + 1);
        }

        // Mark original as cancelled
        $this->invoiceRepo->update($originalInvoiceId, [
            'status'     => 'cancelled',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->notifRepo->create('system_update', 'Gutschrift erstellt', "Rechnung #{$originalInvoiceId} → Gutschrift {$invoiceNumber}");

        return $creditNoteId;
    }

    // ── Mahnwesen ────────────────────────────────────────────────────────

    /**
     * Run dunning escalation for all overdue SaaS invoices.
     * Called by cron or admin. Idempotent per invoice per day.
     */
    public function runDunningCycle(): array
    {
        $stats = ['processed' => 0, 'skipped' => 0, 'errors' => 0];

        $overdueInvoices = $this->db->fetchAll(
            "SELECT i.*, t.email AS tenant_email, t.practice_name, t.owner_name
             FROM saas_invoices i
             JOIN tenants t ON t.id = i.tenant_id
             WHERE i.status IN ('open','overdue')
               AND i.due_date IS NOT NULL
               AND i.due_date < CURDATE()
               AND i.type = 'invoice'
             ORDER BY i.due_date ASC"
        );

        $level1Days = (int)($this->settings['saas_dunning_level1_days'] ?? 7);
        $level2Days = (int)($this->settings['saas_dunning_level2_days'] ?? 14);
        $level3Days = (int)($this->settings['saas_dunning_level3_days'] ?? 21);

        foreach ($overdueInvoices as $inv) {
            try {
                $daysOverdue   = (int)$this->db->fetchColumn(
                    "SELECT DATEDIFF(CURDATE(), ?)", [$inv['due_date']]
                );
                $existingLevel = (int)$this->db->fetchColumn(
                    "SELECT MAX(level) FROM saas_invoice_dunnings WHERE invoice_id = ?",
                    [$inv['id']]
                );

                $targetLevel = 0;
                if ($daysOverdue >= $level3Days && $existingLevel < 3) $targetLevel = 3;
                elseif ($daysOverdue >= $level2Days && $existingLevel < 2) $targetLevel = 2;
                elseif ($daysOverdue >= $level1Days && $existingLevel < 1) $targetLevel = 1;

                if ($targetLevel === 0) {
                    $stats['skipped']++;
                    continue;
                }

                $fee     = (float)($this->settings["saas_dunning_level{$targetLevel}_fee"] ?? 0);
                $dueDate = date('Y-m-d', strtotime("+7 days"));

                $this->db->execute(
                    "INSERT INTO saas_invoice_dunnings (invoice_id, level, fee, due_date, created_at)
                     VALUES (?, ?, ?, ?, NOW())",
                    [$inv['id'], $targetLevel, $fee, $dueDate]
                );

                $this->db->execute(
                    "UPDATE saas_invoices SET status = 'overdue', updated_at = NOW() WHERE id = ?",
                    [$inv['id']]
                );

                $stats['processed']++;
            } catch (\Throwable $e) {
                error_log("[SaasInvoiceBillingService] Dunning error for invoice #{$inv['id']}: " . $e->getMessage());
                $stats['errors']++;
            }
        }

        return $stats;
    }

    /**
     * Get dunning records for a given invoice.
     */
    public function getDunnings(int $invoiceId): array
    {
        try {
            return $this->db->fetchAll(
                "SELECT * FROM saas_invoice_dunnings WHERE invoice_id = ? ORDER BY level ASC",
                [$invoiceId]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Manually create a dunning entry and optionally send email.
     */
    public function createDunning(int $invoiceId, int $level, float $fee, ?string $dueDate = null, bool $sendEmail = false): int
    {
        $dueDate = $dueDate ?? date('Y-m-d', strtotime('+7 days'));

        $this->db->execute(
            "INSERT INTO saas_invoice_dunnings (invoice_id, level, fee, due_date, created_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$invoiceId, $level, $fee, $dueDate]
        );
        $dunningId = (int)$this->db->lastInsertId();

        $this->db->execute(
            "UPDATE saas_invoices SET status = 'overdue', updated_at = NOW() WHERE id = ?",
            [$invoiceId]
        );

        if ($sendEmail) {
            $this->sendDunningEmail($invoiceId, $level, $fee, $dueDate);
        }

        return $dunningId;
    }

    // ── DATEV Export ─────────────────────────────────────────────────────

    /**
     * Generate DATEV-compatible CSV export.
     * Format: DATEV Buchungsstapel (simplified, compatible with DATEV Import)
     */
    public function generateDatevCsv(string $dateFrom, string $dateTo): string
    {
        $invoices = $this->invoiceRepo->getForTaxExport($dateFrom, $dateTo);

        $companyName = $this->settings['company_name']      ?? 'TheraPano SaaS';
        $taxId       = $this->settings['tax_id']            ?? '';
        $vatId       = $this->settings['vat_id']            ?? '';
        $consultant  = $this->settings['datev_consultant']  ?? '12345';
        $client      = $this->settings['datev_client']      ?? '67890';

        $header1 = sprintf(
            '"EXTF";510;21;"Buchungsstapel";5;%s;%s;;"RE";"";%s;%s;%s;%s;%s;EUR;"";"";1;0;;"";"";',
            date('YmdHis000'),
            date('YmdHis000'),
            str_replace('-', '', $dateFrom),
            str_replace('-', '', $dateTo),
            $consultant,
            $client,
            date('Y')
        );

        $header2 = implode(';', [
            'Umsatz (ohne Soll/Haben-Kz)', 'Soll/Haben-Kennzeichen', 'WKZ Umsatz',
            'Kurs', 'Basis-Umsatz', 'WKZ Basis-Umsatz', 'Konto', 'Gegenkonto (ohne BU-Schlüssel)',
            'BU-Schlüssel', 'Belegdatum', 'Belegfeld 1', 'Belegfeld 2',
            'Skonto', 'Buchungstext', 'Postensperre', 'Diverse Adressnummer',
            'Geschäftspartnerbank', 'Sachverhalt', 'Zinssperre', 'Beleglink',
            'Beleginfo - Art 1', 'Beleginfo - Inhalt 1',
        ]);

        $lines = [$header1, $header2];

        foreach ($invoices as $inv) {
            $amount     = number_format(abs((float)$inv['total_gross']), 2, ',', '');
            $shkz       = (float)$inv['total_gross'] >= 0 ? 'S' : 'H';
            $date       = date('dm', strtotime($inv['issue_date'] ?? date('Y-m-d')));
            $invoiceNo  = $inv['invoice_number'];
            $customerNo = 'K' . str_pad((string)$inv['tenant_id'], 4, '0', STR_PAD_LEFT);
            $text       = mb_substr($inv['tenant_display'] ?? '', 0, 60);
            $revenue    = $inv['type'] === 'credit_note' ? '8736' : '8400';

            $lines[] = implode(';', [
                $amount, $shkz, 'EUR', '', '', '', $revenue, $customerNo,
                '', $date, $invoiceNo, '', '', $text, '', '', '', '', '', '',
                'Rechnungsnummer', $invoiceNo,
            ]);
        }

        return implode("\r\n", $lines);
    }

    // ── Self-Healing ──────────────────────────────────────────────────────

    /**
     * Reconcile: find payments in `payments` table that have no matching SaaS invoice.
     * Creates missing invoices for them.
     */
    public function reconcileMissingInvoices(): array
    {
        $stats = ['created' => 0, 'errors' => 0];

        try {
            $orphanedPayments = $this->db->fetchAll(
                "SELECT p.*, t.practice_name, t.owner_name, t.email, t.plan
                 FROM payments p
                 JOIN tenants t ON t.id = p.tenant_id
                 WHERE p.status = 'paid'
                   AND p.method = 'stripe'
                   AND NOT EXISTS (
                       SELECT 1 FROM saas_invoices si
                       WHERE si.stripe_subscription_id = p.external_id
                         AND si.tenant_id = p.tenant_id
                         AND si.status = 'paid'
                   )
                 ORDER BY p.paid_at ASC
                 LIMIT 50"
            );

            foreach ($orphanedPayments as $payment) {
                try {
                    $vatRate     = (float)($this->settings['saas_invoice_vat_rate'] ?? 19);
                    $grossAmount = round((float)$payment['amount'], 2);
                    $netAmount   = round($grossAmount / (1 + $vatRate / 100), 2);
                    $taxAmount   = round($grossAmount - $netAmount, 2);

                    $invoiceNumber = $this->invoiceRepo->getNextInvoiceNumber(
                        $this->settings['saas_invoice_prefix'] ?? 'TP',
                        (int)($this->settings['saas_invoice_start_number'] ?? 1000)
                    );

                    $data = [
                        'tenant_id'              => (int)$payment['tenant_id'],
                        'invoice_number'         => $invoiceNumber,
                        'status'                 => 'paid',
                        'type'                   => 'invoice',
                        'payment_method'         => 'stripe',
                        'issue_date'             => date('Y-m-d', strtotime($payment['paid_at'])),
                        'total_net'              => $netAmount,
                        'total_tax'              => $taxAmount,
                        'total_gross'            => $grossAmount,
                        'notes'                  => 'Self-Healing: Rechnung aus payment rekonstruiert',
                        'payment_terms'          => '',
                        'paid_at'                => $payment['paid_at'],
                        'stripe_subscription_id' => $payment['external_id'],
                        'invoice_type_label'     => 'Stripe-Zahlung (rekonstruiert)',
                        'created_at'             => date('Y-m-d H:i:s'),
                        'updated_at'             => date('Y-m-d H:i:s'),
                    ];

                    $invoiceId = (int)$this->invoiceRepo->create($data);
                    $tenantName = trim($payment['practice_name'] ?? '') ?: trim($payment['owner_name'] ?? '');

                    $this->invoiceRepo->addPosition($invoiceId, [
                        'description' => ($payment['plan'] ?? 'TheraPano Abo') . ' – ' . $tenantName,
                        'quantity'    => 1.0,
                        'unit_price'  => $netAmount,
                        'tax_rate'    => $vatRate,
                        'total'       => $grossAmount,
                    ], 1);

                    $stats['created']++;
                } catch (\Throwable $e) {
                    error_log("[SaasInvoiceBillingService] Reconcile error: " . $e->getMessage());
                    $stats['errors']++;
                }
            }
        } catch (\Throwable $e) {
            error_log("[SaasInvoiceBillingService] Reconcile query error: " . $e->getMessage());
        }

        return $stats;
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private function webhookEventProcessed(string $eventId): bool
    {
        try {
            $row = $this->db->fetch(
                "SELECT id FROM saas_webhook_events WHERE event_id = ?", [$eventId]
            );
            return $row !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    private function markWebhookProcessed(string $eventId, int $invoiceId): void
    {
        try {
            $this->db->execute(
                "INSERT IGNORE INTO saas_webhook_events (event_id, event_type, invoice_id, processed_at)
                 VALUES (?, 'invoice.paid', ?, NOW())",
                [$eventId, $invoiceId]
            );
        } catch (\Throwable) {}
    }

    private function getInvoiceIdByStripeId(string $stripeInvoiceId): ?int
    {
        try {
            $row = $this->db->fetch(
                "SELECT id FROM saas_invoices WHERE stripe_invoice_id = ?", [$stripeInvoiceId]
            );
            return $row ? (int)$row['id'] : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function sendDunningEmail(int $invoiceId, int $level, float $fee, string $dueDate): void
    {
        try {
            $invoice = $this->invoiceRepo->findById($invoiceId);
            if (!$invoice) return;

            $toEmail = $invoice['tenant_email'] ?? null;
            if (!$toEmail) return;

            $subject = str_replace(
                '{{invoice_number}}',
                $invoice['invoice_number'],
                $this->settings["saas_dunning_email_subject_{$level}"] ?? "Mahnung: Rechnung {$invoice['invoice_number']}"
            );

            $tenantName = trim($invoice['tenant_name'] ?? '') ?: trim($invoice['owner_name'] ?? '');
            $body = "Sehr geehrte Damen und Herren,\r\n\r\n"
                . "Ihre Rechnung {$invoice['invoice_number']} über "
                . number_format((float)$invoice['total_gross'], 2, ',', '.') . " € ist noch offen.\r\n\r\n"
                . ($fee > 0 ? "Mahngebühr: " . number_format($fee, 2, ',', '.') . " €\r\n" : '')
                . "Bitte begleichen Sie den Betrag bis zum " . date('d.m.Y', strtotime($dueDate)) . ".\r\n\r\n"
                . "Mit freundlichen Grüßen\r\n"
                . ($this->settings['company_name'] ?? 'TheraPano SaaS');

            $from      = $this->settings['mail_from_address'] ?? ($this->settings['company_email'] ?? 'noreply@therapano.de');
            $fromName  = $this->settings['mail_from_name']    ?? ($this->settings['company_name']  ?? 'TheraPano SaaS');
            $toStr     = $tenantName ? "=?UTF-8?B?" . base64_encode($tenantName) . "?= <{$toEmail}>" : $toEmail;
            $headers   = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$from}>\r\n"
                        . "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n";

            mail($toStr, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);

            $this->db->execute(
                "UPDATE saas_invoice_dunnings SET sent_at = NOW(), sent_to = ? WHERE invoice_id = ? AND level = ? AND sent_at IS NULL ORDER BY id DESC LIMIT 1",
                [$toEmail, $invoiceId, $level]
            );
        } catch (\Throwable $e) {
            error_log("[SaasInvoiceBillingService] Dunning email error: " . $e->getMessage());
        }
    }
}
