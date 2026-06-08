<?php

declare(strict_types=1);

/**
 * Per-company role categories (built-in + custom).
 */
class CompanyRoleService
{
    /** @var list<string> */
    public const RESERVED_SLUGS = ['super_admin', 'client'];

    /** @var list<string> */
    public const BUILTIN_SLUGS = ['admin', 'manager', 'staff', 'viewer'];

    /** @var array<string, string> */
    private const BUILTIN_DESCRIPTIONS = [
        'admin' => 'Full access to company settings, users, payments, and all modules.',
        'manager' => 'Clients, cases, payments, appointments, notifications, and AI — tune access on Role access.',
        'staff' => 'Usually assigned cases only — tune access on Role access.',
        'viewer' => 'View-focused — enable read-only on Role access to block edits.',
    ];

    /** @var array<int, list<array<string, mixed>>> */
    private static array $cache = [];

    public static function tableExists(): bool
    {
        return Database::tableExists('company_roles');
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
     * @return list<array{id: int, slug: string, label: string, description: string, is_builtin: bool, sort_order: int}>
     */
    public static function listForCompany(int $companyId, bool $activeOnly = true): array
    {
        if ($companyId <= 0) {
            $companyId = TenantService::isEnabled() ? TenantService::id() : 1;
        }

        if (!isset(self::$cache[$companyId])) {
            self::$cache[$companyId] = self::loadForCompany($companyId);
        }

        $rows = self::$cache[$companyId];

        if (!$activeOnly) {
            return $rows;
        }

        return array_values(array_filter(
            $rows,
            static fn(array $row): bool => ($row['status'] ?? 'active') === 'active'
        ));
    }

    public static function existsForCompany(int $companyId, string $slug): bool
    {
        $slug = self::normalizeSlug($slug);
        if ($slug === '' || in_array($slug, self::RESERVED_SLUGS, true)) {
            return false;
        }

        if ($slug === 'super_admin') {
            return true;
        }

        foreach (self::listForCompany($companyId) as $row) {
            if (($row['slug'] ?? '') === $slug) {
                return true;
            }
        }

        if (!self::tableExists()) {
            return in_array($slug, array_merge(['admin'], self::BUILTIN_SLUGS), true);
        }

        return false;
    }

    /** @return list<string> */
    public static function activeSlugsForCompany(int $companyId): array
    {
        return array_map(
            static fn(array $row): string => (string) $row['slug'],
            self::listForCompany($companyId, true)
        );
    }

    /** @return list<string> */
    public static function editableSlugsForActor(string $actorRole, ?int $companyId = null): array
    {
        $companyId = $companyId ?? (TenantService::isEnabled() ? TenantService::id() : 1);
        $slugs = self::activeSlugsForCompany($companyId);

        if ($actorRole === 'super_admin') {
            return $slugs;
        }

        if ($actorRole === 'admin') {
            return array_values(array_filter(
                $slugs,
                static fn(string $slug): bool => $slug !== 'admin'
            ));
        }

        return [];
    }

    /** @return list<string> */
    public static function assignableSlugsForActor(string $actorRole, ?int $companyId = null): array
    {
        $companyId = $companyId ?? (TenantService::isEnabled() ? TenantService::id() : 1);
        $editable = self::editableSlugsForActor($actorRole, $companyId);

        if ($actorRole === 'super_admin') {
            $admin = in_array('admin', self::activeSlugsForCompany($companyId), true) ? ['admin'] : [];

            return array_values(array_unique(array_merge($admin, $editable)));
        }

        return $editable;
    }

    public static function labelForSlug(string $slug, ?int $companyId = null): string
    {
        if ($slug === 'super_admin') {
            return 'Super Admin';
        }

        if ($slug === 'client') {
            return 'Client';
        }

        $companyId = $companyId ?? (TenantService::isEnabled() ? TenantService::id() : 1);

        foreach (self::listForCompany($companyId) as $row) {
            if (($row['slug'] ?? '') === $slug) {
                return (string) ($row['label'] ?? $slug);
            }
        }

        return ucwords(str_replace('_', ' ', $slug));
    }

    public static function descriptionForSlug(string $slug, ?int $companyId = null): string
    {
        $companyId = $companyId ?? (TenantService::isEnabled() ? TenantService::id() : 1);

        foreach (self::listForCompany($companyId) as $row) {
            if (($row['slug'] ?? '') === $slug) {
                $desc = trim((string) ($row['description'] ?? ''));

                return $desc !== '' ? $desc : self::builtinDescription($slug);
            }
        }

        return self::builtinDescription($slug);
    }

    public static function builtinDescription(string $slug): string
    {
        return self::BUILTIN_DESCRIPTIONS[$slug] ?? 'Custom role — set permissions on Role access.';
    }

    public static function isStaffRole(string $role, ?int $companyId = null): bool
    {
        $role = trim($role);
        if ($role === '' || $role === 'client') {
            return false;
        }

        if ($role === 'super_admin') {
            return true;
        }

        $companyId = $companyId ?? 0;

        return self::existsForCompany($companyId > 0 ? $companyId : (TenantService::isEnabled() ? TenantService::id() : 1), $role);
    }

    public static function isBuiltin(string $slug): bool
    {
        return in_array($slug, self::BUILTIN_SLUGS, true);
    }

    /**
     * @return array{success: bool, message?: string, slug?: string}
     */
    public static function create(int $companyId, string $label, string $copyFromSlug = 'staff', ?string $description = null): array
    {
        if (!self::tableExists()) {
            return ['success' => false, 'message' => 'Role storage is not installed. Run: php admin/sql/migrate_company_roles.php'];
        }

        if ($companyId <= 0) {
            return ['success' => false, 'message' => 'Invalid company.'];
        }

        $label = trim($label);
        if ($label === '') {
            return ['success' => false, 'message' => 'Role name is required.'];
        }

        $slug = self::uniqueSlugForCompany($companyId, self::slugify($label));
        $copyFromSlug = self::normalizeSlug($copyFromSlug);
        if (!self::existsForCompany($companyId, $copyFromSlug)) {
            $copyFromSlug = 'staff';
        }

        $sortRow = Database::fetch(
            'SELECT COALESCE(MAX(sort_order), 0) + 1 AS next_order FROM company_roles WHERE company_id = ?',
            [$companyId]
        );
        $sortOrder = (int) ($sortRow['next_order'] ?? 1);

        Database::insert(
            'INSERT INTO company_roles (company_id, slug, label, description, is_builtin, sort_order, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, 0, ?, "active", NOW(), NOW())',
            [
                $companyId,
                $slug,
                $label,
                $description !== null && trim($description) !== '' ? trim($description) : null,
                $sortOrder,
            ]
        );

        $sourceConfig = CompanyRoleAccessService::get($companyId, $copyFromSlug);
        CompanyRoleAccessService::save(
            $companyId,
            $slug,
            $sourceConfig['permissions'],
            $sourceConfig['assigned_cases_only'],
            $sourceConfig['read_only']
        );

        self::clearCache($companyId);
        CompanyRoleAccessService::clearCache($companyId);

        return ['success' => true, 'slug' => $slug];
    }

    /**
     * @return array{success: bool, message?: string}
     */
    public static function update(int $companyId, string $slug, string $label, ?string $description = null): array
    {
        if (!self::tableExists()) {
            return ['success' => false, 'message' => 'Role storage is not installed.'];
        }

        $slug = self::normalizeSlug($slug);
        $role = Database::fetch(
            'SELECT * FROM company_roles WHERE company_id = ? AND slug = ? LIMIT 1',
            [$companyId, $slug]
        );

        if (!$role) {
            return ['success' => false, 'message' => 'Role not found.'];
        }

        $label = trim($label);
        if ($label === '') {
            return ['success' => false, 'message' => 'Role name is required.'];
        }

        $descriptionValue = $description !== null && trim($description) !== '' ? trim($description) : null;

        Database::query(
            'UPDATE company_roles SET label = ?, description = ?, updated_at = NOW() WHERE company_id = ? AND slug = ?',
            [$label, $descriptionValue, $companyId, $slug]
        );

        self::clearCache($companyId);

        return ['success' => true];
    }

    /** @return array<string, int> */
    public static function userCountsForCompany(int $companyId): array
    {
        if ($companyId <= 0) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if (Database::columnExists('users', 'company_id')) {
            $where[] = 'company_id = ?';
            $params[] = $companyId;
        }

        $rows = Database::fetchAll(
            'SELECT role, COUNT(*) AS cnt FROM users WHERE ' . implode(' AND ', $where) . ' GROUP BY role',
            $params
        );

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) ($row['role'] ?? '')] = (int) ($row['cnt'] ?? 0);
        }

        return $counts;
    }

    public static function userCountForRole(int $companyId, string $slug): int
    {
        return self::userCountsForCompany($companyId)[self::normalizeSlug($slug)] ?? 0;
    }

    /**
     * @param list<string> $orderedSlugs
     * @return array{success: bool, message?: string}
     */
    public static function duplicate(int $companyId, string $sourceSlug): array
    {
        if (!self::tableExists()) {
            return ['success' => false, 'message' => 'Role storage is not installed.'];
        }

        $sourceSlug = self::normalizeSlug($sourceSlug);
        if (!self::existsForCompany($companyId, $sourceSlug)) {
            return ['success' => false, 'message' => 'Source role not found.'];
        }

        foreach (self::listForCompany($companyId) as $row) {
            if (($row['slug'] ?? '') === $sourceSlug) {
                $baseLabel = trim((string) ($row['label'] ?? 'Role')) . ' copy';
                $label = $baseLabel;
                $suffix = 2;

                while (self::labelExistsForCompany($companyId, $label)) {
                    $label = $baseLabel . ' ' . $suffix;
                    $suffix++;
                }

                $description = trim((string) ($row['description'] ?? ''));

                return self::create(
                    $companyId,
                    $label,
                    $sourceSlug,
                    $description !== '' ? $description : null
                );
            }
        }

        return ['success' => false, 'message' => 'Source role not found.'];
    }

    /**
     * @param list<string> $orderedSlugs Slugs visible to the actor (matrix column order)
     * @return array{success: bool, message?: string}
     */
    public static function moveRole(int $companyId, string $slug, string $direction, array $orderedSlugs): array
    {
        if (!self::tableExists()) {
            return ['success' => false, 'message' => 'Role storage is not installed.'];
        }

        $slug = self::normalizeSlug($slug);
        $direction = $direction === 'left' ? 'left' : 'right';
        $orderedSlugs = array_values(array_map(
            static fn(string $value): string => self::normalizeSlug($value),
            $orderedSlugs
        ));

        if (!in_array($slug, $orderedSlugs, true)) {
            return ['success' => false, 'message' => 'Invalid role.'];
        }

        $index = array_search($slug, $orderedSlugs, true);
        $targetIndex = $direction === 'left' ? $index - 1 : $index + 1;

        if ($targetIndex < 0 || $targetIndex >= count($orderedSlugs)) {
            return ['success' => false, 'message' => 'Cannot move role further.'];
        }

        $neighborSlug = $orderedSlugs[$targetIndex];

        $slugRow = Database::fetch(
            'SELECT sort_order FROM company_roles WHERE company_id = ? AND slug = ? LIMIT 1',
            [$companyId, $slug]
        );
        $neighborRow = Database::fetch(
            'SELECT sort_order FROM company_roles WHERE company_id = ? AND slug = ? LIMIT 1',
            [$companyId, $neighborSlug]
        );

        if (!$slugRow || !$neighborRow) {
            return ['success' => false, 'message' => 'Role not found.'];
        }

        $slugOrder = (int) ($slugRow['sort_order'] ?? 0);
        $neighborOrder = (int) ($neighborRow['sort_order'] ?? 0);

        Database::query(
            'UPDATE company_roles SET sort_order = ?, updated_at = NOW() WHERE company_id = ? AND slug = ?',
            [$neighborOrder, $companyId, $slug]
        );
        Database::query(
            'UPDATE company_roles SET sort_order = ?, updated_at = NOW() WHERE company_id = ? AND slug = ?',
            [$slugOrder, $companyId, $neighborSlug]
        );

        self::clearCache($companyId);

        return ['success' => true];
    }

    private static function labelExistsForCompany(int $companyId, string $label): bool
    {
        if (!self::tableExists()) {
            return false;
        }

        $row = Database::fetch(
            'SELECT id FROM company_roles WHERE company_id = ? AND label = ? LIMIT 1',
            [$companyId, $label]
        );

        return $row !== null;
    }

    /**
     * @return array{success: bool, message?: string}
     */
    public static function delete(int $companyId, string $slug): array
    {
        if (!self::tableExists()) {
            return ['success' => false, 'message' => 'Role storage is not installed.'];
        }

        $slug = self::normalizeSlug($slug);
        $role = Database::fetch(
            'SELECT * FROM company_roles WHERE company_id = ? AND slug = ? LIMIT 1',
            [$companyId, $slug]
        );

        if (!$role) {
            return ['success' => false, 'message' => 'Role not found.'];
        }

        if ((int) ($role['is_builtin'] ?? 0) === 1) {
            return ['success' => false, 'message' => 'Built-in roles cannot be deleted.'];
        }

        $countRow = Database::fetch(
            'SELECT COUNT(*) AS cnt FROM users WHERE company_id = ? AND role = ?',
            [$companyId, $slug]
        );
        $userCount = (int) ($countRow['cnt'] ?? 0);

        if ($userCount > 0) {
            return ['success' => false, 'message' => 'Cannot delete a role that is assigned to ' . $userCount . ' user(s). Reassign them first.'];
        }

        Database::query('DELETE FROM company_roles WHERE company_id = ? AND slug = ?', [$companyId, $slug]);

        if (CompanyRoleAccessService::tableExists()) {
            Database::query(
                'DELETE FROM company_role_permissions WHERE company_id = ? AND role = ?',
                [$companyId, $slug]
            );
        }

        self::clearCache($companyId);
        CompanyRoleAccessService::clearCache($companyId);

        return ['success' => true];
    }

    public static function seedCompany(int $companyId): void
    {
        if ($companyId <= 0 || !self::tableExists()) {
            return;
        }

        $builtins = [
            ['admin', 'Administrator', self::builtinDescription('admin'), 10],
            ['manager', 'Manager', self::builtinDescription('manager'), 20],
            ['staff', 'Staff', self::builtinDescription('staff'), 30],
            ['viewer', 'Viewer', self::builtinDescription('viewer'), 40],
        ];

        foreach ($builtins as [$slug, $label, $description, $sortOrder]) {
            $existing = Database::fetch(
                'SELECT id FROM company_roles WHERE company_id = ? AND slug = ? LIMIT 1',
                [$companyId, $slug]
            );

            if ($existing) {
                continue;
            }

            Database::insert(
                'INSERT INTO company_roles (company_id, slug, label, description, is_builtin, sort_order, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 1, ?, "active", NOW(), NOW())',
                [$companyId, $slug, $label, $description, $sortOrder]
            );
        }

        self::clearCache($companyId);
    }

    public static function seedAllCompanies(): void
    {
        if (!self::tableExists()) {
            self::seedCompany(1);

            return;
        }

        if (!TenantService::isEnabled()) {
            self::seedCompany(1);

            return;
        }

        foreach (Database::fetchAll('SELECT id FROM companies') as $row) {
            self::seedCompany((int) $row['id']);
        }
    }

    public static function normalizeSlug(string $slug): string
    {
        $slug = strtolower(trim($slug));
        $slug = preg_replace('/[^a-z0-9_-]+/', '_', $slug) ?? '';
        $slug = trim($slug, '_');

        return substr($slug, 0, 64);
    }

    private static function slugify(string $label): string
    {
        return self::normalizeSlug($label);
    }

    private static function uniqueSlugForCompany(int $companyId, string $baseSlug): string
    {
        if ($baseSlug === '' || in_array($baseSlug, self::RESERVED_SLUGS, true)) {
            $baseSlug = 'role';
        }

        $slug = $baseSlug;
        $suffix = 2;

        while (self::existsForCompany($companyId, $slug)) {
            $slug = $baseSlug . '_' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    /**
     * @return list<array{id: int, slug: string, label: string, description: string, is_builtin: bool, sort_order: int, status: string}>
     */
    private static function loadForCompany(int $companyId): array
    {
        if (!self::tableExists()) {
            $fallback = [];
            foreach (self::BUILTIN_SLUGS as $index => $slug) {
                $fallback[] = [
                    'id' => 0,
                    'slug' => $slug,
                    'label' => self::labelForSlug($slug, $companyId),
                    'description' => self::builtinDescription($slug),
                    'is_builtin' => true,
                    'sort_order' => ($index + 1) * 10,
                    'status' => 'active',
                ];
            }

            return $fallback;
        }

        self::seedCompany($companyId);

        $rows = Database::fetchAll(
            'SELECT id, slug, label, description, is_builtin, sort_order, status
             FROM company_roles
             WHERE company_id = ?
             ORDER BY sort_order ASC, label ASC',
            [$companyId]
        );

        $roles = [];
        foreach ($rows as $row) {
            $roles[] = [
                'id' => (int) ($row['id'] ?? 0),
                'slug' => (string) ($row['slug'] ?? ''),
                'label' => (string) ($row['label'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'is_builtin' => (int) ($row['is_builtin'] ?? 0) === 1,
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'status' => (string) ($row['status'] ?? 'active'),
            ];
        }

        return $roles;
    }
}
