<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireClient();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verifyRequest()) {
    flash('error', 'Invalid request.');
    header('Location: ' . clientUrl('pages/contact.php'));
    exit;
}

$clientId = Auth::clientId();
$threadId = (int) ($_POST['thread_id'] ?? 0);

if (!$clientId || $threadId <= 0) {
    flash('error', 'Message not found.');
    header('Location: ' . clientUrl('pages/contact.php'));
    exit;
}

if (!ClientMessageService::deleteThread($threadId, $clientId)) {
    flash('error', 'Unable to clear this chat.');
    header('Location: ' . clientUrl('pages/contact.php?thread=' . $threadId));
    exit;
}

flash('success', 'Chat cleared.');
header('Location: ' . clientUrl('pages/contact.php'));
exit;
