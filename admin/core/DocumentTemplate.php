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

    public static function clientLetter(array $case, array $client, string $instructions = ''): string
    {
        $company     = getCompanySettings();
        $companyName = e(companyBrandName($company));
        $logoUrl     = companyLogoUrl($company);
        $brandHtml   = $logoUrl
            ? '<div class="brand-block"><img src="' . e($logoUrl) . '" alt="' . $companyName . '" class="doc-brand-logo"><div class="brand">' . $companyName . '</div></div>'
            : '<div class="brand">' . $companyName . '</div>';
        $primary     = e($company['primary_color'] ?? '#3aafa9');
        $secondary   = e($company['secondary_color'] ?? '#00182c');
        $name        = e(clientFullName($client) ?: 'Client');
        $today       = date('F j, Y');
        $services    = CaseService::getCaseServices($case);
        $serviceList = '';

        foreach ($services as $service) {
            $serviceList .= '<li>' . e($service['type']) . ' — ' . formatCurrency((float) $service['fee']) . '</li>';
        }

        if ($serviceList === '') {
            $serviceList = '<li>' . e($case['service_type'] ?? 'Notary services') . ' — '
                . formatCurrency((float) ($case['service_fee'] ?? 0)) . '</li>';
        }

        $instructionsBlock = trim($instructions) !== ''
            ? '<div class="instructions"><h3>Your instructions</h3><p>' . nl2br(e($instructions)) . '</p></div>'
            : '';

        $deadline = !empty($case['deadline'])
            ? '<p><strong>Target deadline:</strong> ' . formatDate($case['deadline']) . '</p>'
            : '';

        $address = !empty($company['address']) ? '<div class="muted">' . nl2br(e($company['address'])) . '</div>' : '';
        $email   = !empty($company['office_email']) ? '<div class="muted">' . e($company['office_email']) . '</div>' : '';
        $phone   = !empty($company['office_phone']) ? '<div class="muted">' . e($company['office_phone']) . '</div>' : '';

        return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Client Letter — '
            . e($case['case_number'] ?? '') . '</title>'
            . self::letterStyles($primary, $secondary)
            . '</head><body>'
            . '<div class="no-print"><button type="button" onclick="window.print()">Print / Save as PDF</button></div>'
            . '<div class="header"><div>' . $brandHtml . $address . $email . $phone . '</div>'
            . '<div class="doc-meta"><h1>Client Letter</h1><div class="muted">' . $today . '</div></div></div>'
            . '<p class="salutation">Dear ' . $name . ',</p>'
            . '<p>Thank you for choosing ' . $companyName . '. We are pleased to confirm your case '
            . '<strong>' . e($case['case_number'] ?? '') . '</strong> — <em>' . e($case['title'] ?? '') . '</em>.</p>'
            . '<p>Please find attached your quotation for the services listed below. We have also opened your client portal where you can upload documents, view updates, and track progress.</p>'
            . '<div class="case-box"><h3>Case summary</h3>'
            . '<p><strong>Reference:</strong> ' . e($case['case_number'] ?? '') . '</p>'
            . '<p><strong>Services:</strong></p><ul>' . $serviceList . '</ul>'
            . '<p><strong>Total fee:</strong> ' . formatCurrency((float) ($case['service_fee'] ?? 0)) . '</p>'
            . $deadline
            . '</div>'
            . $instructionsBlock
            . '<p>If you have any questions, reply to this email or contact our office. We look forward to working with you.</p>'
            . '<p class="signoff">Kind regards,<br><strong>' . $companyName . '</strong></p>'
            . '<div class="footer">This letter accompanies your quotation for case ' . e($case['case_number'] ?? '') . '.</div>'
            . '</body></html>';
    }

    private static function letterStyles(string $primary, string $secondary): string
    {
        return '<style>
            body{font-family:Montserrat,Arial,sans-serif;color:#0f172a;margin:40px;line-height:1.6;font-size:14px}
            .no-print{margin-bottom:24px}
            .no-print button{padding:10px 18px;background:' . $primary . ';color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-family:inherit}
            .header{display:flex;justify-content:space-between;align-items:flex-start;gap:24px;margin-bottom:32px;padding-bottom:20px;border-bottom:2px solid #e2e8f0}
            .brand{font-size:22px;font-weight:700;color:' . $secondary . ';margin-bottom:6px}
            .brand-block{margin-bottom:8px}
            .doc-brand-logo{display:block;max-height:52px;max-width:220px;width:auto;height:auto;object-fit:contain;margin-bottom:8px}
            .doc-meta{text-align:right}
            h1{color:' . $primary . ';margin:0 0 4px;font-size:24px}
            .muted{color:#64748b;font-size:13px}
            .salutation{font-size:15px;margin-bottom:16px}
            .case-box{background:#f8fafc;border:1px solid #e2e8f0;border-left:4px solid ' . $primary . ';border-radius:8px;padding:16px 18px;margin:20px 0}
            .case-box h3,.instructions h3{margin:0 0 10px;font-size:14px;color:' . $secondary . '}
            .case-box ul{margin:0 0 12px;padding-left:20px}
            .instructions{background:rgba(58,175,169,.08);border:1px solid rgba(58,175,169,.25);border-radius:8px;padding:16px 18px;margin:20px 0}
            .signoff{margin-top:28px}
            .footer{margin-top:40px;padding-top:16px;border-top:1px solid #e2e8f0;font-size:12px;color:#94a3b8;text-align:center}
            @media print{body{margin:20px}.no-print{display:none}}
        </style>';
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
        $amount  = (float) ($invoice['amount'] ?? $invoice['subtotal'] ?? $invoice['total'] ?? 0);
        $taxRate = (float) ($invoice['tax_rate'] ?? 0);
        $taxAmt  = (float) ($invoice['tax_amount'] ?? 0);
        $total   = (float) ($invoice['total'] ?? 0);

        $rows = '<tr><td>' . e($case['service_type'] ?? 'Notary services') . ' — ' . e($case['title'] ?? '') . '</td><td class="num">' . formatCurrency($amount) . '</td></tr>';
        $totals = self::totalsBlock($amount, $taxRate, $taxAmt, $total);

        $due = !empty($invoice['due_date']) ? '<p class="note"><strong>Due date:</strong> ' . formatDate($invoice['due_date']) . '</p>' : '';
        $notes = !empty($invoice['notes']) ? '<p class="note"><strong>Notes:</strong> ' . nl2br(e($invoice['notes'])) . '</p>' : '';

        return self::wrap(
            $company,
            'Invoice',
            $invoice['invoice_number'] ?? '',
            $case,
            'Invoice for ' . ($case['case_number'] ?? ''),
            self::table($rows) . $totals . $due . $notes
        );
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
            . self::styles($primary, $secondary)
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

    private static function styles(string $primary, string $secondary): string
    {
        return '<style>
            body{font-family:Montserrat,Arial,sans-serif;color:#0f172a;margin:40px;line-height:1.5}
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
