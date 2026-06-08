<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::guardAction();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verifyRequest()) {
    flash('error', 'Invalid request.');
    redirect('pages/users.php');
}

$action = $_POST['action'] ?? '';
$actorRole = Auth::role();
$actorId = (int) Auth::id();

try {
    switch ($action) {
        case 'create_user':
            if (!Auth::canManage(RoleAccess::PERMISSION_USERS) || RoleAccess::assignableRoles($actorRole, TenantService::id()) === []) {
                flash('error', 'You do not have permission to add users.');
                redirect('pages/users.php');
            }
            $result = UserService::createStaff($_POST, $actorRole);
            if (!$result['success']) {
                flash('error', $result['message'] ?? 'Could not create user.');
                redirect('pages/user-form.php');
            }
            flash('success', 'User created successfully.');
            redirect('pages/users.php');
            break;

        case 'update_user':
            if (!Auth::canManage(RoleAccess::PERMISSION_USERS) || RoleAccess::assignableRoles($actorRole, TenantService::id()) === []) {
                flash('error', 'You do not have permission to edit users.');
                redirect('pages/users.php');
            }
            $userId = (int) ($_POST['user_id'] ?? 0);
            $result = UserService::updateStaff($userId, $_POST, $actorRole, $actorId);
            if (!$result['success']) {
                flash('error', $result['message'] ?? 'Could not update user.');
                redirect('pages/user-form.php?id=' . $userId);
            }
            flash('success', 'User updated successfully.');
            redirect('pages/users.php');
            break;

        case 'delete_user':
            if (!Auth::canManage(RoleAccess::PERMISSION_USERS)) {
                flash('error', 'You do not have permission to delete users.');
                redirect('pages/users.php');
            }
            $userId = (int) ($_POST['user_id'] ?? 0);
            $result = UserService::deleteStaff($userId, $actorRole, $actorId);
            if (!$result['success']) {
                flash('error', $result['message'] ?? 'Could not delete user.');
                redirect('pages/users.php');
            }
            flash('success', 'User removed successfully.');
            redirect('pages/users.php');
            break;

        default:
            flash('error', 'Unknown action.');
            redirect('pages/users.php');
    }
} catch (Throwable $e) {
    flash('error', $e->getMessage());
    redirect('pages/users.php');
}
