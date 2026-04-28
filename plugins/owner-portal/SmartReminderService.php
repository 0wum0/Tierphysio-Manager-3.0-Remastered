<?php

declare(strict_types=1);

namespace Plugins\OwnerPortal;

use App\Core\Database;
use App\Repositories\SettingsRepository;

/**
 * Smart Erinnerungen für das Besitzerportal.
 *
 * Versendet zwei Typen von E-Mail-Erinnerungen:
 *  1. Übungs-/Hausaufgaben-Erinnerung: wenn Besitzer aktive Pläne hat und seit
 *     `portal_exercise_reminder_days` (Standard: 3) Tagen keine Aufgabe abgehakt hat.
 *  2. Inaktivitäts-Erinnerung: wenn Besitzer seit `portal_inactivity_days`
 *     (Standard: 14) Tagen nicht mehr eingeloggt war.
 *
 * Logik: "wurde Mail gesendet? → wenn nein → senden (auch wenn Zeit überschritten)"
 * → Verhindert endlose Wiederholung durch Eintrag in portal_smart_reminders.
 */
class SmartReminderService
{
    private string $prefix;

    public function __construct(
        private readonly Database           $db,
        private readonly OwnerPortalMailService $mailer,
        private readonly SettingsRepository $settings
    ) {
        $this->prefix = $this->db->getPrefix();
    }

    /* ── Hauptmethode: alle fälligen Erinnerungen senden ── */

    public function processPending(): array
    {
        $exerciseSent = $this->sendExerciseReminders();
        $inactSent    = $this->sendInactivityReminders();

        return [
            'exercise_sent'    => $exerciseSent['sent'],
            'exercise_skipped' => $exerciseSent['skipped'],
            'exercise_failed'  => $exerciseSent['failed'],
            'inactivity_sent'  => $inactSent['sent'],
            'inactivity_skipped' => $inactSent['skipped'],
            'inactivity_failed'  => $inactSent['failed'],
            'total_sent'       => $exerciseSent['sent'] + $inactSent['sent'],
        ];
    }

    /* ── 1. Übungs-Erinnerungen ── */

    private function sendExerciseReminders(): array
    {
        $sent = $failed = $skipped = 0;
        $reminderDays = (int)$this->settings->get('portal_exercise_reminder_days', '3');
        if ($reminderDays <= 0) {
            return ['sent' => 0, 'skipped' => 0, 'failed' => 0];
        }

        /* Besitzer mit aktiven Hausaufgabenplänen, aber ohne Erinnerung in den letzten
           $reminderDays Tagen und ohne Überprüfung seit dieser Zeit */
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$reminderDays} days"));
        $sentCutoff = date('Y-m-d H:i:s', strtotime('-1 day')); // max 1x pro Tag

        $t_users  = $this->prefix . 'owner_portal_users';
        $t_plans  = $this->prefix . 'portal_homework_plans';
        $t_checks = $this->prefix . 'portal_homework_plan_tasks';
        $t_chtask = $this->prefix . 'portal_homework_checklist';
        $t_remind = $this->prefix . 'portal_smart_reminders';

