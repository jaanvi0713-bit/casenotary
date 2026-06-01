<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verifyRequest()) {
    flash('error', 'Invalid request.');
    redirect('pages/settings.php?tab=calendar');
}

GoogleOAuthService::disconnect();
flash('success', 'Google Calendar disconnected.');
redirect('pages/settings.php?tab=calendar');
