<?php

declare(strict_types=1);

class DocumentTemplate
{
    public static function quotation(array $case, array $quotation): string
    {
        $company = getCompanySettings();
        $items   = json_decode($quotation['line_items'] ?? '[]', true) ?: [];
        if ($items === []) {
            $items = [['description' => $case['service_type'] ?? 'Service', 'amount' => (float) ($quotation['subtotal'] ?? 0)]];
        }

        $rows = '';
        foreach ($items as $item) {
            $rows .= '<tr><td>' . e($item['description'] ?? 'Item') . '</td><td class="num">' . formatCurrency((float) ($item['amount'] ?? 0)) . '</td></tr>';
        }

        $taxRate = (float) ($quotation['tax_rate'] ?? 0);
        $subtotal = (float) ($quotation['subtotal'] ?? 0);
        $total    = (float) ($quotation['total'] ?? 0);
        $taxAmt   = max(0, $total - $subtotal);

        $totals = self::totalsBlock($subtotal, $taxRate, $taxAmt, $total);
        $valid  = !empty($quotation['valid_until']) ? '<p class="note"><strong>Valid until:</strong> ' . formatDate($quotation['valid_until']) . '</p>' : '';

        return self::wrap(
            $company,
            'Quotation',
            $quotation['quotation_number'] ?? '',
            $case,
            $quotation['title'] ?? 'Quotation',
            self::table($rows) . $totals . $valid
        );
    }

    /** @deprecated Use ClientLetterService::renderHtml() */
    public static function clientLetter(array $case, array $client, string $instructions = ''): string
    {
        if ($instructions !== '' && Database::columnExists('cases', 'client_instructions')) {
            $case['client_instructions'] = $instructions;
        }

        $caseId   = (int) ($case['id'] ?? 0);
        $sections = ClientLetterService::getSectionsForCase($caseId > 0 ? $caseId : 0);

        return ClientLetterService::renderHtml(max(1, $caseId), $sections);
    }

    public static function proposal(array $case, array $proposal): string
    {
        $company = getCompanySettings();
        $content = nl2br(e($proposal['content'] ?? ''));
        $amount  = formatCurrency((float) ($proposal['amount'] ?? $proposal['total'] ?? 0));

        $body = '<div class="content-block">' . $content . '</div>'
            . '<p class="total-line"><strong>Proposed amount:</strong> ' . $amount . '</p>';

        return self::wrap(
            $company,
            'Proposal',
            $proposal['proposal_number'] ?? '',
            $case,
            $proposal['title'] ?? 'Proposal',
            $body
        );
    }

