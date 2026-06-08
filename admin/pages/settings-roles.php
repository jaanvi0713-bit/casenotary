<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requirePage('settings');

if (!Auth::can(RoleAccess::PERMISSION_SETTINGS)) {
    flash('error', 'You do not have permission to manage role access.');
    redirect('pages/settings.php?tab=profile');
}

$companyIdForRoles = TenantService::id();
$editableRoleKeys = CompanyRoleAccessService::editableRolesForActor(Auth::role(), $companyIdForRoles);

if ($editableRoleKeys === []) {
    flash('error', 'Your account cannot customize role access.');
    redirect('pages/settings.php');
}

$pageTitle = 'Settings';
$pageSubtitle = 'Role access for ' . TenantService::name();
$settingsNavTab = 'roles';
$canManageSettings = true;
$rolesTableReady = CompanyRoleAccessService::tableExists() && CompanyRoleService::tableExists();
$companyRoles = CompanyRoleService::listForCompany($companyIdForRoles);
$companyRoleConfigs = [];

foreach ($editableRoleKeys as $roleKey) {
    $companyRoleConfigs[$roleKey] = CompanyRoleAccessService::get($companyIdForRoles, $roleKey);
}

$copyFromOptions = array_values(array_filter(
    $editableRoleKeys,
    static fn(string $slug): bool => in_array($slug, ['staff', 'manager', 'viewer', 'admin'], true)
));

if ($copyFromOptions === []) {
    $copyFromOptions = $editableRoleKeys;
}

$roleHeaderIcons = [
    'admin' => 'bi-shield-check',
    'manager' => 'bi-diagram-3',
    'staff' => 'bi-person-workspace',
    'viewer' => 'bi-eye',
];

$permissionIcons = [
    RoleAccess::PERMISSION_DASHBOARD => 'bi-grid-1x2',
    RoleAccess::PERMISSION_USERS => 'bi-person-badge',
    RoleAccess::PERMISSION_CLIENTS => 'bi-people',
    RoleAccess::PERMISSION_CASES => 'bi-briefcase',
    RoleAccess::PERMISSION_PAYMENTS => 'bi-credit-card',
    RoleAccess::PERMISSION_APPOINTMENTS => 'bi-calendar3',
    RoleAccess::PERMISSION_NOTIFICATIONS => 'bi-bell',
    RoleAccess::PERMISSION_CHATBOT => 'bi-robot',
    RoleAccess::PERMISSION_SETTINGS => 'bi-gear',
    RoleAccess::PERMISSION_PROFILE => 'bi-person-circle',
];

$pageBodyClass = 'page-settings-roles';
$pageScripts = '<script src="' . e(asset('js/settings-roles.js')) . '"></script>';

require __DIR__ . '/../includes/header.php';
?>

