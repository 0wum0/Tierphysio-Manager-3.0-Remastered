<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\InvoiceRepository;
use App\Repositories\PatientRepository;
use App\Repositories\OwnerRepository;

class DashboardService
{
    public function __construct(
        private readonly InvoiceRepository $invoiceRepository,
        private readonly PatientRepository $patientRepository,
        private readonly OwnerRepository $ownerRepository,
        private readonly Database $db
    ) {}

    private function t(string $table): string
    {
        return $this->db->prefix($table);
    }

    public function getStats(): array
    {
        $invoiceStats = $this->invoiceRepository->getStats();
        $newPatients  = $this->patientRepository->countNew('30 days ago');
        $totalPatients = $this->patientRepository->count();
        $totalOwners   = $this->ownerRepository->count();

        return array_merge($invoiceStats, [
            'new_patients'    => $newPatients,
            'total_patients'  => $totalPatients,
            'total_owners'    => $totalOwners,
        ]);
    }

    public function getChartData(string $type): array
    {
        return $this->invoiceRepository->getChartData($type);
    }

    public function getChartDataByStatus(string $type): array
    {
        return $this->invoiceRepository->getChartDataByStatus($type);
    }

    public function getUpcomingAppointments(int $limit = 8): array
    {
        return $this->db->fetchAll(
            "SELECT a.id, a.title, a.start_at, a.end_at, a.status, a.notes,
                    a.patient_id,
                    p.name AS patient_name, p.species AS patient_species,
                    CONCAT(o.first_name, ' ', o.last_name) AS owner_name,
                    tt.name AS treatment_type_name, tt.color AS treatment_color
             FROM `{$this->t('appointments')}` a
             LEFT JOIN `{$this->t('patients')}` p ON p.id = a.patient_id
             LEFT JOIN `{$this->t('owners')}` o ON o.id = a.owner_id
             LEFT JOIN `{$this->t('treatment_types')}` tt ON tt.id = a.treatment_type_id
             WHERE a.start_at >= NOW() AND a.status NOT IN ('cancelled','noshow')
             ORDER BY a.start_at ASC
             LIMIT ?",
            [$limit]
        );
    }

    public function getTodayAppointments(): array
    {
        return $this->db->fetchAll(
            "SELECT a.id, a.title, a.start_at, a.end_at, a.status, a.notes,
                    a.patient_id,
                    p.name AS patient_name, p.species AS patient_species,
                    CONCAT(o.first_name, ' ', o.last_name) AS owner_name,
                    tt.name AS treatment_type_name, tt.color AS treatment_color
             FROM `{$this->t('appointments')}` a
             LEFT JOIN `{$this->t('patients')}` p ON p.id = a.patient_id
             LEFT JOIN `{$this->t('owners')}` o ON o.id = a.owner_id
             LEFT JOIN `{$this->t('treatment_types')}` tt ON tt.id = a.treatment_type_id
             WHERE DATE(a.start_at) = CURDATE() AND a.status NOT IN ('cancelled','noshow')
             ORDER BY a.start_at ASC"
        );
    }

    public function getNextUpcomingAppointments(int $limit = 3): array
    {
        return $this->db->fetchAll(
            "SELECT a.id, a.title, a.start_at, a.end_at, a.status, a.notes,
                    a.patient_id,
                    p.name AS patient_name, p.species AS patient_species,
                    CONCAT(o.first_name, ' ', o.last_name) AS owner_name,
                    tt.name AS treatment_type_name, tt.color AS treatment_color
             FROM `{$this->t('appointments')}` a
             LEFT JOIN `{$this->t('patients')}` p ON p.id = a.patient_id
             LEFT JOIN `{$this->t('owners')}` o ON o.id = a.owner_id
             LEFT JOIN `{$this->t('treatment_types')}` tt ON tt.id = a.treatment_type_id
             WHERE DATE(a.start_at) > CURDATE() AND a.status NOT IN ('cancelled','noshow')
             ORDER BY a.start_at ASC
             LIMIT ?",
            [$limit]
        );
    }

    public function getPatientTrendData(): array
    {
        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $months[] = date('Y-m', strtotime("-{$i} months"));
        }

        $rows = $this->db->fetchAll(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS count
             FROM `{$this->t('patients')}`
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
             GROUP BY month ORDER BY month ASC"
        );

