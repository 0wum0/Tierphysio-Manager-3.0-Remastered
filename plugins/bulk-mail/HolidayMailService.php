<?php
declare(strict_types=1);
namespace Plugins\BulkMail;

use App\Repositories\SettingsRepository;
use App\Services\MailService;
use App\Core\Database;

class HolidayMailService
{
    public function __construct(
        private readonly Database           $db,
        private readonly SettingsRepository $settings,
        private readonly MailService        $mail
    ) {}

    public function getHolidays(int $year): array
    {
        $easter = $this->easterDate($year);
        $easterClone = clone $easter;
        return [
            'weihnachten'    => ['label' => 'Weihnachten',          'date' => "{$year}-12-24",                                   'days_before' => 1],
            'neujahr'        => ['label' => 'Neujahr / Silvester',  'date' => "{$year}-12-31",                                   'days_before' => 1],
            'advent'         => ['label' => '1. Advent',            'date' => $this->firstAdvent($year),                         'days_before' => 0],
            'nikolaus'       => ['label' => 'Nikolaus',             'date' => "{$year}-12-06",                                   'days_before' => 0],
            'ostern'         => ['label' => 'Ostern',               'date' => $easter->format('Y-m-d'),                          'days_before' => 1],
            'pfingsten'      => ['label' => 'Pfingsten',            'date' => $easterClone->modify('+49 days')->format('Y-m-d'), 'days_before' => 1],
            'valentinstag'   => ['label' => 'Valentinstag',         'date' => "{$year}-02-14",                                   'days_before' => 0],
            'muttertag'      => ['label' => 'Muttertag',            'date' => $this->mothersDay($year),                          'days_before' => 0],
            'vatertag'       => ['label' => 'Vatertag',             'date' => $this->fathersDay($year),                          'days_before' => 0],
            'tag_der_arbeit' => ['label' => 'Tag der Arbeit',       'date' => "{$year}-05-01",                                   'days_before' => 0],
            'sommergruss'    => ['label' => 'Sommergruss',          'date' => "{$year}-07-15",                                   'days_before' => 0],
            'halloween'      => ['label' => 'Halloween',            'date' => "{$year}-10-31",                                   'days_before' => 0],
        ];
    }

