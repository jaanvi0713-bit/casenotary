<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::guardAction();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verifyRequest()) {
    flash('error', 'Invalid request.');
    redirect('pages/settings.php?tab=profile');
}

$action = $_POST['action'] ?? 'update_profile';
$userId = Auth::id();

if (!$userId) {
    flash('error', 'Not authenticated.');
    redirect('auth/login.php');
}

try {
    if ($action === 'change_password') {
        ProfileService::changePassword(
            $userId,
            $_POST['current_password'] ?? '',
            $_POST['new_password'] ?? '',
            $_POST['new_password_confirmation'] ?? ''
        );
        flash('success', 'Password updated successfully.');
        redirect('pages/settings.php?tab=profile');
    }

    ProfileService::update($userId, $_POST);
    flash('success', 'Profile updated successfully.');
    redirect('pages/settings.php?tab=profile');
} catch (Throwable $e) {
    flash('error', $e->getMessage());
    redirect('pages/settings.php?tab=profile');
}
