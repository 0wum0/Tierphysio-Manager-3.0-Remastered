<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Repositories\PackageRepository;
use App\Repositories\OwnerRepository;

/**
 * PackageController — Pakete / Mehrfachkarten (Hundeschule).
 *
 * Verwaltet zwei Ebenen:
 *   - Paket-Katalog (Admin legt Angebote wie "10er Gruppen-Karte" an)
 *   - Gekaufte Pakete pro Halter (Balances) — werden bei Kursteilnahme eingelöst
 */
class PackageController extends Controller
{
    private PackageRepository $packages;
    private OwnerRepository $owners;

    public function __construct(
        \App\Core\View $view,
        \App\Core\Session $session,
        \App\Core\Config $config,
        \App\Core\Translator $translator,
        PackageRepository $packages,
        OwnerRepository $owners,
    ) {
        parent::__construct($view, $session, $config, $translator);
        $this->packages = $packages;
        $this->owners   = $owners;
    }

    /* ═════════════════════ Übersicht ═════════════════════ */

    public function index(array $params = []): void
    {
        $this->requireFeature('dogschool_packages');

        /* Outdated balances automatisch als expired markieren — einmal pro
         * Request reicht; idempotent und sehr schnell. */
        $this->packages->expireOutdated();

        $catalog  = $this->packages->listAll();
        $page     = max(1, (int)$this->get('page', 1));
        $status   = (string)$this->get('status', 'active');
        $balances = $this->packages->listBalances($page, 50, $status);

        $this->render('dogschool/packages/index.twig', [
            'page_title'  => 'Pakete / Karten',
            'active_nav'  => 'packages',
            'catalog'     => $catalog,
            'balances'    => $balances,
            'filter_status' => $status,
        ]);
    }

    /* ═════════════════════ Katalog ═════════════════════ */

    public function create(array $params = []): void
    {
        $this->requireFeature('dogschool_packages');
        $this->render('dogschool/packages/form.twig', [
            'page_title' => 'Neues Paket',
            'active_nav' => 'packages',
            'package'    => [
                'id'            => 0,
                'name'          => '',
                'description'   => '',
                'type'          => 'multi',
                'total_units'   => 10,
                'valid_days'    => 365,
                'price_cents'   => 0,
                'is_active'     => 1,
            ],
            'is_new' => true,
        ]);
    }

    public function edit(array $params = []): void
    {
        $this->requireFeature('dogschool_packages');
        $id  = (int)($params['id'] ?? 0);
        $pkg = $this->packages->findById($id);
        if (!$pkg) {
            $this->flash('error', 'Paket nicht gefunden.');
            $this->redirect('/pakete');
            return;
        }
        $this->render('dogschool/packages/form.twig', [
            'page_title' => 'Paket bearbeiten',
            'active_nav' => 'packages',
            'package'    => $pkg,
            'is_new'     => false,
        ]);
    }

    public function store(array $params = []): void
    {
        $this->requireFeature('dogschool_packages');
        $this->validateCsrf();

        $data = $this->collectPackageData();
        if ($data['name'] === '') {
            $this->flash('error', 'Name darf nicht leer sein.');
            $this->redirect('/pakete/neu');
            return;
        }
        $id = $this->packages->create($data);
        $this->flash('success', 'Paket angelegt.');
        $this->redirect('/pakete');
    }

    public function update(array $params = []): void
    {
        $this->requireFeature('dogschool_packages');
        $this->validateCsrf();

        $id = (int)($params['id'] ?? 0);
        if (!$this->packages->findById($id)) {
            $this->flash('error', 'Paket nicht gefunden.');
            $this->redirect('/pakete');
            return;
        }
        $this->packages->update($id, $this->collectPackageData());
        $this->flash('success', 'Paket aktualisiert.');
        $this->redirect('/pakete');
    }

    public function delete(array $params = []): void
    {
        $this->requireFeature('dogschool_packages');
        $this->validateCsrf();

        $id = (int)($params['id'] ?? 0);
        $this->packages->delete($id);
        $this->flash('success', 'Paket entfernt.');
        $this->redirect('/pakete');
    }

    /* ═════════════════════ Balance-Verkauf ═════════════════════ */

    public function sell(array $params = []): void
    {
        $this->requireFeature('dogschool_packages');
        $this->validateCsrf();

        $packageId = (int)$this->post('package_id', 0);
        $ownerId   = (int)$this->post('owner_id', 0);
        $patientId = ((int)$this->post('patient_id', 0)) ?: null;
        $notes     = trim((string)$this->post('notes', '')) ?: null;

        if ($packageId === 0 || $ownerId === 0) {
            $this->flash('error', 'Paket und Halter sind erforderlich.');
            $this->redirect('/pakete');
            return;
        }

        $ok = $this->packages->createBalance($packageId, $ownerId, $patientId, $notes);
        if ($ok === false) {
            $this->flash('error', 'Paket-Verkauf fehlgeschlagen.');
        } else {
            $this->flash('success', 'Paket verkauft.');
        }
        $this->redirect('/pakete');
    }

    public function showBalance(array $params = []): void
    {
        $this->requireFeature('dogschool_packages');

        $id = (int)($params['balance_id'] ?? 0);
        $balance = $this->packages->findBalance($id);
        if (!$balance) {
            $this->flash('error', 'Kontostand nicht gefunden.');
            $this->redirect('/pakete');
            return;
        }
        $redemptions = $this->packages->redemptionsForBalance($id);

        $this->render('dogschool/packages/balance.twig', [
            'page_title'  => 'Paket-Kontostand',
            'active_nav'  => 'packages',
            'balance'     => $balance,
            'redemptions' => $redemptions,
        ]);
    }

    public function redeem(array $params = []): void
    {
        $this->requireFeature('dogschool_packages');
        $this->validateCsrf();

        $balanceId    = (int)($params['balance_id'] ?? 0);
        $enrollmentId = ((int)$this->post('enrollment_id', 0)) ?: null;
        $sessionId    = ((int)$this->post('session_id', 0))    ?: null;
        $units        = max(1, (int)$this->post('units', 1));
        $notes        = trim((string)$this->post('notes', '')) ?: null;
        $userId       = (int)($this->session->getUser()['id'] ?? 0) ?: null;

        $ok = $this->packages->redeem($balanceId, $units, $enrollmentId, $sessionId, $userId, $notes);
        if (!$ok) {
            $this->flash('error', 'Einlösung fehlgeschlagen (kein Guthaben oder Status ≠ aktiv).');
        } else {
            $this->flash('success', 'Einheit(en) eingelöst.');
        }
        $this->redirect('/pakete/balance/' . $balanceId);
    }

    /* ═════════════════════ Helpers ═════════════════════ */

    private function collectPackageData(): array
    {
        return [
            'name'        => trim((string)$this->post('name', '')),
            'description' => trim((string)$this->post('description', '')),
            'type'        => (string)$this->post('type', 'multi'),
            'total_units' => max(1, (int)$this->post('total_units', 1)),
            'valid_days'  => ((int)$this->post('valid_days', 0)) ?: null,
            'price_cents' => max(0, (int)round(((float)$this->post('price_eur', 0)) * 100)),
            'is_active'   => (int)(bool)$this->post('is_active', 0),
        ];
    }
}
