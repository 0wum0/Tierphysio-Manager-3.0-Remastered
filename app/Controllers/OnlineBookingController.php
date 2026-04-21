<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Services\DogschoolSchemaService;
use App\Services\MailService;

/**
 * OnlineBookingController
 *
 * Öffentliches Buchungs-Portal + interne Verwaltung der eingehenden
 * Anfragen. Die öffentlichen Routen haben KEINE Auth — stattdessen:
 *   - Honeypot-Feld gegen Bots
 *   - Rate-Limit per IP (max 5 Anfragen/Stunde)
 *   - IP + User-Agent loggen für spätere Spam-Auswertung
 *
 * Nach Approval durch einen Admin wird die Anfrage in einen Lead
 * konvertiert (status = approved → lead_id gesetzt).
 */
class OnlineBookingController extends Controller
{
    public function __construct(
        \App\Core\View $view,
        \App\Core\Session $session,
        \App\Core\Config $config,
        \App\Core\Translator $translator,
        private readonly Database $db,
        private readonly DogschoolSchemaService $schema,
        private readonly MailService $mailService,
    ) {
        parent::__construct($view, $session, $config, $translator);
    }

    /* ═══════════════════════ Interne Verwaltung ═══════════════════════ */

    public function adminIndex(array $params = []): void
    {
        $this->requireFeature('dogschool_online_booking');

        $status = (string)$this->get('status', 'pending');
        $conds  = [];
        $par    = [];
        if ($status !== '' && $status !== 'all') {
            $conds[] = 'status = ?';
            $par[]   = $status;
        }
        $where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';
        $rows = $this->db->safeFetchAll(
            "SELECT * FROM `{$this->db->prefix('dogschool_booking_requests')}`
              {$where}
              ORDER BY status = 'pending' DESC, created_at DESC",
            $par
        );
        $this->render('dogschool/booking/admin_index.twig', [
            'page_title' => 'Buchungsanfragen',
            'active_nav' => 'booking_admin',
            'rows'       => $rows,
            'filter_status' => $status,
        ]);
    }

