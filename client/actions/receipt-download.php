<?php

require_once __DIR__ . '/../core/bootstrap.php';

$id    = (int) ($_GET['id'] ?? 0);
$token = trim((string) ($_GET['token'] ?? ''));
$receipt = null;

if (Auth::isClient()) {
    $clientId = Auth::clientId();
    if ($clientId) {
        $receipt = ReceiptService::fetchForClient($id, (int) $clientId);
    }
}

if (!$receipt && $token !== '') {
    $receipt = ReceiptService::fetchByPaymentToken($id, $token);
}

if (!$receipt) {
    if (!Auth::isClient()) {
        header('Location: ' . adminUrl('auth/login.php?portal=client'));
        exit;
    }

    http_response_code(404);
    exit('Receipt not found.');
}

try {
    $html = ReceiptService::renderHtml($receipt);
} catch (Throwable $e) {
    http_response_code(500);
    exit('Unable to generate receipt.');
}

$filename = preg_replace('/[^a-zA-Z0-9._-]+/', '_', (string) ($receipt['receipt_number'] ?? 'receipt')) . '.html';

header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: inline; filename="' . $filename . '"');

echo $html;
