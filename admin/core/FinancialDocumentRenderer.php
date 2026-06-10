<?php

declare(strict_types=1);

/**
 * Unified HTML renderer for Invoices, Quotations, and Payment Receipts.
 */
class FinancialDocumentRenderer
{
    /**
     * @return list<array{description:string, quantity:float, unit_price:float, vat:float, total:float}>
     */
    public static function lineItemsFromBilling(array $billing): array
    {
        $items = [];

        foreach (CaseService::billingToDisplayServices($billing) as $service) {
            $net   = (float) ($service['net'] ?? 0);
            $gross = (float) ($service['gross'] ?? $service['fee'] ?? $net);
            $vat   = (float) ($service['vat_amount'] ?? $service['rate_amount'] ?? max(0, $gross - $net));

            if ($gross <= 0 && $net <= 0) {
                continue;
            }

            $items[] = [
                'description' => (string) ($service['type'] ?? 'Service'),
                'quantity'    => 1.0,
                'unit_price'  => $net > 0 ? $net : $gross,
                'vat'         => round($vat, 2),
                'total'       => round($gross, 2),
            ];
        }

        return $items;
    }

    /**
     * @param list<array<string, mixed>> $stored
     * @return list<array{description:string, quantity:float, unit_price:float, vat:float, total:float}>
     */
    public static function lineItemsFromStored(array $stored, array $billing = []): array
    {
        if ($billing !== []) {
            $fromBilling = self::lineItemsFromBilling($billing);
            if ($fromBilling !== []) {
                return $fromBilling;
            }
        }

        $items = [];
        foreach ($stored as $row) {
            if (!is_array($row)) {
                continue;
            }
            $qty       = (float) ($row['quantity'] ?? 1);
            $unit      = (float) ($row['unit_price'] ?? 0);
            $lineTotal = (float) ($row['line_total'] ?? $row['amount'] ?? ($qty * $unit));
            $desc      = trim((string) ($row['description'] ?? $row['type'] ?? 'Item'));
            $desc      = preg_replace('/\s*\((?:Non-VAT|VAT net)\)\s*$/i', '', $desc) ?? $desc;

            if ($unit <= 0 && $lineTotal > 0) {
                $unit = $qty > 0 ? $lineTotal / $qty : $lineTotal;
            }

            $items[] = [
                'description' => $desc,
                'quantity'    => $qty > 0 ? $qty : 1.0,
                'unit_price'  => round($unit, 2),
                'vat'         => round((float) ($row['vat'] ?? $row['vat_amount'] ?? 0), 2),
                'total'       => round($lineTotal, 2),
            ];
        }

        return $items;
    }

    /**
     * @return array{
     *   subtotal:float,
     *   vat_amount:float,
     *   net_excl_vat:float,
     *   net_incl_vat:float,
     *   amount_paid:float,
     *   amount_due:float,
     *   grand_total:float
     * }
     */
    public static function buildFinancialSummary(array $billing, array $options = []): array
    {
        $bt            = $billing['totals'] ?? [];
        $nonVatGross   = (float) ($bt['non_vat_subtotal'] ?? 0);
        $nonVatNet     = (float) ($bt['non_vat_net_subtotal'] ?? 0);
        $vatNet        = (float) ($bt['vat_net_subtotal'] ?? 0);
        $vatAmt        = (float) ($bt['vat_amount'] ?? 0);
        $nonVatRateAmt = (float) ($bt['non_vat_rate_amount'] ?? 0);
        $grand         = (float) ($bt['grand_total'] ?? 0);

        $taxTotal    = round($vatAmt + $nonVatRateAmt, 2);
        $netExclVat  = $nonVatGross > 0 ? $nonVatGross : $nonVatNet;
        $vatGross    = (float) ($bt['vat_gross_subtotal'] ?? 0);
        $netInclVat  = $vatGross > 0 ? $vatGross : round($vatNet + $vatAmt, 2);
        $subtotal    = round($nonVatNet + $vatNet, 2);

        if ($grand <= 0) {
            $grand = (float) ($options['grand_total'] ?? 0);
        }
        if ($grand <= 0 && $subtotal > 0) {
            $grand = round($subtotal + $taxTotal, 2);
        }
        if ($subtotal <= 0 && $grand > 0) {
            $subtotal = max(0, round($grand - $taxTotal, 2));
        }
        if ($taxTotal <= 0) {
            $taxTotal = (float) ($options['tax_amount'] ?? 0);
        }
        if ($netExclVat <= 0) {
            $netExclVat = max(0, round($grand - $netInclVat, 2));
        }
        if ($netInclVat <= 0 && $vatNet > 0) {
            $netInclVat = round($vatNet + $vatAmt, 2);
        }

        $amountPaid = round((float) ($options['amount_paid'] ?? 0), 2);
        $amountDue  = array_key_exists('amount_due', $options)
            ? round((float) $options['amount_due'], 2)
            : max(0, round($grand - $amountPaid, 2));

        return [
            'subtotal'      => round($subtotal, 2),
            'vat_amount'    => round($taxTotal, 2),
            'net_excl_vat'  => round($netExclVat, 2),
            'net_incl_vat'  => round($netInclVat, 2),
            'amount_paid'   => $amountPaid,
            'amount_due'    => $amountDue,
            'grand_total'   => round($grand, 2),
        ];
    }

