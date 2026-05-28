<?php
/**
 * Cases module migrations. Run: php admin/sql/migrate_cases.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/Database.php';

$pdo = Database::getInstance();

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

echo "Cases module migrations...\n\n";

if (!tableExists($pdo, 'case_notes')) {
    try {
        $pdo->exec("
            CREATE TABLE case_notes (
                id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                case_id     INT UNSIGNED NOT NULL,
                user_id     INT UNSIGNED NOT NULL,
                note        TEXT NOT NULL,
                is_internal TINYINT(1) NOT NULL DEFAULT 1,
                created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_case_notes_case (case_id)
            ) ENGINE=InnoDB
        ");
        echo "[OK] Created case_notes table\n";
    } catch (PDOException $e) {
        echo "[SKIP] case_notes: " . $e->getMessage() . "\n";
    }
} else {
    echo "[OK] case_notes already exists\n";
}

if (tableExists($pdo, 'documents') && !columnExists($pdo, 'documents', 'upload_source')) {
    $pdo->exec("ALTER TABLE documents ADD COLUMN upload_source ENUM('admin','client') NOT NULL DEFAULT 'admin' AFTER uploaded_by");
    echo "[OK] Added documents.upload_source\n";
}

if (!tableExists($pdo, 'proposals')) {
    try {
        $pdo->exec("
            CREATE TABLE proposals (
                id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                case_id          INT UNSIGNED NOT NULL,
                proposal_number  VARCHAR(50) NOT NULL UNIQUE,
                title            VARCHAR(255) NOT NULL,
                content          TEXT NOT NULL,
                amount           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                status           ENUM('draft','sent','accepted','rejected') NOT NULL DEFAULT 'draft',
                pdf_path         VARCHAR(500) DEFAULT NULL,
                created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_proposals_case (case_id)
            ) ENGINE=InnoDB
        ");
        echo "[OK] Created proposals table\n";
    } catch (PDOException $e) {
        echo "[SKIP] proposals: " . $e->getMessage() . "\n";
    }
}

if (!tableExists($pdo, 'quotations')) {
    try {
        $pdo->exec("
            CREATE TABLE quotations (
                id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                case_id           INT UNSIGNED NOT NULL,
                quotation_number  VARCHAR(50) NOT NULL UNIQUE,
                title             VARCHAR(255) NOT NULL,
                line_items        JSON DEFAULT NULL,
                subtotal          DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                tax_rate          DECIMAL(5,2) NOT NULL DEFAULT 0.00,
                total             DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                status            ENUM('draft','sent','accepted','rejected','expired') NOT NULL DEFAULT 'draft',
                valid_until       DATE DEFAULT NULL,
                pdf_path          VARCHAR(500) DEFAULT NULL,
                created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_quotations_case (case_id)
            ) ENGINE=InnoDB
        ");
        echo "[OK] Created quotations table\n";
    } catch (PDOException $e) {
        echo "[SKIP] quotations: " . $e->getMessage() . "\n";
    }
}

$uploadsRoot = dirname(__DIR__) . '/uploads/cases';
if (!is_dir($uploadsRoot)) {
    mkdir($uploadsRoot, 0755, true);
    echo "[OK] Created uploads/cases directory\n";
}

echo "\nCases migration complete.\n";
