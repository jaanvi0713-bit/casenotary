<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requirePage('settings');

$pageTitle = 'Settings';
$pageSubtitle = 'Branding, email delivery, payments, and Role Access';
$settings  = getCompanySettings();
$tab       = $_GET['tab'] ?? 'branding';
if ($tab === 'roles') {
    redirect('pages/settings-roles.php');
}
if ($tab === 'ai') {
    redirect('pages/settings.php?tab=branding');
}
$canManageSettings = Auth::can(RoleAccess::PERMISSION_SETTINGS);
$settingsNavTab = $tab;
$editableRoleKeys = $canManageSettings
    ? CompanyRoleAccessService::editableRolesForActor(Auth::role(), TenantService::id())
    : [];

if (!$canManageSettings) {
    if (!in_array($tab, ['profile', 'notifications', 'backup'], true)) {
        $tab = 'profile';
    }
    $pageSubtitle = match ($tab) {
        'notifications' => 'Notification Preferences',
        'backup'        => 'Backup & Restore',
        default         => 'Your profile',
    };
}

if ($tab === 'backup' && !Auth::isAdmin()) {
    $tab = 'profile';
}

$userId = Auth::id();
$notificationPrefs = NotificationPreferenceService::get($userId);
$preferencesReady = NotificationPreferenceService::columnExists();
$preferencesAction = url('actions/notification-action.php');
$logoUrl    = companyLogoUrl($settings);
$faviconUrl = companyFaviconUrl($settings);
$workspaceSlug = TenantService::isEnabled()
    ? CompanyService::currentSlug(TenantService::id())
    : '';
$clientLoginPreview = $workspaceSlug !== ''
    ? clientLoginUrl(TenantService::id())
    : clientLoginUrl();

if ($tab === 'branding') {
    $pageStyles = '<link href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css" rel="stylesheet">';
}

require __DIR__ . '/../includes/header.php';
?>

<?php $profileUser = Auth::user(); ?>

<?php if ($tab === 'profile'): ?>
<div class="saas-card">
    <div class="saas-card-header">
        <div>
            <h2 class="saas-card-title">My Profile</h2>
            <p class="saas-card-subtitle mb-0">Update your name, email, and password</p>
        </div>
    </div>
    <div class="card-body p-0">
        <?php require __DIR__ . '/../includes/settings-nav.php'; ?>
        <div class="p-4">
        <form method="post" action="<?= url('actions/profile-action.php') ?>" class="row g-3">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="update_profile">
            <div class="col-md-6">
                <label class="form-label">First name</label>
                <input type="text" name="first_name" class="form-control" required value="<?= e(userFirstName($profileUser)) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Last name</label>
                <input type="text" name="last_name" class="form-control" required value="<?= e(userLastName($profileUser)) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" required value="<?= e($profileUser['email'] ?? '') ?>">
            </div>
            <?php if (Database::columnExists('users', 'phone')): ?>
            <div class="col-md-6">
                <label class="form-label">Phone</label>
                <input type="text" name="phone" class="form-control" value="<?= e($profileUser['phone'] ?? '') ?>">
            </div>
            <?php endif; ?>
            <div class="col-12">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save profile</button>
            </div>
        </form>
        <hr class="my-4">
        <h3 class="h6 mb-3">Change password</h3>
        <form method="post" action="<?= url('actions/profile-action.php') ?>" class="row g-3 js-password-strength-form">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="change_password">
            <div class="col-md-4">
                <label class="form-label">Current password</label>
                <?php renderPasswordRevealField('current_password', 'current_password', [
                    'required' => true,
                    'autocomplete' => 'current-password',
                ]); ?>
            </div>
            <div class="col-md-4">
                <label class="form-label">New password</label>
                <?php renderPasswordRevealField('new_password', 'new_password', [
                    'required' => true,
                    'autocomplete' => 'new-password',
                    'strength' => true,
                ]); ?>
            </div>
            <div class="col-md-4">
                <label class="form-label">Confirm new password</label>
                <?php renderPasswordRevealField('new_password_confirmation', 'new_password_confirmation', [
                    'required' => true,
                    'autocomplete' => 'new-password',
                    'minlength' => 8,
                ]); ?>
            </div>
            <div class="col-12">
                <?php renderPasswordStrengthHint(); ?>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-outline-primary">Update password</button>
            </div>
        </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($tab === 'notifications'): ?>
<div class="saas-card">
    <div class="saas-card-header">
        <div>
            <h2 class="saas-card-title">Notification Preferences</h2>
            <p class="saas-card-subtitle mb-0">Choose which alerts you receive in the app and by email</p>
        </div>
    </div>
    <div class="card-body p-0">
        <?php require __DIR__ . '/../includes/settings-nav.php'; ?>
        <div class="p-4">
            <?php
            $notificationPrefsEmbedded = true;
            require __DIR__ . '/../includes/notification-preferences-panel.php';
            ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($tab === 'backup' && Auth::isAdmin()): ?>
<?php
    $backupStats       = getDashboardStats();
    $backupCompanyId   = TenantService::id();
    $backupRecipients  = BackupService::recipients($backupCompanyId);
    $backupEmailLabel  = $backupRecipients !== []
        ? e($backupRecipients[0]) . (count($backupRecipients) > 1 ? ' +' . (count($backupRecipients) - 1) . ' more' : '')
        : 'office email (not configured)';
