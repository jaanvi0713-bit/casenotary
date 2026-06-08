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
        'wikipedia'       => [
            'enabled' => true,
        ],
    ],

    // Optional: enable for the widest answers (any topic). Set OPENAI_API_KEY in the environment.
    'ai' => [
        'enabled'     => filter_var(getenv('CHATBOT_AI_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN),
        'provider'    => getenv('CHATBOT_AI_PROVIDER') ?: 'openai',
        'api_key'     => getenv('OPENAI_API_KEY') ?: '',
        'model'       => getenv('CHATBOT_AI_MODEL') ?: 'gpt-4o-mini',
        'base_url'    => getenv('CHATBOT_AI_BASE_URL') ?: 'https://api.openai.com/v1',
        'ollama_url'  => getenv('OLLAMA_URL') ?: 'http://127.0.0.1:11434',
        'ollama_model'=> getenv('OLLAMA_MODEL') ?: 'llama3.2',
    ],
];
