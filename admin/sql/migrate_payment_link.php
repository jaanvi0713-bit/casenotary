<?php
/**
 * Stripe Payment Link URL on invoices.
 * Run: php admin/sql/migrate_payment_link.php
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

echo "Payment link migration...\n\n";

$after = columnExists($pdo, 'invoices', 'bank_account')
    ? 'AFTER bank_account'
    : (columnExists($pdo, 'invoices', 'payment_instructions') ? 'AFTER payment_instructions' : '');

addColumn($pdo, 'invoices', 'payment_link', "VARCHAR(2000) DEFAULT NULL {$after}");

echo "\nMigration complete.\n";
