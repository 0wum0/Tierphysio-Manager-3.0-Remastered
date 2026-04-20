<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Config;
use App\Core\Session;
use App\Core\Translator;
use App\Core\View;
use App\Core\PluginManager;
use App\Services\SettingsService;
use App\Services\MigrationService;
use App\Services\MailService;
use App\Repositories\UserRepository;
use App\Repositories\TreatmentTypeRepository;
use App\Repositories\HomeworkRepository;

class SettingsController extends Controller
{
    public function __construct(
        View $view,
        Session $session,
        Config $config,
        Translator $translator,
        private readonly SettingsService $settingsService,
        private readonly PluginManager $pluginManager,
        private readonly MigrationService $migrationService,
        private readonly UserRepository $userRepository,
        private readonly TreatmentTypeRepository $treatmentTypeRepository,
        private readonly HomeworkRepository $homeworkRepository,
        private readonly MailService $mailService
    ) {
        parent::__construct($view, $session, $config, $translator);
    }

    public function index(array $params = []): void
    {
        /* ── Email template defaults ─────────────────────────────────── */
        $emailDefaults = [
            'email_invoice_subject'  => 'Deine Rechnung {{invoice_number}}',
            'email_invoice_body'     => "Hallo {{owner_name}},\n\nanbei erhältst du deine Rechnung {{invoice_number}} vom {{issue_date}}.\n\nGesamtbetrag: {{total_gross}}\nBitte überweise den Betrag bis zum {{due_date}}.\n\nLiebe Grüße\n{{company_name}}",
            'email_receipt_subject'  => 'Deine Quittung {{invoice_number}}',
            'email_receipt_body'     => "Hallo {{owner_name}},\n\nvielen Dank für deine Zahlung. Anbei erhältst du deine Quittung für Rechnung {{invoice_number}} vom {{issue_date}}.\n\nBezahlter Betrag: {{total_gross}}\n\nLiebe Grüße\n{{company_name}}",
            'email_reminder_subject' => 'Terminerinnerung: {{appointment_title}} am {{appointment_date}}',
            'email_reminder_body'    => "Hallo {{owner_name}},\n\nhiermit möchte ich dich an deinen bevorstehenden Termin erinnern:\n\n📅 {{appointment_title}}\nDatum: {{appointment_date}}\nUhrzeit: {{appointment_time}}\n{{appointment_patient}}\n\nFalls du den Termin absagen oder verschieben möchtest, melde dich gerne bei mir.\n\nLiebe Grüße\n{{company_name}}",
            'email_invite_subject'   => 'Deine Einladung zur Anmeldung — {{company_name}}',
            'email_invite_body'      => "Du wurdest eingeladen!\n\n{{from_name}} lädt dich ein, dein Tier und dich als Besitzer direkt in meinem System zu registrieren.\n\n{{note}}\n\nJetzt registrieren:\n{{invite_url}}\n\nDieser Link ist 7 Tage gültig.\n\nLiebe Grüße\n{{company_name}}",
            'birthday_mail_subject'  => '🎂 Alles Gute zum Geburtstag, {{patient_name}}!',
            'birthday_mail_body'     => "Liebe/r {{owner_name}},\n\nheute hat {{patient_name}} Geburtstag! 🎉\n\nIch wünsche {{patient_name}} alles Gute und noch viele gesunde, glückliche Jahre.\n\nHerzliche Grüße,\n{{company_name}}",
            'gdpr_text'              => "# Datenschutzerklärung – {{company_name}}\n\n## 1. Allgemeine Hinweise\n\nDer Schutz personenbezogener Daten hat für uns höchste Priorität. Diese Datenschutzerklärung informiert über die Verarbeitung personenbezogener Daten im Rahmen der Nutzung der Software {{company_name}}.\n\n{{company_name}} ist eine cloudbasierte Softwarelösung für Tiertherapeuten, Tierphysiotherapeuten und Hundetrainer.\n\n## 2. Rollen im Datenschutz\n\nIm Rahmen der Nutzung gelten folgende Rollen:\n\n- Nutzer (Therapeuten / Trainer) sind Verantwortliche im Sinne der DSGVO\n- {{company_name}} ist Auftragsverarbeiter\n\nDer Nutzer entscheidet über Zweck und Umfang der Datenverarbeitung. {{company_name}} verarbeitet Daten ausschließlich im Auftrag und nach Weisung des Nutzers.\n\n## 3. Verantwortlicher für die Plattform\n\n{{company_name}}\n{{company_street}}\n{{company_zip}} {{company_city}}\n{{company_email}}\n{{company_phone}}\n\n## 4. Verarbeitung von Daten\n\n{{company_name}} ermöglicht die Verarbeitung folgender Daten:\n\n### Daten von Tierhaltern\n- Name\n- Geburtsdatum\n- E-Mail-Adresse\n- Telefonnummer\n- Adresse\n\n### Tierbezogene Daten\n- Name des Tieres\n- Rasse\n- Alter / Geburtsdatum\n- Gesundheitsdaten\n- Diagnosen\n- Therapieverläufe\n- Behandlungsdokumentationen\n\nDiese Daten werden im Auftrag des jeweiligen Nutzers verarbeitet.\n\n## 5. Zweck der Verarbeitung\n\nDie Verarbeitung dient:\n\n- Verwaltung von Patienten (Tieren)\n- Dokumentation von Behandlungen\n- Kommunikation mit Tierhaltern\n- Zusammenarbeit mit Tierärzten und Trainern\n- Erstellung von Berichten und Auswertungen\n\n## 6. Weitergabe von Daten\n\nDie Weitergabe erfolgt ausschließlich durch den Nutzer:\n\n- an Tierärzte\n- an Trainer\n- an Dritte auf Wunsch des Tierhalters\n\n{{company_name}} gibt keine Daten eigenständig weiter.\n\n## 7. Hosting und Speicherung\n\n- Daten werden auf Servern innerhalb der EU gespeichert (sofern zutreffend)\n- Es werden geeignete technische und organisatorische Maßnahmen eingesetzt\n- Zugriff erfolgt nur im Rahmen der vertraglichen Nutzung\n\n## 8. Auftragsverarbeitung\n\nZwischen {{company_name}} und dem Nutzer wird ein Vertrag zur Auftragsverarbeitung gemäß Art. 28 DSGVO abgeschlossen.\n\n## 9. Datensicherheit\n\nEs werden geeignete Maßnahmen umgesetzt:\n\n- SSL/TLS Verschlüsselung\n- Zugriffsbeschränkungen\n- Authentifizierung\n- Datensicherungen (Backups)\n- Schutz vor unbefugtem Zugriff\n\n## 10. Speicherdauer\n\n- Daten werden nur so lange gespeichert, wie es für die Nutzung erforderlich ist\n- Der Nutzer entscheidet über Löschung oder Aufbewahrung\n- Nach Vertragsende erfolgt Löschung gemäß Vereinbarung\n\n## 11. Rechte der betroffenen Personen\n\nBetroffene Personen wenden sich an den jeweiligen Therapeuten oder Trainer.\n\n{{company_name}} unterstützt die Nutzer technisch bei der Umsetzung:\n\n- Auskunft\n- Berichtigung\n- Löschung\n- Einschränkung\n- Datenübertragbarkeit\n\n## 12. Änderungen\n\nDiese Datenschutzerklärung kann angepasst werden, um rechtliche Anforderungen zu erfüllen.\n\n---\n\n**Stand " . date('F Y') . "**",
        ];

        /* Write defaults to DB on first visit (only if key is not yet set) */
        foreach ($emailDefaults as $key => $defaultValue) {
            if (empty($this->settingsService->get($key))) {
                $this->settingsService->set($key, $defaultValue);
            }
        }

        $settings        = $this->settingsService->all();
        $users           = $this->userRepository->findAll();
        $plugins         = $this->pluginManager->getAllAvailablePlugins();
        $currentVersion  = $this->migrationService->getCurrentVersion();
        $latestVersion   = $this->migrationService->getLatestVersion();
        $pendingMigrations = $this->migrationService->getPendingMigrations();

        $treatmentTypes = [];
        try {
            $treatmentTypes = $this->treatmentTypeRepository->findAll();
        } catch (\Throwable) {}

        $homeworkTemplates = [];
        try {
            $homeworkTemplates = $this->homeworkRepository->findAllTemplatesAdmin();
        } catch (\Throwable) {}

        $scheme   = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $httpHost = $_SERVER['HTTP_HOST'] ?? ($this->config->get('app.url', '') ? parse_url($this->config->get('app.url', ''), PHP_URL_HOST) : 'localhost');
        $appUrl   = $scheme . '://' . $httpHost;

        $this->render('settings/index.twig', [
            'page_title'       => $this->translator->trans('nav.settings'),
            'settings'         => $settings,
            'users'            => $users,
            'plugins'          => $plugins,
            'current_version'  => $currentVersion,
            'latest_version'   => $latestVersion,
            'pending'          => $pendingMigrations,
            'up_to_date'       => empty($pendingMigrations),
            'php_version'      => PHP_VERSION,
            'app_env'          => $this->config->get('app.env', 'production'),
            'active_tab'         => $params['tab'] ?? ($_GET['tab'] ?? 'firma'),
            'treatment_types'    => $treatmentTypes,
            'homework_templates' => $homeworkTemplates,
            'app_host'         => $httpHost,
            'app_url'          => $appUrl,
            'email_tpl_defaults' => [
                'invoice_subject'  => $emailDefaults['email_invoice_subject'],
                'invoice_body'     => $emailDefaults['email_invoice_body'],
                'receipt_subject'  => $emailDefaults['email_receipt_subject'],
                'receipt_body'     => $emailDefaults['email_receipt_body'],
                'reminder_subject' => $emailDefaults['email_reminder_subject'],
                'reminder_body'    => $emailDefaults['email_reminder_body'],
                'invite_subject'   => $emailDefaults['email_invite_subject'],
                'invite_body'      => $emailDefaults['email_invite_body'],
                'birthday_subject' => $emailDefaults['birthday_mail_subject'],
                'birthday_body'    => $emailDefaults['birthday_mail_body'],
            ],
        ]);
    }

