<?php
require_once __DIR__ . '/core/bootstrap.php';

if (Auth::check()) {
    if (Auth::isClient()) {
        header('Location: ' . clientUrl('pages/dashboard.php'));
        exit;
    }
    header('Location: ' . adminUrl('pages/dashboard.php'));
    exit;
}

header('Location: ' . clientLoginUrl(TenantService::resolveLoginCompanyFromRequest() ?: null));
exit;
