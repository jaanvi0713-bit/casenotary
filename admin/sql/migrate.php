<?php
/**
 * One-time migration: align database with application expectations.
 * Run: php admin/sql/migrate.php
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

function runMigration(PDO $pdo, string $sql, string $label): void
{
    try {
        $pdo->exec($sql);
        echo "[OK] {$label}\n";
    } catch (PDOException $e) {
        echo "[SKIP] {$label}: " . $e->getMessage() . "\n";
    }
}

echo "Running database migrations...\n\n";

if (!columnExists($pdo, 'users', 'status')) {
    runMigration(
        $pdo,
        "ALTER TABLE users ADD COLUMN status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active' AFTER avatar",
        'Added users.status column'
    );
} else {
    echo "[OK] users.status already exists\n";
}

runMigration($pdo, "UPDATE users SET status = 'active' WHERE status IS NULL OR status = ''", 'Ensured active status on users');

if (!columnExists($pdo, 'users', 'last_login')) {
    runMigration(
        $pdo,
        'ALTER TABLE users ADD COLUMN last_login DATETIME DEFAULT NULL AFTER email_verified_at',
        'Added users.last_login column'
    );
} else {
    echo "[OK] users.last_login already exists\n";
}

$hash = password_hash('admin123', PASSWORD_BCRYPT);
$admin = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetch();

if (!$admin) {
    $stmt = $pdo->prepare(
        "INSERT INTO users (name, email, password, role, status, is_active, created_at, updated_at)
         VALUES ('Admin User', 'admin@admin.com', ?, 'admin', 'active', 1, NOW(), NOW())"
    );
    $stmt->execute([$hash]);
    echo "[OK] Created admin user (admin@admin.com / admin123)\n";
} else {
    $stmt = $pdo->prepare(
        "UPDATE users SET email = 'admin@admin.com', password = ?, name = 'Admin User', status = 'active', is_active = 1
         WHERE role = 'admin' LIMIT 1"
    );
    $stmt->execute([$hash]);
    echo "[OK] Updated admin credentials (admin@admin.com / admin123)\n";
}

echo "\nMigration complete.\n";
