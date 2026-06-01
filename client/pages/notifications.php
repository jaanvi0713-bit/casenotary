<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireClient();

$clientId = Auth::clientId();
if (!$clientId) {
    flash('error', 'Client profile not found.');
    header('Location: ' . adminUrl('auth/login.php?portal=client'));
    exit;
}

$pageTitle = 'Notifications';
$userId = Auth::id();
$notifications = getAllNotifications($userId, 100);
$unreadCount = getUnreadNotificationCount($userId);
$pageSubtitle = $unreadCount . ' unread';

require __DIR__ . '/../includes/header.php';
?>

<div class="saas-card">
    <div class="saas-card-header appointment-list-header">
        <div>
            <h2 class="saas-card-title">Notifications</h2>
            <p class="saas-card-subtitle mb-0"><?= count($notifications) ?> total · <?= $unreadCount ?> unread</p>
        </div>
        <?php if ($unreadCount > 0): ?>
            <form method="post" action="<?= clientUrl('actions/notification-action.php') ?>" class="m-0">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn btn-light btn-sm">Mark all read</button>
            </form>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if (empty($notifications)): ?>
            <div class="empty-state py-5">
                <i class="bi bi-bell"></i>
                <p class="mb-0">No notifications yet.</p>
            </div>
        <?php else: ?>
            <div class="notification-list">
                <?php foreach ($notifications as $notif): ?>
                    <div class="notification-list-item <?= empty($notif['is_read']) ? 'unread' : '' ?>">
                        <div class="notification-list-icon">
                            <i class="bi <?= notificationIcon($notif['type']) ?>"></i>
                        </div>
                        <div class="notification-list-body">
                            <strong><?= e($notif['title']) ?></strong>
                            <p class="mb-1"><?= e($notif['message']) ?></p>
                            <small class="text-muted"><?= timeAgo($notif['created_at']) ?></small>
                        </div>
                        <div class="notification-list-actions d-flex gap-2">
                            <a href="<?= clientUrl('actions/notification-read.php?id=' . (int) $notif['id']) ?>" class="btn btn-soft btn-sm">Open</a>
                            <form method="post" action="<?= clientUrl('actions/notification-action.php') ?>" class="m-0">
                                <?= CSRF::field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="notification_id" value="<?= (int) $notif['id'] ?>">
                                <button type="submit" class="btn btn-soft-danger btn-sm">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
