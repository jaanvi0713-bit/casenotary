<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::guardAction();

if (!Auth::isAdmin()) {
    flash('error', 'You do not have permission to download backups.');
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

    if ($result['emailed'] > 0) {
        flash('success', 'Backup created and emailed to ' . $result['emailed'] . ' administrator(s).');
    } elseif (BackupService::recipients($companyId) === []) {
        flash('warning', 'Backup downloaded. Configure office email or SMTP to receive backup copies by email.');
    } else {
        flash('warning', 'Backup downloaded but email delivery failed. Check SMTP settings.');
    }
} catch (Throwable $e) {
    flash('error', $e->getMessage());
    redirect('pages/settings.php?tab=backup');
}

$filename = 'system-backup-' . date('Y-m-d-His') . '.json';
$json     = $result['json'];

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($json));
echo $json;
exit;
