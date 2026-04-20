<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

/**
 * CourseCategoryController
 *
 * Editor für den Kursarten-Katalog. Tenant-spezifisch (z.B. eine Trainerin
 * führt nur Welpen + Junghunde, andere betreibt Agility + Social Walks).
 *
 * Self-healing via Controller::requireFeature('dogschool_courses') →
 * DogschoolSchemaService erstellt dogschool_course_categories bei Bedarf.
 */
class CourseCategoryController extends Controller
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
        $this->requireFeature('dogschool_courses');

        $rows = $this->db->safeFetchAll(
            "SELECT * FROM `{$this->db->prefix('dogschool_course_categories')}`
              ORDER BY sort_order ASC, name ASC"
        );
        $this->render('dogschool/categories/index.twig', [
            'page_title' => 'Kursarten',
            'active_nav' => 'categories',
            'categories' => $rows,
        ]);
    }

    public function store(array $params = []): void
    {
        $this->requireFeature('dogschool_courses');
        $this->validateCsrf();

        $data = $this->collect();
        if ($data['slug'] === '' || $data['name'] === '') {
            $this->flash('error', 'Slug und Name erforderlich.');
            $this->redirect('/kursarten');
            return;
        }

        try {
            $this->db->safeExecute(
                "INSERT INTO `{$this->db->prefix('dogschool_course_categories')}`
                    (slug, name, description, icon, color, default_duration_min,
                     default_max_participants, default_price_cents, default_tax_rate,
                     sort_order, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $data['slug'], $data['name'], $data['description'], $data['icon'],
                    $data['color'], $data['default_duration_min'],
                    $data['default_max_participants'], $data['default_price_cents'],
                    $data['default_tax_rate'], $data['sort_order'], $data['is_active'],
                ]
            );
            $this->flash('success', 'Kursart angelegt.');
        } catch (\Throwable $e) {
            $this->flash('error', 'Slug bereits vergeben: ' . $data['slug']);
        }
        $this->redirect('/kursarten');
    }

    public function update(array $params = []): void
    {
        $this->requireFeature('dogschool_courses');
        $this->validateCsrf();

        $id   = (int)($params['id'] ?? 0);
        $data = $this->collect();
        $this->db->safeExecute(
            "UPDATE `{$this->db->prefix('dogschool_course_categories')}`
                SET name = ?, description = ?, icon = ?, color = ?,
                    default_duration_min = ?, default_max_participants = ?,
                    default_price_cents = ?, default_tax_rate = ?,
                    sort_order = ?, is_active = ?
              WHERE id = ?",
            [
                $data['name'], $data['description'], $data['icon'], $data['color'],
                $data['default_duration_min'], $data['default_max_participants'],
                $data['default_price_cents'], $data['default_tax_rate'],
                $data['sort_order'], $data['is_active'], $id,
            ]
        );
        $this->flash('success', 'Kursart aktualisiert.');
        $this->redirect('/kursarten');
    }

    public function delete(array $params = []): void
    {
        $this->requireFeature('dogschool_courses');
        $this->validateCsrf();

        $id = (int)($params['id'] ?? 0);
        $this->db->safeExecute(
            "DELETE FROM `{$this->db->prefix('dogschool_course_categories')}` WHERE id = ?",
            [$id]
        );
        $this->flash('success', 'Kursart gelöscht.');
        $this->redirect('/kursarten');
    }

    private function collect(): array
    {
        $slug = trim((string)$this->post('slug', ''));
        $slug = strtolower(preg_replace('/[^a-z0-9_-]/', '_', $slug));
        return [
            'slug'                      => $slug,
            'name'                      => trim((string)$this->post('name', '')),
            'description'               => trim((string)$this->post('description', '')) ?: null,
            'icon'                      => trim((string)$this->post('icon', '')) ?: null,
            'color'                     => trim((string)$this->post('color', '#60a5fa')),
            'default_duration_min'      => max(5, (int)$this->post('default_duration_min', 60)),
            'default_max_participants'  => max(1, (int)$this->post('default_max_participants', 8)),
            'default_price_cents'       => max(0, (int)round(((float)$this->post('default_price_eur', 0)) * 100)),
            'default_tax_rate'          => max(0, (float)$this->post('default_tax_rate', 19)),
            'sort_order'                => (int)$this->post('sort_order', 0),
            'is_active'                 => (int)(bool)$this->post('is_active', 0),
        ];
    }
}
