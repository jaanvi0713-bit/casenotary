<?php
/**
 * Client letter templates and per-case draft sections.
 * Run: php admin/sql/migrate_client_letters.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/ClientLetterService.php';

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
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);

    return (int) $stmt->fetchColumn() > 0;
}

echo "Client letter migrations...\n\n";

if (!tableExists($pdo, 'client_letter_templates')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE client_letter_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    sections JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_client_letter_template_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    echo "[OK] Created client_letter_templates\n";
} else {
    echo "[OK] client_letter_templates already exists\n";
}

if (!columnExists($pdo, 'cases', 'client_letter_sections')) {
    $pdo->exec('ALTER TABLE cases ADD COLUMN client_letter_sections JSON DEFAULT NULL AFTER description');
    echo "[OK] Added cases.client_letter_sections\n";
} else {
    echo "[OK] cases.client_letter_sections already exists\n";
}

$count = (int) $pdo->query('SELECT COUNT(*) FROM client_letter_templates')->fetchColumn();
if ($count === 0) {
    $sections = json_encode(ClientLetterService::builtinDefaultSections(), JSON_UNESCAPED_UNICODE);
    $pdo->prepare('INSERT INTO client_letter_templates (name, is_default, sections) VALUES (?, 1, ?)')
        ->execute(['Default engagement letter', $sections]);
    echo "[OK] Seeded default letter template\n";
} else {
    echo "[OK] client_letter_templates already has data\n";
}

echo "\nClient letter migration complete.\n";