        $indexed = [];
        foreach ($rows as $r) { $indexed[$r['month']] = (int)$r['count']; }

        $labels = [];
        $data   = [];
        foreach ($months as $m) {
            $dt = \DateTime::createFromFormat('Y-m', $m);
            $labels[] = $dt ? $dt->format('M') : $m;
            $data[]   = $indexed[$m] ?? 0;
        }
        return ['labels' => $labels, 'data' => $data];
    }

    public function getUpcomingBirthdays(int $days = 14): array
    {
        $today    = new \DateTimeImmutable('today');
        $upcoming = [];

        // Patients with birth_date
        $patients = $this->db->fetchAll(
            "SELECT p.id, p.name, p.birth_date, p.species, 'patient' AS type,
                    CONCAT(o.first_name, ' ', o.last_name) AS owner_name, o.id AS owner_id
             FROM `{$this->t('patients')}` p
             LEFT JOIN `{$this->t('owners')}` o ON p.owner_id = o.id
             WHERE p.birth_date IS NOT NULL"
        );

        foreach ($patients as $row) {
            $bday = $this->nextBirthday($row['birth_date'], $today);
            if ($bday === null) continue;
            $diff = (int)$today->diff($bday)->days;
            if ($diff <= $days) {
                $upcoming[] = [
                    'type'       => 'patient',
                    'id'         => $row['id'],
                    'name'       => $row['name'],
                    'sub'        => $row['species'] ? $row['species'] . ($row['owner_name'] ? ' · ' . $row['owner_name'] : '') : ($row['owner_name'] ?? ''),
                    'link'       => '/patienten/' . $row['id'],
                    'birth_date' => $row['birth_date'],
                    'next_bday'  => $bday->format('Y-m-d'),
                    'diff_days'  => $diff,
                    'age_next'   => (int)$bday->format('Y') - (int)substr($row['birth_date'], 0, 4),
                ];
            }
        }

        // Owners with birth_date
        $owners = $this->db->fetchAll(
            "SELECT id, first_name, last_name, birth_date FROM `{$this->t('owners')}` WHERE birth_date IS NOT NULL"
        );

        foreach ($owners as $row) {
            $bday = $this->nextBirthday($row['birth_date'], $today);
            if ($bday === null) continue;
            $diff = (int)$today->diff($bday)->days;
            if ($diff <= $days) {
                $upcoming[] = [
                    'type'       => 'owner',
                    'id'         => $row['id'],
                    'name'       => $row['first_name'] . ' ' . $row['last_name'],
                    'sub'        => 'Tierhalter',
                    'link'       => '/tierhalter/' . $row['id'],
                    'birth_date' => $row['birth_date'],
                    'next_bday'  => $bday->format('Y-m-d'),
                    'diff_days'  => $diff,
                    'age_next'   => (int)$bday->format('Y') - (int)substr($row['birth_date'], 0, 4),
                ];
            }
        }

        usort($upcoming, fn($a, $b) => $a['diff_days'] <=> $b['diff_days']);
        return $upcoming;
    }

    private function nextBirthday(string $birthDate, \DateTimeImmutable $today): ?\DateTimeImmutable
    {
        try {
            $bd   = new \DateTimeImmutable($birthDate);
            $thisYear = $today->format('Y');
            $next = new \DateTimeImmutable($thisYear . '-' . $bd->format('m-d'));
            if ($next < $today) {
                $next = $next->modify('+1 year');
            }
            return $next;
        } catch (\Throwable) {
            return null;
        }
    }

    public function saveLayout(int $userId, array $layout): void
    {
        $this->db->execute(
            "INSERT INTO `{$this->t('user_preferences')}` (user_id, `key`, `value`)
             VALUES (?, 'dashboard_layout', ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)",
            [$userId, json_encode($layout, JSON_UNESCAPED_UNICODE)]
        );
    }

    public function loadLayout(int $userId): ?array
    {
        $row = $this->db->fetchColumn(
            "SELECT `value` FROM `{$this->t('user_preferences')}` WHERE user_id = ? AND `key` = 'dashboard_layout'",
            [$userId]
        );
        if (!$row) return null;
        $decoded = json_decode((string)$row, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function deleteLayout(int $userId): void
    {
        $this->db->execute(
            "DELETE FROM `{$this->t('user_preferences')}` WHERE user_id = ? AND `key` = 'dashboard_layout'",
            [$userId]
        );
    }

    /* ── Patienten nach Tierart (Donut) ── */
    public function getPatientsBySpecies(): array
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT COALESCE(NULLIF(TRIM(species),''), 'Unbekannt') AS label,
                        COUNT(*) AS value
                 FROM `{$this->t('patients')}`
                 WHERE status != 'verstorben'
                 GROUP BY label
                 ORDER BY value DESC
                 LIMIT 10"
            );
            return [
                'labels' => array_column($rows, 'label'),
                'data'   => array_map('intval', array_column($rows, 'value')),
            ];
        } catch (\Throwable) {
            return ['labels' => [], 'data' => []];
        }
    }

    /* ── Termine nach Behandlungsart (Donut) ── */
    public function getAppointmentsByType(): array
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT COALESCE(tt.name, 'Sonstige') AS label,
                        COALESCE(tt.color, '#94a3b8') AS color,
                        COUNT(a.id) AS value
                 FROM `{$this->t('appointments')}` a
                 LEFT JOIN `{$this->t('treatment_types')}` tt ON tt.id = a.treatment_type_id
                 WHERE a.start_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
                   AND a.status NOT IN ('cancelled','noshow')
                 GROUP BY tt.id, tt.name, tt.color
                 ORDER BY value DESC
                 LIMIT 8"
            );
            return [
                'labels' => array_column($rows, 'label'),
                'colors' => array_column($rows, 'color'),
                'data'   => array_map('intval', array_column($rows, 'value')),
            ];
        } catch (\Throwable) {
            return ['labels' => [], 'colors' => [], 'data' => []];
        }
    }

    /* ── Einnahmen nach Zahlungsart (Donut) ── */
    public function getRevenueByPaymentMethod(): array
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT COALESCE(NULLIF(payment_method,''), 'rechnung') AS method,
                        COALESCE(SUM(total_gross), 0) AS amount
                 FROM `{$this->t('invoices')}`
                 WHERE status = 'paid'
                 GROUP BY method"
            );
            $map = [];
            foreach ($rows as $r) { $map[$r['method']] = round((float)$r['amount'], 2); }
            return [
                'labels' => ['Rechnung', 'Barzahlung'],
                'data'   => [$map['rechnung'] ?? 0, $map['bar'] ?? 0],
                'colors' => ['#4f7cff', '#22c55e'],
            ];
        } catch (\Throwable) {
            return ['labels' => ['Rechnung', 'Barzahlung'], 'data' => [0, 0], 'colors' => ['#4f7cff', '#22c55e']];
        }
    }

    /* ── Terminauslastung nach Wochentag (Bar) ── */
    public function getAppointmentsByWeekday(): array
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT DAYOFWEEK(start_at) AS dow, COUNT(*) AS value
                 FROM `{$this->t('appointments')}`
                 WHERE start_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
                   AND status NOT IN ('cancelled','noshow')
                 GROUP BY dow
                 ORDER BY dow ASC"
            );
            $days = [2 => 'Mo', 3 => 'Di', 4 => 'Mi', 5 => 'Do', 6 => 'Fr', 7 => 'Sa', 1 => 'So'];
            $indexed = [];
            foreach ($rows as $r) { $indexed[(int)$r['dow']] = (int)$r['value']; }
            $labels = [];
            $data   = [];
            foreach ($days as $dow => $label) {
                $labels[] = $label;
                $data[]   = $indexed[$dow] ?? 0;
            }
            return ['labels' => $labels, 'data' => $data];
        } catch (\Throwable) {
            return ['labels' => ['Mo','Di','Mi','Do','Fr','Sa','So'], 'data' => [0,0,0,0,0,0,0]];
        }
    }

    /* ── Top 5 Tierhalter nach Umsatz (Horizontal Bar) ── */
    public function getTopOwnersByRevenue(int $limit = 6): array
    {
        try {
            $rows = $this->db->fetchAll(
                "SELECT CONCAT(o.first_name, ' ', o.last_name) AS label,
                        COALESCE(SUM(i.total_gross), 0) AS amount
                 FROM `{$this->t('invoices')}` i
                 JOIN `{$this->t('owners')}` o ON o.id = i.owner_id
                 WHERE i.status = 'paid'
                 GROUP BY o.id, o.first_name, o.last_name
                 ORDER BY amount DESC
                 LIMIT ?",
                [$limit]
            );
            return [
                'labels' => array_column($rows, 'label'),
                'data'   => array_map(fn($r) => round((float)$r['amount'], 2), $rows),
            ];
        } catch (\Throwable) {
            return ['labels' => [], 'data' => []];
        }
    }

    /* ── Umsatz-Prognose nächste 3 Monate (Line) ── */
    public function getRevenueForecast(): array
    {
        try {
            /* Past 6 months actual */
            $past = [];
            for ($i = 5; $i >= 0; $i--) {
                $past[] = date('Y-m', strtotime("-{$i} months"));
            }
            $rows = $this->db->fetchAll(
                "SELECT DATE_FORMAT(issue_date, '%Y-%m') AS period,
                        COALESCE(SUM(total_gross), 0) AS amount
                 FROM `{$this->t('invoices')}`
                 WHERE status = 'paid'
                   AND issue_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                 GROUP BY period ORDER BY period ASC"
            );
            $indexed = [];
            foreach ($rows as $r) { $indexed[$r['period']] = (float)$r['amount']; }

            $actualLabels = [];
            $actualData   = [];
            foreach ($past as $m) {
                $dt = \DateTime::createFromFormat('Y-m', $m);
                $actualLabels[] = $dt ? $dt->format('M y') : $m;
                $actualData[]   = $indexed[$m] ?? 0;
            }

            /* Forecast: simple linear regression on past 6 months */
            $n    = count($actualData);
            $sumX = 0; $sumY = 0; $sumXY = 0; $sumX2 = 0;
            for ($i = 0; $i < $n; $i++) {
                $sumX  += $i; $sumY  += $actualData[$i];
                $sumXY += $i * $actualData[$i]; $sumX2 += $i * $i;
            }
            $denom = ($n * $sumX2 - $sumX * $sumX);
            $slope = $denom != 0 ? ($n * $sumXY - $sumX * $sumY) / $denom : 0;
            $intercept = ($sumY - $slope * $sumX) / $n;

            $forecastLabels = $actualLabels;
            $forecastData   = array_fill(0, $n, null);
            /* Bridge last actual point */
            $forecastData[$n - 1] = $actualData[$n - 1];
            for ($j = 1; $j <= 3; $j++) {
                $dt = new \DateTime('first day of +' . $j . ' month');
                $forecastLabels[] = $dt->format('M y');
                $forecastData[]   = max(0, round($intercept + $slope * ($n - 1 + $j), 2));
            }

            return [
                'labels'   => $forecastLabels,
                'actual'   => $actualData,
                'forecast' => $forecastData,
            ];
        } catch (\Throwable) {
            return ['labels' => [], 'actual' => [], 'forecast' => []];
        }
    }

    /* ── Rechnungseingang der letzten 30 Tage (Area) ── */
    public function getInvoiceInflow(): array
    {
        try {
            $days = [];
            for ($i = 29; $i >= 0; $i--) {
                $days[] = date('Y-m-d', strtotime("-{$i} days"));
            }
            $rows = $this->db->fetchAll(
                "SELECT DATE(issue_date) AS day, COUNT(*) AS count,
                        COALESCE(SUM(total_gross), 0) AS amount
                 FROM `{$this->t('invoices')}`
                 WHERE issue_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 GROUP BY day ORDER BY day ASC"
            );
            $indexed = [];
            foreach ($rows as $r) { $indexed[$r['day']] = [(int)$r['count'], (float)$r['amount']]; }

            $labels  = [];
            $counts  = [];
            $amounts = [];
            foreach ($days as $d) {
                $dt = new \DateTime($d);
                $labels[]  = $dt->format('d.m');
                $counts[]  = $indexed[$d][0] ?? 0;
                $amounts[] = round($indexed[$d][1] ?? 0, 2);
            }
            return ['labels' => $labels, 'counts' => $counts, 'amounts' => $amounts];
        } catch (\Throwable) {
            return ['labels' => [], 'counts' => [], 'amounts' => []];
        }
    }
}
