<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAdmin();

$pageTitle = 'Settings';
$settings  = getCompanySettings();
$tab       = $_GET['tab'] ?? 'branding';
$logoUrl   = SettingsService::logoUrl($settings);

require __DIR__ . '/../includes/header.php';
?>

<div class="saas-card">
    <div class="saas-card-header appointment-calendar-header">
        <div>
            <h2 class="saas-card-title">Company Settings</h2>
            <p class="saas-card-subtitle mb-0">Branding, email delivery, and payment configuration</p>
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
        </ul>

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
                        <input type="text" name="office_phone" class="form-control" value="<?= e($settings['office_phone'] ?? '') ?>" placeholder="+1 (555) 123-4567">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Business Hours</label>
                        <textarea name="business_hours" class="form-control" rows="3" placeholder="Monday – Friday: 9:00 AM – 5:00 PM"><?= e($settings['business_hours'] ?? '') ?></textarea>
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
                        <input type="password" name="smtp_password" class="form-control" placeholder="<?= !empty($settings['smtp_password']) ? '••••••••' : '' ?>">
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
                        <input type="password" name="stripe_secret_key" class="form-control" placeholder="<?= !empty($settings['stripe_secret_key']) ? '••••••••' : '' ?>">
                    </div>
                </div>
            <?php endif; ?>

            <div class="mt-4">
                <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i> Save Settings</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