    public static function renderQuotation(array $case, array $quotation): string
    {
        $billing   = CaseService::getCaseBilling($case);
        $stored    = json_decode((string) ($quotation['line_items'] ?? '[]'), true) ?: [];
        $lineItems = self::lineItemsFromStored(is_array($stored) ? $stored : [], $billing);

        if ($lineItems === []) {
            $lineItems[] = [
                'description' => (string) ($case['service_type'] ?? 'Service'),
                'quantity'    => 1.0,
                'unit_price'  => (float) ($quotation['subtotal'] ?? $quotation['total'] ?? 0),
                'vat'         => (float) ($quotation['tax_amount'] ?? 0),
                'total'       => (float) ($quotation['total'] ?? 0),
            ];
        }

        $grand = (float) ($quotation['total'] ?? $billing['totals']['grand_total'] ?? 0);
        $summary = self::buildFinancialSummary($billing, [
            'grand_total' => $grand,
            'tax_amount'  => (float) ($quotation['tax_amount'] ?? 0),
            'amount_paid' => 0,
            'amount_due'  => $grand,
        ]);

        $notes = [];
        if (!empty($quotation['valid_until'])) {
            $notes[] = '<p class="fdoc-note"><strong>Valid until:</strong> ' . e(formatDate($quotation['valid_until'])) . '</p>';
        }
        $quotationNotes = self::sanitizeNotesForDisplay(
            (string) ($quotation['notes'] ?? ''),
            (string) ($quotation['line_items'] ?? '')
        );
        if ($quotationNotes !== '') {
            $notes[] = '<p class="fdoc-note"><strong>Notes:</strong> ' . nl2br(e($quotationNotes)) . '</p>';
        }

        return self::render([
            'type'        => 'quotation',
            'title'       => 'QUOTATION',
            'number'      => (string) ($quotation['quotation_number'] ?? ''),
            'issue_date'  => date('d/m/Y'),
            'case'        => $case,
            'line_items'  => $lineItems,
            'summary'     => $summary,
            'notes_html'  => implode('', $notes),
            'subject'     => (string) ($quotation['title'] ?? 'Quotation'),
        ]);
    }

