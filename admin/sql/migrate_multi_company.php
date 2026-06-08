<?php
/**
 * Multi-company tenant migration.
 * Run: php admin/sql/migrate_multi_company.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/Database.php';

$pdo = Database::getInstance();

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);

    return (int) $stmt->fetchColumn() > 0;
}

function indexExists(PDO $pdo, string $table, string $index): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $stmt->execute([$table, $index]);

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

function dropIndexIfExists(PDO $pdo, string $table, string $index): void
{
    if (!indexExists($pdo, $table, $index)) {
        return;
    }

    try {
        $pdo->exec("ALTER TABLE {$table} DROP INDEX {$index}");
        echo "[OK] Dropped index {$table}.{$index}\n";
    } catch (Throwable $e) {
        echo "[SKIP] Could not drop index {$table}.{$index}: {$e->getMessage()}\n";
    }
}

echo "Multi-company migration...\n\n";

if ($pdo->query("SHOW TABLES LIKE 'companies'")->rowCount() === 0) {
    $pdo->exec(
        "CREATE TABLE companies (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(100) NOT NULL,
            status ENUM('active','inactive') NOT NULL DEFAULT 'active',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_companies_slug (slug)
        ) ENGINE=InnoDB"
    );
    echo "[OK] Created companies table\n";
} else {
    echo "[OK] companies table already exists\n";
}

$defaultName = 'Default Company';
$settingsRow = $pdo->query('SELECT company_name FROM company_settings ORDER BY id ASC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if ($settingsRow && trim((string) ($settingsRow['company_name'] ?? '')) !== '') {
    $defaultName = trim((string) $settingsRow['company_name']);
}

$companyId = (int) ($pdo->query('SELECT id FROM companies ORDER BY id ASC LIMIT 1')->fetchColumn() ?: 0);
if ($companyId <= 0) {
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $defaultName) ?: 'default-company');
    $slug = trim($slug, '-') ?: 'default-company';
    $stmt = $pdo->prepare('INSERT INTO companies (name, slug, status) VALUES (?, ?, "active")');
    $stmt->execute([$defaultName, $slug]);
    $companyId = (int) $pdo->lastInsertId();
    echo "[OK] Created default company #{$companyId}\n";
} else {
    echo "[OK] Using existing company #{$companyId}\n";
}

addColumn($pdo, 'company_settings', 'company_id', "INT UNSIGNED DEFAULT NULL AFTER id");
addColumn($pdo, 'users', 'company_id', "INT UNSIGNED DEFAULT NULL AFTER id");
addColumn($pdo, 'clients', 'company_id', "INT UNSIGNED DEFAULT NULL AFTER id");
addColumn($pdo, 'cases', 'company_id', "INT UNSIGNED DEFAULT NULL AFTER id");

$pdo->exec("UPDATE company_settings SET company_id = {$companyId} WHERE company_id IS NULL");
$pdo->exec("UPDATE users SET company_id = {$companyId} WHERE company_id IS NULL AND role IN ('admin','client')");
$pdo->exec("UPDATE clients SET company_id = {$companyId} WHERE company_id IS NULL");
$pdo->exec("UPDATE cases cs JOIN clients cl ON cl.id = cs.client_id SET cs.company_id = cl.company_id WHERE cs.company_id IS NULL");

echo "[OK] Backfilled company_id values\n";

try {
    $pdo->exec('ALTER TABLE company_settings MODIFY company_id INT UNSIGNED NOT NULL');
} catch (Throwable $e) {
    echo "[SKIP] company_settings.company_id NOT NULL: {$e->getMessage()}\n";
}

try {
    $pdo->exec('ALTER TABLE clients MODIFY company_id INT UNSIGNED NOT NULL');
} catch (Throwable $e) {
    echo "[SKIP] clients.company_id NOT NULL: {$e->getMessage()}\n";
}

try {
    $pdo->exec('ALTER TABLE cases MODIFY company_id INT UNSIGNED NOT NULL');
} catch (Throwable $e) {
    echo "[SKIP] cases.company_id NOT NULL: {$e->getMessage()}\n";
}

dropIndexIfExists($pdo, 'company_settings', 'uq_company_settings_company');
if (!indexExists($pdo, 'company_settings', 'uq_company_settings_company')) {
    try {
        $pdo->exec('ALTER TABLE company_settings ADD UNIQUE KEY uq_company_settings_company (company_id)');
        echo "[OK] Added unique company_settings.company_id\n";
    } catch (Throwable $e) {
        echo "[SKIP] company_settings unique: {$e->getMessage()}\n";
    }
}

dropIndexIfExists($pdo, 'users', 'email');
if (!indexExists($pdo, 'users', 'uq_users_company_email')) {
    try {
        $pdo->exec('ALTER TABLE users ADD UNIQUE KEY uq_users_company_email (company_id, email)');
        echo "[OK] Added users(company_id, email) unique\n";
    } catch (Throwable $e) {
        echo "[SKIP] users unique: {$e->getMessage()}\n";
    }
}

dropIndexIfExists($pdo, 'clients', 'email');
if (!indexExists($pdo, 'clients', 'uq_clients_company_email')) {
    try {
        $pdo->exec('ALTER TABLE clients ADD UNIQUE KEY uq_clients_company_email (company_id, email)');
        echo "[OK] Added clients(company_id, email) unique\n";
    } catch (Throwable $e) {
        echo "[SKIP] clients unique: {$e->getMessage()}\n";
    }
}

dropIndexIfExists($pdo, 'cases', 'case_number');
dropIndexIfExists($pdo, 'cases', 'cases_case_number_unique');
if (!indexExists($pdo, 'cases', 'uq_cases_company_number')) {
    try {
        $pdo->exec('ALTER TABLE cases ADD UNIQUE KEY uq_cases_company_number (company_id, case_number)');
        echo "[OK] Added cases(company_id, case_number) unique\n";
    } catch (Throwable $e) {
        echo "[SKIP] cases unique: {$e->getMessage()}\n";
    }
}

dropIndexIfExists($pdo, 'invoices', 'invoice_number');
dropIndexIfExists($pdo, 'invoices', 'invoices_invoice_number_unique');
if (!indexExists($pdo, 'invoices', 'uq_invoices_company_number')) {
    if (!columnExists($pdo, 'invoices', 'company_id')) {
        addColumn($pdo, 'invoices', 'company_id', "INT UNSIGNED DEFAULT NULL AFTER id");
        $pdo->exec("UPDATE invoices i JOIN cases cs ON cs.id = i.case_id SET i.company_id = cs.company_id WHERE i.company_id IS NULL");
        try {
            $pdo->exec('ALTER TABLE invoices MODIFY company_id INT UNSIGNED NOT NULL');
        } catch (Throwable $e) {
            // optional strictness
        }
    }
    try {
        $pdo->exec('ALTER TABLE invoices ADD UNIQUE KEY uq_invoices_company_number (company_id, invoice_number)');
        echo "[OK] Added invoices(company_id, invoice_number) unique\n";
    } catch (Throwable $e) {
        echo "[SKIP] invoices unique: {$e->getMessage()}\n";
    }
}

try {
    $pdo->exec("ALTER TABLE users MODIFY role ENUM('admin','super_admin','manager','staff','viewer','client') NOT NULL DEFAULT 'admin'");
    echo "[OK] Extended users.role enum with super_admin\n";
} catch (Throwable $e) {
    echo "[SKIP] users.role enum: {$e->getMessage()}\n";
}

try {
    $pdo->exec("UPDATE users SET role = 'super_admin' WHERE id = 1 AND role = 'admin'");
    echo "[OK] Promoted user #1 to super_admin (if present)\n";
} catch (Throwable $e) {
    echo "[SKIP] super_admin promotion: {$e->getMessage()}\n";
}

echo "\nMigration complete.\n";
