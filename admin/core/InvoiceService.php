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
        return FinancialDocumentRenderer::renderInvoice($case, $invoice);
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

}
