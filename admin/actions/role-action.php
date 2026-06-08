<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::guardAction();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verifyRequest()) {
    flash('error', 'Invalid request.');
    redirect('pages/settings-roles.php');
}

if (!Auth::can(RoleAccess::PERMISSION_SETTINGS)) {
    flash('error', 'You do not have permission to manage roles.');
    redirect('pages/settings-roles.php');
}

$action = $_POST['action'] ?? '';
$companyId = TenantService::id();

try {
    if ($action === 'create') {
        $label = trim((string) ($_POST['label'] ?? ''));
        $copyFrom = (string) ($_POST['copy_from'] ?? 'staff');
        $description = trim((string) ($_POST['description'] ?? ''));

        $result = CompanyRoleService::create(
            $companyId,
            $label,
            $copyFrom,
            $description !== '' ? $description : null
        );

        if (!$result['success']) {
            throw new RuntimeException($result['message'] ?? 'Could not create role.');
        }

        flash('success', 'Role "' . $label . '" created. Set its permissions below and save.');
    } elseif ($action === 'delete') {
        $slug = (string) ($_POST['slug'] ?? '');
        $result = CompanyRoleService::delete($companyId, $slug);

        if (!$result['success']) {
            throw new RuntimeException($result['message'] ?? 'Could not delete role.');
        }

        flash('success', 'Role removed.');
    } else {
        throw new RuntimeException('Unknown action.');
    }
} catch (Throwable $e) {
    flash('error', $e->getMessage());
}

redirect('pages/settings-roles.php');
