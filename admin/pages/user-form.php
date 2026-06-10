<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requirePage('users');

$id = (int) ($_GET['id'] ?? 0);
$isEdit = $id > 0;
$assignableRoles = RoleAccess::assignableRoles(Auth::role(), TenantService::id());
$canManageUsers = Auth::canManage(RoleAccess::PERMISSION_USERS);
$canEditUsers = $canManageUsers && $assignableRoles !== [];

if (!$isEdit && !$canEditUsers) {
    flash('error', 'You cannot add users.');
    redirect('pages/users.php');
}

if ($isEdit && !Auth::can(RoleAccess::PERMISSION_USERS)) {
    flash('error', 'You do not have permission to view users.');
    redirect('pages/users.php');
}

if ($isEdit) {
    $user = UserService::getStaffById($id);
    if (!$user) {
        flash('error', 'User not found.');
        redirect('pages/users.php');
    }
    $pageTitle = 'Edit User';
    $pageSubtitle = (string) ($user['display_name'] ?? $user['email'] ?? '');
} else {
    $user = null;
    $pageTitle = 'Add User';
    $pageSubtitle = 'Create a new team member';
}

require __DIR__ . '/../includes/header.php';

$firstName = $isEdit ? ($user['first_name'] ?? '') : '';
$lastName  = $isEdit ? ($user['last_name'] ?? '') : '';
if ($isEdit && $firstName === '' && $lastName === '' && !empty($user['display_name'])) {
    $parts = preg_split('/\s+/', trim((string) $user['display_name']), 2) ?: [];
    $firstName = $parts[0] ?? '';
    $lastName  = $parts[1] ?? '';
}
?>

<div class="case-form-page">
    <div class="case-form-header">
        <a href="<?= url('pages/users.php') ?>" class="btn btn-primary btn-sm case-back-btn">
            <i class="bi bi-arrow-left"></i> Back to Users
        </a>
        <div class="case-form-header-main">
            <div>
                <h1 class="case-form-title"><?= $isEdit ? 'Edit User' : 'Add User' ?></h1>
                <p class="case-form-subtitle">Assign a role to control which pages this person can open.</p>
            </div>
        </div>
    </div>

    <form method="post" action="<?= url('actions/user-action.php') ?>" class="case-form js-password-strength-form">
        <?= CSRF::field() ?>
        <input type="hidden" name="action" value="<?= $isEdit ? 'update_user' : 'create_user' ?>">
        <?php if ($isEdit && !$canEditUsers): ?>
            <div class="alert alert-info">You can view this account but cannot change it.</div>
        <?php endif; ?>
        <?php if ($isEdit): ?>
            <input type="hidden" name="user_id" value="<?= $id ?>">
        <?php endif; ?>

        <div class="case-form-card">
            <div class="case-form-section">
                <div class="case-form-section-head">
                    <h2 class="case-form-section-title">Account</h2>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">First name</label>
                        <input type="text" name="first_name" class="form-control" required value="<?= e($firstName) ?>" <?= !$canEditUsers ? 'disabled' : '' ?>>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Last name</label>
                        <input type="text" name="last_name" class="form-control" required value="<?= e($lastName) ?>" <?= !$canEditUsers ? 'disabled' : '' ?>>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required value="<?= e($isEdit ? (string) $user['email'] : '') ?>" <?= !$canEditUsers ? 'disabled' : '' ?>>
                    </div>
                    <?php if (Database::columnExists('users', 'phone')): ?>
                    <div class="col-md-6">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?= e($isEdit ? (string) ($user['phone'] ?? '') : '') ?>" <?= !$canEditUsers ? 'disabled' : '' ?>>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-6">
                        <label class="form-label">Role</label>
                        <?php if ($isEdit && !$canEditUsers): ?>
                            <input
                                type="text"
                                class="form-control"
                                disabled
                                value="<?= e(RoleAccess::roleLabel((string) ($user['role'] ?? ''), TenantService::id())) ?>"
                            >
                        <?php else: ?>
                            <select name="role" class="form-select" <?= $isEdit && (int) $id === (int) Auth::id() ? 'disabled' : '' ?> required>
                                <?php foreach ($assignableRoles as $roleOption): ?>
                                    <option value="<?= e($roleOption) ?>" <?= ($isEdit && ($user['role'] ?? '') === $roleOption) || (!$isEdit && $roleOption === 'staff') ? 'selected' : '' ?>>
                                        <?= e(RoleAccess::roleLabel($roleOption, TenantService::id())) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($isEdit && (int) $id === (int) Auth::id()): ?>
                                <input type="hidden" name="role" value="<?= e((string) $user['role']) ?>">
                                <p class="form-text mb-0">You cannot change your own role.</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select" <?= !$canEditUsers || ($isEdit && (int) $id === (int) Auth::id()) ? 'disabled' : '' ?>>
                            <?php foreach (['active', 'inactive', 'suspended'] as $statusOption): ?>
                                <option value="<?= e($statusOption) ?>" <?= ($isEdit && ($user['status'] ?? 'active') === $statusOption) || (!$isEdit && $statusOption === 'active') ? 'selected' : '' ?>>
                                    <?= e(ucfirst($statusOption)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($isEdit && (int) $id === (int) Auth::id()): ?>
                            <input type="hidden" name="status" value="active">
                        <?php endif; ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label"><?= $isEdit ? 'New password (leave blank to keep)' : 'Password' ?></label>
                        <?php renderPasswordRevealField('password', 'password', [
                            'required' => !$isEdit,
                            'disabled' => !$canEditUsers,
                            'autocomplete' => 'new-password',
                            'strength' => true,
                            'strength_optional' => $isEdit,
                        ]); ?>
                        <?php renderPasswordStrengthHint('form-text mt-1 mb-0', $isEdit); ?>
                    </div>
                    <?php if (!$isEdit): ?>
                        <div class="col-12">
                            <p class="small text-muted mb-0"><?= e(RoleAccess::roleDescription('staff')) ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="case-form-actions">
            <?php if ($canEditUsers): ?>
                <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save changes' : 'Create user' ?></button>
            <?php endif; ?>
            <a href="<?= url('pages/users.php') ?>" class="btn btn-outline-secondary"><?= $canEditUsers ? 'Cancel' : 'Back to users' ?></a>
        </div>
    </form>
</div>

<link href="<?= asset('css/case-workspace.css') ?>" rel="stylesheet">
<?php require __DIR__ . '/../includes/footer.php'; ?>
