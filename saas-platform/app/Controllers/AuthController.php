<?php

declare(strict_types=1);

namespace Saas\Controllers;

use Saas\Core\Controller;
use Saas\Core\View;
use Saas\Core\Session;
use Saas\Core\Config;
use Saas\Repositories\AdminRepository;

class AuthController extends Controller
{
    public function __construct(
        View                      $view,
        Session                   $session,
        private Config            $config,
        private AdminRepository   $adminRepo
    ) {
        parent::__construct($view, $session);
    }

    public function loginForm(array $params = []): void
    {
        if ($this->session->has('saas_user_id')) {
            $this->redirect('/admin');
        }
        $this->render('auth/login.twig');
    }

    public function login(array $params = []): void
    {
        $this->verifyCsrf();

        $email    = trim($this->post('email', ''));
        $password = $this->post('password', '');

        if (!$email || !$password) {
            $this->session->flash('error', 'Bitte E-Mail und Passwort eingeben.');
            $this->redirect('/admin/login');
        }

        $admin = $this->adminRepo->findByEmail($email);

        if (!$admin || !password_verify($password, $admin['password'])) {
            $this->session->flash('error', 'Ungültige Anmeldedaten.');
            $this->redirect('/admin/login');
        }

        $this->session->regenerate();
        $this->session->set('saas_user_id', (int)$admin['id']);
        $this->session->set('saas_user',    $admin['name']);
        $this->session->set('saas_email',   $admin['email']);
        $this->session->set('saas_role',    $admin['role']);

        $this->adminRepo->updateLastLogin((int)$admin['id']);

        $this->session->flash('success', 'Willkommen zurück, ' . $admin['name'] . '!');
        $this->redirect('/admin');
    }

    public function logout(array $params = []): void
    {
        $this->session->destroy();
        $this->redirect('/admin/login');
    }
}
