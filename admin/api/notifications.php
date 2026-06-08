<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

header('Content-Type: application/json');

if (!Auth::check()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$userId = Auth::id();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $input = $_POST;
    if (empty($input)) {
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

    $action = (string) ($input['action'] ?? '');

    if ($action === 'mark_all_read') {
        markAllNotificationsAsRead($userId);
        echo json_encode([
            'success' => true,
            'unread_count' => 0,
            'notifications' => formatNotificationsForApi(getRecentNotifications($userId, 5, false), Auth::isClient()),
        ]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

echo json_encode([
    'success' => true,
    'unread_count' => getUnreadNotificationCount($userId),
    'notifications' => formatNotificationsForApi(getRecentNotifications($userId, 5, false), Auth::isClient()),
]);

/**
 * @param list<array<string, mixed>> $rows
 * @return list<array<string, mixed>>
 */
function formatNotificationsForApi(array $rows, bool $isClient): array
{
    $items = [];

    foreach ($rows as $row) {
        $id = (int) ($row['id'] ?? 0);
        $message = (string) ($row['message'] ?? '');
        $readUrl = $isClient
            ? clientUrl('actions/notification-read.php?id=' . $id)
            : url('actions/notification-read.php?id=' . $id);

        $items[] = [
            'id' => $id,
            'title' => (string) ($row['title'] ?? ''),
            'message' => mb_strimwidth($message, 0, 72, '...'),
            'type' => (string) ($row['type'] ?? 'system'),
            'icon' => notificationIcon((string) ($row['type'] ?? 'system')),
            'is_read' => !empty($row['is_read']),
            'time_ago' => timeAgo((string) ($row['created_at'] ?? '')),
            'href' => $readUrl,
        ];
    }

    return $items;
}
