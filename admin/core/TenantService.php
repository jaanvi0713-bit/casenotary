<?php

declare(strict_types=1);

class TenantService
{
    private static ?bool $enabled = null;

    public static function isEnabled(): bool
    {
        if (self::$enabled === null) {
            self::$enabled = Database::columnExists('clients', 'company_id')
                && Database::tableExists('companies');
        }

        return self::$enabled;
    }

    public static function id(): int
    {
        if (!self::isEnabled()) {
            return 1;
        }

        if (!empty($_SESSION['company_id'])) {
            return (int) $_SESSION['company_id'];
        }

        return 1;
    }

    public static function set(int $companyId): void
    {
        if (!self::isEnabled()) {
            $_SESSION['company_id'] = 1;
            return;
        }

        if (!Auth::isSuperAdmin()) {
            throw new RuntimeException('Only super admins can switch companies.');
        }

        if (!self::exists($companyId)) {
            throw new RuntimeException('Company not found.');
        }

        $_SESSION['company_id'] = $companyId;
        SettingsService::clearCache();
        CompanyRoleAccessService::clearCache();
        CompanyRoleService::clearCache();
        unset(
            $_SESSION['chatbot_last_topic'],
            $_SESSION['chatbot_last_entity'],
            $_SESSION['chatbot_appointment_pending']
        );
    }

    public static function resolveOnLogin(array $user): void
    {
        if (!self::isEnabled()) {
            $_SESSION['company_id'] = 1;
            return;
        }

        $role = (string) ($user['role'] ?? '');

        if ($role === 'super_admin') {
            $existing = (int) ($_SESSION['company_id'] ?? 0);
            if ($existing > 0 && self::exists($existing)) {
                return;
            }

            $fromUser = (int) ($user['company_id'] ?? 0);
            $_SESSION['company_id'] = $fromUser > 0 && self::exists($fromUser) ? $fromUser : self::defaultCompanyId();
            return;
        }

        if ($role === 'client') {
            $companyId = self::companyIdForClientUser((int) ($user['id'] ?? 0), (string) ($user['email'] ?? ''));
            $_SESSION['company_id'] = $companyId > 0 ? $companyId : self::defaultCompanyId();
            return;
        }

        $companyId = (int) ($user['company_id'] ?? 0);
        if ($companyId <= 0 || !self::exists($companyId)) {
            throw new RuntimeException('Your admin account is not assigned to a company. Contact support.');
        }

        $_SESSION['company_id'] = $companyId;
    }

    public static function assertSameCompany(?int $companyId): void
    {
        if (!self::isEnabled() || $companyId === null) {
            return;
        }

        if ((int) $companyId !== self::id()) {
            throw new RuntimeException('You do not have access to this company\'s data.');
        }
    }

    /** @return list<array{id:int,name:string,slug:string}> */
    public static function listActive(): array
    {
        if (!self::isEnabled()) {
            return [];
        }

        return Database::fetchAll(
            'SELECT c.id, COALESCE(NULLIF(TRIM(cs.company_name), ""), c.name) AS name, c.slug
             FROM companies c
             LEFT JOIN company_settings cs ON cs.company_id = c.id
             WHERE c.status = "active"
             ORDER BY name ASC'
        );
    }

    public static function name(?int $companyId = null): string
    {
        $companyId = $companyId ?? self::id();

        if (!self::isEnabled()) {
            return 'Company';
        }

        $row = Database::fetch(
            'SELECT COALESCE(NULLIF(TRIM(cs.company_name), ""), c.name) AS name
             FROM companies c
             LEFT JOIN company_settings cs ON cs.company_id = c.id
             WHERE c.id = ?',
            [$companyId]
        );

        return trim((string) ($row['name'] ?? 'Company')) ?: 'Company';
    }

    public static function exists(int $companyId): bool
    {
        if ($companyId <= 0 || !self::isEnabled()) {
            return false;
        }

        return (bool) Database::fetch('SELECT id FROM companies WHERE id = ? AND status = "active"', [$companyId]);
    }

