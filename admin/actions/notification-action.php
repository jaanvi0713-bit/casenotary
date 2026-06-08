<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::guardAction();

$action = $_POST['action'] ?? '';
$redirectTo = $action === 'save_preferences'
    ? 'pages/settings.php?tab=notifications'
    : 'pages/notifications.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verifyRequest()) {
    flash('error', 'Invalid request.');
    redirect($redirectTo);
}

$userId = Auth::id();

try {
    switch ($action) {
        case 'mark_all_read':
            markAllNotificationsAsRead($userId);
            flash('success', 'All notifications marked as read.');
            break;

        case 'save_preferences':
            NotificationPreferenceService::save($userId, $_POST['preferences'] ?? []);
            flash('success', 'Notification Preferences saved.');
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

redirect($redirectTo);
