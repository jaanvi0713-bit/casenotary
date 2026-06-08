<?php
require_once __DIR__ . '/../core/bootstrap.php';

$email = 'clienttest' . time() . '@example.com';
$result = ClientService::create([
    'first_name' => 'Test',
    'last_name' => 'Client',
    'email' => $email,
], true);

echo "Created client #{$result['client_id']} user #{$result['user_id']} pass: {$result['password']}\n";

$login = Auth::attempt($email, $result['password'], 'client');
echo $login['success'] ? "Client login OK\n" : "Client login FAIL: {$login['message']}\n";
echo 'Client ID in session context: ' . (Auth::clientId() ?? 'null') . "\n";
