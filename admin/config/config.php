<?php

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$projectRoot = realpath(__DIR__ . '/../..');
$docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: null;
$relativeRoot = '';

if ($projectRoot && $docRoot && str_starts_with(strtolower($projectRoot), strtolower($docRoot))) {
    $relativeRoot = str_replace('\\', '/', substr($projectRoot, strlen($docRoot)));
}

if ($relativeRoot === '' && !empty($_SERVER['SCRIPT_NAME'])
    && preg_match('#^(/[^/]+)/(?:admin|client)/#', str_replace('\\', '/', $_SERVER['SCRIPT_NAME']), $scriptMatches)) {
    $relativeRoot = $scriptMatches[1];
}

$baseUrl = $scheme . '://' . $host . $relativeRoot;

return [
    'app_name'    => 'Case Notary Platform',
    'app_url'     => $baseUrl . '/admin',
    'client_url'  => $baseUrl . '/client',
    'timezone'    => 'America/New_York',
    'debug'       => true,

    'currency' => [
        'code'   => 'GBP',
        'symbol' => '£',
        'locale' => 'en-GB',
    ],

    'session' => [
        'name'     => 'NOTARY_SESSION',
        'lifetime' => 7200,
    ],

    'upload' => [
        'max_size'      => 10 * 1024 * 1024,
        'allowed_types' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'zip'],
        'path'          => __DIR__ . '/../uploads/',
    ],

    'security' => [
        'csrf_token_name' => '_csrf_token',
    ],

    'chatbot' => [
        'max_attachments' => 10,
        'max_size'        => 10 * 1024 * 1024,
        'allowed_types'   => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'txt', 'doc', 'docx'],
        'upload_path'     => __DIR__ . '/../uploads/chatbot/',
    ],
];
