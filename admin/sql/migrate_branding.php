<?php
/**
 * Branding / company_settings column migrations.
 * Run: php admin/sql/migrate_branding.php
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

function addColumn(PDO $pdo, string $table, string $column, string $definition): void
{
    if (columnExists($pdo, $table, $column)) {
        echo "[OK] {$table}.{$column} already exists\n";
        return;
    }

    try {
        $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
        echo "[OK] Added {$table}.{$column}\n";
    } catch (Throwable $e) {
        echo "[FAIL] {$table}.{$column}: {$e->getMessage()}\n";
    }
}

echo "Branding migrations...\n\n";

if ($pdo->query("SHOW TABLES LIKE 'company_settings'")->rowCount() === 0) {
    echo "[FAIL] company_settings table not found. Run schema.sql or seed first.\n";
    exit(1);
}

addColumn($pdo, 'company_settings', 'logo', 'VARCHAR(500) DEFAULT NULL AFTER company_name');
addColumn($pdo, 'company_settings', 'favicon', 'VARCHAR(500) DEFAULT NULL AFTER logo');
addColumn($pdo, 'company_settings', 'dark_accent', "VARCHAR(7) NOT NULL DEFAULT '#000000' AFTER secondary_color");
addColumn($pdo, 'company_settings', 'font_family', "VARCHAR(100) NOT NULL DEFAULT 'Montserrat' AFTER dark_accent");
addColumn($pdo, 'company_settings', 'description', 'TEXT DEFAULT NULL AFTER font_family');
addColumn($pdo, 'company_settings', 'business_hours', 'TEXT DEFAULT NULL AFTER office_phone');
addColumn($pdo, 'company_settings', 'company_website', 'VARCHAR(500) DEFAULT NULL AFTER address');
addColumn($pdo, 'company_settings', 'registration_number', 'VARCHAR(100) DEFAULT NULL AFTER company_website');
addColumn($pdo, 'company_settings', 'tax_vat_number', 'VARCHAR(100) DEFAULT NULL AFTER registration_number');
addColumn($pdo, 'company_settings', 'facebook_url', 'VARCHAR(500) DEFAULT NULL AFTER tax_vat_number');
addColumn($pdo, 'company_settings', 'instagram_url', 'VARCHAR(500) DEFAULT NULL AFTER facebook_url');
addColumn($pdo, 'company_settings', 'linkedin_url', 'VARCHAR(500) DEFAULT NULL AFTER instagram_url');
addColumn($pdo, 'company_settings', 'google_calendar_id', 'VARCHAR(255) DEFAULT NULL');
addColumn($pdo, 'company_settings', 'outlook_calendar_id', 'VARCHAR(255) DEFAULT NULL');

$brandingDir = __DIR__ . '/../uploads/branding';
if (!is_dir($brandingDir)) {
    mkdir($brandingDir, 0755, true);
    echo "[OK] Created uploads/branding directory\n";
}

echo "\nBranding migration complete.\n";
