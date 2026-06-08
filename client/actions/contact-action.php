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

$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($subject === '' || $message === '') {
    setOld($_POST);
    flash('error', 'Please enter a subject and message.');
    header('Location: ' . clientUrl('pages/contact.php'));
    exit;
}

$company = getCompanySettings();
$client  = ClientService::getById($clientId);
$to      = trim($company['office_email'] ?? '');

if ($to === '') {
    setOld($_POST);
    flash('error', 'The office email is not configured yet. Please try again later.');
    header('Location: ' . clientUrl('pages/contact.php'));
    exit;
}

$name  = clientFullName($client ?? []) ?: 'Client';
$email = $client['email'] ?? Auth::user()['email'] ?? '';

$htmlBody = '<p><strong>From:</strong> ' . e($name) . '<br>'
    . '<strong>Email:</strong> ' . e($email) . '<br>'
    . '<strong>Subject:</strong> ' . e($subject) . '</p>'
    . '<hr>'
    . '<p>' . nl2br(e($message)) . '</p>';

$sent = MailService::send($to, 'Client Portal: ' . $subject, $htmlBody);

if (!$sent) {
    setOld($_POST);
    flash('error', 'Unable to send your message right now. Please try again or email us directly.');
    header('Location: ' . clientUrl('pages/contact.php'));
    exit;
}

clearOld();
flash('success', 'Your message has been sent. We typically respond with one or two business day(s).');
header('Location: ' . clientUrl('pages/contact.php'));
exit;
