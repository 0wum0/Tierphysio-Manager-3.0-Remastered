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
            /* Erinnerung an Tierhalter; Fallback auf Praxis-E-Mail wenn keine vorhanden */
            if (empty($a['owner_email'])) {
                if ($practiceEmail === '') {
                    $skipped++;
                    continue;
                }
                /* Send to practice as fallback so the reminder is not lost */
                $a['owner_email'] = $practiceEmail;
                $a['first_name']  = 'Praxis';
                $a['last_name']   = '';
            }

            $ownerSent = $this->mailService->sendReminder($a);
            if (!$ownerSent) {
                $failed++;
                $lastError = $this->mailService->getLastError();
                continue;
            }
            $sent++;

            /* Optional: zusätzliche Erinnerung an Patient/Kunde */
            if (!empty($a['send_patient_reminder']) && !empty($a['patient_email'])) {
                $success = $this->mailService->sendPatientReminder($a);
                $success ? $sent++ : $failed++;
            }

            /* Erst nach erfolgreichem Besitzer-Versand als gesendet markieren */
            $this->appointmentRepository->markReminderSent((int)$a['id']);
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
