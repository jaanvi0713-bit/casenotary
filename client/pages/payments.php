<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireClient();

$clientId = Auth::clientId();
if (!$clientId) {
    flash('error', 'Client profile not found.');
    header('Location: ' . clientLoginUrl());
    exit;
}

$pageTitle = 'Payments';
$pageSubtitle = 'Your invoices and payment history';
$perPage = 10;

$allInvoices = getClientInvoices($clientId);
$allPayments = getClientPayments($clientId);

$invoicePage = requestPageNumber('invoice_page');
$totalInvoices = count($allInvoices);
$totalInvoicePages = max(1, (int) ceil($totalInvoices / $perPage));
if ($invoicePage > $totalInvoicePages) {
    $invoicePage = $totalInvoicePages;
}
$invoices = array_slice($allInvoices, paginationOffset($invoicePage, $perPage), $perPage);
$invoiceShowingFrom = $totalInvoices > 0 ? paginationOffset($invoicePage, $perPage) + 1 : 0;
$invoiceShowingTo = min($totalInvoices, $invoicePage * $perPage);

$paymentPage = requestPageNumber('payment_page');
$totalPayments = count($allPayments);
$totalPaymentPages = max(1, (int) ceil($totalPayments / $perPage));
if ($paymentPage > $totalPaymentPages) {
    $paymentPage = $totalPaymentPages;
}
$payments = array_slice($allPayments, paginationOffset($paymentPage, $perPage), $perPage);
$paymentShowingFrom = $totalPayments > 0 ? paginationOffset($paymentPage, $perPage) + 1 : 0;
$paymentShowingTo = min($totalPayments, $paymentPage * $perPage);

$stats = getClientDashboardStats($clientId);
$stripeEnabled = StripeService::isConfigured();

if (!empty($_GET['cancelled'])) {
    flash('error', 'Payment was cancelled.');
}

$successMsg = flash('success');
$errorMsg   = flash('error');

require __DIR__ . '/../includes/header.php';
?>

<?php if ($successMsg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert"><?= e($successMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if ($errorMsg): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert"><?= e($errorMsg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="metric-card">
            <div class="metric-icon metric-icon-warning"><i class="bi bi-hourglass-split"></i></div>
            <div class="metric-body">
                <span class="metric-label">Pending Invoices</span>
                <span class="metric-value"><?= number_format($stats['pending_invoices']) ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="metric-card">
            <div class="metric-icon metric-icon-primary"><i class="bi bi-receipt"></i></div>
            <div class="metric-body">
                <span class="metric-label">Total Invoices</span>
                <span class="metric-value"><?= number_format($totalInvoices) ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="metric-card">
            <div class="metric-icon metric-icon-success"><i class="bi bi-cash-stack"></i></div>
            <div class="metric-body">
                <span class="metric-label">Payments Made</span>
                <span class="metric-value"><?= number_format($totalPayments) ?></span>
            </div>
        </div>
    </div>
</div>

<div class="saas-card mb-4" id="client-invoices">
    <div class="saas-card-header appointment-list-header">
        <div>
            <h2 class="saas-card-title">Invoices</h2>
            <p class="saas-card-subtitle mb-0"><?= $totalInvoices ?> invoice(s)</p>
        </div>
        <?php if ($stripeEnabled): ?>
            <span class="badge bg-light text-dark"><i class="bi bi-shield-check"></i> Secure Stripe checkout</span>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if ($totalInvoices === 0): ?>
            <div class="empty-state py-5">
                <i class="bi bi-receipt"></i>
                <p class="mb-0">No invoices yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table saas-table mb-0">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Case</th>
                            <th>Amount</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice): ?>
                            <?php
                            $status = effectiveInvoiceStatus($invoice);
                            $remaining = CaseService::getInvoiceRemainingBalance($invoice);
                            $canPay = in_array($status, ['pending', 'overdue', 'partially_paid'], true) && $remaining > 0;
                            ?>
                            <tr>
                                <td><strong><?= e($invoice['invoice_number']) ?></strong></td>
                                <td>
                                    <?php if (!empty($invoice['case_number'])): ?>
                                        <span class="table-primary"><?= e($invoice['case_number']) ?></span>
                                        <?php if (!empty($invoice['case_title'])): ?>
                                            <span class="table-secondary d-block"><?= e($invoice['case_title']) ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="table-primary"><?= formatCurrency((float) ($invoice['total'] ?? 0)) ?></span>
                                    <?php if ($canPay && $remaining < (float) ($invoice['total'] ?? 0)): ?>
                                        <span class="table-secondary d-block"><?= formatCurrency($remaining) ?> remaining</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted"><?= !empty($invoice['due_date']) ? formatDate($invoice['due_date']) : '—' ?></td>
                                <td><?= statusBadge($status) ?></td>
                                <td class="text-end">
                                    <?php if (!empty($invoice['pdf_path'])): ?>
                                        <a href="<?= adminUrl('actions/document-download.php?path=' . urlencode($invoice['pdf_path'])) ?>" class="btn btn-soft btn-sm" target="_blank">PDF</a>
                                    <?php endif; ?>
                                    <?php if ($canPay && $stripeEnabled): ?>
                                        <form method="post" action="<?= clientUrl('actions/stripe-checkout.php') ?>" class="d-inline">
                                            <?= CSRF::field() ?>
                                            <input type="hidden" name="invoice_id" value="<?= (int) $invoice['id'] ?>">
                                            <button type="submit" class="btn btn-primary btn-sm">Pay <?= formatCurrency($remaining) ?></button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-2 border-top">
                <small class="text-muted">
                    Showing <?= $invoiceShowingFrom ?>–<?= $invoiceShowingTo ?> of <?= $totalInvoices ?> invoices
                </small>
                <?= renderPaginationNav($invoicePage, $totalInvoicePages, 'invoice_page', 'client-invoices') ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="saas-card" id="client-payments">
    <div class="saas-card-header appointment-list-header">
        <div>
            <h2 class="saas-card-title">Payment History</h2>
            <p class="saas-card-subtitle mb-0"><?= $totalPayments ?> payment(s)</p>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if ($totalPayments === 0): ?>
            <div class="empty-state py-5">
                <i class="bi bi-credit-card"></i>
                <p class="mb-0">No payments recorded yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table saas-table mb-0">
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Paid At</th>
                            <th>Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td>
                                    <span class="table-primary"><?= e($payment['invoice_number']) ?></span>
                                    <span class="table-secondary d-block"><?= formatCurrency((float) ($payment['invoice_total'] ?? 0)) ?></span>
                                </td>
                                <td><span class="table-primary"><?= formatCurrency((float) $payment['amount']) ?></span></td>
                                <td><?= paymentMethodBadge($payment['payment_method'] ?? 'other') ?></td>
                                <td><?= paymentStatusBadge(paymentStatusValue($payment)) ?></td>
                                <td class="text-muted"><?= formatDateTime($payment['paid_at'] ?? $payment['created_at']) ?></td>
                                <td>
                                    <?php if (!empty($payment['receipt_id'])): ?>
                                        <a href="<?= clientUrl('actions/receipt-download.php?id=' . (int) $payment['receipt_id']) ?>" class="btn btn-soft btn-sm" target="_blank">
                                            <i class="bi bi-receipt"></i> Receipt
                                        </a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-2 border-top">
                <small class="text-muted">
                    Showing <?= $paymentShowingFrom ?>–<?= $paymentShowingTo ?> of <?= $totalPayments ?> payments
                </small>
                <?= renderPaginationNav($paymentPage, $totalPaymentPages, 'payment_page', 'client-payments') ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
