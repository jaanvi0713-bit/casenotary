<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::guardAction();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verifyRequest()) {
    flash('error', 'Invalid request.');
    redirect('pages/payments.php');
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'record_payment') {
        $invoiceId = (int) ($_POST['invoice_id'] ?? 0);
        if ($invoiceId <= 0) {
            throw new RuntimeException('Please select an invoice.');
        }

        $result = CaseService::recordPayment($invoiceId, $_POST, Auth::id());
        if (empty($result['success'])) {
            throw new RuntimeException($result['message'] ?? 'Unable to record payment.');
        }

        flash('success', 'Payment recorded and receipt generated.');
        redirect('pages/payments.php');
    }

    flash('error', 'Unknown action.');
    redirect('pages/payments.php');
} catch (Throwable $e) {
    flash('error', $e->getMessage());
    redirect('pages/payments.php');
}
