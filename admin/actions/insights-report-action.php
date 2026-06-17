<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::guardAction();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verifyRequest()) {
    flash('error', 'Invalid request.');
    redirect('pages/insights.php?tab=reports');
}

if (!Auth::can(RoleAccess::PERMISSION_INSIGHTS)) {
    flash('error', 'You do not have permission to manage insights settings.');
    redirect('pages/insights.php?tab=reports');
}

$action = trim((string) ($_POST['action'] ?? ''));
if ($action !== 'save_digest_preferences') {
    flash('error', 'Unknown action.');
    redirect('pages/insights.php?tab=reports');
}

$frequency = trim((string) ($_POST['insights_digest_frequency'] ?? 'monthly'));
if (!in_array($frequency, ['weekly', 'monthly'], true)) {
    $frequency = 'monthly';
}

$format = trim((string) ($_POST['insights_digest_format'] ?? 'pdf'));
if (!in_array($format, ['pdf', 'csv'], true)) {
    $format = 'pdf';
}

$recipients = trim((string) ($_POST['insights_digest_recipients'] ?? ''));
$recipients = preg_replace('/\s+/', ' ', $recipients ?? '') ?: '';

SettingsService::saveSetting('insights_digest_frequency', $frequency, TenantService::id());
SettingsService::saveSetting('insights_digest_format', $format, TenantService::id());
SettingsService::saveSetting('insights_digest_recipients', $recipients !== '' ? $recipients : null, TenantService::id());

flash('success', 'Insights digest settings saved.');
redirect('pages/insights.php?tab=reports');
