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
$roleMetaBySlug = [];

foreach ($companyRoles as $companyRole) {
    $roleMetaBySlug[(string) ($companyRole['slug'] ?? '')] = $companyRole;
}

$companyRoleConfigs = [];

foreach ($editableRoleKeys as $roleKey) {
    $companyRoleConfigs[$roleKey] = CompanyRoleAccessService::get($companyIdForRoles, $roleKey);
}

$roleUserCounts = CompanyRoleService::userCountsForCompany($companyIdForRoles);
$roleOrderValue = implode(',', $editableRoleKeys);
$roleColumnTotal = count($editableRoleKeys);

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
                        <span>Permissions for <strong><?= e(TenantService::name()) ?></strong> only. Use <strong>Edit</strong> to rename, <i class="bi bi-copy"></i> to duplicate, and arrows to reorder. Built-in roles cannot be deleted.</span>
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
                                    <?php foreach ($editableRoleKeys as $roleColumnIndex => $roleKey): ?>
                                        <?php
                                        $roleMeta = $roleMetaBySlug[$roleKey] ?? null;
                                        $isBuiltin = $roleMeta === null || !empty($roleMeta['is_builtin']);
                                        $deleteFormId = 'deleteRole-' . $roleKey;
                                        $duplicateFormId = 'duplicateRole-' . $roleKey;
                                        $roleUserCount = (int) ($roleUserCounts[$roleKey] ?? 0);
                                        $canMoveRoleLeft = $roleColumnIndex > 0;
                                        $canMoveRoleRight = $roleColumnIndex < $roleColumnTotal - 1;
                                        ?>
                                        <?php
                                        $roleLabel = CompanyRoleService::labelForSlug($roleKey, $companyIdForRoles);
                                        $roleIcon = $roleHeaderIcons[$roleKey] ?? 'bi-tag';
                                        ?>
                                        <?php $roleDescription = trim((string) ($roleMeta['description'] ?? '')); ?>
                                        <th class="settings-roles-matrix__role-head" data-role="<?= e($roleKey) ?>">
                                            <div class="settings-roles-role-card">
                                                <button
                                                    type="button"
                                                    class="settings-roles-role-card__edit"
                                                    title="Edit role"
                                                    aria-label="Edit <?= e($roleLabel) ?> role"
                                                    data-edit-role
                                                    data-role-slug="<?= e($roleKey) ?>"
                                                    data-role-label="<?= e($roleLabel) ?>"
                                                    data-role-description="<?= e($roleDescription) ?>"
                                                    data-role-builtin="<?= $isBuiltin ? '1' : '0' ?>"
                                                >
                                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                                </button>
                                                <?php if (!$isBuiltin): ?>
                                                    <button
                                                        type="submit"
                                                        form="<?= e($deleteFormId) ?>"
                                                        class="settings-roles-role-card__delete"
                                                        title="Delete role"
                                                        aria-label="Delete <?= e($roleLabel) ?> role"
                                                        onclick="return confirm(<?= json_encode(
                                                            $roleUserCount > 0
                                                                ? 'Delete ' . $roleLabel . '? ' . $roleUserCount . ' user(s) are assigned — reassign them first.'
                                                                : 'Delete ' . $roleLabel . '? This cannot be undone.'
                                                        ) ?>);"
                                                    >
                                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                                    </button>
                                                <?php endif; ?>
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
                                                    <span class="settings-roles-role-card__users">
                                                        <?php if ($roleUserCount === 0): ?>
                                                            No users
                                                        <?php elseif ($roleUserCount === 1): ?>
                                                            1 user
                                                        <?php else: ?>
                                                            <?= $roleUserCount ?> users
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                                <div class="settings-roles-role-card__sort">
                                                    <button
                                                        type="submit"
                                                        form="<?= e($duplicateFormId) ?>"
                                                        class="settings-roles-role-card__sort-btn settings-roles-role-card__sort-btn--duplicate"
                                                        title="Duplicate role"
                                                        aria-label="Duplicate <?= e($roleLabel) ?> role"
                                                    >
                                                        <i class="bi bi-copy" aria-hidden="true"></i>
                                                    </button>
                                                    <button
                                                        type="submit"
                                                        form="moveRole-<?= e($roleKey) ?>-left"
                                                        class="settings-roles-role-card__sort-btn"
                                                        title="Move left"
                                                        aria-label="Move <?= e($roleLabel) ?> left"
                                                        <?= $canMoveRoleLeft ? '' : 'disabled' ?>
                                                    >
                                                        <i class="bi bi-chevron-left" aria-hidden="true"></i>
                                                    </button>
                                                    <button
                                                        type="submit"
                                                        form="moveRole-<?= e($roleKey) ?>-right"
                                                        class="settings-roles-role-card__sort-btn"
                                                        title="Move right"
                                                        aria-label="Move <?= e($roleLabel) ?> right"
                                                        <?= $canMoveRoleRight ? '' : 'disabled' ?>
                                                    >
                                                        <i class="bi bi-chevron-right" aria-hidden="true"></i>
                                                    </button>
                                                </div>
                                            </div>
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
                                <?php
                                $legendStoredDesc = trim((string) (($roleMetaBySlug[$roleKey] ?? [])['description'] ?? ''));
                                $legendDisplayDesc = $legendStoredDesc !== ''
                                    ? $legendStoredDesc
                                    : CompanyRoleService::descriptionForSlug($roleKey, $companyIdForRoles);
                                $legendDescFallback = CompanyRoleService::builtinDescription($roleKey);
                                ?>
                                <article
                                    class="settings-roles-legend-card"
                                    data-role="<?= e($roleKey) ?>"
                                    data-desc-fallback="<?= e($legendDescFallback) ?>"
                                >
                                    <header class="settings-roles-legend-card__head">
                                        <span class="settings-roles-legend-card__avatar"><?= e(strtoupper(substr(CompanyRoleService::labelForSlug($roleKey, $companyIdForRoles), 0, 1))) ?></span>
                                        <strong class="settings-roles-legend-card__title"><?= e(CompanyRoleService::labelForSlug($roleKey, $companyIdForRoles)) ?></strong>
                                    </header>
                                    <p class="settings-roles-legend-card__desc"><?= e($legendDisplayDesc) ?></p>
                                    <?php $legendUserCount = (int) ($roleUserCounts[$roleKey] ?? 0); ?>
                                    <p class="settings-roles-legend-card__users mb-2">
                                        <?php if ($legendUserCount === 0): ?>
                                            No users assigned
                                        <?php elseif ($legendUserCount === 1): ?>
                                            1 user assigned
                                        <?php else: ?>
                                            <?= $legendUserCount ?> users assigned
                                        <?php endif; ?>
                                    </p>
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

            <?php foreach ($editableRoleKeys as $roleKey): ?>
                <?php
                $deleteRoleMeta = $roleMetaBySlug[$roleKey] ?? null;
                $deleteIsBuiltin = $deleteRoleMeta === null || !empty($deleteRoleMeta['is_builtin']);
                ?>
                <form
                    id="duplicateRole-<?= e($roleKey) ?>"
                    method="post"
                    action="<?= url('actions/role-action.php') ?>"
                    class="visually-hidden"
                    aria-hidden="true"
                >
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="duplicate">
                    <input type="hidden" name="slug" value="<?= e($roleKey) ?>">
                </form>
                <form
                    id="moveRole-<?= e($roleKey) ?>-left"
                    method="post"
                    action="<?= url('actions/role-action.php') ?>"
                    class="visually-hidden"
                    aria-hidden="true"
                >
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="reorder">
                    <input type="hidden" name="slug" value="<?= e($roleKey) ?>">
                    <input type="hidden" name="direction" value="left">
                    <input type="hidden" name="role_order" value="<?= e($roleOrderValue) ?>">
                </form>
                <form
                    id="moveRole-<?= e($roleKey) ?>-right"
                    method="post"
                    action="<?= url('actions/role-action.php') ?>"
                    class="visually-hidden"
                    aria-hidden="true"
                >
                    <?= CSRF::field() ?>
                    <input type="hidden" name="action" value="reorder">
                    <input type="hidden" name="slug" value="<?= e($roleKey) ?>">
                    <input type="hidden" name="direction" value="right">
                    <input type="hidden" name="role_order" value="<?= e($roleOrderValue) ?>">
                </form>
                <?php if (!$deleteIsBuiltin): ?>
                    <form
                        id="deleteRole-<?= e($roleKey) ?>"
                        method="post"
                        action="<?= url('actions/role-action.php') ?>"
                        class="visually-hidden"
                        aria-hidden="true"
                    >
                        <?= CSRF::field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="slug" value="<?= e($roleKey) ?>">
                    </form>
                <?php endif; ?>
            <?php endforeach; ?>

            <div class="modal fade" id="editRoleModal" tabindex="-1" aria-labelledby="editRoleModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <form method="post" action="<?= url('actions/role-action.php') ?>" class="modal-content" id="editRoleForm">
                        <?= CSRF::field() ?>
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="slug" id="editRoleSlug" value="">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editRoleModalLabel">Edit role category</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label" for="editRoleLabel">Role name</label>
                                <input type="text" name="label" id="editRoleLabel" class="form-control" required maxlength="120" placeholder="e.g. Billing, Reception">
                            </div>
                            <div class="mb-0">
                                <label class="form-label" for="editRoleDescription">Description <span class="text-muted fw-normal">(optional)</span></label>
                                <textarea name="description" id="editRoleDescription" class="form-control" rows="3" maxlength="500" placeholder="Short summary for your team"></textarea>
                            </div>
                            <p class="form-text mt-3 mb-0" id="editRoleBuiltinNote">
                                Built-in roles keep their system ID; only the display name and description change here.
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> Save changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
