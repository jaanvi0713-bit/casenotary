<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireClient();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verifyRequest()) {
    flash('error', 'Invalid request.');
    header('Location: ' . clientUrl('pages/contact.php'));
    exit;
}

$clientId  = Auth::clientId();
$threadId  = (int) ($_POST['thread_id'] ?? 0);
$messageId = (int) ($_POST['message_id'] ?? 0);
$body      = trim((string) ($_POST['body'] ?? ''));

if (!$clientId || $threadId <= 0 || $messageId <= 0 || $body === '') {
    flash('error', 'Please enter a message.');
    header('Location: ' . clientUrl('pages/contact.php?thread=' . $threadId));
    exit;
}

if (ClientMessageService::isMessagingBlocked($clientId)) {
    flash('error', 'Your messaging access has been restricted. Please contact the office directly.');
    header('Location: ' . clientUrl('pages/contact.php?thread=' . $threadId));
    exit;
}

if (!ClientMessageService::updateMessageForClient($messageId, $threadId, $clientId, $body)) {
    flash('error', 'Unable to update this message.');
    header('Location: ' . clientUrl('pages/contact.php?thread=' . $threadId));
    exit;
}

flash('success', 'Message updated.');
header('Location: ' . clientUrl('pages/contact.php?thread=' . $threadId));
exit;
