<?php

require_once __DIR__ . '/../core/bootstrap.php';

$settings = getCompanySettings();
$favicon  = $settings['favicon'] ?? null;

if (!$favicon) {
    http_response_code(404);
    exit;
}

$config = require __DIR__ . '/../config/config.php';
$path   = rtrim($config['upload']['path'], '/\\') . '/' . ltrim($favicon, '/');

if (!is_file($path)) {
    http_response_code(404);
    exit;
}

$ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = match ($ext) {
    'png' => 'image/png',
    'ico' => 'image/x-icon',
    default => 'application/octet-stream',
};

header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=86400');
readfile($path);
exit;
