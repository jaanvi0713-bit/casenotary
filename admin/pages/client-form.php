<?php
require_once __DIR__ . '/../core/bootstrap.php';

$id = (int) ($_GET['id'] ?? 0);
$isEdit = $id > 0;
Auth::requireAdmin();

if ($isEdit) {
    $client = ClientService::getById($id);
    if (!$client) {
        flash('error', 'Client not found.');
        redirect('pages/clients.php');
    }
    $pageTitle = 'Edit Client';
} else {
    $client = null;
    $pageTitle = 'Add Client';
}

$pageSubtitle = $isEdit ? clientFullName($client) : 'Create a new client profile';

require __DIR__ . '/../includes/header.php';
?>

<link href="<?= asset('css/case-workspace.css') ?>" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/intl-tel-input@23.0.12/build/css/intlTelInput.css" rel="stylesheet">

<div class="case-form-page">
    <div class="case-form-header">
        <a href="<?= url('pages/clients.php') ?>" class="btn btn-primary btn-sm case-back-btn">
            <i class="bi bi-arrow-left"></i> Back to Clients
        </a>
        <div class="case-form-header-main">
            <div>
                <h1 class="case-form-title"><?= $isEdit ? 'Edit Client' : 'Add New Client' ?></h1>
                <p class="case-form-subtitle">Enter client details and optionally create a portal login.</p>
            </div>
        </div>
    </div>

    <form method="post" action="<?= url('actions/client-action.php') ?>" class="case-form">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="<?= $isEdit ? 'update_client' : 'create_client' ?>">
        <?php if ($isEdit): ?>
            <input type="hidden" name="client_id" value="<?= $id ?>">
        <?php endif; ?>

        <div class="case-form-card">
            <div class="case-form-section">
                <div class="case-form-section-head">
                    <i class="bi bi-person"></i>
                    <div>
                        <h2 class="case-form-section-title">Contact Information</h2>
                        <p class="case-form-section-desc">Primary client details.</p>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="case-form-label" for="first_name">First Name <span class="text-danger">*</span></label>
                        <input type="text" id="first_name" name="first_name" class="form-control case-form-control" required
                               value="<?= e($client['first_name'] ?? old('first_name')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="case-form-label" for="last_name">Last Name <span class="text-danger">*</span></label>
                        <input type="text" id="last_name" name="last_name" class="form-control case-form-control" required
                               value="<?= e($client['last_name'] ?? old('last_name')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="case-form-label" for="email">Email <span class="text-danger">*</span></label>
                        <input type="email" id="email" name="email" class="form-control case-form-control" required
                               value="<?= e($client['email'] ?? old('email')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="case-form-label" for="phone">Phone</label>
                        <input type="tel" id="phone" name="phone" class="form-control case-form-control"
                               value="<?= e($client['phone'] ?? old('phone')) ?>" autocomplete="tel">
                    </div>
                    <div class="col-md-6">
                        <label class="case-form-label" for="company_name">Company</label>
                        <input type="text" id="company_name" name="company_name" class="form-control case-form-control"
                               value="<?= e($client['company_name'] ?? old('company_name')) ?>">
                    </div>
                    <?php if ($isEdit): ?>
                    <div class="col-md-6">
                        <label class="case-form-label" for="status">Status</label>
                        <select id="status" name="status" class="form-select case-form-control">
                            <?php foreach (['active','inactive','suspended'] as $st): ?>
                                <option value="<?= $st ?>" <?= ($client['status'] ?? $client['user_status'] ?? 'active') === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="case-form-section">
                <div class="case-form-section-head">
                    <i class="bi bi-geo-alt"></i>
                    <div>
                        <h2 class="case-form-section-title">Address</h2>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="case-form-label" for="address">Street Address</label>
                        <input type="text" id="address" name="address" class="form-control case-form-control"
                               value="<?= e($client['address'] ?? old('address')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="case-form-label" for="city">City</label>
                        <input type="text" id="city" name="city" class="form-control case-form-control"
                               value="<?= e($client['city'] ?? old('city')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="case-form-label" for="state">State</label>
                        <input type="text" id="state" name="state" class="form-control case-form-control"
                               value="<?= e($client['state'] ?? old('state')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="case-form-label" for="zip_code">ZIP Code</label>
                        <input type="text" id="zip_code" name="zip_code" class="form-control case-form-control"
                               value="<?= e($client['zip_code'] ?? old('zip_code')) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="case-form-label" for="country">Country</label>
                        <input type="text" id="country" name="country" class="form-control case-form-control"
                               value="<?= e($client['country'] ?? old('country', 'USA')) ?>">
                    </div>
                    <div class="col-12">
                        <label class="case-form-label" for="notes">Internal Notes</label>
                        <textarea id="notes" name="notes" class="form-control case-form-control" rows="2"><?= e($client['notes'] ?? old('notes')) ?></textarea>
                    </div>
                </div>
            </div>

            <?php if (!$isEdit): ?>
            <div class="case-form-section case-form-section-last">
                <div class="case-form-section-head">
                    <i class="bi bi-key"></i>
                    <div>
                        <h2 class="case-form-section-title">Portal Login</h2>
                        <p class="case-form-section-desc">Optionally create client portal access now.</p>
                    </div>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="create_login" value="1" id="create_login" checked>
                    <label class="form-check-label" for="create_login">Create client portal login</label>
                </div>
                <div id="portalPasswordFields" class="row g-3">
                    <div class="col-md-6">
                        <label class="case-form-label" for="password">Portal Password <span class="text-danger">*</span></label>
                        <input type="password" id="password" name="password" class="form-control case-form-control" minlength="6" autocomplete="new-password">
                    </div>
                    <div class="col-md-6">
                        <label class="case-form-label" for="password_confirmation">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" id="password_confirmation" name="password_confirmation" class="form-control case-form-control" minlength="6" autocomplete="new-password">
                    </div>
                </div>
            </div>
            <?php elseif (!empty($client['user_id'])): ?>
            <div class="case-form-section case-form-section-last">
                <p class="text-muted small mb-0"><i class="bi bi-check-circle text-success"></i> This client has portal login access.</p>
            </div>
            <?php else: ?>
            <div class="case-form-section case-form-section-last">
                <div class="case-form-section-head">
                    <i class="bi bi-key"></i>
                    <div>
                        <h2 class="case-form-section-title">Portal Login</h2>
                        <p class="case-form-section-desc">Create portal access for this client.</p>
                    </div>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="create_login" value="1" id="create_login">
                    <label class="form-check-label" for="create_login">Create client portal login</label>
                </div>
                <div id="portalPasswordFields" class="row g-3" style="display:none;">
                    <div class="col-md-6">
                        <label class="case-form-label" for="password">Portal Password <span class="text-danger">*</span></label>
                        <input type="password" id="password" name="password" class="form-control case-form-control" minlength="6" autocomplete="new-password">
                    </div>
                    <div class="col-md-6">
                        <label class="case-form-label" for="password_confirmation">Confirm Password <span class="text-danger">*</span></label>
                        <input type="password" id="password_confirmation" name="password_confirmation" class="form-control case-form-control" minlength="6" autocomplete="new-password">
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="case-form-footer">
            <p class="case-form-required-note"><span class="text-danger">*</span> Required fields</p>
            <div class="case-form-actions">
                <a href="<?= url('pages/clients.php') ?>" class="btn btn-soft">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> <?= $isEdit ? 'Save Client' : 'Add Client' ?>
                </button>
            </div>
        </div>
    </form>
