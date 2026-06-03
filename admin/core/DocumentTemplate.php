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
        return InvoiceService::renderHtml($case, $invoice);
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

        $addressHtml = companyAddressHtml($company);
        $address     = $addressHtml !== '' ? '<div class="muted">' . $addressHtml . '</div>' : '';
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
