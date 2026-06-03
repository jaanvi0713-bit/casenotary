<?php

/**
 * Custom company role categories + VARCHAR users.role.
 * Run: php admin/sql/migrate_company_roles.php
 */

require_once __DIR__ . '/../core/bootstrap.php';

try {
    Database::query(
        'CREATE TABLE IF NOT EXISTS company_roles (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            company_id INT UNSIGNED NOT NULL,
            slug VARCHAR(64) NOT NULL,
            label VARCHAR(120) NOT NULL,
            description VARCHAR(500) DEFAULT NULL,
            is_builtin TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            status VARCHAR(16) NOT NULL DEFAULT "active",
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_company_role_slug (company_id, slug),
            KEY idx_company_roles_company (company_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    echo "[OK] Created company_roles table\n";
} catch (Throwable $e) {
    echo "[SKIP] company_roles: {$e->getMessage()}\n";
}

try {
    Database::query(
        "ALTER TABLE users MODIFY role VARCHAR(64) NOT NULL DEFAULT 'admin'"
    );
    echo "[OK] users.role is VARCHAR(64)\n";
} catch (Throwable $e) {
    echo "[SKIP] users.role VARCHAR: {$e->getMessage()}\n";
}

if (CompanyRoleService::tableExists()) {
    CompanyRoleService::seedAllCompanies();
    echo "[OK] Seeded company role categories\n";
}

if (CompanyRoleAccessService::tableExists()) {
    CompanyRoleAccessService::seedAllCompanies();
    echo "[OK] Synced company role permissions\n";
}

echo "\nMigration complete.\n";
