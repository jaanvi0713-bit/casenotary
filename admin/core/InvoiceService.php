<?php

declare(strict_types=1);

class InvoiceService
{
    /**
     * @return list<array{description:string, quantity:float, unit_price:float, line_total:float}>
     */
    public static function lineItemsFromBilling(array $billing): array
    {
        $items = [];

        foreach (CaseService::billingToDisplayServices($billing) as $service) {
            $amount = (float) ($service['fee'] ?? $service['gross'] ?? 0);
            if ($amount <= 0 && (float) ($service['net'] ?? 0) <= 0) {
                continue;
            }
            $items[] = [
                'description' => (string) ($service['type'] ?? 'Service'),
                'quantity'    => 1.0,
                'unit_price'  => $amount,
                'line_total'  => $amount,
            ];
        }

        return $items;
    }

    /**
     * @param list<array{description:string, line_total:float}> $lineItems
     *
     * @return array{
     *   line_subtotal:float,
     *   non_vat:float,
     *   vat_net:float,
     *   vat_amount:float,
     *   total:float,
     *   has_vat:bool,
     *   has_non_vat:bool,
     *   has_vat_net:bool
     * }
     */
    public static function resolveTotals(array $invoice, array $case, array $lineItems): array
    {
        $billing = CaseService::getCaseBilling($case);
        $bt      = $billing['totals'] ?? [];

        $lineSubtotal = round(array_sum(array_map(
            static fn(array $row): float => (float) ($row['line_total'] ?? 0),
            $lineItems
        )), 2);

        $grand         = (float) ($bt['grand_total'] ?? 0);
        $vatAmt        = (float) ($bt['vat_amount'] ?? 0);
        $nonVatRateAmt = (float) ($bt['non_vat_rate_amount'] ?? 0);
        $vatNet        = (float) ($bt['vat_net_subtotal'] ?? 0);
        $nonVatGross   = (float) ($bt['non_vat_subtotal'] ?? 0);
        $nonVatNet     = (float) ($bt['non_vat_net_subtotal'] ?? 0);

        $taxTotal = round($vatAmt + $nonVatRateAmt, 2);
        $total    = $grand > 0 ? $grand : (float) ($invoice['total'] ?? 0);
        if ($total <= 0) {
            $total = round($lineSubtotal, 2);
        }

        if ($grand > 0 && abs($lineSubtotal - $grand) < 0.02) {
            $lineSubtotal = $grand;
        }

        return [
            'line_subtotal' => $lineSubtotal,
            'non_vat'       => $nonVatGross > 0 ? $nonVatGross : $nonVatNet,
            'vat_net'       => $vatNet,
            'vat_amount'    => $taxTotal > 0 ? $taxTotal : (float) ($invoice['tax_amount'] ?? 0),
            'total'         => $total,
            'has_vat'       => $taxTotal > 0.001,
            'has_non_vat'   => ($nonVatGross + $nonVatNet) > 0.001,
            'has_vat_net'   => $vatNet > 0.001,
        ];
    }

    /**
     * @return list<array{description:string, line_total:float}>
     */
    public static function resolveLineItems(array $invoice, array $case): array
    {
        $decoded = json_decode((string) ($invoice['line_items'] ?? '[]'), true);
        if (is_array($decoded) && $decoded !== []) {
            $rows = [];
            foreach ($decoded as $service) {
                if (!is_array($service)) {
                    continue;
                }
                $qty       = (float) ($service['quantity'] ?? 1);
                $unit      = (float) ($service['unit_price'] ?? $service['fee'] ?? 0);
                $lineTotal = (float) ($service['line_total'] ?? ($qty * $unit));
                $desc      = trim((string) ($service['description'] ?? $service['type'] ?? 'Service'));
                $desc      = preg_replace('/\s*\((?:Non-VAT|VAT net)\)\s*$/i', '', $desc) ?? $desc;
                $rows[]    = [
                    'description' => $desc,
                    'line_total'  => $lineTotal,
                ];
            }

            if ($rows !== []) {
                return $rows;
            }
        }

        return array_map(
            static fn(array $row): array => [
                'description' => $row['description'],
                'line_total'  => $row['line_total'],
            ],
            self::lineItemsFromBilling(CaseService::getCaseBilling($case))
        );
    }