    public static function renderInvoice(array $case, array $invoice): string
    {
        $billing   = CaseService::getCaseBilling($case);
        $stored    = InvoiceService::resolveLineItems($invoice, $case);
        $lineItems = self::lineItemsFromStored($stored, $billing);

        $invoiceId = (int) ($invoice['id'] ?? 0);
        $amountPaid = $invoiceId > 0 ? CaseService::getInvoicePaidTotal($invoiceId) : 0.0;
        $amountDue  = $invoiceId > 0
            ? CaseService::getInvoiceRemainingBalance($invoice)
            : max(0, (float) ($invoice['total'] ?? 0) - $amountPaid);

        $summary = self::buildFinancialSummary($billing, [
            'grand_total' => (float) ($invoice['total'] ?? 0),
            'tax_amount'  => (float) ($invoice['tax_amount'] ?? 0),
            'amount_paid' => $amountPaid,
            'amount_due'  => $amountDue,
        ]);

        $company = getCompanySettings();
        $paymentTerms = trim((string) ($invoice['payment_terms'] ?? ''));
        if ($paymentTerms === '') {
            $paymentTerms = trim((string) ($company['default_invoice_payment_terms'] ?? ''));
        }

        $notes = [];
        if ($paymentTerms !== '') {
            $notes[] = '<p class="fdoc-note"><strong>Payment terms:</strong> ' . nl2br(e($paymentTerms)) . '</p>';
        }
        $invoiceNotes = self::sanitizeNotesForDisplay(
            (string) ($invoice['notes'] ?? ''),
            (string) ($invoice['line_items'] ?? '')
        );
        if ($invoiceNotes !== '') {
            $notes[] = '<p class="fdoc-note"><strong>Notes:</strong> ' . nl2br(e($invoiceNotes)) . '</p>';
        }

        $payableName = trim((string) ($company['invoice_payable_name'] ?? ''));
        if ($payableName === '') {
            $payableName = companyBrandName($company);
        }

        $footerHtml = '<section class="fdoc-payment">'
            . '<p class="fdoc-payment-label">Payable To:</p>'
            . '<div class="fdoc-bank">'
            . '<div class="fdoc-payee">' . e($payableName) . '</div>'
            . self::bankDetailsHtml($company, $invoice)
            . '</div>'
            . '<p class="fdoc-vat-no"><span class="fdoc-date-label">VAT Number:</span> ' . e(InvoiceService::companyVatNumber($company)) . '</p>'
            . '</section>';

        return self::render([
            'type'        => 'invoice',
            'title'       => 'INVOICE',
            'number'      => (string) ($invoice['invoice_number'] ?? ''),
            'issue_date'  => !empty($invoice['issue_date']) ? formatDate($invoice['issue_date'], 'd/m/Y') : date('d/m/Y'),
            'due_date'    => !empty($invoice['due_date']) ? formatDate($invoice['due_date'], 'd/m/Y') : '',
            'case'        => $case,
            'line_items'  => $lineItems,
            'summary'     => $summary,
            'notes_html'  => implode('', $notes),
            'footer_html' => $footerHtml,
        ]);
    }

    public static function renderReceipt(array $receipt, array $case, array $invoice): string
    {
        $billing   = CaseService::getCaseBilling($case);
        $lineItems = self::lineItemsFromBilling($billing);

        if ($lineItems === []) {
            $paymentAmount = (float) ($receipt['amount'] ?? $receipt['payment_amount'] ?? 0);
            $lineItems[] = [
                'description' => 'Payment for invoice ' . ($receipt['invoice_number'] ?? ''),
                'quantity'    => 1.0,
                'unit_price'  => $paymentAmount,
                'vat'         => 0.0,
                'total'       => $paymentAmount,
            ];
        }

        $invoiceId  = (int) ($invoice['id'] ?? 0);
        $amountPaid = $invoiceId > 0 ? CaseService::getInvoicePaidTotal($invoiceId) : (float) ($receipt['amount'] ?? 0);
        $grand      = (float) ($invoice['total'] ?? $receipt['invoice_total'] ?? 0);
        $amountDue  = $invoiceId > 0
            ? CaseService::getInvoiceRemainingBalance($invoice)
            : max(0, $grand - $amountPaid);

        $summary = self::buildFinancialSummary($billing, [
            'grand_total' => $grand,
            'tax_amount'  => (float) ($invoice['tax_amount'] ?? 0),
            'amount_paid' => $amountPaid,
            'amount_due'  => $amountDue,
        ]);

        $method = ucwords(str_replace('_', ' ', (string) ($receipt['payment_method'] ?? 'other')));
        $paidAt = formatDateTimeStacked($receipt['paid_at'] ?? $receipt['created_at'] ?? '');
        $notes  = trim((string) ($receipt['payment_notes'] ?? ''));

        $detailNote = '<p class="fdoc-note"><strong>Payment received:</strong> '
            . e(formatCurrency((float) ($receipt['amount'] ?? $receipt['payment_amount'] ?? 0)))
            . ' via ' . e($method) . ' on ' . $paidAt . '</p>';
        if ($notes !== '') {
            $detailNote .= '<p class="fdoc-note"><strong>Notes:</strong> ' . nl2br(e($notes)) . '</p>';
        }

        return self::render([
            'type'        => 'receipt',
            'title'       => 'RECEIPT',
            'number'      => (string) ($receipt['receipt_number'] ?? ''),
            'issue_date'  => !empty($receipt['created_at']) ? formatDate($receipt['created_at'], 'd/m/Y') : date('d/m/Y'),
            'case'        => $case,
            'line_items'  => $lineItems,
            'summary'     => $summary,
            'notes_html'  => $detailNote,
            'meta_html'   => '<p class="fdoc-meta-line"><strong>Invoice reference:</strong> ' . e($receipt['invoice_number'] ?? '') . '</p>',
        ]);
    }

