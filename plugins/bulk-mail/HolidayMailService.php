<?php
declare(strict_types=1);
namespace Plugins\BulkMail;

use App\Repositories\SettingsRepository;
use App\Services\MailService;
use App\Core\Database;
use PDO;

/**
 * Detects upcoming German holidays and sends personalised HTML greeting emails.
 * Holidays supported: Weihnachten, Silvester/Neujahr, Ostern, Pfingsten,
 *                     Tag der Arbeit, Valentinstag, Muttertag, Sommerpause.
 */
class HolidayMailService
{
    public function __construct(
        private readonly Database           $db,
        private readonly SettingsRepository $settings,
        private readonly MailService        $mail
    ) {}

    /* ── Public API ── */

    /** Returns all defined holidays with their next send date & enabled state. */
    public function getHolidays(int $year): array
    {
        $easter = $this->easterDate($year);
        return [
            'weihnachten' => [
                'label'  => 'Weihnachten',
                'date'   => "{$year}-12-24",
                'days_before' => 1,
            ],
            'neujahr' => [
                'label'  => 'Neujahr / Silvester',
                'date'   => "{$year}-12-31",
                'days_before' => 1,
            ],
            'ostern' => [
                'label'  => 'Ostern',
                'date'   => $easter->format('Y-m-d'),
                'days_before' => 1,
            ],
            'pfingsten' => [
                'label'  => 'Pfingsten',
                'date'   => $easter->modify('+49 days')->format('Y-m-d'),
                'days_before' => 1,
            ],
            'tag_der_arbeit' => [
                'label'  => 'Tag der Arbeit',
                'date'   => "{$year}-05-01",
                'days_before' => 0,
            ],
            'valentinstag' => [
                'label'  => 'Valentinstag',
                'date'   => "{$year}-02-14",
                'days_before' => 0,
            ],
            'muttertag' => [
                'label'  => 'Muttertag',
                'date'   => $this->mothersDay($year),
                'days_before' => 0,
            ],
            'halloween' => [
                'label'  => 'Halloween',
                'date'   => "{$year}-10-31",
                'days_before' => 0,
            ],
        ];
    }

    /**
     * Called by cron — checks today and sends any due greetings.
     * Returns array of results.
     */
    public function runDue(): array
    {
        $today   = date('Y-m-d');
        $year    = (int)date('Y');
        $results = [];

        foreach ($this->getHolidays($year) as $slug => $h) {
            $enabled = $this->settings->get("holiday_mail_{$slug}_enabled", '0');
            if ($enabled !== '1') continue;

            $sendDate = date('Y-m-d', strtotime($h['date'] . " -{$h['days_before']} days"));
            if ($sendDate !== $today) continue;

            $alreadySent = $this->settings->get("holiday_mail_{$slug}_last_sent", '');
            if ($alreadySent === $today) continue;

            $recipients = $this->resolveRecipients(
                $this->settings->get("holiday_mail_{$slug}_group", 'with_email')
            );

            $subject = $this->settings->get(
                "holiday_mail_{$slug}_subject",
                $this->defaultSubject($slug, $h['label'])
            );
            $bodyText = $this->settings->get(
                "holiday_mail_{$slug}_body",
                $this->defaultBody($slug)
            );
            $companyName = $this->settings->get('company_name', 'Tierphysio Praxis');

            $sent = 0; $failed = 0;
            foreach ($recipients as $r) {
                if (empty($r['email'])) continue;
                $lastName  = $r['last_name'] ?? '';
                $anrede    = $lastName !== '' ? 'Frau/Herr ' . $lastName : $r['name'];
                $patients  = $r['patient_names'] ?? '';
                $personal = str_replace(
                    ['{{name}}', '{{vorname}}', '{{praxis}}', '{{patient}}'],
                    [$anrede, $r['first_name'] ?? $r['name'], $companyName, $patients],
                    $bodyText
                );
                try {
                    $html = $this->buildHolidayHtml($slug, $h['label'], $personal, $companyName);
                    $ok   = $this->sendRaw($r['email'], $r['name'], $subject, $html, $personal);
                    $ok ? $sent++ : $failed++;
                } catch (\Throwable $e) {
                    $failed++;
                    error_log("[HolidayMail:{$slug}] " . $e->getMessage());
                }
            }

            $this->settings->set("holiday_mail_{$slug}_last_sent", $today);
            $results[$slug] = ['label' => $h['label'], 'sent' => $sent, 'failed' => $failed];
        }

        return $results;
    }

