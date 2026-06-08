<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::guardAction();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verifyRequest()) {
    flash('error', 'Invalid request.');
    redirect('pages/settings.php');
}

$tab = $_POST['tab'] ?? 'branding';

try {
    if ($tab === 'roles') {
        if (!Auth::can(RoleAccess::PERMISSION_SETTINGS)) {
            throw new RuntimeException('You do not have permission to manage role access.');
        }

        $companyId = TenantService::id();
        $editableRoles = CompanyRoleAccessService::editableRolesForActor(Auth::role(), TenantService::id());

        if ($editableRoles === []) {
            throw new RuntimeException('Your account cannot edit role access.');
        }

        foreach ($editableRoles as $role) {
            $permissions = $_POST['role_permissions'][$role] ?? [];
            if (!is_array($permissions)) {
                $permissions = [];
            }

            $permissions = array_values(array_filter(
                $permissions,
                static fn($permission): bool => is_string($permission)
            ));

            $assignedOnly = !empty($_POST['assigned_cases_only'][$role]);
            $readOnly = !empty($_POST['read_only'][$role]);

            CompanyRoleAccessService::save($companyId, $role, $permissions, $assignedOnly, $readOnly);
        }
    } elseif ($tab === 'calendar') {
        SettingsService::updateCalendar($_POST);
    } else {
        SettingsService::update(
            $_POST,
            $_FILES['logo'] ?? null,
            $_FILES['favicon'] ?? null,
            $tab
        );
    }
    flash('success', $tab === 'roles' ? 'Role Access saved successfully.' : 'Settings saved successfully.');
    redirect($tab === 'roles' ? 'pages/settings-roles.php' : 'pages/settings.php?tab=' . urlencode($tab));
} catch (Throwable $e) {
    flash('error', $e->getMessage());
    redirect($tab === 'roles' ? 'pages/settings-roles.php' : 'pages/settings.php?tab=' . urlencode($tab));
}
