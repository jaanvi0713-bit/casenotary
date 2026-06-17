<?php
/**
 * Adds settings columns used by Insights digest scheduling.
 * Run: php admin/sql/migrate_insights_digest_settings.php
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

echo "Insights digest settings migration...\n\n";

addColumn($pdo, 'company_settings', 'insights_digest_frequency', "VARCHAR(20) NOT NULL DEFAULT 'monthly' AFTER backup_frequency");
addColumn($pdo, 'company_settings', 'insights_digest_format', "VARCHAR(20) NOT NULL DEFAULT 'pdf' AFTER insights_digest_frequency");
addColumn($pdo, 'company_settings', 'insights_digest_recipients', "TEXT DEFAULT NULL AFTER insights_digest_format");

echo "\nMigration complete.\n";
