<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::guardAction();

$code  = trim($_GET['code'] ?? '');
$error = trim($_GET['error'] ?? '');
$state = trim($_GET['state'] ?? '');

if ($error !== '') {
    flash('error', 'Microsoft 365 connection was cancelled.');
    redirect('pages/settings.php?tab=email');
}

if ($code === '') {
    flash('error', 'Missing authorization code from Microsoft.');
    redirect('pages/settings.php?tab=email');
}

try {
    MicrosoftOAuthService::handleCallback($code, $state);
    flash('success', 'Microsoft 365 email connected successfully.');
} catch (Throwable $e) {
    flash('error', $e->getMessage());
}

redirect('pages/settings.php?tab=email');
