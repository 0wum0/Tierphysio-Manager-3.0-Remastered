<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('TEMPLATES_PATH', ROOT_PATH . '/templates');
define('PLUGINS_PATH', ROOT_PATH . '/plugins');
define('MIGRATIONS_PATH', ROOT_PATH . '/migrations');

/* ── Subdomain routing: portal.* → /portal/* ──────────────────────────────
   If the request comes from the portal subdomain, transparently prefix
   REQUEST_URI with /portal so the existing router handles it normally.
   Static asset requests (files that actually exist) are left untouched.   */
(function () {
    $host = strtolower($_SERVER['HTTP_HOST'] ?? '');
    if (str_starts_with($host, 'portal.')) {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        // Strip query string for the file-existence check
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';

        // Already prefixed or is a real file/dir → do nothing
        $isRealFile = is_file(__DIR__ . $path) || is_dir(__DIR__ . $path);
        if (!$isRealFile && !str_starts_with($path, '/portal')) {
            // Empty path → send to portal login
            if ($path === '/' || $path === '') {
                $_SERVER['REQUEST_URI'] = '/portal/login';
            } else {
                // Prepend /portal, keep query string intact
                $qs = parse_url($uri, PHP_URL_QUERY);
                $_SERVER['REQUEST_URI'] = '/portal' . $path . ($qs ? '?' . $qs : '');
            }
        }
    }
})();

require_once ROOT_PATH . '/vendor/autoload.php';

use App\Core\Application;

$app = new Application(ROOT_PATH);
$app->run();
