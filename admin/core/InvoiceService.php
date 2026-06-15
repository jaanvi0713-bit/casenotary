<?php

declare(strict_types=1);

class InvoiceService
{
    /**
     * @return list<array{
     *   description:string,
     *   quantity:float,
     *   unit_price:float,
     *   line_total:float,
     *   net:float,
     *   vat:float,
     *   vat_rate:float,
     *   category:string
     * }>
     */
    public static function billingToInvoiceLineItems(array $billing): array
    {
        $items      = [];
        $vatRate    = (float) ($billing['vat_rate'] ?? CaseService::vatRate());
        $nonVatRate = (float) ($billing['non_vat_rate'] ?? CaseService::NON_VAT_RATE);

        foreach ($billing['non_vat'] ?? [] as $row) {
            $net = (float) ($row['net'] ?? 0);
            if ($net <= 0) {
                continue;
            }
            $vat   = round($net * $nonVatRate / 100, 2);
            $gross = round($net + $vat, 2);
            $items[] = self::makeLineItem(
                (string) ($row['type'] ?? 'Service'),
                1.0,
                $net,
                $vat,
                $nonVatRate,
                'non_vat',
                $gross
            );
        }

        foreach ($billing['vat'] ?? [] as $row) {
            $net = (float) ($row['net'] ?? 0);
            if ($net <= 0) {
                continue;
            }
            $vat   = round($net * $vatRate / 100, 2);
            $gross = round($net + $vat, 2);
            $items[] = self::makeLineItem(
                (string) ($row['type'] ?? 'Service'),
                1.0,
                $net,
                $vat,
                $vatRate,
                'vat',
                $gross
            );
        }

        return $items;
    }

    /**
     * @return list<array{
     *   description:string,
     *   quantity:float,
     *   unit_price:float,
     *   line_total:float,
     *   net:float,
     *   vat:float,
     *   vat_rate:float,
     *   category:string
     * }>
     */
    public static function parseLineItemsFromRequest(array $data, array $case): array
    {
        if (isset($data['invoice_services_non_vat']) || isset($data['invoice_services_vat'])) {
            $billing = CaseService::parseInvoiceBillingFromRequest($data);
            $items   = self::billingToInvoiceLineItems($billing);
            if ($items !== []) {
                return $items;
            }
        }

        return self::parseLegacyFlatLineItems($data, $case);
    }

    /**
     * @param list<array<string, mixed>> $lineItems
     * @return array{
     *   subtotal:float,
     *   tax_amount:float,
     *   tax_rate:float,
     *   total:float,
     *   non_vat_net:float,
     *   non_vat_gross:float,
     *   vat_net:float,
     *   vat_gross:float,
     *   vat_enabled:bool
     * }
     */
    public static function totalsFromLineItems(array $lineItems): array
    {
        $nonVatNet     = 0.0;
        $nonVatRateAmt = 0.0;
        $vatNet        = 0.0;
        $vatAmt        = 0.0;
        $vatRate       = 0.0;

        foreach ($lineItems as $row) {
            if (!is_array($row)) {
                continue;
            }

            $qty     = max(1.0, (float) ($row['quantity'] ?? 1));
            $unitNet = (float) ($row['unit_price'] ?? 0);
            $net     = (float) ($row['net'] ?? round($qty * $unitNet, 2));
            $vat     = (float) ($row['vat'] ?? $row['vat_amount'] ?? 0);
            $rate    = (float) ($row['vat_rate'] ?? 0);
            $category = (string) ($row['category'] ?? '');

            if ($category === '') {
                $category = $vat > 0 || $rate > 0 ? 'vat' : 'non_vat';
            }

            if ($category === 'non_vat') {
                $nonVatNet += $net;
                $nonVatRateAmt += $vat;
            } else {
                $vatNet += $net;
                $vatAmt += $vat;
                if ($rate > 0) {
                    $vatRate = $rate;
                }
            }
        }

        $nonVatGross = round($nonVatNet + $nonVatRateAmt, 2);
        $vatGross    = round($vatNet + $vatAmt, 2);
        $subtotal    = round($nonVatNet + $vatNet, 2);
        $taxTotal    = round($nonVatRateAmt + $vatAmt, 2);
        $grand       = round($nonVatGross + $vatGross, 2);

        return [
            'subtotal'              => $subtotal,
            'tax_amount'            => $taxTotal,
            'tax_rate'              => $vatRate,
            'total'                 => $grand,
            'non_vat_net'           => round($nonVatNet, 2),
            'non_vat_gross'         => $nonVatGross,
            'non_vat_rate_amount'   => round($nonVatRateAmt, 2),
            'vat_net'               => round($vatNet, 2),
            'vat_gross'             => $vatGross,
            'vat_amount'            => round($vatAmt, 2),
            'vat_enabled'           => $taxTotal > 0.001,
        ];
    }

