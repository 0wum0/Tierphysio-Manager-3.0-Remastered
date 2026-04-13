<?php

declare(strict_types=1);

namespace Saas\Core;

class Session
{
    public function __construct(private Config $config) {}

    public function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $lifetime = $this->config->get('session.lifetime', 120) * 60;
        $secure   = $this->config->get('session.secure', true);
        $domain   = $this->config->get('session.domain', '');

        $cookieParams = [
            'lifetime' => $lifetime,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        if ($domain !== '') {
            $cookieParams['domain'] = $domain;
        }

        session_set_cookie_params($cookieParams);
        ini_set('session.gc_maxlifetime', (string)$lifetime);

        session_name('SAAS_SESSION');
        session_start();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }

    public function getFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    public function destroy(): void
    {
        session_destroy();
        $_SESSION = [];
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public function csrf(): string
    {
        if (!isset($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public function verifyCsrf(string $token): bool
    {
        return hash_equals($this->csrf(), $token);
    }
}
