<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$config = require __DIR__ . '/../config/config.php';
$tokenName = $config['security']['csrf_token_name'];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$isMultipart = str_contains($contentType, 'multipart/form-data');
$input = $isMultipart ? $_POST : (json_decode(file_get_contents('php://input'), true) ?: []);

$token = $input[$tokenName] ?? $_POST[$tokenName] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
$message = trim((string) ($input['message'] ?? $_POST['message'] ?? ''));
$action = trim((string) ($input['action'] ?? ''));
$conversationId = (int) ($input['conversation_id'] ?? $_POST['conversation_id'] ?? 0);

if (!CSRF::validate($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid request. Please refresh the page.']);
    exit;
}

$userId = (int) (Auth::id() ?? 0);
if ($userId <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Please log in again.']);
    exit;
}

if ($action !== '') {
    if (!ChatbotChatStore::isAvailable()) {
        echo json_encode(['success' => false, 'message' => ChatbotChatStore::unavailableMessage()]);
        exit;
    }

    switch ($action) {
        case 'list':
            echo json_encode([
                'success'       => true,
                'conversations' => ChatbotChatStore::listForUser($userId),
            ]);
            exit;

        case 'get':
            $id = (int) ($input['id'] ?? 0);
            $conversation = ChatbotChatStore::getForUser($userId, $id);
            if ($conversation === null) {
                echo json_encode(['success' => false, 'message' => 'Chat not found.']);
                exit;
            }
            echo json_encode(['success' => true, 'conversation' => $conversation]);
            exit;

        case 'save':
            $id = (int) ($input['id'] ?? 0);
            $messages = is_array($input['messages'] ?? null) ? $input['messages'] : [];
            $title = isset($input['title']) ? trim((string) $input['title']) : null;
            if ($id <= 0) {
                $id = ChatbotChatStore::create($userId, $title ?: 'New chat');
            }
            if (ChatbotChatStore::getForUser($userId, $id) === null) {
                echo json_encode(['success' => false, 'message' => 'Chat not found.']);
                exit;
            }
            ChatbotChatStore::save($userId, $id, $messages, $title);
            echo json_encode([
                'success'      => true,
                'conversation' => ChatbotChatStore::getForUser($userId, $id),
            ]);
            exit;

        case 'rename':
            $id = (int) ($input['id'] ?? 0);
            $title = trim((string) ($input['title'] ?? ''));
            if ($id <= 0 || $title === '') {
                echo json_encode(['success' => false, 'message' => 'Title is required.']);
                exit;
            }
            echo json_encode([
                'success' => ChatbotChatStore::rename($userId, $id, $title),
            ]);
            exit;

        case 'delete':
            $id = (int) ($input['id'] ?? 0);
            echo json_encode([
                'success' => $id > 0 && ChatbotChatStore::delete($userId, $id),
            ]);
            exit;

        case 'clear':
            chatbotClearSession();
            echo json_encode(['success' => true]);
            exit;
    }
}

if ($message === '') {
    echo json_encode(['success' => false, 'message' => 'Please enter a message.']);
    exit;
}

try {
    $reply = ChatbotService::reply($message);

    $response = ['success' => true, 'reply' => $reply];

    if (ChatbotChatStore::isAvailable()) {
        $saved = ChatbotChatStore::appendExchange($userId, $conversationId, [
            ['type' => 'user', 'text' => $message, 'attachments' => ''],
            ['type' => 'bot', 'text' => $reply, 'attachments' => ''],
        ]);
        $response['conversation_id'] = (int) ($saved['id'] ?? 0);
        $response['conversation_title'] = (string) ($saved['title'] ?? 'New chat');
    }

    echo json_encode($response);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Assistant error. Please try again.']);
}
