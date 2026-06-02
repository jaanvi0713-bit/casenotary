<?php

require_once __DIR__ . '/../core/bootstrap.php';
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$message = trim($input['message'] ?? $_POST['message'] ?? '');
if ($message === '') {
    echo json_encode(['success' => false, 'message' => 'Please enter a message.']);
    exit;
}
$reply = generateChatbotReply($message);
echo json_encode([
    'success' => true,
    'reply'   => $reply,
]);
the apply and reset should be removed in the admin portal for the following; clients, cases, payments and appointments

Sear
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
$isRegenerate = filter_var($input['regenerate'] ?? false, FILTER_VALIDATE_BOOL);

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

$hasUploads = false;
if ($isMultipart && !empty($_FILES['attachments'])) {
    $uploadNames = $_FILES['attachments']['name'] ?? '';
    if (is_array($uploadNames)) {
        foreach ($uploadNames as $uploadName) {
            if (trim((string) $uploadName) !== '') {
                $hasUploads = true;
                break;
            }
        }
    } else {
        $hasUploads = trim((string) $uploadNames) !== '';
    }
}

if ($message === '' && !$hasUploads) {
    echo json_encode(['success' => false, 'message' => 'Please enter a message or attach a file.']);
    exit;
}

try {
    if ($hasUploads) {
        $reply = ChatbotService::replyWithAttachments($message, $_FILES['attachments']);
    } elseif ($isRegenerate) {
        $reply = ChatbotService::regenerate($message);
    } else {
        $reply = ChatbotService::reply($message);
    }

    $attachmentNames = '';
    if ($hasUploads) {
        $names = is_array($_FILES['attachments']['name'] ?? null)
            ? $_FILES['attachments']['name']
            : [$_FILES['attachments']['name']];
        $attachmentNames = implode('|', array_filter(array_map('strval', $names)));
    }

    $response = ['success' => true, 'reply' => $reply];

    if (ChatbotChatStore::isAvailable()) {
        $saved = ChatbotChatStore::appendExchange($userId, $conversationId, [
            ['type' => 'user', 'text' => $message ?: '(attached files)', 'attachments' => $attachmentNames],
            ['type' => 'bot', 'text' => $reply, 'attachments' => ''],
        ]);
        $response['conversation_id'] = (int) ($saved['id'] ?? 0);
        $response['conversation_title'] = (string) ($saved['title'] ?? 'New chat');
    }

    echo json_encode($response, JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    http_response_code(500);
    $config = require __DIR__ . '/../config/config.php';
    $errorMessage = 'Assistant error. Please try again.';
    if (!empty($config['debug'])) {
        $errorMessage = 'Assistant error: ' . $e->getMessage();
    }
    echo json_encode(['success' => false, 'message' => $errorMessage], JSON_INVALID_UTF8_SUBSTITUTE);
}
