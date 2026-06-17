<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireClient();

$clientId = Auth::clientId();
if (!$clientId) {
    flash('error', 'Client profile not found.');
    header('Location: ' . clientLoginUrl());
    exit;
}

$company = getCompanySettings();
$contactPhone = trim($company['office_phone'] ?? '') ?: '+1 (555) 123-4567';
$businessHours = trim($company['business_hours'] ?? '') ?: 'Monday – Friday: 9:00 AM – 5:00 PM, Saturday – Sunday: Closed';
$activeThreadId = (int) ($_GET['thread'] ?? 0);
$clientThreads  = ClientMessageService::listThreadsForClient($clientId);
$messagingBlocked = ClientMessageService::isMessagingBlocked($clientId);
$activeThread   = $activeThreadId > 0
    ? ClientMessageService::getThreadForClient($activeThreadId, $clientId)
    : null;
if ($activeThreadId > 0 && !$activeThread) {
    flash('error', 'Message not found.');
    header('Location: ' . clientUrl('pages/contact.php'));
    exit;
}
$pageTitle = 'Contact';
$pageSubtitle = 'Get in touch with our team';
if ($activeThread) {
    $pageScripts = '<script src="' . e(adminAsset('js/message-thread.js')) . '"></script>';
}

require __DIR__ . '/../includes/header.php';
?>