    /**
     * @param array{
     *   type:string,
     *   title:string,
     *   number:string,
     *   issue_date:string,
     *   due_date?:string,
     *   case:array,
     *   line_items:list<array{description:string, quantity:float, unit_price:float, vat:float, total:float}>,
     *   summary:array<string, float>,
     *   notes_html?:string,
     *   footer_html?:string,
     *   meta_html?:string,
     *   subject?:string
     * } $config
     */
    public static function render(array $config): string
    {
        $company     = getCompanySettings();
        $primary     = (string) ($company['primary_color'] ?? '#3aafa9');
        $secondary   = (string) ($company['secondary_color'] ?? '#00182c');
        $companyName = e(companyBrandName($company));
        $logoUrl     = companyLogoUrl($company);
        $addressHtml = companyAddressHtml($company);
        $case        = $config['case'];
        $clientName  = e(clientFullName($case));
        $clientAddr  = self::clientAddressHtml($case);
        $number      = e($config['number']);
        $title       = e($config['title']);
        $issueDate   = e($config['issue_date']);
        $dueDate     = !empty($config['due_date']) ? e((string) $config['due_date']) : '';

        $contactHtml = '';
        if (!empty($company['office_email'])) {
            $contactHtml .= '<div class="fdoc-contact">' . e($company['office_email']) . '</div>';
        }
        if (!empty($company['office_phone'])) {
            $contactHtml .= '<div class="fdoc-contact">' . e($company['office_phone']) . '</div>';
        }

        $dateBlock = '<p><span class="fdoc-date-label">Issue Date:</span> ' . $issueDate . '</p>';
        if ($dueDate !== '') {
            $dateBlock .= '<p><span class="fdoc-date-label">Due Date:</span> ' . $dueDate . '</p>';
        }

        $subjectHtml = !empty($config['subject'])
            ? '<h2 class="fdoc-subject">' . e((string) $config['subject']) . '</h2>'
            : '';

        $metaHtml = $config['meta_html'] ?? '';

        return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>' . $title . ' ' . $number . '</title>'
            . self::styles($primary, $secondary, $company)
            . '</head><body>'
            . '<div class="no-print"><button type="button" onclick="window.print()">Print / Save as PDF</button></div>'
            . '<div class="fdoc-doc">'
            . '<header class="fdoc-top">'
            . '<div class="fdoc-brand">'
            . ($logoUrl ? '<img src="' . e($logoUrl) . '" alt="' . $companyName . '" class="fdoc-logo">' : '<div class="fdoc-logo-text">' . $companyName . '</div>')
            . '</div>'
            . '<div class="fdoc-heading"><h1>' . $title . '</h1><div class="fdoc-number">#' . $number . '</div></div>'
            . '</header>'
            . '<section class="fdoc-parties">'
            . '<div class="fdoc-from">'
            . '<div class="fdoc-from-name">' . $companyName . '</div>'
            . ($addressHtml !== '' ? '<div class="fdoc-address">' . $addressHtml . '</div>' : '')
            . $contactHtml
            . '<div class="fdoc-dates">' . $dateBlock . '</div>'
            . '</div>'
            . '<div class="fdoc-bill-to">'
            . '<p class="fdoc-bill-to-label">Bill To:</p>'
            . '<p class="fdoc-bill-to-name">' . $clientName . '</p>'
            . ($clientAddr !== '' ? '<p class="fdoc-bill-to-line">' . $clientAddr . '</p>' : '')
            . (!empty($case['email']) ? '<p class="fdoc-bill-to-line">' . e($case['email']) . '</p>' : '')
            . '</div>'
            . '</section>'
            . $metaHtml
            . $subjectHtml
            . self::lineItemsTable($config['line_items'])
            . self::summaryPanel($config['summary'])
            . ($config['notes_html'] ?? '')
            . ($config['footer_html'] ?? '')
            . '<div class="fdoc-footer">Thank you for your business.</div>'
            . '</div></body></html>';
    }

