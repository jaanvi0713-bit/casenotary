<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireClient();

$sessionId = trim($_GET['session_id'] ?? '');

if ($sessionId === '') {
    flash('error', 'Missing payment session.');
    header('Location: ' . clientUrl('pages/payments.php'));
    exit;
}

try {
    if (!StripeService::isConfigured()) {
        throw new RuntimeException('Stripe is not configured.');
    }

    $session  = StripeService::retrieveCheckoutSession($sessionId);
    $clientId = Auth::clientId();
    $metaClient = (int) ($session['metadata']['client_id'] ?? 0);
    $invoiceId  = (int) ($session['metadata']['invoice_id'] ?? 0);

    if ($metaClient !== $clientId || $invoiceId <= 0) {
        throw new RuntimeException('Payment session does not match your account.');
    }

    if (($session['payment_status'] ?? '') !== 'paid') {
        flash('error', 'Payment was not completed.');
        header('Location: ' . clientUrl('pages/payments.php'));
        exit;
    }

    $amount = round(((int) ($session['amount_total'] ?? 0)) / 100, 2);
    $stripePaymentId = (string) ($session['payment_intent'] ?? $session['id'] ?? '');

    $result = CaseService::recordStripePayment($invoiceId, $amount, $stripePaymentId);

    if (empty($result['success'])) {
        throw new RuntimeException($result['message'] ?? 'Unable to record payment.');
    }

    flash('success', 'Payment successful! Your receipt is available below.');
    header('Location: ' . clientUrl('pages/payments.php'));
    exit;
} catch (Throwable $e) {
    flash('error', $e->getMessage());
    header('Location: ' . clientUrl('pages/payments.php'));
    exit;
}
