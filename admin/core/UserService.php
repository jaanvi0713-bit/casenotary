<?php

declare(strict_types=1);

class UserService
{
    /**
     * @return array{0: string, 1: list<string|int>}
     */
    private static function staffRoleFilter(int $companyId): array
    {
        $slugs = CompanyRoleService::activeSlugsForCompany($companyId);
        if ($slugs === []) {
            $slugs = CompanyRoleService::BUILTIN_SLUGS;
        }

        $placeholders = implode(',', array_fill(0, count($slugs), '?'));

        return ["u.role IN ({$placeholders})", $slugs];
    }

    /**
     * @return array{0: list<string>, 1: list<string|int>}
     */
    private static function staffListWhere(?string $search = null): array
    {
        $companyId = TenantService::isEnabled() ? TenantService::id() : 1;
        [$roleSql, $roleParams] = self::staffRoleFilter($companyId);
        $where = [$roleSql];
        $params = $roleParams;

        if (TenantService::isEnabled() && Database::columnExists('users', 'company_id')) {
            $where[] = 'u.company_id = ?';
            $params[] = TenantService::id();
        }

        if ($search !== null && trim($search) !== '') {
            $term = '%' . trim($search) . '%';
            if (Database::columnExists('users', 'name')) {
                $where[] = '(u.email LIKE ? OR u.name LIKE ?)';
                $params[] = $term;
                $params[] = $term;
            } elseif (Database::columnExists('users', 'first_name')) {
                $where[] = '(u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)';
                $params[] = $term;
                $params[] = $term;
                $params[] = $term;
            } else {
                $where[] = 'u.email LIKE ?';
                $params[] = $term;
            }
        }

        return [$where, $params];
    }

    public static function countStaff(?string $search = null): int
    {
        [$where, $params] = self::staffListWhere($search);

        return (int) (Database::fetch(
            'SELECT COUNT(*) AS c FROM users u WHERE ' . implode(' AND ', $where),
            $params
        )['c'] ?? 0);
    }

    /** @return list<array<string, mixed>> */
    public static function listStaffPaginated(int $page, int $perPage = 10, ?string $search = null): array
    {
        [$where, $params] = self::staffListWhere($search);
        $offset = paginationOffset($page, $perPage);
        $nameSelect = self::nameSelectSql('u');

        $params[] = $perPage;
        $params[] = $offset;

        return Database::fetchAll(
            'SELECT u.id, u.email, u.role, u.status, u.last_login, ' . $nameSelect . '
             FROM users u
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY u.role ASC, ' . self::nameOrderSql('u') . '
             LIMIT ? OFFSET ?',
            $params
        );
    }

    /** @return list<array<string, mixed>> */
    public static function listStaff(?string $search = null): array
    {
        return self::listStaffPaginated(1, max(1, self::countStaff($search)), $search);
    }

    public static function getStaffById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $companyId = TenantService::isEnabled() ? TenantService::id() : 1;
        [$roleSql, $roleParams] = self::staffRoleFilter($companyId);
        $where = ['u.id = ?', $roleSql];
        $params = array_merge([$id], $roleParams);

        if (TenantService::isEnabled() && Database::columnExists('users', 'company_id')) {
            $where[] = 'u.company_id = ?';
            $params[] = TenantService::id();
        }

        $nameSelect = self::nameSelectSql('u');

