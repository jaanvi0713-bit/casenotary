<?php
/**
 * Invoice payment gateway fields (prototype + future live gateways).
 * Run: php admin/sql/migrate_invoice_payment_gateway.php
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

function enumHasValue(PDO $pdo, string $table, string $column, string $value): bool
{
    $stmt = $pdo->prepare(
        'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    $type = (string) $stmt->fetchColumn();

    return stripos($type, "'" . $value . "'") !== false;
}

function ensureFailedStatus(PDO $pdo, string $column): void
{
    if (!columnExists($pdo, 'invoices', $column)) {
        echo "[SKIP] invoices.{$column} not present\n";
        return;
    }

    if (enumHasValue($pdo, 'invoices', $column, 'failed')) {
        echo "[OK] invoices.{$column} already includes failed\n";
        return;
    }

    $stmt = $pdo->prepare(
        'SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute(['invoices', $column]);
    $type = (string) $stmt->fetchColumn();

    if (stripos($type, 'enum(') !== 0) {
        echo "[SKIP] invoices.{$column} is not ENUM ({$type})\n";
        return;
    }

    $newType = rtrim($type, ')') . ",'failed')";
    $pdo->exec("ALTER TABLE invoices MODIFY COLUMN `{$column}` {$newType} NOT NULL DEFAULT 'pending'");
    echo "[OK] Added failed to invoices.{$column}\n";
}

echo "Invoice payment gateway migration...\n\n";

if (!columnExists($pdo, 'invoices', 'payment_link')) {
    addColumn($pdo, 'invoices', 'payment_link', 'VARCHAR(2000) DEFAULT NULL');
}

addColumn($pdo, 'invoices', 'payment_token', 'VARCHAR(64) DEFAULT NULL');
addColumn($pdo, 'invoices', 'payment_date', 'DATETIME DEFAULT NULL');
addColumn($pdo, 'invoices', 'transaction_reference', 'VARCHAR(120) DEFAULT NULL');

if (columnExists($pdo, 'invoices', 'payment_token')) {
    try {
        $pdo->exec('ALTER TABLE invoices ADD UNIQUE INDEX idx_invoices_payment_token (payment_token)');
        echo "[OK] Added unique index on payment_token\n";
    } catch (Throwable $e) {
        echo "[OK] payment_token index may already exist\n";
    }
}

ensureFailedStatus($pdo, 'status');
ensureFailedStatus($pdo, 'payment_status');

require_once __DIR__ . '/../core/bootstrap.php';
$fixed = PaymentGatewayService::repairPaymentLinks();
echo "[OK] Repaired {$fixed} invoice payment link(s)\n";

echo "\nMigration complete.\n";
