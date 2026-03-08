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
            if (empty($a['owner_email'])) {
                $this->appointmentRepository->markReminderSent((int)$a['id']);
                continue;
            }

            $success = $this->mailService->sendReminder($a);

            $this->appointmentRepository->markReminderSent((int)$a['id']);
            $success ? $sent++ : $failed++;
        }

        return ['sent' => $sent, 'failed' => $failed, 'total' => count($appointments)];
    }

}
