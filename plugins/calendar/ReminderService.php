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
        $sent   = 0;
        $failed = 0;

        foreach ($appointments as $a) {
            // Erinnerung an Praxisinhaber
            if (!empty($a['owner_email'])) {
                $success = $this->mailService->sendReminder($a);
                $success ? $sent++ : $failed++;
            }

            // Erinnerung an Patient/Kunde (falls aktiviert und email vorhanden)
            if (!empty($a['send_patient_reminder']) && !empty($a['patient_email'])) {
                $success = $this->mailService->sendPatientReminder($a);
                $success ? $sent++ : $failed++;
            }

            $this->appointmentRepository->markReminderSent((int)$a['id']);
        }

        return ['sent' => $sent, 'failed' => $failed, 'total' => count($appointments)];
    }

}
