<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SettingsRepository;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class MailService
{
    private string $lastError = '';

    public function __construct(
        private readonly SettingsRepository $settingsRepository
    ) {}

    public function getLastError(): string
    {
        return $this->lastError;
    }

    public function sendInvoice(array $invoice, array $owner, string $pdfContent): bool
    {
        try {
            $mailer = $this->createMailer();
            $mailer->addAddress($owner['email'], $owner['first_name'] . ' ' . $owner['last_name']);

            $placeholders = $this->buildPlaceholders($invoice, $owner);
            $mailer->Subject = $this->applyPlaceholders(
                $this->settingsRepository->get('email_invoice_subject', 'Ihre Rechnung {{invoice_number}}'),
                $placeholders
            );
            $body = $this->applyPlaceholders(
                $this->settingsRepository->get('email_invoice_body',
                    "Sehr geehrte/r {{owner_name}},\n\nanbei erhalten Sie Ihre Rechnung {{invoice_number}}.\n\nMit freundlichen Grüßen\n{{company_name}}"
                ),
                $placeholders
            );
            $mailer->Body    = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
            $mailer->AltBody = $body;
            $mailer->isHTML(true);

            $mailer->addStringAttachment(
                $pdfContent,
                'Rechnung-' . $invoice['invoice_number'] . '.pdf',
                PHPMailer::ENCODING_BASE64,
                'application/pdf'
            );

            return $mailer->send();
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('[MailService::sendInvoice] ' . $e->getMessage());
            return false;
        }
    }

    public function sendReceipt(array $invoice, array $owner, string $pdfContent): bool
    {
        try {
            $mailer = $this->createMailer();
            $mailer->addAddress($owner['email'], $owner['first_name'] . ' ' . $owner['last_name']);

            $placeholders = $this->buildPlaceholders($invoice, $owner);
            $mailer->Subject = $this->applyPlaceholders(
                $this->settingsRepository->get('email_receipt_subject', 'Ihre Quittung {{invoice_number}}'),
                $placeholders
            );
            $body = $this->applyPlaceholders(
                $this->settingsRepository->get('email_receipt_body',
                    "Sehr geehrte/r {{owner_name}},\n\nanbei erhalten Sie Ihre Quittung für Rechnung {{invoice_number}}.\n\nMit freundlichen Grüßen\n{{company_name}}"
                ),
                $placeholders
            );
            $mailer->Body    = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
            $mailer->AltBody = $body;
            $mailer->isHTML(true);

            $mailer->addStringAttachment(
                $pdfContent,
                'Quittung-' . $invoice['invoice_number'] . '.pdf',
                PHPMailer::ENCODING_BASE64,
                'application/pdf'
            );

            return $mailer->send();
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('[MailService::sendReceipt] ' . $e->getMessage());
            return false;
        }
    }

    private function buildPlaceholders(array $invoice, array $owner): array
    {
        $companyName = $this->settingsRepository->get('company_name', 'Tierphysio Praxis');
        $ownerName   = trim(($owner['first_name'] ?? '') . ' ' . ($owner['last_name'] ?? ''));

        $issueDate = '';
        if (!empty($invoice['issue_date'])) {
            try {
                $issueDate = (new \DateTime($invoice['issue_date']))->format('d.m.Y');
            } catch (\Throwable) { $issueDate = $invoice['issue_date']; }
        }
        $dueDate = '';
        if (!empty($invoice['due_date'])) {
            try {
                $dueDate = (new \DateTime($invoice['due_date']))->format('d.m.Y');
            } catch (\Throwable) { $dueDate = $invoice['due_date']; }
        }

        $gross = number_format((float)($invoice['total_gross'] ?? 0), 2, ',', '.') . ' €';

        return [
            '{{invoice_number}}' => $invoice['invoice_number'] ?? '',
            '{{owner_name}}'     => $ownerName,
            '{{owner_first}}'    => $owner['first_name'] ?? '',
            '{{owner_last}}'     => $owner['last_name'] ?? '',
            '{{owner_email}}'    => $owner['email'] ?? '',
            '{{issue_date}}'     => $issueDate,
            '{{due_date}}'       => $dueDate,
            '{{total_gross}}'    => $gross,
            '{{company_name}}'   => $companyName,
        ];
    }

    private function applyPlaceholders(string $template, array $placeholders): string
    {
        return str_replace(array_keys($placeholders), array_values($placeholders), $template);
    }

    public function sendRaw(string $to, string $toName, string $subject, string $body, array $attachments = []): bool
    {
        try {
            $mailer = $this->createMailer();
            $mailer->addAddress($to, $toName);
            $mailer->Subject = $subject;
            $mailer->Body    = $body;
            $mailer->isHTML(true);

            foreach ($attachments as $attachment) {
                if (isset($attachment['content'], $attachment['name'])) {
                    $mailer->addStringAttachment(
                        $attachment['content'],
                        $attachment['name'],
                        PHPMailer::ENCODING_BASE64,
                        $attachment['mime'] ?? 'application/octet-stream'
                    );
                }
            }

            return $mailer->send();
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            error_log('[MailService::sendRaw] ' . $e->getMessage());
            return false;
        }
    }

    private function createMailer(): PHPMailer
    {
        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host       = $this->settingsRepository->get('smtp_host', 'localhost');
        $mailer->Port       = (int)$this->settingsRepository->get('smtp_port', '587');
        $mailer->Username   = $this->settingsRepository->get('smtp_username', '');
        $mailer->Password   = $this->settingsRepository->get('smtp_password', '');
        $mailer->SMTPAuth   = !empty($mailer->Username);
        $enc = $this->settingsRepository->get('smtp_encryption', 'tls');
        if ($enc === 'ssl') {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($enc === 'none') {
            $mailer->SMTPSecure = '';
            $mailer->SMTPAutoTLS = false;
        } else {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        $fromAddress = $this->settingsRepository->get('mail_from_address', 'noreply@tierphysio.local');
        $fromName    = $this->settingsRepository->get('mail_from_name', 'Tierphysio Manager');
        $mailer->setFrom($fromAddress, $fromName);
        $mailer->CharSet = PHPMailer::CHARSET_UTF8;
        return $mailer;
    }
}
