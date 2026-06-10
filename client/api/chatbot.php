<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

Auth::requireClient();

$config = require __DIR__ . '/../../admin/config/config.php';
$tokenName = $config['security']['csrf_token_name'];
$input = $_POST;
if (empty($input)) {
    $decoded = json_decode(file_get_contents('php://input') ?: '', true);
    $input = is_array($decoded) ? $decoded : [];
}

$token = $input[$tokenName] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
if (!CSRF::validate($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid request. Please refresh the page.']);
    exit;
}

$message = trim((string) ($input['message'] ?? ''));
if ($message === '') {
    echo json_encode(['success' => false, 'message' => 'Please enter a message.']);
    exit;
}

try {
    echo json_encode([
        'success' => true,
        'reply'   => ClientChatbotService::reply($message),
    ], JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Assistant error. Please try again.']);
}