<style>
    .contact-page .saas-card-header.contact-panel-header,
    .contact-page .saas-card-header.contact-form-header {
        padding: 1.25rem 2.5rem !important;
    }

    .contact-page .contact-info-body,
    .contact-page .contact-form-body {
        padding: 2rem 2.5rem 2.25rem !important;
    }

    .contact-page .contact-message-form .form-control {
        width: 100%;
        box-sizing: border-box;
    }

    .contact-info-list {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .contact-info-row {
        display: flex !important;
        align-items: flex-start !important;
    }

    .contact-info-icon {
        font-size: 1.125rem !important;
        color: var(--primary) !important;
        margin-right: 0.875rem !important;
        width: 1.25rem !important;
        text-align: center !important;
        line-height: 1.45 !important;
        flex-shrink: 0;
    }

    .contact-info-content {
        display: flex;
        flex-direction: column;
        gap: 0.15rem;
    }

    .contact-info-term {
        font-weight: 700 !important;
        color: var(--secondary) !important;
        display: block !important;
    }

    .contact-info-text {
        color: var(--gray-600) !important;
        font-weight: 400;
        line-height: 1.55;
    }

    .contact-info-link {
        text-decoration: none !important;
        color: var(--primary) !important;
    }

    .contact-info-link:hover {
        text-decoration: underline !important;
    }

    .contact-page .contact-quick-links-body {
        padding: 1.75rem 2.5rem 2.25rem !important;
    }

    .contact-quick-tile {
        display: flex;
        flex-direction: column;
        height: 100%;
        min-height: 148px;
        border: 1px solid var(--gray-200);
        border-radius: 12px;
        overflow: hidden;
        text-decoration: none !important;
        color: inherit;
        background: var(--white);
        box-shadow: 0 2px 8px rgba(0, 24, 44, 0.06);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .contact-quick-tile:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(0, 24, 44, 0.1);
        color: inherit;
    }

    .contact-quick-tile-accent {
        height: 10px;
        background: var(--primary);
        flex-shrink: 0;
    }

    .contact-quick-tile-main {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 1.125rem 1rem 1rem;
        flex: 1;
    }

    .contact-quick-tile-icon {
        width: 42px;
        height: 42px;
        border-radius: 10px;
        background: var(--primary-light);
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.125rem;
        flex-shrink: 0;
    }

    .contact-quick-tile-title {
        font-weight: 700;
        font-size: 0.9375rem;
        color: var(--secondary);
        line-height: 1.3;
    }

    .contact-quick-tile-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        padding: 0.875rem 1rem 1.125rem;
        border-top: 1px solid var(--gray-100);
        font-size: 0.8125rem;
        font-weight: 500;
        color: var(--gray-600);
    }

    .contact-quick-tile-footer i {
        color: var(--primary);
        font-size: 0.875rem;
        flex-shrink: 0;
    }

    .contact-thread-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .contact-thread-link {
        display: block;
        padding: 0.875rem 1rem;
        border: 1px solid var(--gray-200);
        border-radius: 10px;
        text-decoration: none !important;
        color: inherit;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .contact-thread-link:hover,
    .contact-thread-link.active {
        border-color: var(--primary);
        box-shadow: 0 0 0 1px color-mix(in srgb, var(--primary) 25%, transparent);
    }

    .contact-thread-link strong {
        display: block;
        color: var(--secondary);
        margin-bottom: 0.25rem;
    }

    .contact-thread-link span {
        display: block;
        font-size: 0.8125rem;
        color: var(--gray-600);
    }

    .contact-thread-link small {
        display: block;
        margin-top: 0.35rem;
        font-size: 0.75rem;
        color: var(--gray-500);
    }
</style>
<div class="contact-page">
    <div class="row g-4 contact-page-top">
        <div class="col-lg-6">
            <div class="saas-card h-100 contact-panel">
                <div class="saas-card-header contact-panel-header">
                    <h2 class="saas-card-title mb-0">Office Information</h2>
                </div>
                <div class="card-body contact-info-body">
                    <div class="contact-info-list">
                        <div class="contact-info-row">
                            <div class="contact-info-icon"><i class="bi bi-building"></i></div>
                            <div class="contact-info-content">
                                <span class="contact-info-term">Company:</span>
                                <span class="contact-info-text"><?= e(companyBrandName($company)) ?></span>
                            </div>
                        </div>

                        <?php if (!empty($company['description'])): ?>
                            <div class="contact-info-row">
                                <div class="contact-info-icon"><i class="bi bi-briefcase"></i></div>
                                <div class="contact-info-content">
                                    <span class="contact-info-term">Services:</span>
                                    <span class="contact-info-text"><?= e($company['description']) ?></span>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($company['office_email'])): ?>
                            <div class="contact-info-row">
                                <div class="contact-info-icon"><i class="bi bi-envelope"></i></div>
                                <div class="contact-info-content">
                                    <span class="contact-info-term">Email Us:</span>
                                    <a href="mailto:<?= e($company['office_email']) ?>" class="contact-info-text contact-info-link">
                                        <?= e($company['office_email']) ?>
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="contact-info-row">
                            <div class="contact-info-icon"><i class="bi bi-telephone"></i></div>
                            <div class="contact-info-content">
                                <span class="contact-info-term">Contact Us:</span>
                                <a href="tel:<?= e(preg_replace('/\s+/', '', $contactPhone)) ?>" class="contact-info-text contact-info-link">
                                    <?= e($contactPhone) ?>
                                </a>
                            </div>
                        </div>

                        <div class="contact-info-row">
                            <div class="contact-info-icon"><i class="bi bi-clock"></i></div>
                            <div class="contact-info-content">
                                <span class="contact-info-term">Business Hours:</span>
                                <span class="contact-info-text">
                                    Monday – Friday: 9:00 AM – 5:00 PM<br>
                                    <span style="color: #6c757d;">Saturday – Sunday: Closed</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <?php if ($activeThread): ?>
                <?php
                $activeMessages = $activeThread['messages'] ?? [];
                $threadClosed = ($activeThread['status'] ?? '') === 'closed';
                ?>
                <div class="contact-chat-stack">
                    <a href="<?= clientUrl('pages/contact.php') ?>" class="btn btn-soft btn-sm contact-thread-back">
                        <i class="bi bi-arrow-left me-1"></i> Back
                    </a>
                    <div class="saas-card contact-panel contact-thread-card">
                        <div class="saas-card-header contact-thread-header message-view-header--brand">
                            <div class="message-view-header-main">
                                <h2 class="saas-card-title mb-0"><?= e((string) ($activeThread['subject'] ?? '')) ?></h2>
                                <p class="saas-card-subtitle mb-0">
                                    <?= formatDateTime((string) ($activeThread['created_at'] ?? '')) ?>
                                    · <?= $threadClosed ? 'Closed' : 'Open' ?>
                                </p>
                            </div>
                            <div class="message-view-header-meta contact-thread-header-actions">
                                <?php if (!$messagingBlocked): ?>
                                    <a href="<?= clientUrl('pages/contact.php') ?>" class="btn btn-light btn-sm">New message</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body message-thread-body message-thread-body--panel contact-thread-body">
                            <?php foreach ($activeMessages as $message): ?>
                                <?php
                                $isOutbound = ($message['direction'] ?? '') === 'outbound';
                                $sender     = $isOutbound ? companyBrandName($company) : 'You';
                                $canEditMessage = false;
                                $editFormAction = clientUrl('actions/contact-message-edit-action.php');
                                require __DIR__ . '/../../admin/pages/partials/message-thread-item.php';
                                ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <?php if (!$threadClosed && !$messagingBlocked): ?>
                        <div class="saas-card contact-panel message-reply-card contact-reply-card">
                            <div class="saas-card-header message-view-header message-view-header--brand">
                                <h3 class="saas-card-title mb-0">Your reply</h3>
                            </div>
                            <div class="card-body message-reply-body contact-thread-reply-body">
                                <label class="form-label contact-form-label" for="reply">Message</label>
                                <textarea id="reply"
                                          name="message"
                                          form="contactReplyForm"
                                          class="form-control mb-3"
                                          rows="4"
                                          required
                                          placeholder="Write a follow-up message..."></textarea>
                                <div class="contact-thread-reply-actions">
                                    <form method="post"
                                          action="<?= clientUrl('actions/contact-clear-action.php') ?>"
                                          class="m-0 contact-clear-form"
                                          onsubmit="return confirm('Clear this entire chat? This cannot be undone.');">
                                        <?= CSRF::field() ?>
                                        <input type="hidden" name="thread_id" value="<?= (int) $activeThread['id'] ?>">
                                        <button type="submit" class="btn btn-soft-danger btn-sm contact-thread-action-btn">
                                            <i class="bi bi-trash me-2"></i> Clear chat
                                        </button>
                                    </form>
                                    <form method="post" action="<?= clientUrl('actions/contact-reply-action.php') ?>" id="contactReplyForm" class="m-0">
                                        <?= CSRF::field() ?>
                                        <input type="hidden" name="thread_id" value="<?= (int) $activeThread['id'] ?>">
                                        <button type="submit" class="btn btn-primary btn-sm contact-thread-action-btn">
                                            <i class="bi bi-send me-2"></i> Send reply
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($messagingBlocked): ?>
                        <div class="alert alert-warning border mb-0">
                            Your ability to send messages has been restricted. Please contact the office by phone or email.
                        </div>
                    <?php else: ?>
                        <div class="saas-card contact-panel contact-thread-footer-card">
                            <div class="card-body contact-thread-footer">
                                <p class="text-muted small mb-0">This chat has been closed by the office.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
            <div class="saas-card h-100 contact-panel">
                <div class="saas-card-header contact-form-header">
                    <h2 class="saas-card-title mb-0">Send a Message</h2>
                    <?php if (!$messagingBlocked): ?>
                        <p class="contact-response-note mb-0">We typically respond with one or two business day(s).</p>
                    <?php endif; ?>
                </div>
                <div class="card-body contact-form-body">
                    <?php if ($messagingBlocked): ?>
                        <div class="alert alert-warning border mb-0">
                            Your ability to send messages has been restricted. Please contact the office by phone or email.
                        </div>
                    <?php else: ?>
                    <form method="post" action="<?= clientUrl('actions/contact-action.php') ?>" class="contact-message-form">
                        <?= CSRF::field() ?>
                        <div class="mb-3">
                            <label class="form-label contact-form-label" for="subject">Subject</label>
                            <input type="text" id="subject" name="subject" class="form-control" required
                                   value="<?= e(old('subject')) ?>" placeholder="How can we help?">
                        </div>
                        <div class="mb-4">
                            <label class="form-label contact-form-label" for="message">Message</label>
                            <textarea id="message" name="message" class="form-control" rows="7" required
                                      placeholder="Write your message here..."><?= e(old('message')) ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send me-2"></i> Send Message
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($clientThreads !== [] && !$activeThread): ?>
        <div class="saas-card contact-panel mt-4">
            <div class="saas-card-header contact-panel-header">
                <h2 class="saas-card-title mb-0">Your Messages</h2>
            </div>
            <div class="card-body contact-info-body">
                <div class="contact-thread-list">
                    <?php foreach ($clientThreads as $thread): ?>
                        <a href="<?= clientUrl('pages/contact.php?thread=' . (int) $thread['id']) ?>" class="contact-thread-link">
                            <strong><?= e((string) ($thread['subject'] ?? '')) ?></strong>
                            <span><?= e(mb_strimwidth((string) ($thread['preview'] ?? ''), 0, 120, '…')) ?></span>
                            <small><?= timeAgo((string) ($thread['last_message_at'] ?? '')) ?> · <?= ($thread['status'] ?? '') === 'closed' ? 'Closed' : 'Open' ?></small>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="saas-card contact-quick-links-card contact-panel mt-4">
        <div class="saas-card-header contact-panel-header">
            <div>
                <h2 class="saas-card-title mb-0">Quick Links</h2>
                <p class="saas-card-subtitle mb-0">Common actions in your portal</p>
            </div>
        </div>
        <div class="card-body contact-quick-links-body">
            <div class="row g-3 g-lg-4">
                <div class="col-sm-6 col-xl-3">
                    <a href="<?= clientUrl('pages/cases.php') ?>" class="contact-quick-tile">
                        <div class="contact-quick-tile-accent"></div>
                        <div class="contact-quick-tile-main">
                            <div class="contact-quick-tile-icon"><i class="bi bi-briefcase"></i></div>
                            <span class="contact-quick-tile-title">Cases</span>
                        </div>
                        <div class="contact-quick-tile-footer">
                            <span>My Cases</span>
                            <i class="bi bi-arrow-right"></i>
                        </div>
                    </a>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <a href="<?= clientUrl('pages/payments.php') ?>" class="contact-quick-tile">
                        <div class="contact-quick-tile-accent"></div>
                        <div class="contact-quick-tile-main">
                            <div class="contact-quick-tile-icon"><i class="bi bi-credit-card"></i></div>
                            <span class="contact-quick-tile-title">Invoice/Payment</span>
                        </div>
                        <div class="contact-quick-tile-footer">
                            <span>Check Invoices &amp; Payments</span>
                            <i class="bi bi-arrow-right"></i>
                        </div>
                    </a>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <a href="<?= clientUrl('pages/appointments.php') ?>" class="contact-quick-tile">
                        <div class="contact-quick-tile-accent"></div>
                        <div class="contact-quick-tile-main">
                            <div class="contact-quick-tile-icon"><i class="bi bi-calendar3"></i></div>
                            <span class="contact-quick-tile-title">Appointments</span>
                        </div>
                        <div class="contact-quick-tile-footer">
                            <span>View Appointments</span>
                            <i class="bi bi-arrow-right"></i>
                        </div>
                    </a>
                </div>
                <div class="col-sm-6 col-xl-3">
                    <a href="<?= clientUrl('pages/notifications.php') ?>" class="contact-quick-tile">
                        <div class="contact-quick-tile-accent"></div>
                        <div class="contact-quick-tile-main">
                            <div class="contact-quick-tile-icon"><i class="bi bi-bell"></i></div>
                            <span class="contact-quick-tile-title">Notification</span>
                        </div>
                        <div class="contact-quick-tile-footer">
                            <span>All Notifications</span>
                            <i class="bi bi-arrow-right"></i>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>