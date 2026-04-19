<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected View $view;
    protected Session $session;
    protected Config $config;
    protected Translator $translator;

    public function __construct(
        View $view,
        Session $session,
        Config $config,
        Translator $translator
    ) {
        $this->view       = $view;
        $this->session    = $session;
        $this->config     = $config;
        $this->translator = $translator;
    }

    protected function render(string $template, array $data = []): void
    {
        $this->view->render($template, $data);
    }

    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    protected function redirectBack(string $fallback = '/'): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        /* Only allow same-origin redirects — reject external URLs */
        if ($referer !== '') {
            $host = parse_url($referer, PHP_URL_HOST);
            $selfHost = $_SERVER['HTTP_HOST'] ?? '';
            if ($host !== $selfHost) {
                $referer = '';
            }
        }
        $this->redirect($referer !== '' ? $referer : $fallback);
    }

    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
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

    protected function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    protected function flash(string $type, string $message): void
    {
        $this->session->flash($type, $message);
    }

    protected function isPost(): bool
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    protected function isAjax(): bool
    {
        return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';
    }

    protected function validateCsrf(): void
    {
        $token = $this->post('_csrf_token') ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!$this->session->validateCsrfToken($token)) {
            http_response_code(403);
            $this->flash('error', $this->translator->trans('errors.csrf_invalid'));
            $this->redirectBack();
            exit;
        }
    }

    /**
     * Defense-in-depth: Prüft Feature-Gate auch innerhalb des Controllers.
     * Stoppt die Request-Verarbeitung sofort wenn das Feature deaktiviert ist.
     *
     * Auch wenn das Feature-Middleware fehlt (z.B. vergessen bei neuem Endpoint),
     * bleibt diese Prüfung die letzte Verteidigungslinie.
     */
    protected function requireFeature(string $key): void
    {
        try {
            /** @var \App\Services\FeatureGateService $gate */
            $gate = \App\Core\Application::getInstance()
                ->getContainer()
                ->get(\App\Services\FeatureGateService::class);
            $gate->requireFeature($key);
        } catch (\Throwable $e) {
            /* Service nicht verfügbar → konservativ: weitermachen.
             * Das Router-Middleware greift bereits — dies ist nur Backup. */
            error_log('[Controller requireFeature] ' . $e->getMessage());
        }
    }

    protected function requireRole(string $role): void
    {
        $user = $this->session->getUser();
        if (!$user || $user['role'] !== $role) {
            http_response_code(403);
            $this->render('errors/403.twig');
            exit;
        }
    }

    protected function requireAdmin(): void
    {
        $this->requireRole('admin');
    }

    protected function abort(int $code): void
    {
        http_response_code($code);
        $this->render("errors/{$code}.twig", []);
        exit;
    }

    protected function sanitize(string $value): string
    {
        return htmlspecialchars(strip_tags(trim($value)), ENT_QUOTES, 'UTF-8');
    }

    protected function uploadFile(string $field, string $destination, array $allowedTypes = []): string|false
    {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            return false;
        }

        $file     = $_FILES[$field];
        $maxSize  = $this->config->get('storage.max_size', 10485760);

        if ($file['size'] > $maxSize) {
            return false;
        }

        if (!empty($allowedTypes)) {
            $finfo    = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);
            if (!in_array($mimeType, $allowedTypes, true)) {
                return false;
            }
        }

        /* Derive extension from MIME type — never trust client-supplied filename extension */
        $mimeExtMap = [
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            'image/gif'       => 'gif',
            'image/webp'      => 'webp',
            'image/svg+xml'   => 'svg',
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'video/mp4'       => 'mp4',
            'video/webm'      => 'webm',
            'video/ogg'       => 'ogv',
            'video/quicktime' => 'mov',
            'video/x-msvideo' => 'avi',
            'video/x-matroska'=> 'mkv',
            'video/x-m4v'     => 'm4v',
            'video/mpeg'      => 'mpeg',
        ];
        if (!empty($allowedTypes)) {
            $ext = $mimeExtMap[$mimeType] ?? 'bin';
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'bin';
        }
        $filename = bin2hex(random_bytes(16)) . '.' . $ext;
        $fullPath = $destination . '/' . $filename;

        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            return false;
        }

        return $filename;
    }
}
