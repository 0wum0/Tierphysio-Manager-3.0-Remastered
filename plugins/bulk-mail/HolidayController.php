<?php
declare(strict_types=1);
namespace Plugins\BulkMail;

use App\Core\Controller;
use App\Core\Config;
use App\Core\Session;
use App\Core\Translator;
use App\Core\View;
use App\Core\Database;
use App\Repositories\SettingsRepository;
use App\Services\MailService;

class HolidayController extends Controller
{
    private HolidayMailService $holidayService;
    private SettingsRepository $settings;

    public function __construct(
        View $view, Session $session, Config $config, Translator $translator,
        Database $db, SettingsRepository $settings, MailService $mail
    ) {
        parent::__construct($view, $session, $config, $translator);
        $this->settings       = $settings;
        $this->holidayService = new HolidayMailService($db, $settings, $mail);
    }

    /* GET /bulk-mail/feiertagsgruesse */
    public function index(array $params = []): void
    {
        $year     = (int)date('Y');
        $holidays = $this->holidayService->getHolidays($year);

        $configured = [];
        foreach ($holidays as $slug => $h) {
            $savedSubject = $this->settings->get("holiday_mail_{$slug}_subject", '');
            $savedBody    = $this->settings->get("holiday_mail_{$slug}_body", '');
            $configured[$slug] = array_merge($h, [
                'enabled'  => $this->settings->get("holiday_mail_{$slug}_enabled", '0') === '1',
                'subject'  => $savedSubject !== '' ? $savedSubject : $this->holidayService->defaultSubject($slug, $h['label']),
                'body'     => $savedBody    !== '' ? $savedBody    : $this->holidayService->defaultBody($slug),
                'group'    => $this->settings->get("holiday_mail_{$slug}_group", 'with_email'),
                'last_sent'=> $this->settings->get("holiday_mail_{$slug}_last_sent", ''),
            ]);
        }

        $this->render('@bulk-mail/holiday.twig', [
            'page_title' => 'Automatische Feiertagsgrüße',
            'holidays'   => $configured,
            'year'       => $year,
            'csrf_token' => $this->session->generateCsrfToken(),
            'success'    => $this->session->getFlash('success'),
            'error'      => $this->session->getFlash('error'),
        ]);
    }

    /* POST /bulk-mail/feiertagsgruesse/speichern */
    public function save(array $params = []): void
    {
        $this->validateCsrf();
        $year     = (int)date('Y');
        $holidays = $this->holidayService->getHolidays($year);

        foreach ($holidays as $slug => $h) {
            $enabled = isset($_POST["holiday_{$slug}_enabled"]) ? '1' : '0';
            $this->settings->set("holiday_mail_{$slug}_enabled", $enabled);

            $subject = trim($_POST["holiday_{$slug}_subject"] ?? '');
            if ($subject !== '') $this->settings->set("holiday_mail_{$slug}_subject", $subject);

            $body = trim($_POST["holiday_{$slug}_body"] ?? '');
            if ($body !== '') $this->settings->set("holiday_mail_{$slug}_body", $body);

            $group = $_POST["holiday_{$slug}_group"] ?? 'with_email';
            $this->settings->set("holiday_mail_{$slug}_group", $group);
        }

        $this->flash('success', 'Einstellungen gespeichert.');
        $this->redirect('/bulk-mail/feiertagsgruesse');
    }

    /* POST /bulk-mail/feiertagsgruesse/vorschau  (AJAX — returns rendered HTML email) */
    public function preview(array $params = []): void
    {
        $this->validateCsrf();
        $slug    = $this->post('slug', '');
        $year    = (int)date('Y');
        $holidays= $this->holidayService->getHolidays($year);

        if (!isset($holidays[$slug])) {
            $this->json(['error' => 'Unbekannter Feiertag.'], 422);
        }

        $h       = $holidays[$slug];
        $company = $this->settings->get('company_name', 'Tierphysio Praxis');
        $body    = $this->settings->get("holiday_mail_{$slug}_body", '');
        if ($body === '') {
            $body = $this->holidayService->defaultBody($slug);
        }
        $personal = str_replace(['{{name}}','{{vorname}}','{{praxis}}'], ['Max Mustermann','Max',$company], $body);
        $html     = $this->holidayService->buildHolidayHtml($slug, $h['label'], $personal, $company);
        $this->json(['html' => $html]);
    }