    public function update(array $params = []): void
    {
        $this->validateCsrf();

        $allowed = [
            'company_name', 'company_street', 'company_zip', 'company_city',
            'company_phone', 'company_email', 'company_website',
            'bank_name', 'bank_iban', 'bank_bic',
            'payment_terms', 'invoice_prefix', 'invoice_start_number',
            'tax_number', 'vat_number', 'default_tax_rate', 'kleinunternehmer',
            'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password',
            'smtp_encryption', 'mail_from_name', 'mail_from_address',
            'default_language', 'default_theme',
            'pdf_primary_color', 'pdf_accent_color', 'pdf_row_color',
            'pdf_color_company_name', 'pdf_color_company_info', 'pdf_color_recipient',
            'pdf_color_table_header_bg', 'pdf_color_table_header_text',
            'pdf_color_table_text', 'pdf_color_line', 'pdf_color_total_label',
            'pdf_color_total_gross', 'pdf_color_footer',
            'pdf_font', 'pdf_font_size', 'pdf_layout',
            'pdf_header_style', 'pdf_logo_position', 'pdf_logo_width', 'pdf_margin',
            'pdf_show_logo', 'pdf_show_patient', 'pdf_show_chip',
            'pdf_show_page_numbers', 'pdf_show_iban', 'pdf_show_tax_number', 'pdf_show_website',
            'pdf_zebra_rows', 'pdf_watermark',
            'pdf_footer_text', 'pdf_intro_text', 'pdf_closing_text',
            'calendar_cron_secret',
            'mail_imap_host', 'mail_imap_port', 'mail_imap_encrypt', 'mail_imap_user',
            'email_invoice_subject',  'email_invoice_body',
            'email_receipt_subject',  'email_receipt_body',
            'email_reminder_subject', 'email_reminder_body',
            'email_invite_subject',   'email_invite_body',
            'birthday_mail_subject',  'birthday_mail_body', 'birthday_cron_token',
            'birthday_mail_enabled',
            'google_client_id', 'google_client_secret', 'google_redirect_uri',
            'portal_show_homework',
            'practice_type',
            'gdpr_text',
        ];

        $data = [];
        foreach ($allowed as $key) {
            if (isset($_POST[$key])) {
                $data[$key] = $this->sanitize($_POST[$key]);
            }
        }
        /* Checkboxes: explicitly write '0' when unchecked (not present in POST) */
        foreach (['birthday_mail_enabled', 'portal_show_homework'] as $cbKey) {
            if (!isset($data[$cbKey])) {
                $data[$cbKey] = '0';
            }
        }

        if (empty($data)) {
            $this->session->flash('error', 'DEBUG: Keine Daten empfangen. POST-Keys: ' . implode(', ', array_keys($_POST)));
            $this->redirect('/einstellungen');
            return;
        }

        foreach ($data as $key => $value) {
            $this->settingsService->set($key, $value);
        }

        /* Wechselt der Praxis-Typ (therapeut ↔ trainer), muss der lokale
         * Feature-Gate-Cache geleert werden. Sonst sieht die Praxis-App
         * bis zum TTL-Ablauf (15s) noch die alte Tenant-Typ-Zuordnung
         * — Hundeschul-Features würden im UI verzögert auftauchen bzw.
         * verschwinden. Das DELETE triggert beim nächsten Request ein
         * frisches syncFromSaas() inkl. neuer practiceTypeCache-Lesung. */
        if (array_key_exists('practice_type', $data)) {
            try {
                \App\Core\Application::getInstance()->getContainer()
                    ->get(\App\Services\FeatureGateService::class)
                    ->forceSync();
            } catch (\Throwable $e) {
                /* Cache-Reset ist best-effort — Setting-Save bleibt gültig */
                error_log('[SettingsController] feature cache reset: ' . $e->getMessage());
            }
        }

        $this->session->flash('success', $this->translator->trans('settings.saved'));
        $this->redirect('/einstellungen');
    }

