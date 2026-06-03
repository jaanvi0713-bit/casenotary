<?php
/**
 * Drop legacy global UNIQUE indexes on document numbers after multi-company migration.
 * Keeps per-company unique keys (company_id, number).
 *
 * Run: php admin/sql/migrate_fix_global_number_indexes.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/Database.php';

$pdo = Database::getInstance();

function indexExists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $stmt->execute([$table, $index]);

    return (int) $stmt->fetchColumn() > 0;
}

function dropIndexIfExists(PDO $pdo, string $table, string $index): void
{
    if (!indexExists($pdo, $table, $index)) {
        return;
    }

    try {
        $pdo->exec("ALTER TABLE {$table} DROP INDEX `{$index}`");
        echo "[OK] Dropped {$table}.{$index}\n";
    } catch (Throwable $e) {
        echo "[FAIL] {$table}.{$index}: {$e->getMessage()}\n";
    }
}

/** @var array<string, list<string>> */
$legacyIndexes = [
    'cases'      => ['case_number', 'cases_case_number_unique'],
    'invoices'   => ['invoice_number', 'invoices_invoice_number_unique'],
    'quotations' => ['quotation_number', 'quotations_quotation_number_unique'],
    'proposals'  => ['proposal_number', 'proposals_proposal_number_unique'],
    'receipts'   => ['receipt_number', 'receipts_receipt_number_unique'],
    'payments'   => ['payment_number', 'payments_payment_number_unique'],
];

echo "Fixing legacy global number indexes...\n\n";

foreach ($legacyIndexes as $table => $indexes) {
    $exists = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table))->rowCount() > 0;
    if (!$exists) {
        echo "[SKIP] Table {$table} not found\n";
        continue;
    }

    foreach ($indexes as $index) {
        dropIndexIfExists($pdo, $table, $index);
    }
}

echo "\nDone.\n";
