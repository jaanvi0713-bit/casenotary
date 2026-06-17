<?php
require_once __DIR__ . '/../core/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . clientUrl('pages/payments.php'));
    exit;
}

$token     = trim((string) ($_POST['token'] ?? ''));
$returnUrl = $token !== ''
    ? clientUrl('pages/pay-invoice.php?token=' . urlencode($token))
    : clientUrl('pages/payments.php');

if (!CSRF::verifyRequest()) {
    flash('error', 'Invalid security token. Please refresh the page and try again.');
    header('Location: ' . $returnUrl);
    exit;
}

$action = trim((string) ($_POST['payment_action'] ?? $_POST['action'] ?? ''));

if ($token === '') {
    flash('error', 'Invalid payment session.');
    header('Location: ' . clientUrl('pages/payments.php'));
    exit;
}

if ($action === 'complete') {
    $result = PaymentGatewayService::completePayment($token);
} elseif ($action === 'fail') {
    $result = PaymentGatewayService::failPayment($token);
} else {
    flash('error', 'Unknown payment action.');
    header('Location: ' . $returnUrl);
    exit;
}

if (!empty($result['success'])) {
    $message = $result['message'] ?? 'Payment processed.';
    if ($action === 'complete' && !empty($result['receipt_id'])) {
        $message = 'Payment completed successfully. Your receipt is ready to download.';
    }
    flash('success', $message);
} else {
    flash('error', $result['message'] ?? 'Payment could not be processed.');
}

header('Location: ' . $returnUrl);
exit;
