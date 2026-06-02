<?php
$company          = getCompanySettings();
$user             = Auth::user();
$unreadCount      = getUnreadNotificationCount(Auth::id());
$navNotifications = getRecentNotifications(Auth::id(), 5, true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Portal') ?> — <?= e(companyBrandName($company)) ?></title>
    <?= renderFaviconTags($company) ?>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= adminAsset('css/app.css') ?>" rel="stylesheet">
    <link href="<?= adminAsset('css/case-workspace.css') ?>" rel="stylesheet">
    <?php if (!empty($pageStyles)): ?>
        <?= $pageStyles ?>
    <?php endif; ?>
    <style>
        :root {
            --primary: <?= e($company['primary_color']) ?>;
            --secondary: <?= e($company['secondary_color']) ?>;
            --dark-accent: <?= e($company['dark_accent']) ?>;
        }
    </style>
</head>
<body>
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
                            <?= renderCompanyLogo('topbar', $company, 'client') ?>
                        </div>
                    <?php endif; ?>
                    <div class="topbar-title-text">
                    <div class="topbar-page-title"><?= e($pageTitle ?? 'Portal') ?></div>
                    <?php if (!empty($pageSubtitle)): ?>
                        <p class="topbar-page-subtitle"><?= e($pageSubtitle) ?></p>
                    <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="topbar-actions">
                <div class="dropdown">
                    <button type="button" class="topbar-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Notifications">
                        <i class="bi bi-bell"></i>
                        <?php if ($unreadCount > 0): ?>
                            <span class="notification-dot"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end notification-dropdown">
                        <div class="dropdown-header">
                            <span>Notifications</span>
                            <?php if ($unreadCount > 0): ?>
                                <span class="badge rounded-pill bg-primary"><?= $unreadCount ?> new</span>
                            <?php endif; ?>
                        </div>
                        <?php if (empty($navNotifications)): ?>
                            <div class="dropdown-item-text text-muted text-center py-4 small">No notifications</div>
                        <?php else: ?>
                            <?php foreach ($navNotifications as $notif): ?>
                                <a href="<?= clientUrl('actions/notification-read.php?id=' . (int) $notif['id']) ?>" class="dropdown-item notification-item<?= empty($notif['is_read']) ? ' unread' : '' ?>">
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
                        <div class="dropdown-divider"></div>
                        <a href="<?= clientUrl('pages/notifications.php') ?>" class="dropdown-item text-center small fw-semibold">View all notifications</a>
                    </div>
                </div>

                <div class="dropdown">
                    <button type="button" class="topbar-profile" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="profile-avatar"><?= e(userInitials($user)) ?></div>
                        <div class="profile-info d-none d-md-block">
                            <span class="profile-name"><?= e(userFullName($user)) ?></span>
                            <span class="profile-role">Client</span>
                        </div>
                        <i class="bi bi-chevron-down profile-chevron d-none d-md-block"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end profile-dropdown">
                        <li class="dropdown-header profile-dropdown-header">
                            <strong><?= e(userFullName($user)) ?></strong>
                            <small><?= e($user['email']) ?></small>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= clientUrl('pages/profile.php') ?>"><i class="bi bi-person me-2"></i>My Profile</a></li>
                        <li><a class="dropdown-item" href="<?= clientUrl('pages/contact.php') ?>"><i class="bi bi-envelope me-2"></i>Contact</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="<?= adminUrl('auth/logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
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
            <?php if ($msg = flash('error')): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-circle me-2"></i><?= e($msg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