    /* ── HTML email builder ── */

    public function buildHolidayHtml(string $slug, string $label, string $body, string $company): string
    {
        $svg        = $this->getHolidaySvg($slug);
        $gradient   = $this->getGradient($slug);
        $bodyHtml   = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
        $labelHtml  = htmlspecialchars($label);
        $companyHtml= htmlspecialchars($company);

        return <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>{$labelHtml}</title>
</head>
<body style="margin:0;padding:0;background:#0f0f1a;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0f0f1a;padding:32px 16px;">
  <tr><td align="center">
    <table role="presentation" width="600" cellpadding="0" cellspacing="0"
           style="max-width:600px;width:100%;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);border-radius:16px;overflow:hidden;">

      <!-- HOLIDAY HEADER with SVG illustration -->
      <tr><td style="background:{$gradient};padding:40px 40px 32px;text-align:center;">
        <div style="margin-bottom:16px;">{$svg}</div>
        <h1 style="margin:0;color:#ffffff;font-size:1.6rem;font-weight:800;line-height:1.3;">{$labelHtml}</h1>
        <p style="margin:8px 0 0;color:rgba(255,255,255,0.6);font-size:0.82rem;letter-spacing:0.05em;text-transform:uppercase;">{$companyHtml}</p>
      </td></tr>

      <!-- BODY -->
      <tr><td style="padding:36px 40px;">
        <div style="color:rgba(255,255,255,0.85);font-size:0.97rem;line-height:1.85;">
          {$bodyHtml}
        </div>
      </td></tr>

      <!-- FOOTER -->
      <tr><td style="padding:16px 40px 28px;text-align:center;border-top:1px solid rgba(255,255,255,0.08);">
        <p style="margin:0;color:rgba(255,255,255,0.25);font-size:0.74rem;">{$companyHtml} &middot; Automatisch generierte Nachricht</p>
      </td></tr>

    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
    }

    /* ── SVG illustrations per holiday ── */

