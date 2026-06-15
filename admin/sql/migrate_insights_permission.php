<?php

/**
 * Add Insights permission to existing company role configs.
 * Run: php admin/sql/migrate_insights_permission.php
 */

require_once __DIR__ . '/../core/bootstrap.php';

if (!CompanyRoleAccessService::tableExists()) {
    echo "[SKIP] company_role_permissions table not found. Run migrate_company_role_access.php first.\n";
    exit(0);
}

$insights = RoleAccess::PERMISSION_INSIGHTS;
$grantRoles = ['admin', 'manager'];
$updated = 0;

$rows = Database::fetchAll('SELECT company_id, role, permissions FROM company_role_permissions');

foreach ($rows as $row) {
    $role = (string) ($row['role'] ?? '');
    if (!in_array($role, $grantRoles, true)) {
        continue;
    }

    $decoded = json_decode((string) ($row['permissions'] ?? '[]'), true);
    if (!is_array($decoded)) {
        continue;
    }

    if (in_array($insights, $decoded, true) || in_array('*', $decoded, true)) {
        continue;
    }

    $decoded[] = $insights;
    sort($decoded);

    $json = json_encode(array_values($decoded), JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        continue;
    }

    Database::query(
        'UPDATE company_role_permissions SET permissions = ?, updated_at = NOW() WHERE company_id = ? AND role = ?',
        [$json, (int) $row['company_id'], $role]
    );
    $updated++;
}

CompanyRoleAccessService::clearCache();

echo "[OK] Added Insights permission to {$updated} role config(s) (admin & manager).\n";
echo "Migration complete.\n";