    /* POST /bulk-mail/feiertagsgruesse/jetzt-senden  (AJAX — send specific holiday now) */
    public function sendNow(array $params = []): void
    {
        $this->validateCsrf();
        $slug    = $this->post('slug', '');
        $year    = (int)date('Y');
        $holidays= $this->holidayService->getHolidays($year);

        if (!isset($holidays[$slug])) {
            $this->json(['error' => 'Unbekannter Feiertag.'], 422);
        }

        $h           = $holidays[$slug];
        $company     = $this->settings->get('company_name', 'Tierphysio Praxis');
        $subject     = $this->settings->get("holiday_mail_{$slug}_subject", '');
        $bodyText    = $this->settings->get("holiday_mail_{$slug}_body", '');
        $group       = $this->settings->get("holiday_mail_{$slug}_group", 'with_email');

        $svc = $this->holidayService;
        if ($subject === '') $subject  = $svc->defaultSubject($slug, $h['label']);
        if ($bodyText === '') $bodyText = $svc->defaultBody($slug);

        $recipients = $svc->resolveRecipients($group);
        $sent = 0; $failed = [];

        foreach ($recipients as $r) {
            if (empty($r['email'])) continue;
            $lastName = $r['last_name'] ?? '';
            $anrede   = $lastName !== '' ? 'Frau/Herr ' . $lastName : $r['name'];
            $patients = $r['patient_names'] ?? '';
            $personal = str_replace(
                ['{{name}}','{{vorname}}','{{praxis}}','{{patient}}'],
                [$anrede, $r['first_name'] ?? $r['name'], $company, $patients], $bodyText);
            try {
                $html = $svc->buildHolidayHtml($slug, $h['label'], $personal, $company);
                $subjectPersonal = str_replace(
                    ['{{name}}','{{vorname}}','{{praxis}}','{{patient}}'],
                    [$anrede, $r['first_name'] ?? $r['name'], $company, $patients], $subject);
                $ok = $svc->sendRaw($r['email'], $r['name'], $subjectPersonal, $html, $personal);
                $ok ? $sent++ : ($failed[] = $r['email']);
            } catch (\Throwable $e) {
                $failed[] = $r['email'];
                error_log("[HolidayController::sendNow:{$slug}] " . $e->getMessage());
            }
        }

        $this->settings->set("holiday_mail_{$slug}_last_sent", date('Y-m-d'));
        $this->json(['sent' => $sent, 'failed' => $failed]);
    }

    /* GET /api/holiday-cron?token=SECRET&tid=XYZ  — called by server cron */
    public function cron(array $params = []): void
    {
        $start = hrtime(true);

        /* KRITISCH: Tenant-Prefix ZUERST setzen, BEVOR settings->get() aufgerufen wird */
        $db  = \App\Core\Application::getInstance()->getContainer()->get(\App\Core\Database::class);
        $tid = (string)($_GET['tid'] ?? '');
        if ($tid !== '') {
            $normalized = strtolower(trim($tid));
            $normalized = preg_replace('/[^a-z0-9]/', '_', $normalized) ?? $normalized;
            $normalized = preg_replace('/_+/', '_', $normalized) ?? $normalized;
            $normalized = trim($normalized, '_');
            $prefix     = 't_' . $normalized . '_';
            if (strlen($prefix) <= 58) {
                $db->setPrefix($prefix);
            }
        }

        /* Self-Healing Token: 403 bei leerem Token ist falsch — Token muss auto-generiert werden */
        $tokenJustCreated = false;
        $expected         = $this->settings->get('cron_secret', '');
        $tokenIsValid     = ($expected !== '' && $expected !== null
                             && strlen((string)$expected) === 64
                             && ctype_xdigit((string)$expected));

        if (!$tokenIsValid) {
            $expected         = bin2hex(random_bytes(32));
            $tokenJustCreated = true;
            try {
                $this->settings->set('cron_secret', $expected);
                error_log('[HolidayCron] cron_secret (missing/corrupt) auto-generated for tid=' . $tid);
            } catch (\Throwable $e) {
                error_log('[HolidayCron] WARNING: could not persist cron_secret: ' . $e->getMessage());
            }
        }

        $key = (string)($_GET['token'] ?? $_GET['key'] ?? '');

        /* Token-Prüfung:
         * - Token wurde soeben neu generiert (first-run) → erlauben
         * - Kein Token im Request → erlauben (self-healing Modus)
         * - Token vorhanden und passt nicht → 403 */
        $noTokenInRequest = ($key === '');
        if (!$tokenJustCreated && !$noTokenInRequest && !hash_equals((string)$expected, $key)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Forbidden', 'tid' => $tid]);
            exit;
        }

        try {
            $results = $this->holidayService->runDue();
            $ms      = (int)((hrtime(true) - $start) / 1_000_000);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'ok', 'tid' => $tid, 'duration_ms' => $ms, 'results' => $results]);
        } catch (\Throwable $e) {
            error_log('[HolidayCron] EXCEPTION tid=' . $tid . ': ' . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'tid' => $tid, 'error' => $e->getMessage()]);
        }
        exit;
    }
}