        try {
            /* Besitzer mit aktiven Plänen, wo die letzte Aktivität länger als $cutoff zurückliegt */
            $owners = $this->db->fetchAll(
                "SELECT DISTINCT u.id AS user_id, u.owner_id, u.email,
                        u.last_login_at, p.practice_name AS company
                 FROM `{$t_users}` u
                 JOIN `{$t_plans}` php ON php.owner_id = u.owner_id AND php.status = 'active'
                 WHERE u.is_active = 1
                   AND u.email != ''
                   AND (
                       /* Hat seit $cutoff keine Aufgabe abgehakt */
                       NOT EXISTS (
                           SELECT 1 FROM `{$t_chtask}` ch
                           WHERE ch.owner_id = u.owner_id
                             AND ch.checked_at >= ?
                       )
                   )
                   AND NOT EXISTS (
                       SELECT 1 FROM `{$t_remind}` r
                       WHERE r.owner_id = u.owner_id
                         AND r.type IN ('exercise','homework')
                         AND r.sent_at >= ?
                         AND r.status = 'sent'
                   )",
                [$cutoff, $sentCutoff]
            );
        } catch (\Throwable $e) {
            error_log('[SmartReminderService] DB error (exercise): ' . $e->getMessage());
            return ['sent' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $companyName = $this->settings->get('company_name', 'Ihre Tierphysio Praxis');

        foreach ($owners as $owner) {
            $email = (string)($owner['email'] ?? '');
            if ($email === '') { $skipped++; continue; }

            try {
                $ok = $this->mailer->sendExerciseReminder(
                    $email,
                    (string)($owner['company'] ?? $companyName)
                );
                $this->logReminder(
                    (int)$owner['owner_id'],
                    'exercise',
                    null,
                    $email,
                    $ok ? 'sent' : 'failed'
                );
                $ok ? $sent++ : $failed++;
            } catch (\Throwable $e) {
                error_log('[SmartReminderService] exercise reminder failed: ' . $e->getMessage());
                $this->logReminder((int)$owner['owner_id'], 'exercise', null, $email, 'failed', $e->getMessage());
                $failed++;
            }
        }

        return compact('sent', 'skipped', 'failed');
    }

    /* ── 2. Inaktivitäts-Erinnerungen ── */

    private function sendInactivityReminders(): array
    {
        $sent = $failed = $skipped = 0;
        $inactivityDays = (int)$this->settings->get('portal_inactivity_days', '14');
        if ($inactivityDays <= 0) {
            return ['sent' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $cutoff     = date('Y-m-d H:i:s', strtotime("-{$inactivityDays} days"));
        $sentCutoff = date('Y-m-d H:i:s', strtotime('-7 days')); // max 1x pro Woche

        $t_users  = $this->prefix . 'owner_portal_users';
        $t_remind = $this->prefix . 'portal_smart_reminders';

        try {
            /* Aktive Portal-User die seit $cutoff nicht eingeloggt waren und
               denen wir in den letzten 7 Tagen noch keine Inaktivitäts-Mail geschickt haben */
            $owners = $this->db->fetchAll(
                "SELECT u.id AS user_id, u.owner_id, u.email, u.last_login_at
                 FROM `{$t_users}` u
                 WHERE u.is_active = 1
                   AND u.email != ''
                   AND (u.last_login IS NULL OR u.last_login < CAST(? AS DATETIME))
                   AND NOT EXISTS (
                       SELECT 1 FROM `{$t_remind}` r
                       WHERE r.owner_id = u.owner_id
                         AND r.type = 'inactivity'
                         AND r.sent_at >= ?
                         AND r.status = 'sent'
                   )",
                [$cutoff, $sentCutoff]
            );
        } catch (\Throwable $e) {
            error_log('[SmartReminderService] DB error (inactivity): ' . $e->getMessage());
            return ['sent' => 0, 'skipped' => 0, 'failed' => 0];
        }

        $companyName = $this->settings->get('company_name', 'Ihre Tierphysio Praxis');

        foreach ($owners as $owner) {
            $email = (string)($owner['email'] ?? '');
            if ($email === '') { $skipped++; continue; }

            $lastLogin = $owner['last_login'] ?? null;
            $daysSince = $lastLogin
                ? (int)round((time() - strtotime((string)$lastLogin)) / 86400)
                : $inactivityDays;

            try {
                $ok = $this->mailer->sendInactivityReminder(
                    $email,
                    $companyName,
                    $daysSince
                );
                $this->logReminder(
                    (int)$owner['owner_id'],
                    'inactivity',
                    null,
                    $email,
                    $ok ? 'sent' : 'failed'
                );
                $ok ? $sent++ : $failed++;
            } catch (\Throwable $e) {
                error_log('[SmartReminderService] inactivity reminder failed: ' . $e->getMessage());
                $this->logReminder((int)$owner['owner_id'], 'inactivity', null, $email, 'failed', $e->getMessage());
                $failed++;
            }
        }

        return compact('sent', 'skipped', 'failed');
    }

    /* ── Hilfsmethoden ── */

    private function logReminder(
        int $ownerId,
        string $type,
        ?int $refId,
        string $email,
        string $status,
        ?string $error = null
    ): void {
        try {
            $t = $this->prefix . 'portal_smart_reminders';
            $this->db->query(
                "INSERT INTO `{$t}` (owner_id, type, ref_id, email, status, error)
                 VALUES (?, ?, ?, ?, ?, ?)",
                [$ownerId, $type, $refId, $email, $status, $error]
            );
        } catch (\Throwable $e) {
            error_log('[SmartReminderService] log failed: ' . $e->getMessage());
        }
    }
}
