<?php
/**
 * Chatbot saved conversations.
 * Run: php admin/sql/migrate_chatbot.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/Database.php';

$pdo = Database::getInstance();

echo "Chatbot conversation migrations...\n\n";

$sql = <<<'SQL'
CREATE TABLE IF NOT EXISTS chatbot_conversations (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    title       VARCHAR(255) NOT NULL DEFAULT 'New chat',
    messages    JSON NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_chatbot_conversations_user (user_id),
    INDEX idx_chatbot_conversations_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

try {
    $pdo->exec($sql);
    echo "[OK] chatbot_conversations table ready\n";
} catch (Throwable $e) {
    echo '[FAIL] ' . $e->getMessage() . "\n";
    exit(1);
}

echo "\nDone.\n";
