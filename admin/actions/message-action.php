<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::guardAction();
if (!Auth::can(RoleAccess::PERMISSION_NOTIFICATIONS)) {
    flash('error', 'You do not have permission to manage client messages.');
    redirect('pages/dashboard.php');
}

$action   = trim((string) ($_POST['action'] ?? ''));
$threadId = (int) ($_POST['thread_id'] ?? 0);

if ($threadId <= 0) {
    flash('error', 'Message not found.');
    redirect('pages/notifications.php?tab=messages');
}

switch ($action) {
    case 'reply':
        if (!Auth::canManage(RoleAccess::PERMISSION_NOTIFICATIONS)) {
            flash('error', 'Your account is read-only.');
            redirect('pages/message-view.php?id=' . $threadId);
        }

        $body = trim((string) ($_POST['reply'] ?? ''));
        if ($body === '') {
            flash('error', 'Please enter a reply.');
            redirect('pages/message-view.php?id=' . $threadId);
        }

        if (!ClientMessageService::replyFromAdmin($threadId, Auth::id(), $body)) {
            flash('error', 'Unable to send reply.');
            redirect('pages/message-view.php?id=' . $threadId);
        }

        flash('success', 'Reply sent to the client.');
        redirect('pages/message-view.php?id=' . $threadId);

    case 'close':
        if (!Auth::canManage(RoleAccess::PERMISSION_NOTIFICATIONS)) {
            flash('error', 'Your account is read-only.');
            redirect('pages/message-view.php?id=' . $threadId);
        }

        ClientMessageService::setStatus($threadId, 'closed');
        flash('success', 'Conversation marked as closed.');
        redirect('pages/message-view.php?id=' . $threadId);

    case 'reopen':
        if (!Auth::canManage(RoleAccess::PERMISSION_NOTIFICATIONS)) {
            flash('error', 'Your account is read-only.');
            redirect('pages/message-view.php?id=' . $threadId);
        }

        ClientMessageService::setStatus($threadId, 'open');
        flash('success', 'Conversation reopened.');
        redirect('pages/message-view.php?id=' . $threadId);

    case 'clear':
        if (!Auth::canManage(RoleAccess::PERMISSION_NOTIFICATIONS)) {
            flash('error', 'Your account is read-only.');
            redirect('pages/message-view.php?id=' . $threadId);
        }

        if (!ClientMessageService::deleteThread($threadId)) {
            flash('error', 'Unable to clear this chat.');
            redirect('pages/message-view.php?id=' . $threadId);
        }

        flash('success', 'Chat cleared.');
        redirect('pages/notifications.php?tab=messages');

    case 'edit_message':
        if (!Auth::canManage(RoleAccess::PERMISSION_NOTIFICATIONS)) {
            flash('error', 'Your account is read-only.');
            redirect('pages/message-view.php?id=' . $threadId);
        }

        $messageId = (int) ($_POST['message_id'] ?? 0);
        $body      = trim((string) ($_POST['body'] ?? ''));
        if ($messageId <= 0 || $body === '') {
            flash('error', 'Please enter a message.');
            redirect('pages/message-view.php?id=' . $threadId);
        }

        if (!ClientMessageService::updateMessageForAdmin($messageId, $threadId, $body)) {
            flash('error', 'Unable to update this message.');
            redirect('pages/message-view.php?id=' . $threadId);
        }

        flash('success', 'Message updated.');
        redirect('pages/message-view.php?id=' . $threadId);

    case 'block_client':
        if (!Auth::canManage(RoleAccess::PERMISSION_NOTIFICATIONS)) {
            flash('error', 'Your account is read-only.');
            redirect('pages/message-view.php?id=' . $threadId);
        }

        $thread = ClientMessageService::getThreadForAdmin($threadId);
        $clientId = (int) ($thread['client_id'] ?? 0);
        if (!$thread || $clientId <= 0) {
            flash('error', 'Client not found.');
            redirect('pages/notifications.php?tab=messages');
        }

        if (!ClientMessageService::setMessagingBlocked($clientId, true)) {
            flash('error', 'Unable to block this client from messaging.');
            redirect('pages/message-view.php?id=' . $threadId);
        }

        ClientMessageService::setStatus($threadId, 'closed');
        flash('success', 'Client blocked from sending messages. This chat has been closed.');
        redirect('pages/message-view.php?id=' . $threadId);

    case 'unblock_client':
        if (!Auth::canManage(RoleAccess::PERMISSION_NOTIFICATIONS)) {
            flash('error', 'Your account is read-only.');
            redirect('pages/message-view.php?id=' . $threadId);
        }

        $thread = ClientMessageService::getThreadForAdmin($threadId);
        $clientId = (int) ($thread['client_id'] ?? 0);
        if (!$thread || $clientId <= 0) {
            flash('error', 'Client not found.');
            redirect('pages/notifications.php?tab=messages');
        }

        if (!ClientMessageService::setMessagingBlocked($clientId, false)) {
            flash('error', 'Unable to restore messaging for this client.');
            redirect('pages/message-view.php?id=' . $threadId);
        }

        flash('success', 'Client can send messages again.');
        redirect('pages/message-view.php?id=' . $threadId);

    default:
        flash('error', 'Unknown action.');
        redirect('pages/notifications.php?tab=messages');
}
