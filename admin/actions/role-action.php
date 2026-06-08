<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::guardAction();

$wantsJson = !empty($_POST['ajax'])
    || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

$jsonRoleResponse = static function (array $payload, int $status = 200) use ($wantsJson): void {
    if (!$wantsJson) {
        return;
    }

    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verifyRequest()) {
    $jsonRoleResponse(['success' => false, 'message' => 'Invalid request.'], 403);
    flash('error', 'Invalid request.');
    redirect('pages/settings-roles.php');
}

if (!Auth::can(RoleAccess::PERMISSION_SETTINGS)) {
    $jsonRoleResponse(['success' => false, 'message' => 'You do not have permission to manage roles.'], 403);
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
    } elseif ($action === 'update') {
        $slug = (string) ($_POST['slug'] ?? '');
        $label = trim((string) ($_POST['label'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));

        $editable = CompanyRoleAccessService::editableRolesForActor(Auth::role(), $companyId);
        if (!in_array(CompanyRoleService::normalizeSlug($slug), $editable, true)) {
            throw new RuntimeException('You cannot edit this role.');
        }

        $result = CompanyRoleService::update(
            $companyId,
            $slug,
            $label,
            $description !== '' ? $description : null
        );

        if (!$result['success']) {
            throw new RuntimeException($result['message'] ?? 'Could not update role.');
        }

        $jsonRoleResponse([
            'success' => true,
            'slug' => CompanyRoleService::normalizeSlug($slug),
            'label' => $label,
            'description' => $description,
        ]);

        flash('success', 'Role "' . $label . '" updated.');
    } elseif ($action === 'delete') {
        $slug = (string) ($_POST['slug'] ?? '');
        $result = CompanyRoleService::delete($companyId, $slug);

        if (!$result['success']) {
            throw new RuntimeException($result['message'] ?? 'Could not delete role.');
        }

        flash('success', 'Role removed.');
    } elseif ($action === 'duplicate') {
        $slug = (string) ($_POST['slug'] ?? '');
        $editable = CompanyRoleAccessService::editableRolesForActor(Auth::role(), $companyId);

        if (!in_array(CompanyRoleService::normalizeSlug($slug), $editable, true)) {
            throw new RuntimeException('You cannot duplicate this role.');
        }

        $result = CompanyRoleService::duplicate($companyId, $slug);

        if (!$result['success']) {
            throw new RuntimeException($result['message'] ?? 'Could not duplicate role.');
        }

        flash('success', 'Role duplicated. Review its permissions below and save.');
    } elseif ($action === 'reorder') {
        $slug = (string) ($_POST['slug'] ?? '');
        $direction = (string) ($_POST['direction'] ?? 'left');
        $ordered = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) ($_POST['role_order'] ?? ''))
        )));
        $editable = CompanyRoleAccessService::editableRolesForActor(Auth::role(), $companyId);
        $ordered = array_values(array_filter(
            $ordered,
            static fn(string $roleSlug): bool => in_array($roleSlug, $editable, true)
        ));

        if ($ordered === []) {
            $ordered = $editable;
        }

        $result = CompanyRoleService::moveRole($companyId, $slug, $direction, $ordered);

        if (!$result['success']) {
            throw new RuntimeException($result['message'] ?? 'Could not reorder roles.');
        }

        flash('success', 'Role order updated.');
    } else {
        throw new RuntimeException('Unknown action.');
    }
} catch (Throwable $e) {
    $jsonRoleResponse(['success' => false, 'message' => $e->getMessage()], 400);
    flash('error', $e->getMessage());
}

redirect('pages/settings-roles.php');
