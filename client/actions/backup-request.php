<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireClient();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verifyRequest()) {
    flash('error', 'Invalid request.');
    header('Location: ' . clientUrl('pages/backup.php'));
    exit;
}

$clientId = Auth::clientId();
if (!$clientId) {
    flash('error', 'Client profile not found.');
    header('Location: ' . clientUrl('pages/backup.php'));
    exit;
}

try {
    $result = BackupService::createClientBackup($clientId);

    if ($result['emailed']) {
        flash('success', 'Your data backup has been emailed to you. Please check your inbox (and spam folder).');
    } else {
        flash('warning', 'Your backup was created but could not be emailed. Please try downloading instead or contact the office.');
    }
} catch (Throwable $e) {
    flash('error', $e->getMessage());
}

header('Location: ' . clientUrl('pages/backup.php'));
exit;
