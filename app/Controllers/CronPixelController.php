<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Application;

/**
 * Pixel-Cron-Controller
 *
 * Works exactly like the 2MoonsCE browser-game cron pixel:
 * Every page loads a 1x1 transparent GIF from /cron/pixel.gif
 * This endpoint immediately returns the image, then runs any
 * due cron jobs AFTER the response is flushed to the browser.
 *
 * No external cron scheduler needed — jobs fire automatically
 * whenever any user (or portal visitor) loads a page.
 *
 * Intervals:
 *   birthday          — at most once per day   (86400 s)
 *   calendar_reminders— at most every 15 min   (900 s)
 *   tcp_reminders     — at most every 15 min   (900 s)
 *   google_calendar   — at most every 60 min   (3600 s)
 */
class CronPixelController
{
    private const JOBS = [
        'birthday'           => 86400,
        'calendar_reminders' => 900,
        'tcp_reminders'      => 900,
        'google_calendar'    => 3600,
    ];

    /* 1x1 transparent GIF (binary) */
    private const PIXEL = "\x47\x49\x46\x38\x39\x61\x01\x00\x01\x00\x80\x00\x00\xff\xff\xff"
                        . "\x00\x00\x00\x21\xf9\x04\x00\x00\x00\x00\x00\x2c\x00\x00\x00\x00"
                        . "\x01\x00\x01\x00\x00\x02\x02\x44\x01\x00\x3b";

    public function pixel(): void
    {
        /* ── Serve the pixel immediately ── */
        header('Content-Type: image/gif');
        header('Content-Length: ' . strlen(self::PIXEL));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

        echo self::PIXEL;

        /* Flush response to browser before doing any work */
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            if (ob_get_level()) { ob_end_flush(); }
            flush();
        }

        ignore_user_abort(true);

        /* ── Now run any due jobs ── */
        try {
            $db = Application::getInstance()->getContainer()->get(Database::class);
            foreach (self::JOBS as $jobKey => $intervalSeconds) {
                if ($this->isDue($db, $jobKey, $intervalSeconds)) {
                    $this->runJob($db, $jobKey);
                }
            }
        } catch (\Throwable) {
            /* Never surface errors from pixel endpoint */
        }
        exit;
    }

    /* ─── Check if a job is due ─────────────────────────────── */
    private function isDue(Database $db, string $jobKey, int $intervalSeconds): bool
    {
        try {
            $stmt = $db->query(
                'SELECT created_at FROM cron_job_log
                 WHERE job_key = ? AND status != ?
                 ORDER BY created_at DESC LIMIT 1',
                [$jobKey, 'error']
            );
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$row) {
                return true; /* Never run — run now */
            }
            $lastRun = strtotime($row['created_at']);
            return (time() - $lastRun) >= $intervalSeconds;
        } catch (\Throwable) {
            return false; /* Table may not exist yet */
        }
    }

    /* ─── Dispatch a specific job ────────────────────────────── */
    private function runJob(Database $db, string $jobKey): void
    {
        $start = hrtime(true);
        try {
            $result = match($jobKey) {
                'birthday'           => $this->runBirthday($db),
                'calendar_reminders' => $this->runCalendarReminders($db),
                'tcp_reminders'      => $this->runTcpReminders($db),
                'google_calendar'    => $this->runGoogleCalendar($db),
                default              => ['ok' => false, 'msg' => 'unknown job'],
            };
            $status  = ($result['ok'] ?? false) ? 'success' : 'error';
            $message = $result['msg'] ?? '';
        } catch (\Throwable $e) {
            $status  = 'error';
            $message = $e->getMessage();
        }
        $ms = (int)((hrtime(true) - $start) / 1_000_000);
        CronAdminController::logRun($db, $jobKey, $status, $message, $ms, 'pixel');
    }

    /* ─── Job: Birthday emails ───────────────────────────────── */
    private function runBirthday(Database $db): array
    {
        $container = Application::getInstance()->getContainer();
        $service   = $container->get(\App\Services\BirthdayMailService::class);
        $result    = $service->runDailyCheck();
        return [
            'ok'  => true,
            'msg' => 'sent=' . $result['sent'] . ', skipped=' . $result['skipped'] . ', errors=' . $result['errors'],
        ];
    }

    /* ─── Job: Calendar reminders ────────────────────────────── */
    private function runCalendarReminders(Database $db): array
    {
        $container = Application::getInstance()->getContainer();

        $appointmentRepo = $container->get(\Plugins\Calendar\AppointmentRepository::class);
        $mailService     = $container->get(\App\Services\MailService::class);
        $settingsRepo    = $container->get(\App\Repositories\SettingsRepository::class);

        $reminderService = new \Plugins\Calendar\ReminderService(
            $appointmentRepo, $mailService, $settingsRepo
        );
        $result = $reminderService->processPending();
        return [
            'ok'  => true,
            'msg' => 'sent=' . ($result['sent'] ?? 0) . ', skipped=' . ($result['skipped'] ?? 0),
        ];
    }

    /* ─── Job: TherapyCare reminders ─────────────────────────── */
    private function runTcpReminders(Database $db): array
    {
        $container = Application::getInstance()->getContainer();
        $repo      = $container->get(\Plugins\TherapyCarePro\TherapyCareRepository::class);
        $mailer    = $container->get(\App\Services\MailService::class);
        $queue     = $repo->getPendingReminderQueue();
        $sent = 0; $failed = 0;

        foreach ($queue as $item) {
            try {
                $ok = $mailer->sendRaw(
                    $item['owner_email'],
                    $item['owner_first_name'] . ' ' . $item['owner_last_name'],
                    $item['subject'],
                    $item['body']
                );
                if ($ok) {
                    $repo->markReminderSent($item['id']);
                    $sent++;
                } else {
                    $repo->markReminderFailed($item['id'], $mailer->getLastError() ?? 'send failed');
                    $failed++;
                }
            } catch (\Throwable $e) {
                $repo->markReminderFailed($item['id'], $e->getMessage());
                $failed++;
            }
        }
        return [
            'ok'  => true,
            'msg' => 'sent=' . $sent . ', failed=' . $failed . ', total=' . count($queue),
        ];
    }

    /* ─── Job: Google Calendar sync ──────────────────────────── */
    private function runGoogleCalendar(Database $db): array
    {
        $container  = Application::getInstance()->getContainer();
        $gcRepo     = $container->get(\Plugins\GoogleCalendarSync\GoogleCalendarRepository::class);
        $api        = new \Plugins\GoogleCalendarSync\GoogleApiService($gcRepo);
        $syncSvc    = new \Plugins\GoogleCalendarSync\GoogleSyncService($gcRepo, $api, $db);
        $push       = $syncSvc->bulkSyncAll();
        $pull       = $syncSvc->pullFromGoogle();
        return [
            'ok'  => true,
            'msg' => 'push=' . json_encode($push) . ' pull=' . json_encode($pull),
        ];
    }
}
