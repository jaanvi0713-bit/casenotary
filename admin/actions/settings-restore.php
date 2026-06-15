<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::guardAction();

if (!Auth::isAdmin()) {
    flash('error', 'You do not have permission to restore backups.');
    redirect('pages/settings.php?tab=backup');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verifyRequest()) {
    flash('error', 'Invalid request.');
    redirect('pages/settings.php?tab=backup');
}

$file = $_FILES['backup_file'] ?? null;

if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    flash('error', 'Please choose a backup file to restore.');
    redirect('pages/settings.php?tab=backup');
}

$content = file_get_contents($file['tmp_name']);
$data    = json_decode((string) $content, true);

if (!is_array($data)) {
    flash('error', 'Backup file is not valid JSON.');
    redirect('pages/settings.php?tab=backup');
}

try {
    SettingsService::restoreBackup($data);
    flash('success', 'Settings restored successfully from backup.');
} catch (Throwable $e) {
    flash('error', $e->getMessage());
}

redirect('pages/settings.php?tab=backup');
