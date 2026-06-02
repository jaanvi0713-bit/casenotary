<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAdmin();

$pageTitle = 'Notifications';
$userId    = Auth::id();
$q = trim((string) ($_GET['q'] ?? ''));
$readFilter = trim((string) ($_GET['read'] ?? ''));
$perPage = 10;
$page = requestPageNumber();
$totalNotifications = countNotifications($userId, $q, $readFilter);
$totalPages = max(1, (int) ceil($totalNotifications / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$notifications = getNotificationsPaginated($userId, $page, $perPage, $q, $readFilter);
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
            <form method="post" action="<?= url('actions/notification-action.php') ?>" class="m-0">
                <?= CSRF::field() ?>
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn btn-light btn-sm">Mark all read</button>
            </form>
        <?php endif; ?>
    </div>
    <form method="get" class="table-toolbar">
        <div class="table-search">
            <i class="bi bi-search"></i>
            <input type="search" class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="Search notifications...">
        </div>
        <select class="form-select form-select-sm table-filter" name="read">
            <option value="">All</option>
            <option value="unread" <?= $readFilter === 'unread' ? 'selected' : '' ?>>Unread</option>
            <option value="read" <?= $readFilter === 'read' ? 'selected' : '' ?>>Read</option>
        </select>
        <button type="submit" class="btn btn-light btn-sm">Apply</button>
        <a href="<?= url('pages/notifications.php') ?>" class="btn btn-soft btn-sm">Reset</a>
    </form>
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
                            <?php if (!empty($notif['link'])): ?>
                                <a href="<?= url('actions/notification-read.php?id=' . (int) $notif['id']) ?>" class="btn btn-soft btn-sm">Open</a>
                            <?php endif; ?>
                            <form method="post" action="<?= url('actions/notification-action.php') ?>" class="m-0">
                                <?= CSRF::field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="notification_id" value="<?= (int) $notif['id'] ?>">
                                <button type="submit" class="btn btn-soft-danger btn-sm">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
                <small class="text-muted">
                    Showing <?= count($notifications) ?> of <?= $totalNotifications ?> notifications
                </small>
                <?= renderPaginationNav($page, $totalPages) ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
