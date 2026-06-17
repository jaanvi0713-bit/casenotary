<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requirePage('message-view');

$threadId = (int) ($_GET['id'] ?? 0);
$thread   = $threadId > 0 ? ClientMessageService::getThreadForAdmin($threadId) : null;

if (!$thread) {
    flash('error', 'Message not found.');
    redirect('pages/notifications.php?tab=messages');
}

ClientMessageService::markAdminRead($threadId);

$pageTitle    = 'Message — ' . ($thread['subject'] ?? '');
$pageSubtitle = clientFullName($thread) ?: 'Client';
$canReply     = Auth::canManage(RoleAccess::PERMISSION_NOTIFICATIONS);
$isClosed     = ($thread['status'] ?? '') === 'closed';
$clientId     = (int) ($thread['client_id'] ?? 0);
$clientBlocked = $clientId > 0 && ClientMessageService::isMessagingBlocked($clientId);
$messages     = $thread['messages'] ?? [];
$messageCount = count($messages);
$pageScripts  = '<script src="' . e(asset('js/message-thread.js')) . '"></script>';

require __DIR__ . '/../includes/header.php';
?>

<div class="message-view-page">
    <div class="message-view-top">
        <a href="<?= url('pages/notifications.php?tab=messages') ?>" class="btn btn-soft btn-sm message-view-back">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
        <?php if ($canReply): ?>
            <div class="message-view-top-actions d-flex flex-wrap align-items-center gap-2">
                <?php if ($isClosed): ?>
                    <form method="post" action="<?= url('actions/message-action.php') ?>" class="m-0">
                        <?= CSRF::field() ?>
                        <input type="hidden" name="action" value="reopen">
                        <input type="hidden" name="thread_id" value="<?= $threadId ?>">
                        <button type="submit" class="btn btn-soft btn-sm">Reopen</button>
                    </form>
                <?php else: ?>
                    <form method="post" action="<?= url('actions/message-action.php') ?>" class="m-0">
                        <?= CSRF::field() ?>
                        <input type="hidden" name="action" value="close">
                        <input type="hidden" name="thread_id" value="<?= $threadId ?>">
                        <button type="submit" class="btn btn-soft btn-sm">Mark closed</button>
                    </form>
                <?php endif; ?>
                <form method="post"
                      action="<?= url('actions/message-action.php') ?>"
                      class="m-0"
                      onsubmit="return confirm('Clear this entire chat? This cannot be undone.');">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="clear">
                    <input type="hidden" name="thread_id" value="<?= $threadId ?>">
                    <button type="submit" class="btn btn-soft-danger btn-sm">Clear chat</button>
                </form>
                <?php if ($clientBlocked): ?>
                    <form method="post" action="<?= url('actions/message-action.php') ?>" class="m-0">
                        <?= CSRF::field() ?>
                        <input type="hidden" name="action" value="unblock_client">
                        <input type="hidden" name="thread_id" value="<?= $threadId ?>">
                        <button type="submit" class="btn btn-soft btn-sm">Unblock messaging</button>
                    </form>
                <?php else: ?>
                    <form method="post"
                          action="<?= url('actions/message-action.php') ?>"
                          class="m-0"
                          onsubmit="return confirm('Block this client from sending new messages? Use this for spam or abuse.');">
                        <?= CSRF::field() ?>
                        <input type="hidden" name="action" value="block_client">
                        <input type="hidden" name="thread_id" value="<?= $threadId ?>">
                        <button type="submit" class="btn btn-soft-danger btn-sm">Block messaging</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="saas-card message-view-card">
        <div class="saas-card-header message-view-header message-view-header--brand">
            <div class="message-view-header-main">
                <h2 class="saas-card-title"><?= e((string) ($thread['subject'] ?? '')) ?></h2>
                <p class="saas-card-subtitle mb-0">
                    <?= e(clientFullName($thread) ?: 'Client') ?>
                    · <?= e((string) ($thread['email'] ?? '')) ?>
                    · <?= formatDateTime((string) ($thread['created_at'] ?? '')) ?>
                </p>
            </div>
            <div class="message-view-header-meta">
                <span class="message-thread-status"><?= $isClosed ? 'Closed' : 'Open' ?></span>
                <?php if ($clientBlocked): ?>
                    <span class="message-thread-status message-thread-status--blocked">Blocked</span>
                <?php endif; ?>
                <span class="message-thread-count"><?= $messageCount ?> message<?= $messageCount === 1 ? '' : 's' ?></span>
            </div>
        </div>
        <div class="card-body message-thread-body message-thread-body--panel">
            <?php if ($messages === []): ?>
                <p class="text-muted small mb-0">No messages in this chat.</p>
            <?php else: ?>
                <?php foreach ($messages as $message): ?>
                    <?php
                    $isOutbound = ($message['direction'] ?? '') === 'outbound';
                    $sender     = $isOutbound
                        ? trim(($message['admin_first_name'] ?? '') . ' ' . ($message['admin_last_name'] ?? ''))
                        : (clientFullName($thread) ?: 'Client');
                    if ($isOutbound && $sender === '') {
                        $sender = 'Office';
                    }
                    $canEditMessage = $canReply && !$isClosed && $isOutbound;
                    $editFormAction = url('actions/message-action.php');
                    require __DIR__ . '/partials/message-thread-item.php';
                    ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($canReply && !$isClosed): ?>
        <div class="saas-card message-reply-card">
            <div class="saas-card-header message-view-header message-view-header--brand">
                <h2 class="saas-card-title mb-0">Reply to client</h2>
            </div>
            <div class="card-body message-reply-body">
                <form method="post" action="<?= url('actions/message-action.php') ?>">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="reply">
                    <input type="hidden" name="thread_id" value="<?= $threadId ?>">
                    <div class="mb-3">
                        <label class="form-label" for="reply">Your reply</label>
                        <textarea id="reply" name="reply" class="form-control" rows="5" required placeholder="Write your reply..."></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send me-2"></i> Send reply
                    </button>
                </form>
            </div>
        </div>
    <?php elseif ($isClosed): ?>
        <div class="alert alert-light border mb-0">This chat is closed. Reopen it to send another reply.</div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
