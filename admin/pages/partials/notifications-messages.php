<?php
/** @var list<array<string, mixed>> $threads */
/** @var int $total */
/** @var int $showingFrom */
/** @var int $showingTo */
/** @var int $page */
/** @var int $totalPages */
?>
<?php if ($threads === []): ?>
    <div class="empty-state py-5">
        <i class="bi bi-envelope"></i>
        <p class="mb-0">No client messages yet.</p>
    </div>
<?php else: ?>
    <div class="notification-list message-inbox-list">
        <?php foreach ($threads as $thread): ?>
            <?php
            $clientName = clientFullName($thread) ?: 'Client';
            $isUnread   = !empty($thread['admin_unread']);
            $statusLabel = $isUnread
                ? 'Unread'
                : ((($thread['status'] ?? '') === 'closed') ? 'Closed' : 'Open');
            ?>
            <div class="notification-list-item message-inbox-item <?= $isUnread ? 'unread' : '' ?>">
                <div class="notification-list-icon">
                    <i class="bi bi-envelope<?= $isUnread ? '-fill' : '' ?>"></i>
                </div>
                <div class="notification-list-body">
                    <strong><?= e($clientName) ?> — <?= e((string) ($thread['subject'] ?? '')) ?></strong>
                    <p class="mb-1"><?= e(mb_strimwidth((string) ($thread['preview'] ?? ''), 0, 120, '…')) ?></p>
                    <small class="text-muted">
                        <?= timeAgo((string) ($thread['last_message_at'] ?? '')) ?>
                        · <?= e($statusLabel) ?>
                        <?php if (!empty($thread['company_name'])): ?>
                            · <?= e((string) $thread['company_name']) ?>
                        <?php endif; ?>
                    </small>
                </div>
                <div class="notification-list-actions d-flex gap-2">
                    <a href="<?= url('pages/message-view.php?id=' . (int) $thread['id']) ?>" class="btn btn-soft btn-sm">Open</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-2 border-top">
        <small class="text-muted">
            Showing <?= $showingFrom ?>–<?= $showingTo ?> of <?= $total ?> messages
        </small>
        <?= renderPaginationNav($page, $totalPages) ?>
    </div>
<?php endif; ?>