    public static function defaultCompanyId(): int
    {
        if (!self::isEnabled()) {
            return 1;
        }

        $id = (int) (Database::fetch('SELECT id FROM companies ORDER BY id ASC LIMIT 1')['id'] ?? 0);

        return $id > 0 ? $id : 1;
    }

    public static function resolveLoginCompanyFromRequest(): int
    {
        if (!self::isEnabled()) {
            return 0;
        }

        $slug = trim((string) ($_POST['company'] ?? $_GET['company'] ?? ''));
        if ($slug === '') {
            return 0;
        }

        return self::idFromSlug($slug);
    }

    public static function slug(int $companyId): string
    {
        if ($companyId <= 0 || !self::isEnabled()) {
            return '';
        }

        $row = Database::fetch(
            'SELECT slug FROM companies WHERE id = ? AND status = "active" LIMIT 1',
            [$companyId]
        );

        return trim((string) ($row['slug'] ?? ''));
    }

    public static function idFromSlug(string $slug): int
    {
        $slug = trim($slug);
        if ($slug === '' || !self::isEnabled()) {
            return 0;
        }

        $row = Database::fetch(
            'SELECT id FROM companies WHERE slug = ? AND status = "active" LIMIT 1',
            [$slug]
        );

        return $row ? (int) $row['id'] : 0;
    }

    public static function scopeClause(string $alias = '', string $column = 'company_id'): string
    {
        if (!self::isEnabled()) {
            return '1=1';
        }

        $col = ($alias !== '' ? rtrim($alias, '.') . '.' : '') . $column;

        return $col . ' = ' . self::id();
    }

    /** @param list<mixed> $params */
    public static function appendClientScope(array &$where, array &$params, string $alias = 'cl'): void
    {
        self::appendScope($where, $params, $alias, 'company_id');
    }

    public static function hasNotificationScope(): bool
    {
        return self::isEnabled() && Database::columnExists('notifications', 'company_id');
    }

    public static function hasChatScope(): bool
    {
        return self::isEnabled() && Database::columnExists('chatbot_conversations', 'company_id');
    }

    /** @param list<mixed> $params */
    public static function appendNotificationScope(array &$where, array &$params, string $alias = ''): void
    {
        if (!self::hasNotificationScope()) {
            return;
        }

        $col = ($alias !== '' ? rtrim($alias, '.') . '.' : '') . 'company_id';
        $where[] = "{$col} = ?";
        $params[] = self::id();
    }

    /** @return list<int> */
    public static function adminNotifierUserIds(int $companyId): array
    {
        if ($companyId <= 0) {
            return [];
        }

        $ids = [];

        foreach (Database::fetchAll(
            "SELECT id FROM users WHERE status = 'active' AND role = 'admin' AND company_id = ?",
            [$companyId]
        ) as $row) {
            $ids[] = (int) $row['id'];
        }

        foreach (Database::fetchAll(
            "SELECT id FROM users WHERE status = 'active' AND role = 'super_admin'"
        ) as $row) {
            $ids[] = (int) $row['id'];
        }

        return array_values(array_unique($ids));
    }

    /** @param list<mixed> $params */
    public static function appendScope(array &$where, array &$params, string $alias = '', string $column = 'company_id'): void
    {
        if (!self::isEnabled()) {
            return;
        }

        $col = ($alias !== '' ? rtrim($alias, '.') . '.' : '') . $column;
        $where[] = "{$col} = ?";
        $params[] = self::id();
    }

    private static function companyIdForClientUser(int $userId, string $email): int
    {
        if ($userId > 0) {
            $row = Database::fetch('SELECT company_id FROM clients WHERE user_id = ? LIMIT 1', [$userId]);
            if (!empty($row['company_id'])) {
                return (int) $row['company_id'];
            }
        }

        $email = trim($email);
        if ($email !== '') {
            $row = Database::fetch('SELECT company_id FROM clients WHERE email = ? LIMIT 1', [$email]);
            if (!empty($row['company_id'])) {
                return (int) $row['company_id'];
            }
        }

        return 0;
    }
}
