<?php
/**
 * Company isolation for notifications and chatbot conversations.
 * Run: php admin/sql/migrate_tenant_isolation.php
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

    $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    echo "[OK] Added {$table}.{$column}\n";
}

echo "Tenant isolation migration...\n\n";

addColumn($pdo, 'notifications', 'company_id', 'INT UNSIGNED DEFAULT NULL AFTER user_id');
addColumn($pdo, 'chatbot_conversations', 'company_id', 'INT UNSIGNED DEFAULT NULL AFTER user_id');

$defaultCompany = (int) ($pdo->query('SELECT id FROM companies ORDER BY id ASC LIMIT 1')->fetchColumn() ?: 1);

if (columnExists($pdo, 'notifications', 'company_id')) {
    $pdo->exec("UPDATE notifications n
        LEFT JOIN users u ON u.id = n.user_id
        SET n.company_id = COALESCE(u.company_id, {$defaultCompany})
        WHERE n.company_id IS NULL");
    echo "[OK] Backfilled notifications.company_id\n";
}

if (columnExists($pdo, 'chatbot_conversations', 'company_id')) {
    $pdo->exec("UPDATE chatbot_conversations c
        LEFT JOIN users u ON u.id = c.user_id
        SET c.company_id = COALESCE(u.company_id, {$defaultCompany})
        WHERE c.company_id IS NULL");
    echo "[OK] Backfilled chatbot_conversations.company_id\n";
}

echo "\nMigration complete.\n";
