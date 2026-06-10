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
$canManageSettings = Auth::can(RoleAccess::PERMISSION_SETTINGS);
$settingsNavTab = $tab;
$editableRoleKeys = $canManageSettings
    ? CompanyRoleAccessService::editableRolesForActor(Auth::role(), TenantService::id())
    : [];

if (!$canManageSettings) {
    if (!in_array($tab, ['profile', 'notifications'], true)) {
        $tab = 'profile';
    }
    $pageSubtitle = $tab === 'notifications' ? 'Notification Preferences' : 'Your profile';
}

$userId = Auth::id();
$notificationPrefs = NotificationPreferenceService::get($userId);
$preferencesReady = NotificationPreferenceService::columnExists();
$preferencesAction = url('actions/notification-action.php');
$logoUrl    = companyLogoUrl($settings);
$faviconUrl = companyFaviconUrl($settings);

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

<?php if ($canManageSettings): ?>
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
                        <div class="col-md-6">
                            <label class="form-label">Account number</label>
                            <input type="text" name="bank_account_number" class="form-control" value="<?= e($settings['bank_account_number'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Sort code</label>
                            <input type="text" name="bank_sort_code" class="form-control" value="<?= e($settings['bank_sort_code'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">IBAN</label>
                            <input type="text" name="bank_iban" class="form-control" value="<?= e($settings['bank_iban'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">BIC</label>
                            <input type="text" name="bank_bic" class="form-control" value="<?= e($settings['bank_bic'] ?? '') ?>">
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
            <?php elseif ($tab === 'ai'): ?>
                <?php ChatbotCompanyKnowledge::ensureSchema(); ?>
                <div class="settings-form-section">
                    <div class="settings-form-section__header">
                        <h3 class="settings-form-section__title">Company knowledge for AI</h3>
                        <p class="settings-form-section__desc mb-0">
                            FAQs, fee schedule, office policies, and anything staff and client assistants should know.
                            One topic per line works well (e.g. <code>Apostille fee: £85 per document</code>).
                        </p>
                    </div>
                    <div class="settings-form-section__body mt-3">
                        <label class="form-label" for="ai_knowledge">Knowledge base</label>
                        <textarea
                            id="ai_knowledge"
                            name="ai_knowledge"
                            class="form-control font-monospace"
                            rows="16"
                            placeholder="Office hours: Mon–Fri 9am–5pm&#10;Standard notarization: from £120&#10;We offer remote online notarization by appointment&#10;Parking: free street parking on High Street"
                        ><?= e(ChatbotCompanyKnowledge::get()) ?></textarea>
                        <p class="form-text mb-0">Used when users ask about fees, hours, policies, or your process — in admin and client assistants.</p>
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
    var aspectRatio = 1;

    function destroyCropper() {
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
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
        var square = cropper.getCroppedCanvas({ width: 128, height: 128, imageSmoothingQuality: "high" });
        var auth = cropper.getCroppedCanvas({ width: 128, height: 128, imageSmoothingQuality: "high" });
        if (square) setPreviewImage(liveSidebar, square.toDataURL("image/png"));
        if (auth) setPreviewImage(liveAuth, auth.toDataURL("image/png"));
    }

    function initCropper(src) {
        destroyCropper();
        cropImg.src = src;
        cropImg.onload = function() {
            cropper = new Cropper(cropImg, {
                aspectRatio: aspectRatio || NaN,
                viewMode: 1,
                dragMode: "move",
                autoCropArea: 0.9,
                responsive: true,
                background: false,
                crop: updateLivePreviews
            });
            updateLivePreviews();
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
            aspectRatio = val > 0 ? val : NaN;
            if (cropper) {
                cropper.setAspectRatio(aspectRatio);
                updateLivePreviews();
            }
        });
    });

    applyBtn.addEventListener("click", function() {
        if (!cropper) return;
        var canvas = cropper.getCroppedCanvas({
            width: 512,
            height: 512,
            imageSmoothingQuality: "high"
        });
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
