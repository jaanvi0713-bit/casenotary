<?php



declare(strict_types=1);



require_once __DIR__ . '/../core/bootstrap.php';



header('Content-Type: application/json');



if (!Auth::check()) {

    http_response_code(401);

    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);

    exit;

}



if (!Auth::can(RoleAccess::PERMISSION_ASSISTANT)) {

    http_response_code(403);

    echo json_encode(['success' => false, 'message' => 'You do not have permission to use the AI assistant.']);

    exit;

}



AssistantService::ensureSessionIntegrity();



/** @return array<string, mixed> */

function assistantLibraryPayload(): array

{

    return [

        'conversation_id'   => AssistantService::conversationId(),

        'conversations'     => AssistantService::library(),

        'library_available' => AssistantChatStore::isAvailable(),

    ];

}



$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';



if ($method === 'GET') {

    AssistantActions::rehydrateDraftsFromHistory(AssistantService::history());

    echo json_encode(array_merge([

        'success'  => true,

        'status'   => AssistantService::status(),

        'messages' => AssistantService::history(),

        'prompts'  => AssistantService::quickPrompts(),

    ], assistantLibraryPayload()));

    exit;

}



if ($method !== 'POST') {

    http_response_code(405);

    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);

    exit;

}



$isMultipart = str_contains(strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? '')), 'multipart/form-data');

$input = $_POST;

if (!$isMultipart && $input === []) {

    $decoded = json_decode(file_get_contents('php://input') ?: '', true);

    $input = is_array($decoded) ? $decoded : [];

}



$config = require __DIR__ . '/../config/config.php';

$csrfName = $config['security']['csrf_token_name'];

$csrfToken = $input[$csrfName] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;



if (!CSRF::validate($csrfToken)) {

    http_response_code(403);

    echo json_encode(['success' => false, 'message' => 'Invalid security token.']);

    exit;

}



$action = (string) ($input['action'] ?? 'chat');



try {

    if ($action === 'clear' || $action === 'new_chat') {

        AssistantService::startNewChat();

        echo json_encode(array_merge([

            'success'  => true,

            'messages' => [],

            'status'   => AssistantService::status(),

        ], assistantLibraryPayload()));

        exit;

    }



    if ($action === 'edit_message') {

        $index = (int) ($input['index'] ?? -1);

        if ($index < 0) {

            throw new InvalidArgumentException('Message index is required.');

        }



        $messages = AssistantService::truncateHistory($index);

        echo json_encode(array_merge([

            'success'  => true,

            'messages' => $messages,

            'status'   => AssistantService::status(),

        ], assistantLibraryPayload()));

        exit;

    }



    if ($action === 'load_chat') {

        $conversationId = (int) ($input['conversation_id'] ?? 0);

        if ($conversationId <= 0) {

            throw new InvalidArgumentException('Conversation id is required.');

        }



        $messages = AssistantService::loadConversation($conversationId);

        echo json_encode(array_merge([

            'success'  => true,

            'messages' => $messages,

            'status'   => AssistantService::status(),

        ], assistantLibraryPayload()));

        exit;

    }



    if ($action === 'rename_chat') {

        $conversationId = (int) ($input['conversation_id'] ?? 0);

        $title = trim((string) ($input['title'] ?? ''));

        if ($conversationId <= 0 || $title === '') {

            throw new InvalidArgumentException('Conversation id and title are required.');

        }



        AssistantService::renameConversation($conversationId, $title);

        echo json_encode(array_merge([

            'success' => true,

            'title'   => $title,

        ], assistantLibraryPayload()));

        exit;

    }



    if ($action === 'delete_chat') {

        $conversationId = (int) ($input['conversation_id'] ?? 0);

        if ($conversationId <= 0) {

            throw new InvalidArgumentException('Conversation id is required.');

        }



        AssistantService::deleteConversation($conversationId);

        echo json_encode(array_merge([

            'success'  => true,

            'messages' => AssistantService::history(),

            'status'   => AssistantService::status(),

        ], assistantLibraryPayload()));

        exit;

    }



    if ($action === 'confirm') {

        $draftId = trim((string) ($input['draft_id'] ?? ''));

        if ($draftId === '') {

            throw new InvalidArgumentException('Draft id is required.');

        }



        $result = AssistantService::confirmDraft($draftId);

        AssistantService::rememberExchange('Confirm action', $result);



        echo json_encode(array_merge([

            'success'  => true,

            'reply'    => $result['content'],

            'type'     => $result['type'] ?? 'text',

            'messages' => AssistantService::history(),

            'status'   => AssistantService::status(),

        ], assistantLibraryPayload()));

        exit;

    }



    if ($action !== 'chat') {

        http_response_code(400);

        echo json_encode(['success' => false, 'message' => 'Unknown action.']);

        exit;

    }



    $message = trim((string) ($input['message'] ?? ''));

    $upload = isset($_FILES['attachment']) && is_array($_FILES['attachment']) ? $_FILES['attachment'] : null;



    if ($message === '' && ($upload === null || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)) {

        throw new InvalidArgumentException('Message or attachment is required.');

    }



    $result = AssistantService::handle($message, $upload);

    AssistantService::rememberExchange($message !== '' ? $message : '[Document upload]', $result);



    echo json_encode(array_merge([

        'success'  => true,

        'reply'    => $result['content'],

        'type'     => $result['type'] ?? 'text',

        'draft'    => $result['draft'] ?? null,

        'alerts'   => $result['alerts'] ?? [],

        'messages' => AssistantService::history(),

        'status'   => AssistantService::status(),

    ], assistantLibraryPayload()));

} catch (InvalidArgumentException | RuntimeException $e) {

    http_response_code($e instanceof InvalidArgumentException ? 400 : 500);

    echo json_encode([

        'success' => false,

        'message' => $e->getMessage(),

        'status'  => AssistantService::status(),

    ]);

} catch (Throwable $e) {

    error_log('Assistant API [' . get_class($e) . ']: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([

        'success' => false,

        'message' => 'Something went wrong. Please try again.',

        'status'  => AssistantService::status(),

    ]);

}


