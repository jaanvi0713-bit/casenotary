<?php

declare(strict_types=1);

/**
 * Per-company role permission configuration.
 */
class CompanyRoleAccessService
{
    /** @var array<int, array<string, array{permissions: list<string>, assigned_cases_only: bool, read_only: bool}>> */
    private static array $cache = [];

    /** @var list<string> */
    public const CONFIGURABLE_PERMISSIONS = [
        RoleAccess::PERMISSION_DASHBOARD,
        RoleAccess::PERMISSION_INSIGHTS,
        RoleAccess::PERMISSION_USERS,
        RoleAccess::PERMISSION_CLIENTS,
        RoleAccess::PERMISSION_CASES,
        RoleAccess::PERMISSION_PAYMENTS,
        RoleAccess::PERMISSION_APPOINTMENTS,
        RoleAccess::PERMISSION_NOTIFICATIONS,
        RoleAccess::PERMISSION_ASSISTANT,
        RoleAccess::PERMISSION_SETTINGS,
        RoleAccess::PERMISSION_PROFILE,
    ];

    /** @var list<string> */
    public const CONFIGURABLE_ROLES = ['admin', 'manager', 'staff', 'viewer'];

    /** @return array<string, list<string>> */
    public static function defaultRolePermissions(): array
    {
        return [
            'admin' => [
                RoleAccess::PERMISSION_DASHBOARD,
                RoleAccess::PERMISSION_INSIGHTS,
                RoleAccess::PERMISSION_USERS,
                RoleAccess::PERMISSION_CLIENTS,
                RoleAccess::PERMISSION_CASES,
                RoleAccess::PERMISSION_PAYMENTS,
                RoleAccess::PERMISSION_APPOINTMENTS,
                RoleAccess::PERMISSION_NOTIFICATIONS,
                RoleAccess::PERMISSION_ASSISTANT,
                RoleAccess::PERMISSION_SETTINGS,
                RoleAccess::PERMISSION_PROFILE,
            ],
            'manager' => [
                RoleAccess::PERMISSION_DASHBOARD,
                RoleAccess::PERMISSION_INSIGHTS,
                RoleAccess::PERMISSION_CLIENTS,
                RoleAccess::PERMISSION_CASES,
                RoleAccess::PERMISSION_PAYMENTS,
                RoleAccess::PERMISSION_APPOINTMENTS,
                RoleAccess::PERMISSION_NOTIFICATIONS,
                RoleAccess::PERMISSION_ASSISTANT,
                RoleAccess::PERMISSION_PROFILE,
            ],
            'staff' => [
                RoleAccess::PERMISSION_DASHBOARD,
                RoleAccess::PERMISSION_CLIENTS,
                RoleAccess::PERMISSION_CASES,
                RoleAccess::PERMISSION_APPOINTMENTS,
                RoleAccess::PERMISSION_NOTIFICATIONS,
                RoleAccess::PERMISSION_ASSISTANT,
                RoleAccess::PERMISSION_PROFILE,
            ],
            'viewer' => [
                RoleAccess::PERMISSION_DASHBOARD,
                RoleAccess::PERMISSION_CLIENTS,
                RoleAccess::PERMISSION_CASES,
                RoleAccess::PERMISSION_PAYMENTS,
                RoleAccess::PERMISSION_APPOINTMENTS,
                RoleAccess::PERMISSION_NOTIFICATIONS,
                RoleAccess::PERMISSION_ASSISTANT,
                RoleAccess::PERMISSION_PROFILE,
            ],
        ];
    }

    public static function defaultAssignedCasesOnly(string $role): bool
    {
        return $role === 'staff';
    }

    public static function defaultReadOnly(string $role): bool
    {
        return $role === 'viewer';
    }

    public static function tableExists(): bool
    {
        return Database::tableExists('company_role_permissions');
    }

    public static function clearCache(?int $companyId = null): void
    {
        if ($companyId === null) {
            self::$cache = [];

            return;
        }

        unset(self::$cache[$companyId]);
    }

    /**
     * @return array{permissions: list<string>, assigned_cases_only: bool, read_only: bool}
     */
    public static function get(int $companyId, string $role): array
    {
        if ($role === 'super_admin') {
            return [
                'permissions' => ['*'],
                'assigned_cases_only' => false,
                'read_only' => false,
            ];
        }

        if ($companyId <= 0) {
            $companyId = TenantService::isEnabled() ? TenantService::id() : 1;
        }

        if (!isset(self::$cache[$companyId])) {
            self::$cache[$companyId] = self::loadCompany($companyId);
        }

        return self::$cache[$companyId][$role] ?? self::buildDefault($role);
    }