        return Database::fetch(
            'SELECT u.*, ' . $nameSelect . '
             FROM users u
             WHERE ' . implode(' AND ', $where) . '
             LIMIT 1',
            $params
        ) ?: null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array{success: bool, message?: string, user_id?: int}
     */
    public static function createStaff(array $data, string $actorRole): array
    {
        $role = (string) ($data['role'] ?? 'staff');
        $companyId = TenantService::isEnabled() ? TenantService::id() : 1;
        if (!in_array($role, RoleAccess::assignableRoles($actorRole, $companyId), true)) {
            return ['success' => false, 'message' => 'You cannot assign that role.'];
        }

        $email = strtolower(trim((string) ($data['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'A valid email is required.'];
        }

        $existing = Database::fetch('SELECT id FROM users WHERE email = ? LIMIT 1', [$email]);
        if ($existing) {
            return ['success' => false, 'message' => 'That email is already registered.'];
        }

        $password = (string) ($data['password'] ?? '');
        if (strlen($password) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters.'];
        }

        $nameFields = self::normalizeNameFields($data);
        if ($nameFields['error'] !== null) {
            return ['success' => false, 'message' => $nameFields['error']];
        }

        $status = in_array(($data['status'] ?? 'active'), ['active', 'inactive', 'suspended'], true)
            ? (string) $data['status']
            : 'active';

        $fields = array_merge($nameFields['fields'], [
            'email' => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'role' => $role,
            'status' => $status,
        ]);

        if (TenantService::isEnabled() && Database::columnExists('users', 'company_id')) {
            $fields['company_id'] = TenantService::id();
        }

        if (Database::columnExists('users', 'phone')) {
            $phone = trim((string) ($data['phone'] ?? ''));
            if ($phone !== '') {
                $fields['phone'] = $phone;
            }
        }

        $userId = self::insertUser($fields);

        return ['success' => true, 'user_id' => $userId];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{success: bool, message?: string}
     */
    public static function updateStaff(int $id, array $data, string $actorRole, int $actorId): array
    {
        $user = self::getStaffById($id);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found.'];
        }

        if ((string) ($user['role'] ?? '') === 'admin' && $actorRole !== 'super_admin' && $actorRole !== 'admin') {
            return ['success' => false, 'message' => 'You cannot edit this user.'];
        }

        $role = (string) ($data['role'] ?? $user['role']);
        $companyId = TenantService::isEnabled() ? TenantService::id() : 1;
        $assignable = RoleAccess::assignableRoles($actorRole, $companyId);
        if ($id === $actorId) {
            $role = (string) $user['role'];
        } elseif (!in_array($role, $assignable, true)) {
            return ['success' => false, 'message' => 'You cannot assign that role.'];
        }

        $nameFields = self::normalizeNameFields($data);
        if ($nameFields['error'] !== null) {
            return ['success' => false, 'message' => $nameFields['error']];
        }

        $email = strtolower(trim((string) ($data['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'A valid email is required.'];
        }

        $duplicate = Database::fetch('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1', [$email, $id]);
        if ($duplicate) {
            return ['success' => false, 'message' => 'That email is already in use.'];
        }

        $status = in_array(($data['status'] ?? $user['status']), ['active', 'inactive', 'suspended'], true)
            ? (string) $data['status']
            : (string) $user['status'];

        if ($id === $actorId && $status !== 'active') {
            return ['success' => false, 'message' => 'You cannot deactivate your own account.'];
        }

        $sets = ['email = ?', 'role = ?', 'status = ?', 'updated_at = NOW()'];
        $params = [$email, $role, $status];

        foreach ($nameFields['fields'] as $column => $value) {
            $sets[] = $column . ' = ?';
            $params[] = $value;
        }

        if (Database::columnExists('users', 'phone')) {
            $sets[] = 'phone = ?';
            $params[] = trim((string) ($data['phone'] ?? '')) ?: null;
        }

        $password = (string) ($data['password'] ?? '');
        if ($password !== '') {
            if (strlen($password) < 8) {
                return ['success' => false, 'message' => 'Password must be at least 8 characters.'];
            }
            $sets[] = 'password = ?';
            $params[] = password_hash($password, PASSWORD_BCRYPT);
        }

        $params[] = $id;
        Database::query('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);

        return ['success' => true];
    }

    /** @return array{success: bool, message?: string} */
    public static function deleteStaff(int $id, string $actorRole, int $actorId): array
    {
        if ($id <= 0) {
            return ['success' => false, 'message' => 'Invalid user.'];
        }

        if ($id === $actorId) {
            return ['success' => false, 'message' => 'You cannot delete your own account.'];
        }

        $user = self::getStaffById($id);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found.'];
        }

        $targetRole = (string) ($user['role'] ?? '');
        if ($targetRole === 'admin' && $actorRole !== 'super_admin') {
            return ['success' => false, 'message' => 'Only a super admin can remove an administrator.'];
        }

        if (Database::tableExists('documents')) {
            $docRow = Database::fetch(
                'SELECT COUNT(*) AS c FROM documents WHERE uploaded_by = ?',
                [$id]
            );
            if ((int) ($docRow['c'] ?? 0) > 0) {
                return [
                    'success' => false,
                    'message' => 'This user uploaded documents. Set their status to Inactive instead of deleting.',
                ];
            }
        }

        Database::query('DELETE FROM users WHERE id = ?', [$id]);

        return ['success' => true];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{fields: array<string, string>, error: ?string}
     */
    private static function normalizeNameFields(array $data): array
    {
        $firstName = trim((string) ($data['first_name'] ?? ''));
        $lastName  = trim((string) ($data['last_name'] ?? ''));
        $fullName  = trim($firstName . ' ' . $lastName);

        if (Database::columnExists('users', 'first_name') && Database::columnExists('users', 'last_name')) {
            if ($firstName === '' || $lastName === '') {
                return ['fields' => [], 'error' => 'First and last name are required.'];
            }

            return [
                'fields' => ['first_name' => $firstName, 'last_name' => $lastName],
                'error' => null,
            ];
        }

        if (Database::columnExists('users', 'name')) {
            if ($fullName === '') {
                return ['fields' => [], 'error' => 'Name is required.'];
            }

            return ['fields' => ['name' => $fullName], 'error' => null];
        }

        return ['fields' => [], 'error' => 'User name fields are not configured in the database.'];
    }

    /** @param array<string, mixed> $fields */
    private static function insertUser(array $fields): int
    {
        $columns = [];
        $placeholders = [];
        $params = [];

        foreach ($fields as $key => $value) {
            if (!Database::columnExists('users', $key)) {
                continue;
            }
            $columns[] = $key;
            $placeholders[] = '?';
            $params[] = $value;
        }

        if (Database::columnExists('users', 'is_active')) {
            $columns[] = 'is_active';
            $placeholders[] = '?';
            $params[] = ($fields['status'] ?? 'active') === 'active' ? 1 : 0;
        }

        return Database::insert(
            'INSERT INTO users (' . implode(', ', $columns) . ', created_at, updated_at)
             VALUES (' . implode(', ', $placeholders) . ', NOW(), NOW())',
            $params
        );
    }

    private static function nameSelectSql(string $alias): string
    {
        if (Database::columnExists('users', 'name')) {
            if (Database::columnExists('users', 'first_name')) {
                return 'COALESCE(NULLIF(TRIM(' . $alias . '.name), ""), TRIM(CONCAT(COALESCE(' . $alias . '.first_name, ""), " ", COALESCE(' . $alias . '.last_name, "")))) AS display_name';
            }

            return 'NULLIF(TRIM(' . $alias . '.name), "") AS display_name';
        }

        if (Database::columnExists('users', 'first_name')) {
            return 'TRIM(CONCAT(COALESCE(' . $alias . '.first_name, ""), " ", COALESCE(' . $alias . '.last_name, ""))) AS display_name';
        }

        return $alias . '.email AS display_name';
    }

    private static function nameOrderSql(string $alias): string
    {
        if (Database::columnExists('users', 'name')) {
            if (Database::columnExists('users', 'first_name')) {
                return 'COALESCE(NULLIF(TRIM(' . $alias . '.name), ""), TRIM(CONCAT(COALESCE(' . $alias . '.first_name, ""), " ", COALESCE(' . $alias . '.last_name, "")))) ASC';
            }

            return 'TRIM(' . $alias . '.name) ASC';
        }

        if (Database::columnExists('users', 'first_name')) {
            return 'TRIM(CONCAT(COALESCE(' . $alias . '.first_name, ""), " ", COALESCE(' . $alias . '.last_name, ""))) ASC';
        }

        return $alias . '.email ASC';
    }
}
