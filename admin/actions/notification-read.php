<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAdmin();

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    redirect('pages/dashboard.php');
}

$notif = Database::fetch(
    'SELECT * FROM notifications WHERE id = ? AND user_id = ?',
    [$id, Auth::id()]
);

if (!$notif) {
    flash('error', 'Notification not found.');
    redirect('pages/dashboard.php');
}

markNotificationAsRead($id, Auth::id());

$target = resolveNotificationRedirect($notif['link'] ?? null);

if (str_starts_with($target, 'http://') || str_starts_with($target, 'https://')) {
    header('Location: ' . $target);
    exit;
}

redirect($target);
