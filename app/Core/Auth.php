<?php

declare(strict_types=1);

namespace App\Core;

class Auth
{
    private static ?Session $session = null;

    public static function init(Session $session): void
    {
        self::$session = $session;
    }

    public static function user(): ?array
    {
        if (!self::$session) {
            return null;
        }
        return self::$session->getUser();
    }

    public static function isLoggedIn(): bool
    {
        if (!self::$session) {
            return false;
        }
        return self::$session->isLoggedIn();
    }

    public static function isAdmin(): bool
    {
        if (!self::$session) {
            return false;
        }
        return self::$session->isAdmin();
    }
}
