<?php
declare(strict_types=1);

/**
 * Serves files from storage/uploads/ and storage/patients/.
 * Used when public/ is the Apache DocumentRoot and storage/ is outside it.
 */

$allowed = ['uploads', 'patients'];

$dir  = $_GET['dir']  ?? '';
$file = $_GET['file'] ?? '';

if (
    !in_array($dir, $allowed, true)
    || $file === ''
    || str_contains($file, '/')
    || str_contains($file, '\\')
    || str_contains($file, '..')
) {
    http_response_code(403);
    exit;
}

$storagePath = dirname(__DIR__) . '/storage/' . $dir . '/' . basename($file);

if (!is_file($storagePath)) {
    http_response_code(404);
    exit;
}

$ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$mime = match ($ext) {
    'jpg', 'jpeg' => 'image/jpeg',
    'png'         => 'image/png',
    'gif'         => 'image/gif',
    'webp'        => 'image/webp',
    'svg'         => 'image/svg+xml',
    'pdf'         => 'application/pdf',
    default       => 'application/octet-stream',
};

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($storagePath));
header('Cache-Control: public, max-age=86400');
readfile($storagePath);
exit;
