<?php

declare(strict_types=1);

namespace Saas\Controllers;

use Saas\Core\Controller;
use Saas\Core\View;
use Saas\Core\Session;
use Saas\Repositories\LegalRepository;

class LegalController extends Controller
{
    public function __construct(
        View                    $view,
        Session                 $session,
        private LegalRepository $legalRepo
    ) {
        parent::__construct($view, $session);
    }

    public function index(array $params = []): void
    {
        $this->requireAuth();

        $docs = $this->legalRepo->all();
        $this->render('admin/legal/index.twig', [
            'docs'       => $docs,
            'page_title' => 'Rechtliche Dokumente',
        ]);
    }

    public function edit(array $params = []): void
    {
        $this->requireAuth();

        $doc = $this->legalRepo->find((int)($params['id'] ?? 0));
        if (!$doc) {
            $this->notFound();
        }

        $this->render('admin/legal/edit.twig', [
            'doc'        => $doc,
            'page_title' => 'Dokument bearbeiten: ' . $doc['title'],
        ]);
    }

    public function update(array $params = []): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $id  = (int)($params['id'] ?? 0);
        $doc = $this->legalRepo->find($id);
        if (!$doc) {
            $this->notFound();
        }

        $this->legalRepo->update($id, [
            'title'   => trim($this->post('title', $doc['title'])),
            'content' => $this->post('content', $doc['content']),
            'version' => trim($this->post('version', $doc['version'])),
        ]);

        $this->session->flash('success', 'Dokument aktualisiert.');
        $this->redirect('/admin/legal');
    }

    public function view(array $params = []): void
    {
        $slug = $params['slug'] ?? '';
        $doc  = $this->legalRepo->findBySlug($slug);
        if (!$doc) {
            $this->notFound();
        }

        $this->render('legal/view.twig', [
            'doc'        => $doc,
            'page_title' => $doc['title'],
        ]);
    }

    public function impressum(array $params = []): void
    {
        $this->view(['slug' => 'impressum']);
    }

    public function datenschutz(array $params = []): void
    {
        $this->view(['slug' => 'datenschutz']);
    }
}
