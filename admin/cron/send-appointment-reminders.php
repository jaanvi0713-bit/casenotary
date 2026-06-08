<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    Auth::requireAdmin();
}

$sent = ReminderService::sendDueReminders();

if (PHP_SAPI === 'cli') {
    echo date('Y-m-d H:i:s') . " — Sent {$sent} appointment reminder(s).\n";
    exit(0);
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'sent'    => $sent,
    'time'    => date('c'),
]);
