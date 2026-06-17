<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requirePage('payments');

$pageTitle = 'Payments';
$q = trim((string) ($_GET['q'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$methodFilter = trim((string) ($_GET['method'] ?? ''));
$monthFilter = trim((string) ($_GET['month'] ?? ''));
$perPage = 10;
$page = requestPageNumber();
$totalPayments = countPayments($q, $statusFilter, $methodFilter, $monthFilter);
$totalPages = max(1, (int) ceil($totalPayments / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$payments = getPaymentsPaginated($page, $perPage, $q, $statusFilter, $methodFilter, $monthFilter);

$invQ = trim((string) ($_GET['inv_q'] ?? ''));
$invStatusFilter = trim((string) ($_GET['inv_status'] ?? ''));
$invMonthFilter = trim((string) ($_GET['inv_month'] ?? ''));
$invPage = requestPageNumber('inv_page');
$totalInvoices = countInvoices($invQ, $invStatusFilter, $invMonthFilter);
$totalInvoicePages = max(1, (int) ceil($totalInvoices / $perPage));
if ($invPage > $totalInvoicePages) {
    $invPage = $totalInvoicePages;
}
$invoices = getInvoicesPaginated($invPage, $perPage, $invQ, $invStatusFilter, $invMonthFilter);

$pendingInvoices = getPendingInvoices();
$overdueInvoices = getOverdueInvoices();
$stats = getDashboardStats();
$pageSubtitle = formatCurrency($stats['total_revenue']) . ' total revenue';

$paymentMonths = paymentHistoryMonthOptions();

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

<?php if (!empty($overdueInvoices)): ?>
    <div class="alert alert-danger d-flex align-items-start gap-3 mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill fs-5 mt-1"></i>
        <div class="flex-grow-1">
            <strong><?= count($overdueInvoices) ?> overdue invoice<?= count($overdueInvoices) === 1 ? '' : 's' ?></strong>
            <p class="mb-2 small">Follow up with clients or record payments to clear balances.</p>
            <ul class="mb-0 small ps-3">
                <?php foreach (array_slice($overdueInvoices, 0, 5) as $inv): ?>
                    <li>
                        <?= e($inv['invoice_number']) ?> — <?= e(clientFullName($inv)) ?> — <?= formatCurrency((float) $inv['total']) ?>
                        (due <?= formatDate($inv['due_date']) ?>)
                        <?php if (!empty($inv['case_id'])): ?>
                            · <a href="<?= url('pages/case-view.php?id=' . (int) $inv['case_id'] . '#invoice-payments') ?>" class="alert-link">Open case</a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="metric-card">
            <div class="metric-icon metric-icon-success"><i class="bi bi-cash-stack"></i></div>
            <div class="metric-body">
                <span class="metric-label">Total Revenue</span>
                <span class="metric-value metric-value-sm"><?= formatCurrency($stats['total_revenue']) ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="metric-card">
            <div class="metric-icon metric-icon-primary"><i class="bi bi-calendar3"></i></div>
            <div class="metric-body">
                <span class="metric-label">This Month</span>
                <span class="metric-value metric-value-sm"><?= formatCurrency($stats['monthly_revenue']) ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="metric-card">
            <div class="metric-icon metric-icon-warning"><i class="bi bi-hourglass-split"></i></div>
            <div class="metric-body">
                <span class="metric-label">Pending Invoices</span>
                <span class="metric-value"><?= number_format($stats['pending_invoices']) ?></span>
            </div>
        </div>
    </div>
</div>

<div class="saas-card">
    <div class="saas-card-header appointment-list-header">
        <div>
            <h2 class="saas-card-title">Payment History</h2>
            <p class="saas-card-subtitle mb-0"><?= $totalPayments ?> transactions</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php if (!empty($payments)): ?>
                <a href="<?= url('actions/payment-export.php') ?>" class="btn btn-light btn-sm">
                    <i class="bi bi-download"></i> Export CSV
                </a>
            <?php endif; ?>
            <?php if (!empty($pendingInvoices)): ?>
                <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#recordPaymentModal">
                    <i class="bi bi-plus-lg"></i> Record Payment
                </button>
            <?php endif; ?>
        </div>
    </div>
    <form method="get" class="table-toolbar">
        <?php if ($invQ !== ''): ?><input type="hidden" name="inv_q" value="<?= e($invQ) ?>"><?php endif; ?>
        <?php if ($invStatusFilter !== ''): ?><input type="hidden" name="inv_status" value="<?= e($invStatusFilter) ?>"><?php endif; ?>
        <?php if ($invMonthFilter !== ''): ?><input type="hidden" name="inv_month" value="<?= e($invMonthFilter) ?>"><?php endif; ?>
        <?php if ($invPage > 1): ?><input type="hidden" name="inv_page" value="<?= (int) $invPage ?>"><?php endif; ?>
        <div class="table-search">
            <i class="bi bi-search"></i>
            <input type="search" class="form-control form-control-sm" id="tableSearch" name="q" value="<?= e($q) ?>" placeholder="Search by service...">
        </div>
        <select class="form-select form-select-sm table-filter" id="statusFilter" name="status" onchange="this.form.requestSubmit()">
            <option value="">All statuses</option>
            <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="failed" <?= $statusFilter === 'failed' ? 'selected' : '' ?>>Failed</option>
            <option value="refunded" <?= $statusFilter === 'refunded' ? 'selected' : '' ?>>Refunded</option>
        </select>
        <select class="form-select form-select-sm table-filter" id="methodFilter" name="method" onchange="this.form.requestSubmit()">
            <option value="">All methods</option>
            <option value="stripe" <?= $methodFilter === 'stripe' ? 'selected' : '' ?>>Stripe</option>
            <option value="bank_transfer" <?= $methodFilter === 'bank_transfer' ? 'selected' : '' ?>>Bank Transfer</option>
            <option value="cash" <?= $methodFilter === 'cash' ? 'selected' : '' ?>>Cash</option>
            <option value="check" <?= $methodFilter === 'check' ? 'selected' : '' ?>>Check</option>
            <option value="other" <?= $methodFilter === 'other' ? 'selected' : '' ?>>Other</option>
        </select>
        <select class="form-select form-select-sm table-filter table-filter-month" id="monthFilter" name="month" onchange="this.form.requestSubmit()">
            <option value="">All months</option>
            <?php foreach ($paymentMonths as $monthKey => $monthLabel): ?>
                <option value="<?= e($monthKey) ?>" <?= $monthFilter === (string) $monthKey ? 'selected' : '' ?>><?= e($monthLabel) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <div class="card-body p-0">
        <?php if (empty($payments)): ?>
            <div class="empty-state py-5">
                <i class="bi bi-credit-card"></i>
                <p class="mb-0">No payments recorded yet.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table saas-table appointment-list-table mb-0" id="dataTable">
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Client</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Status</th>
                            <th>Paid At</th>
                            <th>Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <?php
                            $payStatus = paymentStatusValue($payment);
                            $paidAt = $payment['paid_at'] ?? $payment['created_at'] ?? null;
                            $paidMonth = $paidAt ? date('m', strtotime((string) $paidAt)) : '';
                            $searchBlob = caseRowSearchBlob($payment, [
                                $payment['invoice_number'] ?? '',
                                clientFullName($payment),
                                $payment['receipt_number'] ?? '',
                                $payment['payment_method'] ?? '',
                            ]);
                            ?>
                            <tr data-status="<?= e($payStatus) ?>" data-method="<?= e($payment['payment_method'] ?? '') ?>" data-month="<?= e($paidMonth) ?>" data-search="<?= e($searchBlob) ?>">
                                <td>
                                    <span class="table-primary"><?= e($payment['invoice_number']) ?></span>
                                    <span class="table-secondary d-block"><?= formatCurrency((float) $payment['invoice_total']) ?></span>
                                </td>
                                <td><?= e(clientFullName($payment)) ?></td>
                                <td><span class="table-primary"><?= formatCurrency((float) $payment['amount']) ?></span></td>
                                <td><?= paymentMethodBadge($payment['payment_method'] ?? 'other') ?></td>
                                <td><?= paymentStatusBadge($payStatus) ?></td>
                                <td class="text-muted"><?= formatDateTime($payment['paid_at'] ?? $payment['created_at']) ?></td>
                                <td>
                                    <?php if (!empty($payment['receipt_id'])): ?>
                                        <a href="<?= url('actions/receipt-download.php?id=' . (int) $payment['receipt_id']) ?>" class="btn btn-soft btn-sm" target="_blank">
                                            <i class="bi bi-receipt"></i> <?= e($payment['receipt_number']) ?>
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
            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
                <small class="text-muted">
                    Showing <?= count($payments) ?> of <?= $totalPayments ?> transactions
                </small>
                <?= renderPaginationNav($page, $totalPages) ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="saas-card mt-4">
    <div class="saas-card-header appointment-list-header">
        <div>
            <h2 class="saas-card-title">Invoices</h2>
            <p class="saas-card-subtitle mb-0"><?= $totalInvoices ?> invoice<?= $totalInvoices === 1 ? '' : 's' ?></p>
        </div>
    </div>
    <form method="get" class="table-toolbar">
        <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
        <?php if ($statusFilter !== ''): ?><input type="hidden" name="status" value="<?= e($statusFilter) ?>"><?php endif; ?>
        <?php if ($methodFilter !== ''): ?><input type="hidden" name="method" value="<?= e($methodFilter) ?>"><?php endif; ?>
        <?php if ($monthFilter !== ''): ?><input type="hidden" name="month" value="<?= e($monthFilter) ?>"><?php endif; ?>
        <?php if ($page > 1): ?><input type="hidden" name="page" value="<?= (int) $page ?>"><?php endif; ?>
        <div class="table-search">
            <i class="bi bi-search"></i>
            <input type="search" class="form-control form-control-sm" name="inv_q" value="<?= e($invQ) ?>" placeholder="Search by invoice or client...">
        </div>
        <select class="form-select form-select-sm table-filter" name="inv_status" onchange="this.form.requestSubmit()">
            <option value="">All statuses</option>
            <option value="pending" <?= $invStatusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="partially_paid" <?= $invStatusFilter === 'partially_paid' ? 'selected' : '' ?>>Partially Paid</option>
            <option value="overdue" <?= $invStatusFilter === 'overdue' ? 'selected' : '' ?>>Overdue</option>
            <option value="failed" <?= $invStatusFilter === 'failed' ? 'selected' : '' ?>>Failed</option>
            <option value="failed" <?= $invStatusFilter === 'failed' ? 'selected' : '' ?>>Failed</option>
        </select>
        <select class="form-select form-select-sm table-filter table-filter-month" name="inv_month" onchange="this.form.requestSubmit()">
            <option value="">All months</option>
            <?php foreach ($paymentMonths as $monthKey => $monthLabel): ?>
                <option value="<?= e($monthKey) ?>" <?= $invMonthFilter === (string) $monthKey ? 'selected' : '' ?>><?= e($monthLabel) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <div class="card-body p-0">
        <?php if (empty($invoices)): ?>
            <div class="empty-state py-5">
                <i class="bi bi-receipt"></i>
                <p class="mb-0">No invoices found.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table saas-table appointment-list-table mb-0">
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Client</th>
                            <th>Total</th>
                            <th>Amount Due</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Issued</th>
                            <th>Due Date</th>
                            <th>PDF</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice): ?>
                            <?php
                            $invStatus = invoiceStatusValue($invoice);
                            $amountDue = CaseService::getInvoiceRemainingBalance($invoice);
                            $caseId = (int) ($invoice['case_id'] ?? 0);
                            $payInfo = PaymentGatewayService::paymentInfoSummary($invoice);
                            ?>
                            <tr>
                                <td>
                                    <span class="table-primary"><?= e($invoice['invoice_number']) ?></span>
                                    <?php if (!empty($invoice['case_number'])): ?>
                                        <span class="table-secondary d-block"><?= e($invoice['case_number']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e(clientFullName($invoice)) ?></td>
                                <td><span class="table-primary"><?= formatCurrency((float) $invoice['total']) ?></span></td>
                                <td><span class="table-primary"><?= formatCurrency($amountDue) ?></span></td>
                                <td><?= invoiceGatewayStatusBadge($invoice) ?></td>
                                <td>
                                    <?php if ($payInfo['has_link'] || $payInfo['transaction_reference'] !== '' || !empty($payInfo['payment_date'])): ?>
                                        <button type="button" class="btn btn-soft btn-sm" data-bs-toggle="modal" data-bs-target="#invoicePaymentModal" data-invoice-number="<?= e($invoice['invoice_number']) ?>" data-invoice-status="<?= e($payInfo['status_label']) ?>" data-invoice-due="<?= e(formatCurrency($payInfo['amount_due'])) ?>" data-invoice-total="<?= e(formatCurrency($payInfo['total'])) ?>" data-payment-link="<?= e($payInfo['payment_link']) ?>" data-payment-date="<?= !empty($payInfo['payment_date']) ? e(formatDateTime($payInfo['payment_date'])) : '' ?>" data-transaction-ref="<?= e($payInfo['transaction_reference']) ?>">
                                            <i class="bi bi-credit-card"></i> Details
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted"><?= formatDate($invoice['issue_date'] ?? $invoice['created_at']) ?></td>
                                <td class="text-muted"><?= !empty($invoice['due_date']) ? formatDate($invoice['due_date']) : '—' ?></td>
                                <td>
                                    <?php if (!empty($invoice['pdf_path'])): ?>
                                        <a href="<?= url('actions/document-download.php?path=' . urlencode($invoice['pdf_path'])) ?>" class="btn btn-soft btn-sm" target="_blank" rel="noopener">
                                            <i class="bi bi-file-pdf"></i> View
                                        </a>
                                    <?php elseif ($caseId > 0): ?>
                                        <a href="<?= url('pages/case-view.php?id=' . $caseId . '#invoices') ?>" class="btn btn-soft btn-sm">
                                            <i class="bi bi-folder2-open"></i> Case
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
            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
                <small class="text-muted">
                    Showing <?= count($invoices) ?> of <?= $totalInvoices ?> invoices
                </small>
                <?= renderPaginationNav($invPage, $totalInvoicePages, 'inv_page') ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($pendingInvoices)): ?>
<div class="modal fade" id="recordPaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" action="<?= url('actions/payment-action.php') ?>" class="modal-content">
            <?= CSRF::field() ?>
            <input type="hidden" name="action" value="record_payment">
            <div class="modal-header">
                <h5 class="modal-title">Record Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Invoice</label>
                    <select name="invoice_id" class="form-select" required>
                        <?php foreach ($pendingInvoices as $inv): ?>
                            <?php $remaining = CaseService::getInvoiceRemainingBalance($inv); ?>
                            <option value="<?= (int) $inv['id'] ?>">
                                <?= e($inv['invoice_number']) ?> — <?= e(clientFullName($inv)) ?> — <?= formatCurrency($remaining) ?> due
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Amount</label>
                    <input type="number" step="0.01" min="0" name="amount" class="form-control" placeholder="Leave blank to pay remaining balance">
                </div>
                <div class="mb-3">
                    <label class="form-label">Payment Method</label>
                    <select name="payment_method" class="form-select">
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="cash">Cash</option>
                        <option value="check">Check</option>
                        <option value="stripe">Stripe (manual entry)</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="mb-0">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Record & Generate Receipt</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="modal fade" id="invoicePaymentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Payment information</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <dl class="row mb-0 invoice-payment-dl">
                    <dt class="col-sm-4">Invoice</dt>
                    <dd class="col-sm-8" id="ipmInvoiceNumber">—</dd>
                    <dt class="col-sm-4">Status</dt>
                    <dd class="col-sm-8" id="ipmStatus">—</dd>
                    <dt class="col-sm-4">Amount due</dt>
                    <dd class="col-sm-8" id="ipmDue">—</dd>
                    <dt class="col-sm-4">Invoice total</dt>
                    <dd class="col-sm-8" id="ipmTotal">—</dd>
                    <dt class="col-sm-4">Paid at</dt>
                    <dd class="col-sm-8" id="ipmPaidAt">—</dd>
                    <dt class="col-sm-4">Reference</dt>
                    <dd class="col-sm-8"><code id="ipmRef">—</code></dd>
                    <dt class="col-sm-4">Payment link</dt>
                    <dd class="col-sm-8" id="ipmLinkWrap">—</dd>
                </dl>
            </div>
        </div>
    </div>
</div>

<?php
$pageScripts = '<script>
document.getElementById("invoicePaymentModal")?.addEventListener("show.bs.modal", function(e) {
    var btn = e.relatedTarget;
    if (!btn) return;
    document.getElementById("ipmInvoiceNumber").textContent = btn.dataset.invoiceNumber || "—";
    document.getElementById("ipmStatus").textContent = btn.dataset.invoiceStatus || "—";
    document.getElementById("ipmDue").textContent = btn.dataset.invoiceDue || "—";
    document.getElementById("ipmTotal").textContent = btn.dataset.invoiceTotal || "—";
    document.getElementById("ipmPaidAt").textContent = btn.dataset.paymentDate || "—";
    document.getElementById("ipmRef").textContent = btn.dataset.transactionRef || "—";
    var link = btn.dataset.paymentLink || "";
    var linkWrap = document.getElementById("ipmLinkWrap");
    if (link) {
        linkWrap.innerHTML = "<a href=\"" + link.replace(/"/g, "&quot;") + "\" target=\"_blank\" rel=\"noopener\">Open payment page</a>";
    } else {
        linkWrap.textContent = "—";
    }
});
</script>';

require __DIR__ . '/../includes/footer.php'; ?>