    /**
     * @param list<array{description:string, quantity:float, unit_price:float, vat:float, total:float}> $items
     */
    private static function lineItemsTable(array $items): string
    {
        $rows = '';
        foreach ($items as $item) {
            $rows .= '<tr>'
                . '<td>' . e((string) $item['description']) . '</td>'
                . '<td class="num">' . e(self::formatQuantity((float) $item['quantity'])) . '</td>'
                . '<td class="num">' . formatCurrency((float) $item['unit_price']) . '</td>'
                . '<td class="num">' . formatCurrency((float) $item['vat']) . '</td>'
                . '<td class="num">' . formatCurrency((float) $item['total']) . '</td>'
                . '</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="5" class="fdoc-empty">No items listed</td></tr>';
        }

        return '<table class="fdoc-table"><thead><tr>'
            . '<th>Description</th>'
            . '<th class="num">Quantity</th>'
            . '<th class="num">Unit Price</th>'
            . '<th class="num">VAT</th>'
            . '<th class="num">Total</th>'
            . '</tr></thead><tbody>' . $rows . '</tbody></table>';
    }

    /**
     * @param array<string, float> $summary
     */
    private static function summaryPanel(array $summary): string
    {
        $rows = [
            ['Subtotal', $summary['subtotal'] ?? 0, false],
            ['VAT Amount', $summary['vat_amount'] ?? 0, false],
            ['Net Amount (Excluding VAT)', $summary['net_excl_vat'] ?? 0, false],
            ['Net Amount (Including VAT)', $summary['net_incl_vat'] ?? 0, false],
            ['Amount Paid', $summary['amount_paid'] ?? 0, false],
            ['Amount Due', $summary['amount_due'] ?? 0, true],
            ['Grand Total', $summary['grand_total'] ?? 0, true],
        ];

        $html = '<section class="fdoc-summary" aria-label="Financial summary">';
        foreach ($rows as [$label, $amount, $highlight]) {
            $class = 'fdoc-summary-row' . ($highlight ? ' fdoc-summary-row--highlight' : '');
            $html .= '<div class="' . $class . '"><span class="fdoc-summary-label">' . e($label)
                . '</span><span class="fdoc-summary-value">' . formatCurrency((float) $amount) . '</span></div>';
        }
        $html .= '</section>';

        return $html;
    }

    /**
     * Detect notes fields that were incorrectly stored as line-item JSON.
     */
    public static function isLineItemsJsonPayload(string $value): bool
    {
        $trimmed = trim($value);
        if ($trimmed === '' || !str_starts_with($trimmed, '[')) {
            return false;
        }

        $decoded = json_decode($trimmed, true);
        if (!is_array($decoded) || $decoded === []) {
            return false;
        }

        foreach ($decoded as $row) {
            if (!is_array($row) || !isset($row['description'])) {
                return false;
            }
            if (!isset($row['amount']) && !isset($row['line_total']) && !isset($row['unit_price'])) {
                return false;
            }
        }

        return true;
    }

    public static function sanitizeNotesForDisplay(string $notes, string $lineItemsJson = ''): string
    {
        $notes = trim($notes);
        if ($notes === '') {
            return '';
        }

        if ($lineItemsJson !== '' && $notes === trim($lineItemsJson)) {
            return '';
        }

        if (self::isLineItemsJsonPayload($notes)) {
            return '';
        }

        return $notes;
    }

    private static function formatQuantity(float $qty): string
    {
        if (abs($qty - round($qty)) < 0.001) {
            return (string) (int) round($qty);
        }

        return number_format($qty, 2);
    }

    private static function clientAddressHtml(array $case): string
    {
        $cityLine = trim((string) ($case['city'] ?? ''));
        $zip      = trim((string) ($case['zip_code'] ?? ''));
        if ($cityLine !== '' && $zip !== '') {
            $cityLine .= ', ' . $zip;
        } elseif ($zip !== '') {
            $cityLine = $zip;
        }

        $lines = array_filter([
            trim((string) ($case['address'] ?? '')),
            $cityLine,
            trim((string) ($case['state'] ?? '')),
            trim((string) ($case['country'] ?? '')),
        ]);

        if ($lines === []) {
            return '';
        }

        return implode('<br>', array_map(static fn(string $line): string => e($line), $lines));
    }

