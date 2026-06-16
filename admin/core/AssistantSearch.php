<?php

declare(strict_types=1);

class AssistantSearch
{
    /** @return array{content: string} */
    public static function handle(string $message): array
    {
        $term = self::extractSearchTerm($message);
        if ($term === '') {
            return [
                'content' => 'What should I search for? Examples: _Find clients named Jean_, _Invoices for Case #1024_, _Receipts for Rs 25,000_.',
            ];
        }

        $sections = [];

        if (self::wantsEntity($message, 'client')) {
            $sections[] = self::searchClients($term);
        }
        if (self::wantsEntity($message, 'case')) {
            $sections[] = self::searchCases($term);
        }
        if (self::wantsEntity($message, 'invoice') || self::wantsEntity($message, 'payment') || self::wantsEntity($message, 'receipt')) {
            $sections[] = self::searchInvoicesAndPayments($term, $message);
        }
        if (self::wantsEntity($message, 'document') || self::wantsEntity($message, 'upload')) {
            $sections[] = self::searchDocuments($term);
        }

        if ($sections === []) {
            $sections = [
                self::searchClients($term),
                self::searchCases($term),
                self::searchInvoicesAndPayments($term, $message),
                self::searchDocuments($term),
            ];
        }

        $sections = array_values(array_filter($sections));

        if ($sections === []) {
            return ['content' => 'No records matched **“' . $term . '”**. Try a client name, case number, invoice number, or amount.'];
        }

        return ['content' => implode("\n\n", $sections)];
    }

    private static function extractSearchTerm(string $message): string
    {
        $message = assistantNormalizeUserMessage($message);
        $patterns = [
            '/\bnamed\s+(.+)$/i',
            '/\bfor\s+(.+)$/i',
            '/\bmatching\s+(.+)$/i',
            '/\bsearch(?:ing)?\s+(?:for\s+)?(.+)$/i',
            '/\bfind(?: all)?\s+(?:clients?|cases?|invoices?|receipts?|documents?)?\s*(?:named|called|for)?\s*(.+)$/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $message, $matches)) {
                $term = trim($matches[1], " \t.?!");
                if ($term !== '') {
                    return $term;
                }
            }
        }

