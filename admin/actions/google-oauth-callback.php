<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::guardAction();

$code  = trim($_GET['code'] ?? '');
$error = trim($_GET['error'] ?? '');
$state = trim($_GET['state'] ?? '');

if ($error !== '') {
    flash('error', 'Google Calendar connection was cancelled.');
    redirect('pages/settings.php?tab=calendar');
}

if ($code === '') {
    flash('error', 'Missing authorization code from Google.');
    redirect('pages/settings.php?tab=calendar');
}

try {
    GoogleOAuthService::handleCallback($code, $state);
    flash('success', 'Google Calendar connected successfully.');
} catch (Throwable $e) {
    flash('error', $e->getMessage());
}

redirect('pages/settings.php?tab=calendar');
