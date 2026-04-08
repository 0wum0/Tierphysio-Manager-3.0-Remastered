<?php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

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
        if (!$isRealFile && !str_starts_with($path, '/portal') && !str_starts_with($path, '/api/')) {
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
require_once ROOT_PATH . '/app/helpers.php';

use App\Core\Application;

try {
    $app = new Application(ROOT_PATH);
    $app->run();
} catch (\Throwable $e) {
    $debug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $logDir  = ROOT_PATH . '/storage/logs';
    if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
    @file_put_contents(
        $logDir . '/error.log',
        '[' . date('Y-m-d H:i:s') . '] ' . get_class($e) . ': ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n"
        . $e->getTraceAsString() . "\n\n",
        FILE_APPEND
    );
    http_response_code(500);
    if ($debug) {
        echo '<pre style="background:#1a1a2e;color:#e94560;padding:20px;font-family:monospace;">';
        echo '<strong>Bootstrap Error:</strong> ' . htmlspecialchars($e->getMessage()) . "\n\n";
        echo '<strong>File:</strong> ' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . "\n\n";
        echo '<strong>Trace:</strong>' . "\n" . htmlspecialchars($e->getTraceAsString());
        echo '</pre>';
    } else {
        echo '<!DOCTYPE html><html><body style="background:#0f0f1a;color:#fff;font-family:sans-serif;text-align:center;padding:100px;">';
        echo '<h1>500 - Interner Serverfehler</h1><p>Bitte versuchen Sie es später erneut.</p>';
        echo '</body></html>';
    }
}