    public static function renderHtml(array $case, array $invoice): string
    {
        $company     = getCompanySettings();
        $primary     = (string) ($company['primary_color'] ?? '#3aafa9');
        $secondary   = (string) ($company['secondary_color'] ?? '#00182c');
        $companyName = e(companyBrandName($company));
        $logoUrl     = companyLogoUrl($company);
        $addressHtml = companyAddressHtml($company);
        $vatNumber   = self::companyVatNumber($company);
        $issueDate   = !empty($invoice['issue_date']) ? formatDate($invoice['issue_date'], 'd/m/Y') : date('d/m/Y');
        $dueDate     = !empty($invoice['due_date']) ? formatDate($invoice['due_date'], 'd/m/Y') : '';

        $lineItems = self::resolveLineItems($invoice, $case);
        $totals    = self::resolveTotals($invoice, $case, $lineItems);

        $serviceRows = '';
        foreach ($lineItems as $service) {
            $serviceRows .= '<tr>'
                . '<td>' . e((string) $service['description']) . '</td>'
                . '<td class="num">' . formatCurrency((float) $service['line_total']) . '</td>'
                . '</tr>';
        }
        if ($serviceRows === '') {
            $serviceRows = '<tr><td colspan="2" class="muted">No services listed</td></tr>';
        }

        $clientName    = e(clientFullName($case));
        $clientAddress = self::formatClientAddressHtml($case);

        $paymentTerms = trim((string) ($invoice['payment_terms'] ?? ''));
        if ($paymentTerms === '') {
            $paymentTerms = trim((string) ($company['default_invoice_payment_terms'] ?? ''));
        }

        $payableName = trim((string) ($company['invoice_payable_name'] ?? ''));
        if ($payableName === '') {
            $payableName = companyBrandName($company);
        }

        $bankHtml = self::bankDetailsHtml($company, $invoice);
        $notes    = trim((string) ($invoice['notes'] ?? ''));
        $number   = e($invoice['invoice_number'] ?? '');

        return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Invoice ' . $number . '</title>'
            . self::styles($primary, $secondary, $company)
            . '</head><body>'
            . '<div class="no-print"><button type="button" onclick="window.print()">Print / Save as PDF</button></div>'
            . '<div class="invoice-doc">'
            . '<header class="inv-top">'
            . '<div class="inv-brand">'
            . ($logoUrl ? '<img src="' . e($logoUrl) . '" alt="' . $companyName . '" class="inv-logo">' : '<div class="inv-logo-text">' . $companyName . '</div>')
            . '</div>'
            . '<div class="inv-heading"><h1>INVOICE</h1><div class="inv-number">#' . $number . '</div></div>'
            . '</header>'
            . '<section class="inv-parties">'
            . '<div class="inv-from">'
            . '<div class="inv-from-name">' . $companyName . '</div>'
            . ($addressHtml !== '' ? '<div class="inv-address">' . $addressHtml . '</div>' : '')
            . '<div class="inv-dates">'
            . '<p><span class="inv-date-label">Date:</span> ' . e($issueDate) . '</p>'
            . ($dueDate !== '' ? '<p><span class="inv-date-label">Due Date:</span> ' . e($dueDate) . '</p>' : '')
            . '</div>'
            . '</div>'
            . '<div class="inv-bill-to">'
            . '<p class="inv-bill-to-label">Bill To:</p>'
            . '<p class="inv-bill-to-name">' . $clientName . '</p>'
            . ($clientAddress !== '' ? '<p class="inv-bill-to-line">' . $clientAddress . '</p>' : '')
            . '</div>'
            . '</section>'
            . '<table class="inv-table"><thead><tr><th>Notary Service</th><th class="num">Amount</th></tr></thead><tbody>'
            . $serviceRows
            . '</tbody></table>'
            . self::totalsPanelHtml($totals)
            . ($paymentTerms !== '' ? '<p class="inv-note"><strong>Payment terms:</strong> ' . nl2br(e($paymentTerms)) . '</p>' : '')
            . ($notes !== '' ? '<p class="inv-note"><strong>Notes:</strong> ' . nl2br(e($notes)) . '</p>' : '')
            . '<section class="inv-payment">'
            . '<p class="inv-payment-label">Payable To:</p>'
            . '<div class="inv-bank">'
            . '<div class="inv-payee">' . e($payableName) . '</div>'
            . ($bankHtml !== '' ? $bankHtml : '')
            . '</div>'
            . '<p class="inv-vat-no"><span class="inv-date-label">VAT Number:</span> ' . e($vatNumber) . '</p>'
            . '</section>'
            . '</div></body></html>';
    }

