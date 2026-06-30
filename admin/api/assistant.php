<?php



declare(strict_types=1);



require_once __DIR__ . '/../core/bootstrap.php';



header('Content-Type: application/json');

// Never leak PHP warnings/notices into API output.
@ini_set('display_errors', '0');
error_reporting(E_ALL);

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    // Do not turn notices/deprecations (e.g. iconv on PDF bytes) into fatal API failures.
    if (!in_array($severity, [E_ERROR, E_RECOVERABLE_ERROR, E_USER_ERROR], true)) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if (!$error) {
        return;
    }

    $fatal = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array((int) ($error['type'] ?? 0), $fatal, true)) {
        return;
    }

    error_log(sprintf(
        'Assistant API fatal: %s in %s:%d',
        (string) ($error['message'] ?? 'unknown'),
        (string) ($error['file'] ?? ''),
        (int) ($error['line'] ?? 0)
    ));

    if (headers_sent()) {
        return;
    }

    http_response_code(500);
    header('Content-Type: application/json');

    echo assistantJsonEncode([
        'success' => false,
        'message' => 'Assistant request failed. Please retry.',
    ]);
});



if (!Auth::check()) {

    http_response_code(401);

    echo assistantJsonEncode(['success' => false, 'message' => 'Unauthorized.']);

    exit;

}