    public static function invoice(array $case, array $invoice): string
    {
        $company = getCompanySettings();
        $netExVat  = (float) ($invoice['subtotal'] ?? $invoice['amount'] ?? $invoice['total'] ?? 0);
        $vatEnabled = !empty($invoice['vat_enabled']) || (float) ($invoice['tax_rate'] ?? 0) > 0;
        $vatRate   = $vatEnabled ? 20.0 : 0.0;
        $vatAmount = (float) ($invoice['tax_amount'] ?? round($netExVat * $vatRate / 100, 2));
        $total     = (float) ($invoice['total'] ?? round($netExVat + $vatAmount, 2));

        $companyName = e(companyBrandName($company));
        $logoUrl     = companyLogoUrl($company);
        $address     = trim((string) ($company['address'] ?? ''));
        $vatNumber   = trim((string) ($company['tax_vat_number'] ?? ''));
        $issueDate   = !empty($invoice['issue_date']) ? formatDate($invoice['issue_date']) : date('d/m/Y');
        $dueDate     = !empty($invoice['due_date']) ? formatDate($invoice['due_date']) : '';

        $lineItems = json_decode((string) ($invoice['line_items'] ?? '[]'), true);
        if (!is_array($lineItems) || $lineItems === []) {
            $services = CaseService::getCaseServices($case);
            if ($services === []) {
                $services = [[
                    'type' => ($case['service_type'] ?? 'Notary Service') . (!empty($case['title']) ? ' - ' . $case['title'] : ''),
                    'fee'  => $netExVat,
                ]];
            }
            $lineItems = array_map(static fn(array $s): array => [
                'description' => (string) ($s['type'] ?? 'Service'),
                'quantity'    => 1,
                'unit_price'  => (float) ($s['fee'] ?? 0),
                'line_total'  => (float) ($s['fee'] ?? 0),
            ], $services);
        }

        $serviceRows = '';
        foreach ($lineItems as $service) {
            $qty = (float) ($service['quantity'] ?? 1);
            $unit = (float) ($service['unit_price'] ?? $service['fee'] ?? 0);
            $lineTotal = (float) ($service['line_total'] ?? ($qty * $unit));
            $serviceRows .= '<tr>'
                . '<td class="qty">' . e(rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.')) . '</td>'
                . '<td>' . e((string) ($service['description'] ?? $service['type'] ?? 'Service')) . '</td>'
                . '<td class="num">' . formatCurrency($unit) . '</td>'
                . '<td class="num">' . formatCurrency($lineTotal) . '</td>'
                . '</tr>';
        }

        $clientName = e(clientFullName($case));
        $clientAddr = array_filter([
            trim((string) ($case['address'] ?? '')),
            trim((string) ($case['city'] ?? '')) . ((string) ($case['zip_code'] ?? '') !== '' ? ', ' . (string) $case['zip_code'] : ''),
            trim((string) ($case['state'] ?? '')),
            trim((string) ($case['country'] ?? '')),
        ]);
        $clientAddress = $clientAddr !== [] ? e(implode(', ', $clientAddr)) : '';
        $clientEmail = trim((string) ($case['email'] ?? ''));
        $clientCompany = trim((string) ($case['company_name'] ?? ''));
        $paymentTerms = trim((string) ($invoice['payment_terms'] ?? ''));
        $paymentInstructions = trim((string) ($invoice['payment_instructions'] ?? ''));

        return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Invoice ' . e($invoice['invoice_number'] ?? '') . '</title>'
            . self::invoiceStyles((string) ($company['primary_color'] ?? '#3aafa9'), (string) ($company['secondary_color'] ?? '#00182c'), $company)
            . '</head><body>'
            . '<div class="no-print"><button type="button" onclick="window.print()">Print / Save as PDF</button></div>'
            . '<div class="invoice-doc">'
            . '<header class="invoice-header">'
            . '<div class="invoice-company">'
            . ($logoUrl ? '<img src="' . e($logoUrl) . '" alt="' . $companyName . '" class="invoice-logo">' : '')
            . '<div class="invoice-company-name">' . $companyName . '</div>'
            . (!empty($address) ? '<div class="invoice-company-address">' . nl2br(e($address)) . '</div>' : '')
            . '</div>'
            . '<div class="invoice-title-box">'
            . '<h1>INVOICE</h1>'
            . '<div class="invoice-number">#' . e($invoice['invoice_number'] ?? '') . '</div>'
            . '</div>'
            . '</header>'
            . '<section class="invoice-meta">'
            . '<div class="bill-to"><strong>Bill To:</strong><div>' . $clientName . '</div>'
            . ($clientCompany !== '' ? '<div>' . e($clientCompany) . '</div>' : '')
            . ($clientEmail !== '' ? '<div>' . e($clientEmail) . '</div>' : '')
            . ($clientAddress !== '' ? '<div>' . $clientAddress . '</div>' : '')
            . '</div>'
            . '<div class="invoice-dates"><div><strong>Date:</strong> ' . e($issueDate) . '</div>'
            . ($dueDate !== '' ? '<div><strong>Due Date:</strong> ' . e($dueDate) . '</div>' : '')
            . '</div></section>'
            . '<table class="invoice-items"><thead><tr><th class="qty">Qty</th><th>Notary Service</th><th class="num">Unit Price</th><th class="num">Amount</th></tr></thead><tbody>'
            . $serviceRows
            . '</tbody></table>'
            . '<section class="invoice-totals-panel">'
            . '<div>Subtotal: <strong>' . formatCurrency($netExVat) . '</strong></div>'
            . '<div>VAT (' . e(number_format($vatRate, 0)) . '%): <strong>' . formatCurrency($vatAmount) . '</strong></div>'
            . '<div>Net Amount (Excluding VAT): <strong>' . formatCurrency($netExVat) . '</strong></div>'
            . '<div>Net Amount (Including VAT): <strong>' . formatCurrency($total) . '</strong></div>'
            . '<div class="invoice-total">Total: <strong>' . formatCurrency($total) . '</strong></div>'
            . '</section>'
            . (!empty($invoice['notes']) ? '<p class="invoice-notes"><strong>Notes:</strong> ' . nl2br(e((string) $invoice['notes'])) . '</p>' : '')
            . ($paymentTerms !== '' ? '<p class="invoice-notes"><strong>Payment terms:</strong> ' . nl2br(e($paymentTerms)) . '</p>' : '')
            . ($paymentInstructions !== '' ? '<p class="invoice-notes"><strong>Payment instructions:</strong> ' . nl2br(e($paymentInstructions)) . '</p>' : '')
            . '<section class="invoice-payable"><strong>Payable To:</strong>'
            . '<div class="payee-name">' . $companyName . '</div>'
            . ($vatNumber !== '' ? '<div class="payee-vat">VAT Number: ' . e($vatNumber) . '</div>' : '')
            . '</section>'
            . '</div></body></html>';
    }

    private static function invoiceStyles(string $primary, string $secondary, ?array $company = null): string
    {
        $font = companyFontInlineStack($company);
        $primary = e($primary);
        $secondary = e($secondary);

        return '<style>
            body{font-family:' . $font . ';color:#0f172a;margin:30px;line-height:1.45;font-size:14px}
            .no-print{margin-bottom:18px}
            .no-print button{padding:10px 18px;background:' . $primary . ';color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-family:inherit}
            .invoice-doc{max-width:900px;margin:0 auto;background:#fff}
            .invoice-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:24px}
            .invoice-logo{max-height:62px;max-width:180px;object-fit:contain;display:block;margin-bottom:10px}
            .invoice-company-name{font-size:34px;font-weight:700;color:' . $secondary . ';margin-bottom:6px}
            .invoice-company-address{font-size:14px}
            .invoice-title-box{text-align:right}
            .invoice-title-box h1{margin:0;color:' . $primary . ';font-size:46px;letter-spacing:.04em}
            .invoice-number{font-size:30px;font-weight:700;color:' . $secondary . '}
            .invoice-meta{display:flex;justify-content:space-between;gap:24px;margin:12px 0 20px}
            .bill-to{font-size:20px;line-height:1.5}
            .invoice-dates{font-size:28px;line-height:1.4;color:#0f172a}
            .invoice-items{width:100%;border-collapse:collapse;margin:10px 0 20px}
            .invoice-items th,.invoice-items td{border:1px solid #334155;padding:10px 12px;font-size:18px}
            .invoice-items .qty{width:72px;text-align:center}
            .invoice-items th{background:#0a2238;color:#fff;text-align:left}
            .invoice-items .num{text-align:right;white-space:nowrap}
            .invoice-totals-panel{background:' . $primary . ';color:#fff;padding:16px 20px;border-radius:4px;font-size:28px;line-height:1.45;font-weight:600;margin-bottom:26px}
            .invoice-totals-panel strong{font-weight:800}
            .invoice-total{margin-top:8px;font-size:34px}
            .invoice-notes{font-size:18px;margin:14px 0 18px}
            .invoice-payable{font-size:20px;line-height:1.5}
            .payee-name{color:' . $primary . ';font-weight:700;margin-top:8px}
            .payee-vat{margin-top:12px}
            @media print{body{margin:12mm}.no-print{display:none}}
        </style>';
    }

    private static function table(string $rows): string
    {
        return '<table class="items"><thead><tr><th>Description</th><th class="num">Amount</th></tr></thead><tbody>' . $rows . '</tbody></table>';
    }

    private static function totalsBlock(float $subtotal, float $taxRate, float $taxAmt, float $total): string
    {
        $taxLine = $taxRate > 0
            ? '<tr><td>Tax (' . number_format($taxRate, 2) . '%)</td><td class="num">' . formatCurrency($taxAmt) . '</td></tr>'
            : '';

        return '<table class="totals">'
            . '<tr><td>Subtotal</td><td class="num">' . formatCurrency($subtotal) . '</td></tr>'
            . $taxLine
            . '<tr class="grand"><td>Total</td><td class="num">' . formatCurrency($total) . '</td></tr>'
            . '</table>';
    }

    private static function wrap(
        array $company,
        string $docType,
        string $number,
        array $case,
        string $subject,
        string $body
    ): string {
        $primary   = e($company['primary_color'] ?? '#3aafa9');
        $secondary = e($company['secondary_color'] ?? '#00182c');
        $client    = e(clientFullName($case));
        $companyName = e(companyBrandName($company));
        $logoUrl     = companyLogoUrl($company);
        $brandHtml   = $logoUrl
            ? '<div class="brand-block"><img src="' . e($logoUrl) . '" alt="' . $companyName . '" class="doc-brand-logo"><div class="brand">' . $companyName . '</div></div>'
            : '<div class="brand">' . $companyName . '</div>';

        $address = !empty($company['address']) ? '<div class="muted">' . nl2br(e($company['address'])) . '</div>' : '';
        $email   = !empty($company['office_email']) ? '<div class="muted">' . e($company['office_email']) . '</div>' : '';
        $phone   = !empty($company['office_phone']) ? '<div class="muted">' . e($company['office_phone']) . '</div>' : '';

        return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>' . e($docType) . ' ' . e($number) . '</title>'
            . self::styles($primary, $secondary, $company)
            . '</head><body>'
            . '<div class="no-print"><button type="button" onclick="window.print()">Print / Save as PDF</button></div>'
            . '<div class="header"><div>' . $brandHtml . $address . $email . $phone . '</div>'
            . '<div class="doc-meta"><h1>' . e($docType) . '</h1><div class="doc-number">' . e($number) . '</div>'
            . '<div class="muted">' . date('F j, Y') . '</div></div></div>'
            . '<div class="parties"><div><strong>Bill to</strong><div>' . $client . '</div>'
            . (!empty($case['email']) ? '<div class="muted">' . e($case['email']) . '</div>' : '')
            . (!empty($case['company_name']) ? '<div class="muted">' . e($case['company_name']) . '</div>' : '')
            . '</div><div><strong>Case reference</strong><div>' . e($case['case_number'] ?? '') . '</div>'
            . '<div class="muted">' . e($case['title'] ?? '') . '</div></div></div>'
            . '<h2 class="subject">' . e($subject) . '</h2>'
            . $body
            . '<div class="footer">Thank you for your business.</div>'
            . '</body></html>';
    }

    private static function styles(string $primary, string $secondary, ?array $company = null): string
    {
        $font = companyFontInlineStack($company);

        return '<style>
            body{font-family:' . $font . ';color:#0f172a;margin:40px;line-height:1.5}
            .no-print{margin-bottom:24px}
            .no-print button{padding:10px 18px;background:' . $primary . ';color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-family:inherit}
            .header{display:flex;justify-content:space-between;align-items:flex-start;gap:24px;margin-bottom:32px;padding-bottom:20px;border-bottom:2px solid #e2e8f0}
            .brand{font-size:22px;font-weight:700;color:' . $secondary . ';margin-bottom:6px}
            .brand-block{margin-bottom:8px}
            .doc-brand-logo{display:block;max-height:52px;max-width:220px;width:auto;height:auto;object-fit:contain;margin-bottom:8px}
            .doc-meta{text-align:right}
            h1{color:' . $primary . ';margin:0 0 4px;font-size:28px}
            .doc-number{font-size:16px;font-weight:700;color:' . $secondary . '}
            .muted{color:#64748b;font-size:13px}
            .parties{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:28px}
            .subject{font-size:16px;color:' . $secondary . ';margin:0 0 16px}
            table.items{width:100%;border-collapse:collapse;margin:16px 0}
            table.items th,table.items td{padding:12px;border-bottom:1px solid #e2e8f0;text-align:left;font-size:14px}
            table.items th{background:#f8fafc;font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:#64748b}
            table.totals{width:280px;margin-left:auto;margin-top:8px;border-collapse:collapse}
            table.totals td{padding:8px 12px;font-size:14px}
            table.totals tr.grand td{font-size:18px;font-weight:700;color:' . $secondary . ';border-top:2px solid #e2e8f0;padding-top:12px}
            .num{text-align:right;white-space:nowrap}
            .content-block{background:#f8fafc;border-radius:8px;padding:16px;margin:16px 0;font-size:14px}
            .total-line{font-size:16px;margin-top:16px}
            .note{font-size:13px;color:#475569;margin-top:16px}
            .footer{margin-top:40px;padding-top:16px;border-top:1px solid #e2e8f0;font-size:12px;color:#94a3b8;text-align:center}
            @media print{body{margin:20px}.no-print{display:none}}
        </style>';
    }
}