<div class="saas-card settings-roles-card">
    <div class="saas-card-header settings-roles-card__header">
        <div>
            <h2 class="saas-card-title">Company Settings</h2>
            <p class="saas-card-subtitle mb-0">Role categories and permissions for this workspace</p>
        </div>
    </div>
    <div class="card-body p-0">
        <?php require __DIR__ . '/../includes/settings-nav.php'; ?>

        <?php if (!$rolesTableReady): ?>
            <div class="p-4">
                <div class="alert alert-warning border-0 mb-0">
                    Role storage is not installed. Run
                    <code>php admin/sql/migrate_company_roles.php</code>
                    from the project root, then reload this page.
                </div>
            </div>
        <?php else: ?>
            <div class="settings-roles-add-panel">
                <div class="settings-roles-add-panel__intro">
                    <div class="settings-roles-add-panel__icon" aria-hidden="true">
                        <i class="bi bi-shield-plus"></i>
                    </div>
                    <div>
                        <h3 class="settings-roles-add-panel__title">Add role category</h3>
                        <p class="settings-roles-add-panel__desc mb-0">
                            Create a custom role for your team. <strong>Copy permissions from</strong> picks a starting template (e.g. Staff) — you can change everything in the grid below after saving.
                        </p>
                    </div>
                </div>
                <form method="post" action="<?= url('actions/role-action.php') ?>" class="settings-roles-add-form">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="create">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Role name</label>
                            <input type="text" name="label" class="form-control" required maxlength="120" placeholder="e.g. Billing, Reception">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Copy permissions from</label>
                            <select name="copy_from" class="form-select">
                                <?php foreach ($copyFromOptions as $copySlug): ?>
                                    <option value="<?= e($copySlug) ?>" <?= $copySlug === 'staff' ? 'selected' : '' ?>>
                                        <?= e(CompanyRoleService::labelForSlug($copySlug, $companyIdForRoles)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Description <span class="text-muted fw-normal">(optional)</span></label>
                            <input type="text" name="description" class="form-control" maxlength="500" placeholder="Short summary for your team">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-plus-lg me-1"></i> Add role
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <form method="post" action="<?= url('actions/settings-action.php') ?>" class="settings-roles-form" id="settingsRolesForm">
                <?= CSRF::field() ?>
                <input type="hidden" name="tab" value="roles">

                <div class="settings-roles-toolbar">
                    <div class="settings-roles-toolbar__copy">
                        <i class="bi bi-building"></i>
                        <span>Permissions for <strong><?= e(TenantService::name()) ?></strong> only. Built-in roles cannot be deleted.</span>
                    </div>
                    <div class="settings-roles-toolbar__hint">
                        <i class="bi bi-hand-index"></i> Click any cell to toggle
                    </div>
                </div>

                <div class="settings-roles-matrix-panel">
                    <div class="settings-roles-table-wrap">
                        <table class="settings-roles-matrix">
                            <thead>
                                <tr>
                                    <th class="settings-roles-matrix__corner">Permission</th>
                                    <?php foreach ($editableRoleKeys as $roleKey): ?>
                                        <?php
                                        $roleMeta = null;
                                        foreach ($companyRoles as $cr) {
                                            if (($cr['slug'] ?? '') === $roleKey) {
                                                $roleMeta = $cr;
                                                break;
                                            }
                                        }
                                        $isBuiltin = $roleMeta === null || !empty($roleMeta['is_builtin']);
                                        ?>
                                        <?php
                                        $roleLabel = CompanyRoleService::labelForSlug($roleKey, $companyIdForRoles);
                                        $roleIcon = $roleHeaderIcons[$roleKey] ?? 'bi-tag';
                                        ?>
                                        <th class="settings-roles-matrix__role-head" data-role="<?= e($roleKey) ?>">
                                            <div class="settings-roles-role-card">
                                                <div class="settings-roles-role-card__accent" aria-hidden="true"></div>
                                                <div class="settings-roles-role-card__body">
                                                    <span class="settings-roles-role-card__avatar" aria-hidden="true">
                                                        <i class="bi <?= e($roleIcon) ?>"></i>
                                                    </span>
                                                    <span class="settings-roles-role-card__name"><?= e($roleLabel) ?></span>
                                                    <?php if ($isBuiltin): ?>
                                                        <span class="settings-roles-role-card__badge">Built-in</span>
                                                    <?php else: ?>
                                                        <span class="settings-roles-role-card__badge settings-roles-role-card__badge--custom">Custom</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <?php if (!$isBuiltin): ?>
                                                <form method="post" action="<?= url('actions/role-action.php') ?>" class="settings-roles-role-remove" onsubmit="return confirm('Remove this role? Users must be reassigned first.');">
                                                    <?= CSRF::field() ?>
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="slug" value="<?= e($roleKey) ?>">
                                                    <button type="submit" class="btn btn-link btn-sm p-0">Remove</button>
                                                </form>
                                            <?php endif; ?>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (CompanyRoleAccessService::CONFIGURABLE_PERMISSIONS as $permissionKey): ?>
                                    <?php $permIcon = $permissionIcons[$permissionKey] ?? 'bi-check2-square'; ?>
                                    <tr class="settings-roles-matrix__perm-row">
                                        <th scope="row" class="settings-roles-matrix__perm-label">
                                            <span class="settings-roles-perm-icon"><i class="bi <?= e($permIcon) ?>"></i></span>
                                            <span><?= e(CompanyRoleAccessService::permissionLabel($permissionKey)) ?></span>
                                        </th>
                                        <?php foreach ($editableRoleKeys as $roleKey): ?>
                                            <?php
                                            $roleConfig = $companyRoleConfigs[$roleKey];
                                            $inputId = 'role-' . $roleKey . '-' . $permissionKey;
                                            $checked = in_array($permissionKey, $roleConfig['permissions'], true);
                                            ?>
                                            <td class="settings-roles-matrix__cell<?= $checked ? ' is-on' : '' ?>">
                                                <label class="settings-roles-toggle" for="<?= e($inputId) ?>">
                                                    <input
                                                        type="checkbox"
                                                        class="settings-roles-matrix__checkbox"
                                                        name="role_permissions[<?= e($roleKey) ?>][]"
                                                        value="<?= e($permissionKey) ?>"
                                                        id="<?= e($inputId) ?>"
                                                        <?= $checked ? 'checked' : '' ?>
                                                    >
                                                    <span class="settings-roles-toggle__ui" aria-hidden="true">
                                                        <i class="bi bi-check-lg"></i>
                                                    </span>
                                                </label>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="settings-roles-matrix__divider">
                                    <td colspan="<?= count($editableRoleKeys) + 1 ?>">Case scope &amp; editing</td>
                                </tr>
                                <tr class="settings-roles-matrix__scope-row">
                                    <th scope="row" class="settings-roles-matrix__perm-label">
                                        <span class="settings-roles-perm-icon settings-roles-perm-icon--scope"><i class="bi bi-funnel"></i></span>
                                        <span>Only assigned cases</span>
                                    </th>
                                    <?php foreach ($editableRoleKeys as $roleKey): ?>
                                        <?php
                                        $roleConfig = $companyRoleConfigs[$roleKey];
                                        $inputId = 'role-' . $roleKey . '-assigned';
                                        $checked = $roleConfig['assigned_cases_only'];
                                        ?>
                                        <td class="settings-roles-matrix__cell settings-roles-matrix__cell--scope<?= $checked ? ' is-on' : '' ?>">
                                            <label class="settings-roles-toggle" for="<?= e($inputId) ?>">
                                                <input
                                                    type="checkbox"
                                                    class="settings-roles-matrix__checkbox"
                                                    name="assigned_cases_only[<?= e($roleKey) ?>]"
                                                    value="1"
                                                    id="<?= e($inputId) ?>"
                                                    <?= $checked ? 'checked' : '' ?>
                                                >
                                                <span class="settings-roles-toggle__ui" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
                                            </label>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                                <tr class="settings-roles-matrix__scope-row">
                                    <th scope="row" class="settings-roles-matrix__perm-label">
                                        <span class="settings-roles-perm-icon settings-roles-perm-icon--scope"><i class="bi bi-eye"></i></span>
                                        <span>Read-only access</span>
                                    </th>
                                    <?php foreach ($editableRoleKeys as $roleKey): ?>
                                        <?php
                                        $roleConfig = $companyRoleConfigs[$roleKey];
                                        $inputId = 'role-' . $roleKey . '-readonly';
                                        $checked = $roleConfig['read_only'];
                                        ?>
                                        <td class="settings-roles-matrix__cell settings-roles-matrix__cell--scope<?= $checked ? ' is-on' : '' ?>">
                                            <label class="settings-roles-toggle" for="<?= e($inputId) ?>">
                                                <input
                                                    type="checkbox"
                                                    class="settings-roles-matrix__checkbox"
                                                    name="read_only[<?= e($roleKey) ?>]"
                                                    value="1"
                                                    id="<?= e($inputId) ?>"
                                                    <?= $checked ? 'checked' : '' ?>
                                                >
                                                <span class="settings-roles-toggle__ui" aria-hidden="true"><i class="bi bi-check-lg"></i></span>
                                            </label>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="settings-roles-legend">
                    <h4 class="settings-roles-legend__title">Role summaries</h4>
                    <div class="row g-3">
                        <?php foreach ($editableRoleKeys as $roleKey): ?>
                            <?php $roleConfig = $companyRoleConfigs[$roleKey]; ?>
                            <div class="col-md-6 col-xl-3">
                                <article class="settings-roles-legend-card" data-role="<?= e($roleKey) ?>">
                                    <header class="settings-roles-legend-card__head">
                                        <span class="settings-roles-legend-card__avatar"><?= e(strtoupper(substr(CompanyRoleService::labelForSlug($roleKey, $companyIdForRoles), 0, 1))) ?></span>
                                        <strong><?= e(CompanyRoleService::labelForSlug($roleKey, $companyIdForRoles)) ?></strong>
                                    </header>
                                    <p class="settings-roles-legend-card__desc"><?= e(CompanyRoleService::descriptionForSlug($roleKey, $companyIdForRoles)) ?></p>
                                    <div class="settings-roles-legend-card__tags">
                                        <?php if ($roleConfig['assigned_cases_only']): ?>
                                            <span class="settings-roles-tag">Assigned cases</span>
                                        <?php endif; ?>
                                        <?php if ($roleConfig['read_only']): ?>
                                            <span class="settings-roles-tag settings-roles-tag--muted">Read-only</span>
                                        <?php else: ?>
                                            <span class="settings-roles-tag settings-roles-tag--ok">Can edit &amp; save</span>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="settings-roles-actions-bar">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i> Save role access
                    </button>
                    <a href="<?= url('pages/users.php') ?>" class="btn btn-soft">Back to users</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
