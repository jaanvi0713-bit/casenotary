<?php
/**
 * Structured company address columns on company_settings.
 * Run: php admin/sql/migrate_company_address.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/Database.php';

$pdo = Database::getInstance();

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);

    return (int) $stmt->fetchColumn() > 0;
}

function addColumn(PDO $pdo, string $table, string $column, string $definition): void
{
    if (columnExists($pdo, $table, $column)) {
        echo "[OK] {$table}.{$column} already exists\n";
        return;
    }

    $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    echo "[OK] Added {$table}.{$column}\n";
}

echo "Company address migration...\n\n";

addColumn($pdo, 'company_settings', 'city', 'VARCHAR(100) DEFAULT NULL AFTER address');
addColumn($pdo, 'company_settings', 'state', 'VARCHAR(100) DEFAULT NULL AFTER city');
addColumn($pdo, 'company_settings', 'zip_code', 'VARCHAR(20) DEFAULT NULL AFTER state');
addColumn($pdo, 'company_settings', 'country', 'VARCHAR(100) DEFAULT NULL AFTER zip_code');

echo "\nMigration complete.\n";
