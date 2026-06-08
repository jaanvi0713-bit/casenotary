<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireClient();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verifyRequest()) {
    flash('error', 'Invalid request.');
    header('Location: ' . clientUrl('pages/notifications.php'));
    exit;
}

$action = $_POST['action'] ?? '';
$userId = Auth::id();
$redirectTo = $action === 'save_preferences'
    ? clientUrl('pages/profile.php')
    : clientUrl('pages/notifications.php');

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

header('Location: ' . $redirectTo);
exit;
