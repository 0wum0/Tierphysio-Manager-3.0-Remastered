<?php

declare(strict_types=1);

namespace Plugins\PatientInvite;

use App\Repositories\SettingsRepository;
use App\Services\MailService;

class InviteMailService
{
    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly MailService $mailService
    ) {}

    public function sendInviteEmail(string $toEmail, string $inviteUrl, string $note = ''): bool
    {
        return $this->mailService->sendInvite($toEmail, $inviteUrl, $note);
    }

    /**
     * Baut eine WhatsApp-Share-URL.
     *
     * Wenn $phone leer ist, wird https://wa.me/?text=... zurückgegeben —
     * der offizielle universelle WhatsApp-Share-Link, bei dem der User den
     * Empfänger in WhatsApp selbst auswählt. Dadurch funktioniert der
     * "Per WhatsApp einladen"-Flow auch komplett ohne eingegebene Nummer.
     */
    public function buildWhatsAppUrl(
        string $phone,
        string $inviteUrl,
        string $appName,
        string $note = ''
    ): string {
        $phone = $phone === '' ? '' : (string)preg_replace('/[^0-9+]/', '', $phone);

        $msg  = "Hallo! 👋\n\nSie wurden von {$appName} eingeladen, sich direkt in unserem System zu registrieren.";
        if ($note !== '') {
            $msg .= "\n\n💬 {$note}";
        }
        $msg .= "\n\nKlicken Sie auf den folgenden Link, um Ihr Tier und sich selbst zu registrieren:\n👉 {$inviteUrl}\n\nDer Link ist 7 Tage gültig.";

        return 'https://wa.me/' . ltrim($phone, '+') . '?text=' . rawurlencode($msg);
    }

}
