<?php

declare(strict_types=1);

namespace Plugins\Calendar;

use App\Repositories\SettingsRepository;
use App\Services\MailService;

class ReminderService
{
    public function __construct(
        private readonly AppointmentRepository $appointmentRepository,
        private readonly MailService           $mailService,
        private readonly SettingsRepository    $settingsRepository
    ) {}

    public function processPending(): array
    {
        $appointments = $this->appointmentRepository->findPendingReminders();
        $sent      = 0;
        $failed    = 0;
        $skipped   = 0;
        $lastError = '';

        /* Practice fallback e-mail (used when owner has no e-mail) */
        $practiceEmail = $this->settingsRepository->get('mail_from', '');
        if ($practiceEmail === '') {
            $practiceEmail = $this->settingsRepository->get('smtp_user', '');
        }

        foreach ($appointments as $a) {
            $apptLabel = '"' . ($a['title'] ?? 'Termin') . '" am ' . ($a['start_at'] ?? '?');
            /* DIAGNOSE: zeigt exakt was die DB zurückgibt */
            error_log('[ReminderService] DIAGNOSE id=' . ($a['id'] ?? '?')
                . ' owner_id=' . ($a['owner_id'] ?? 'NULL')
                . ' owner_email=' . ($a['owner_email'] ?? 'NULL')
                . ' first_name=' . ($a['first_name'] ?? 'NULL')
                . ' title=' . ($a['title'] ?? '?'));

            /* Erinnerung an Tierhalter; Fallback auf Praxis-E-Mail wenn keine vorhanden */
            if (empty($a['owner_email'])) {
                if ($practiceEmail === '') {
                    error_log('[ReminderService] SKIP ' . $apptLabel . ' — kein owner_email und keine Praxis-E-Mail in Einstellungen konfiguriert');
                    $skipped++;
                    continue;
                }
                /* Send to practice as fallback so the reminder is not lost */
                error_log('[ReminderService] FALLBACK ' . $apptLabel . ' — kein Tierhalter verknüpft → sende an Praxis: ' . $practiceEmail);
                $a['owner_email'] = $practiceEmail;
                $a['first_name']  = 'Praxis';
                $a['last_name']   = '';
            } else {
                error_log('[ReminderService] SEND ' . $apptLabel . ' → ' . $a['owner_email']);
            }

            /* Claim-before-Send: Erinnerung ATOMAR beanspruchen, BEVOR gesendet
             * wird. Erinnerungen laufen über mehrere, teils gleichzeitige Auslöser
             * (Dispatcher, Praxis-Cron, Cron-Pixel). Das alte Muster
             * (senden → danach markieren) hat bei parallelen Läufen und bei
             * Prozessabbruch während des SMTP-Versands Doppel-Mails erzeugt. */
            if (!$this->appointmentRepository->claimReminder((int)$a['id'])) {
                error_log('[ReminderService] SKIP ' . $apptLabel . ' — bereits von parallelem Lauf beansprucht');
                $skipped++;
                continue;
            }

            $ownerSent = $this->mailService->sendReminder($a);
            if (!$ownerSent) {
                /* Claim freigeben → nächster Lauf versucht es erneut,
                 * solange das Erinnerungsfenster (start_at > NOW()) offen ist. */
                $this->appointmentRepository->releaseReminder((int)$a['id']);
                $failed++;
                $lastError = $this->mailService->getLastError();
                error_log('[ReminderService] FAILED ' . $apptLabel . ' — ' . $lastError);
                continue;
            }
            $sent++;

            /* Optional: zusätzliche Erinnerung an Patient/Kunde */
            if (!empty($a['send_patient_reminder']) && !empty($a['patient_email'])) {
                $success = $this->mailService->sendPatientReminder($a);
                $success ? $sent++ : $failed++;
            }
        }

        return [
            'sent'       => $sent,
            'failed'     => $failed,
            'skipped'    => $skipped,
            'total'      => count($appointments),
            'last_error' => $lastError,
        ];
    }

}