?>
<div class="saas-card">
    <div class="saas-card-header appointment-list-header">
        <div>
            <h2 class="saas-card-title">Website Data Backup</h2>
            <p class="saas-card-subtitle mb-0">Export settings, clients, cases, invoices, payments, and all records</p>
        </div>
    </div>
    <div class="card-body p-0">
        <?php require __DIR__ . '/../includes/settings-nav.php'; ?>
        <div class="p-4 backup-settings-page">
            <div class="backup-settings-grid">
                <div class="settings-form-section backup-settings-card">
                    <div class="settings-form-section__header backup-settings-card__header backup-settings-card__header--download">
                        <div class="backup-settings-card__icon" aria-hidden="true"><i class="bi bi-cloud-download"></i></div>
                        <div>
                            <h3 class="settings-form-section__title">Download website data</h3>
                            <p class="settings-form-section__desc mb-0">Download a JSON file with all website records.</p>
                        </div>
                    </div>
                    <div class="settings-form-section__body">
                        <p class="backup-settings-card__hint">
                            Includes company settings, <?= number_format($backupStats['total_clients']) ?> client(s),
                            <?= number_format($backupStats['active_cases'] + $backupStats['completed_cases']) ?> case(s),
                            invoices, payments, appointments, documents (paths), letters, proposals, and quotations.
                            A copy is also emailed to <strong><?= $backupEmailLabel ?></strong>.
                        </p>
                        <a href="<?= url('actions/settings-backup.php') ?>" class="btn btn-primary">
                            <i class="bi bi-download me-1"></i> Download Backup Now
                        </a>
                    </div>
                </div>

                <div class="settings-form-section backup-settings-card">
                    <div class="settings-form-section__header backup-settings-card__header backup-settings-card__header--restore">
                        <div class="backup-settings-card__icon" aria-hidden="true"><i class="bi bi-envelope-arrow-up"></i></div>
                        <div>
                            <h3 class="settings-form-section__title">Email website data</h3>
                            <p class="settings-form-section__desc mb-0">Receive the backup file by email without downloading.</p>
                        </div>
                    </div>
                    <div class="settings-form-section__body">
                        <p class="backup-settings-card__hint mb-3">
                            We will send the same JSON export to <strong><?= $backupEmailLabel ?></strong>.
                            Administrators and office email only — clients are not included.
                        </p>
                        <form method="post" action="<?= url('actions/settings-backup-email.php') ?>">
                            <?= CSRF::field() ?>
                            <button type="submit" class="btn btn-soft" onclick="return confirm('Email the website backup to administrators?')">
                                <i class="bi bi-envelope me-1"></i> Email Backup Now
                            </button>
                        </form>
                    </div>
                </div>

                <div class="settings-form-section backup-settings-card backup-settings-card--wide">
                    <div class="settings-form-section__header backup-settings-card__header backup-settings-card__header--schedule">
                        <div class="backup-settings-card__icon" aria-hidden="true"><i class="bi bi-clock-history"></i></div>
                        <div>
                            <h3 class="settings-form-section__title">Automatic backups</h3>
                            <p class="settings-form-section__desc mb-0">Schedule weekly or monthly backups — saved on the server and emailed to admins.</p>
                        </div>
                    </div>
                    <div class="settings-form-section__body">
                        <form method="post" action="<?= url('actions/settings-action.php') ?>" class="backup-schedule-form mb-4">
                            <?= CSRF::field() ?>
                            <input type="hidden" name="tab" value="backup">
                            <div class="row g-3 align-items-end">
                                <div class="col-md-5 col-lg-4">
                                    <label class="form-label">Backup frequency</label>
                                    <select name="backup_frequency" class="form-select">
                                        <option value="never" <?= ($settings['backup_frequency'] ?? 'never') === 'never' ? 'selected' : '' ?>>Never (manual only)</option>
                                        <option value="weekly" <?= ($settings['backup_frequency'] ?? 'never') === 'weekly' ? 'selected' : '' ?>>Every week</option>
                                        <option value="monthly" <?= ($settings['backup_frequency'] ?? 'never') === 'monthly' ? 'selected' : '' ?>>Every month</option>
                                    </select>
                                </div>
                                <div class="col-md-7 col-lg-5">
                                    <?php if (!empty($settings['last_backup_at'])): ?>
                                        <p class="backup-last-run mb-2 mb-md-0">
                                            <i class="bi bi-check-circle-fill"></i>
                                            Last automatic backup:
                                            <strong><?= e(formatDateTime($settings['last_backup_at'])) ?></strong>
                                        </p>
                                    <?php else: ?>
                                        <p class="backup-last-run backup-last-run--empty mb-2 mb-md-0">
                                            <i class="bi bi-dash-circle"></i>
                                            No automatic backup has run yet
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="col-lg-3">
                                    <button type="submit" class="btn btn-primary w-100 w-lg-auto">
                                        <i class="bi bi-check-lg me-1"></i> Save Schedule
                                    </button>
                                </div>
                            </div>
                        </form>
                        <div class="backup-schedule-info">
                            <div class="backup-schedule-info__item">
                                <i class="bi bi-folder2"></i>
                                <span>Stored in <code>admin/storage/backups/</code></span>
                            </div>
                            <div class="backup-schedule-info__item">
                                <i class="bi bi-terminal"></i>
                                <span>Requires daily cron: <code>php admin/cron/auto-backup.php</code></span>
                            </div>
                            <div class="backup-schedule-info__item">
                                <i class="bi bi-arrow-repeat"></i>
                                <span>Server copies kept for <?= BackupService::RETENTION_DAYS ?> days</span>
                            </div>
                            <div class="backup-schedule-info__item">
                                <i class="bi bi-envelope-check"></i>
                                <span>Emailed to office email and admin users when created</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="settings-form-section backup-settings-card backup-settings-card--wide">
                    <div class="settings-form-section__header backup-settings-card__header backup-settings-card__header--schedule">
                        <div class="backup-settings-card__icon" aria-hidden="true"><i class="bi bi-info-circle"></i></div>
                        <div>
                            <h3 class="settings-form-section__title">What is included</h3>
                            <p class="settings-form-section__desc mb-0">Full website database export for this company.</p>
                        </div>
                    </div>
                    <div class="settings-form-section__body">
                        <div class="backup-schedule-info mb-4">
                            <div class="backup-schedule-info__item">
                                <i class="bi bi-gear"></i>
                                <span>Company settings (branding, SMTP, Stripe, bank accounts)</span>
                            </div>
                            <div class="backup-schedule-info__item">
                                <i class="bi bi-people"></i>
                                <span>Clients, staff users, cases, invoices, payments, and receipts</span>
                            </div>
                            <div class="backup-schedule-info__item">
                                <i class="bi bi-calendar-event"></i>
                                <span>Appointments, document paths, client letters, proposals, and quotations</span>
                            </div>
                            <div class="backup-schedule-info__item">
                                <i class="bi bi-file-earmark-text"></i>
                                <span>Uploaded PDFs and logos are not embedded — only database paths</span>
                            </div>
                            <div class="backup-schedule-info__item">
                                <i class="bi bi-shield-check"></i>
                                <span>Restore currently applies company settings only</span>
                            </div>
                        </div>

                        <hr class="my-4">

                        <h4 class="h6 mb-2">Restore from backup</h4>
                        <p class="backup-settings-card__hint">Upload a previously exported backup file to restore company settings.</p>
                        <form method="post" action="<?= url('actions/settings-restore.php') ?>" enctype="multipart/form-data" class="row g-3 align-items-end">
                            <?= CSRF::field() ?>
                            <div class="col-md-8 col-lg-6">
                                <label class="form-label">Backup file (.json)</label>
                                <input type="file" name="backup_file" class="form-control" accept=".json" required>
                            </div>
                            <div class="col-md-4 col-lg-3">
                                <button type="submit" class="btn btn-soft w-100" onclick="return confirm('This will overwrite current settings. Continue?')">
                                    <i class="bi bi-upload me-1"></i> Restore Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($canManageSettings && $tab !== 'backup'): ?>
