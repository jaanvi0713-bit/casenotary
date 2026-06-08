<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::guardAction();

syncOverdueInvoices();
$payments = getAllPayments();

$filename = 'payments-' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Invoice', 'Client', 'Amount', 'Method', 'Status', 'Paid At', 'Receipt']);

foreach ($payments as $payment) {
    fputcsv($out, [
        $payment['invoice_number'] ?? '',
        clientFullName($payment),
        number_format((float) ($payment['amount'] ?? 0), 2, '.', ''),
        ucwords(str_replace('_', ' ', $payment['payment_method'] ?? '')),
        paymentStatusValue($payment),
        $payment['paid_at'] ?? $payment['created_at'] ?? '',
        $payment['receipt_number'] ?? '',
    ]);
}

fclose($out);
exit;
