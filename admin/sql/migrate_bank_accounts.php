<?php
/**
 * Multiple bank accounts on company_settings + per-invoice selection.
 * Run: php admin/sql/migrate_bank_accounts.php
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

echo "Bank accounts migration...\n\n";

addColumn($pdo, 'company_settings', 'bank_account_1', 'TEXT DEFAULT NULL AFTER bank_bic');
addColumn($pdo, 'company_settings', 'bank_account_2', 'TEXT DEFAULT NULL AFTER bank_account_1');
addColumn($pdo, 'company_settings', 'bank_account_3', 'TEXT DEFAULT NULL AFTER bank_account_2');
addColumn($pdo, 'company_settings', 'invoice_bank_account', 'TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER bank_account_3');

$invoiceAfter = columnExists($pdo, 'invoices', 'payment_instructions')
    ? 'AFTER payment_instructions'
    : (columnExists($pdo, 'invoices', 'notes') ? 'AFTER notes' : '');
addColumn($pdo, 'invoices', 'bank_account', "TINYINT UNSIGNED DEFAULT NULL {$invoiceAfter}");

$rows = $pdo->query('SELECT id, bank_account_number, bank_sort_code, bank_iban, bank_bic, bank_account_1 FROM company_settings')->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
  if (trim((string) ($row['bank_account_1'] ?? '')) !== '') {
      continue;
  }

    $lines = [];
    foreach (['bank_account_number' => 'Account number', 'bank_sort_code' => 'Sort code', 'bank_iban' => 'IBAN', 'bank_bic' => 'BIC'] as $col => $label) {
        $value = trim((string) ($row[$col] ?? ''));
        if ($value !== '') {
            $lines[] = "{$label}: {$value}";
        }
    }

    if ($lines === []) {
        continue;
    }

    $stmt = $pdo->prepare('UPDATE company_settings SET bank_account_1 = ? WHERE id = ?');
    $stmt->execute([implode("\n", $lines), $row['id']]);
    echo "[OK] Migrated legacy bank details to bank_account_1 for settings #{$row['id']}\n";
}

echo "\nMigration complete.\n";
