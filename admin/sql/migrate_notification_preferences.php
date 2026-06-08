<?php

/**
 * Per-user notification channel preferences.
 * Run: php admin/sql/migrate_notification_preferences.php
 */

require_once __DIR__ . '/../core/bootstrap.php';

try {
    if (!Database::columnExists('users', 'notification_preferences')) {
        Database::query(
            'ALTER TABLE users ADD COLUMN notification_preferences TEXT DEFAULT NULL AFTER status'
        );
        echo "[OK] Added users.notification_preferences\n";
    } else {
        echo "[OK] users.notification_preferences already exists\n";
    }
} catch (Throwable $e) {
    echo "[SKIP] users.notification_preferences: {$e->getMessage()}\n";
}

echo "\nMigration complete.\n";
