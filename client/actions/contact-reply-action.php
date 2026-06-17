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
$message  = trim((string) ($_POST['message'] ?? ''));

if (!$clientId || $threadId <= 0 || $message === '') {
    flash('error', 'Please enter a message.');
    header('Location: ' . clientUrl('pages/contact.php'));
    exit;
}

if (ClientMessageService::isMessagingBlocked($clientId)) {
    flash('error', 'Your messaging access has been restricted. Please contact the office directly.');
    header('Location: ' . clientUrl('pages/contact.php?thread=' . $threadId));
    exit;
}

if (!ClientMessageService::replyFromClient($threadId, $clientId, $message)) {
    flash('error', 'Unable to send your reply.');
    header('Location: ' . clientUrl('pages/contact.php?thread=' . $threadId));
    exit;
}

flash('success', 'Your reply has been sent.');
header('Location: ' . clientUrl('pages/contact.php?thread=' . $threadId));
exit;