    public function uploadLogo(array $params = []): void
    {
        $this->validateCsrf();

        $destination = tenant_storage_path('uploads');
        $filename    = $this->uploadFile('logo', $destination, ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

        if ($filename === false) {
            $this->session->flash('error', $this->translator->trans('settings.logo_upload_failed'));
            $this->redirect('/einstellungen');
            return;
        }

        $this->settingsService->set('company_logo', $filename);
        $this->session->flash('success', $this->translator->trans('settings.logo_updated'));
        $this->redirect('/einstellungen');
    }

    public function uploadPdfRechnungBild(array $params = []): void
    {
        $this->validateCsrf();
        $dest     = ROOT_PATH . '/public/assets/img';
        $filename = $this->uploadFile('pdf_rechnung_bild', $dest, ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
        if ($filename === false) {
            $this->session->flash('error', 'Bild-Upload fehlgeschlagen.');
            $this->redirect('/einstellungen?tab=pdf');
            return;
        }
        // Rename to fixed filename so PdfService always finds it
        $ext = pathinfo($dest . '/' . $filename, PATHINFO_EXTENSION);
        rename($dest . '/' . $filename, $dest . '/rechnung-script.' . $ext);
        $this->settingsService->set('pdf_rechnung_bild', 'rechnung-script.' . $ext);
        $this->session->flash('success', '"Rechnung"-Bild aktualisiert.');
        $this->redirect('/einstellungen?tab=pdf');
    }

    public function uploadPdfVielenDankBild(array $params = []): void
    {
        $this->validateCsrf();
        $dest     = ROOT_PATH . '/public/assets/img';
        $filename = $this->uploadFile('pdf_vielen_dank_bild', $dest, ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
        if ($filename === false) {
            $this->session->flash('error', 'Bild-Upload fehlgeschlagen.');
            $this->redirect('/einstellungen?tab=pdf');
            return;
        }
        $ext = pathinfo($dest . '/' . $filename, PATHINFO_EXTENSION);
        rename($dest . '/' . $filename, $dest . '/vielen-dank-script.' . $ext);
        $this->settingsService->set('pdf_vielen_dank_bild', 'vielen-dank-script.' . $ext);
        $this->session->flash('success', '"Vielen Dank!"-Bild aktualisiert.');
        $this->redirect('/einstellungen?tab=pdf');
    }

    public function uploadPdfQuittungBild(array $params = []): void
    {
        $this->validateCsrf();
        $dest     = ROOT_PATH . '/public/assets/img';
        $filename = $this->uploadFile('pdf_quittung_bild', $dest, ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
        if ($filename === false) {
            $this->session->flash('error', 'Bild-Upload fehlgeschlagen.');
            $this->redirect('/einstellungen?tab=pdf');
            return;
        }
        $ext = pathinfo($dest . '/' . $filename, PATHINFO_EXTENSION);
        rename($dest . '/' . $filename, $dest . '/quittung-script.' . $ext);
        $this->settingsService->set('pdf_quittung_bild', 'quittung-script.' . $ext);
        $this->session->flash('success', '"Quittung"-Bild aktualisiert.');
        $this->redirect('/einstellungen?tab=pdf');
    }

    public function uploadPdfBarzahlungBild(array $params = []): void
    {
        $this->validateCsrf();
        $dest     = ROOT_PATH . '/public/assets/img';
        $filename = $this->uploadFile('pdf_barzahlung_bild', $dest, ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
        if ($filename === false) {
            $this->session->flash('error', 'Bild-Upload fehlgeschlagen.');
            $this->redirect('/einstellungen?tab=pdf');
            return;
        }
        $ext = pathinfo($dest . '/' . $filename, PATHINFO_EXTENSION);
        rename($dest . '/' . $filename, $dest . '/barzahlung-script.' . $ext);
        $this->settingsService->set('pdf_barzahlung_bild', 'barzahlung-script.' . $ext);
        $this->session->flash('success', '"Barzahlung"-Bild aktualisiert.');
        $this->redirect('/einstellungen?tab=pdf');
    }

    public function uploadPdfErinnerungBild(array $params = []): void
    {
        $this->validateCsrf();
        $dest     = ROOT_PATH . '/public/assets/img';
        $filename = $this->uploadFile('pdf_erinnerung_bild', $dest, ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
        if ($filename === false) {
            $this->session->flash('error', 'Bild-Upload fehlgeschlagen.');
            $this->redirect('/einstellungen?tab=pdf');
            return;
        }
        $ext = pathinfo($dest . '/' . $filename, PATHINFO_EXTENSION);
        rename($dest . '/' . $filename, $dest . '/erinnerung-script.' . $ext);
        $this->settingsService->set('pdf_erinnerung_bild', 'erinnerung-script.' . $ext);
        $this->session->flash('success', '"Erinnerung"-Bild aktualisiert.');
        $this->redirect('/einstellungen?tab=pdf');
    }

    public function uploadPdfMahnungBild(array $params = []): void
    {
        $this->validateCsrf();
        $dest     = ROOT_PATH . '/public/assets/img';
        $filename = $this->uploadFile('pdf_mahnung_bild', $dest, ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
        if ($filename === false) {
            $this->session->flash('error', 'Bild-Upload fehlgeschlagen.');
            $this->redirect('/einstellungen?tab=pdf');
            return;
        }
        $ext = pathinfo($dest . '/' . $filename, PATHINFO_EXTENSION);
        rename($dest . '/' . $filename, $dest . '/mahnung-script.' . $ext);
        $this->settingsService->set('pdf_mahnung_bild', 'mahnung-script.' . $ext);
        $this->session->flash('success', '"Mahnung"-Bild aktualisiert.');
        $this->redirect('/einstellungen?tab=pdf');
    }

    public function plugins(array $params = []): void
    {
        $this->redirect('/einstellungen#plugins');
    }

    public function enablePlugin(array $params = []): void
    {
        $this->validateCsrf();
        $this->pluginManager->enablePlugin($params['name']);
        $this->session->flash('success', $this->translator->trans('settings.plugin_enabled'));
        $this->redirect('/einstellungen#plugins');
    }

    public function disablePlugin(array $params = []): void
    {
        $this->validateCsrf();
        $this->pluginManager->disablePlugin($params['name']);
        $this->session->flash('success', $this->translator->trans('settings.plugin_disabled'));
        $this->redirect('/einstellungen#plugins');
    }

    public function updater(array $params = []): void
    {
        $this->redirect('/einstellungen#updates');
    }

    public function runMigrations(array $params = []): void
    {
        $this->validateCsrf();

        try {
            $ran = $this->migrationService->runPending();
            $this->session->flash('success', $this->translator->trans('settings.migrations_ran', ['count' => count($ran)]));
        } catch (\Throwable $e) {
            $this->session->flash('error', $this->translator->trans('settings.migrations_failed') . ': ' . $e->getMessage());
        }

        $this->redirect('/einstellungen#updates');
    }

    /**
     * Forced Sync / Repair: Resets version to 0 and re-runs all migrations.
     */
    public function repairMigrations(array $params = []): void
    {
        $this->validateCsrf();

        try {
            $ran = $this->migrationService->forceSync();
            $this->session->flash('success', 'System-Reparatur abgeschlossen. ' . count($ran) . ' Migrations-Schritte überprüft.');
        } catch (\Throwable $e) {
            $this->session->flash('error', 'Fehler bei der Reparatur: ' . $e->getMessage());
        }

        $this->redirect('/einstellungen#updates');
    }

    /**
     * Tests SMTP Connection with POST data
     */
    public function testSmtp(array $params = []): void
    {
        $this->validateCsrf();

        // Collect config from POST (not yet saved to DB)
        $config = [
            'smtp_host'          => $this->post('smtp_host', ''),
            'smtp_port'          => $this->post('smtp_port', '587'),
            'smtp_username'      => $this->post('smtp_username', ''),
            'smtp_password'      => $this->post('smtp_password', ''),
            'smtp_encryption'    => $this->post('smtp_encryption', 'tls'),
            'mail_from_address'  => $this->post('mail_from_address', ''),
            'mail_from_name'     => $this->post('mail_from_name', 'SMTP Test'),
        ];

        $target = $config['mail_from_address'] ?: 'test@example.com';
        
        header('Content-Type: application/json');
        
        if (empty($config['smtp_host'])) {
            echo json_encode(['success' => false, 'message' => 'Bitte geben Sie einen SMTP-Host an.']);
            exit;
        }

        if ($this->mailService->testConnection($config, $target)) {
            echo json_encode(['success' => true, 'message' => 'Verbindung erfolgreich! Eine Test-E-Mail wurde an ' . $target . ' gesendet.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Verbindung fehlgeschlagen: ' . $this->mailService->getLastError()]);
        }
        exit;
    }

    public function users(array $params = []): void
    {
        $this->redirect('/einstellungen#benutzer');
    }

    public function createUser(array $params = []): void
    {
        $this->validateCsrf();

        $name     = $this->sanitize($this->post('name', ''));
        $email    = $this->sanitize($this->post('email', ''));
        $password = $this->post('password', '');
        $role     = $this->sanitize($this->post('role', 'mitarbeiter'));

        if (empty($name) || empty($email) || empty($password)) {
            $this->session->flash('error', $this->translator->trans('settings.fill_required'));
            $this->redirect('/einstellungen/benutzer');
            return;
        }

        if ($this->userRepository->findByEmail($email)) {
            $this->session->flash('error', $this->translator->trans('settings.email_exists'));
            $this->redirect('/einstellungen/benutzer');
            return;
        }

        $this->userRepository->create([
            'name'     => $name,
            'email'    => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            'role'     => $role,
            'active'   => 1,
        ]);

        $this->session->flash('success', $this->translator->trans('settings.user_created'));
        $this->redirect('/einstellungen#benutzer');
    }

    public function updateUser(array $params = []): void
    {
        $this->validateCsrf();

        $id    = (int)$params['id'];
        $name  = $this->sanitize($this->post('name', ''));
        $email = $this->sanitize($this->post('email', ''));
        $role  = in_array($this->post('role'), ['admin', 'mitarbeiter'], true) ? $this->post('role') : 'mitarbeiter';

        if (empty($name) || empty($email)) {
            $this->session->flash('error', $this->translator->trans('settings.fill_required'));
            $this->redirect('/einstellungen/benutzer');
            return;
        }

        $data = ['name' => $name, 'email' => $email, 'role' => $role];

        $password = $this->post('password', '');
        if (!empty($password)) {
            if (strlen($password) < 8) {
                $this->session->flash('error', $this->translator->trans('profile.password_mismatch'));
                $this->redirect('/einstellungen/benutzer');
                return;
            }
            $data['password'] = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        }

        $this->userRepository->update($id, $data);
        $this->session->flash('success', $this->translator->trans('settings.user_updated'));
        $this->redirect('/einstellungen/benutzer');
    }

    public function deleteUser(array $params = []): void
    {
        $this->validateCsrf();

        $currentUserId = (int)$this->session->get('user_id');
        if ((int)$params['id'] === $currentUserId) {
            $this->session->flash('error', $this->translator->trans('settings.cannot_delete_self'));
            $this->redirect('/einstellungen#benutzer');
            return;
        }

        $this->userRepository->delete((int)$params['id']);
        $this->session->flash('success', $this->translator->trans('settings.user_deleted'));
        $this->redirect('/einstellungen#benutzer');
    }

    public function createTreatmentType(array $params = []): void
    {
        $this->validateCsrf();

        $name = $this->sanitize($this->post('name', ''));
        if (empty($name)) {
            $this->session->flash('error', 'Name ist erforderlich.');
            $this->redirect('/einstellungen?tab=behandlungsarten');
            return;
        }

        $this->treatmentTypeRepository->create([
            'name'        => $name,
            'color'       => $this->post('color', '#4f7cff'),
            'price'       => $this->post('price', ''),
            'description' => $this->sanitize($this->post('description', '')),
            'active'      => (int)(bool)$this->post('active', 1),
            'sort_order'  => (int)$this->post('sort_order', 0),
        ]);

        $this->session->flash('success', 'Behandlungsart erstellt.');
        $this->redirect('/einstellungen?tab=behandlungsarten');
    }

    public function updateTreatmentType(array $params = []): void
    {
        $this->validateCsrf();

        $id   = (int)$params['id'];
        $name = $this->sanitize($this->post('name', ''));
        if (empty($name)) {
            $this->session->flash('error', 'Name ist erforderlich.');
            $this->redirect('/einstellungen?tab=behandlungsarten');
            return;
        }

        $this->treatmentTypeRepository->update($id, [
            'name'        => $name,
            'color'       => $this->post('color', '#4f7cff'),
            'price'       => $this->post('price', ''),
            'description' => $this->sanitize($this->post('description', '')),
            'active'      => (int)(bool)$this->post('active', 1),
            'sort_order'  => (int)$this->post('sort_order', 0),
        ]);

        $this->session->flash('success', 'Behandlungsart aktualisiert.');
        $this->redirect('/einstellungen?tab=behandlungsarten');
    }

    public function deleteTreatmentType(array $params = []): void
    {
        $this->validateCsrf();
        $this->treatmentTypeRepository->delete((int)$params['id']);
        $this->session->flash('success', 'Behandlungsart gelöscht.');
        $this->redirect('/einstellungen?tab=behandlungsarten');
    }

    public function treatmentTypesJson(array $params = []): void
    {
        $types = $this->treatmentTypeRepository->findActive();
        header('Content-Type: application/json');
        echo json_encode($types);
        exit;
    }

    // ── Hausaufgaben-Templates ─────────────────────────────────────────────

    public function createHomeworkTemplate(array $params = []): void
    {
        $this->validateCsrf();

        $title = $this->sanitize($this->post('title', ''));
        if (empty($title)) {
            $this->session->flash('error', 'Titel ist erforderlich.');
            $this->redirect('/einstellungen?tab=hausaufgaben');
            return;
        }

        $category = $this->post('category', 'sonstiges');
        $emojis = [
            'bewegung' => '🏃', 'dehnung' => '🤸', 'massage' => '💆',
            'kalt_warm' => '🌡️', 'medikamente' => '💊', 'fuetterung' => '🍽️',
            'beobachtung' => '👁️', 'sonstiges' => '📌',
        ];

        $this->homeworkRepository->createTemplate([
            'title'           => $title,
            'description'     => $this->sanitize($this->post('description', '')),
            'category'        => $category,
            'category_emoji'  => $emojis[$category] ?? '📌',
            'frequency'       => $this->post('frequency', 'daily'),
            'duration_value'  => $this->post('duration_value', 10),
            'duration_unit'   => $this->post('duration_unit', 'minutes'),
            'therapist_notes' => $this->sanitize($this->post('therapist_notes', '')),
        ]);

        $this->session->flash('success', 'Hausaufgaben-Template erstellt.');
        $this->redirect('/einstellungen?tab=hausaufgaben');
    }

    public function updateHomeworkTemplate(array $params = []): void
    {
        $this->validateCsrf();

        $id    = (int)$params['id'];
        $title = $this->sanitize($this->post('title', ''));
        if (empty($title)) {
            $this->session->flash('error', 'Titel ist erforderlich.');
            $this->redirect('/einstellungen?tab=hausaufgaben');
            return;
        }

        $category = $this->post('category', 'sonstiges');
        $emojis = [
            'bewegung' => '🏃', 'dehnung' => '🤸', 'massage' => '💆',
            'kalt_warm' => '🌡️', 'medikamente' => '💊', 'fuetterung' => '🍽️',
            'beobachtung' => '👁️', 'sonstiges' => '📌',
        ];

        $this->homeworkRepository->updateTemplate($id, [
            'title'           => $title,
            'description'     => $this->sanitize($this->post('description', '')),
            'category'        => $category,
            'category_emoji'  => $emojis[$category] ?? '📌',
            'frequency'       => $this->post('frequency', 'daily'),
            'duration_value'  => $this->post('duration_value', 10),
            'duration_unit'   => $this->post('duration_unit', 'minutes'),
            'therapist_notes' => $this->sanitize($this->post('therapist_notes', '')),
            'is_active'       => $this->post('is_active', 0),
        ]);

        $this->session->flash('success', 'Hausaufgaben-Template aktualisiert.');
        $this->redirect('/einstellungen?tab=hausaufgaben');
    }

    public function deleteHomeworkTemplate(array $params = []): void
    {
        $this->validateCsrf();
        $this->homeworkRepository->deleteTemplate((int)$params['id']);
        $this->session->flash('success', 'Hausaufgaben-Template gelöscht.');
        $this->redirect('/einstellungen?tab=hausaufgaben');
    }
}
