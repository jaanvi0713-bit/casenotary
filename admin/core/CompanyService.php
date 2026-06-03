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

    private static function uniqueSlug(string $name): string
    {
        $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $name) ?? '', '-'));
        if ($base === '') {
            $base = 'company';
        }

        $slug = $base;
        $i    = 2;
        while (Database::fetch('SELECT id FROM companies WHERE slug = ?', [$slug])) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }
}
