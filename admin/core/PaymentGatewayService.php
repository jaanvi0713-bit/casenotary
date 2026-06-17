<?php
declare(strict_types=1);

/**
 * Payment gateway abstraction — prototype (mock) driver by default.
 * Replace MockPaymentGateway with StripePaymentGateway (etc.) later without changing callers.
 */
class PaymentGatewayService
{
    public static function isEnabled(): bool
    {
        return true;
    }

    public static function assignPaymentLink(int $invoiceId): string
    {
        $invoice = Database::fetch('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
        if (!$invoice) {
            throw new RuntimeException('Invoice not found.');
        }

        $status = invoiceStatusValue($invoice);
        if (in_array($status, ['paid'], true)) {
            throw new RuntimeException('Cannot add a payment link to a paid invoice.');
        }

        if (!empty($invoice['payment_token'])) {
            $url = self::buildPaymentUrl((string) $invoice['payment_token']);
            if ((string) ($invoice['payment_link'] ?? '') !== $url) {
                self::updateInvoiceColumns($invoiceId, ['payment_link' => $url]);
                $caseId = (int) ($invoice['case_id'] ?? 0);
                if ($caseId > 0) {
                    CaseService::regenerateInvoiceHtml($caseId, $invoiceId);
                }
            }

            return $url;
        }

        if (!empty($invoice['payment_link'])) {
            return (string) $invoice['payment_link'];
        }

        $token = self::generateToken();
        $url   = self::buildPaymentUrl($token);

        $updates = [
            'payment_link' => $url,
            'payment_token' => $token,
        ];

        $statusCol = invoiceStatusColumn();
        $updates[$statusCol] = 'pending';

        if (Database::columnExists('invoices', 'payment_date')) {
            $updates['payment_date'] = null;
        }
        if (Database::columnExists('invoices', 'transaction_reference')) {
            $updates['transaction_reference'] = null;
        }

        self::updateInvoiceColumns($invoiceId, $updates);

        $caseId = (int) ($invoice['case_id'] ?? 0);
        if ($caseId > 0) {
            CaseService::regenerateInvoiceHtml($caseId, $invoiceId);
        }

        return $url;
    }

    public static function findInvoiceByToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        if (Database::columnExists('invoices', 'payment_token')) {
            $row = Database::fetch('SELECT * FROM invoices WHERE payment_token = ?', [$token]);
            if ($row) {
                return $row;
            }
        }

        if (Database::columnExists('invoices', 'payment_link')) {
            $row = Database::fetch('SELECT * FROM invoices WHERE payment_link LIKE ?', ['%token=' . $token . '%']);
            if ($row) {
                return $row;
            }
        }

        return null;
    }

    public static function completePayment(string $token): array
    {
        $invoice = self::findInvoiceByToken($token);
        if (!$invoice) {
            return ['success' => false, 'message' => 'Invalid or expired payment link.'];
        }

        $invoiceId = (int) $invoice['id'];
        $status    = invoiceStatusValue($invoice);

        if ($status === 'paid') {
            return ['success' => true, 'message' => 'This invoice is already paid.', 'invoice' => $invoice];
        }

        $remaining = CaseService::getInvoiceRemainingBalance($invoice);
        if ($remaining <= 0) {
            return ['success' => false, 'message' => 'Nothing left to pay on this invoice.'];
        }

        $transactionRef = self::generateTransactionReference();

        $result = CaseService::recordPayment($invoiceId, [
            'amount'          => $remaining,
            'payment_method'  => 'other',
            'stripe_payment_id' => $transactionRef,
            'notes'           => 'Online payment (demo gateway)',
        ], 0);

        if (empty($result['success'])) {
            return ['success' => false, 'message' => $result['message'] ?? 'Could not record payment.'];
        }

        $statusCol = invoiceStatusColumn();
        $patch = [$statusCol => 'paid'];
        if (Database::columnExists('invoices', 'payment_date')) {
            $patch['payment_date'] = date('Y-m-d H:i:s');
        }
        if (Database::columnExists('invoices', 'transaction_reference')) {
            $patch['transaction_reference'] = $transactionRef;
        }
        self::updateInvoiceColumns($invoiceId, $patch);

        $caseId = (int) ($invoice['case_id'] ?? 0);
        if ($caseId > 0) {
            CaseService::regenerateInvoiceHtml($caseId, $invoiceId);
        }

        $invoice = Database::fetch('SELECT * FROM invoices WHERE id = ?', [$invoiceId]) ?: $invoice;

        $receiptId = (int) ($result['receipt_id'] ?? 0);
        if ($receiptId <= 0 && !empty($result['payment_id'])) {
            $receiptId = CaseService::generateReceipt((int) $result['payment_id'], $invoice);
        }

        return [
            'success' => true,
            'message' => 'Payment completed successfully.',
            'invoice' => $invoice,
            'transaction_reference' => $transactionRef,
            'payment_id' => (int) ($result['payment_id'] ?? 0),
            'receipt_id' => $receiptId,
        ];
    }