    /**
     * @param list<string> $permissions
     */
    public static function save(int $companyId, string $role, array $permissions, bool $assignedCasesOnly, bool $readOnly): void
    {
        if ($companyId <= 0 || !CompanyRoleService::existsForCompany($companyId, $role)) {
            throw new RuntimeException('Invalid company or role.');
        }

        $permissions = array_values(array_unique(array_filter(
            $permissions,
            static fn(string $p): bool => in_array($p, self::CONFIGURABLE_PERMISSIONS, true)
        )));

        if ($permissions === []) {
            throw new RuntimeException('Select at least one permission for this role.');
        }

        if (!self::tableExists()) {
            throw new RuntimeException('Role access storage is not installed. Run: php admin/sql/migrate_company_role_access.php');
        }

        $json = json_encode($permissions, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Could not encode permissions.');
        }

        $existing = Database::fetch(
            'SELECT company_id FROM company_role_permissions WHERE company_id = ? AND role = ? LIMIT 1',
            [$companyId, $role]
        );

        if ($existing) {
            Database::query(
                'UPDATE company_role_permissions
                 SET permissions = ?, assigned_cases_only = ?, read_only = ?, updated_at = NOW()
                 WHERE company_id = ? AND role = ?',
                [$json, $assignedCasesOnly ? 1 : 0, $readOnly ? 1 : 0, $companyId, $role]
            );
        } else {
            Database::query(
                'INSERT INTO company_role_permissions (company_id, role, permissions, assigned_cases_only, read_only, updated_at)
                 VALUES (?, ?, ?, ?, ?, NOW())',
                [$companyId, $role, $json, $assignedCasesOnly ? 1 : 0, $readOnly ? 1 : 0]
            );
        }

        self::clearCache($companyId);
    }

    public static function seedCompany(int $companyId): void
    {
        if ($companyId <= 0 || !self::tableExists()) {
            return;
        }

        if (CompanyRoleService::tableExists()) {
            CompanyRoleService::seedCompany($companyId);
        }

        foreach (CompanyRoleService::activeSlugsForCompany($companyId) as $role) {
            $defaults = self::buildDefault($role);
            $existing = Database::fetch(
                'SELECT company_id FROM company_role_permissions WHERE company_id = ? AND role = ? LIMIT 1',
                [$companyId, $role]
            );

            if ($existing) {
                continue;
            }

            $json = json_encode($defaults['permissions'], JSON_UNESCAPED_UNICODE);
            Database::query(
                'INSERT INTO company_role_permissions (company_id, role, permissions, assigned_cases_only, read_only, updated_at)
                 VALUES (?, ?, ?, ?, ?, NOW())',
                [
                    $companyId,
                    $role,
                    $json,
                    $defaults['assigned_cases_only'] ? 1 : 0,
                    $defaults['read_only'] ? 1 : 0,
                ]
            );
        }

        self::clearCache($companyId);
    }

    public static function seedAllCompanies(): void
    {
        if (!TenantService::isEnabled() || !self::tableExists()) {
            self::seedCompany(1);

            return;
        }

        foreach (Database::fetchAll('SELECT id FROM companies') as $row) {
            self::seedCompany((int) $row['id']);
        }
    }

    /** @return list<string> */
    public static function editableRolesForActor(string $actorRole, ?int $companyId = null): array
    {
        return CompanyRoleService::editableSlugsForActor($actorRole, $companyId);
    }

    public static function permissionLabel(string $permission): string
    {
        return match ($permission) {
            RoleAccess::PERMISSION_DASHBOARD => 'Dashboard',
            RoleAccess::PERMISSION_INSIGHTS => 'Insights',
            RoleAccess::PERMISSION_USERS => 'Users',
            RoleAccess::PERMISSION_CLIENTS => 'Clients',
            RoleAccess::PERMISSION_CASES => 'Cases',
            RoleAccess::PERMISSION_PAYMENTS => 'Payments',
            RoleAccess::PERMISSION_APPOINTMENTS => 'Appointments',
            RoleAccess::PERMISSION_NOTIFICATIONS => 'Notifications',
            RoleAccess::PERMISSION_ASSISTANT => 'AI Assistant',
            RoleAccess::PERMISSION_SETTINGS => 'Company settings',
            RoleAccess::PERMISSION_PROFILE => 'My profile',
            default => ucwords(str_replace('_', ' ', $permission)),
        };
    }

    /**
     * @return array<string, array{permissions: list<string>, assigned_cases_only: bool, read_only: bool}>
     */
    private static function loadCompany(int $companyId): array
    {
        $config = [];

        foreach (CompanyRoleService::activeSlugsForCompany($companyId) as $role) {
            $config[$role] = self::buildDefault($role);
        }

        if (!self::tableExists()) {
            return $config;
        }

        self::seedCompany($companyId);

        $rows = Database::fetchAll(
            'SELECT role, permissions, assigned_cases_only, read_only
             FROM company_role_permissions
             WHERE company_id = ?',
            [$companyId]
        );

        foreach ($rows as $row) {
            $role = (string) ($row['role'] ?? '');
            if (!CompanyRoleService::existsForCompany($companyId, $role)) {
                continue;
            }

            $decoded = json_decode((string) ($row['permissions'] ?? '[]'), true);
            $permissions = is_array($decoded)
                ? array_values(array_filter($decoded, static fn($p): bool => is_string($p) && in_array($p, self::CONFIGURABLE_PERMISSIONS, true)))
                : [];

            if ($permissions === []) {
                $permissions = self::buildDefault($role)['permissions'];
            }

            $config[$role] = [
                'permissions' => $permissions,
                'assigned_cases_only' => (int) ($row['assigned_cases_only'] ?? 0) === 1,
                'read_only' => (int) ($row['read_only'] ?? 0) === 1,
            ];
        }

        return $config;
    }

    /**
     * @return array{permissions: list<string>, assigned_cases_only: bool, read_only: bool}
     */
    private static function buildDefault(string $role): array
    {
        $defaults = self::defaultRolePermissions();
        $fallbackRole = isset($defaults[$role]) ? $role : 'staff';

        return [
            'permissions' => $defaults[$fallbackRole],
            'assigned_cases_only' => self::defaultAssignedCasesOnly($fallbackRole),
            'read_only' => self::defaultReadOnly($fallbackRole),
        ];
    }
}
