<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireClient();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verifyRequest()) {
    flash('error', 'Invalid request.');
    header('Location: ' . clientUrl('pages/payments.php'));
    exit;
}

$clientId  = Auth::clientId();
$invoiceId = (int) ($_POST['invoice_id'] ?? 0);

try {
    if (!$clientId || $invoiceId <= 0) {
        throw new RuntimeException('Invalid invoice.');
    }

    if (!StripeService::isConfigured()) {
        throw new RuntimeException('Online payments are not available. Please contact the office.');
    }

    $invoice = Database::fetch(
        'SELECT i.*, cs.case_number, cs.title AS case_title
         FROM invoices i
         LEFT JOIN cases cs ON cs.id = i.case_id
         WHERE i.id = ? AND i.client_id = ?',
        [$invoiceId, $clientId]
    );

    if (!$invoice) {
        throw new RuntimeException('Invoice not found.');
    }

    syncOverdueInvoices();
    $status = invoiceStatusValue($invoice);

    if (!in_array($status, ['pending', 'overdue', 'partially_paid'], true)) {
        throw new RuntimeException('This invoice cannot be paid online.');
    }

    $amount = CaseService::getInvoiceRemainingBalance($invoice);
    $session = StripeService::createCheckoutSession($invoice, $clientId, $amount);

    if (empty($session['url'])) {
        throw new RuntimeException('Could not start Stripe checkout.');
    }

    header('Location: ' . $session['url']);
    exit;
} catch (Throwable $e) {
    flash('error', $e->getMessage());
    header('Location: ' . clientUrl('pages/payments.php'));
    exit;
}
