<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireClient();

$clientId = Auth::clientId();
if (!$clientId) {
    flash('error', 'Client profile not found.');
    header('Location: ' . clientUrl('pages/dashboard.php'));
    exit;
}

try {
    $result = BackupService::createClientBackup($clientId);

    if ($result['emailed']) {
        flash('success', 'Your data backup was downloaded and a copy was emailed to you.');
    } else {
        flash('warning', 'Backup downloaded. Email delivery failed — check spam or contact the office if SMTP is not configured.');
    }
} catch (Throwable $e) {
    flash('error', $e->getMessage());
    header('Location: ' . clientUrl('pages/backup.php'));
    exit;
}

$filename = 'my-data-backup-' . date('Y-m-d-His') . '.json';
$json     = $result['json'];

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($json));
echo $json;
exit;
