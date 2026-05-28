<?php
$company = getCompanySettings();
$user    = Auth::user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Portal') ?> — <?= e($company['company_name']) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= adminUrl('assets/css/app.css') ?>" rel="stylesheet">
    <link href="<?= adminUrl('assets/css/case-workspace.css') ?>" rel="stylesheet">
</head>
<body>
<div class="app-wrapper">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-brand">
                <div class="brand-icon"><i class="bi bi-shield-check"></i></div>
                <div class="brand-text">
                    <span class="brand-name"><?= e($company['company_name']) ?></span>
                    <span class="brand-tag">Client</span>
                </div>
            </div>
        </div>
        <nav class="sidebar-nav">
            <ul class="nav-list">
                <li class="nav-item"><a href="<?= clientUrl('pages/dashboard.php') ?>" class="nav-link"><i class="bi bi-grid"></i><span class="nav-label">Dashboard</span></a></li>
                <li class="nav-item"><a href="<?= clientUrl('pages/cases.php') ?>" class="nav-link"><i class="bi bi-briefcase"></i><span class="nav-label">My Cases</span></a></li>
            </ul>
        </nav>
        <div class="sidebar-footer">
            <a href="<?= adminUrl('auth/logout.php') ?>" class="sidebar-logout"><i class="bi bi-box-arrow-right"></i><span class="nav-label">Sign Out</span></a>
        </div>
    </aside>
    <div class="main-content">
        <header class="topbar">
            <div class="topbar-left">
                <div class="topbar-title">
                    <div class="topbar-page-title"><?= e($pageTitle ?? 'Portal') ?></div>
                </div>
            </div>
            <div class="topbar-actions">
                <div class="topbar-profile">
                    <div class="profile-avatar"><?= e(userInitials($user)) ?></div>
                    <div class="profile-info d-none d-md-block">
                        <span class="profile-name"><?= e(userFullName($user)) ?></span>
                        <span class="profile-role">Client</span>
                    </div>
                </div>
            </div>
        </header>
        <main class="page-content">
