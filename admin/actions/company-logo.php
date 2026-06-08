<?php

require_once __DIR__ . '/../core/bootstrap.php';

$settings = getCompanySettings();
$logo     = $settings['logo'] ?? null;

if (!$logo) {
    http_response_code(404);
    exit;
}

$config = require __DIR__ . '/../config/config.php';
$path   = rtrim($config['upload']['path'], '/\\') . '/' . ltrim($logo, '/');

if (!is_file($path)) {
    http_response_code(404);
    exit;
}

$ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = match ($ext) {
    'png'  => 'image/png',
    'jpg', 'jpeg' => 'image/jpeg',
    'webp' => 'image/webp',
    'svg'  => 'image/svg+xml',
    default => 'application/octet-stream',
};

header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=86400');
readfile($path);
exit;
