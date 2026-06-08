<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireClient();

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    header('Location: ' . clientUrl('pages/dashboard.php'));
    exit;
}

$notif = Database::fetch(
    'SELECT * FROM notifications WHERE id = ? AND user_id = ?',
    [$id, Auth::id()]
);

if (!$notif) {
    flash('error', 'Notification not found.');
    header('Location: ' . clientUrl('pages/dashboard.php'));
    exit;
}

markNotificationAsRead($id, Auth::id());

$target = clientNotificationRedirectTarget($notif);

if (str_starts_with($target, 'http://') || str_starts_with($target, 'https://')) {
    header('Location: ' . $target);
    exit;
}

header('Location: ' . clientUrl($target));
exit;