<div class="saas-card<?= in_array($tab, ['profile', 'notifications'], true) ? ' mt-4' : '' ?>">
    <div class="saas-card-header appointment-calendar-header">
        <div>
            <h2 class="saas-card-title">Company Settings</h2>
            <p class="saas-card-subtitle mb-0">Branding, email delivery, and payment configuration</p>
        </div>
    </div>
    <div class="card-body p-0">
        <?php require __DIR__ . '/../includes/settings-nav.php'; ?>

        <form
            method="post"
            action="<?= url('actions/settings-action.php') ?>"
            <?= $tab === 'branding' ? 'enctype="multipart/form-data"' : '' ?>
            class="p-4"
        >
            <?= CSRF::field() ?>
            <input type="hidden" name="tab" value="<?= e($tab) ?>">

            <?php if ($tab === 'branding'): ?>
                <div class="row g-4 settings-branding-form">
                    <div class="col-12">
                        <div class="settings-form-section">
                            <div class="settings-form-section__header">
                                <h3 class="settings-form-section__title">Brand &amp; appearance</h3>
                                <p class="settings-form-section__desc">Name, typography, and colors used across the admin and client portals.</p>
                            </div>
                            <div class="settings-form-section__body row g-3">
                                <div class="col-md-8">
                                    <label class="form-label">Company Name</label>
                                    <input type="text" name="company_name" class="form-control" required value="<?= e($settings['company_name']) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Font Family</label>
                                    <?php $activeFont = companyFontFamily($settings); ?>
                                    <select name="font_family" id="fontFamilySelect" class="form-select">
                                        <?php foreach (companyFontCatalog() as $fontKey => $fontMeta): ?>
                                            <option value="<?= e($fontKey) ?>" <?= $activeFont === $fontKey ? 'selected' : '' ?>>
                                                <?= e($fontMeta['label']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php if (TenantService::isEnabled()): ?>
                                <div class="col-12">
                                    <label class="form-label">Workspace ID</label>
                                    <input type="text"
                                           name="company_slug"
                                           class="form-control"
                                           value="<?= e($workspaceSlug) ?>"
                                           pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                                           title="Lowercase letters, numbers, and hyphens only">
                                    <div class="form-text">
                                        Used in client portal login links. Update this when you rename the company so links stay clear.
                                        <?php if ($clientLoginPreview !== ''): ?>
                                            <span class="d-block mt-1"><a href="<?= e($clientLoginPreview) ?>" target="_blank" rel="noopener"><?= e($clientLoginPreview) ?></a></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <div class="col-md-4">
                                    <label class="form-label">Primary Color</label>
                                    <input type="color" name="primary_color" class="form-control form-control-color w-100" value="<?= e($settings['primary_color']) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Secondary Color</label>
                                    <input type="color" name="secondary_color" class="form-control form-control-color w-100" value="<?= e($settings['secondary_color']) ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Accent Color</label>
                                    <input type="color" name="dark_accent" class="form-control form-control-color w-100" value="<?= e($settings['dark_accent'] ?? '#000000') ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="settings-form-section">
                            <div class="settings-form-section__header">
                                <h3 class="settings-form-section__title">Company Information</h3>
                                <p class="settings-form-section__desc">Legal identifiers and primary contact details for your business.</p>
                            </div>
                            <div class="settings-form-section__body row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Company Website</label>
                                    <input type="url" name="company_website" class="form-control" value="<?= e($settings['company_website'] ?? '') ?>" placeholder="https://www.example.com">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Company Registration Number</label>
                                    <input type="text" name="registration_number" class="form-control" value="<?= e($settings['registration_number'] ?? '') ?>" placeholder="e.g. 12345678">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Tax / VAT Number</label>
                                    <input type="text" name="tax_vat_number" class="form-control" value="<?= e($settings['tax_vat_number'] ?? '') ?>" placeholder="Leave blank to auto-generate on invoices">
                                    <div class="form-text">Shown on invoices. If empty, a number is generated from your company details.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Office Email</label>
                                    <input type="email" name="office_email" class="form-control" value="<?= e($settings['office_email'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Office Phone</label>
                                    <input type="text" name="office_phone" class="form-control" value="<?= e($settings['office_phone'] ?? '') ?>" placeholder="+1 (555) 123-4567">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Street Address</label>
                                    <input type="text" name="address" class="form-control" value="<?= e($settings['address'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">City</label>
                                    <input type="text" name="city" class="form-control" value="<?= e($settings['city'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">State / Region</label>
                                    <input type="text" name="state" class="form-control" value="<?= e($settings['state'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Postal / ZIP Code</label>
                                    <input type="text" name="zip_code" class="form-control" value="<?= e($settings['zip_code'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Country</label>
                                    <input type="text" name="country" class="form-control" value="<?= e($settings['country'] ?? '') ?>">
                                </div>
                                <?php
                                $defaultBankAccount = SettingsService::defaultBankAccountChoice($settings);
                                $bankAccountSlots = [
                                    1 => 'Bank account 1',
                                    2 => 'Bank account 2',
                                    3 => 'Bank account 3',
                                ];
                                ?>
                                <div class="col-12">
                                    <div class="bank-accounts-block">
                                        <div class="bank-accounts-block__header">
                                            <div class="bank-accounts-block__icon" aria-hidden="true"><i class="bi bi-bank2"></i></div>
                                            <div>
                                                <h6 class="bank-accounts-block__title">Bank accounts for invoices</h6>
                                                <p class="bank-accounts-block__desc">Set up to three accounts. The default is pre-selected on new invoices; you can change it per invoice when generating.</p>
                                            </div>
                                        </div>

                                        <div class="bank-format-tip">
                                            <div class="bank-format-tip__label"><i class="bi bi-info-circle"></i> How this appears on invoices</div>
                                            <div class="bank-format-tip__sample">
                                                <?php foreach (SettingsService::BANK_FIELD_LABELS as $tipKey => $tipLabel): ?>
                                                    <span class="bank-format-tip__line"><em><?= e($tipLabel) ?>:</em> …</span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>

                                        <ul class="nav nav-pills bank-account-tabs" id="bankAccountTabs" role="tablist">
                                            <?php foreach ($bankAccountSlots as $num => $slotLabel): ?>
                                                <?php $hasDetails = SettingsService::bankAccountHasDetails($settings, $num); ?>
                                                <li class="nav-item" role="presentation">
                                                    <button
                                                        class="nav-link<?= $num === 1 ? ' active' : '' ?>"
                                                        id="bank-tab-<?= $num ?>"
                                                        data-bs-toggle="tab"
                                                        data-bs-target="#bank-pane-<?= $num ?>"
                                                        type="button"
                                                        role="tab"
                                                        aria-controls="bank-pane-<?= $num ?>"
                                                        <?= $num === 1 ? 'aria-selected="true"' : 'aria-selected="false"' ?>
                                                    >
                                                        <span class="bank-account-tabs__num"><?= $num ?></span>
                                                        <?= e($slotLabel) ?>
                                                        <?php if ($hasDetails): ?><span class="bank-account-tabs__dot" title="Configured"></span><?php endif; ?>
                                                    </button>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>

                                        <div class="tab-content bank-account-tab-panes">
                                            <?php foreach ($bankAccountSlots as $num => $slotLabel):
                                                $bankFields = SettingsService::bankAccountFieldsForSlot($settings, $num);
                                            ?>
                                            <div class="tab-pane fade<?= $num === 1 ? ' show active' : '' ?>" id="bank-pane-<?= $num ?>" role="tabpanel" aria-labelledby="bank-tab-<?= $num ?>" tabindex="0">
                                                <div class="bank-account-card">
                                                    <div class="bank-account-card__head">
                                                        <span class="bank-account-card__badge"><?= $num ?></span>
                                                        <div>
                                                            <strong><?= e($slotLabel) ?></strong>
                                                            <span class="text-muted small d-block">Shown on invoices when this account is selected</span>
                                                        </div>
                                                    </div>
                                                    <div class="row g-3">
                                                        <?php foreach (SettingsService::BANK_FIELD_LABELS as $fieldKey => $fieldLabel): ?>
                                                            <div class="col-md-6">
                                                                <label class="form-label" for="bank_<?= $num ?>_<?= e($fieldKey) ?>"><?= e($fieldLabel) ?></label>
                                                                <div class="input-group bank-field-input">
                                                                    <span class="input-group-text"><i class="bi <?= e(SettingsService::bankFieldIcon($fieldKey)) ?>"></i></span>
                                                                    <input
                                                                        type="text"
                                                                        class="form-control"
                                                                        id="bank_<?= $num ?>_<?= e($fieldKey) ?>"
                                                                        name="bank_accounts[<?= $num ?>][<?= e($fieldKey) ?>]"
                                                                        value="<?= e($bankFields[$fieldKey] ?? '') ?>"
                                                                        placeholder="<?= e(match ($fieldKey) {
                                                                            'bank_name' => 'Barclays Bank',
                                                                            'account_name' => 'YOUR COMPANY LTD',
                                                                            'account_number' => '12345678',
                                                                            'sort_code' => '20-00-00',
                                                                            'iban' => 'GB00 BARC 2000 0012 3456 78',
                                                                            'bic' => 'BARCGB22',
                                                                            'reference' => 'Quote invoice number',
                                                                            default => '',
                                                                        }) ?>"
                                                                    >
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <div class="bank-accounts-default">
                                            <label class="form-label" for="invoice_bank_account"><i class="bi bi-star-fill bank-accounts-default__icon me-1"></i> Default account on invoices</label>
                                            <select name="invoice_bank_account" id="invoice_bank_account" class="form-select">
                                                <?php foreach ($bankAccountSlots as $num => $slotLabel): ?>
                                                    <?php
                                                    $preview = SettingsService::bankAccountLabel(
                                                        SettingsService::formatBankAccountText(
                                                            SettingsService::bankAccountFieldsForSlot($settings, $num)
                                                        ),
                                                        $num
                                                    );
                                                    ?>
                                                    <option value="<?= $num ?>" <?= $defaultBankAccount === $num ? 'selected' : '' ?>><?= e($preview) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="settings-form-section">
                            <div class="settings-form-section__header">
                                <h3 class="settings-form-section__title">Social Media</h3>
                                <p class="settings-form-section__desc">Profile links shown on the client portal and documents where applicable.</p>
                            </div>
                            <div class="settings-form-section__body row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Facebook URL</label>
                                    <input type="url" name="facebook_url" class="form-control" value="<?= e($settings['facebook_url'] ?? '') ?>" placeholder="https://facebook.com/...">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Instagram URL</label>
                                    <input type="url" name="instagram_url" class="form-control" value="<?= e($settings['instagram_url'] ?? '') ?>" placeholder="https://instagram.com/...">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">LinkedIn URL</label>
                                    <input type="url" name="linkedin_url" class="form-control" value="<?= e($settings['linkedin_url'] ?? '') ?>" placeholder="https://linkedin.com/company/...">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="settings-form-section">
                            <div class="settings-form-section__header">
                                <h3 class="settings-form-section__title">Office &amp; description</h3>
                                <p class="settings-form-section__desc">Hours, location, and a short summary of your services.</p>
                            </div>
                            <div class="settings-form-section__body row g-3">
                                <div class="col-12">
                                    <label class="form-label">Business Hours</label>
                                    <textarea name="business_hours" class="form-control" rows="3" placeholder="Monday – Friday: 9:00 AM – 5:00 PM"><?= e($settings['business_hours'] ?? '') ?></textarea>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control" rows="3"><?= e($settings['description'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="settings-form-section">
                            <div class="settings-form-section__header">
                                <h3 class="settings-form-section__title">Logo &amp; favicon</h3>
                                <p class="settings-form-section__desc">Upload a square logo for the sidebar and login page, plus a browser tab icon.</p>
                            </div>
                            <div class="settings-form-section__body">
                        <label class="form-label d-block">Company Logo</label>
                        <div class="logo-branding-panel mb-4">
                            <div class="logo-upload-toolbar">
                                <input type="file" id="logoFileInput" name="logo" class="form-control" accept=".jpg,.jpeg,.png,.webp,.svg,image/*">
                                <?php if ($logoUrl): ?>
                                    <button type="button" class="btn btn-soft btn-sm" id="logoEditCurrentBtn">
                                        <i class="bi bi-crop"></i> Edit logo
                                    </button>
                                    <button type="submit" name="remove_logo" value="1" class="btn btn-soft-danger btn-sm" onclick="return confirm('Remove the company logo?');">Remove logo</button>
                                <?php endif; ?>
                            </div>
                            <div class="logo-placement-preview">
                                <p class="logo-placement-preview-title">Where your logo appears</p>
                                <div class="logo-placement-grid">
                                    <div class="logo-placement-card">
                                        <p class="logo-placement-name">Sidebar</p>
                                        <div class="logo-placement-stage logo-placement-stage--sidebar">
                                            <div class="logo-placement-mock-bar">
                                                <div class="logo-frame-preview logo-frame-preview--sidebar" id="logoPreviewSidebar">
                                                    <?php if ($logoUrl): ?>
                                                        <img src="<?= e($logoUrl) ?>" alt="">
                                                    <?php else: ?>
                                                        <?= renderCompanyLogo('sidebar', $settings, 'admin') ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="logo-placement-mock-copy">
                                                    <span class="logo-placement-mock-title"><?= e(companyBrandName($settings)) ?></span>
                                                    <span class="logo-placement-mock-tag">Admin</span>
                                                </div>
                                            </div>
                                        </div>
                                        <span class="logo-placement-meta">38 × 38 px</span>
                                    </div>
                                    <div class="logo-placement-card">
                                        <p class="logo-placement-name">Login page</p>
                                        <div class="logo-placement-stage logo-placement-stage--auth">
                                            <div class="logo-frame-preview logo-frame-preview--auth" id="logoPreviewAuth">
                                                <?php if ($logoUrl): ?>
                                                    <img src="<?= e($logoUrl) ?>" alt="">
                                                <?php else: ?>
                                                    <span class="logo-frame-preview-empty" aria-hidden="true"><i class="bi bi-image"></i></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <span class="logo-placement-meta">64 × 64 px</span>
                                    </div>
                                </div>
                                <?php if (!$logoUrl): ?>
                                    <p class="logo-placement-footnote mb-0">Until you upload a logo, the sidebar shows a default icon.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <label class="form-label d-block">Favicon</label>
                        <div class="logo-branding-panel favicon-branding-panel">
                            <div class="logo-upload-toolbar">
                                <input type="file" name="favicon" class="form-control" accept=".ico,.png,image/x-icon,image/png">
                                <?php if ($faviconUrl): ?>
                                    <button type="submit" name="remove_favicon" value="1" class="btn btn-soft-danger btn-sm" onclick="return confirm('Remove the favicon?');">Remove favicon</button>
                                <?php endif; ?>
                            </div>
                            <div class="favicon-preview-wrap">
                                <?php if ($faviconUrl): ?>
                                    <div class="favicon-tab-mock" aria-hidden="true">
                                        <span class="favicon-tab-mock-icon">
                                            <img src="<?= e($faviconUrl) ?>" alt="">
                                        </span>
                                        <span class="favicon-tab-mock-title"><?= e(companyBrandName($settings)) ?></span>
                                    </div>
                                    <img src="<?= e($faviconUrl) ?>" alt="Favicon preview" class="favicon-preview-img" width="32" height="32">
                                <?php else: ?>
                                    <p class="favicon-preview-empty mb-0">No favicon uploaded — the browser uses its default tab icon.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php elseif ($tab === 'email'): ?>
                <div class="alert alert-info border-0 small">
                    Configure SMTP to send quotation, login, and appointment emails. Leave host empty to use PHP <code>mail()</code> (logged in debug mode).
                </div>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">SMTP Host</label>
                        <input type="text" name="smtp_host" class="form-control" placeholder="smtp.gmail.com" value="<?= e($settings['smtp_host'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">SMTP Port</label>
                        <input type="number" name="smtp_port" class="form-control" value="<?= (int) ($settings['smtp_port'] ?? 587) ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">SMTP Username</label>
                        <input type="text" name="smtp_username" class="form-control" value="<?= e($settings['smtp_username'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Encryption</label>
                        <select name="smtp_encryption" class="form-select">
                            <?php foreach (['tls', 'ssl', 'none'] as $enc): ?>
                                <option value="<?= $enc ?>" <?= ($settings['smtp_encryption'] ?? 'tls') === $enc ? 'selected' : '' ?>><?= strtoupper($enc) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row g-3 account-password-grid align-items-end mt-1 pt-3 border-top">
                    <div class="col-md-4">
                        <label class="form-label" for="current_smtp_password_display">Current SMTP password</label>
                        <div class="login-pw-field login-pw-field--static">
                            <div class="login-pw-input-wrap">
                                <input
                                    type="text"
                                    id="current_smtp_password_display"
                                    class="form-control login-pw-input"
                                    value="<?= !empty($settings['smtp_password']) ? '••••••••' : 'Not set' ?>"
                                    disabled
                                    aria-label="Current SMTP password"
                                >
                                <span class="login-pw-reveal login-pw-reveal--spacer" aria-hidden="true"></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="new_smtp_password">New SMTP password</label>
                        <?php renderPasswordRevealField('new_smtp_password', 'new_smtp_password', [
                            'autocomplete' => 'new-password',
                        ]); ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="new_smtp_password_confirmation">Confirm new SMTP password</label>
                        <?php renderPasswordRevealField('new_smtp_password_confirmation', 'new_smtp_password_confirmation', [
                            'autocomplete' => 'new-password',
                        ]); ?>
                    </div>
                    <div class="col-12">
                        <p class="form-text mb-0">Leave new SMTP password blank to keep the current password.</p>
                    </div>
                </div>
            <?php elseif ($tab === 'payments'): ?>
                <div class="settings-form-section mb-4">
                    <div class="settings-form-section__header">
                        <h3 class="settings-form-section__title">Invoice &amp; bank details</h3>
                        <p class="settings-form-section__desc mb-0">Shown on generated invoices (Payable To, account details, and default payment terms). Company address and VAT number are set under Branding.</p>
                    </div>
                    <div class="settings-form-section__body row g-3 mt-2">
                        <div class="col-md-6">
                            <label class="form-label">Payable to (legal name)</label>
                            <input type="text" name="invoice_payable_name" class="form-control" value="<?= e($settings['invoice_payable_name'] ?? '') ?>" placeholder="e.g. WHARF NOTARIES LTD">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Default payment terms</label>
                            <input type="text" name="default_invoice_payment_terms" class="form-control" value="<?= e($settings['default_invoice_payment_terms'] ?? '') ?>" placeholder="Payment due within 14 days">
                        </div>
                        <div class="col-12">
                            <p class="text-muted small mb-0">Bank account details are configured under <a href="<?= url('pages/settings.php?tab=branding') ?>">Branding → Company Information</a>.</p>
                        </div>
                    </div>
                </div>
                <div class="alert alert-info border-0 small mb-3">
                    Add your Stripe publishable and secret keys to enable client online checkout. Manual payments can still be recorded from the Payments page or case workspace.
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Stripe Publishable Key</label>
                        <input type="text" name="stripe_public_key" class="form-control" value="<?= e($settings['stripe_public_key'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Stripe Secret Key</label>
                        <?php renderPasswordRevealField('stripe_secret_key', 'stripe_secret_key', [
                            'placeholder' => !empty($settings['stripe_secret_key']) ? '••••••••' : '',
                            'autocomplete' => 'off',
                        ]); ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save Settings</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if ($canManageSettings && $tab === 'branding'): ?>
