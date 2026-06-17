<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requirePage('notifications');

$tab = trim((string) ($_GET['tab'] ?? 'alerts'));
if (!in_array($tab, ['alerts', 'messages'], true)) {
    $tab = 'alerts';
}

$pageTitle = 'Notifications';
$userId    = Auth::id();

$q          = trim((string) ($_GET['q'] ?? ''));
$readFilter = trim((string) ($_GET['read'] ?? ''));
$msgStatus  = trim((string) ($_GET['status'] ?? ''));
$perPage    = 10;
$page       = requestPageNumber();

$unreadCount        = getUnreadNotificationCount($userId);
$unreadMessageCount = ClientMessageService::countAdminUnread();

if ($tab === 'messages') {
    $total        = ClientMessageService::countThreads($q, $msgStatus);
    $totalPages   = max(1, (int) ceil($total / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $threads     = ClientMessageService::listThreadsForAdmin($q, $msgStatus, $page, $perPage);
    $showingFrom = $total > 0 ? paginationOffset($page, $perPage) + 1 : 0;
    $showingTo   = min($total, $page * $perPage);
    $pageSubtitle = $unreadMessageCount . ' message' . ($unreadMessageCount === 1 ? '' : 's') . ' unread';
} else {
    $totalNotifications = countNotifications($userId, $q, $readFilter);
    $totalPages         = max(1, (int) ceil($totalNotifications / $perPage));
    if ($page > $totalPages) {
        $page = $totalPages;
    }
    $notifications = getNotificationsPaginated($userId, $page, $perPage, $q, $readFilter);
    $showingFrom   = $totalNotifications > 0 ? paginationOffset($page, $perPage) + 1 : 0;
    $showingTo     = min($totalNotifications, $page * $perPage);
    $pageSubtitle  = $unreadCount . ' alert' . ($unreadCount === 1 ? '' : 's') . ' unread';
    if ($unreadMessageCount > 0) {
        $pageSubtitle .= ' · ' . $unreadMessageCount . ' message' . ($unreadMessageCount === 1 ? '' : 's');
    }
}

require __DIR__ . '/../includes/header.php';
?>

<div class="saas-card">
    <ul class="nav nav-tabs settings-tabs px-3 pt-3" role="tablist">
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'alerts' ? 'active' : '' ?>" href="<?= url('pages/notifications.php?tab=alerts') ?>">
                Alerts
                <?php if ($unreadCount > 0): ?>
                    <span class="badge bg-primary-subtle text-primary ms-1"><?= $unreadCount ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'messages' ? 'active' : '' ?>" href="<?= url('pages/notifications.php?tab=messages') ?>">
                Client Messages
                <?php if ($unreadMessageCount > 0): ?>
                    <span class="badge bg-primary-subtle text-primary ms-1"><?= $unreadMessageCount ?></span>
                <?php endif; ?>
            </a>
        </li>
    </ul>

    <div class="saas-card-header appointment-list-header border-top-0">
        <div>
            <h2 class="saas-card-title"><?= $tab === 'messages' ? 'Client Messages' : 'Alerts' ?></h2>
            <p class="saas-card-subtitle mb-0">
                <?php if ($tab === 'messages'): ?>
                    <?= $total ?> total · <?= $unreadMessageCount ?> unread
                <?php else: ?>
                    <?= $totalNotifications ?> total · <?= $unreadCount ?> unread
                <?php endif; ?>
            </p>
        </div>
        <?php if ($tab === 'alerts'): ?>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <a href="<?= url('pages/settings.php?tab=notifications') ?>" class="btn btn-light btn-sm">
                    <i class="bi bi-gear me-1"></i> Preferences
                </a>
                <?php if ($unreadCount > 0): ?>
                    <form method="post" action="<?= url('actions/notification-action.php') ?>" class="m-0">
                        <?= CSRF::field() ?>
                        <input type="hidden" name="action" value="mark_all_read">
                        <button type="submit" class="btn btn-light btn-sm">Mark all read</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <form method="get" class="table-toolbar notifications-filter-toolbar">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <div class="table-search">
            <i class="bi bi-search"></i>
            <input type="search"
                   class="form-control form-control-sm"
                   name="q"
                   value="<?= e($q) ?>"
                   placeholder="<?= $tab === 'messages' ? 'Search messages...' : 'Search notifications...' ?>">
        </div>
        <?php if ($tab === 'messages'): ?>
            <select class="form-select form-select-sm table-filter" name="status" onchange="this.form.requestSubmit()">
                <option value="">All</option>
                <option value="unread" <?= $msgStatus === 'unread' ? 'selected' : '' ?>>Unread</option>
                <option value="read" <?= $msgStatus === 'read' ? 'selected' : '' ?>>Read</option>
            </select>
        <?php else: ?>
            <select class="form-select form-select-sm table-filter" name="read" onchange="this.form.requestSubmit()">
                <option value="">All</option>
                <option value="unread" <?= $readFilter === 'unread' ? 'selected' : '' ?>>Unread</option>
                <option value="read" <?= $readFilter === 'read' ? 'selected' : '' ?>>Read</option>
            </select>
        <?php endif; ?>
    </form>

    <?php if ($tab === 'messages'): ?>
        <div class="card-body p-0">
            <?php require __DIR__ . '/partials/notifications-messages.php'; ?>
        </div>
    <?php else: ?>
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
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-2 border-top">
                    <small class="text-muted">
                        Showing <?= $showingFrom ?>–<?= $showingTo ?> of <?= $totalNotifications ?> notifications
                    </small>
                    <?= renderPaginationNav($page, $totalPages) ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
