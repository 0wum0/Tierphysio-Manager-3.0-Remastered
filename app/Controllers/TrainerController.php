<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

/**
 * TrainerController — Trainer-Team-Management.
 *
 * Erweitert bestehendes `users`-Konzept um Hundeschul-spezifische
 * Profildaten (Bio, Spezialisierung, Avatar, Farbe, Verfügbarkeiten).
 * Keine Duplikation der User-Auth — Zugriff bleibt über die normale Rolle.
 */
class TrainerController extends Controller
{
    public function __construct(
        \App\Core\View $view,
        \App\Core\Session $session,
        \App\Core\Config $config,
        \App\Core\Translator $translator,
        private readonly Database $db,
    ) {
        parent::__construct($view, $session, $config, $translator);
    }

    public function index(array $params = []): void
    {
        $this->requireFeature('dogschool_trainers');

        $trainers = $this->db->safeFetchAll(
            "SELECT u.id AS user_id, u.name AS user_name, u.email, u.role,
                    t.id AS profile_id, t.display_name, t.bio, t.specializations,
                    t.color, t.avatar_url, t.phone, t.email_public, t.public_profile,
                    t.is_active,
                    (SELECT COUNT(*) FROM `{$this->db->prefix('dogschool_courses')}` c
                      WHERE c.trainer_user_id = u.id AND c.status IN ('active','full')) AS active_courses
               FROM `{$this->db->prefix('users')}` u
               LEFT JOIN `{$this->db->prefix('dogschool_trainer_profiles')}` t ON t.user_id = u.id
              WHERE u.role IN ('admin','trainer','mitarbeiter')
              ORDER BY (t.is_active IS NULL) ASC, t.is_active DESC, u.name ASC"
        );

        $this->render('dogschool/trainers/index.twig', [
            'page_title' => 'Trainer-Team',
            'active_nav' => 'trainers',
            'trainers'   => $trainers,
        ]);
    }

    public function show(array $params = []): void
    {
        $this->requireFeature('dogschool_trainers');

        $userId = (int)($params['id'] ?? 0);
        $user = $this->db->safeFetch(
            "SELECT * FROM `{$this->db->prefix('users')}` WHERE id = ?",
            [$userId]
        );
        if (!$user) {
            $this->flash('error', 'Nutzer nicht gefunden.');
            $this->redirect('/trainer');
            return;
        }

        /* Profil sicherstellen (oder leer anzeigen) */
        $profile = $this->db->safeFetch(
            "SELECT * FROM `{$this->db->prefix('dogschool_trainer_profiles')}` WHERE user_id = ?",
            [$userId]
        ) ?: [];

        $availability = $this->db->safeFetchAll(
            "SELECT * FROM `{$this->db->prefix('dogschool_trainer_availability')}`
              WHERE user_id = ?
              ORDER BY weekday ASC, start_time ASC",
            [$userId]
        );

        $this->render('dogschool/trainers/show.twig', [
            'page_title'    => $user['name'] ?? 'Trainer',
            'active_nav'    => 'trainers',
            'user'          => $user,
            'profile'       => $profile,
            'availability'  => $availability,
            'weekdays'      => [1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do', 5 => 'Fr', 6 => 'Sa', 0 => 'So'],
        ]);
    }

    public function update(array $params = []): void
    {
        $this->requireFeature('dogschool_trainers');
        $this->validateCsrf();

        $userId = (int)($params['id'] ?? 0);
        $data = [
            'user_id'         => $userId,
            'display_name'    => trim((string)$this->post('display_name', '')) ?: null,
            'bio'             => trim((string)$this->post('bio', '')) ?: null,
            'qualifications'  => trim((string)$this->post('qualifications', '')) ?: null,
            'specializations' => trim((string)$this->post('specializations', '')) ?: null,
            'color'           => trim((string)$this->post('color', '#60a5fa')),
            'avatar_url'      => trim((string)$this->post('avatar_url', '')) ?: null,
            'phone'           => trim((string)$this->post('phone', '')) ?: null,
            'email_public'    => trim((string)$this->post('email_public', '')) ?: null,
            'public_profile'  => (int)(bool)$this->post('public_profile', 0),
            'is_active'       => (int)(bool)$this->post('is_active', 0),
        ];

        /* Upsert */
        $existing = $this->db->safeFetch(
            "SELECT id FROM `{$this->db->prefix('dogschool_trainer_profiles')}` WHERE user_id = ?",
            [$userId]
        );
        if ($existing) {
            $this->db->safeExecute(
                "UPDATE `{$this->db->prefix('dogschool_trainer_profiles')}`
                    SET display_name = ?, bio = ?, qualifications = ?, specializations = ?,
                        color = ?, avatar_url = ?, phone = ?, email_public = ?,
                        public_profile = ?, is_active = ?
                  WHERE user_id = ?",
                [
                    $data['display_name'], $data['bio'], $data['qualifications'],
                    $data['specializations'], $data['color'], $data['avatar_url'],
                    $data['phone'], $data['email_public'], $data['public_profile'],
                    $data['is_active'], $userId,
                ]
            );
        } else {
            $this->db->safeExecute(
                "INSERT INTO `{$this->db->prefix('dogschool_trainer_profiles')}`
                    (user_id, display_name, bio, qualifications, specializations,
                     color, avatar_url, phone, email_public, public_profile, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $userId, $data['display_name'], $data['bio'], $data['qualifications'],
                    $data['specializations'], $data['color'], $data['avatar_url'],
                    $data['phone'], $data['email_public'], $data['public_profile'],
                    $data['is_active'],
                ]
            );
        }

        $this->flash('success', 'Profil gespeichert.');
        $this->redirect('/trainer/' . $userId);
    }

    public function addAvailability(array $params = []): void
    {
        $this->requireFeature('dogschool_trainers');
        $this->validateCsrf();

        $userId = (int)($params['id'] ?? 0);
        $this->db->safeExecute(
            "INSERT INTO `{$this->db->prefix('dogschool_trainer_availability')}`
                (user_id, weekday, start_time, end_time, is_active)
             VALUES (?, ?, ?, ?, 1)",
            [
                $userId,
                (int)$this->post('weekday', 1),
                (string)$this->post('start_time', '09:00'),
                (string)$this->post('end_time', '18:00'),
            ]
        );
        $this->flash('success', 'Verfügbarkeit hinzugefügt.');
        $this->redirect('/trainer/' . $userId);
    }

    public function removeAvailability(array $params = []): void
    {
        $this->requireFeature('dogschool_trainers');
        $this->validateCsrf();

        $userId = (int)($params['id'] ?? 0);
        $availId = (int)($params['avail_id'] ?? 0);
        $this->db->safeExecute(
            "DELETE FROM `{$this->db->prefix('dogschool_trainer_availability')}` WHERE id = ? AND user_id = ?",
            [$availId, $userId]
        );
        $this->redirect('/trainer/' . $userId);
    }
}