    public function adminUpdate(array $params = []): void
    {
        $this->requireFeature('dogschool_online_booking');
        $this->validateCsrf();

        $id     = (int)($params['id'] ?? 0);
        $status = (string)$this->post('status', 'pending');
        $allowed = ['pending','approved','declined','spam'];
        if (!in_array($status, $allowed, true)) {
            $this->redirect('/anfragen');
            return;
        }

        $this->db->safeExecute(
            "UPDATE `{$this->db->prefix('dogschool_booking_requests')}`
                SET status = ? WHERE id = ?",
            [$status, $id]
        );

        /* Bei Approval: in Lead konvertieren (falls nicht bereits geschehen) */
        if ($status === 'approved') {
            $req = $this->db->safeFetch(
                "SELECT * FROM `{$this->db->prefix('dogschool_booking_requests')}` WHERE id = ?",
                [$id]
            );
            if ($req && empty($req['lead_id'])) {
                try {
                    $leadId = (int)$this->db->insert(
                        "INSERT INTO `{$this->db->prefix('dogschool_leads')}`
                            (source, first_name, last_name, email, phone,
                             dog_name, dog_breed, dog_age_months, message,
                             interest, status)
                         VALUES ('Online-Buchung', ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new')",
                        [
                            $req['first_name'], $req['last_name'], $req['email'], $req['phone'],
                            $req['dog_name'], $req['dog_breed'], $req['dog_age_months'],
                            $req['message'], $req['requested_for'],
                        ]
                    );
                    $this->db->safeExecute(
                        "UPDATE `{$this->db->prefix('dogschool_booking_requests')}`
                            SET lead_id = ? WHERE id = ?",
                        [$leadId, $id]
                    );
                } catch (\Throwable $e) {
                    error_log('[OnlineBooking convert] ' . $e->getMessage());
                }
            }
        }

        $this->flash('success', 'Status aktualisiert.');
        $this->redirect('/anfragen');
    }

    /* ═══════════════════════ Öffentliches Portal ═══════════════════════ */

    public function publicForm(array $params = []): void
    {
        /* Schema idempotent sicherstellen — öffentlicher Weg hat kein requireFeature */
        try { $this->schema->ensure(); } catch (\Throwable) {}

        /* Aktive Kurse anzeigen (falls vorhanden) */
        $courses = $this->db->safeFetchAll(
            "SELECT c.id, c.name, c.type, c.level, c.start_date, c.start_time,
                    c.max_participants, c.price_cents, c.location,
                    (SELECT COUNT(*) FROM `{$this->db->prefix('dogschool_enrollments')}` e
                      WHERE e.course_id = c.id AND e.status = 'active') AS enrolled
               FROM `{$this->db->prefix('dogschool_courses')}` c
              WHERE c.status = 'active'
                AND (c.start_date IS NULL OR c.start_date >= CURDATE())
              ORDER BY c.start_date ASC
              LIMIT 20"
        );

        $this->view->render('dogschool/booking/public_form.twig', [
            'page_title' => 'Buchungsanfrage',
            'courses'    => $courses,
            'layout'     => 'layouts/public.twig',
        ]);
    }

    public function publicSubmit(array $params = []): void
    {
        try { $this->schema->ensure(); } catch (\Throwable) {}

        /* Honeypot — wenn das versteckte Feld ausgefüllt ist, ist es ein Bot */
        if (trim((string)$this->post('website', '')) !== '') {
            /* Bot erkennen: so tun als wäre erfolgreich, aber nichts speichern */
            $this->redirect('/buchung/danke');
            return;
        }

        /* Rate-Limit per IP: max 5 / Stunde */
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        if ($ip !== '') {
            $recentCount = (int)$this->db->safeFetchColumn(
                "SELECT COUNT(*) FROM `{$this->db->prefix('dogschool_booking_requests')}`
                  WHERE ip_address = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)",
                [$ip]
            );
            if ($recentCount >= 5) {
                $this->flash('error', 'Zu viele Anfragen von dieser IP. Bitte später erneut versuchen.');
                $this->redirect('/buchung');
                return;
            }
        }

        /* Pflichtfelder */
        $firstName = trim((string)$this->post('first_name', ''));
        $lastName  = trim((string)$this->post('last_name', ''));
        $email     = trim((string)$this->post('email', ''));
        if ($firstName === '' || $lastName === '' || $email === '') {
            $this->flash('error', 'Name und E-Mail sind Pflichtfelder.');
            $this->redirect('/buchung');
            return;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash('error', 'Bitte eine gültige E-Mail-Adresse eingeben.');
            $this->redirect('/buchung');
            return;
        }

        /* DSGVO-Einwilligung Pflicht — Clientseitig ist die Checkbox
         * `required`, serverseitig absichern damit kein manipulierter
         * Request ohne aktive Zustimmung durchkommt. */
        if ((string)$this->post('gdpr_consent', '') !== '1') {
            $this->flash('error', 'Bitte bestätige die Datenschutzerklärung, um deine Anfrage zu senden.');
            $this->redirect('/buchung');
            return;
        }

        $token = bin2hex(random_bytes(16));
        $this->db->safeExecute(
            "INSERT INTO `{$this->db->prefix('dogschool_booking_requests')}`
                (token, course_id, first_name, last_name, email, phone,
                 dog_name, dog_breed, dog_age_months, message, requested_for,
                 ip_address, user_agent, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')",
            [
                $token,
                ((int)$this->post('course_id', 0)) ?: null,
                $firstName, $lastName, $email,
                trim((string)$this->post('phone', '')) ?: null,
                trim((string)$this->post('dog_name', '')) ?: null,
                trim((string)$this->post('dog_breed', '')) ?: null,
                ((int)$this->post('dog_age_months', 0)) ?: null,
                trim((string)$this->post('message', '')) ?: null,
                (string)$this->post('requested_for', 'course'),
                $ip,
                substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
            ]
        );

        /* ── Eingangsbestätigung an den Anfragenden senden (tenant-type-aware).
         * Fehler beim Mail-Versand blockieren NICHT die Weiterleitung zur
         * Danke-Seite — die Anfrage ist in der DB sicher gelandet. */
        try {
            $requestedFor = (string)$this->post('requested_for', 'course');
            $subjectMap   = [
                'course'       => 'Kursbuchung',
                'trial'        => 'Probetraining',
                'consultation' => 'Beratungsgespräch',
            ];
            $this->mailService->sendBookingRequestConfirmation(
                $email,
                $firstName,
                trim((string)$this->post('dog_name', '')) ?: null,
                $subjectMap[$requestedFor] ?? null
            );
        } catch (\Throwable $e) {
            error_log('[OnlineBooking publicSubmit mail] ' . $e->getMessage());
        }

        $this->redirect('/buchung/danke');
    }

    public function publicThanks(array $params = []): void
    {
        $this->view->render('dogschool/booking/public_thanks.twig', [
            'page_title' => 'Danke',
            'layout'     => 'layouts/public.twig',
        ]);
    }
}
