<?php
/**
 * Add rescheduled to appointments.status enum.
 * Run: php admin/sql/migrate_appointment_rescheduled.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/Database.php';

$pdo = Database::getInstance();

echo "Appointment rescheduled status migration...\n\n";

try {
    $pdo->exec(
        "ALTER TABLE appointments MODIFY status ENUM('requested', 'scheduled', 'confirmed', 'rescheduled', 'completed', 'cancelled', 'no_show') NOT NULL DEFAULT 'scheduled'"
    );
    echo "[OK] appointments.status includes rescheduled\n";
} catch (Throwable $e) {
    echo "[FAIL] {$e->getMessage()}\n";
    exit(1);
}

echo "\nMigration complete.\n";
