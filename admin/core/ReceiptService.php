<?php

declare(strict_types=1);

class ReceiptService
{
    public static function fetchForAdmin(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        return Database::fetch(self::selectSql() . ' AND r.id = ?', [$id]) ?: null;
    }

    public static function fetchForClient(int $id, int $clientId): ?array
    {
        if ($id <= 0 || $clientId <= 0) {
            return null;
        }

        return Database::fetch(self::clientSelectSql() . ' AND r.id = ? AND i.client_id = ?', [$id, $clientId]) ?: null;
    }

    public static function fetchByPaymentToken(int $receiptId, string $token): ?array
    {
        $token = trim($token);
        if ($receiptId <= 0 || $token === '') {
            return null;
        }

        if (!Database::columnExists('invoices', 'payment_token')) {
            return null;
        }

        return Database::fetch(
            self::clientSelectSql() . ' AND r.id = ? AND i.payment_token = ?',
            [$receiptId, $token]
        ) ?: null;
    }

    private static function clientSelectSql(): string
    {
        $postal = clientPostalSelectSql('cl');

        return 'SELECT r.*, i.id AS invoice_id, i.invoice_number, i.total AS invoice_total, i.case_id,
            cl.first_name, cl.last_name, cl.email AS client_email, cl.company_name,
            cl.address, cl.city, cl.state, ' . $postal . ', cl.country,
            p.payment_method, p.paid_at, p.notes AS payment_notes, p.amount AS payment_amount
         FROM receipts r
         INNER JOIN payments p ON p.id = r.payment_id
         INNER JOIN invoices i ON i.id = p.invoice_id
         INNER JOIN clients cl ON cl.id = i.client_id
         LEFT JOIN cases cs ON cs.id = i.case_id
         WHERE 1=1';
    }

    private static function selectSql(): string
    {
        $postal = clientPostalSelectSql('cl');
        $tenant = TenantService::isEnabled()
            ? ' AND cs.company_id = ' . TenantService::id()
            : '';

        return 'SELECT r.*, i.id AS invoice_id, i.invoice_number, i.total AS invoice_total, i.case_id,
            cl.first_name, cl.last_name, cl.email AS client_email, cl.company_name,
            cl.address, cl.city, cl.state, ' . $postal . ', cl.country,
            p.payment_method, p.paid_at, p.notes AS payment_notes, p.amount AS payment_amount
         FROM receipts r
         JOIN payments p ON p.id = r.payment_id
         JOIN invoices i ON i.id = p.invoice_id
         LEFT JOIN cases cs ON cs.id = i.case_id
         JOIN clients cl ON cl.id = i.client_id
         WHERE 1=1' . $tenant;
    }

    public static function renderHtml(array $receipt): string
    {
        $invoiceId = (int) ($receipt['invoice_id'] ?? 0);
        $invoice   = $invoiceId > 0
            ? (Database::fetch('SELECT * FROM invoices WHERE id = ?', [$invoiceId]) ?: [])
            : [];

        $caseId = (int) ($invoice['case_id'] ?? $receipt['case_id'] ?? 0);
        $case   = $caseId > 0 ? (CaseService::getCaseById($caseId) ?: []) : $receipt;

        if ($case === []) {
            $case = $receipt;
        }

        return FinancialDocumentRenderer::renderReceipt($receipt, $case, $invoice);
    }
}
