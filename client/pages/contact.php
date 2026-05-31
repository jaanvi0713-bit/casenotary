<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireClient();

$clientId = Auth::clientId();
if (!$clientId) {
    flash('error', 'Client profile not found.');
    header('Location: ' . adminUrl('auth/login.php?portal=client'));
    exit;
}

$company = getCompanySettings();
$client = ClientService::getById($clientId);
$pageTitle = 'Contact';
$pageSubtitle = 'Get in touch with our team';

require __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="saas-card h-100">
            <div class="saas-card-header">
                <h2 class="saas-card-title mb-0">Office Information</h2>
            </div>
            <div class="card-body contact-info-body">
                <div class="contact-info-list">
                    <div class="contact-info-item">
                        <span class="contact-info-label">Company</span>
                        <span class="contact-info-value"><?= e($company['company_name']) ?></span>
                    </div>

                    <?php if (!empty($company['description'])): ?>
                        <div class="contact-info-item">
                            <p class="contact-info-desc mb-0"><?= nl2br(e($company['description'])) ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($company['office_email'])): ?>
                        <div class="contact-info-item">
                            <span class="contact-info-label">Email</span>
                            <a href="mailto:<?= e($company['office_email']) ?>" class="contact-info-value contact-info-link">
                                <?= e($company['office_email']) ?>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($company['office_phone'])): ?>
                        <div class="contact-info-item">
                            <span class="contact-info-label">Phone</span>
                            <a href="tel:<?= e(preg_replace('/\s+/', '', $company['office_phone'])) ?>" class="contact-info-value contact-info-link">
                                <?= e($company['office_phone']) ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (empty($company['office_email']) && empty($company['office_phone'])): ?>
                    <div class="empty-state py-4">
                        <i class="bi bi-envelope"></i>
                        <p class="mb-0">Contact details have not been configured yet. Please check back later.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="saas-card h-100">
            <div class="saas-card-header contact-form-header">
                <h2 class="saas-card-title mb-0">Send a Message</h2>
                <p class="contact-response-note mb-0">We typically respond with one or two business day(s).</p>
            </div>
            <div class="card-body contact-form-body">
                <form method="post" action="<?= clientUrl('actions/contact-action.php') ?>" class="contact-message-form">
                    <?= CSRF::field() ?>
                    <div class="mb-4">
                        <label class="form-label contact-form-label" for="subject">Subject</label>
                        <input type="text" id="subject" name="subject" class="form-control" required
                               value="<?= e(old('subject')) ?>" placeholder="How can we help?">
                    </div>
                    <div class="mb-4">
                        <label class="form-label contact-form-label" for="message">Message</label>
                        <textarea id="message" name="message" class="form-control" rows="6" required
                                  placeholder="Write your message here..."><?= e(old('message')) ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send me-2"></i> Send Message
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-1">
    <div class="col-12">
        <div class="saas-card">
            <div class="saas-card-header">
                <div>
                    <h2 class="saas-card-title mb-0">Quick Links</h2>
                    <p class="saas-card-subtitle mb-0">Common actions in your portal</p>
                </div>
            </div>
            <div class="card-body contact-quick-links-body">
                <div class="row g-3">
                    <div class="col-sm-6 col-lg-3">
                        <a href="<?= clientUrl('pages/cases.php') ?>" class="btn btn-soft w-100 text-start py-2">
                            <i class="bi bi-briefcase me-2"></i> View my cases
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a href="<?= clientUrl('pages/payments.php') ?>" class="btn btn-soft w-100 text-start py-2">
                            <i class="bi bi-receipt me-2"></i> Check invoices & payments
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a href="<?= clientUrl('pages/appointments.php') ?>" class="btn btn-soft w-100 text-start py-2">
                            <i class="bi bi-calendar3 me-2"></i> View appointments
                        </a>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <a href="<?= clientUrl('pages/notifications.php') ?>" class="btn btn-soft w-100 text-start py-2">
                            <i class="bi bi-bell me-2"></i> Notifications
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
