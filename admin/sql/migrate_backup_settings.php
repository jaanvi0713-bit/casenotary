<?php
/**
 * Backup schedule columns on company_settings.
 * Run: php admin/sql/migrate_backup_settings.php
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

echo "Backup settings migration...\n\n";

$after = columnExists($pdo, 'company_settings', 'stripe_secret_key')
    ? 'AFTER stripe_secret_key'
    : '';

addColumn($pdo, 'company_settings', 'backup_frequency', "VARCHAR(20) NOT NULL DEFAULT 'never' {$after}");
addColumn($pdo, 'company_settings', 'last_backup_at', 'DATETIME DEFAULT NULL AFTER backup_frequency');

echo "\nMigration complete.\n";
