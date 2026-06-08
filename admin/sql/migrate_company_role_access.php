<?php

/**
 * Per-company role access permissions.
 * Run: php admin/sql/migrate_company_role_access.php
 */

require_once __DIR__ . '/../core/bootstrap.php';

try {
    Database::query(
        'CREATE TABLE IF NOT EXISTS company_role_permissions (
            company_id INT UNSIGNED NOT NULL,
            role VARCHAR(32) NOT NULL,
            permissions JSON NOT NULL,
            assigned_cases_only TINYINT(1) NOT NULL DEFAULT 0,
            read_only TINYINT(1) NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (company_id, role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    echo "[OK] Created company_role_permissions table\n";
} catch (Throwable $e) {
    echo "[SKIP] company_role_permissions: {$e->getMessage()}\n";
}

CompanyRoleAccessService::seedAllCompanies();
echo "[OK] Seeded default role permissions for all companies\n";

echo "\nMigration complete.\n";