    /**
     * @param array{
     *   line_subtotal:float,
     *   non_vat:float,
     *   vat_net:float,
     *   vat_amount:float,
     *   total:float,
     *   has_vat:bool,
     *   has_non_vat:bool,
     *   has_vat_net:bool
     * } $totals
     */
    private static function totalsPanelHtml(array $totals): string
    {
        $rows = [];

        if ($totals['has_vat_net']) {
            $rows[] = ['Net Amount (Including VAT)', $totals['vat_net']];
        }
        if ($totals['has_vat']) {
            $rows[] = ['VAT Amount', $totals['vat_amount']];
        }
        if ($totals['has_non_vat']) {
            $rows[] = ['Net Amount (Excluding VAT)', $totals['non_vat']];
        }

        if ($rows === []) {
            $rows[] = ['Subtotal', $totals['line_subtotal']];
        }

        $html = '<section class="inv-summary" aria-label="Invoice totals">';
        foreach ($rows as [$label, $amount]) {
            $html .= '<div class="inv-summary-row"><span class="inv-summary-label">' . e($label) . '</span><span class="inv-summary-value">'
                . formatCurrency((float) $amount) . '</span></div>';
        }
        $html .= '<div class="inv-summary-row inv-summary-total"><span class="inv-summary-label">Total</span><span class="inv-summary-value">'
            . formatCurrency((float) $totals['total']) . '</span></div>';
        $html .= '</section>';

        return $html;
    }

