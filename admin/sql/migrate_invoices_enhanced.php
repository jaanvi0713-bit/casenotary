<?php
/**
 * Adds enhanced invoice columns (VAT toggle, line items, payment settings).
 * Run: php admin/sql/migrate_invoices_enhanced.php
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

echo "Invoice enhancement migration...\n\n";

addColumn($pdo, 'invoices', 'line_items', 'JSON DEFAULT NULL AFTER amount');
addColumn($pdo, 'invoices', 'subtotal', 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER line_items');
addColumn($pdo, 'invoices', 'vat_enabled', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER subtotal');
addColumn($pdo, 'invoices', 'payment_terms', 'TEXT DEFAULT NULL AFTER notes');
addColumn($pdo, 'invoices', 'payment_instructions', 'TEXT DEFAULT NULL AFTER payment_terms');

echo "\nMigration complete.\n";