<div class="modal fade" id="logoCropModal" tabindex="-1" aria-labelledby="logoCropModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="logoCropModalLabel">Adjust company logo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="logo-crop-aspect-btns mb-3" role="group" aria-label="Crop aspect ratio">
                    <button type="button" class="btn btn-soft btn-sm active" data-logo-aspect="1">Square (recommended)</button>
                    <button type="button" class="btn btn-soft btn-sm" data-logo-aspect="0">Free crop</button>
                </div>
                <div class="logo-crop-workspace">
                    <div class="logo-crop-stage">
                        <img src="" alt="" id="logoCropImage" class="logo-crop-image">
                    </div>
                    <div class="logo-crop-previews">
                        <p class="logo-crop-previews-title">Live preview</p>
                        <div class="logo-crop-preview-stack">
                            <div class="logo-placement-stage logo-placement-stage--sidebar logo-crop-live-stage">
                                <div class="logo-placement-mock-bar">
                                    <div class="logo-frame-preview logo-frame-preview--sidebar logo-crop-live" id="logoCropLiveSidebar"></div>
                                    <div class="logo-placement-mock-copy">
                                        <span class="logo-placement-mock-title">Sidebar</span>
                                    </div>
                                </div>
                            </div>
                            <div class="logo-placement-stage logo-placement-stage--auth logo-crop-live-stage">
                                <div class="logo-frame-preview logo-frame-preview--auth logo-crop-live" id="logoCropLiveAuth"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <p class="text-muted small mb-0 mt-3">Drag to reposition, use the handles to resize the crop area, or zoom with the mouse wheel.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="logoCropApplyBtn">
                    <i class="bi bi-check-lg"></i> Apply logo
                </button>
            </div>
        </div>
    </div>