    /**
     * @param list<array<string, mixed>> $lineItems
     * @return array<string, float>
     */
    public static function financialSummaryOptions(array $invoice, array $lineItems, float $amountPaid, float $amountDue): array
    {
        $totals = self::totalsFromLineItems($lineItems);
        $grand  = (float) ($invoice['total'] ?? 0);
        $tax    = (float) ($invoice['tax_amount'] ?? 0);

        if ($grand <= 0) {
            $grand = $totals['total'];
        }
        if ($tax <= 0) {
            $tax = $totals['tax_amount'];
        }

        return [
            'grand_total'         => $grand,
            'tax_amount'          => $tax,
            'amount_paid'         => $amountPaid,
            'amount_due'          => $amountDue,
            'subtotal'            => $totals['subtotal'],
            'non_vat_net'         => $totals['non_vat_net'],
            'non_vat_gross'       => $totals['non_vat_gross'],
            'non_vat_rate_amount' => $totals['non_vat_rate_amount'],
            'vat_net'             => $totals['vat_net'],
            'vat_gross'           => $totals['vat_gross'],
            'vat_amount'          => $totals['vat_amount'],
        ];
    }

    /**
     * @deprecated Use billingToInvoiceLineItems()
     * @return list<array{description:string, quantity:float, unit_price:float, line_total:float}>
     */
    public static function lineItemsFromBilling(array $billing): array
    {
        return self::billingToInvoiceLineItems($billing);
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
        $stored = self::decodeLineItems((string) ($invoice['line_items'] ?? ''));
        if ($stored !== []) {
            $totals = self::totalsFromLineItems($stored);

            return [
                'line_subtotal' => $totals['subtotal'],
                'non_vat'       => $totals['non_vat_gross'],
                'vat_net'       => $totals['vat_net'],
                'vat_amount'    => $totals['tax_amount'],
                'total'         => (float) ($invoice['total'] ?? $totals['total']),
                'has_vat'       => $totals['vat_enabled'],
                'has_non_vat'   => $totals['non_vat_net'] > 0.001,
                'has_vat_net'   => $totals['vat_net'] > 0.001,
            ];
        }

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
     * @return list<array<string, mixed>>
     */
    public static function decodeLineItems(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return list<array{description:string, line_total:float}>
     */
    public static function resolveLineItems(array $invoice, array $case): array
    {
        $decoded = self::decodeLineItems((string) ($invoice['line_items'] ?? ''));
        if ($decoded !== []) {
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
                $rows[]    = array_merge($service, [
                    'description' => $desc,
                    'quantity'    => $qty > 0 ? $qty : 1.0,
                    'unit_price'  => $unit,
                    'line_total'  => $lineTotal,
                ]);
            }

            if ($rows !== []) {
                return $rows;
            }
        }

        return self::billingToInvoiceLineItems(CaseService::getCaseBilling($case));
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

    /**
     * @return list<array{
     *   description:string,
     *   quantity:float,
     *   unit_price:float,
     *   line_total:float,
     *   net:float,
     *   vat:float,
     *   vat_rate:float,
     *   category:string
     * }>
     */
    private static function parseLegacyFlatLineItems(array $data, array $case): array
    {
        $descriptions = $data['item_description'] ?? [];
        $qtys         = $data['item_qty'] ?? [];
        $prices       = $data['item_amount'] ?? [];
        $categories   = $data['item_category'] ?? [];
        $vatRates     = $data['item_vat_rate'] ?? [];

        if (!is_array($descriptions) || !is_array($qtys) || !is_array($prices)) {
            return self::billingToInvoiceLineItems(CaseService::getCaseBilling($case));
        }

        $defaultVatRate = CaseService::vatRate();
        $rows           = [];
        $max            = max(count($descriptions), count($qtys), count($prices));

        for ($i = 0; $i < $max; $i++) {
            $description = trim((string) ($descriptions[$i] ?? ''));
            $qty         = (float) ($qtys[$i] ?? 0);
            $unit        = (float) ($prices[$i] ?? 0);
            $category    = (string) ($categories[$i] ?? 'non_vat');
            $rate        = (float) ($vatRates[$i] ?? ($category === 'vat' ? $defaultVatRate : 0));

            if ($description === '' && $qty <= 0 && $unit <= 0) {
                continue;
            }

            if ($qty <= 0) {
                $qty = 1.0;
            }

            $net = round($qty * $unit, 2);
            $vat = $category === 'vat' ? round($net * $rate / 100, 2) : 0.0;
            $rows[] = self::makeLineItem(
                $description !== '' ? $description : 'Service',
                $qty,
                $unit,
                $vat,
                $category === 'vat' ? $rate : 0.0,
                $category === 'vat' ? 'vat' : 'non_vat',
                round($net + $vat, 2)
            );
        }

        if ($rows === []) {
            return self::billingToInvoiceLineItems(CaseService::getCaseBilling($case));
        }

        return $rows;
    }

    private static function makeLineItem(
        string $description,
        float $quantity,
        float $unitNet,
        float $vat,
        float $vatRate,
        string $category,
        float $gross
    ): array {
        return [
            'description' => $description,
            'quantity'    => $quantity,
            'unit_price'  => round($unitNet, 2),
            'line_total'  => round($gross, 2),
            'net'         => round($quantity * $unitNet, 2),
            'vat'         => round($vat, 2),
            'vat_rate'    => round($vatRate, 2),
            'category'    => $category,
        ];
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
