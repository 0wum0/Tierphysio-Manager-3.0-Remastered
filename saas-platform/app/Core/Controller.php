<?php

declare(strict_types=1);

namespace Saas\Core;

abstract class Controller
{
    public function __construct(
        protected View    $view,
        protected Session $session
    ) {}

    protected function render(string $template, array $data = []): void
    {
        echo $this->view->render($template, $data);
    }

    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function requireAuth(): void
    {
        if (!$this->session->has('saas_user_id')) {
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
                || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
                || (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'multipart'));
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Session abgelaufen. Bitte neu einloggen.']);
                exit;
            }
            $this->redirect('/admin/login');
        }
    }

    protected function requireRole(string ...$roles): void
    {
        $this->requireAuth();
        $userRole = $this->session->get('saas_role', '');
        if (!in_array($userRole, $roles, true)) {
            http_response_code(403);
            $this->render('errors/403.twig');
            exit;
        }
    }

    protected function verifyCsrf(): void
    {
        $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!$this->session->verifyCsrf($token)) {
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
                || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
                || (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'multipart'));
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                http_response_code(419);
                echo json_encode(['success' => false, 'message' => 'CSRF-Token abgelaufen. Bitte Seite neu laden.']);
                exit;
            }
            http_response_code(419);
            $this->session->flash('error', 'Sicherheitstoken abgelaufen. Bitte erneut versuchen.');
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/admin');
        }
    }

    protected function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    protected function post(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $default;
    }

    protected function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    protected function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    protected function notFound(): void
    {
        http_response_code(404);
        $this->render('errors/404.twig');
        exit;
    }
}
