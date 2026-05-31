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
$pageTitle = 'Contact';
$pageSubtitle = 'Get in touch with us';

require __DIR__ . '/../includes/header.php';
?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="saas-card h-100">
            <div class="saas-card-header">
                <div>
                    <h2 class="saas-card-title">Contact</h2>
                    <p class="saas-card-subtitle mb-0">We typically respond with one or two business day(s).</p>
                </div>
            </div>
            <div class="card-body pt-4">
                <div class="row g-4">
                    <?php if (!empty($company['office_email'])): ?>
                        <div class="col-sm-6">
                            <div class="metric-card h-100">
                                <div class="metric-icon metric-icon-primary"><i class="bi bi-envelope"></i></div>
                                <div class="metric-body">
                                    <span class="metric-label">Email</span>
                                    <a href="mailto:<?= e($company['office_email']) ?>" class="metric-value metric-value-sm text-decoration-none">
                                        <?= e($company['office_email']) ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($company['office_phone'])): ?>
                        <div class="col-sm-6">
                            <div class="metric-card h-100">
                                <div class="metric-icon metric-icon-success"><i class="bi bi-telephone"></i></div>
                                <div class="metric-body">
                                    <span class="metric-label">Phone</span>
                                    <a href="tel:<?= e(preg_replace('/\s+/', '', $company['office_phone'])) ?>" class="metric-value metric-value-sm text-decoration-none">
                                        <?= e($company['office_phone']) ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (empty($company['office_email']) && empty($company['office_phone'])): ?>
                    <div class="empty-state py-4 mt-2">
                        <i class="bi bi-envelope"></i>
                        <p class="mb-0">Contact details have not been configured yet. Please check back later.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="saas-card h-100">
            <div class="saas-card-header">
                <div>
                    <h2 class="saas-card-title">Quick Links</h2>
                    <p class="saas-card-subtitle mb-0">Common actions in your portal</p>
                </div>
            </div>
            <div class="card-body pt-4">
                <div class="d-grid gap-3">
                    <a href="<?= clientUrl('pages/cases.php') ?>" class="btn btn-soft text-start py-2">
                        <i class="bi bi-briefcase me-2"></i> View my cases
                    </a>
                    <a href="<?= clientUrl('pages/payments.php') ?>" class="btn btn-soft text-start py-2">
                        <i class="bi bi-receipt me-2"></i> Check invoices & payments
                    </a>
                    <a href="<?= clientUrl('pages/appointments.php') ?>" class="btn btn-soft text-start py-2">
                        <i class="bi bi-calendar3 me-2"></i> View appointments
                    </a>
                    <a href="<?= clientUrl('pages/notifications.php') ?>" class="btn btn-soft text-start py-2">
                        <i class="bi bi-bell me-2"></i> Notifications
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
