<?php
/**
 * Saved / published client letters per case.
 * Run: php admin/sql/migrate_case_client_letters.php
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

echo "Case client letters migration...\n\n";

if (!tableExists($pdo, 'case_client_letters')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE case_client_letters (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    pdf_path VARCHAR(500) DEFAULT NULL,
    html_path VARCHAR(500) DEFAULT NULL,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    version_group_id INT UNSIGNED DEFAULT NULL,
    is_current TINYINT(1) NOT NULL DEFAULT 1,
    saved_to_record TINYINT(1) NOT NULL DEFAULT 0,
    published_to_portal TINYINT(1) NOT NULL DEFAULT 0,
    created_by INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_ccl_case (case_id),
  INDEX idx_ccl_client (client_id),
  INDEX idx_ccl_published (case_id, published_to_portal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    echo "[OK] Created case_client_letters\n";
} else {
    echo "[OK] case_client_letters already exists\n";
}

echo "\nMigration complete.\n";