    private function getHolidaySvg(string $slug): string
    {
        return match($slug) {
            'weihnachten' => '<svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
  <!-- Star on top -->
  <polygon points="40,6 43,17 55,17 45,24 49,35 40,28 31,35 35,24 25,17 37,17" fill="#FFD700" opacity="0.95"/>
  <!-- Tree body -->
  <polygon points="40,20 10,65 70,65" fill="#2d7a3a"/>
  <polygon points="40,28 16,58 64,58" fill="#3a9e4a"/>
  <polygon points="40,36 22,55 58,55" fill="#4dc25f"/>
  <!-- Trunk -->
  <rect x="34" y="65" width="12" height="10" rx="2" fill="#8B4513"/>
  <!-- Ornaments -->
  <circle cx="30" cy="50" r="3.5" fill="#e53e3e"/>
  <circle cx="50" cy="50" r="3.5" fill="#e53e3e"/>
  <circle cx="40" cy="44" r="3.5" fill="#FFD700"/>
  <circle cx="25" cy="58" r="3" fill="#4299e1"/>
  <circle cx="55" cy="58" r="3" fill="#4299e1"/>
  <!-- Snow dots -->
  <circle cx="18" cy="22" r="2" fill="white" opacity="0.6"/>
  <circle cx="60" cy="15" r="1.5" fill="white" opacity="0.5"/>
  <circle cx="68" cy="30" r="2.5" fill="white" opacity="0.4"/>
  <circle cx="12" cy="40" r="1.5" fill="white" opacity="0.5"/>
</svg>',

            'neujahr' => '<svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
  <!-- Fireworks -->
  <circle cx="40" cy="35" r="3" fill="#FFD700"/>
  <!-- Rays -->
  <line x1="40" y1="10" x2="40" y2="22" stroke="#FFD700" stroke-width="2.5" stroke-linecap="round"/>
  <line x1="40" y1="48" x2="40" y2="60" stroke="#FFD700" stroke-width="2.5" stroke-linecap="round"/>
  <line x1="15" y1="35" x2="27" y2="35" stroke="#FFD700" stroke-width="2.5" stroke-linecap="round"/>
  <line x1="53" y1="35" x2="65" y2="35" stroke="#FFD700" stroke-width="2.5" stroke-linecap="round"/>
  <line x1="22" y1="17" x2="30" y2="25" stroke="#FFD700" stroke-width="2.5" stroke-linecap="round"/>
  <line x1="50" y1="45" x2="58" y2="53" stroke="#FFD700" stroke-width="2.5" stroke-linecap="round"/>
  <line x1="58" y1="17" x2="50" y2="25" stroke="#FFD700" stroke-width="2.5" stroke-linecap="round"/>
  <line x1="30" y1="45" x2="22" y2="53" stroke="#FFD700" stroke-width="2.5" stroke-linecap="round"/>
  <!-- Secondary sparks -->
  <circle cx="22" cy="17" r="2.5" fill="#e53e3e"/>
  <circle cx="58" cy="17" r="2.5" fill="#4299e1"/>
  <circle cx="22" cy="53" r="2.5" fill="#9f7aea"/>
  <circle cx="58" cy="53" r="2.5" fill="#48bb78"/>
  <!-- "2025" style bottom text replaced with champagne glass -->
  <path d="M32 62 Q40 55 48 62 L46 72 Q40 70 34 72 Z" fill="#C0C0C0" opacity="0.8"/>
  <line x1="40" y1="72" x2="40" y2="78" stroke="#C0C0C0" stroke-width="3" stroke-linecap="round"/>
  <line x1="34" y1="78" x2="46" y2="78" stroke="#C0C0C0" stroke-width="2.5" stroke-linecap="round"/>
  <circle cx="36" cy="65" r="1.5" fill="#FFD700"/>
  <circle cx="44" cy="64" r="1.5" fill="#FFD700"/>
</svg>',

            'ostern' => '<svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
  <!-- Eggs -->
  <ellipse cx="28" cy="45" rx="13" ry="17" fill="#f6ad55"/>
  <path d="M15 45 Q21 38 41 38" fill="none" stroke="#fff" stroke-width="2.5" opacity="0.6"/>
  <path d="M15 50 Q21 43 41 43" fill="none" stroke="#fff" stroke-width="2" opacity="0.4"/>
  <ellipse cx="52" cy="47" rx="12" ry="15" fill="#68d391"/>
  <path d="M40 44 Q50 37 64 40" fill="none" stroke="#fff" stroke-width="2.5" opacity="0.6"/>
  <!-- Bunny ears -->
  <ellipse cx="40" cy="16" rx="5" ry="11" fill="#f9c8d4"/>
  <ellipse cx="40" cy="16" rx="2.5" ry="8" fill="#f48fb1"/>
  <ellipse cx="52" cy="18" rx="4" ry="9" fill="#f9c8d4"/>
  <ellipse cx="52" cy="18" rx="2" ry="6.5" fill="#f48fb1"/>
  <!-- Bunny head -->
  <circle cx="46" cy="30" r="11" fill="#f5f0eb"/>
  <circle cx="42" cy="28" r="1.5" fill="#555"/>
  <circle cx="50" cy="28" r="1.5" fill="#555"/>
  <ellipse cx="46" cy="33" rx="3" ry="2" fill="#f48fb1"/>
  <!-- Chick -->
  <circle cx="22" cy="62" r="8" fill="#FFD700"/>
  <polygon points="22,57 20,54 24,54" fill="#f6ad55"/>
  <circle cx="20" cy="60" r="1.2" fill="#333"/>
</svg>',

            'pfingsten' => '<svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
  <!-- Dove / Holy Spirit dove -->
  <path d="M40 40 C30 30 15 35 18 48 C21 58 35 55 40 48 C45 55 59 58 62 48 C65 35 50 30 40 40Z" fill="white" opacity="0.9"/>
  <path d="M40 40 C38 32 42 25 40 18 C38 14 35 16 36 22 C34 18 30 20 33 25 C38 30 40 35 40 40Z" fill="white" opacity="0.85"/>
  <circle cx="37" cy="36" r="1.5" fill="#4299e1"/>
  <!-- Flame rays (Pfingsten = Pentecost fire) -->
  <path d="M55 20 C53 14 58 10 56 6 C60 10 63 16 58 22Z" fill="#f6ad55" opacity="0.9"/>
  <path d="M62 28 C58 23 62 18 60 14 C65 19 67 26 62 30Z" fill="#fc8181" opacity="0.8"/>
  <path d="M22 20 C24 14 19 10 21 6 C17 10 14 16 19 22Z" fill="#f6ad55" opacity="0.9"/>
  <path d="M16 28 C20 23 16 18 18 14 C13 19 11 26 16 30Z" fill="#fc8181" opacity="0.8"/>
  <!-- Sunburst below -->
  <circle cx="40" cy="68" r="10" fill="#FFD700" opacity="0.3"/>
  <circle cx="40" cy="68" r="6" fill="#FFD700" opacity="0.5"/>
  <circle cx="40" cy="68" r="3" fill="#FFD700" opacity="0.8"/>
</svg>',

            'valentinstag' => '<svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
  <!-- Large heart -->
  <path d="M40 65 C40 65 12 48 12 30 C12 20 20 14 28 16 C33 17 37 21 40 25 C43 21 47 17 52 16 C60 14 68 20 68 30 C68 48 40 65 40 65Z" fill="#e53e3e"/>
  <!-- Inner highlight -->
  <path d="M28 22 C24 22 20 26 20 31" stroke="rgba(255,255,255,0.4)" stroke-width="3" stroke-linecap="round"/>
  <!-- Small hearts -->
  <path d="M62 18 C62 18 56 13 62 8 C68 13 62 18 62 18Z" fill="#fc8181" opacity="0.8"/>
  <path d="M18 55 C18 55 13 51 18 47 C23 51 18 55 18 55Z" fill="#fc8181" opacity="0.7"/>
  <!-- Sparkles -->
  <line x1="68" y1="40" x2="68" y2="46" stroke="#FFD700" stroke-width="2" stroke-linecap="round"/>
  <line x1="65" y1="43" x2="71" y2="43" stroke="#FFD700" stroke-width="2" stroke-linecap="round"/>
  <line x1="12" y1="20" x2="12" y2="26" stroke="#FFD700" stroke-width="2" stroke-linecap="round"/>
  <line x1="9" y1="23" x2="15" y2="23" stroke="#FFD700" stroke-width="2" stroke-linecap="round"/>
</svg>',

            'muttertag' => '<svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
  <!-- Flower bouquet -->
  <!-- Stems -->
  <line x1="40" y1="75" x2="35" y2="50" stroke="#2d7a3a" stroke-width="2.5" stroke-linecap="round"/>
  <line x1="40" y1="75" x2="40" y2="45" stroke="#2d7a3a" stroke-width="2.5" stroke-linecap="round"/>
  <line x1="40" y1="75" x2="45" y2="50" stroke="#2d7a3a" stroke-width="2.5" stroke-linecap="round"/>
  <line x1="40" y1="75" x2="28" y2="52" stroke="#2d7a3a" stroke-width="2.5" stroke-linecap="round"/>
  <line x1="40" y1="75" x2="52" y2="52" stroke="#2d7a3a" stroke-width="2.5" stroke-linecap="round"/>
  <!-- Leaves -->
  <ellipse cx="30" cy="62" rx="7" ry="3" fill="#3a9e4a" transform="rotate(-30 30 62)"/>
  <ellipse cx="50" cy="62" rx="7" ry="3" fill="#3a9e4a" transform="rotate(30 50 62)"/>
  <!-- Pink rose -->
  <circle cx="40" cy="38" r="9" fill="#f48fb1"/>
  <circle cx="40" cy="38" r="5.5" fill="#f06292"/>
  <circle cx="40" cy="38" r="2.5" fill="#e91e63"/>
  <!-- Yellow flower left -->
  <circle cx="26" cy="44" r="6" fill="#FFD700"/>
  <circle cx="26" cy="44" r="3" fill="#f6ad55"/>
  <!-- Purple flower right -->
  <circle cx="54" cy="44" r="6" fill="#9f7aea"/>
  <circle cx="54" cy="44" r="3" fill="#805ad5"/>
  <!-- White flower top-left -->
  <circle cx="31" cy="32" r="5" fill="#fff" opacity="0.9"/>
  <circle cx="31" cy="32" r="2.5" fill="#FFD700"/>
  <!-- Ribbon at bottom -->
  <path d="M33 75 Q40 72 47 75 L45 79 Q40 77 35 79 Z" fill="#f48fb1" opacity="0.8"/>
</svg>',

            'tag_der_arbeit' => '<svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
  <!-- Gear -->
  <circle cx="40" cy="38" r="13" fill="none" stroke="#4299e1" stroke-width="5"/>
  <circle cx="40" cy="38" r="5" fill="#4299e1"/>
  <rect x="37" y="18" width="6" height="10" rx="2" fill="#4299e1"/>
  <rect x="37" y="48" width="6" height="10" rx="2" fill="#4299e1"/>
  <rect x="18" y="35" width="10" height="6" rx="2" fill="#4299e1"/>
  <rect x="48" y="35" width="10" height="6" rx="2" fill="#4299e1"/>
  <rect x="23" y="23" width="6" height="10" rx="2" fill="#4299e1" transform="rotate(45 26 28)"/>
  <rect x="49" y="23" width="6" height="10" rx="2" fill="#4299e1" transform="rotate(-45 52 28)"/>
  <rect x="23" y="42" width="6" height="10" rx="2" fill="#4299e1" transform="rotate(-45 26 47)"/>
  <rect x="49" y="42" width="6" height="10" rx="2" fill="#4299e1" transform="rotate(45 52 47)"/>
  <!-- Star of solidarity below -->
  <polygon points="40,62 42,68 48,68 43,72 45,78 40,74 35,78 37,72 32,68 38,68" fill="#FFD700" opacity="0.9"/>
</svg>',

            'halloween' => '<svg width="80" height="80" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg">
  <!-- Pumpkin body -->
  <ellipse cx="40" cy="52" rx="28" ry="22" fill="#f6803a"/>
  <ellipse cx="28" cy="52" rx="12" ry="20" fill="#e8692a" opacity="0.7"/>
  <ellipse cx="52" cy="52" rx="12" ry="20" fill="#e8692a" opacity="0.7"/>
  <!-- Stem -->
  <path d="M40 30 C40 24 44 20 48 18 C44 22 42 26 42 30Z" fill="#2d7a3a"/>
  <!-- Face eyes -->
  <polygon points="26,46 30,40 34,46" fill="#1a0a00"/>
  <polygon points="46,46 50,40 54,46" fill="#1a0a00"/>
  <!-- Face mouth -->
  <path d="M26 58 Q30 54 34 58 Q37 55 40 58 Q43 55 46 58 Q50 54 54 58" fill="#1a0a00"/>
  <!-- Moon -->
  <path d="M65 12 C60 12 56 16 56 21 C56 26 60 30 65 30 C62 28 60 25 60 21 C60 17 62 14 65 12Z" fill="#FFD700" opacity="0.9"/>
  <!-- Stars -->
  <circle cx="18" cy="18" r="2" fill="white" opacity="0.7"/>
  <circle cx="10" cy="32" r="1.5" fill="white" opacity="0.5"/>
  <circle cx="72" cy="38" r="1.5" fill="white" opacity="0.6"/>
</svg>',

            default => '<svg width="80" height="80" viewBox="0 0 80 80" fill="none"><circle cx="40" cy="40" r="30" fill="none" stroke="#4299e1" stroke-width="4"/><path d="M28 40 L36 48 L52 32" stroke="#4299e1" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        };
    }

    private function getGradient(string $slug): string
    {
        return match($slug) {
            'weihnachten'    => 'linear-gradient(135deg,#1a472a,#2d6a3f)',
            'neujahr'        => 'linear-gradient(135deg,#1a1a3e,#2d2d6e)',
            'ostern'         => 'linear-gradient(135deg,#2d5a1b,#4a8c2d)',
            'pfingsten'      => 'linear-gradient(135deg,#1a2a4a,#2d4a7a)',
            'valentinstag'   => 'linear-gradient(135deg,#5a1a2a,#8c2d40)',
            'muttertag'      => 'linear-gradient(135deg,#4a1a4a,#7a2d7a)',
            'tag_der_arbeit' => 'linear-gradient(135deg,#1a2a4a,#1a4a6a)',
            'halloween'      => 'linear-gradient(135deg,#2a1a0a,#4a2d0a)',
            default          => 'linear-gradient(135deg,rgba(79,124,255,0.35),rgba(139,92,246,0.35))',
        };
    }

    public function defaultSubject(string $slug, string $label): string
    {
        return match($slug) {
            'weihnachten'    => 'Frohe Weihnachten von {{praxis}}!',
            'neujahr'        => 'Einen guten Rutsch ins neue Jahr!',
            'ostern'         => 'Frohe Ostern von {{praxis}}!',
            'pfingsten'      => 'Schöne Pfingsttage wünscht {{praxis}}!',
            'valentinstag'   => 'Alles Liebe zum Valentinstag!',
            'muttertag'      => 'Alles Gute zum Muttertag!',
            'tag_der_arbeit' => 'Schönen Feiertag wünscht {{praxis}}!',
            'halloween'      => 'Happy Halloween! 🎃',
            default          => "Schöne {$label}!",
        };
    }

    public function defaultBody(string $slug): string
    {
        return match($slug) {
            'weihnachten' =>
                "Sehr geehrte/r {{name}},\n\nich wünsche Ihnen und Ihren Liebsten von Herzen ein besinnliches und frohes Weihnachtsfest.\n\nBesonders {{patient}} darf sich sicher über etwas ganz Besonderes freuen – mögen die Festtage Ihnen alle Ruhe, Wärme und schöne gemeinsame Momente schenken.\n\nHerzliche Weihnachtsgrüße,\n{{praxis}}",
            'neujahr' =>
                "Sehr geehrte/r {{name}},\n\nich wünsche Ihnen und {{patient}} einen wunderschönen Jahreswechsel und ein gesundes, glückliches neues Jahr!\n\nAuf ein tolles gemeinsames Jahr,\n{{praxis}}",
            'ostern' =>
                "Sehr geehrte/r {{name}},\n\nich wünsche Ihnen und Ihrer Familie ein fröhliches Osterfest voller bunter Überraschungen!\n\nGenießen Sie die freien Tage in der Frühlingssonne – am besten gemeinsam mit {{patient}}.\n\nHerzliche Ostergrüße,\n{{praxis}}",
            'pfingsten' =>
                "Sehr geehrte/r {{name}},\n\nich wünsche Ihnen schöne und erholsame Pfingsttage!\n\nNutzen Sie das verlängerte Wochenende für entspannte Ausflüge mit {{patient}} und genießen Sie die warme Jahreszeit.\n\nHerzliche Grüße,\n{{praxis}}",
            'valentinstag' =>
                "Sehr geehrte/r {{name}},\n\nam Valentinstag denke ich auch an die wunderbare Verbindung zwischen Ihnen und {{patient}} – diese besondere Liebe ist etwas ganz Einzigartiges!\n\nAlles Liebe zum Valentinstag,\n{{praxis}}",
            'muttertag' =>
                "Sehr geehrte/r {{name}},\n\nheute möchte ich Ihnen ganz persönlich danken: Die Fürsorge, die Sie {{patient}} täglich entgegenbringen, ist wunderschön und von Herzen!\n\nAlles Gute zum Muttertag,\n{{praxis}}",
            'tag_der_arbeit' =>
                "Sehr geehrte/r {{name}},\n\nich wünsche Ihnen einen entspannten und erholsamen Feiertag – Sie haben sich eine Pause mehr als verdient!\n\nGenießen Sie den Tag mit Ihrer Familie und natürlich mit {{patient}}.\n\nHerzliche Grüße,\n{{praxis}}",
            'halloween' =>
                "Sehr geehrte/r {{name}},\n\nHappy Halloween! 🎃\n\nIch hoffe, {{patient}} lässt sich von den Kostümen und dem Trubel heute Abend nicht allzu sehr erschrecken!\n\nGruselig-herzliche Grüße,\n{{praxis}}",
            default => "Sehr geehrte/r {{name}},\n\nherzliche Grüße von {{praxis}}!",
        };
    }

    /* ── Helpers ── */

    public function resolveRecipients(string $group): array
    {
        $sql = "SELECT o.id AS owner_id,
                       CONCAT(o.first_name,' ',o.last_name) AS name,
                       o.first_name, o.last_name, o.email,
                       (
                           SELECT GROUP_CONCAT(p2.name ORDER BY p2.name SEPARATOR ', ')
                           FROM patients p2
                           WHERE p2.owner_id = o.id
                           AND (p2.status IS NULL OR p2.status != 'archiviert')
                       ) AS patient_names
                FROM owners o WHERE o.email IS NOT NULL AND o.email != ''";
        if ($group === 'active') {
            $sql .= " AND EXISTS (SELECT 1 FROM patients p WHERE p.owner_id=o.id AND p.status='aktiv')";
        }
        $sql .= " ORDER BY o.last_name, o.first_name";
        try { return $this->db->fetchAll($sql); } catch (\Throwable) { return []; }
    }

    public function sendRaw(string $to, string $name, string $subject, string $html, string $text): bool
    {
        try {
            $pm = new \PHPMailer\PHPMailer\PHPMailer(true);
            $pm->isSMTP();
            $pm->Host        = $this->settings->get('smtp_host','localhost');
            $pm->Port        = (int)$this->settings->get('smtp_port','587');
            $pm->Username    = $this->settings->get('smtp_username','');
            $pm->Password    = $this->settings->get('smtp_password','');
            $pm->SMTPAuth    = !empty($pm->Username);
            $pm->Timeout     = 10;
            $pm->SMTPKeepAlive = true;
            $enc = $this->settings->get('smtp_encryption','tls');
            if ($enc==='ssl')  $pm->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            elseif ($enc==='none') { $pm->SMTPSecure=''; $pm->SMTPAutoTLS=false; }
            else $pm->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $pm->setFrom($this->settings->get('mail_from_address','noreply@tierphysio.local'),
                         $this->settings->get('mail_from_name','Tierphysio Manager'));
            $pm->CharSet = \PHPMailer\PHPMailer\PHPMailer::CHARSET_UTF8;
            $pm->addAddress($to, $name);
            $pm->Subject = $subject;
            $pm->isHTML(true);
            $pm->Body    = $html;
            $pm->AltBody = $text;
            return $pm->send();
        } catch (\Throwable $e) {
            error_log("[HolidayMail::sendRaw] {$e->getMessage()}");
            return false;
        }
    }

    private function easterDate(int $year): \DateTime
    {
        $a = $year % 19; $b = intdiv($year,100); $c = $year % 100;
        $d = intdiv($b,4); $e = $b % 4; $f = intdiv($b+8,25);
        $g = intdiv($b-$f+1,3); $h = (19*$a+$b-$d-$g+15) % 30;
        $i = intdiv($c,4); $k = $c % 4;
        $l = (32+2*$e+2*$i-$h-$k) % 7;
        $m = intdiv($a+11*$h+22*$l,451);
        $month = intdiv($h+$l-7*$m+114,31);
        $day   = (($h+$l-7*$m+114) % 31)+1;
        return new \DateTime("{$year}-{$month}-{$day}");
    }

    private function mothersDay(int $year): string
    {
        $may1  = new \DateTime("{$year}-05-01");
        $dow   = (int)$may1->format('N');
        $daysToSunday = $dow === 7 ? 7 : (7 - $dow);
        $firstSunday  = (clone $may1)->modify("+{$daysToSunday} days");
        $secondSunday = (clone $firstSunday)->modify('+7 days');
        return $secondSunday->format('Y-m-d');
    }
}