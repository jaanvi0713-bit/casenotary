<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAdmin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    exit('Receipt not found.');
}

$receipt = Database::fetch(
    'SELECT r.*, i.invoice_number, i.total AS invoice_total,
            cl.first_name, cl.last_name, cl.email AS client_email, cl.company_name,
            p.payment_method, p.paid_at, p.notes AS payment_notes, p.amount AS payment_amount
     FROM receipts r
     JOIN payments p ON p.id = r.payment_id
     JOIN invoices i ON i.id = p.invoice_id
     JOIN clients cl ON cl.id = i.client_id
     WHERE r.id = ?',
    [$id]
);

if (!$receipt) {
    http_response_code(404);
    exit('Receipt not found.');
}

$company = getCompanySettings();
$method  = ucwords(str_replace('_', ' ', $receipt['payment_method'] ?? 'other'));

header('Content-Type: text/html; charset=utf-8');
header('Content-Disposition: inline; filename="' . ($receipt['receipt_number'] ?? 'receipt') . '.html"');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= e($receipt['receipt_number']) ?> — Receipt</title>
    <style>
        body { font-family: Montserrat, Arial, sans-serif; color: #0f172a; margin: 40px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 32px; }
        .brand { font-size: 22px; font-weight: 700; color: <?= e($company['secondary_color'] ?? '#00182c') ?>; }
        .receipt-brand-logo { display: block; max-height: 48px; max-width: 200px; width: auto; height: auto; object-fit: contain; margin-bottom: 10px; }
        .meta { text-align: right; color: #475569; font-size: 14px; }
        h1 { color: <?= e($company['primary_color'] ?? '#3aafa9') ?>; margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th, td { padding: 12px; border-bottom: 1px solid #e2e8f0; text-align: left; }
        .total { font-size: 20px; font-weight: 700; color: <?= e($company['secondary_color'] ?? '#00182c') ?>; }
        @media print { body { margin: 20px; } .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom:20px;">
        <button onclick="window.print()" style="padding:10px 16px;background:#3aafa9;color:#fff;border:none;border-radius:8px;cursor:pointer;">Print / Save as PDF</button>
    </div>
    <div class="header">
        <div>
            <?php if ($logoUrl = companyLogoUrl($company)): ?>
                <img src="<?= e($logoUrl) ?>" alt="<?= e(companyBrandName($company)) ?>" class="receipt-brand-logo">
            <?php endif; ?>
            <div class="brand"><?= e(companyBrandName($company)) ?></div>
            <?php if (!empty($company['address'])): ?><div><?= nl2br(e($company['address'])) ?></div><?php endif; ?>
            <?php if (!empty($company['office_email'])): ?><div><?= e($company['office_email']) ?></div><?php endif; ?>
        </div>
        <div class="meta">
            <h1>Receipt</h1>
            <div><strong><?= e($receipt['receipt_number']) ?></strong></div>
            <div><?= formatDateTime($receipt['created_at']) ?></div>
        </div>
    </div>
    <p><strong>Bill to:</strong> <?= e(clientFullName($receipt)) ?><br>
    <?= e($receipt['client_email'] ?? '') ?></p>
    <table>
        <tr><th>Invoice</th><td><?= e($receipt['invoice_number']) ?></td></tr>
        <tr><th>Payment method</th><td><?= e($method) ?></td></tr>
        <tr><th>Paid at</th><td><?= formatDateTime($receipt['paid_at'] ?? $receipt['created_at']) ?></td></tr>
        <?php if (!empty($receipt['payment_notes'])): ?>
            <tr><th>Notes</th><td><?= e($receipt['payment_notes']) ?></td></tr>
        <?php endif; ?>
        <tr><th>Amount received</th><td class="total"><?= formatCurrency((float) ($receipt['amount'] ?? $receipt['payment_amount'] ?? 0)) ?></td></tr>
    </table>
</body>
</html>
