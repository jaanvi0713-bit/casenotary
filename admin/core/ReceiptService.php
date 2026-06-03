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

        return Database::fetch(self::selectSql() . ' AND r.id = ? AND i.client_id = ?', [$id, $clientId]) ?: null;
    }

    private static function selectSql(): string
    {
        $postal = clientPostalSelectSql('cl');
        $tenant = TenantService::isEnabled()
            ? ' AND cs.company_id = ' . TenantService::id()
            : '';

        return 'SELECT r.*, i.invoice_number, i.total AS invoice_total,
            cl.first_name, cl.last_name, cl.email AS client_email, cl.company_name,
            cl.address, cl.city, cl.state, ' . $postal . ', cl.country,
            p.payment_method, p.paid_at, p.notes AS payment_notes, p.amount AS payment_amount
         FROM receipts r
         JOIN payments p ON p.id = r.payment_id
         JOIN invoices i ON i.id = p.invoice_id
         JOIN cases cs ON cs.id = i.case_id
         JOIN clients cl ON cl.id = i.client_id
         WHERE 1=1' . $tenant;
    }

    public static function renderHtml(array $receipt): string
    {
        $company      = getCompanySettings();
        $primary      = (string) ($company['primary_color'] ?? '#3aafa9');
        $secondary    = (string) ($company['secondary_color'] ?? '#00182c');
        $companyName  = e(companyBrandName($company));
        $logoUrl      = companyLogoUrl($company);
        $addressHtml  = companyAddressHtml($company);
        $clientName   = e(clientFullName($receipt));
        $clientAddress = clientAddressHtml($receipt);
        $number       = e($receipt['receipt_number'] ?? '');
        $method       = e(ucwords(str_replace('_', ' ', (string) ($receipt['payment_method'] ?? 'other'))));
        $amount       = formatCurrency((float) ($receipt['amount'] ?? $receipt['payment_amount'] ?? 0));
        $issuedAt     = formatDateTimeStacked($receipt['created_at'] ?? '');
        $paidAt       = formatDateTimeStacked($receipt['paid_at'] ?? $receipt['created_at'] ?? '');
        $invoiceNo    = e($receipt['invoice_number'] ?? '');
        $notes        = trim((string) ($receipt['payment_notes'] ?? ''));

        $detailRows = '<tr><td>Invoice</td><td>' . $invoiceNo . '</td></tr>'
            . '<tr><td>Payment method</td><td>' . $method . '</td></tr>'
            . '<tr><td>Paid at</td><td class="rcp-datetime">' . $paidAt . '</td></tr>';

        if ($notes !== '') {
            $detailRows .= '<tr><td>Notes</td><td>' . e($notes) . '</td></tr>';
        }

        return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Receipt ' . $number . '</title>'
            . self::styles($primary, $secondary, $company)
            . '</head><body>'
            . '<div class="no-print"><button type="button" onclick="window.print()">Print / Save as PDF</button></div>'
            . '<div class="receipt-doc">'
            . '<header class="rcp-top">'
            . '<div class="rcp-brand">'
            . ($logoUrl ? '<img src="' . e($logoUrl) . '" alt="' . $companyName . '" class="rcp-logo">' : '<div class="rcp-logo-text">' . $companyName . '</div>')
            . '</div>'
            . '<div class="rcp-heading"><h1>RECEIPT</h1><div class="rcp-number">' . $number . '</div><div class="rcp-issued rcp-datetime">' . $issuedAt . '</div></div>'
            . '</header>'
            . '<section class="rcp-parties">'
            . '<div class="rcp-from">'
            . '<div class="rcp-from-name">' . $companyName . '</div>'
            . ($addressHtml !== '' ? '<div class="rcp-address">' . $addressHtml . '</div>' : '')
            . (!empty($company['office_email']) ? '<div class="rcp-contact">' . e($company['office_email']) . '</div>' : '')
            . '</div>'
            . '<div class="rcp-bill-to">'
            . '<p class="rcp-bill-to-label">Bill To:</p>'
            . '<p class="rcp-bill-to-name">' . $clientName . '</p>'
            . ($clientAddress !== '' ? '<p class="rcp-bill-to-line">' . $clientAddress . '</p>' : '')
            . '</div>'
            . '</section>'
            . '<table class="rcp-table"><thead><tr><th>Detail</th><th class="num">Value</th></tr></thead><tbody>'
            . $detailRows
            . '</tbody></table>'
            . '<section class="rcp-summary" aria-label="Amount received">'
            . '<div class="rcp-summary-row"><span class="rcp-summary-label">Amount received</span><span class="rcp-summary-value">' . $amount . '</span></div>'
            . '</section>'
            . '</div></body></html>';
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
            .receipt-doc{max-width:880px;margin:0 auto}
            .rcp-top{display:flex;justify-content:space-between;align-items:flex-start;gap:32px;padding-bottom:28px;margin-bottom:36px;border-bottom:1px solid #e2e8f0}
            .rcp-logo{max-height:100px;max-width:300px;object-fit:contain;display:block}
            .rcp-logo-text{font-size:20px;font-weight:700;color:' . $secondary . ';letter-spacing:.02em}
            .rcp-heading{text-align:right;flex-shrink:0}
            .rcp-heading h1{margin:0;color:' . $primary . ';font-size:44px;font-weight:700;letter-spacing:.06em;line-height:1}
            .rcp-number{font-size:20px;font-weight:700;color:' . $secondary . ';margin-top:10px;letter-spacing:.02em}
            .rcp-issued{margin-top:6px;font-size:14px;color:#64748b}
            .rcp-datetime{line-height:1.45}
            .rcp-table tbody td.rcp-datetime{text-align:right}
            .rcp-parties{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);column-gap:64px;align-items:start;margin-bottom:40px;width:100%}
            .rcp-from{grid-column:1;min-width:0}
            .rcp-bill-to{grid-column:2;min-width:0;text-align:right;padding-right:2px}
            .rcp-from-name{font-size:16px;font-weight:700;color:' . $secondary . ';margin:0 0 8px}
            .rcp-address,.rcp-contact{margin:0;font-size:15px;line-height:1.8;color:#475569}
            .rcp-contact{margin-top:4px}
            .rcp-bill-to-label{margin:0 0 12px;font-size:15px;font-weight:700;color:#1e293b;text-align:right}
            .rcp-bill-to-name{margin:0 0 8px;font-size:15px;font-weight:400;color:#475569;line-height:1.8;text-align:right}
            .rcp-bill-to-line{margin:0;font-size:15px;line-height:1.8;color:#475569;text-align:right}
            .rcp-table{width:100%;border-collapse:collapse;margin-bottom:32px;border:1px solid #cbd5e1;border-radius:6px;overflow:hidden}
            .rcp-table thead th{background:' . $secondary . ';color:#fff;font-weight:600;font-size:14px;letter-spacing:.03em;padding:14px 18px;border:none}
            .rcp-table tbody td{padding:14px 18px;font-size:15px;color:#1e293b;border:none;border-top:1px solid #e2e8f0;vertical-align:top}
            .rcp-table tbody tr:first-child td{border-top:none}
            .rcp-table .num{text-align:right;width:160px;padding-right:18px}
            .rcp-table thead th.num{padding-right:18px}
            .rcp-table tbody td:last-child{text-align:right;font-weight:500;font-variant-numeric:tabular-nums}
            .rcp-summary{background:' . $primary . ';color:#fff;padding:22px 26px;border-radius:6px}
            .rcp-summary-row{display:flex;justify-content:space-between;align-items:baseline;gap:32px;font-size:22px;font-weight:700;line-height:1.4}
            .rcp-summary-label{opacity:.95}
            .rcp-summary-value{font-variant-numeric:tabular-nums;white-space:nowrap}
            @page{size:A4 portrait;margin:8mm 10mm}
            @media print{
                html,body{height:auto;margin:0;padding:0;font-size:13px;line-height:1.45;-webkit-print-color-adjust:exact;print-color-adjust:exact}
                .no-print{display:none!important}
                .receipt-doc{max-width:none;margin:0;padding:0}
                .rcp-top{gap:20px;padding-bottom:14px;margin-bottom:16px}
                .rcp-logo{max-height:88px;max-width:260px}
                .rcp-heading h1{font-size:34px}
                .rcp-number{font-size:17px;margin-top:6px}
                .rcp-issued{font-size:12px}
                .rcp-parties{column-gap:40px;margin-bottom:18px}
                .rcp-from-name{margin-bottom:4px;font-size:14px}
                .rcp-address,.rcp-contact,.rcp-bill-to-name,.rcp-bill-to-line{font-size:13px;line-height:1.5}
                .rcp-bill-to-label{margin-bottom:6px;font-size:13px}
                .rcp-table{margin-bottom:14px;border-radius:0;break-inside:avoid;page-break-inside:avoid}
                .rcp-table thead th{padding:8px 12px;font-size:12px}
                .rcp-table tbody td{padding:8px 12px;font-size:13px}
                .rcp-summary{padding:12px 16px;border-radius:0;break-inside:avoid;page-break-inside:avoid}
                .rcp-summary-row{font-size:17px}
                .rcp-top,.rcp-parties{break-inside:avoid;page-break-inside:avoid}
            }
        </style>';
    }
}
