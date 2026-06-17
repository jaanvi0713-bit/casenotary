<?php
/**
 * @var array<string, mixed> $message
 * @var string $sender
 * @var bool $isOutbound
 * @var bool $canEditMessage
 * @var string $editFormAction
 * @var int $threadId
 */
$messageBody = (string) ($message['body'] ?? '');
$messageId   = (int) ($message['id'] ?? 0);
$editedAt    = (string) ($message['edited_at'] ?? '');
?>
<div class="message-thread-item <?= $isOutbound ? 'message-thread-item--outbound' : 'message-thread-item--inbound' ?>"
     data-message-id="<?= $messageId ?>">
    <div class="message-thread-meta">
        <div class="message-thread-meta-main">
            <strong><?= e($sender) ?></strong>
            <span><?= formatDateTime((string) ($message['created_at'] ?? '')) ?></span>
            <?php if ($editedAt !== ''): ?>
                <span class="message-thread-edited">edited</span>
            <?php endif; ?>
        </div>
        <div class="message-thread-actions">
            <button type="button"
                    class="btn btn-link btn-sm message-copy-btn"
                    data-copy-text="<?= e($messageBody) ?>"
                    title="Copy message">
                <i class="bi bi-clipboard me-1"></i>Copy
            </button>
            <?php if ($canEditMessage): ?>
                <button type="button" class="btn btn-link btn-sm message-edit-btn" title="Edit message">
                    <i class="bi bi-pencil me-1"></i>Edit
                </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="message-thread-bubble message-thread-bubble--display"><?= nl2br(e($messageBody)) ?></div>
    <?php if ($canEditMessage): ?>
        <form method="post" action="<?= e($editFormAction) ?>" class="message-edit-form d-none">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="edit_message">
            <input type="hidden" name="thread_id" value="<?= $threadId ?>">
            <input type="hidden" name="message_id" value="<?= $messageId ?>">
            <textarea name="body" class="form-control form-control-sm mb-2" rows="4" required><?= e($messageBody) ?></textarea>
            <div class="d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary btn-sm">Save</button>
                <button type="button" class="btn btn-light btn-sm message-edit-cancel">Cancel</button>
            </div>
        </form>
    <?php endif; ?>
</div>
