<?php

declare(strict_types=1);

namespace Plugins\LicenseGuard;

use App\Core\Controller;
use App\Core\View;
use App\Core\Session;
use App\Core\Config;
use App\Core\Translator;
use App\Core\Database;

class LicenseSetupController extends Controller
{
    private Database $db;

    public function __construct(
        View       $view,
        Session    $session,
        Config     $config,
        Translator $translator,
        Database   $database
    ) {
        parent::__construct($view, $session, $config, $translator);
        $this->db = $database;
    }

    private function t(string $table): string
    {
        return $this->db->prefix($table);
    }

    public function index(array $params = []): void
    {
        $this->requireRole('admin');

        $settings = [
            'saas_url'           => $this->getSetting('saas_url'),
            'tenant_uuid'        => $this->getSetting('tenant_uuid'),
            'license_token'      => $this->getSetting('license_token'),
            'license_checked_at' => $this->getSetting('license_checked_at'),
            'license_status'     => $this->session->get('license_status', 'unknown'),
            'license_plan'       => $this->session->get('license_plan', ''),
        ];

        $this->render('license-setup.twig', [
            'settings'   => $settings,
            'page_title' => 'Lizenz-Konfiguration',
            'active_nav' => 'settings',
        ]);
    }

    public function save(array $params = []): void
    {
        $this->requireRole('admin');
        $this->validateCsrf();

        $saasUrl = rtrim(trim($_POST['saas_url'] ?? ''), '/');
        $uuid    = trim($_POST['tenant_uuid'] ?? '');
        $token   = trim($_POST['license_token'] ?? '');

        if (!$saasUrl || !$uuid) {
            $this->session->flash('error', 'SaaS-URL und Tenant-UUID sind erforderlich.');
            $this->redirect('/license-setup');
        }

        $this->saveSetting('saas_url', $saasUrl);
        $this->saveSetting('tenant_uuid', $uuid);
        if ($token) {
            $this->saveSetting('license_token', $token);
        }
        // Force re-check on next request
        $this->saveSetting('license_checked_at', '0');

        $this->session->flash('success', 'Lizenz-Einstellungen gespeichert. Die Lizenz wird beim nächsten Seitenaufruf geprüft.');
        $this->redirect('/license-setup');
    }

    private function getSetting(string $key): string
    {
        try {
            $result = $this->db->fetchColumn(
                "SELECT `value` FROM `{$this->t('settings')}` WHERE `key` = ?", [$key]
            );
            return $result !== false ? (string)$result : '';
        } catch (\Throwable) {
            return '';
        }
    }

    private function saveSetting(string $key, string $value): void
    {
        try {
            $this->db->execute(
                "INSERT INTO `{$this->t('settings')}` (`key`, `value`) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE `value` = ?",
                [$key, $value, $value]
            );
        } catch (\Throwable) {}
    }
}
