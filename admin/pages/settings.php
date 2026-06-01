<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAdmin();

$pageTitle = 'Settings';
$settings  = getCompanySettings();
$tab       = $_GET['tab'] ?? 'branding';
$logoUrl   = SettingsService::logoUrl($settings);
$profile   = ProfileService::getById((int) Auth::id()) ?? Auth::user() ?? [];

require __DIR__ . '/../includes/header.php';
?>

<div class="saas-card">
    <div class="saas-card-header appointment-calendar-header">
        <div>
            <h2 class="saas-card-title">Company Settings</h2>
            <p class="saas-card-subtitle mb-0">Company configuration, email, calendar, backup, and your profile</p>
        </div>
    </div>
    <div class="card-body p-0">
        <ul class="nav nav-tabs settings-tabs px-3 pt-3" role="tablist">
            <li class="nav-item">
                <a class="nav-link <?= $tab === 'branding' ? 'active' : '' ?>" href="<?= url('pages/settings.php?tab=branding') ?>">Branding</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $tab === 'email' ? 'active' : '' ?>" href="<?= url('pages/settings.php?tab=email') ?>">Email / SMTP</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $tab === 'payments' ? 'active' : '' ?>" href="<?= url('pages/settings.php?tab=payments') ?>">Payments</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $tab === 'calendar' ? 'active' : '' ?>" href="<?= url('pages/settings.php?tab=calendar') ?>">Calendar</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $tab === 'backup' ? 'active' : '' ?>" href="<?= url('pages/settings.php?tab=backup') ?>">Backup</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $tab === 'profile' ? 'active' : '' ?>" href="<?= url('pages/settings.php?tab=profile') ?>">Profile</a>
            </li>
        </ul>

        <?php if ($tab === 'calendar'): ?>
            <div class="p-4">
                <div class="alert alert-info border-0 small">
                    Connect Google Calendar to auto-sync appointments with email reminders. Without OAuth, clients still get one-click “Add to Google Calendar” links and downloadable .ics files.
                </div>
                <form method="post" action="<?= url('actions/settings-action.php') ?>" class="mb-4">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="tab" value="calendar">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Google Client ID</label>
                            <input type="text" name="google_client_id" class="form-control" value="<?= e($settings['google_client_id'] ?? '') ?>" placeholder="xxxx.apps.googleusercontent.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Google Client Secret</label>
                            <div class="password-field-wrap">
                                <input type="password" name="google_client_secret" id="google_client_secret" class="form-control" placeholder="<?= !empty($settings['google_client_secret']) ? '••••••••' : '' ?>">
                                <button type="button" class="password-toggle js-password-toggle" data-target="google_client_secret" tabindex="-1" aria-label="Show password">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Google Calendar ID</label>
                            <input type="text" name="google_calendar_id" class="form-control" value="<?= e($settings['google_calendar_id'] ?? '') ?>" placeholder="primary">
                            <div class="form-text">Leave blank to use your primary calendar.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email reminder (hours before)</label>
                            <input type="number" name="appointment_reminder_hours" class="form-control" min="1" max="168" value="<?= (int) ($settings['appointment_reminder_hours'] ?? 24) ?>">
                            <div class="form-text">Clients receive a reminder email this many hours before the appointment. Run <code>php admin/cron/send-appointment-reminders.php</code> on a schedule.</div>
                        </div>
                    </div>
                    <div class="mt-4 d-flex flex-wrap gap-2 align-items-center">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save Calendar Settings</button>
                        <?php if (GoogleOAuthService::isConfigured()): ?>
                            <?php if (GoogleOAuthService::isConnected()): ?>
                                <span class="badge bg-success-subtle text-success-emphasis px-3 py-2">Google Calendar connected</span>
                                <a href="<?= url('actions/google-oauth.php') ?>" class="btn btn-soft btn-sm">Reconnect</a>
                            <?php else: ?>
                                <a href="<?= url('actions/google-oauth.php') ?>" class="btn btn-soft btn-sm"><i class="bi bi-google me-1"></i> Connect Google Calendar</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </form>
                <?php if (GoogleOAuthService::isConnected()): ?>
                    <form method="post" action="<?= url('actions/google-oauth-disconnect.php') ?>" class="m-0">
                        <?= CSRF::field() ?>
                        <button type="submit" class="btn btn-soft-danger btn-sm">Disconnect Google Calendar</button>
                    </form>
                <?php endif; ?>
                <div class="mt-4 small text-muted">
                    <strong>OAuth redirect URI:</strong> <?= e(GoogleOAuthService::redirectUri()) ?>
                </div>
            </div>
        <?php elseif ($tab === 'backup'): ?>
            <div class="p-4">
                <div class="alert alert-info border-0 small">
                    Download a JSON backup of all company settings (including SMTP, Stripe, and Google credentials). Restore replaces current settings with the backup file.
                </div>
                <div class="d-flex flex-wrap gap-2 mb-4">
                    <a href="<?= url('actions/settings-backup.php') ?>" class="btn btn-primary btn-sm">
                        <i class="bi bi-download me-1"></i> Download Settings Backup
                    </a>
                </div>
                <form method="post" action="<?= url('actions/settings-restore.php') ?>" enctype="multipart/form-data" class="settings-restore-form">
                    <?= CSRF::field() ?>
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Restore from backup file</label>
                            <input type="file" name="backup_file" class="form-control" accept=".json,application/json" required>
                            <div class="form-text">Upload a <code>.json</code> file previously exported from this page.</div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-soft-danger btn-sm" onclick="return confirm('Restore settings from this backup? Current settings will be overwritten.');">
                            <i class="bi bi-upload me-1"></i> Restore Settings
                        </button>
                    </div>
                </form>
            </div>
        <?php elseif ($tab === 'profile'): ?>
            <div class="p-4">
                <form method="post" action="<?= url('actions/profile-action.php') ?>" class="mb-4">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="update_profile">
                    <h3 class="h6 mb-3">Account details</h3>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">First name</label>
                            <input type="text" name="first_name" class="form-control" required value="<?= e($profile['first_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last name</label>
                            <input type="text" name="last_name" class="form-control" required value="<?= e($profile['last_name'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required value="<?= e($profile['email'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?= e($profile['phone'] ?? '') ?>">
                        </div>
                    </div>
                    <?php if (!empty($profile['last_login'])): ?>
                        <p class="text-muted small mt-3 mb-0">Last sign-in: <?= e(formatDateTime($profile['last_login'])) ?></p>
                    <?php endif; ?>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save Profile</button>
                    </div>
                </form>
                <hr>
                <form method="post" action="<?= url('actions/profile-action.php') ?>">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="change_password">
                    <h3 class="h6 mb-3">Change password</h3>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Current password</label>
                            <div class="password-field-wrap">
                                <input type="password" name="current_password" id="current_password" class="form-control" required autocomplete="current-password">
                                <button type="button" class="password-toggle js-password-toggle" data-target="current_password" tabindex="-1" aria-label="Show password">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">New password</label>
                            <div class="password-field-wrap">
                                <input type="password" name="new_password" id="new_password" class="form-control" required autocomplete="new-password">
                                <button type="button" class="password-toggle js-password-toggle" data-target="new_password" tabindex="-1" aria-label="Show password">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Confirm new password</label>
                            <div class="password-field-wrap">
                                <input type="password" name="new_password_confirmation" id="new_password_confirmation" class="form-control" required autocomplete="new-password">
                                <button type="button" class="password-toggle js-password-toggle" data-target="new_password_confirmation" tabindex="-1" aria-label="Show password">
                                    <i class="bi bi-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="form-text">At least 8 characters with uppercase, lowercase, and a number.</div>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-soft btn-sm">Update Password</button>
                    </div>
                </form>
            </div>
        <?php else: ?>
        <form method="post" action="<?= url('actions/settings-action.php') ?>" enctype="multipart/form-data" class="p-4">
            <?= CSRF::field() ?>
            <input type="hidden" name="tab" value="<?= e($tab) ?>">

            <?php if ($tab === 'branding'): ?>
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Company Name</label>
                        <input type="text" name="company_name" class="form-control" required value="<?= e($settings['company_name']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Font Family</label>
                        <input type="text" name="font_family" class="form-control" value="<?= e($settings['font_family'] ?? 'Montserrat') ?>">
                    </div>
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
                    <div class="col-md-6">
                        <label class="form-label">Office Email</label>
                        <input type="email" name="office_email" class="form-control" value="<?= e($settings['office_email'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Office Phone</label>
                        <input type="text" name="office_phone" class="form-control" value="<?= e($settings['office_phone'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="2"><?= e($settings['address'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"><?= e($settings['description'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Logo</label>
                        <input type="file" name="logo" class="form-control" accept=".jpg,.jpeg,.png,.webp,.svg">
                        <?php if ($logoUrl): ?>
                            <div class="mt-2"><img src="<?= e($logoUrl) ?>" alt="Logo" style="max-height:48px;border-radius:8px;"></div>
                        <?php endif; ?>
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
                    <div class="col-md-6">
                        <label class="form-label">SMTP Username</label>
                        <input type="text" name="smtp_username" class="form-control" value="<?= e($settings['smtp_username'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">SMTP Password</label>
                        <div class="password-field-wrap">
                            <input type="password" name="smtp_password" id="smtp_password" class="form-control" placeholder="<?= !empty($settings['smtp_password']) ? '••••••••' : '' ?>">
                            <button type="button" class="password-toggle js-password-toggle" data-target="smtp_password" tabindex="-1" aria-label="Show password">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Encryption</label>
                        <select name="smtp_encryption" class="form-select">
                            <?php foreach (['tls', 'ssl', 'none'] as $enc): ?>
                                <option value="<?= $enc ?>" <?= ($settings['smtp_encryption'] ?? 'tls') === $enc ? 'selected' : '' ?>><?= strtoupper($enc) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Send test email to</label>
                        <input type="email" name="test_email" class="form-control" value="<?= e(Auth::user()['email'] ?? $settings['office_email'] ?? '') ?>" placeholder="you@example.com">
                        <div class="form-text">Uses the SMTP values above (saved password if left blank). Save settings first if you changed the password field.</div>
                    </div>
                </div>
            <?php else: ?>
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
                        <div class="password-field-wrap">
                            <input type="password" name="stripe_secret_key" id="stripe_secret_key" class="form-control" placeholder="<?= !empty($settings['stripe_secret_key']) ? '••••••••' : '' ?>">
                            <button type="button" class="password-toggle js-password-toggle" data-target="stripe_secret_key" tabindex="-1" aria-label="Show password">
                                <i class="bi bi-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="mt-4 d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save Settings</button>
                <?php if ($tab === 'email'): ?>
                    <button type="submit" class="btn btn-soft btn-sm" formaction="<?= url('actions/settings-test-smtp.php') ?>" formmethod="post">
                        <i class="bi bi-envelope-check me-1"></i> Test SMTP
                    </button>
                <?php endif; ?>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
