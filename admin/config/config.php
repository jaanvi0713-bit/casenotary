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

    // Logo used on client letters, invoices, and receipts for every workspace.
    'document_branding' => [
        'company_name' => 'Wharf Notaries',
        'company_slug' => 'wharf-notaries',
        // Profile with full letterhead (address, colours). Omit to auto-pick the richest match.
        'company_id'   => 1,
    ],

    // Local AI assistant via Ollama.
    'assistant' => [
        'enabled'      => filter_var(getenv('ASSISTANT_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'base_url'     => getenv('OLLAMA_URL') ?: 'http://127.0.0.1:11434',
        'model'        => getenv('OLLAMA_MODEL') ?: 'qwen2.5:0.5b',
        'vision_model' => getenv('OLLAMA_VISION_MODEL') ?: 'llava',
        'timeout'      => (int) (getenv('OLLAMA_TIMEOUT') ?: 120),
    ],

];
