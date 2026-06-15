<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::guardAction();

if (!Auth::isAdmin()) {
    flash('error', 'You do not have permission to create backups.');
    redirect('pages/settings.php?tab=backup');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verifyRequest()) {
    flash('error', 'Invalid request.');
    redirect('pages/settings.php?tab=backup');
}

try {
    $companyId = TenantService::id();
    $user      = Auth::user();
    $result    = BackupService::create(
        $companyId,
        'manual',
        is_array($user) ? (string) ($user['email'] ?? '') : null
    );

    $recipientCount = (int) $result['emailed'];
    if ($recipientCount > 0) {
        flash('success', 'Website backup emailed to ' . $recipientCount . ' administrator(s).');
    } elseif (BackupService::recipients($companyId) === []) {
        flash('warning', 'No admin email addresses configured. Set office email in Branding settings.');
    } else {
        flash('warning', 'Backup created but email delivery failed. Check SMTP settings.');
    }
} catch (Throwable $e) {
    flash('error', $e->getMessage());
}

redirect('pages/settings.php?tab=backup');
