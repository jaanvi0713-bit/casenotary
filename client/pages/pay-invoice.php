<?php
require_once __DIR__ . '/../core/bootstrap.php';

$token = trim((string) ($_GET['token'] ?? ''));
$invoice = $token !== '' ? PaymentGatewayService::findInvoiceByToken($token) : null;

$pageTitle = 'Pay Invoice';
$successMsg = flash('success');
$errorMsg   = flash('error');

if (!$invoice) {
    http_response_code(404);
}

$client = null;
$case = null;
$amountDue = 0.0;
$status = 'pending';
$canPay = false;
$receipt = null;

if ($invoice) {
    $client = Database::fetch('SELECT * FROM clients WHERE id = ?', [(int) $invoice['client_id']]);
    if (!empty($invoice['case_id'])) {
        $case = Database::fetch('SELECT case_number, title FROM cases WHERE id = ?', [(int) $invoice['case_id']]);
    }
    $amountDue = CaseService::getInvoiceRemainingBalance($invoice);
    $status = invoiceStatusValue($invoice);
    $canPay = PaymentGatewayService::invoiceHasPayableLink($invoice);
    if ($status === 'paid') {
        $receipt = Database::fetch(
            'SELECT r.id, r.receipt_number
             FROM receipts r
             INNER JOIN payments p ON p.id = r.payment_id
             WHERE p.invoice_id = ?
             ORDER BY r.id DESC
             LIMIT 1',
            [(int) $invoice['id']]
        );
    }
}

$company = getCompanySettings();
$brandName = companyBrandName($company);

require __DIR__ . '/../includes/header.php';
?>

<div class="payment-gateway-page">
    <?php if (!$invoice): ?>
        <div class="payment-gateway-card payment-gateway-card--error">
            <div class="payment-gateway-card__icon"><i class="bi bi-exclamation-triangle"></i></div>
            <h1 class="payment-gateway-card__title">Payment link not found</h1>
            <p class="payment-gateway-card__text">This link may have expired or is invalid. Contact <?= e($brandName) ?> if you need assistance.</p>
            <?php if (Auth::isClient()): ?>
                <a href="<?= clientUrl('pages/payments.php') ?>" class="btn btn-primary">Back to invoices</a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="payment-gateway-demo-banner">
            <i class="bi bi-info-circle"></i>
            <span><strong>Demo mode</strong> — payments are simulated. No real charges are made.</span>
        </div>

        <?php if ($successMsg): ?>
            <div class="alert alert-success"><?= e($successMsg) ?></div>
        <?php endif; ?>
        <?php if ($errorMsg): ?>
            <div class="alert alert-danger"><?= e($errorMsg) ?></div>
        <?php endif; ?>

        <div class="payment-gateway-card">
            <div class="payment-gateway-card__header">
                <div>
                    <p class="payment-gateway-card__eyebrow"><?= e($brandName) ?></p>
                    <h1 class="payment-gateway-card__title">Pay Invoice</h1>
                </div>
                <?= invoiceGatewayStatusBadge($invoice) ?>
            </div>

            <div class="payment-gateway-details">
                <div class="payment-gateway-detail">
                    <span class="payment-gateway-detail__label">Invoice</span>
                    <strong class="payment-gateway-detail__value"><?= e($invoice['invoice_number']) ?></strong>
                </div>
                <?php if ($case): ?>
                <div class="payment-gateway-detail">
                    <span class="payment-gateway-detail__label">Case</span>
                    <strong class="payment-gateway-detail__value"><?= e($case['case_number']) ?></strong>
                    <?php if (!empty($case['title'])): ?>
                        <span class="payment-gateway-detail__sub"><?= e($case['title']) ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if ($client): ?>
                <div class="payment-gateway-detail">
                    <span class="payment-gateway-detail__label">Bill to</span>
                    <strong class="payment-gateway-detail__value"><?= e(clientFullName($client)) ?></strong>
                </div>
                <?php endif; ?>
                <div class="payment-gateway-detail">
                    <span class="payment-gateway-detail__label">Due date</span>
                    <strong class="payment-gateway-detail__value"><?= !empty($invoice['due_date']) ? formatDate($invoice['due_date']) : '—' ?></strong>
                </div>
                <div class="payment-gateway-detail payment-gateway-detail--amount">
                    <span class="payment-gateway-detail__label">Amount due</span>
                    <strong class="payment-gateway-detail__value payment-gateway-detail__value--lg"><?= formatCurrency($amountDue) ?></strong>
                </div>
            </div>

            <?php if ($status === 'paid'): ?>
                <div class="payment-gateway-success">
                    <i class="bi bi-check-circle-fill"></i>
                    <div>
                        <strong>Payment received</strong>
                        <?php if (!empty($invoice['payment_date'])): ?>
                            <p>Paid on <?= formatDateTime($invoice['payment_date']) ?></p>
                        <?php endif; ?>
                        <?php if (!empty($invoice['transaction_reference'])): ?>
                            <p class="payment-gateway-ref">Reference: <code><?= e($invoice['transaction_reference']) ?></code></p>
                        <?php endif; ?>
                        <?php if ($receipt): ?>
                            <p class="mb-2">Receipt <strong><?= e($receipt['receipt_number']) ?></strong> has been generated.</p>
                            <a href="<?= clientUrl('actions/receipt-download.php?id=' . (int) $receipt['id'] . '&token=' . urlencode($token)) ?>" class="btn btn-primary btn-sm" target="_blank" rel="noopener">
                                <i class="bi bi-receipt"></i> Download receipt
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php elseif ($status === 'failed'): ?>
                <div class="payment-gateway-failed">
                    <i class="bi bi-x-circle-fill"></i>
                    <div>
                        <strong>Last payment attempt failed</strong>
                        <p>You can try again below.</p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($canPay): ?>
                <form method="post" action="<?= clientUrl('actions/payment-gateway-action.php') ?>" class="payment-gateway-actions">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <input type="hidden" name="payment_action" value="complete">
                    <button type="submit" class="btn btn-primary btn-lg payment-gateway-btn payment-gateway-btn--success">
                        <i class="bi bi-shield-check"></i> Complete Payment (Demo)
                    </button>
                </form>
                <form method="post" action="<?= clientUrl('actions/payment-gateway-action.php') ?>" class="payment-gateway-actions mt-2">
                    <?= CSRF::field() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <input type="hidden" name="payment_action" value="fail">
                    <button type="submit" class="btn btn-outline-danger payment-gateway-btn w-100">
                        <i class="bi bi-x-lg"></i> Payment Failed (Demo)
                    </button>
                </form>
                <p class="payment-gateway-footnote">Secured checkout prototype — replace with Stripe, Juice, or PayPal when live credentials are available.</p>
            <?php elseif ($status !== 'paid'): ?>
                <p class="payment-gateway-footnote mb-0">This invoice cannot be paid online at the moment.</p>
            <?php endif; ?>

            <?php if (!empty($invoice['pdf_path'])): ?>
                <div class="payment-gateway-invoice-link">
                    <a href="<?= adminUrl('actions/document-download.php?path=' . urlencode($invoice['pdf_path'])) ?>" target="_blank" rel="noopener" class="btn btn-soft btn-sm">
                        <i class="bi bi-file-earmark-text"></i> View invoice
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
