<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requirePage('users');

$pageTitle = 'Users';
$q = trim((string) ($_GET['q'] ?? ''));
$search = $q !== '' ? $q : null;
$perPage = 10;
$page = requestPageNumber();
$totalUsers = UserService::countStaff($search);
$totalPages = max(1, (int) ceil($totalUsers / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$users = UserService::listStaffPaginated($page, $perPage, $search);
$pageSubtitle = $totalUsers . ' team member' . ($totalUsers === 1 ? '' : 's');
$canViewUsers = Auth::can(RoleAccess::PERMISSION_USERS);
$canManageUsers = Auth::canManage(RoleAccess::PERMISSION_USERS);
$assignableRoles = RoleAccess::assignableRoles(Auth::role(), TenantService::id());
$canEditUsers = $canManageUsers && $assignableRoles !== [];

require __DIR__ . '/../includes/header.php';
?>

<div class="saas-card">
    <div class="saas-card-header">
        <div>
            <h2 class="saas-card-title">Team Users</h2>
            <p class="saas-card-subtitle">Manage who can access the admin portal and their role</p>
        </div>
        <?php if ($canEditUsers): ?>
        <a href="<?= url('pages/user-form.php') ?>" class="btn btn-primary btn-sm"><i class="bi bi-person-plus"></i> Add User</a>
        <?php endif; ?>
    </div>
    <form method="get" class="table-toolbar">
        <div class="table-search">
            <i class="bi bi-search"></i>
            <input type="search" class="form-control form-control-sm" name="q" value="<?= e($q) ?>" placeholder="Search users...">
        </div>
    </form>
    <div class="card-body p-0">
        <?php if ($users === []): ?>
            <div class="empty-state py-5">
                <i class="bi bi-person-badge"></i>
                <p><?= $search !== null ? 'No users match your search.' : 'No team users yet. Add managers or staff to share access.' ?></p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table saas-table mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Last login</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $row): ?>
                            <tr>
                                <td>
                                    <strong><?= e($row['display_name'] ?? '') ?></strong>
                                    <?php if ((int) $row['id'] === (int) Auth::id()): ?>
                                        <span class="badge bg-light text-dark ms-1">You</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($row['email']) ?></td>
                                <td><span class="badge bg-primary-subtle text-primary"><?= e(RoleAccess::roleLabel((string) $row['role'], TenantService::id())) ?></span></td>
                                <td>
                                    <?php if (($row['status'] ?? '') === 'active'): ?>
                                        <span class="badge bg-success-subtle text-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?= e(ucfirst((string) ($row['status'] ?? 'inactive'))) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted small"><?= !empty($row['last_login']) ? e(timeAgo($row['last_login'])) : '—' ?></td>
                                <td class="text-end">
                                    <div class="table-row-actions d-flex gap-2 justify-content-end">
                                        <?php if ($canEditUsers): ?>
                                            <a href="<?= url('pages/user-form.php?id=' . (int) $row['id']) ?>" class="btn btn-soft btn-sm text-decoration-none">Edit</a>
                                            <?php if ((int) $row['id'] !== (int) Auth::id()): ?>
                                                <button
                                                    type="submit"
                                                    class="btn btn-soft-danger btn-sm"
                                                    form="delete-user-<?= (int) $row['id'] ?>"
                                                    onclick="return confirm('Remove <?= e($row['display_name'] ?? 'this user') ?>? They will lose admin portal access.');"
                                                >Delete</button>
                                            <?php endif; ?>
                                        <?php elseif ($canViewUsers): ?>
                                            <a href="<?= url('pages/user-form.php?id=' . (int) $row['id']) ?>" class="btn btn-soft btn-sm text-decoration-none">View</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
                <small class="text-muted">
                    Showing <?= count($users) ?> of <?= $totalUsers ?> team member<?= $totalUsers === 1 ? '' : 's' ?>
                </small>
                <?= renderPaginationNav($page, $totalPages) ?>
            </div>
            <?php if ($canEditUsers): ?>
                <?php foreach ($users as $row): ?>
                    <?php if ((int) $row['id'] !== (int) Auth::id()): ?>
                        <form method="post" action="<?= url('actions/user-action.php') ?>" id="delete-user-<?= (int) $row['id'] ?>" class="d-none">
                            <?= CSRF::field() ?>
                            <input type="hidden" name="action" value="delete_user">
                            <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                        </form>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<div class="saas-card mt-4">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
            <h3 class="h6 mb-0">Role access for <?= e(TenantService::name()) ?></h3>
            <?php if (Auth::can(RoleAccess::PERMISSION_SETTINGS) && CompanyRoleAccessService::editableRolesForActor(Auth::role(), TenantService::id()) !== []): ?>
                <a href="<?= url('pages/settings-roles.php') ?>" class="btn btn-soft btn-sm">Customize roles</a>
            <?php endif; ?>
        </div>
        <div class="row g-3">
            <?php
            $overviewCompanyId = TenantService::id();
            foreach (RoleAccess::assignableRoles(Auth::role(), TenantService::id()) as $assignableRole):
                $roleConfig = CompanyRoleAccessService::get($overviewCompanyId, $assignableRole);
                $permissionLabels = array_map(
                    static fn(string $p): string => CompanyRoleAccessService::permissionLabel($p),
                    $roleConfig['permissions']
                );
            ?>
                <div class="col-md-4">
                    <div class="border rounded p-3 h-100">
                        <strong><?= e(RoleAccess::roleLabel($assignableRole, TenantService::id())) ?></strong>
                        <p class="small text-muted mb-2 mt-2"><?= e(RoleAccess::roleDescription($assignableRole, TenantService::id())) ?></p>
                        <p class="small mb-1"><span class="text-muted">Access:</span> <?= e(implode(', ', $permissionLabels)) ?></p>
                        <?php if ($roleConfig['assigned_cases_only']): ?>
                            <p class="small mb-1"><span class="badge bg-secondary-subtle text-secondary">Assigned cases only</span></p>
                        <?php endif; ?>
                        <?php if ($roleConfig['read_only']): ?>
                            <p class="small mb-0"><span class="badge bg-secondary-subtle text-secondary">Read-only — no edits or saves</span></p>
                        <?php else: ?>
                            <p class="small mb-0"><span class="badge bg-success-subtle text-success">Can edit &amp; save</span></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
