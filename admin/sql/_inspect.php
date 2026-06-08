<?php
require_once __DIR__ . '/../core/bootstrap.php';
foreach (['users', 'clients'] as $table) {
    echo "=== $table ===\n";
    foreach (Database::fetchAll("SHOW COLUMNS FROM $table") as $c) {
        echo $c['Field'] . "\n";
    }
}
$clients = Database::fetchAll("SELECT c.id, c.email, c.user_id, u.email as uemail, u.role, u.status FROM clients c LEFT JOIN users u ON u.id = c.user_id ORDER BY c.id DESC LIMIT 5");
echo "=== recent clients ===\n";
print_r($clients);
