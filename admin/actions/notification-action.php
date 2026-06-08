<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::guardAction();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verifyRequest()) {
    flash('error', 'Invalid request.');
    redirect('pages/notifications.php');
}

$action = $_POST['action'] ?? '';
$userId = Auth::id();

try {
    switch ($action) {
        case 'mark_all_read':
            markAllNotificationsAsRead($userId);
            flash('success', 'All notifications marked as read.');
            break;

        case 'delete':
            $id = (int) ($_POST['notification_id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Invalid notification.');
            }
            deleteNotification($id, $userId);
            flash('success', 'Notification removed.');
            break;

        default:
            flash('error', 'Unknown action.');
    }
} catch (Throwable $e) {
    flash('error', $e->getMessage());
}

redirect('pages/notifications.php');