    public static function failPayment(string $token): array
    {
        $invoice = self::findInvoiceByToken($token);
        if (!$invoice) {
            return ['success' => false, 'message' => 'Invalid or expired payment link.'];
        }

        $invoiceId = (int) $invoice['id'];
        $status    = invoiceStatusValue($invoice);

        if ($status === 'paid') {
            return ['success' => false, 'message' => 'This invoice is already paid.'];
        }

        $statusCol = invoiceStatusColumn();
        $patch = [$statusCol => 'failed'];
        if (Database::columnExists('invoices', 'transaction_reference')) {
            $patch['transaction_reference'] = null;
        }
        self::updateInvoiceColumns($invoiceId, $patch);

        $caseId = (int) ($invoice['case_id'] ?? 0);
        if ($caseId > 0) {
            CaseService::regenerateInvoiceHtml($caseId, $invoiceId);
        }

        $invoice = Database::fetch('SELECT * FROM invoices WHERE id = ?', [$invoiceId]) ?: $invoice;

        return [
            'success' => true,
            'message' => 'Payment was not completed. You can try again.',
            'invoice' => $invoice,
        ];
    }

    public static function invoiceHasPayableLink(array $invoice): bool
    {
        if (empty($invoice['payment_link']) && empty($invoice['payment_token'])) {
            return false;
        }

        $status = invoiceStatusValue($invoice);

        return in_array($status, ['pending', 'partially_paid', 'overdue', 'failed'], true)
            && CaseService::getInvoiceRemainingBalance($invoice) > 0;
    }

    public static function paymentInfoSummary(array $invoice): array
    {
        $status = invoiceStatusValue($invoice);

        return [
            'status'                  => $status,
            'status_label'            => self::statusLabel($status),
            'has_link'                => !empty($invoice['payment_link']),
            'payment_link'            => (string) ($invoice['payment_link'] ?? ''),
            'payment_date'            => $invoice['payment_date'] ?? null,
            'transaction_reference'   => (string) ($invoice['transaction_reference'] ?? ''),
            'amount_due'              => CaseService::getInvoiceRemainingBalance($invoice),
            'total'                   => (float) ($invoice['total'] ?? 0),
        ];
    }

    public static function statusLabel(string $status): string
    {
        $map = [
            'pending'        => 'Pending',
            'paid'           => 'Paid',
            'failed'         => 'Failed',
            'partially_paid' => 'Partially Paid',
            'overdue'        => 'Overdue',
        ];

        return $map[$status] ?? ucfirst(str_replace('_', ' ', $status));
    }

    private static function generateToken(): string
    {
        return bin2hex(random_bytes(24));
    }

    private static function generateTransactionReference(): string
    {
        return 'DEMO-' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 12));
    }

    public static function checkoutUrl(string $token): string
    {
        return self::buildPaymentUrl($token);
    }

    public static function repairPaymentLinks(): int
    {
        if (!Database::columnExists('invoices', 'payment_token') || !Database::columnExists('invoices', 'payment_link')) {
            return 0;
        }

        $rows = Database::fetchAll(
            "SELECT id, case_id, payment_token, payment_link FROM invoices WHERE payment_token IS NOT NULL AND payment_token <> ''"
        );
        $fixed = 0;

        foreach ($rows as $row) {
            $token = (string) $row['payment_token'];
            $url   = self::buildPaymentUrl($token);
            if ((string) ($row['payment_link'] ?? '') === $url) {
                continue;
            }

            self::updateInvoiceColumns((int) $row['id'], ['payment_link' => $url]);
            $caseId = (int) ($row['case_id'] ?? 0);
            if ($caseId > 0) {
                CaseService::regenerateInvoiceHtml($caseId, (int) $row['id']);
            }
            $fixed++;
        }

        return $fixed;
    }

    private static function buildPaymentUrl(string $token): string
    {
        return rtrim(clientUrl('pages/pay-invoice.php'), '/') . '?token=' . urlencode($token);
    }

    private static function updateInvoiceColumns(int $invoiceId, array $columns): void
    {
        $sets   = [];
        $params = [];

        foreach ($columns as $col => $value) {
            if (!Database::columnExists('invoices', (string) $col)) {
                continue;
            }
            $sets[]   = "`{$col}` = ?";
            $params[] = $value;
        }

        if ($sets === []) {
            return;
        }

        $sets[]   = 'updated_at = NOW()';
        $params[] = $invoiceId;

        Database::query(
            'UPDATE invoices SET ' . implode(', ', $sets) . ' WHERE id = ?',
            $params
        );
    }
}