</div>

<?php
$pageScripts = '<script src="https://cdn.jsdelivr.net/npm/intl-tel-input@23.0.12/build/js/intlTelInput.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    var form = document.querySelector(".case-form");
    var phoneInput = document.getElementById("phone");
    var iti = null;

    if (phoneInput && window.intlTelInput) {
        iti = window.intlTelInput(phoneInput, {
            separateDialCode: true,
            initialCountry: "us",
            preferredCountries: ["us", "mu", "gb", "ca", "au", "in"],
            utilsScript: "https://cdn.jsdelivr.net/npm/intl-tel-input@23.0.12/build/js/utils.js"
        });

        if (form) {
            form.addEventListener("submit", function() {
                var fullNumber = iti.getNumber();
                phoneInput.value = fullNumber || phoneInput.value.trim();
            });
        }
    }

    var checkbox = document.getElementById("create_login");
    var fields = document.getElementById("portalPasswordFields");
    var password = document.getElementById("password");
    var confirm = document.getElementById("password_confirmation");
    if (!checkbox || !fields) return;

    function syncPasswordFields() {
        var enabled = checkbox.checked;
        fields.style.display = enabled ? "" : "none";
        if (password) password.required = enabled;
        if (confirm) confirm.required = enabled;
        if (!enabled) {
            if (password) password.value = "";
            if (confirm) confirm.value = "";
        }
    }

    checkbox.addEventListener("change", syncPasswordFields);
    syncPasswordFields();
});
</script>';
require __DIR__ . '/../includes/footer.php';
?>
