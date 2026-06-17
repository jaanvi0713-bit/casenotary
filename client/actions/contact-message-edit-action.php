<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireClient();

flash('error', 'Only the office can edit messages in this chat.');
$threadId = (int) ($_POST['thread_id'] ?? $_GET['thread'] ?? 0);
$redirect = $threadId > 0
    ? clientUrl('pages/contact.php?thread=' . $threadId)
    : clientUrl('pages/contact.php');
header('Location: ' . $redirect);
exit;
