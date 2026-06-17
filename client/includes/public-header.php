<?php
$company = getCompanySettings();
$brandName = companyBrandName($company);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/../../admin/includes/theme-head.php'; ?>
    <meta name="csrf-token" content="<?= e(CSRF::generateToken()) ?>">
    <title><?= e($pageTitle ?? 'Checkout') ?> — <?= e($brandName) ?></title>
    <?= renderFaviconTags($company) ?>
    <?= renderCompanyFontStylesheet($company) ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= adminAsset('css/app.css') ?>" rel="stylesheet">
    <link href="<?= adminAsset('css/theme.css') ?>" rel="stylesheet">
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
        body { font-family: var(--font-family); background: #f1f5f9; margin: 0; }
        .public-checkout { min-height: 100vh; display: flex; flex-direction: column; }
        .public-checkout__bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.25rem;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
        }
        .public-checkout__brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-width: 0;
        }
        .public-checkout__brand-name {
            font-weight: 700;
            color: #0f172a;
            font-size: 1rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .public-checkout__main {
            flex: 1;
            padding: 2rem 1rem 3rem;
        }
        [data-theme="dark"] body { background: #0f172a; }
        [data-theme="dark"] .public-checkout__bar {
            background: #1e293b;
            border-bottom-color: #334155;
        }
        [data-theme="dark"] .public-checkout__brand-name { color: #f8fafc; }
    </style>
</head>
<body<?= !empty($pageBodyClass) ? ' class="' . e($pageBodyClass) . '"' : '' ?>>
<div class="public-checkout">
    <header class="public-checkout__bar">
        <div class="public-checkout__brand">
            <?php if (companyLogoUrl($company)): ?>
                <?= renderCompanyLogo('topbar', $company, 'client') ?>
            <?php endif; ?>
            <span class="public-checkout__brand-name"><?= e($brandName) ?></span>
        </div>
        <?php require __DIR__ . '/../../admin/includes/theme-toggle.php'; ?>
    </header>
    <main class="public-checkout__main">
        <?php if ($msg = flash('success')): ?>
            <div class="alert alert-success alert-dismissible fade show payment-gateway-page" role="alert">
                <i class="bi bi-check-circle me-2"></i><?= e($msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <?php if ($msg = flash('error')): ?>
            <div class="alert alert-danger alert-dismissible fade show payment-gateway-page" role="alert">
                <i class="bi bi-exclamation-circle me-2"></i><?= e($msg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
