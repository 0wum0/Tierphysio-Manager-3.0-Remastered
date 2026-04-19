<?php

declare(strict_types=1);

namespace Saas\Controllers;

use Saas\Core\Controller;
use Saas\Core\Database;
use Saas\Core\Session;
use Saas\Core\View;

/**
 * SaaS-Admin: Zentrales Feature-Gating-Dashboard.
 *
 * Drei Steuerungs-Ebenen:
 *   1. Global Kill-Switch  → saas_feature_flags.global_enabled
 *   2. Plan-Zuordnung      → plans.features (JSON Array)
 *   3. Tenant-Override     → tenants.features_override (JSON Map)
 *
 * Jede Änderung wirkt in der Praxis-App nach maximal 5 Minuten
 * (FeatureGateService::CACHE_TTL) ODER sofort über forceSync()
 * beim nächsten Request.
 */
class FeaturesController extends Controller
{
    public function __construct(
        View                     $view,
        Session                  $session,
        private readonly Database $db,
    ) {
        parent::__construct($view, $session);
    }

    /* ═══════════════════════════════════════════════════════════
       1. Übersicht: alle Features + globale Kill-Switches
       ═══════════════════════════════════════════════════════════ */
    public function index(array $params = []): void
    {
        $this->requireAuth();

        $features = $this->db->fetchAll(
            "SELECT feature_key, label, description, required_plan, global_enabled
               FROM saas_feature_flags
              ORDER BY FIELD(required_plan,'basic','pro','ultra'), label ASC"
        );

        $plans = $this->db->fetchAll("SELECT id, slug, name, features FROM plans WHERE is_active = 1 ORDER BY price_month ASC");

        /* Plan-Feature-Matrix für die UI aufbauen */
        $planMatrix = [];
        foreach ($plans as $plan) {
            $list = json_decode((string)($plan['features'] ?? '[]'), true);
            $planMatrix[$plan['id']] = [
                'plan'     => $plan,
                'features' => is_array($list) ? array_flip($list) : [],
            ];
        }

        $this->render('admin/features/index.twig', [
            'features'    => $features,
            'plans'       => $plans,
            'plan_matrix' => $planMatrix,
            'page_title'  => 'Feature-Gating',
        ]);
    }

    /* ═══════════════════════════════════════════════════════════
       2. Globalen Kill-Switch togglen
       POST /admin/features/toggle-global
       ═══════════════════════════════════════════════════════════ */
    public function toggleGlobal(array $params = []): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $key     = trim((string)$this->post('feature_key', ''));
        $enabled = (int)(bool)$this->post('enabled', 0);

        if ($key === '') {
            $this->session->flash('error', 'Feature-Key fehlt.');
            $this->redirect('/admin/features');
        }

        try {
            $this->db->execute(
                "UPDATE saas_feature_flags SET global_enabled = ? WHERE feature_key = ?",
                [$enabled, $key]
            );
            $state = $enabled ? 'aktiviert' : 'DEAKTIVIERT (global)';
            $this->session->flash('success', "Feature „{$key}" . '" ' . $state);
        } catch (\Throwable $e) {
            $this->session->flash('error', 'Fehler: ' . $e->getMessage());
        }

        $this->redirect('/admin/features');
    }

    /* ═══════════════════════════════════════════════════════════
       3. Plan-Feature-Matrix aktualisieren
       POST /admin/features/update-plan-matrix
       Formular liefert: plan_features[plan_id][] = feature_key
       ═══════════════════════════════════════════════════════════ */
    public function updatePlanMatrix(array $params = []): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $matrix = $_POST['plan_features'] ?? [];
        if (!is_array($matrix)) {
            $matrix = [];
        }

        $allValid = array_column(
            $this->db->fetchAll("SELECT feature_key FROM saas_feature_flags"),
            'feature_key'
        );

        $updated = 0;
        foreach ($matrix as $planId => $features) {
            if (!is_array($features)) {
                $features = [];
            }
            /* Nur bekannte Keys zulassen */
            $features = array_values(array_intersect(array_map('strval', $features), $allValid));
            try {
                $this->db->execute(
                    "UPDATE plans SET features = ? WHERE id = ?",
                    [json_encode($features), (int)$planId]
                );
                $updated++;
            } catch (\Throwable $e) {
                error_log('[FeaturesController updatePlanMatrix] ' . $e->getMessage());
            }
        }

        $this->session->flash('success', "Plan-Feature-Matrix aktualisiert ({$updated} Pläne).");
        $this->redirect('/admin/features');
    }

    /* ═══════════════════════════════════════════════════════════
       4. Per-Tenant Override
       POST /admin/features/tenant-override
       Formular: tenant_id, feature_key, mode (on|off|reset)
       ═══════════════════════════════════════════════════════════ */
    public function setTenantOverride(array $params = []): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $tenantId = (int)$this->post('tenant_id', 0);
        $key      = trim((string)$this->post('feature_key', ''));
        $mode     = (string)$this->post('mode', 'reset');

        if ($tenantId <= 0 || $key === '') {
            $this->session->flash('error', 'Ungültige Parameter.');
            $this->redirect('/admin/tenants/' . $tenantId);
        }

        try {
            $row = $this->db->fetch("SELECT features_override FROM tenants WHERE id = ?", [$tenantId]);
            $map = [];
            if ($row && !empty($row['features_override'])) {
                $decoded = json_decode((string)$row['features_override'], true);
                if (is_array($decoded)) {
                    $map = $decoded;
                }
            }

            switch ($mode) {
                case 'on':
                    $map[$key] = true;
                    break;
                case 'off':
                    $map[$key] = false;
                    break;
                case 'reset':
                default:
                    unset($map[$key]);
                    break;
            }

            $this->db->execute(
                "UPDATE tenants SET features_override = ? WHERE id = ?",
                [empty($map) ? null : json_encode($map), $tenantId]
            );

            $this->session->flash('success', "Override für „{$key}" . '" gesetzt: ' . $mode);
        } catch (\Throwable $e) {
            $this->session->flash('error', 'Fehler: ' . $e->getMessage());
        }

        $this->redirect('/admin/tenants/' . $tenantId);
    }
}
