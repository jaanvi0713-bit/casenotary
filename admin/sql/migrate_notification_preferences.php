<?php

/**
 * Per-user notification channel preferences.
 * Run: php admin/sql/migrate_notification_preferences.php
 */

require_once __DIR__ . '/../core/bootstrap.php';

try {
    Database::query(
        'CREATE TABLE IF NOT EXISTS user_notification_preferences (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id INT UNSIGNED NOT NULL,
            type VARCHAR(32) NOT NULL,
            in_app TINYINT(1) NOT NULL DEFAULT 1,
            email TINYINT(1) NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_user_notification_pref (user_id, type),
            KEY idx_notification_pref_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    echo "[OK] Created user_notification_preferences table\n";
} catch (Throwable $e) {
    echo "[SKIP] user_notification_preferences: {$e->getMessage()}\n";
}

echo "\nMigration complete.\n";
