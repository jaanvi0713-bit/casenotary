<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::guardAction();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verifyRequest()) {
    flash('error', 'Invalid request.');
    redirect('pages/settings.php?tab=email');
}

$to = trim($_POST['test_email'] ?? Auth::user()['email'] ?? '');

try {
    $stored = getCompanySettings();
    $smtpPassword = trim($_POST['new_smtp_password'] ?? $_POST['smtp_password'] ?? '');
    if ($smtpPassword === '') {
        $smtpPassword = (string) ($stored['smtp_password'] ?? '');
    }

    MailService::sendTestEmail($to, [
        'smtp_host'       => trim($_POST['smtp_host'] ?? ''),
        'smtp_port'       => (int) ($_POST['smtp_port'] ?? 587),
        'smtp_username'   => trim($_POST['smtp_username'] ?? ''),
        'smtp_password'   => $smtpPassword,
        'smtp_encryption' => $_POST['smtp_encryption'] ?? 'tls',
        'office_email'    => trim($_POST['office_email'] ?? '') ?: null,
        'company_name'    => trim($_POST['company_name'] ?? '') ?: null,
    ]);

    flash('success', 'Test email sent to ' . $to . '. Check your inbox (and spam folder).');
} catch (Throwable $e) {
    flash('error', $e->getMessage());
}

redirect('pages/settings.php?tab=email');
