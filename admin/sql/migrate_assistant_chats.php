<?php

/**
 * Assistant chat library table (reuses chatbot_conversations).
 * Run: php admin/sql/migrate_assistant_chats.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

echo "Assistant chat library migrations...\n\n";

$pdo = Database::getInstance();

$pdo->exec("
CREATE TABLE IF NOT EXISTS chatbot_conversations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    company_id INT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL DEFAULT 'New chat',
    messages JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_chatbot_conversations_user (user_id),
    INDEX idx_chatbot_conversations_updated (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

echo "[OK] chatbot_conversations table ready\n";

if (!Database::columnExists('chatbot_conversations', 'company_id')) {
    $pdo->exec('ALTER TABLE chatbot_conversations ADD COLUMN company_id INT UNSIGNED NULL AFTER user_id');
    echo "[OK] Added company_id column\n";
}

echo "\nDone.\n";
