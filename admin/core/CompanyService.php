<?php

declare(strict_types=1);

class CompanyService
{
    /** @return list<array<string, mixed>> */
    public static function listAll(): array
    {
        if (!TenantService::isEnabled()) {
            return [];
        }

        return Database::fetchAll(
            'SELECT c.*,
                    cs.company_name AS brand_name,
                    (SELECT COUNT(*) FROM clients cl WHERE cl.company_id = c.id) AS client_count,
                    (SELECT COUNT(*) FROM cases cs2 WHERE cs2.company_id = c.id) AS case_count
             FROM companies c
             LEFT JOIN company_settings cs ON cs.company_id = c.id
             ORDER BY c.name ASC'
        );
    }

    public static function getById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        return Database::fetch('SELECT * FROM companies WHERE id = ?', [$id]) ?: null;
    }

    public static function create(string $name): int
    {
        if (!TenantService::isEnabled()) {
            throw new RuntimeException('Multi-company mode is not enabled. Run the migration first.');
        }

        $name = trim($name);
        if ($name === '') {
            throw new RuntimeException('Company name is required.');
        }

        $slug = self::uniqueSlug($name);
        $companyId = Database::insert(
            'INSERT INTO companies (name, slug, status, created_at, updated_at) VALUES (?, ?, "active", NOW(), NOW())',
            [$name, $slug]
        );

        $existingSettings = Database::fetch('SELECT id FROM company_settings WHERE company_id = ?', [$companyId]);
        if (!$existingSettings) {
            Database::insert(
                'INSERT INTO company_settings (company_id, company_name, primary_color, secondary_color, dark_accent, font_family)
                 VALUES (?, ?, "#3aafa9", "#00182c", "#000000", "Montserrat")',
                [$companyId, $name]
            );
        }

        CompanyRoleService::seedCompany($companyId);
        CompanyRoleAccessService::seedCompany($companyId);

        return $companyId;
    }

    public static function syncDisplayName(int $companyId, string $name): void
    {
        if (!TenantService::isEnabled() || $companyId <= 0) {
            return;
        }

        $name = trim($name);
        if ($name === '') {
            return;
        }

        Database::query(
            'UPDATE companies SET name = ?, updated_at = NOW() WHERE id = ?',
            [$name, $companyId]
        );
    }

    public static function normalizeSlug(string $value): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $value) ?? '', '-'));

        return $slug !== '' ? $slug : 'company';
    }

    public static function slugAvailable(string $slug, ?int $excludeCompanyId = null): bool
    {
        if ($slug === '') {
            return false;
        }

        $params = [$slug];
        $sql    = 'SELECT id FROM companies WHERE slug = ?';

        if ($excludeCompanyId !== null && $excludeCompanyId > 0) {
            $sql     .= ' AND id != ?';
            $params[] = $excludeCompanyId;
        }

        return !Database::fetch($sql, $params);
    }

    public static function updateSlug(int $companyId, string $slug): void
    {
        if (!TenantService::isEnabled() || $companyId <= 0) {
            return;
        }

        $slug = self::normalizeSlug($slug);
        if ($slug === '') {
            throw new RuntimeException('Workspace ID is required.');
        }

        if (!self::slugAvailable($slug, $companyId)) {
            throw new RuntimeException('That workspace ID is already used by another company.');
        }

        Database::query(
            'UPDATE companies SET slug = ?, updated_at = NOW() WHERE id = ?',
            [$slug, $companyId]
        );
    }

    public static function currentSlug(int $companyId): string
    {
        $company = self::getById($companyId);

        return trim((string) ($company['slug'] ?? ''));
    }

    public static function countAll(): int
    {
        if (!TenantService::isEnabled()) {
            return 0;
        }

        return (int) (Database::fetch('SELECT COUNT(*) AS c FROM companies')['c'] ?? 0);
    }

    /** @return array{clients: int, cases: int, staff: int} */
    public static function usageCounts(int $companyId): array
    {
        if ($companyId <= 0) {
            return ['clients' => 0, 'cases' => 0, 'staff' => 0];
        }

        return [
            'clients' => (int) (Database::fetch('SELECT COUNT(*) AS c FROM clients WHERE company_id = ?', [$companyId])['c'] ?? 0),
            'cases'   => (int) (Database::fetch('SELECT COUNT(*) AS c FROM cases WHERE company_id = ?', [$companyId])['c'] ?? 0),
            'staff'   => (int) (Database::fetch(
                'SELECT COUNT(*) AS c FROM users WHERE company_id = ? AND role NOT IN ("super_admin", "client")',
                [$companyId]
            )['c'] ?? 0),
        ];
    }

    public static function canDelete(int $companyId): bool
    {
        return $companyId > 0 && self::countAll() > 1;
    }

    /** @return non-empty-string */
    public static function deleteConfirmMessage(int $companyId, string $displayName): string
    {
        $usage = self::usageCounts($companyId);
        $parts = [];

        if ($usage['clients'] > 0) {
            $parts[] = $usage['clients'] . ' client' . ($usage['clients'] === 1 ? '' : 's');
        }
        if ($usage['cases'] > 0) {
            $parts[] = $usage['cases'] . ' case' . ($usage['cases'] === 1 ? '' : 's');
        }
        if ($usage['staff'] > 0) {
            $parts[] = $usage['staff'] . ' staff user' . ($usage['staff'] === 1 ? '' : 's');
        }

        $message = 'You are about to delete ' . $displayName . '.';
        if ($parts !== []) {
            $message .= ' This will remove ' . implode(', ', $parts) . ', plus all related records.';
        } else {
            $message .= ' This will remove all company settings and workspace data.';
        }

        return $message . ' This cannot be undone.';
    }

    public static function delete(int $companyId): void
    {
        self::assertManageable($companyId);

        if (!self::canDelete($companyId)) {
            throw new RuntimeException('You cannot delete the only company workspace.');
        }

        $restoreId = TenantService::id();

        $_SESSION['company_id'] = $companyId;
        SettingsService::clearCache();
        CompanyRoleAccessService::clearCache();
        CompanyRoleService::clearCache();

        self::purgeCompanyData($companyId);
        self::purgeCompanyRecords($companyId);

        if ($restoreId === $companyId) {
            self::switchToAnotherActiveCompany($companyId);
        } elseif (self::getById($restoreId)) {
            TenantService::set($restoreId);
        } else {
            self::switchToAnotherActiveCompany($companyId);
        }
    }

    private static function assertManageable(int $companyId): void
    {
        if (!TenantService::isEnabled()) {
            throw new RuntimeException('Multi-company mode is not enabled.');
        }

        if ($companyId <= 0 || !self::getById($companyId)) {
            throw new RuntimeException('Company not found.');
        }
    }

    private static function switchToAnotherActiveCompany(int $excludeCompanyId): void
    {
        $next = Database::fetch(
            'SELECT id FROM companies WHERE id != ? ORDER BY name ASC LIMIT 1',
            [$excludeCompanyId]
        );

        if (!$next) {
            throw new RuntimeException('No other company workspace is available to switch to.');
        }

        TenantService::set((int) $next['id']);
    }

    private static function purgeCompanyData(int $companyId): void
    {
        $uploadsRoot = dirname(__DIR__) . '/uploads';

        foreach (Database::fetchAll('SELECT id FROM cases WHERE company_id = ?', [$companyId]) as $row) {
            $caseId = (int) $row['id'];

            try {
                CaseService::deleteCase($caseId);
            } catch (Throwable $e) {
                // Continue removing the company even if one case fails.
            }

            self::removeDirectory($uploadsRoot . '/cases/' . $caseId);
        }

        foreach (Database::fetchAll('SELECT id FROM clients WHERE company_id = ?', [$companyId]) as $row) {
            try {
                ClientService::deleteClient((int) $row['id']);
            } catch (Throwable $e) {
                // Continue removing the company even if one client fails.
            }
        }

        if (Database::tableExists('client_contact_threads') && Database::columnExists('client_contact_threads', 'company_id')) {
            foreach (Database::fetchAll('SELECT id FROM client_contact_threads WHERE company_id = ?', [$companyId]) as $row) {
                try {
                    ClientMessageService::deleteThread((int) $row['id']);
                } catch (Throwable $e) {
                    // Best effort.
                }
            }
        }

        Database::query(
            'DELETE FROM users WHERE company_id = ? AND role NOT IN ("super_admin", "client")',
            [$companyId]
        );

        if (Database::tableExists('notifications') && Database::columnExists('notifications', 'company_id')) {
            Database::query('DELETE FROM notifications WHERE company_id = ?', [$companyId]);
        }

        if (Database::tableExists('client_intake_submissions') && Database::columnExists('client_intake_submissions', 'company_id')) {
            Database::query('DELETE FROM client_intake_submissions WHERE company_id = ?', [$companyId]);
        }

        self::removeDirectory(dirname(__DIR__) . '/storage/backups/company_' . $companyId);
    }

    private static function purgeCompanyRecords(int $companyId): void
    {
        Database::query('DELETE FROM company_role_permissions WHERE company_id = ?', [$companyId]);

        if (CompanyRoleService::tableExists()) {
            Database::query('DELETE FROM company_roles WHERE company_id = ?', [$companyId]);
        }

        Database::query('DELETE FROM company_settings WHERE company_id = ?', [$companyId]);

        if (Database::tableExists('chatbot_conversations') && Database::columnExists('chatbot_conversations', 'company_id')) {
            Database::query('DELETE FROM chatbot_conversations WHERE company_id = ?', [$companyId]);
        }

        Database::query('DELETE FROM companies WHERE id = ?', [$companyId]);

        $uploadRoot = dirname(__DIR__) . '/uploads/company_' . $companyId;
        if (is_dir($uploadRoot)) {
            self::removeDirectory($uploadRoot);
        }
    }

    private static function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($fullPath)) {
                self::removeDirectory($fullPath);
            } elseif (is_file($fullPath)) {
                @unlink($fullPath);
            }
        }

        @rmdir($path);
    }

    private static function uniqueSlug(string $name): string
    {
        $base = self::normalizeSlug($name);
        $slug = $base;
        $i    = 2;

        while (!self::slugAvailable($slug)) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }
}
