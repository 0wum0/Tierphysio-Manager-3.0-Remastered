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
            $configured[$slug] = array_merge($h, [
                'enabled'  => $this->settings->get("holiday_mail_{$slug}_enabled", '0') === '1',
                'subject'  => $this->settings->get("holiday_mail_{$slug}_subject", ''),
                'body'     => $this->settings->get("holiday_mail_{$slug}_body", ''),
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
            $svc  = $this->holidayService;
            $ref  = new \ReflectionMethod($svc, 'defaultBody');
            $ref->setAccessible(true);
            $body = $ref->invoke($svc, $slug);
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

        /* Use defaults if not yet saved */
        $svc = $this->holidayService;
        if ($subject === '') {
            $r = new \ReflectionMethod($svc, 'defaultSubject');
            $r->setAccessible(true);
            $subject = $r->invoke($svc, $slug, $h['label']);
        }
        if ($bodyText === '') {
            $r = new \ReflectionMethod($svc, 'defaultBody');
            $r->setAccessible(true);
            $bodyText = $r->invoke($svc, $slug);
        }

        $rRecip = new \ReflectionMethod($svc, 'resolveRecipients');
        $rRecip->setAccessible(true);
        $recipients = $rRecip->invoke($svc, $group);

        $sent = 0; $failed = [];
        $rSend = new \ReflectionMethod($svc, 'sendRaw');
        $rSend->setAccessible(true);

        foreach ($recipients as $r) {
            if (empty($r['email'])) continue;
            $personal = str_replace(['{{name}}','{{vorname}}','{{praxis}}'],
                [$r['name'], $r['first_name'] ?? $r['name'], $company], $bodyText);
            try {
                $html = $svc->buildHolidayHtml($slug, $h['label'], $personal, $company);
                $subjectPersonal = str_replace(['{{name}}','{{vorname}}','{{praxis}}'],
                    [$r['name'], $r['first_name'] ?? $r['name'], $company], $subject);
                $ok = $rSend->invoke($svc, $r['email'], $r['name'], $subjectPersonal, $html, $personal);
                $ok ? $sent++ : ($failed[] = $r['email']);
            } catch (\Throwable $e) {
                $failed[] = $r['email'];
                error_log("[HolidayController::sendNow:{$slug}] " . $e->getMessage());
            }
        }

        $this->settings->set("holiday_mail_{$slug}_last_sent", date('Y-m-d'));
        $this->json(['sent' => $sent, 'failed' => $failed]);
    }

    /* GET /api/holiday-cron?key=SECRET  — called by server cron */
    public function cron(array $params = []): void
    {
        $key      = $_GET['key'] ?? '';
        $expected = $this->settings->get('cron_secret', '');
        if ($expected === '' || $key !== $expected) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }
        $results = $this->holidayService->runDue();
        $this->json(['status' => 'ok', 'results' => $results]);
    }
}