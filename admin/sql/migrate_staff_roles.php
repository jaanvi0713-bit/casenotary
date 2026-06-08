<?php

/**
 * Adds manager and staff roles for role-based page access.
 * Run: php admin/sql/migrate_staff_roles.php
 */

require_once __DIR__ . '/../core/bootstrap.php';

try {
    Database::query("ALTER TABLE users MODIFY role ENUM('admin','super_admin','manager','staff','viewer','client') NOT NULL DEFAULT 'admin'");
    echo "[OK] Extended users.role enum with manager, staff, and viewer\n";
} catch (Throwable $e) {
    echo "[SKIP] users.role enum: {$e->getMessage()}\n";
}

echo "\nMigration complete.\n";
