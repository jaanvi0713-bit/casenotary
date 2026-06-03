<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::guardAction();

$id = (int) ($_GET['id'] ?? 0);
$receipt = ReceiptService::fetchForAdmin($id);

if (!$receipt) {
    http_response_code(404);
    exit('Receipt not found.');
}

header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: inline; filename="' . ($receipt['receipt_number'] ?? 'receipt') . '.html"');

echo ReceiptService::renderHtml($receipt);