if (!Auth::can(RoleAccess::PERMISSION_ASSISTANT)) {

    http_response_code(403);

    echo assistantJsonEncode(['success' => false, 'message' => 'You do not have permission to use the AI assistant.']);

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



/** @return list<array<string, mixed>> */
function assistantParseUploads(): array
{
    $out = [];

    if (isset($_FILES['attachments']) && is_array($_FILES['attachments']['name'] ?? null)) {
        $names = $_FILES['attachments']['name'];
        if (!is_array($names)) {
            $names = [$names];
        }

        foreach (array_keys($names) as $index) {
            if (($_FILES['attachments']['error'][$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }

            $out[] = [
                'name'     => (string) ($names[$index] ?? ''),
                'type'     => (string) ($_FILES['attachments']['type'][$index] ?? ''),
                'tmp_name' => (string) ($_FILES['attachments']['tmp_name'][$index] ?? ''),
                'error'    => (int) ($_FILES['attachments']['error'][$index] ?? 0),
                'size'     => (int) ($_FILES['attachments']['size'][$index] ?? 0),
            ];
        }
    }

    if ($out === [] && isset($_FILES['attachment']) && is_array($_FILES['attachment'])
        && ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        $out[] = $_FILES['attachment'];
    }

    return $out;
}

/** @return list<array{id?: string, name?: string, text?: string, source?: string}> */
function assistantParseDocumentItems(array $input): array
{
    if (!empty($input['document_items'])) {
        $decoded = json_decode((string) $input['document_items'], true);
        if (is_array($decoded)) {
            $items = [];
            foreach ($decoded as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $items[] = [
                    'id'     => (string) ($row['id'] ?? ''),
                    'name'   => (string) ($row['name'] ?? ''),
                    'text'   => (string) ($row['text'] ?? ''),
                    'source' => (string) ($row['source'] ?? ''),
                ];
            }

            return $items;
        }
    }

    $text = trim((string) ($input['document_text'] ?? ''));
    if ($text === '') {
        return [];
    }

    return [[
        'name'   => trim((string) ($input['document_name'] ?? 'Uploaded document')),
        'text'   => $text,
        'source' => trim((string) ($input['document_source'] ?? '')),
    ]];
}

/** @return list<array{name: string}> */
function assistantAttachmentMetaFromRequest(array $uploads, array $clientItems): array
{
    $out = [];
    $seen = [];

    foreach ($uploads as $upload) {
        if (!is_array($upload)) {
            continue;
        }

        $name = trim((string) ($upload['name'] ?? ''));
        if ($name === '') {
            continue;
        }

        $key = strtolower($name);
        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $out[] = ['name' => $name];
    }

    foreach ($clientItems as $item) {
        if (!is_array($item)) {
            continue;
        }

        $name = trim((string) ($item['name'] ?? ''));
        if ($name === '') {
            continue;
        }

        if (strtolower($name) === 'uploaded document' && $out !== []) {
            continue;
        }

        $key = strtolower($name);
        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $out[] = ['name' => $name];
    }

    return $out;
}



$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';



if ($method === 'GET') {

    AssistantActions::rehydrateDraftsFromHistory(AssistantService::history());

    echo assistantJsonEncode(array_merge([

        'success'  => true,

        'status'   => AssistantService::status(),

        'messages' => AssistantService::history(),

        'prompts'  => AssistantService::quickPrompts(),

    ], assistantLibraryPayload()));

    exit;

}



if ($method !== 'POST') {

    http_response_code(405);

    echo assistantJsonEncode(['success' => false, 'message' => 'Method not allowed.']);

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

    echo assistantJsonEncode(['success' => false, 'message' => 'Invalid security token.']);

    exit;

}



$action = (string) ($input['action'] ?? 'chat');



try {

    if ($action === 'clear' || $action === 'new_chat') {

        AssistantService::startNewChat();

        echo assistantJsonEncode(array_merge([

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

        echo assistantJsonEncode(array_merge([

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

        echo assistantJsonEncode(array_merge([

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

        echo assistantJsonEncode(array_merge([

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

        echo assistantJsonEncode(array_merge([

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



        echo assistantJsonEncode(array_merge([

            'success'  => true,

            'reply'    => $result['content'],

            'type'     => $result['type'] ?? 'text',

            'messages' => AssistantService::history(),

            'status'   => AssistantService::status(),

        ], assistantLibraryPayload()));

        exit;

    }



    if ($action === 'update_draft') {

        $draftId = trim((string) ($input['draft_id'] ?? ''));

        if ($draftId === '') {

            throw new InvalidArgumentException('Draft id is required.');

        }

        $fields = $input['fields'] ?? null;

        if (!is_array($fields) || $fields === []) {

            throw new InvalidArgumentException('At least one field is required.');

        }

        $previewUpdates = [];

        foreach ($fields as $label => $value) {

            $previewUpdates[(string) $label] = (string) $value;

        }

        $draft = AssistantDraftEdit::update($draftId, $previewUpdates);

        AssistantService::replaceDraftInHistory($draftId, $draft);



        echo assistantJsonEncode(array_merge([

            'success'      => true,

            'reply'        => 'Draft updated. Review the changes and click **Confirm** when ready.',

            'type'         => 'text',

            'draft_update' => $draft,

            'messages'     => AssistantService::history(),

            'status'       => AssistantService::status(),

        ], assistantLibraryPayload()));

        exit;

    }



    if ($action !== 'chat') {

        http_response_code(400);

        echo assistantJsonEncode(['success' => false, 'message' => 'Unknown action.']);

        exit;

    }



    $message = trim((string) ($input['message'] ?? ''));

    $uploads = assistantParseUploads();
    $clientDocumentItems = assistantParseDocumentItems($input);
    $documentText = trim((string) ($input['document_text'] ?? ''));
    $documentSource = trim((string) ($input['document_source'] ?? ''));

    $hasUpload = $uploads !== [];
    $hasClientItems = $clientDocumentItems !== [] || $documentText !== '';

    if ($message === '' && !$hasUpload && !$hasClientItems) {

        throw new InvalidArgumentException('Message or attachment is required.');

    }



    $result = AssistantService::handle(
        $message,
        $uploads[0] ?? null,
        $documentText,
        $documentSource,
        $uploads,
        $clientDocumentItems
    );

    AssistantService::rememberExchange(
        $message,
        $result,
        $uploads !== [] ? assistantAttachmentMetaFromRequest($uploads, $clientDocumentItems) : []
    );

    $response = array_merge([

        'success'  => true,

        'reply'    => $result['content'],

        'type'     => $result['type'] ?? 'text',

        'draft'    => $result['draft'] ?? null,

        'draft_update' => $result['draft_update'] ?? null,

        'alerts'   => $result['alerts'] ?? [],

        'messages' => AssistantService::history(),

        'status'   => AssistantService::status(),

    ], assistantLibraryPayload());

    $cachedItems = AssistantDocuments::cachedDocumentItems();
    if ($cachedItems !== []) {
        $response['document_context'] = AssistantDocuments::cachedDocumentText();
        $response['document_items'] = $cachedItems;
    }

    echo assistantJsonEncode($response);

} catch (InvalidArgumentException | RuntimeException $e) {

    http_response_code($e instanceof InvalidArgumentException ? 400 : 500);

    echo assistantJsonEncode([

        'success' => false,

        'message' => $e->getMessage(),

        'status'  => AssistantService::status(),

    ]);

} catch (Throwable $e) {

    error_log('Assistant API [' . get_class($e) . ']: ' . $e->getMessage());

    http_response_code(500);

    echo assistantJsonEncode([

        'success' => false,

        'message' => 'Something went wrong. Please try again.',

        'status'  => AssistantService::status(),

    ]);

}


