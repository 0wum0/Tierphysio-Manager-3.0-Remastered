<?php
require 'vendor/autoload.php';
$loader = new \Twig\Loader\FilesystemLoader([
    __DIR__,
    __DIR__ . '/storage/themes/smart-tierphysio',
]);
$twig = new \Twig\Environment($loader);
$files = [
    'storage/themes/smart-tierphysio/layout.twig',
    'plugins/owner-portal/templates/admin_messages.twig',
    'plugins/owner-portal/templates/admin_message_thread.twig',
    'plugins/owner-portal/templates/owner_messages.twig',
    'plugins/owner-portal/templates/owner_message_thread.twig',
];
foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    if (!file_exists($path)) { echo "MISSING: $file\n"; continue; }
    try {
        $src = file_get_contents($path);
        $twig->parse($twig->tokenize(new \Twig\Source($src, $file)));
        echo "OK: $file\n";
    } catch (\Exception $e) {
        echo "ERROR in $file:\n  " . $e->getMessage() . "\n";
    }
}
