<?php
/**
 * Deadlines, document requests, intake submissions, document summaries.
 * Run: php admin/sql/migrate_case_features.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/Database.php';

$pdo = Database::getInstance();

function colExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);

    return (int) $stmt->fetchColumn() > 0;
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);

    return (int) $stmt->fetchColumn() > 0;
}

echo "Case features migration...\n\n";

if (!tableExists($pdo, 'case_deadlines')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE case_deadlines (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id INT UNSIGNED NOT NULL,
    label VARCHAR(200) NOT NULL,
    deadline_type VARCHAR(40) NOT NULL DEFAULT 'other',
    due_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    notes TEXT DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_case_deadlines_case (case_id),
    INDEX idx_case_deadlines_due (due_date),
    INDEX idx_case_deadlines_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    echo "[OK] Created case_deadlines\n";
} else {
    echo "[OK] case_deadlines already exists\n";
}

if (!tableExists($pdo, 'case_document_requests')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE case_document_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id INT UNSIGNED NOT NULL,
    label VARCHAR(200) NOT NULL,
    description VARCHAR(500) DEFAULT NULL,
    required TINYINT(1) NOT NULL DEFAULT 1,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    document_id INT UNSIGNED DEFAULT NULL,
    created_by INT UNSIGNED DEFAULT NULL,
    fulfilled_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_case_doc_req_case (case_id),
    INDEX idx_case_doc_req_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    echo "[OK] Created case_document_requests\n";
} else {
    echo "[OK] case_document_requests already exists\n";
}

if (!tableExists($pdo, 'client_intake_submissions')) {
    $pdo->exec(<<<'SQL'
CREATE TABLE client_intake_submissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    client_id INT UNSIGNED NOT NULL,
    company_id INT UNSIGNED DEFAULT NULL,
    matter_description TEXT NOT NULL,
    suggested_service VARCHAR(120) DEFAULT NULL,
    suggested_fee_min DECIMAL(12,2) DEFAULT NULL,
    suggested_fee_max DECIMAL(12,2) DEFAULT NULL,
    checklist_preview JSON DEFAULT NULL,
    ai_notes TEXT DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    case_id INT UNSIGNED DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_intake_client (client_id),
    INDEX idx_intake_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    echo "[OK] Created client_intake_submissions\n";
} else {
    echo "[OK] client_intake_submissions already exists\n";
}

if (tableExists($pdo, 'documents') && !colExists($pdo, 'documents', 'ai_summary')) {
    $pdo->exec('ALTER TABLE documents ADD COLUMN ai_summary TEXT DEFAULT NULL AFTER mime_type');
    echo "[OK] Added documents.ai_summary\n";
} else {
    echo "[OK] documents.ai_summary already exists or documents table missing\n";
}

echo "\nMigration complete.\n";
