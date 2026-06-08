<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::guardAction();
Auth::requireSuperAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verifyRequest()) {
    flash('error', 'Invalid request. Please try again.');
    redirectReturn($_POST['return'] ?? null);
}

$companyId = (int) ($_POST['company_id'] ?? 0);

try {
    TenantService::set($companyId);
    $companyName = TenantService::name($companyId);
    $requestedReturn = resolveAdminReturn($_POST['return'] ?? null);
    $safeReturn = resolveAdminReturnAfterCompanySwitch($_POST['return'] ?? null);

    if ($safeReturn !== $requestedReturn) {
        flash('warning', 'Switched to ' . $companyName . '. The previous page is not available in this workspace.');
    } else {
        flash('success', 'Switched to ' . $companyName . '.');
    }
} catch (Throwable $e) {
    flash('error', $e->getMessage());
    $safeReturn = resolveAdminReturnAfterCompanySwitch($_POST['return'] ?? null);
}

redirect($safeReturn);
