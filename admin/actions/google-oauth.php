<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAdmin();

try {
    if (!GoogleOAuthService::isConfigured()) {
        throw new RuntimeException('Add Google Client ID and Secret in Settings → Calendar first.');
    }

    header('Location: ' . GoogleOAuthService::getAuthUrl());
    exit;
} catch (Throwable $e) {
    flash('error', $e->getMessage());
    redirect('pages/settings.php?tab=calendar');
}
