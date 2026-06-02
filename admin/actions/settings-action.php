<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verifyRequest()) {
    flash('error', 'Invalid request.');
    redirect('pages/settings.php');
}

$tab = $_POST['tab'] ?? 'branding';

try {
    if ($tab === 'calendar') {
        SettingsService::updateCalendar($_POST);
    } else {
        SettingsService::update(
            $_POST,
            $_FILES['logo'] ?? null,
            $_FILES['favicon'] ?? null
        );
    }
    flash('success', 'Settings saved successfully.');
    redirect('pages/settings.php?tab=' . urlencode($tab));
} catch (Throwable $e) {
    flash('error', $e->getMessage());
    redirect('pages/settings.php?tab=' . urlencode($tab));
}
