<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireClient();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verifyRequest()) {
    flash('error', 'Invalid request.');
    header('Location: ' . clientUrl('pages/contact.php'));
    exit;
}

$clientId = Auth::clientId();
if (!$clientId) {
    flash('error', 'Client profile not found.');
    header('Location: ' . clientUrl('pages/contact.php'));
    exit;
}

if (ClientMessageService::isMessagingBlocked($clientId)) {
    flash('error', 'Your messaging access has been restricted. Please contact the office directly.');
    header('Location: ' . clientUrl('pages/contact.php'));
    exit;
}

$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($subject === '' || $message === '') {
    setOld($_POST);
    flash('error', 'Please enter a subject and message.');
    header('Location: ' . clientUrl('pages/contact.php'));
    exit;
}

try {
    $threadId = ClientMessageService::createFromClient($clientId, $subject, $message);
} catch (RuntimeException $e) {
    setOld($_POST);
    flash('error', $e->getMessage());
    header('Location: ' . clientUrl('pages/contact.php'));
    exit;
} catch (Throwable $e) {
    setOld($_POST);
    flash('error', 'Unable to save your message. Please try again.');
    header('Location: ' . clientUrl('pages/contact.php'));
    exit;
}

$company = getCompanySettings();
$client  = ClientService::getById($clientId);
$to      = trim($company['office_email'] ?? '');

$emailSent = false;
if ($to !== '') {
    $name  = clientFullName($client ?? []) ?: 'Client';
    $email = $client['email'] ?? Auth::user()['email'] ?? '';

    $htmlBody = '<p><strong>From:</strong> ' . e($name) . '<br>'
        . '<strong>Email:</strong> ' . e($email) . '<br>'
        . '<strong>Subject:</strong> ' . e($subject) . '</p>'
        . '<hr>'
        . '<p>' . nl2br(e($message)) . '</p>'
        . '<p><a href="' . e(adminUrl('pages/message-view.php?id=' . $threadId)) . '">View in Admin Portal</a></p>';

    $emailSent = MailService::send($to, 'Client Portal: ' . $subject, $htmlBody);
}

clearOld();
if ($emailSent) {
    flash('success', 'Your message has been sent. We typically respond within one or two business days.');
} else {
    flash('success', 'Your message has been received. Our team will respond through the portal or by email.');
}
header('Location: ' . clientUrl('pages/contact.php?thread=' . $threadId));
exit;