    public function runDue(): array
    {
        $today = date('Y-m-d'); $year = (int)date('Y'); $results = [];
        foreach ($this->getHolidays($year) as $slug => $h) {
            if ($this->settings->get("holiday_mail_{$slug}_enabled",'0') !== '1') continue;
            $sendDate = date('Y-m-d', strtotime($h['date']." -{$h['days_before']} days"));
            if ($sendDate !== $today) continue;
            if ($this->settings->get("holiday_mail_{$slug}_last_sent",'') === $today) continue;
            $recipients  = $this->resolveRecipients($this->settings->get("holiday_mail_{$slug}_group",'with_email'));
            $subject     = $this->settings->get("holiday_mail_{$slug}_subject",'') ?: $this->defaultSubject($slug,$h['label']);
            $bodyText    = $this->settings->get("holiday_mail_{$slug}_body",'')    ?: $this->defaultBody($slug);
            $companyName = $this->settings->get('company_name','Tierphysio Praxis');
            $sent = 0; $failed = 0;
            foreach ($recipients as $r) {
                if (empty($r['email'])) continue;
                $anrede  = ($r['last_name']??'') !== '' ? 'Frau/Herr '.$r['last_name'] : $r['name'];
                $patients= $r['patient_names'] ?? '';
                $ph = ['{{name}}','{{vorname}}','{{praxis}}','{{patient}}'];
                $rv = [$anrede, $r['first_name']??$r['name'], $companyName, $patients];
                $personal = str_replace($ph,$rv,$bodyText);
                $subjectP = str_replace($ph,$rv,$subject);
                try {
                    $html = $this->buildHolidayHtml($slug,$h['label'],$personal,$companyName);
                    $ok   = $this->sendRaw($r['email'],$r['name'],$subjectP,$html,$personal);
                    $ok ? $sent++ : $failed++;
                } catch (\Throwable $e) { $failed++; error_log("[HolidayMail:{$slug}] ".$e->getMessage()); }
            }
            $this->settings->set("holiday_mail_{$slug}_last_sent",$today);
            $results[$slug] = ['label'=>$h['label'],'sent'=>$sent,'failed'=>$failed];
        }
        return $results;
    }
    public function buildHolidayHtml(string $slug, string $label, string $body, string $company): string
    {
        $headerSvg   = $this->getHolidaySvg($slug);
        $bottomSvg   = $this->getBottomIllustration($slug);
        $gradient    = $this->getGradient($slug);
        $bodyHtml    = nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8'));
        $labelHtml   = htmlspecialchars($label);
        $companyHtml = htmlspecialchars($company);
        return <<<HTML
<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>{$labelHtml}</title></head>
<body style="margin:0;padding:0;background:#0f0f1a;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0f0f1a;padding:32px 16px;">
<tr><td align="center">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background:rgba(255,255,255,0.06);border:1px solid rgba(255,255,255,0.12);border-radius:16px;overflow:hidden;">
<tr><td style="background:{$gradient};padding:40px 40px 32px;text-align:center;">
  <div style="margin-bottom:16px;">{$headerSvg}</div>
  <h1 style="margin:0;color:#ffffff;font-size:1.6rem;font-weight:800;line-height:1.3;">{$labelHtml}</h1>
  <p style="margin:8px 0 0;color:rgba(255,255,255,0.6);font-size:0.82rem;letter-spacing:0.05em;text-transform:uppercase;">{$companyHtml}</p>
</td></tr>
<tr><td style="padding:36px 40px 24px;">
  <div style="color:rgba(255,255,255,0.85);font-size:0.97rem;line-height:1.85;">{$bodyHtml}</div>
</td></tr>
<tr><td style="padding:0 40px 28px;text-align:center;">{$bottomSvg}</td></tr>
<tr><td style="padding:16px 40px 24px;text-align:center;border-top:1px solid rgba(255,255,255,0.08);">
  <p style="margin:0;color:rgba(255,255,255,0.25);font-size:0.74rem;">{$companyHtml} &middot; Automatisch generierte Nachricht</p>
</td></tr>
</table></td></tr></table></body></html>
HTML;
    }
    private function getHolidaySvg(string $slug): string
    {
        return match($slug) {
            'weihnachten'    => '<svg width="64" height="64" viewBox="0 0 80 80" fill="none"><polygon points="40,6 43,17 55,17 45,24 49,35 40,28 31,35 35,24 25,17 37,17" fill="#FFD700"/><polygon points="40,20 10,65 70,65" fill="#2d7a3a"/><polygon points="40,28 16,58 64,58" fill="#3a9e4a"/><polygon points="40,36 22,55 58,55" fill="#4dc25f"/><rect x="34" y="65" width="12" height="10" rx="2" fill="#8B4513"/><circle cx="30" cy="50" r="3.5" fill="#e53e3e"/><circle cx="50" cy="50" r="3.5" fill="#e53e3e"/><circle cx="40" cy="44" r="3.5" fill="#FFD700"/></svg>',
            'neujahr'        => '<svg width="64" height="64" viewBox="0 0 80 80" fill="none"><circle cx="40" cy="35" r="3" fill="#FFD700"/><line x1="40" y1="10" x2="40" y2="22" stroke="#FFD700" stroke-width="2.5" stroke-linecap="round"/><line x1="40" y1="48" x2="40" y2="60" stroke="#FFD700" stroke-width="2.5" stroke-linecap="round"/><line x1="15" y1="35" x2="27" y2="35" stroke="#FFD700" stroke-width="2.5" stroke-linecap="round"/><line x1="53" y1="35" x2="65" y2="35" stroke="#FFD700" stroke-width="2.5" stroke-linecap="round"/></svg>',
            'advent'         => '<svg width="64" height="64" viewBox="0 0 80 80" fill="none"><rect x="30" y="50" width="20" height="22" rx="3" fill="#8B4513"/><ellipse cx="40" cy="50" rx="18" ry="6" fill="#2d7a3a"/><rect x="38" y="20" width="4" height="30" rx="2" fill="#D2691E"/><ellipse cx="40" cy="18" rx="5" ry="8" fill="#FFA500" opacity="0.9"/><ellipse cx="40" cy="16" rx="3" ry="5" fill="#FFD700"/><circle cx="40" cy="12" r="2.5" fill="#fff" opacity="0.9"/></svg>',
            'nikolaus'       => '<svg width="64" height="64" viewBox="0 0 80 80" fill="none"><circle cx="40" cy="32" r="14" fill="#f5e6d3"/><rect x="26" y="44" width="28" height="24" rx="4" fill="#c0392b"/><rect x="36" y="6" width="8" height="16" rx="4" fill="#c0392b"/><path d="M22 18 Q40 8 58 18" fill="#c0392b"/><ellipse cx="40" cy="8" rx="10" ry="4" fill="#c0392b"/><circle cx="36" cy="30" r="2" fill="#555"/><circle cx="44" cy="30" r="2" fill="#555"/><path d="M36 38 Q40 42 44 38" stroke="#c0392b" stroke-width="2" fill="none" stroke-linecap="round"/><rect x="32" y="12" width="16" height="5" rx="2" fill="#fff" opacity="0.9"/><rect x="26" y="44" width="28" height="6" rx="0" fill="#fff" opacity="0.8"/></svg>',
            'ostern'         => '<svg width="64" height="64" viewBox="0 0 80 80" fill="none"><ellipse cx="28" cy="45" rx="13" ry="17" fill="#f6ad55"/><ellipse cx="52" cy="47" rx="12" ry="15" fill="#68d391"/><ellipse cx="40" cy="16" rx="5" ry="11" fill="#f9c8d4"/><ellipse cx="52" cy="18" rx="4" ry="9" fill="#f9c8d4"/><circle cx="46" cy="30" r="11" fill="#f5f0eb"/><circle cx="42" cy="28" r="1.5" fill="#555"/><circle cx="50" cy="28" r="1.5" fill="#555"/><ellipse cx="46" cy="33" rx="3" ry="2" fill="#f48fb1"/></svg>',
            'pfingsten'      => '<svg width="64" height="64" viewBox="0 0 80 80" fill="none"><path d="M40 40 C30 30 15 35 18 48 C21 58 35 55 40 48 C45 55 59 58 62 48 C65 35 50 30 40 40Z" fill="white" opacity="0.9"/><path d="M40 40 C38 32 42 25 40 18 C38 14 35 16 36 22 C34 18 30 20 33 25 C38 30 40 35 40 40Z" fill="white" opacity="0.85"/><circle cx="37" cy="36" r="1.5" fill="#4299e1"/><path d="M55 20 C53 14 58 10 56 6 C60 10 63 16 58 22Z" fill="#f6ad55" opacity="0.9"/><path d="M22 20 C24 14 19 10 21 6 C17 10 14 16 19 22Z" fill="#f6ad55" opacity="0.9"/></svg>',
            'valentinstag'   => '<svg width="64" height="64" viewBox="0 0 80 80" fill="none"><path d="M40 65 C40 65 12 48 12 30 C12 20 20 14 28 16 C33 17 37 21 40 25 C43 21 47 17 52 16 C60 14 68 20 68 30 C68 48 40 65 40 65Z" fill="#e53e3e"/><path d="M28 22 C24 22 20 26 20 31" stroke="rgba(255,255,255,0.4)" stroke-width="3" stroke-linecap="round"/></svg>',
            'muttertag'      => '<svg width="64" height="64" viewBox="0 0 80 80" fill="none"><line x1="40" y1="75" x2="35" y2="50" stroke="#2d7a3a" stroke-width="2.5" stroke-linecap="round"/><line x1="40" y1="75" x2="40" y2="45" stroke="#2d7a3a" stroke-width="2.5" stroke-linecap="round"/><line x1="40" y1="75" x2="45" y2="50" stroke="#2d7a3a" stroke-width="2.5" stroke-linecap="round"/><circle cx="40" cy="38" r="9" fill="#f48fb1"/><circle cx="40" cy="38" r="5.5" fill="#f06292"/><circle cx="26" cy="44" r="6" fill="#FFD700"/><circle cx="54" cy="44" r="6" fill="#9f7aea"/></svg>',
            'vatertag'       => '<svg width="64" height="64" viewBox="0 0 80 80" fill="none"><rect x="20" y="40" width="40" height="28" rx="5" fill="#4a90d9"/><rect x="28" y="30" width="24" height="18" rx="12" fill="#f5d5a0"/><circle cx="36" cy="38" r="2" fill="#555"/><circle cx="44" cy="38" r="2" fill="#555"/><path d="M36 44 Q40 48 44 44" stroke="#c0392b" stroke-width="1.5" fill="none" stroke-linecap="round"/><rect x="15" y="36" width="50" height="8" rx="4" fill="#2c3e50"/><line x1="40" y1="36" x2="40" y2="20" stroke="#c0392b" stroke-width="3"/><circle cx="40" cy="18" r="5" fill="#f6ad55"/></svg>',
            'tag_der_arbeit' => '<svg width="64" height="64" viewBox="0 0 80 80" fill="none"><circle cx="40" cy="38" r="13" fill="none" stroke="#4299e1" stroke-width="5"/><circle cx="40" cy="38" r="5" fill="#4299e1"/><rect x="37" y="18" width="6" height="10" rx="2" fill="#4299e1"/><rect x="37" y="48" width="6" height="10" rx="2" fill="#4299e1"/><rect x="18" y="35" width="10" height="6" rx="2" fill="#4299e1"/><rect x="48" y="35" width="10" height="6" rx="2" fill="#4299e1"/></svg>',
            'sommergruss'    => '<svg width="64" height="64" viewBox="0 0 80 80" fill="none"><circle cx="40" cy="35" r="16" fill="#FFD700"/><line x1="40" y1="8" x2="40" y2="15" stroke="#FFD700" stroke-width="3" stroke-linecap="round"/><line x1="40" y1="55" x2="40" y2="62" stroke="#FFD700" stroke-width="3" stroke-linecap="round"/><line x1="13" y1="35" x2="20" y2="35" stroke="#FFD700" stroke-width="3" stroke-linecap="round"/><line x1="60" y1="35" x2="67" y2="35" stroke="#FFD700" stroke-width="3" stroke-linecap="round"/><line x1="21" y1="16" x2="26" y2="21" stroke="#FFD700" stroke-width="3" stroke-linecap="round"/><line x1="59" y1="16" x2="54" y2="21" stroke="#FFD700" stroke-width="3" stroke-linecap="round"/></svg>',
            'halloween'      => '<svg width="64" height="64" viewBox="0 0 80 80" fill="none"><ellipse cx="40" cy="50" rx="28" ry="22" fill="#f6803a"/><ellipse cx="28" cy="50" rx="12" ry="20" fill="#e8692a" opacity="0.7"/><path d="M40 30 C40 24 44 20 48 18 C44 22 42 26 42 30Z" fill="#2d7a3a"/><polygon points="26,46 30,40 34,46" fill="#1a0a00"/><polygon points="46,46 50,40 54,46" fill="#1a0a00"/><path d="M26 58 Q30 54 34 58 Q37 55 40 58 Q43 55 46 58 Q50 54 54 58" fill="#1a0a00"/></svg>',
            default          => '<svg width="64" height="64" viewBox="0 0 80 80" fill="none"><circle cx="40" cy="40" r="28" fill="none" stroke="#4299e1" stroke-width="4"/><path d="M28 40 L36 48 L52 32" stroke="#4299e1" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        };
    }
    private function getBottomIllustration(string $slug): string
    {
        $svgs = [];

        $svgs['weihnachten'] = '<svg width="320" height="140" viewBox="0 0 320 140" fill="none" xmlns="http://www.w3.org/2000/svg"><ellipse cx="160" cy="135" rx="155" ry="12" fill="white" opacity="0.12"/><ellipse cx="70" cy="110" rx="28" ry="22" fill="#c0392b"/><rect x="50" y="125" width="12" height="14" rx="4" fill="#2c3e50"/><rect x="68" y="125" width="12" height="14" rx="4" fill="#2c3e50"/><rect x="48" y="133" width="16" height="6" rx="3" fill="#1a1a1a"/><rect x="66" y="133" width="16" height="6" rx="3" fill="#1a1a1a"/><rect x="42" y="106" width="56" height="8" rx="2" fill="#1a1a1a"/><rect x="65" y="104" width="10" height="12" rx="2" fill="#FFD700"/><circle cx="70" cy="100" r="2" fill="#fff" opacity="0.6"/><circle cx="70" cy="92" r="2" fill="#fff" opacity="0.6"/><circle cx="70" cy="76" r="16" fill="#f5d5a0"/><ellipse cx="70" cy="86" rx="14" ry="8" fill="white"/><ellipse cx="62" cy="82" rx="7" ry="5" fill="white"/><ellipse cx="78" cy="82" rx="7" ry="5" fill="white"/><circle cx="65" cy="74" r="2" fill="#555"/><circle cx="75" cy="74" r="2" fill="#555"/><circle cx="70" cy="78" r="2.5" fill="#e8926a"/><path d="M56 70 Q60 48 70 38 Q80 48 84 70Z" fill="#c0392b"/><rect x="54" y="66" width="32" height="7" rx="3" fill="white"/><circle cx="70" cy="36" r="5" fill="white"/><path d="M42 100 Q30 90 28 80" stroke="#c0392b" stroke-width="8" stroke-linecap="round"/><rect x="16" y="68" width="22" height="20" rx="3" fill="#e53e3e"/><rect x="26" y="68" width="4" height="20" fill="#FFD700"/><rect x="16" y="76" width="22" height="4" fill="#FFD700"/><ellipse cx="200" cy="108" rx="30" ry="16" fill="#8B5a2b"/><circle cx="230" cy="94" r="12" fill="#8B5a2b"/><ellipse cx="235" cy="100" rx="7" ry="5" fill="#8B5a2b"/><circle cx="240" cy="101" r="4" fill="#e53e3e"/><circle cx="241" cy="100" r="1.5" fill="rgba(255,255,255,0.5)"/><circle cx="228" cy="92" r="2" fill="#222"/><line x1="222" y1="84" x2="214" y2="70" stroke="#6b3a1f" stroke-width="3" stroke-linecap="round"/><line x1="214" y1="70" x2="208" y2="62" stroke="#6b3a1f" stroke-width="2.5" stroke-linecap="round"/><line x1="232" y1="83" x2="240" y2="69" stroke="#6b3a1f" stroke-width="3" stroke-linecap="round"/><line x1="240" y1="69" x2="246" y2="61" stroke="#6b3a1f" stroke-width="2.5" stroke-linecap="round"/><rect x="178" y="120" width="8" height="16" rx="3" fill="#7a4f2a"/><rect x="192" y="120" width="8" height="16" rx="3" fill="#7a4f2a"/><rect x="206" y="120" width="8" height="16" rx="3" fill="#7a4f2a"/><rect x="220" y="120" width="8" height="16" rx="3" fill="#7a4f2a"/><circle cx="130" cy="30" r="3" fill="white" opacity="0.5"/><circle cx="280" cy="40" r="2.5" fill="white" opacity="0.5"/></svg>';

        $svgs['neujahr'] = '<svg width="320" height="140" viewBox="0 0 320 140" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="0" y="90" width="320" height="50" fill="#1a1a3e" opacity="0.6"/><rect x="10" y="70" width="25" height="70" fill="#1a1a3e"/><rect x="40" y="60" width="18" height="80" fill="#1a1a3e"/><rect x="62" y="75" width="22" height="65" fill="#1a1a3e"/><rect x="250" y="65" width="20" height="75" fill="#1a1a3e"/><rect x="274" y="55" width="26" height="85" fill="#1a1a3e"/><rect x="300" y="72" width="20" height="68" fill="#1a1a3e"/><rect x="15" y="78" width="5" height="5" rx="1" fill="#FFD700" opacity="0.8"/><rect x="24" y="78" width="5" height="5" rx="1" fill="#FFD700" opacity="0.6"/><rect x="44" y="66" width="4" height="4" rx="1" fill="#FFD700" opacity="0.7"/><rect x="280" y="62" width="5" height="5" rx="1" fill="#4299e1" opacity="0.8"/><circle cx="120" cy="40" r="3" fill="#FFD700"/><line x1="120" y1="40" x2="120" y2="18" stroke="#FFD700" stroke-width="2" stroke-linecap="round"/><line x1="120" y1="40" x2="120" y2="62" stroke="#FFD700" stroke-width="2" stroke-linecap="round"/><line x1="120" y1="40" x2="98" y2="40" stroke="#FFD700" stroke-width="2" stroke-linecap="round"/><line x1="120" y1="40" x2="142" y2="40" stroke="#FFD700" stroke-width="2" stroke-linecap="round"/><line x1="120" y1="40" x2="104" y2="24" stroke="#FFD700" stroke-width="2" stroke-linecap="round"/><line x1="120" y1="40" x2="136" y2="24" stroke="#FFD700" stroke-width="2" stroke-linecap="round"/><line x1="120" y1="40" x2="104" y2="56" stroke="#FFD700" stroke-width="2" stroke-linecap="round"/><line x1="120" y1="40" x2="136" y2="56" stroke="#FFD700" stroke-width="2" stroke-linecap="round"/><circle cx="200" cy="28" r="2.5" fill="#e53e3e"/><line x1="200" y1="28" x2="200" y2="10" stroke="#e53e3e" stroke-width="2" stroke-linecap="round"/><line x1="200" y1="28" x2="200" y2="46" stroke="#e53e3e" stroke-width="2" stroke-linecap="round"/><line x1="200" y1="28" x2="182" y2="28" stroke="#e53e3e" stroke-width="2" stroke-linecap="round"/><line x1="200" y1="28" x2="218" y2="28" stroke="#e53e3e" stroke-width="2" stroke-linecap="round"/><line x1="200" y1="28" x2="187" y2="15" stroke="#e53e3e" stroke-width="2" stroke-linecap="round"/><line x1="200" y1="28" x2="213" y2="15" stroke="#e53e3e" stroke-width="2" stroke-linecap="round"/><circle cx="165" cy="55" r="2" fill="#9f7aea"/><line x1="165" y1="55" x2="165" y2="42" stroke="#9f7aea" stroke-width="1.5" stroke-linecap="round"/><line x1="165" y1="55" x2="165" y2="68" stroke="#9f7aea" stroke-width="1.5" stroke-linecap="round"/><line x1="165" y1="55" x2="152" y2="55" stroke="#9f7aea" stroke-width="1.5" stroke-linecap="round"/><line x1="165" y1="55" x2="178" y2="55" stroke="#9f7aea" stroke-width="1.5" stroke-linecap="round"/><path d="M148 120 Q160 105 172 120 L169 132 Q160 129 151 132 Z" fill="#C0C0C0" opacity="0.7"/><line x1="160" y1="132" x2="160" y2="138" stroke="#C0C0C0" stroke-width="3" stroke-linecap="round"/><line x1="153" y1="138" x2="167" y2="138" stroke="#C0C0C0" stroke-width="2" stroke-linecap="round"/><circle cx="155" cy="122" r="1.5" fill="#FFD700"/><circle cx="163" cy="120" r="1.5" fill="#FFD700"/></svg>';

        $svgs['nikolaus'] = '<svg width="320" height="140" viewBox="0 0 320 140" fill="none" xmlns="http://www.w3.org/2000/svg"><ellipse cx="160" cy="135" rx="155" ry="12" fill="white" opacity="0.12"/><path d="M110 140 L100 80 Q100 68 120 68 L160 68 Q180 68 180 80 L170 140Z" fill="#c0392b"/><rect x="100" y="96" width="80" height="9" rx="2" fill="#1a1a1a"/><rect x="132" y="93" width="14" height="15" rx="2" fill="#FFD700"/><rect x="98" y="68" width="84" height="8" rx="4" fill="white" opacity="0.9"/><circle cx="140" cy="54" r="20" fill="#f5d5a0"/><ellipse cx="140" cy="68" rx="20" ry="12" fill="white"/><ellipse cx="124" cy="62" rx="10" ry="8" fill="white"/><ellipse cx="156" cy="62" rx="10" ry="8" fill="white"/><path d="M130 56 Q140 60 150 56" stroke="white" stroke-width="3" fill="none" stroke-linecap="round"/><circle cx="133" cy="50" r="2.5" fill="#555"/><circle cx="147" cy="50" r="2.5" fill="#555"/><ellipse cx="126" cy="56" rx="5" ry="3" fill="#f48fb1" opacity="0.5"/><ellipse cx="154" cy="56" rx="5" ry="3" fill="#f48fb1" opacity="0.5"/><circle cx="140" cy="54" r="3" fill="#e8926a"/><path d="M122 38 Q130 12 140 6 Q150 12 158 38Z" fill="#c0392b"/><rect x="118" y="34" width="44" height="8" rx="4" fill="white"/><circle cx="140" cy="5" r="6" fill="white"/><ellipse cx="200" cy="90" rx="32" ry="28" fill="#8B4513"/><path d="M180 68 Q200 55 220 68" stroke="#6b3010" stroke-width="3" stroke-linecap="round" fill="none"/><rect x="186" y="68" width="16" height="14" rx="2" fill="#e53e3e"/><rect x="193" y="68" width="3" height="14" fill="#FFD700"/><rect x="186" y="74" width="16" height="3" fill="#FFD700"/><rect x="204" y="66" width="14" height="12" rx="2" fill="#4dc25f"/><rect x="210" y="66" width="3" height="12" fill="#FFD700"/><circle cx="50" cy="30" r="2.5" fill="white" opacity="0.5"/><circle cx="270" cy="20" r="2" fill="white" opacity="0.4"/></svg>';

        $svgs['advent'] = '<svg width="320" height="140" viewBox="0 0 320 140" fill="none" xmlns="http://www.w3.org/2000/svg"><ellipse cx="160" cy="105" rx="110" ry="28" fill="none" stroke="#2d7a3a" stroke-width="18" opacity="0.9"/><ellipse cx="160" cy="105" rx="110" ry="28" fill="none" stroke="#3a9e4a" stroke-width="10" opacity="0.7"/><ellipse cx="160" cy="105" rx="95" ry="22" fill="none" stroke="#4dc25f" stroke-width="4" stroke-dasharray="8 6" opacity="0.6"/><path d="M160 78 C150 70 140 74 145 80 C150 86 160 82 160 78Z" fill="#c0392b"/><path d="M160 78 C170 70 180 74 175 80 C170 86 160 82 160 78Z" fill="#c0392b"/><circle cx="160" cy="80" r="5" fill="#e53e3e"/><rect x="62" y="72" width="14" height="36" rx="3" fill="#c0392b"/><rect x="60" y="108" width="18" height="5" rx="2" fill="#a0291b"/><path d="M69 72 C67 65 71 58 69 52 C67 58 65 65 69 72Z" fill="#FFA500"/><ellipse cx="69" cy="68" rx="4" ry="6" fill="#FFD700" opacity="0.7"/><circle cx="69" cy="63" r="2" fill="#fff" opacity="0.8"/><rect x="118" y="75" width="14" height="34" rx="3" fill="#8B0000" opacity="0.6"/><line x1="125" y1="75" x2="125" y2="70" stroke="#888" stroke-width="2" stroke-linecap="round"/><rect x="178" y="75" width="14" height="34" rx="3" fill="#8B0000" opacity="0.6"/><line x1="185" y1="75" x2="185" y2="70" stroke="#888" stroke-width="2" stroke-linecap="round"/><rect x="234" y="72" width="14" height="36" rx="3" fill="#8B0000" opacity="0.6"/><line x1="241" y1="72" x2="241" y2="67" stroke="#888" stroke-width="2" stroke-linecap="round"/><circle cx="100" cy="88" r="4" fill="#e53e3e"/><circle cx="108" cy="96" r="4" fill="#e53e3e"/><circle cx="215" cy="88" r="4" fill="#e53e3e"/><circle cx="208" cy="96" r="4" fill="#e53e3e"/></svg>';

        $svgs['ostern'] = '<svg width="320" height="140" viewBox="0 0 320 140" fill="none" xmlns="http://www.w3.org/2000/svg"><ellipse cx="160" cy="132" rx="155" ry="14" fill="#3a9e4a" opacity="0.4"/><ellipse cx="80" cy="105" rx="30" ry="32" fill="#f0ece6"/><ellipse cx="80" cy="112" rx="16" ry="18" fill="#f9c8d4" opacity="0.6"/><ellipse cx="62" cy="130" rx="14" ry="8" fill="#f0ece6"/><ellipse cx="98" cy="130" rx="14" ry="8" fill="#f0ece6"/><ellipse cx="68" cy="60" rx="9" ry="22" fill="#f0ece6"/><ellipse cx="68" cy="60" rx="5" ry="17" fill="#f48fb1"/><ellipse cx="92" cy="58" rx="9" ry="22" fill="#f0ece6"/><ellipse cx="92" cy="58" rx="5" ry="17" fill="#f48fb1"/><circle cx="80" cy="82" r="22" fill="#f0ece6"/><circle cx="73" cy="79" r="3" fill="#555"/><circle cx="87" cy="79" r="3" fill="#555"/><circle cx="74" cy="78" r="1" fill="white"/><circle cx="88" cy="78" r="1" fill="white"/><ellipse cx="80" cy="86" rx="3" ry="2" fill="#f48fb1"/><path d="M76 89 Q80 93 84 89" stroke="#d4a0a0" stroke-width="1.5" fill="none" stroke-linecap="round"/><path d="M170 118 Q170 100 200 100 Q230 100 230 118 L225 135 Q200 140 175 135 Z" fill="#8B4513"/><path d="M180 100 Q200 80 220 100" fill="none" stroke="#8B4513" stroke-width="5" stroke-linecap="round"/><ellipse cx="190" cy="116" rx="11" ry="14" fill="#f6ad55"/><ellipse cx="210" cy="116" rx="11" ry="14" fill="#68d391"/><ellipse cx="200" cy="112" rx="9" ry="11" fill="#9f7aea"/><circle cx="140" cy="120" r="6" fill="#FFD700"/><circle cx="140" cy="113" r="4" fill="#FFA500" opacity="0.8"/><circle cx="147" cy="117" r="4" fill="#FFA500" opacity="0.8"/><circle cx="133" cy="117" r="4" fill="#FFA500" opacity="0.8"/><circle cx="265" cy="120" r="12" fill="#FFD700"/><polygon points="265,113 263,109 267,109" fill="#f6ad55"/><circle cx="262" cy="118" r="1.8" fill="#333"/></svg>';

        return isset($svgs[$slug]) ? $svgs[$slug] : $this->getBottomIllustrationB($slug);
    }

    private function getBottomIllustrationB(string $slug): string
    {
        $svgs = [];
        $svgs['pfingsten'] = '<svg width="320" height="140" viewBox="0 0 320 140" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M120 70 C95 55 65 62 70 80 C75 96 100 90 120 80 C140 90 165 96 170 80 C175 62 145 55 120 70Z" fill="white" opacity="0.95"/><circle cx="175" cy="65" r="2.5" fill="#4299e1"/><circle cx="175.5" cy="64.5" r="1" fill="white"/><path d="M183 67 L190 65 L183 70Z" fill="#f6ad55"/><circle cx="170" cy="68" r="14" fill="white" opacity="0.95"/><path d="M185 65 C198 58 210 62 220 55" stroke="#3a9e4a" stroke-width="2.5" stroke-linecap="round" fill="none"/><ellipse cx="195" cy="60" rx="5" ry="3" fill="#4dc25f" transform="rotate(-20 195 60)"/><ellipse cx="207" cy="57" rx="5" ry="3" fill="#4dc25f" transform="rotate(-10 207 57)"/><path d="M40 90 C35 75 45 62 40 50 C50 62 55 78 48 90Z" fill="#f6ad55" opacity="0.9"/><path d="M40 90 C38 80 44 70 42 62 C46 70 46 82 44 90Z" fill="#FFD700" opacity="0.8"/><path d="M260 88 C255 73 265 60 260 48 C270 60 275 76 268 88Z" fill="#f6ad55" opacity="0.9"/><path d="M260 88 C258 78 264 68 262 60 C266 68 266 80 264 88Z" fill="#FFD700" opacity="0.8"/><circle cx="160" cy="128" r="16" fill="#FFD700" opacity="0.2"/><circle cx="160" cy="128" r="10" fill="#FFD700" opacity="0.3"/><circle cx="160" cy="128" r="5" fill="#FFD700" opacity="0.5"/></svg>';

        $svgs['valentinstag'] = '<svg width="320" height="140" viewBox="0 0 320 140" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M30 50 C30 42 38 38 42 44 C46 38 54 42 54 50 C54 60 42 68 42 68 C42 68 30 60 30 50Z" fill="#e53e3e" opacity="0.2"/><path d="M255 35 C255 28 262 24 266 30 C270 24 277 28 277 35 C277 44 266 52 266 52 C266 52 255 44 255 35Z" fill="#e53e3e" opacity="0.2"/><path d="M160 115 C160 115 95 80 95 45 C95 26 108 16 122 20 C134 23 145 32 160 46 C175 32 186 23 198 20 C212 16 225 26 225 45 C225 80 160 115 160 115Z" fill="#e53e3e"/><path d="M115 30 C110 30 104 36 104 44" stroke="rgba(255,255,255,0.4)" stroke-width="4" stroke-linecap="round"/><line x1="80" y1="30" x2="240" y2="90" stroke="#FFD700" stroke-width="3" stroke-linecap="round"/><polygon points="240,90 228,82 234,96" fill="#FFD700"/><path d="M80 30 C76 26 76 22 82 24 C78 28 80 30 80 30Z" fill="#FFD700"/><path d="M50 105 C50 101 54 99 56 102 C58 99 62 101 62 105 C62 110 56 114 56 114 C56 114 50 110 50 105Z" fill="#fc8181"/><path d="M258 108 C258 104 262 102 264 105 C266 102 270 104 270 108 C270 113 264 117 264 117 C264 117 258 113 258 108Z" fill="#fc8181"/><line x1="55" y1="55" x2="55" y2="63" stroke="#FFD700" stroke-width="2" stroke-linecap="round"/><line x1="51" y1="59" x2="59" y2="59" stroke="#FFD700" stroke-width="2" stroke-linecap="round"/></svg>';

        $svgs['muttertag'] = '<svg width="320" height="140" viewBox="0 0 320 140" fill="none" xmlns="http://www.w3.org/2000/svg"><line x1="160" y1="135" x2="160" y2="68" stroke="#2d7a3a" stroke-width="4" stroke-linecap="round"/><path d="M160 110 Q140 95 118 90" stroke="#2d7a3a" stroke-width="3" stroke-linecap="round" fill="none"/><path d="M160 100 Q138 85 112 76" stroke="#2d7a3a" stroke-width="3" stroke-linecap="round" fill="none"/><path d="M160 90 Q142 78 125 65" stroke="#2d7a3a" stroke-width="3" stroke-linecap="round" fill="none"/><path d="M160 110 Q180 95 202 90" stroke="#2d7a3a" stroke-width="3" stroke-linecap="round" fill="none"/><path d="M160 100 Q182 85 208 76" stroke="#2d7a3a" stroke-width="3" stroke-linecap="round" fill="none"/><path d="M160 90 Q178 78 195 65" stroke="#2d7a3a" stroke-width="3" stroke-linecap="round" fill="none"/><circle cx="160" cy="56" r="14" fill="#f06292"/><circle cx="160" cy="56" r="9" fill="#e91e63"/><circle cx="160" cy="56" r="4.5" fill="#c2185b"/><ellipse cx="160" cy="42" rx="6" ry="9" fill="#f48fb1" opacity="0.8"/><ellipse cx="173" cy="48" rx="6" ry="9" fill="#f48fb1" opacity="0.8" transform="rotate(60 173 48)"/><ellipse cx="173" cy="64" rx="6" ry="9" fill="#f48fb1" opacity="0.8" transform="rotate(120 173 64)"/><ellipse cx="147" cy="48" rx="6" ry="9" fill="#f48fb1" opacity="0.8" transform="rotate(300 147 48)"/><circle cx="116" cy="82" r="10" fill="#FFD700"/><circle cx="116" cy="82" r="5" fill="#f6ad55"/><circle cx="116" cy="72" r="6" fill="#FFD700" opacity="0.8"/><circle cx="126" cy="78" r="6" fill="#FFD700" opacity="0.8"/><circle cx="106" cy="78" r="6" fill="#FFD700" opacity="0.8"/><circle cx="204" cy="82" r="10" fill="#9f7aea"/><circle cx="204" cy="82" r="5" fill="#805ad5"/><circle cx="204" cy="72" r="6" fill="#9f7aea" opacity="0.8"/><circle cx="214" cy="78" r="6" fill="#9f7aea" opacity="0.8"/><circle cx="194" cy="78" r="6" fill="#9f7aea" opacity="0.8"/><path d="M140 135 Q160 128 180 135 L177 140 Q160 137 143 140 Z" fill="#f48fb1" opacity="0.8"/></svg>';

        $svgs['vatertag'] = '<svg width="320" height="140" viewBox="0 0 320 140" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="0" y="100" width="320" height="40" fill="#3a9e4a" opacity="0.2"/><rect x="95" y="75" width="46" height="55" rx="8" fill="#4a90d9"/><rect x="100" y="125" width="14" height="16" rx="4" fill="#2c3e50"/><rect x="120" y="125" width="14" height="16" rx="4" fill="#2c3e50"/><rect x="95" y="105" width="46" height="7" rx="2" fill="#2c3e50"/><rect x="114" y="103" width="10" height="11" rx="2" fill="#FFD700"/><path d="M108 75 L118 85 L128 75" stroke="white" stroke-width="2" fill="none"/><circle cx="118" cy="58" r="18" fill="#f5d5a0"/><path d="M100 50 Q100 38 118 38 Q136 38 136 50" fill="#4a3728"/><circle cx="112" cy="55" r="2.5" fill="#555"/><circle cx="124" cy="55" r="2.5" fill="#555"/><path d="M110 63 Q118 69 126 63" stroke="#c0392b" stroke-width="2" fill="none" stroke-linecap="round"/><path d="M141 85 Q160 80 168 90" stroke="#4a90d9" stroke-width="10" stroke-linecap="round"/><rect x="158" y="95" width="30" height="38" rx="6" fill="#e53e3e"/><rect x="162" y="128" width="10" height="14" rx="3" fill="#2c3e50"/><rect x="175" y="128" width="10" height="14" rx="3" fill="#2c3e50"/><circle cx="173" cy="80" r="14" fill="#f5d5a0"/><path d="M159 74 Q159 64 173 64 Q187 64 187 74" fill="#c8860a"/><circle cx="168" cy="78" r="2" fill="#555"/><circle cx="178" cy="78" r="2" fill="#555"/><path d="M167 85 Q173 89 179 85" stroke="#c0392b" stroke-width="1.5" fill="none" stroke-linecap="round"/><rect x="50" y="85" width="30" height="40" rx="4" fill="#f6ad55" opacity="0.85"/><rect x="80" y="93" width="10" height="20" rx="5" fill="#f6ad55" opacity="0.6"/><ellipse cx="65" cy="85" rx="16" ry="8" fill="white" opacity="0.9"/><ellipse cx="58" cy="82" rx="6" ry="5" fill="white" opacity="0.8"/><ellipse cx="72" cy="81" rx="6" ry="5" fill="white" opacity="0.8"/><rect x="52" y="92" width="26" height="30" rx="2" fill="#d4820a" opacity="0.7"/><path d="M240 105 C230 95 230 80 240 80 C248 80 248 90 240 95 C232 100 228 112 238 112 C248 112 252 102 248 96" stroke="#8B4513" stroke-width="5" fill="none" stroke-linecap="round"/><circle cx="237" cy="80" r="4" fill="#8B4513"/><circle cx="238" cy="96" r="2" fill="white"/><circle cx="244" cy="88" r="2" fill="white"/></svg>';

        $svgs['tag_der_arbeit'] = '<svg width="320" height="140" viewBox="0 0 320 140" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="0" y="90" width="320" height="50" fill="#1a2a4a" opacity="0.4"/><rect x="15" y="65" width="30" height="75" fill="#1a2a4a" opacity="0.7"/><rect x="50" y="52" width="22" height="88" fill="#1a2a4a" opacity="0.7"/><rect x="240" y="60" width="28" height="80" fill="#1a2a4a" opacity="0.7"/><rect x="272" y="50" width="22" height="90" fill="#1a2a4a" opacity="0.7"/><circle cx="160" cy="72" r="42" fill="none" stroke="#4299e1" stroke-width="14"/><circle cx="160" cy="72" r="16" fill="#4299e1"/><circle cx="160" cy="72" r="8" fill="#0f0f1a"/><rect x="156" y="22" width="8" height="16" rx="3" fill="#4299e1"/><rect x="156" y="106" width="8" height="16" rx="3" fill="#4299e1"/><rect x="106" y="68" width="16" height="8" rx="3" fill="#4299e1"/><rect x="198" y="68" width="16" height="8" rx="3" fill="#4299e1"/><rect x="122" y="36" width="8" height="16" rx="3" fill="#4299e1" transform="rotate(45 126 44)"/><rect x="186" y="36" width="8" height="16" rx="3" fill="#4299e1" transform="rotate(-45 194 44)"/><rect x="122" y="90" width="8" height="16" rx="3" fill="#4299e1" transform="rotate(-45 126 98)"/><rect x="186" y="90" width="8" height="16" rx="3" fill="#4299e1" transform="rotate(45 194 98)"/><rect x="55" y="58" width="12" height="50" rx="4" fill="#9f7aea" transform="rotate(-30 61 83)"/><circle cx="48" cy="52" r="10" fill="none" stroke="#9f7aea" stroke-width="6"/><circle cx="48" cy="52" r="4" fill="#0f0f1a"/><rect x="235" y="62" width="10" height="48" rx="3" fill="#f6ad55" transform="rotate(25 240 86)"/><rect x="220" y="56" width="28" height="16" rx="4" fill="#f6ad55" transform="rotate(25 234 64)"/><polygon points="160,115 162,122 170,122 164,127 166,134 160,130 154,134 156,127 150,122 158,122" fill="#FFD700" opacity="0.9"/></svg>';

        $svgs['sommergruss'] = '<svg width="320" height="140" viewBox="0 0 320 140" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="0" y="90" width="320" height="50" fill="#f6ad55" opacity="0.3"/><rect x="0" y="100" width="320" height="40" fill="#4299e1" opacity="0.2"/><circle cx="260" cy="40" r="30" fill="#FFD700" opacity="0.9"/><circle cx="260" cy="40" r="22" fill="#FFD700"/><line x1="260" y1="2" x2="260" y2="12" stroke="#FFD700" stroke-width="4" stroke-linecap="round"/><line x1="260" y1="68" x2="260" y2="78" stroke="#FFD700" stroke-width="4" stroke-linecap="round"/><line x1="222" y1="40" x2="232" y2="40" stroke="#FFD700" stroke-width="4" stroke-linecap="round"/><line x1="288" y1="40" x2="298" y2="40" stroke="#FFD700" stroke-width="4" stroke-linecap="round"/><line x1="233" y1="13" x2="240" y2="20" stroke="#FFD700" stroke-width="4" stroke-linecap="round"/><line x1="287" y1="13" x2="280" y2="20" stroke="#FFD700" stroke-width="4" stroke-linecap="round"/><ellipse cx="95" cy="108" rx="35" ry="20" fill="#c8860a"/><circle cx="130" cy="96" r="18" fill="#c8860a"/><ellipse cx="145" cy="100" rx="10" ry="7" fill="#d4940e"/><ellipse cx="152" cy="98" rx="5" ry="3" fill="#333"/><circle cx="134" cy="93" r="3" fill="#333"/><circle cx="135" cy="92" r="1" fill="white"/><ellipse cx="120" cy="84" rx="8" ry="14" fill="#b07010" transform="rotate(-15 120 84)"/><ellipse cx="138" cy="82" rx="8" ry="14" fill="#b07010" transform="rotate(15 138 82)"/><path d="M62 100 C50 88 42 78 50 70 C55 66 62 70 60 80Z" fill="#c8860a"/><rect x="70" y="122" width="12" height="14" rx="4" fill="#b07010"/><rect x="88" y="122" width="12" height="14" rx="4" fill="#b07010"/><rect x="106" y="122" width="12" height="14" rx="4" fill="#b07010"/><path d="M148 105 Q152 112 148 114 Q144 116 143 112 Q140 108 143 105Z" fill="#e53e3e"/><circle cx="190" cy="118" r="18" fill="white"/><path d="M172 118 Q181 106 190 100 Q199 106 208 118" fill="#e53e3e"/><path d="M172 118 Q181 130 190 136 Q199 130 208 118" fill="#4299e1"/><path d="M48 80 L42 110 L54 110 Z" fill="#f5f5dc"/><circle cx="48" cy="80" r="12" fill="#f48fb1"/><path d="M0 125 Q40 115 80 125 Q120 135 160 125 Q200 115 240 125 Q280 135 320 125" fill="none" stroke="#4299e1" stroke-width="3" opacity="0.5" stroke-linecap="round"/></svg>';

        $svgs['halloween'] = '<svg width="320" height="140" viewBox="0 0 320 140" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="270" cy="30" r="22" fill="#1a1a3e"/><path d="M252 30 C252 18 260 12 270 14 C264 18 261 24 261 30 C261 36 264 42 270 46 C260 48 252 42 252 30Z" fill="#FFD700" opacity="0.8"/><circle cx="40" cy="20" r="2" fill="white" opacity="0.7"/><circle cx="180" cy="18" r="2" fill="white" opacity="0.5"/><circle cx="310" cy="40" r="2" fill="white" opacity="0.6"/><rect x="195" y="70" width="80" height="70" fill="#1a1a3e" opacity="0.8"/><polygon points="195,70 235,40 275,70" fill="#1a1a3e" opacity="0.8"/><rect x="212" y="85" width="18" height="24" rx="2" fill="#FFD700" opacity="0.5"/><rect x="242" y="85" width="18" height="24" rx="2" fill="#FFD700" opacity="0.3"/><rect x="222" y="109" width="16" height="31" fill="#1a1a3e"/><ellipse cx="100" cy="100" rx="42" ry="34" fill="#f6803a"/><ellipse cx="82" cy="100" rx="18" ry="30" fill="#e8692a" opacity="0.6"/><ellipse cx="118" cy="100" rx="18" ry="30" fill="#e8692a" opacity="0.6"/><path d="M100 66 C100 58 106 52 112 50 C107 54 104 59 104 66Z" fill="#2d7a3a"/><polygon points="82,92 88,82 94,92" fill="#1a0a00"/><polygon points="106,92 112,82 118,92" fill="#1a0a00"/><path d="M78 108 Q84 102 90 108 Q94 104 100 108 Q106 104 110 108 Q116 102 122 108" fill="#1a0a00"/><path d="M165 60 C165 45 178 40 178 40 C178 40 191 45 191 60 L191 88 C188 84 185 88 182 84 C179 88 175 84 172 88 C169 84 165 88 165 88 Z" fill="white" opacity="0.85"/><circle cx="173" cy="65" r="3.5" fill="#333"/><circle cx="183" cy="65" r="3.5" fill="#333"/><ellipse cx="50" cy="50" rx="8" ry="6" fill="#333"/><path d="M42 50 C38 44 28 46 26 54 C30 52 36 52 42 50Z" fill="#333"/><path d="M58 50 C62 44 72 46 74 54 C70 52 64 52 58 50Z" fill="#333"/><circle cx="47" cy="48" r="1.5" fill="#e53e3e"/><circle cx="53" cy="48" r="1.5" fill="#e53e3e"/><path d="M28 110 Q28 90 50 88 Q72 90 72 110 L68 128 Q50 133 32 128 Z" fill="#333"/><ellipse cx="50" cy="90" rx="22" ry="8" fill="#444"/><ellipse cx="50" cy="92" rx="16" ry="6" fill="#4dc25f" opacity="0.6"/></svg>';

        return $svgs[$slug] ?? '<svg width="280" height="80" viewBox="0 0 280 80" fill="none"><circle cx="140" cy="40" r="30" fill="none" stroke="#4299e1" stroke-width="4"/><path d="M126 40 L136 50 L154 32" stroke="#4299e1" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }
    private function getGradient(string $slug): string
    {
        return match($slug) {
            'weihnachten'    => 'linear-gradient(135deg,#1a472a,#2d6a3f)',
            'neujahr'        => 'linear-gradient(135deg,#1a1a3e,#2d2d6e)',
            'advent'         => 'linear-gradient(135deg,#2a1a1a,#6a2020)',
            'nikolaus'       => 'linear-gradient(135deg,#4a0a0a,#8a1a1a)',
            'ostern'         => 'linear-gradient(135deg,#2d5a1b,#4a8c2d)',
            'pfingsten'      => 'linear-gradient(135deg,#1a2a4a,#2d4a7a)',
            'valentinstag'   => 'linear-gradient(135deg,#5a1a2a,#8c2d40)',
            'muttertag'      => 'linear-gradient(135deg,#4a1a4a,#7a2d7a)',
            'vatertag'       => 'linear-gradient(135deg,#1a2a4a,#2d4a6a)',
            'tag_der_arbeit' => 'linear-gradient(135deg,#1a2a4a,#1a4a6a)',
            'sommergruss'    => 'linear-gradient(135deg,#4a3000,#7a5010)',
            'halloween'      => 'linear-gradient(135deg,#2a1a0a,#4a2d0a)',
            default          => 'linear-gradient(135deg,rgba(79,124,255,0.35),rgba(139,92,246,0.35))',
        };
    }

    public function defaultSubject(string $slug, string $label): string
    {
        return match($slug) {
            'weihnachten'    => 'Frohe Weihnachten von {{praxis}}!',
            'neujahr'        => 'Einen guten Rutsch ins neue Jahr!',
            'advent'         => 'Die Adventszeit beginnt – {{praxis}} wünscht eine besinnliche Zeit!',
            'nikolaus'       => 'Frohen Nikolaustag von {{praxis}}!',
            'ostern'         => 'Frohe Ostern von {{praxis}}!',
            'pfingsten'      => 'Schöne Pfingsttage wünscht {{praxis}}!',
            'valentinstag'   => 'Alles Liebe zum Valentinstag!',
            'muttertag'      => 'Alles Gute zum Muttertag!',
            'vatertag'       => 'Alles Gute zum Vatertag!',
            'tag_der_arbeit' => 'Schönen Feiertag wünscht {{praxis}}!',
            'sommergruss'    => 'Sonnige Sommergrüße von {{praxis}}!',
            'halloween'      => 'Happy Halloween!',
            default          => "Schöne {$label}!",
        };
    }

    public function defaultBody(string $slug): string
    {
        return match($slug) {
            'weihnachten'    => "Sehr geehrte/r {{name}},\n\nich wünsche Ihnen und Ihren Liebsten von Herzen ein besinnliches und frohes Weihnachtsfest.\n\nBesonders {{patient}} darf sich sicher über etwas ganz Besonderes freuen - mögen die Festtage Ihnen alle Ruhe, Wärme und schöne gemeinsame Momente schenken.\n\nHerzliche Weihnachtsgrüße,\n{{praxis}}",
            'neujahr'        => "Sehr geehrte/r {{name}},\n\nich wünsche Ihnen und {{patient}} einen wunderschönen Jahreswechsel und ein gesundes, glückliches neues Jahr!\n\nAuf ein tolles gemeinsames Jahr,\n{{praxis}}",
            'advent'         => "Sehr geehrte/r {{name}},\n\ndie schönste Zeit des Jahres hat begonnen! Ich wünsche Ihnen und {{patient}} eine wunderschöne, besinnliche Adventszeit voller Vorfreude.\n\nHerzliche Grüße,\n{{praxis}}",
            'nikolaus'       => "Sehr geehrte/r {{name}},\n\nam heutigen Nikolaustag denke ich auch an Sie und {{patient}}.\n\nIch hoffe, der Nikolaus hat für Sie beide etwas Besonderes im Gepäck!\n\nHerzliche Nikolausgrüße,\n{{praxis}}",
            'ostern'         => "Sehr geehrte/r {{name}},\n\nich wünsche Ihnen und Ihrer Familie ein fröhliches Osterfest voller bunter Überraschungen!\n\nGenießen Sie die freien Tage in der Frühlingssonne - am besten gemeinsam mit {{patient}}.\n\nHerzliche Ostergrüße,\n{{praxis}}",
            'pfingsten'      => "Sehr geehrte/r {{name}},\n\nich wünsche Ihnen schöne und erholsame Pfingsttage!\n\nNutzen Sie das verlängerte Wochenende für entspannte Ausflüge mit {{patient}} und genießen Sie die warme Jahreszeit.\n\nHerzliche Grüße,\n{{praxis}}",
            'valentinstag'   => "Sehr geehrte/r {{name}},\n\nam Valentinstag denke ich auch an die wunderbare Verbindung zwischen Ihnen und {{patient}} - diese besondere Liebe ist etwas ganz Einzigartiges!\n\nAlles Liebe zum Valentinstag,\n{{praxis}}",
            'muttertag'      => "Sehr geehrte/r {{name}},\n\nheute möchte ich Ihnen ganz persönlich danken: Die Fürsorge, die Sie {{patient}} täglich entgegenbringen, ist wunderschön und von Herzen!\n\nAlles Gute zum Muttertag,\n{{praxis}}",
            'vatertag'       => "Sehr geehrte/r {{name}},\n\nam heutigen Vatertag wünsche ich Ihnen einen wunderschönen, entspannten Tag!\n\nIch hoffe, Sie genießen den Tag gemeinsam mit {{patient}} und Ihrer Familie.\n\nHerzliche Vatertags-Grüße,\n{{praxis}}",
            'tag_der_arbeit' => "Sehr geehrte/r {{name}},\n\nich wünsche Ihnen einen entspannten und erholsamen Feiertag - Sie haben sich eine Pause mehr als verdient!\n\nGenießen Sie den Tag mit Ihrer Familie und natürlich mit {{patient}}.\n\nHerzliche Grüße,\n{{praxis}}",
            'sommergruss'    => "Sehr geehrte/r {{name}},\n\nich wünsche Ihnen und {{patient}} einen wunderschönen Sommer voller schöner Momente und warmer Sonnenstunden!\n\nNutzen Sie die schöne Jahreszeit für gemeinsame Ausflüge.\n\nSonnige Sommergrüße,\n{{praxis}}",
            'halloween'      => "Sehr geehrte/r {{name}},\n\nHappy Halloween!\n\nIch hoffe, {{patient}} lässt sich von den Kostümen und dem Trubel heute Abend nicht allzu sehr erschrecken!\n\nGruselig-herzliche Grüße,\n{{praxis}}",
            default          => "Sehr geehrte/r {{name}},\n\nherzliche Grüße von {{praxis}}!",
        };
    }

    public function resolveRecipients(string $group): array
    {
        $sql = "SELECT o.id AS owner_id,
                       CONCAT(o.first_name,' ',o.last_name) AS name,
                       o.first_name, o.last_name, o.email,
                       (SELECT GROUP_CONCAT(p2.name ORDER BY p2.name SEPARATOR ', ')
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
            $pm->Host      = $this->settings->get('smtp_host','localhost');
            $pm->Port      = (int)$this->settings->get('smtp_port','587');
            $pm->Username  = $this->settings->get('smtp_username','');
            $pm->Password  = $this->settings->get('smtp_password','');
            $pm->SMTPAuth  = !empty($pm->Username);
            $pm->Timeout   = 10;
            $pm->SMTPKeepAlive = true;
            $enc = $this->settings->get('smtp_encryption','tls');
            if ($enc==='ssl')      { $pm->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS; }
            elseif ($enc==='none') { $pm->SMTPSecure = ''; $pm->SMTPAutoTLS = false; }
            else                   { $pm->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS; }
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
            error_log("[HolidayMail::sendRaw] ".$e->getMessage());
            return false;
        }
    }

    private function easterDate(int $year): \DateTime
    {
        $a=$year%19;$b=intdiv($year,100);$c=$year%100;$d=intdiv($b,4);$e=$b%4;
        $f=intdiv($b+8,25);$g=intdiv($b-$f+1,3);$h=(19*$a+$b-$d-$g+15)%30;
        $i=intdiv($c,4);$k=$c%4;$l=(32+2*$e+2*$i-$h-$k)%7;
        $m=intdiv($a+11*$h+22*$l,451);
        $month=intdiv($h+$l-7*$m+114,31);
        $day=(($h+$l-7*$m+114)%31)+1;
        return new \DateTime("{$year}-{$month}-{$day}");
    }

    private function mothersDay(int $year): string
    {
        $may1 = new \DateTime("{$year}-05-01");
        $dow  = (int)$may1->format('N');
        $daysToSunday = $dow === 7 ? 7 : (7 - $dow);
        $firstSunday  = (clone $may1)->modify("+{$daysToSunday} days");
        return (clone $firstSunday)->modify('+7 days')->format('Y-m-d');
    }

    private function fathersDay(int $year): string
    {
        $easter = $this->easterDate($year);
        return (clone $easter)->modify('+39 days')->format('Y-m-d');
    }

    private function firstAdvent(int $year): string
    {
        $christmas = new \DateTime("{$year}-12-25");
        $dow = (int)$christmas->format('N');
        $daysBack = ($dow === 7) ? 28 : (28 - (7 - $dow) % 7);
        return (clone $christmas)->modify("-{$daysBack} days")->format('Y-m-d');
    }
}