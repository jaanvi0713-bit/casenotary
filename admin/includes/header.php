<?php
$company     = getCompanySettings();
$user        = Auth::user();
$unreadCount = getUnreadNotificationCount(Auth::id());
$navNotifications = getRecentNotifications(Auth::id(), 5, true);
$headerCsrfName = (require __DIR__ . '/../config/config.php')['security']['csrf_token_name'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/theme-head.php'; ?>
    <meta name="csrf-token" content="<?= e(CSRF::generateToken()) ?>">
    <title><?= e($pageTitle ?? 'Dashboard') ?> — <?= e(companyBrandName($company)) ?></title>
    <?= renderFaviconTags($company) ?>
    <?= renderCompanyFontStylesheet($company) ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= asset('css/app.css') ?>" rel="stylesheet">
    <link href="<?= asset('css/theme.css') ?>" rel="stylesheet">
    <?php if (!empty($pageStyles)): ?>
        <?= $pageStyles ?>
    <?php endif; ?>
    <style>
        :root {
            --primary: <?= e($company['primary_color']) ?>;
            --secondary: <?= e($company['secondary_color']) ?>;
            --dark-accent: <?= e($company['dark_accent']) ?>;
            --font-family: <?= companyFontCssStack($company) ?>;
        }
        body { font-family: var(--font-family); }
    </style>
</head>
<body<?= !empty($pageBodyClass) ? ' class="' . e($pageBodyClass) . '"' : '' ?>>
    <div class="app-wrapper">
        <?php require __DIR__ . '/sidebar.php'; ?>

        <div class="main-content" id="mainContent">
            <header class="topbar">
                <div class="topbar-left">
                    <button type="button" class="sidebar-toggle d-lg-none" id="sidebarToggle" aria-label="Open menu">
                        <i class="bi bi-list"></i>
                    </button>
                    <div class="topbar-title">
                    <?php if (companyLogoUrl($company)): ?>
                        <div class="topbar-brand-logo-wrap" aria-hidden="true">
                            <?= renderCompanyLogo('topbar', $company, 'admin') ?>
                        </div>
                    <?php endif; ?>
                    <div class="topbar-title-text">
                        <div class="topbar-page-title"><?= e($pageTitle ?? 'Dashboard') ?></div>
                        <p class="topbar-page-subtitle<?= empty($pageSubtitle) ? ' topbar-page-subtitle--placeholder' : '' ?>"><?= !empty($pageSubtitle) ? e($pageSubtitle) : "\u{00a0}" ?></p>
                    </div>
                </div>
                </div>

                <div class="topbar-actions">
                    <?php require __DIR__ . '/theme-toggle.php'; ?>
                    <div
                        id="topbarNotifications"
                        class="dropdown topbar-dropdown"
                        data-api-url="<?= e(url('api/notifications.php')) ?>"
                        data-csrf-name="<?= e($headerCsrfName) ?>"
                    >
                        <button type="button" class="topbar-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
                            <i class="bi bi-bell"></i>
                            <?php if ($unreadCount > 0): ?>
                                <span class="notification-dot"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span>
                            <?php endif; ?>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end notification-dropdown">
                            <div class="dropdown-header notification-dropdown__header">
                                <div class="notification-dropdown__title">
                                    <span>Notifications</span>
                                    <span id="notificationHeaderBadge" class="badge rounded-pill bg-primary<?= $unreadCount > 0 ? '' : ' d-none' ?>"><?= $unreadCount ?> new</span>
                                </div>
                                <button
                                    type="button"
                                    id="notificationMarkAllRead"
                                    class="btn btn-link btn-sm notification-dropdown__mark-all<?= $unreadCount > 0 ? '' : ' d-none' ?>"
                                >Mark all read</button>
                            </div>
                            <div id="notificationDropdownList">
                                <?php if (empty($navNotifications)): ?>
                                    <div class="dropdown-item-text text-muted text-center py-4 small">No notifications</div>
                                <?php else: ?>
                                    <?php foreach ($navNotifications as $notif): ?>
                                        <a href="<?= url('actions/notification-read.php?id=' . (int) $notif['id']) ?>" class="dropdown-item notification-item<?= empty($notif['is_read']) ? ' unread' : '' ?>">
                                            <div class="notification-icon">
                                                <i class="bi <?= notificationIcon($notif['type']) ?>"></i>
                                            </div>
                                            <div class="notification-content">
                                                <strong><?= e($notif['title']) ?></strong>
                                                <p><?= e(mb_strimwidth($notif['message'], 0, 72, '...')) ?></p>
                                                <small><?= timeAgo($notif['created_at']) ?></small>
                                            </div>
                                        </a>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a href="<?= url('pages/notifications.php') ?>" class="dropdown-item text-center small fw-semibold">View all notifications</a>
                        </div>
                    </div>

                    <div class="dropdown topbar-dropdown">
                        <button type="button" class="topbar-profile" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="profile-avatar"><?= e(userInitials($user)) ?></div>
                            <div class="profile-info d-none d-md-block">
                                <span class="profile-name"><?= e(userFullName($user)) ?></span>
                                <span class="profile-role"><?= e(RoleAccess::roleLabel(Auth::role())) ?></span>
                            </div>
                            <i class="bi bi-chevron-down profile-chevron d-none d-md-block"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end profile-dropdown">
                            <li class="dropdown-header profile-dropdown-header">
                                <strong><?= e(userFullName($user)) ?></strong>
                                <small><?= e($user['email']) ?></small>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= url('pages/settings.php?tab=profile') ?>"><i class="bi bi-person me-2"></i>My Profile</a></li>
                            <?php if (Auth::can(RoleAccess::PERMISSION_SETTINGS)): ?>
                                <li><a class="dropdown-item" href="<?= url('pages/settings.php') ?>"><i class="bi bi-gear me-2"></i>Settings</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= url('auth/logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
                        </ul>
                    </div>
                </div>
            </header>

            <main class="page-content">
                <?php if ($msg = flash('success')): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i><?= e($msg) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if ($msg = flash('warning')): ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i><?= e($msg) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if ($msg = flash('error')): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-circle me-2"></i><?= e($msg) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
