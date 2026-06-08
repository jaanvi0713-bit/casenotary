<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::guardAction();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verifyRequest()) {
    flash('error', 'Invalid request.');
    redirect('pages/settings.php?tab=notifications');
}

$userId = Auth::id();

if ($userId <= 0) {
    flash('error', 'Not authenticated.');
    redirect('auth/login.php');
}

try {
    $input = [];

    foreach (NotificationPreferenceService::TYPES as $type) {
        $input[$type] = [
            'in_app' => !empty($_POST['pref'][$type]['in_app']),
            'email' => !empty($_POST['pref'][$type]['email']),
        ];
    }

    NotificationPreferenceService::saveForUser($userId, $input);
    flash('success', 'Notification preferences saved.');
} catch (Throwable $e) {
    flash('error', $e->getMessage());
}

redirect('pages/settings.php?tab=notifications');
