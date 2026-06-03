<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireClient();

$pageTitle = 'My Profile';
$profile   = ProfileService::getById((int) Auth::id()) ?? Auth::user() ?? [];
$pageSubtitle = $profile['email'] ?? '';

require __DIR__ . '/../includes/header.php';
?>

<div class="saas-card">
    <div class="saas-card-header appointment-list-header">
        <div>
            <h2 class="saas-card-title">Profile Settings</h2>
            <p class="saas-card-subtitle mb-0">Update your account details and password</p>
        </div>
    </div>
    <div class="card-body p-4">
        <form method="post" action="<?= clientUrl('actions/profile-action.php') ?>" class="mb-4">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="update_profile">
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
            <div class="mt-4">
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-lg me-1"></i> Save Profile</button>
            </div>
        </form>

        <hr>

        <form method="post" action="<?= clientUrl('actions/profile-action.php') ?>">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="change_password">
            <h3 class="h6 mb-3">Change password</h3>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Current password</label>
                    <div class="login-pw-field">
                        <div class="login-pw-input-wrap">
                            <input type="text" name="current_password" id="current_password"
                                   class="form-control login-pw-input login-pw-masked" required autocomplete="current-password" spellcheck="false">
                            <button type="button" class="login-pw-reveal" aria-label="Show password" aria-pressed="false" title="Show password">
                                <i class="bi bi-eye login-pw-icon-show" aria-hidden="true"></i>
                                <i class="bi bi-eye-slash login-pw-icon-hide" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">New password</label>
                    <div class="login-pw-field">
                        <div class="login-pw-input-wrap">
                            <input type="text" name="new_password" id="new_password"
                                   class="form-control login-pw-input login-pw-masked" required autocomplete="new-password" spellcheck="false">
                            <button type="button" class="login-pw-reveal" aria-label="Show password" aria-pressed="false" title="Show password">
                                <i class="bi bi-eye login-pw-icon-show" aria-hidden="true"></i>
                                <i class="bi bi-eye-slash login-pw-icon-hide" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Confirm new password</label>
                    <div class="login-pw-field">
                        <div class="login-pw-input-wrap">
                            <input type="text" name="new_password_confirmation" id="new_password_confirmation"
                                   class="form-control login-pw-input login-pw-masked" required autocomplete="new-password" spellcheck="false">
                            <button type="button" class="login-pw-reveal" aria-label="Show password" aria-pressed="false" title="Show password">
                                <i class="bi bi-eye login-pw-icon-show" aria-hidden="true"></i>
                                <i class="bi bi-eye-slash login-pw-icon-hide" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-text">At least 8 characters with uppercase, lowercase, and a number.</div>
            <div class="mt-4">
                <button type="submit" class="btn btn-soft btn-sm">Update Password</button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
