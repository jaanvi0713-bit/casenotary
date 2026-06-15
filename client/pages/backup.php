<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireClient();

$clientId = Auth::clientId();
if (!$clientId) {
    flash('error', 'Client profile not found.');
    header('Location: ' . clientUrl('pages/dashboard.php'));
    exit;
}

$user = Auth::user();
$pageTitle = 'My Data Backup';
$pageSubtitle = 'Download or email a copy of your portal data';
$profile = ProfileService::getById((int) Auth::id()) ?? $user ?? [];
$clientEmail = trim((string) ($profile['email'] ?? ''));
$stats = getClientDashboardStats($clientId);
$caseCount = count(getClientCases($clientId));

require __DIR__ . '/../includes/header.php';
?>

<div class="saas-card">
    <div class="saas-card-header appointment-list-header">
        <div>
            <h2 class="saas-card-title">My Data Backup</h2>
            <p class="saas-card-subtitle mb-0">Export your profile, cases, invoices, payments, and appointments</p>
        </div>
    </div>
    <div class="card-body p-4 backup-settings-page">
        <div class="backup-settings-grid">
            <div class="settings-form-section backup-settings-card">
                <div class="settings-form-section__header backup-settings-card__header backup-settings-card__header--download">
                    <div class="backup-settings-card__icon" aria-hidden="true"><i class="bi bi-cloud-download"></i></div>
                    <div>
                        <h3 class="settings-form-section__title">Download my data</h3>
                        <p class="settings-form-section__desc mb-0">Download a JSON file with all your portal records.</p>
                    </div>
                </div>
                <div class="settings-form-section__body">
                    <p class="backup-settings-card__hint">
                        Includes your profile, <?= number_format($caseCount) ?> case(s),
                        invoices, payments, appointments, documents (paths), letters, and quotations linked to your account.
                        A copy is also emailed to <strong><?= e($clientEmail) ?></strong>.
                    </p>
                    <a href="<?= clientUrl('actions/backup-download.php') ?>" class="btn btn-primary">
                        <i class="bi bi-download me-1"></i> Download Backup Now
                    </a>
                </div>
            </div>

            <div class="settings-form-section backup-settings-card">
                <div class="settings-form-section__header backup-settings-card__header backup-settings-card__header--restore">
                    <div class="backup-settings-card__icon" aria-hidden="true"><i class="bi bi-envelope-arrow-up"></i></div>
                    <div>
                        <h3 class="settings-form-section__title">Email my data</h3>
                        <p class="settings-form-section__desc mb-0">Receive the backup file by email without downloading.</p>
                    </div>
                </div>
                <div class="settings-form-section__body">
                    <p class="backup-settings-card__hint mb-3">
                        We will send the same JSON export to <strong><?= e($clientEmail) ?></strong>. Only you receive this email.
                    </p>
                    <form method="post" action="<?= clientUrl('actions/backup-request.php') ?>">
                        <?= CSRF::field() ?>
                        <button type="submit" class="btn btn-soft" onclick="return confirm('Email your data backup to <?= e($clientEmail) ?>?')">
                            <i class="bi bi-envelope me-1"></i> Email Backup Now
                        </button>
                    </form>
                </div>
            </div>

            <div class="settings-form-section backup-settings-card backup-settings-card--wide">
                <div class="settings-form-section__header backup-settings-card__header backup-settings-card__header--schedule">
                    <div class="backup-settings-card__icon" aria-hidden="true"><i class="bi bi-info-circle"></i></div>
                    <div>
                        <h3 class="settings-form-section__title">What is included</h3>
                        <p class="settings-form-section__desc mb-0">Your personal website data from the client portal.</p>
                    </div>
                </div>
                <div class="settings-form-section__body">
                    <div class="backup-schedule-info">
                        <div class="backup-schedule-info__item">
                            <i class="bi bi-person"></i>
                            <span>Profile and account details (no passwords)</span>
                        </div>
                        <div class="backup-schedule-info__item">
                            <i class="bi bi-briefcase"></i>
                            <span>Cases, invoices, payments, receipts, and appointments</span>
                        </div>
                        <div class="backup-schedule-info__item">
                            <i class="bi bi-file-earmark-text"></i>
                            <span>Document paths and client letters (PDF files are not embedded)</span>
                        </div>
                        <div class="backup-schedule-info__item">
                            <i class="bi bi-shield-check"></i>
                            <span>Sent only to your email — administrators do not receive client backups</span>
                        </div>
                        <div class="backup-schedule-info__item">
                            <i class="bi bi-arrow-repeat"></i>
                            <span>Server copies kept for <?= BackupService::RETENTION_DAYS ?> days</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