    /** VAT from settings, else derived from company registration or a stable generated number. */
    public static function companyVatNumber(array $company): string
    {
        $manual = trim((string) ($company['tax_vat_number'] ?? ''));
        if ($manual !== '') {
            return self::formatVatNumber($manual);
        }

        $regDigits = preg_replace('/\D/', '', (string) ($company['registration_number'] ?? ''));
        if (strlen($regDigits) >= 9) {
            return self::formatVatNumber(substr($regDigits, -9));
        }

        $id = max(1, (int) ($company['id'] ?? 1));
        $seed = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($company['company_name'] ?? 'CO')));
        $hash = abs(crc32($seed . '|' . $id));
        $nine = str_pad((string) ($hash % 1_000_000_000), 9, '0', STR_PAD_LEFT);

        return self::formatVatNumber($nine);
    }

    private static function formatVatNumber(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        if (preg_match('/^GB\s*/i', $trimmed)) {
            $digits = preg_replace('/\D/', '', $trimmed);
            if (strlen($digits) >= 11) {
                $nine = substr($digits, -9);

                return 'GB ' . self::formatNineDigitVat($nine);
            }
            if (strlen($digits) === 9) {
                return 'GB ' . self::formatNineDigitVat($digits);
            }

            return strtoupper($trimmed);
        }

        $digits = preg_replace('/\D/', '', $trimmed);
        if (strlen($digits) >= 9) {
            return self::formatNineDigitVat(substr($digits, -9));
        }

        return $trimmed;
    }

    private static function formatNineDigitVat(string $nine): string
    {
        $nine = str_pad(substr(preg_replace('/\D/', '', $nine), 0, 9), 9, '0', STR_PAD_LEFT);

        return substr($nine, 0, 3) . ' ' . substr($nine, 3, 4) . ' ' . substr($nine, 7, 2);
    }

    private static function formatClientAddressHtml(array $case): string
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
            .no-print{max-width:880px;margin:0 auto 24px}
            .no-print button{padding:10px 20px;background:' . $primary . ';color:#fff;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-family:inherit;font-size:14px}
            .invoice-doc{max-width:880px;margin:0 auto}
            .inv-top{display:flex;justify-content:space-between;align-items:flex-start;gap:32px;padding-bottom:28px;margin-bottom:36px;border-bottom:1px solid #e2e8f0}
            .inv-logo{max-height:100px;max-width:300px;object-fit:contain;display:block}
            .inv-logo-text{font-size:20px;font-weight:700;color:' . $secondary . ';letter-spacing:.02em}
            .inv-heading{text-align:right;flex-shrink:0}
            .inv-heading h1{margin:0;color:' . $primary . ';font-size:44px;font-weight:700;letter-spacing:.06em;line-height:1}
            .inv-number{font-size:22px;font-weight:700;color:' . $secondary . ';margin-top:10px;letter-spacing:.02em}
            .inv-parties{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);column-gap:64px;align-items:start;margin-bottom:40px;width:100%}
            .inv-from{grid-column:1;grid-row:1;min-width:0}
            .inv-bill-to{grid-column:2;grid-row:1;min-width:0;text-align:right;padding-right:2px}
            .inv-from-name{font-size:16px;font-weight:700;color:' . $secondary . ';margin:0 0 12px}
            .inv-address{margin:0 0 0;font-size:15px;line-height:1.8;color:#475569}
            .inv-bill-to-label{margin:0 0 12px;font-size:15px;font-weight:700;color:#1e293b;text-align:right}
            .inv-bill-to-name{margin:0 0 8px;font-size:15px;font-weight:400;color:#475569;line-height:1.8;text-align:right}
            .inv-bill-to-line{margin:0;font-size:15px;line-height:1.8;color:#475569;text-align:right}
            .inv-dates{margin-top:24px;padding-top:20px;border-top:1px solid #f1f5f9}
            .inv-dates p{margin:0 0 10px;font-size:15px;color:#1e293b}
            .inv-dates p:last-child{margin-bottom:0}
            .inv-date-label{font-weight:600;color:#334155}
            .inv-table{width:100%;border-collapse:collapse;margin-bottom:32px;border:1px solid #cbd5e1;border-radius:6px;overflow:hidden}
            .inv-table thead th{background:' . $secondary . ';color:#fff;font-weight:600;font-size:14px;letter-spacing:.03em;text-transform:none;padding:14px 18px;border:none}
            .inv-table tbody td{padding:14px 18px;font-size:15px;color:#1e293b;border:none;border-top:1px solid #e2e8f0;vertical-align:top}
            .inv-table tbody tr:first-child td{border-top:none}
            .inv-table .num{text-align:right;white-space:nowrap;font-weight:600;font-variant-numeric:tabular-nums;width:160px;padding-right:18px}
            .inv-table thead th.num{padding-right:18px}
            .inv-table .muted{color:#94a3b8;font-style:italic}
            .inv-summary{background:' . $primary . ';color:#fff;padding:22px 26px;margin-bottom:36px;border-radius:6px}
            .inv-summary-row{display:flex;justify-content:space-between;align-items:baseline;gap:32px;padding:5px 0;font-size:16px;font-weight:500;line-height:1.65}
            .inv-summary-label{opacity:.95}
            .inv-summary-value{font-weight:700;font-variant-numeric:tabular-nums;white-space:nowrap}
            .inv-summary-total{margin-top:12px;padding-top:14px;border-top:1px solid rgba(255,255,255,.35);font-size:22px;font-weight:700}
            .inv-summary-total .inv-summary-value{font-size:24px}
            .inv-note{font-size:14px;line-height:1.75;margin:0 0 20px;color:#64748b;padding:14px 18px;background:#f8fafc;border-radius:6px}
            .inv-payment{font-size:15px;line-height:1.6;color:#475569}
            .inv-payment-label{margin:0 0 12px;font-weight:700;color:#1e293b;font-size:15px}
            .inv-bank{margin:0;line-height:1.6}
            .inv-payee{margin:0;padding:0;font-size:17px;font-weight:700;line-height:1.4;color:' . $primary . ';letter-spacing:.01em}
            .inv-vat-no{margin:28px 0 0;padding:0;font-size:15px;line-height:1.6;color:#475569}
            @page{size:A4 portrait;margin:8mm 10mm}
            @media print{
                html,body{height:auto;margin:0;padding:0;font-size:13px;line-height:1.45;-webkit-print-color-adjust:exact;print-color-adjust:exact}
                .no-print{display:none!important}
                .invoice-doc{max-width:none;margin:0;padding:0}
                .inv-top{gap:20px;padding-bottom:14px;margin-bottom:16px}
                .inv-logo{max-height:88px;max-width:260px}
                .inv-heading h1{font-size:34px}
                .inv-number{font-size:18px;margin-top:6px}
                .inv-parties{column-gap:40px;margin-bottom:18px}
                .inv-from-name{margin-bottom:6px;font-size:14px}
                .inv-address,.inv-bill-to-name,.inv-bill-to-line{font-size:13px;line-height:1.5}
                .inv-bill-to-label{margin-bottom:6px;font-size:13px}
                .inv-dates{margin-top:12px;padding-top:10px}
                .inv-dates p{margin-bottom:6px;font-size:13px}
                .inv-table{margin-bottom:14px;border-radius:0}
                .inv-table thead th{padding:8px 12px;font-size:12px}
                .inv-table tbody td{padding:8px 12px;font-size:13px}
                .inv-summary{padding:12px 16px;margin-bottom:14px;border-radius:0;break-inside:avoid;page-break-inside:avoid}
                .inv-summary-row{padding:2px 0;font-size:13px;line-height:1.4}
                .inv-summary-total{margin-top:8px;padding-top:8px;font-size:17px}
                .inv-summary-total .inv-summary-value{font-size:18px}
                .inv-note{font-size:12px;line-height:1.45;margin-bottom:10px;padding:8px 12px}
                .inv-payment{font-size:13px;line-height:1.45;break-inside:avoid;page-break-inside:avoid}
                .inv-payment-label{margin-bottom:6px;font-size:13px}
                .inv-payee{font-size:15px;line-height:1.35}
                .inv-bank{line-height:1.45}
                .inv-vat-no{margin-top:14px;font-size:13px}
                .inv-top,.inv-parties,.inv-table{break-inside:avoid;page-break-inside:avoid}
            }
        </style>';
    }
}
