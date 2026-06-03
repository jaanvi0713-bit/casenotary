<?php
/**
 * Invoice / bank details on company_settings.
 * Run: php admin/sql/migrate_invoice_settings.php
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

echo "Invoice settings migration...\n\n";

addColumn($pdo, 'company_settings', 'invoice_payable_name', 'VARCHAR(255) DEFAULT NULL AFTER tax_vat_number');
addColumn($pdo, 'company_settings', 'bank_account_number', 'VARCHAR(64) DEFAULT NULL AFTER invoice_payable_name');
addColumn($pdo, 'company_settings', 'bank_sort_code', 'VARCHAR(32) DEFAULT NULL AFTER bank_account_number');
addColumn($pdo, 'company_settings', 'bank_iban', 'VARCHAR(64) DEFAULT NULL AFTER bank_sort_code');
addColumn($pdo, 'company_settings', 'bank_bic', 'VARCHAR(32) DEFAULT NULL AFTER bank_iban');
addColumn($pdo, 'company_settings', 'default_invoice_payment_terms', 'VARCHAR(255) DEFAULT NULL AFTER bank_bic');

echo "\nMigration complete.\n";
