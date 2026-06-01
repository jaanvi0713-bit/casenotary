<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verifyRequest()) {
    flash('error', 'Invalid request.');
    redirect('pages/clients.php');
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'create_client':
            $createLogin = !empty($_POST['create_login']);
            $result      = ClientService::create($_POST, $createLogin);

            $message = 'Client added successfully.';
            if ($createLogin) {
                $message .= ' Portal login created. Client can sign in at ' . adminUrl('auth/login.php?portal=client');
                if (!empty($_POST['email']) && !empty($result['password'])) {
                    MailService::sendLoginEmail(
                        ['email' => $_POST['email'], 'first_name' => $_POST['first_name'] ?? '', 'last_name' => $_POST['last_name'] ?? ''],
                        'Welcome to our client portal. You can view cases, documents, and appointments after signing in.',
                        null
                    );
                }
            }

            flash('success', $message);
            redirect('pages/clients.php');
            break;

        case 'update_client':
            $id = (int) ($_POST['client_id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Invalid client.');
            }
            $newPassword = ClientService::update($id, $_POST);
            $message = 'Client updated successfully.';
            if ($newPassword) {
                $message .= ' Portal login created. Client can sign in at ' . adminUrl('auth/login.php?portal=client');
            }
            flash('success', $message);
            redirect('pages/client-form.php?id=' . $id);
            break;

        case 'delete_client':
            $id = (int) ($_POST['client_id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('Invalid client.');
            }
            ClientService::deleteClient($id);
            flash('success', 'Client deleted successfully.');
            redirect('pages/clients.php');
            break;

        default:
            flash('error', 'Unknown action.');
            redirect('pages/clients.php');
    }
} catch (Throwable $e) {
    setOld($_POST);
    flash('error', $e->getMessage());
    if (($action ?? '') === 'delete_client') {
        redirect('pages/clients.php');
    }
    $id = (int) ($_POST['client_id'] ?? 0);
    redirect($id > 0 ? 'pages/client-form.php?id=' . $id : 'pages/client-form.php');
}
