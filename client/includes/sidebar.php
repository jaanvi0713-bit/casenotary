<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$company = getCompanySettings();

$navItems = [
    ['icon' => 'bi-grid', 'label' => 'Dashboard', 'href' => 'pages/dashboard.php', 'page' => 'dashboard'],
    ['icon' => 'bi-briefcase', 'label' => 'Cases', 'href' => 'pages/cases.php', 'page' => 'cases'],
    ['icon' => 'bi-credit-card', 'label' => 'Payments', 'href' => 'pages/payments.php', 'page' => 'payments'],
    ['icon' => 'bi-calendar3', 'label' => 'Appointments', 'href' => 'pages/appointments.php', 'page' => 'appointments'],
    ['icon' => 'bi-envelope', 'label' => 'Contact', 'href' => 'pages/contact.php', 'page' => 'contact'],
    ['icon' => 'bi-bell', 'label' => 'Notifications', 'href' => 'pages/notifications.php', 'page' => 'notifications'],
];
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <div class="brand-icon">
                <?= renderCompanyLogo('sidebar', $company, 'client') ?>
            </div>
            <div class="brand-text">
                <span class="brand-name"><?= e(companyBrandName($company)) ?></span>
                <span class="brand-tag">Client</span>
            </div>
        </div>
        <button type="button" class="sidebar-collapse-btn d-none d-lg-flex" id="sidebarCollapse" aria-label="Collapse sidebar">
            <i class="bi bi-chevron-left"></i>
        </button>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav-list">
            <?php foreach ($navItems as $item): ?>
                <li class="nav-item">
                    <a href="<?= clientUrl($item['href']) ?>"
                       class="nav-link <?= $currentPage === $item['page'] ? 'active' : '' ?>"
                       title="<?= e($item['label']) ?>">
                        <i class="bi <?= e($item['icon']) ?>"></i>
                        <span class="nav-label"><?= e($item['label']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <a href="<?= adminUrl('auth/logout.php') ?>" class="sidebar-logout" title="Sign Out">
            <i class="bi bi-box-arrow-right"></i>
            <span class="nav-label">Sign Out</span>
        </a>
    </div>
</aside>
