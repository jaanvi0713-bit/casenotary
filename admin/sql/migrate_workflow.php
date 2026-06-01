<?php
/**
 * Workflow migrations: client instructions, appointment columns.
 * Run: php admin/sql/migrate_workflow.php
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

echo "Workflow migrations...\n\n";

if (!columnExists($pdo, 'cases', 'client_instructions')) {
    try {
        $pdo->exec("ALTER TABLE cases ADD COLUMN client_instructions TEXT DEFAULT NULL AFTER description");
        echo "[OK] Added cases.client_instructions\n";
    } catch (Throwable $e) {
        echo "[SKIP] cases.client_instructions: {$e->getMessage()}\n";
    }
} else {
    echo "[OK] cases.client_instructions already exists\n";
}

foreach (['starts_at' => 'start_time', 'ends_at' => 'end_time'] as $newCol => $legacyCol) {
    if (!columnExists($pdo, 'appointments', $newCol) && columnExists($pdo, 'appointments', $legacyCol)) {
        try {
            $pdo->exec("ALTER TABLE appointments ADD COLUMN {$newCol} DATETIME DEFAULT NULL AFTER {$legacyCol}");
            $pdo->exec("UPDATE appointments SET {$newCol} = {$legacyCol} WHERE {$newCol} IS NULL");
            echo "[OK] Added appointments.{$newCol}\n";
        } catch (Throwable $e) {
            echo "[SKIP] appointments.{$newCol}: {$e->getMessage()}\n";
        }
    }
}

$logDir = __DIR__ . '/../storage/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
    echo "[OK] Created storage/logs directory\n";
}

$tokenColumns = [
    'google_access_token'  => 'TEXT DEFAULT NULL',
    'google_refresh_token' => 'TEXT DEFAULT NULL',
    'google_token_expires' => 'INT UNSIGNED DEFAULT NULL',
];

foreach ($tokenColumns as $column => $definition) {
    if (!columnExists($pdo, 'company_settings', $column)) {
        try {
            $pdo->exec("ALTER TABLE company_settings ADD COLUMN {$column} {$definition}");
            echo "[OK] Added company_settings.{$column}\n";
        } catch (Throwable $e) {
            echo "[SKIP] company_settings.{$column}: {$e->getMessage()}\n";
        }
    }
}

$calendarColumns = [
    'google_client_id'           => 'VARCHAR(255) DEFAULT NULL',
    'google_client_secret'       => 'VARCHAR(255) DEFAULT NULL',
    'appointment_reminder_hours' => 'INT UNSIGNED NOT NULL DEFAULT 24',
];

foreach ($calendarColumns as $column => $definition) {
    if (!columnExists($pdo, 'company_settings', $column)) {
        try {
            $pdo->exec("ALTER TABLE company_settings ADD COLUMN {$column} {$definition}");
            echo "[OK] Added company_settings.{$column}\n";
        } catch (Throwable $e) {
            echo "[SKIP] company_settings.{$column}: {$e->getMessage()}\n";
        }
    }
}

$userColumns = [
    'phone' => 'VARCHAR(30) DEFAULT NULL',
];

foreach ($userColumns as $column => $definition) {
    if (!columnExists($pdo, 'users', $column)) {
        try {
            $pdo->exec("ALTER TABLE users ADD COLUMN {$column} {$definition}");
            echo "[OK] Added users.{$column}\n";
        } catch (Throwable $e) {
            echo "[SKIP] users.{$column}: {$e->getMessage()}\n";
        }
    } else {
        echo "[OK] users.{$column} already exists\n";
    }
}

echo "\nWorkflow migration complete.\n";