</div>
<?php
$pageScripts = '<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
<script>
(function() {
    var fileInput = document.getElementById("logoFileInput");
    var editBtn = document.getElementById("logoEditCurrentBtn");
    var modalEl = document.getElementById("logoCropModal");
    var cropImg = document.getElementById("logoCropImage");
    var applyBtn = document.getElementById("logoCropApplyBtn");
    var form = document.querySelector("form[enctype]");
    var previewSidebar = document.getElementById("logoPreviewSidebar");
    var previewAuth = document.getElementById("logoPreviewAuth");
    var liveSidebar = document.getElementById("logoCropLiveSidebar");
    var liveAuth = document.getElementById("logoCropLiveAuth");
    var currentLogoUrl = ' . json_encode($logoUrl ?: '') . ';

    if (!fileInput || !modalEl || !cropImg || typeof Cropper === "undefined") {
        return;
    }

    var modal = new bootstrap.Modal(modalEl);
    var cropper = null;
    var croppedFile = null;
    var pendingCropFile = false;
    var isSquareCrop = true;

    function destroyCropper() {
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
    }

    function getExportCanvasOptions() {
        if (!cropper) return null;
        if (isSquareCrop) {
            return { width: 512, height: 512, imageSmoothingQuality: "high" };
        }
        var crop = cropper.getData(true);
        var w = Math.max(1, Math.round(crop.width));
        var h = Math.max(1, Math.round(crop.height));
        var maxEdge = 512;
        if (Math.max(w, h) > maxEdge) {
            var scale = maxEdge / Math.max(w, h);
            w = Math.round(w * scale);
            h = Math.round(h * scale);
        }
        return { width: w, height: h, imageSmoothingQuality: "high" };
    }

    function getPreviewCanvasOptions() {
        var exportOpts = getExportCanvasOptions();
        if (!exportOpts) return null;
        var w = exportOpts.width;
        var h = exportOpts.height;
        var max = 128;
        if (Math.max(w, h) > max) {
            var scale = max / Math.max(w, h);
            w = Math.round(w * scale);
            h = Math.round(h * scale);
        }
        return { width: w, height: h, imageSmoothingQuality: "high" };
    }

    function maximizeSquareCrop() {
        if (!cropper || !isSquareCrop) return;
        var image = cropper.getImageData();
        var size = Math.min(image.naturalWidth, image.naturalHeight);
        if (!size) return;
        cropper.setData({
            x: Math.max(0, (image.naturalWidth - size) / 2),
            y: Math.max(0, (image.naturalHeight - size) / 2),
            width: size,
            height: size
        });
    }

    function setPreviewImage(container, dataUrl) {
        if (!container) return;
        container.innerHTML = "";
        if (!dataUrl) return;
        var img = document.createElement("img");
        img.src = dataUrl;
        img.alt = "Logo preview";
        container.appendChild(img);
    }

    function updateLivePreviews() {
        if (!cropper) return;
        var opts = getPreviewCanvasOptions();
        if (!opts) return;
        var canvas = cropper.getCroppedCanvas(opts);
        if (!canvas) return;
        var dataUrl = canvas.toDataURL("image/png");
        setPreviewImage(liveSidebar, dataUrl);
        setPreviewImage(liveAuth, dataUrl);
    }

    function initCropper(src) {
        destroyCropper();
        cropImg.src = src;
        cropImg.onload = function() {
            cropper = new Cropper(cropImg, {
                aspectRatio: isSquareCrop ? 1 : NaN,
                viewMode: 1,
                dragMode: isSquareCrop ? "move" : "crop",
                autoCropArea: 1,
                responsive: true,
                background: false,
                movable: true,
                zoomable: true,
                scalable: false,
                rotatable: false,
                checkOrientation: true,
                minCropBoxWidth: 24,
                minCropBoxHeight: 24,
                crop: updateLivePreviews,
                ready: function() {
                    if (isSquareCrop) {
                        maximizeSquareCrop();
                    }
                    updateLivePreviews();
                }
            });
        };
    }

    function openCropperFromFile(file) {
        if (!file) return;
        if (file.type === "image/svg+xml" || (file.name && file.name.toLowerCase().endsWith(".svg"))) {
            alert("SVG files are uploaded as-is. For crop and frame preview, use PNG or JPG.");
            return;
        }
        var reader = new FileReader();
        reader.onload = function(e) {
            modal.show();
            initCropper(e.target.result);
        };
        reader.readAsDataURL(file);
    }

    function openCropperFromUrl(url) {
        if (!url) return;
        modal.show();
        initCropper(url);
    }

    fileInput.addEventListener("change", function() {
        croppedFile = null;
        pendingCropFile = !!(fileInput.files && fileInput.files[0]);
        if (pendingCropFile) {
            openCropperFromFile(fileInput.files[0]);
        }
    });

    if (editBtn) {
        editBtn.addEventListener("click", function() {
            fileInput.value = "";
            openCropperFromUrl(currentLogoUrl);
        });
    }

    document.querySelectorAll("[data-logo-aspect]").forEach(function(btn) {
        btn.addEventListener("click", function() {
            document.querySelectorAll("[data-logo-aspect]").forEach(function(b) { b.classList.remove("active"); });
            btn.classList.add("active");
            var val = parseFloat(btn.getAttribute("data-logo-aspect"));
            isSquareCrop = val > 0;
            if (cropper) {
                cropper.setAspectRatio(isSquareCrop ? 1 : NaN);
                cropper.setDragMode(isSquareCrop ? "move" : "crop");
                if (isSquareCrop) {
                    maximizeSquareCrop();
                }
                updateLivePreviews();
            }
        });
    });

    applyBtn.addEventListener("click", function() {
        if (!cropper) return;
        var canvasOpts = getExportCanvasOptions();
        if (!canvasOpts) return;
        var canvas = cropper.getCroppedCanvas(canvasOpts);
        if (!canvas) return;

        canvas.toBlob(function(blob) {
            if (!blob) return;
            croppedFile = new File([blob], "logo.png", { type: "image/png" });
            pendingCropFile = false;
            var dataUrl = canvas.toDataURL("image/png");
            setPreviewImage(previewSidebar, dataUrl);
            setPreviewImage(previewAuth, dataUrl);
            var dt = new DataTransfer();
            dt.items.add(croppedFile);
            fileInput.files = dt.files;
            modal.hide();
        }, "image/png", 0.92);
    });

    modalEl.addEventListener("hidden.bs.modal", function() {
        destroyCropper();
        cropImg.removeAttribute("src");
        if (pendingCropFile && !croppedFile) {
            fileInput.value = "";
        }
        pendingCropFile = false;
    });

    if (form) {
        form.addEventListener("submit", function() {
            if (croppedFile) {
                var dt = new DataTransfer();
                dt.items.add(croppedFile);
                fileInput.files = dt.files;
            }
        });
    }
})();
</script>';
endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