        return trim(preg_replace(
            '/\b(find|search|look up|lookup|show me|list|clients?|cases?|invoices?|receipts?|documents?|uploads?)\b/i',
            ' ',
            $message
        ) ?? $message);
    }

    private static function wantsEntity(string $message, string $entity): bool
    {
        return (bool) preg_match('/\b' . preg_quote($entity, '/') . 's?\b/i', $message);
    }

    private static function searchClients(string $term): string
    {
        $clients = assistantFindClients($term, 8);
        if ($clients === []) {
            return '';
        }

        $lines = ['**Clients** (' . count($clients) . ')', ''];
        foreach ($clients as $client) {
            $company = !empty($client['company_name']) ? ' (' . $client['company_name'] . ')' : '';
            $lines[] = '• **' . clientFullName($client) . '**' . $company
                . ' — ' . assistantAdminLink('pages/client-form.php?id=' . (int) $client['id'], 'Open');
        }

        return implode("\n", $lines);
    }

    private static function searchCases(string $term): string
    {
        $where = [
            '(LOWER(cs.case_number) LIKE LOWER(?)
                OR LOWER(cs.title) LIKE LOWER(?)
                OR LOWER(cs.description) LIKE LOWER(?)
                OR LOWER(cl.first_name) LIKE LOWER(?)
                OR LOWER(cl.last_name) LIKE LOWER(?))',
        ];
        $like = '%' . $term . '%';
        $params = [$like, $like, $like, $like, $like];
        appendCaseTenantScope($where, $params, 'cs', 'cl');
        appendAssignedCaseScope($where, $params, 'cs');
        $params[] = 10;

        $cases = Database::fetchAll(
            'SELECT cs.id, cs.case_number, cs.title, cs.status, cs.updated_at, cl.first_name, cl.last_name
             FROM cases cs JOIN clients cl ON cl.id = cs.client_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY cs.updated_at DESC LIMIT ?',
            $params
        );

        if ($cases === []) {
            return '';
        }

        $lines = ['**Cases** (' . count($cases) . ')', ''];
        foreach ($cases as $case) {
            $status = ucwords(str_replace('_', ' ', (string) ($case['status'] ?? '')));
            $lines[] = '• **' . ($case['case_number'] ?? 'Case') . '** — '
                . ($case['title'] ?? '') . " (*{$status}*, updated " . formatDateTime($case['updated_at'] ?? '') . ') — '
                . assistantAdminLink('pages/case-view.php?id=' . (int) $case['id'], 'Open');
        }

        return implode("\n", $lines);
    }

    private static function searchInvoicesAndPayments(string $term, string $message): string
    {
        $amount = self::extractAmount($term) ?? self::extractAmount($message);
        $statusCol = invoiceStatusColumn();
        $paymentStatus = paymentStatusColumn();

        $invoiceWhere = [
            '(LOWER(i.invoice_number) LIKE LOWER(?)
                OR LOWER(cs.case_number) LIKE LOWER(?)
                OR LOWER(cl.first_name) LIKE LOWER(?)
                OR LOWER(cl.last_name) LIKE LOWER(?))',
        ];
        $like = '%' . $term . '%';
        $invoiceParams = [$like, $like, $like, $like];
        TenantService::appendClientScope($invoiceWhere, $invoiceParams, 'cl');
        if ($amount !== null) {
            $invoiceWhere[] = 'ABS(i.total - ?) < 0.01';
            $invoiceParams[] = $amount;
        }
        $invoiceParams[] = 8;

        $invoices = Database::fetchAll(
            "SELECT i.invoice_number, i.total, i.{$statusCol} AS invoice_status, cl.first_name, cl.last_name, cs.case_number
             FROM invoices i
             JOIN clients cl ON cl.id = i.client_id
             LEFT JOIN cases cs ON cs.id = i.case_id
             WHERE " . implode(' AND ', $invoiceWhere) . '
             ORDER BY i.created_at DESC LIMIT ?',
            $invoiceParams
        );

        $paymentWhere = [
            '(LOWER(i.invoice_number) LIKE LOWER(?)
                OR LOWER(cl.first_name) LIKE LOWER(?)
                OR LOWER(cl.last_name) LIKE LOWER(?))',
        ];
        $paymentParams = [$like, $like, $like];
        TenantService::appendClientScope($paymentWhere, $paymentParams, 'cl');
        if ($amount !== null) {
            $paymentWhere[] = 'ABS(p.amount - ?) < 0.01';
            $paymentParams[] = $amount;
        }
        $paymentParams[] = 8;

        $payments = Database::fetchAll(
            "SELECT p.amount, p.paid_at, p.{$paymentStatus} AS payment_status, i.invoice_number, cl.first_name, cl.last_name
             FROM payments p
             JOIN invoices i ON i.id = p.invoice_id
             JOIN clients cl ON cl.id = i.client_id
             WHERE " . implode(' AND ', $paymentWhere) . '
             ORDER BY p.paid_at DESC LIMIT ?',
            $paymentParams
        );

        $chunks = [];

        if ($invoices !== []) {
            $lines = ['**Invoices** (' . count($invoices) . ')', ''];
            foreach ($invoices as $row) {
                $lines[] = '• **' . ($row['invoice_number'] ?? 'Invoice') . '** — '
                    . formatCurrency((float) ($row['total'] ?? 0)) . ' — '
                    . clientFullName($row) . ' (' . ucfirst((string) ($row['invoice_status'] ?? '')) . ')';
            }
            $chunks[] = implode("\n", $lines);
        }

        if ($payments !== []) {
            $lines = ['**Receipts / payments** (' . count($payments) . ')', ''];
            foreach ($payments as $row) {
                $lines[] = '• **' . formatCurrency((float) ($row['amount'] ?? 0)) . '** — '
                    . ($row['invoice_number'] ?? 'Invoice') . ' — '
                    . clientFullName($row) . ' — ' . formatDateTime($row['paid_at'] ?? '');
            }
            $chunks[] = implode("\n", $lines);
        }

        return implode("\n\n", $chunks);
    }

    private static function searchDocuments(string $term): string
    {
        $where = [
            '(LOWER(d.original_name) LIKE LOWER(?)
                OR LOWER(d.document_type) LIKE LOWER(?)
                OR LOWER(cs.case_number) LIKE LOWER(?)
                OR LOWER(cs.title) LIKE LOWER(?))',
        ];
        $like = '%' . $term . '%';
        $params = [$like, $like, $like, $like];
        appendCaseTenantScope($where, $params, 'cs', 'cl');
        appendAssignedCaseScope($where, $params, 'cs');
        $params[] = 10;

        $rows = Database::fetchAll(
            'SELECT d.original_name, d.document_type, d.created_at, cs.id AS case_id, cs.case_number
             FROM documents d
             JOIN cases cs ON cs.id = d.case_id
             JOIN clients cl ON cl.id = cs.client_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY d.created_at DESC LIMIT ?',
            $params
        );

        if ($rows === []) {
            return '';
        }

        $lines = ['**Documents & uploads** (' . count($rows) . ')', ''];
        foreach ($rows as $row) {
            $lines[] = '• **' . ($row['original_name'] ?? 'Document') . '** — '
                . ($row['case_number'] ?? 'Case') . ' — ' . formatDateTime($row['created_at'] ?? '') . ' — '
                . assistantAdminLink('pages/case-view.php?id=' . (int) $row['case_id'], 'Open case');
        }

        return implode("\n", $lines);
    }

    private static function extractAmount(string $text): ?float
    {
        if (preg_match('/(?:rs\.?|mur|₨)?\s*([\d,]+(?:\.\d{1,2})?)/i', $text, $matches)) {
            $value = (float) str_replace(',', '', $matches[1]);

            return $value > 0 ? $value : null;
        }

        return null;
    }
}