    private static function bankDetailsHtml(array $company, array $invoice): string
    {
        $custom = trim((string) ($invoice['payment_instructions'] ?? ''));
        if ($custom !== '') {
            return nl2br(e($custom));
        }

        $lines   = [];
        $account = trim((string) ($company['bank_account_number'] ?? ''));
        $sort    = trim((string) ($company['bank_sort_code'] ?? ''));
        $iban    = trim((string) ($company['bank_iban'] ?? ''));
        $bic     = trim((string) ($company['bank_bic'] ?? ''));

        if ($account !== '') {
            $lines[] = 'Account number: ' . e($account);
        }
        if ($sort !== '') {
            $lines[] = 'Sort code: ' . e($sort);
        }
        if ($iban !== '') {
            $lines[] = 'IBAN: ' . e($iban);
        }
        if ($bic !== '') {
            $lines[] = 'BIC: ' . e($bic);
        }

        return $lines === [] ? '' : implode('<br>', $lines);
    }

    private static function styles(string $primary, string $secondary, ?array $company = null): string
    {
        $font      = companyFontInlineStack($company);
        $primary   = e($primary);
        $secondary = e($secondary);

        return '<style>
            *,*::before,*::after{box-sizing:border-box}
            body{font-family:' . $font . ';color:#1e293b;margin:0;padding:40px 48px 48px;font-size:15px;line-height:1.6;background:#fff;-webkit-font-smoothing:antialiased}
            .no-print{max-width:900px;margin:0 auto 24px}
            .no-print button{padding:10px 20px;background:' . $primary . ';color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-family:inherit;font-size:14px}
            .fdoc-doc{max-width:900px;margin:0 auto}
            .fdoc-top{display:flex;justify-content:space-between;align-items:flex-start;gap:32px;padding-bottom:28px;margin-bottom:32px;border-bottom:1px solid #e2e8f0}
            .fdoc-logo{max-height:96px;max-width:280px;object-fit:contain;display:block}
            .fdoc-logo-text{font-size:20px;font-weight:700;color:' . $secondary . ';letter-spacing:.02em}
            .fdoc-heading{text-align:right;flex-shrink:0}
            .fdoc-heading h1{margin:0;color:' . $primary . ';font-size:40px;font-weight:700;letter-spacing:.06em;line-height:1}
            .fdoc-number{font-size:20px;font-weight:700;color:' . $secondary . ';margin-top:8px;letter-spacing:.02em}
            .fdoc-parties{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);column-gap:56px;align-items:start;margin-bottom:28px;width:100%}
            .fdoc-from{min-width:0}
            .fdoc-bill-to{text-align:right;min-width:0;padding-right:2px}
            .fdoc-from-name{font-size:16px;font-weight:700;color:' . $secondary . ';margin:0 0 10px}
            .fdoc-address,.fdoc-contact{margin:0;font-size:14px;line-height:1.75;color:#475569}
            .fdoc-contact{margin-top:2px}
            .fdoc-bill-to-label{margin:0 0 10px;font-size:14px;font-weight:700;color:#1e293b;text-align:right}
            .fdoc-bill-to-name{margin:0 0 6px;font-size:14px;font-weight:600;color:#334155;line-height:1.75;text-align:right}
            .fdoc-bill-to-line{margin:0;font-size:14px;line-height:1.75;color:#475569;text-align:right}
            .fdoc-dates{margin-top:18px;padding-top:16px;border-top:1px solid #f1f5f9}
            .fdoc-dates p{margin:0 0 8px;font-size:14px;color:#1e293b}
            .fdoc-dates p:last-child{margin-bottom:0}
            .fdoc-date-label{font-weight:600;color:#334155}
            .fdoc-meta-line{margin:0 0 20px;font-size:14px;color:#475569}
            .fdoc-subject{font-size:16px;font-weight:600;color:' . $secondary . ';margin:0 0 16px}
            .fdoc-table{width:100%;border-collapse:collapse;margin-bottom:28px;border:1px solid #cbd5e1;border-radius:6px;overflow:hidden}
            .fdoc-table thead th{background:' . $secondary . ';color:#fff;font-weight:600;font-size:13px;letter-spacing:.02em;padding:12px 14px;border:none;text-align:left}
            .fdoc-table tbody td{padding:12px 14px;font-size:14px;color:#1e293b;border:none;border-top:1px solid #e2e8f0;vertical-align:top}
            .fdoc-table tbody tr:first-child td{border-top:none}
            .fdoc-table .num{text-align:right;white-space:nowrap;font-weight:600;font-variant-numeric:tabular-nums}
            .fdoc-table thead th.num{padding-right:14px}
            .fdoc-empty{color:#94a3b8;font-style:italic;text-align:center}
            .fdoc-summary{background:' . $primary . ';color:#fff;padding:20px 24px;margin-bottom:28px;border-radius:6px}
            .fdoc-summary-row{display:flex;justify-content:space-between;align-items:baseline;gap:24px;padding:4px 0;font-size:15px;font-weight:500;line-height:1.55}
            .fdoc-summary-label{opacity:.95}
            .fdoc-summary-value{font-weight:700;font-variant-numeric:tabular-nums;white-space:nowrap;text-align:right;min-width:120px}
            .fdoc-summary-row--highlight{margin-top:8px;padding-top:10px;border-top:1px solid rgba(255,255,255,.32);font-size:18px;font-weight:700}
            .fdoc-summary-row--highlight:last-child{font-size:20px}
            .fdoc-note{font-size:14px;line-height:1.7;margin:0 0 16px;color:#64748b;padding:12px 16px;background:#f8fafc;border-radius:6px}
            .fdoc-payment{font-size:14px;line-height:1.6;color:#475569;margin-bottom:20px}
            .fdoc-payment-label{margin:0 0 10px;font-weight:700;color:#1e293b;font-size:14px}
            .fdoc-bank{margin:0;line-height:1.6}
            .fdoc-payee{margin:0 0 6px;font-size:16px;font-weight:700;line-height:1.4;color:' . $primary . '}
            .fdoc-vat-no{margin:16px 0 0;font-size:14px;color:#475569}
            .fdoc-footer{margin-top:32px;padding-top:14px;border-top:1px solid #e2e8f0;font-size:12px;color:#94a3b8;text-align:center}
            @page{size:A4 portrait;margin:8mm 10mm}
            @media print{
                html,body{height:auto;margin:0;padding:0;font-size:13px;line-height:1.45;-webkit-print-color-adjust:exact;print-color-adjust:exact}
                .no-print{display:none!important}
                .fdoc-doc{max-width:none;margin:0;padding:0}
                .fdoc-top{gap:20px;padding-bottom:14px;margin-bottom:16px}
                .fdoc-logo{max-height:84px;max-width:240px}
                .fdoc-heading h1{font-size:32px}
                .fdoc-number{font-size:17px;margin-top:6px}
                .fdoc-parties{column-gap:36px;margin-bottom:16px}
                .fdoc-from-name{margin-bottom:6px;font-size:13px}
                .fdoc-address,.fdoc-contact,.fdoc-bill-to-name,.fdoc-bill-to-line{font-size:12px;line-height:1.5}
                .fdoc-bill-to-label{margin-bottom:6px;font-size:12px}
                .fdoc-dates{margin-top:10px;padding-top:8px}
                .fdoc-dates p{margin-bottom:5px;font-size:12px}
                .fdoc-table{margin-bottom:14px;border-radius:0}
                .fdoc-table thead th{padding:8px 10px;font-size:11px}
                .fdoc-table tbody td{padding:8px 10px;font-size:12px}
                .fdoc-summary{padding:12px 14px;margin-bottom:14px;border-radius:0;break-inside:avoid;page-break-inside:avoid}
                .fdoc-summary-row{padding:2px 0;font-size:12px;line-height:1.4}
                .fdoc-summary-row--highlight{font-size:15px}
                .fdoc-summary-row--highlight:last-child{font-size:16px}
                .fdoc-note{font-size:11px;line-height:1.45;margin-bottom:10px;padding:8px 10px}
                .fdoc-payment{font-size:12px;break-inside:avoid;page-break-inside:avoid}
                .fdoc-top,.fdoc-parties,.fdoc-table{break-inside:avoid;page-break-inside:avoid}
            }
        </style>';
    }
}
