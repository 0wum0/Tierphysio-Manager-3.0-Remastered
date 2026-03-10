<?php

declare(strict_types=1);

namespace Plugins\OwnerPortal;

use App\Repositories\SettingsRepository;
use App\Services\MailService;

class OwnerPortalMailService
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly MailService $mailer
    ) {}

    public function sendInvite(string $email, string $token): void
    {
        $baseUrl     = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                     . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $inviteUrl   = $baseUrl . '/portal/einladung/' . $token;
        $companyName = $this->settings->get('company_name', 'Tierphysio Praxis');

        $subject = 'Ihr Zugang zum Besitzerportal — ' . $companyName;
        $body    = "Guten Tag,\n\n"
                 . "Sie wurden eingeladen, das Besitzerportal von {$companyName} zu nutzen.\n\n"
                 . "Über das Portal können Sie Ihre Tiere, Rechnungen, Termine und Übungen einsehen.\n\n"
                 . "Klicken Sie auf den folgenden Link, um Ihr Passwort festzulegen:\n"
                 . $inviteUrl . "\n\n"
                 . "Dieser Link ist 7 Tage gültig.\n\n"
                 . "Mit freundlichen Grüßen\n"
                 . $companyName;

        $this->mailer->send(
            to: $email,
            subject: $subject,
            body: $body
        );
    }
}
