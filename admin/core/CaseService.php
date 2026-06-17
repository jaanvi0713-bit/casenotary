<?php

declare(strict_types=1);

require_once __DIR__ . '/CaseChecklistService.php';

class CaseService
{
    public const STATUSES = [
        'pending',
        'in_progress',
        'waiting_for_client',
        'completed',
        'closed',
    ];

    public const STATUS_TRANSITIONS = [
        'pending'            => ['in_progress', 'waiting_for_client', 'closed'],
        'in_progress'        => ['waiting_for_client', 'completed', 'closed'],
        'waiting_for_client' => ['in_progress', 'completed', 'closed'],
        'completed'          => ['closed', 'in_progress'],
        'closed'             => ['pending'],
    ];

    private static ?string $clientPostalSelectSql = null;

    private static function ensureStatusHistorySchema(): void
    {
        if (Database::tableExists('case_status_history')) {
            return;
        }

        try {
            Database::query(
                'CREATE TABLE IF NOT EXISTS case_status_history (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    case_id INT UNSIGNED NOT NULL,
                    from_status VARCHAR(50) DEFAULT NULL,
                    to_status VARCHAR(50) NOT NULL,
                    note VARCHAR(500) DEFAULT NULL,
                    changed_by INT UNSIGNED DEFAULT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_case_status_history_case (case_id),
                    INDEX idx_case_status_history_created (created_at)
                ) ENGINE=InnoDB'
            );
        } catch (Throwable $e) {
            // Best-effort runtime migration.
        }
    }

    public static function isValidStatus(string $status): bool
    {
        return in_array($status, self::STATUSES, true);
    }

    public static function canTransitionStatus(string $from, string $to): bool
    {
        if ($from === $to) {
            return true;
        }

        if (!self::isValidStatus($from) || !self::isValidStatus($to)) {
            return false;
        }

        return in_array($to, self::STATUS_TRANSITIONS[$from] ?? [], true);
    }

    public static function getAllowedStatuses(string $current): array
    {
        if (!self::isValidStatus($current)) {
            return self::STATUSES;
        }

        $options = array_merge([$current], self::STATUS_TRANSITIONS[$current] ?? []);

        return array_values(array_unique($options));
    }

    public static function statusLabel(string $status): string
    {
        return ucwords(str_replace('_', ' ', $status));
    }

    public static function assertStatusTransition(string $from, string $to): void
    {
        if (!self::canTransitionStatus($from, $to)) {
            throw new RuntimeException(
                'Cannot change status from ' . self::statusLabel($from) . ' to ' . self::statusLabel($to) . '.'
            );
        }
    }
    public const DEFAULT_VAT_RATE = 20.0;
    public const NON_VAT_RATE = 0.0;

    public static function vatRate(): float
    {
        return self::DEFAULT_VAT_RATE;
    }

    /**
     * @return array{version:int, vat_rate:float, non_vat:list<array{type:string, net:float}>, vat:list<array{type:string, net:float}>, totals:array<string, float>}
     */
    public static function emptyCaseBilling(): array
    {
        return self::buildCaseBilling([], [], self::vatRate());
    }

    /**
     * @return list<array{type:string, net:float}>
     */
    private static function parseServiceRowsFromRequest(?array $types, ?array $fees): array
    {
        if (!is_array($types) || !is_array($fees)) {
            return [];
        }

        $rows = [];
        $unnamed = 0;
        foreach ($types as $index => $type) {
            $type = trim((string) $type);
            $net  = max(0, (float) ($fees[$index] ?? 0));
            if ($type === '' && $net <= 0) {
                continue;
            }
            if ($type === '') {
                $unnamed++;
                $type = 'Service ' . $unnamed;
            }

            $rows[] = [
                'type' => $type,
                'net'  => $net,
            ];
        }

        return $rows;
    }

    /**
     * @param list<array{type:string, net:float}> $nonVat
     * @param list<array{type:string, net:float}> $vatNet
     * @return array{version:int, vat_rate:float, non_vat:list<array{type:string, net:float}>, vat:list<array{type:string, net:float}>, totals:array<string, float>}
     */
    public static function buildCaseBilling(
        array $nonVat,
        array $vatNet,
        ?float $vatRate = null,
        ?float $nonVatRate = null
    ): array {
        $vatRate    = $vatRate ?? self::vatRate();
        $nonVatRate = self::NON_VAT_RATE;

        $nonVatNetSub = 0.0;
        foreach ($nonVat as $row) {
            $nonVatNetSub += (float) ($row['net'] ?? 0);
        }

        $vatNetSub = 0.0;
        foreach ($vatNet as $row) {
            $vatNetSub += (float) ($row['net'] ?? 0);
        }

        $nonVatRateAmount = round($nonVatNetSub * $nonVatRate / 100, 2);
        $nonVatGross      = round($nonVatNetSub + $nonVatRateAmount, 2);
        $vatAmount        = round($vatNetSub * $vatRate / 100, 2);
        $grand            = round($nonVatGross + $vatNetSub + $vatAmount, 2);

        return [
            'version'      => 2,
            'vat_rate'     => $vatRate,
            'non_vat_rate' => $nonVatRate,
            'non_vat'      => $nonVat,
            'vat'          => $vatNet,
            'totals'       => [
                'non_vat_net_subtotal' => round($nonVatNetSub, 2),
                'non_vat_rate_amount'  => $nonVatRateAmount,
                'non_vat_subtotal'     => $nonVatGross,
                'vat_net_subtotal'     => round($vatNetSub, 2),
                'vat_amount'           => $vatAmount,
                'vat_gross_subtotal'   => round($vatNetSub + $vatAmount, 2),
                'grand_total'          => $grand,
            ],
        ];
    }

    /**
     * @return array{version:int, vat_rate:float, non_vat:list, vat:list, totals:array<string, float>}
     */
    public static function parseCaseBillingFromRequest(array $data): array
    {
        $nonVat = self::parseServiceRowsFromRequest(
            $data['services_non_vat']['type'] ?? null,
            $data['services_non_vat']['fee'] ?? null
        );
        $vatNet = self::parseServiceRowsFromRequest(
            $data['services_vat']['type'] ?? null,
            $data['services_vat']['fee'] ?? null
        );

        if ($nonVat === [] && $vatNet === []) {
            $legacy = self::parseLegacyServicesFromRequest($data);

            return self::billingFromLegacyFlatList($legacy);
        }

        $vatRate = self::vatRate();
        if (isset($data['vat_rate']) && $data['vat_rate'] !== '') {
            $vatRate = max(0.0, min(100.0, (float) $data['vat_rate']));
        }

        return self::buildCaseBilling($nonVat, $vatNet, $vatRate);
    }

    /**
     * @return array{version:int, vat_rate:float, non_vat:list, vat:list, totals:array<string, float>}
     */
    public static function parseInvoiceBillingFromRequest(array $data): array
    {
        $nonVat = self::parseServiceRowsFromRequest(
            $data['invoice_services_non_vat']['type'] ?? null,
            $data['invoice_services_non_vat']['fee'] ?? null
        );
        $vatNet = self::parseServiceRowsFromRequest(
            $data['invoice_services_vat']['type'] ?? null,
            $data['invoice_services_vat']['fee'] ?? null
        );

        $vatRate = self::vatRate();
        if (isset($data['invoice_vat_rate']) && $data['invoice_vat_rate'] !== '') {
            $vatRate = max(0.0, min(100.0, (float) $data['invoice_vat_rate']));
        }

        return self::buildCaseBilling($nonVat, $vatNet, $vatRate);
    }

    /**
     * @return list<array{type:string, fee:float}>
     */
    private static function parseLegacyServicesFromRequest(array $data): array
    {
        $types = $data['services']['type'] ?? null;
        $fees  = $data['services']['fee'] ?? null;

        if (is_array($types) && is_array($fees)) {
            $services = [];
            foreach ($types as $index => $type) {
                $type = trim((string) $type);
                if ($type === '') {
                    continue;
                }
                $services[] = ['type' => $type, 'fee' => (float) ($fees[$index] ?? 0)];
            }
            if ($services !== []) {
                return $services;
            }
        }

        $type = trim((string) (is_array($data['service_type'] ?? null) ? ($data['service_type'][0] ?? '') : ($data['service_type'] ?? '')));
        if ($type === '') {
            throw new RuntimeException('At least one service is required.');
        }

        $fee = $data['service_fee'] ?? 0;
        if (is_array($fee)) {
            $fee = $fee[0] ?? 0;
        }

        return [['type' => $type, 'fee' => (float) $fee]];
    }

    /**
     * @param list<array{type:string, fee:float}> $legacy
     */
    private static function billingFromLegacyFlatList(array $legacy): array
    {
        $nonVat = [];
        foreach ($legacy as $item) {
            $nonVat[] = [
                'type' => (string) ($item['type'] ?? ''),
                'net'  => (float) ($item['fee'] ?? 0),
            ];
        }

        return self::buildCaseBilling($nonVat, [], self::vatRate());
    }

    /**
     * @return array{version:int, vat_rate:float, non_vat:list, vat:list, totals:array<string, float>}
     */
    public static function getCaseBilling(array $case): array
    {
        if (!empty($case['services'])) {
            $decoded = is_string($case['services']) ? json_decode($case['services'], true) : $case['services'];

            if (is_array($decoded) && isset($decoded['version']) && (int) $decoded['version'] === 2) {
                $nonVat = is_array($decoded['non_vat'] ?? null) ? $decoded['non_vat'] : [];
                $vat    = is_array($decoded['vat'] ?? null) ? $decoded['vat'] : [];
                $rate = (float) ($decoded['vat_rate'] ?? self::vatRate());

                return self::buildCaseBilling($nonVat, $vat, $rate);
            }

            if (is_array($decoded) && $decoded !== []) {
                $legacy = [];
                foreach ($decoded as $item) {
                    if (!is_array($item)) {
                        continue;
                    }
                    $type = trim((string) ($item['type'] ?? $item['description'] ?? ''));
                    if ($type === '') {
                        continue;
                    }
                    $legacy[] = [
                        'type' => $type,
                        'fee'  => (float) ($item['fee'] ?? $item['amount'] ?? $item['net'] ?? 0),
                    ];
                }
                if ($legacy !== []) {
                    return self::billingFromLegacyFlatList($legacy);
                }
            }
        }

        $grand = (float) ($case['service_fee'] ?? 0);
        $nonVatSub = (float) ($case['fee_non_vat'] ?? $grand);
        $vatNet    = (float) ($case['fee_vat_net'] ?? 0);
        $vatAmt    = (float) ($case['fee_vat_amount'] ?? 0);

        if ($vatNet > 0 || $vatAmt > 0) {
            return [
                'version'  => 2,
                'vat_rate' => self::vatRate(),
                'non_vat'  => $nonVatSub > 0 ? [['type' => trim($case['service_type'] ?? 'Service'), 'net' => $nonVatSub]] : [],
                'vat'      => $vatNet > 0 ? [['type' => 'VAT services', 'net' => $vatNet]] : [],
                'totals'   => [
                    'non_vat_subtotal'   => round($nonVatSub, 2),
                    'vat_net_subtotal'   => round($vatNet, 2),
                    'vat_amount'         => round($vatAmt, 2),
                    'vat_gross_subtotal' => round($vatNet + $vatAmt, 2),
                    'grand_total'        => round($grand, 2),
                ],
            ];
        }

        $type = trim($case['service_type'] ?? '');
        $nonVat = $type !== '' ? [['type' => $type, 'net' => $grand]] : [];

        return self::buildCaseBilling($nonVat, [], self::vatRate());
    }

    public static function parseServicesFromRequest(array $data): array
    {
        $billing = self::parseCaseBillingFromRequest($data);

        return self::billingToDisplayServices($billing);
    }

    /**
     * @return list<array{type:string, fee:float, category:string, net:float, vat_amount?:float, gross?:float}>
     */
    public static function billingToDisplayServices(array $billing): array
    {
        $vatRate    = (float) ($billing['vat_rate'] ?? self::vatRate());
        $nonVatRate = (float) ($billing['non_vat_rate'] ?? 0);
        $out        = [];

        foreach ($billing['non_vat'] ?? [] as $row) {
            $net  = (float) ($row['net'] ?? 0);
            $rate = round($net * $nonVatRate / 100, 2);
            $out[] = [
                'type'        => (string) ($row['type'] ?? ''),
                'net'         => $net,
                'rate_amount' => $rate,
                'gross'       => round($net + $rate, 2),
                'fee'         => round($net + $rate, 2),
                'category'    => 'non_vat',
            ];
        }

        foreach ($billing['vat'] ?? [] as $row) {
            $net = (float) ($row['net'] ?? 0);
            $vat = round($net * $vatRate / 100, 2);
            $out[] = [
                'type'        => (string) ($row['type'] ?? ''),
                'net'         => $net,
                'vat_amount'  => $vat,
                'gross'       => round($net + $vat, 2),
                'fee'         => round($net + $vat, 2),
                'category'    => 'vat',
            ];
        }

        return $out;
    }

    public static function getCaseServices(array $case): array
    {
        $display = self::billingToDisplayServices(self::getCaseBilling($case));

        if ($display === []) {
            return [['type' => '', 'fee' => 0.0, 'category' => 'non_vat', 'net' => 0.0]];
        }

        return $display;
    }

    public static function caseServicesTotal(array $servicesOrBilling): float
    {
        if (isset($servicesOrBilling['totals']['grand_total'])) {
            return (float) $servicesOrBilling['totals']['grand_total'];
        }

        $total = 0.0;
        foreach ($servicesOrBilling as $service) {
            if (!is_array($service)) {
                continue;
            }
            if (isset($service['gross'])) {
                $total += (float) $service['gross'];
            } else {
                $total += (float) ($service['fee'] ?? $service['net'] ?? 0);
            }
        }

        return round($total, 2);
    }

    public static function formatCaseServicesLabel(array $services, int $maxLength = 150): string
    {
        if ($services === []) {
            return '';
        }

        if (isset($services['non_vat']) || isset($services['totals'])) {
            $labels = [];
            foreach ($services['non_vat'] ?? [] as $row) {
                $labels[] = (string) ($row['type'] ?? '');
            }
            foreach ($services['vat'] ?? [] as $row) {
                $labels[] = (string) ($row['type'] ?? '');
            }
            $joined = implode(', ', array_filter($labels));
        } else {
            $labels = array_map(static fn(array $service): string => (string) ($service['type'] ?? ''), $services);
            $joined = implode(', ', array_filter($labels));
        }

        if ($joined === '') {
            return '';
        }

        if (strlen($joined) <= $maxLength) {
            return $joined;
        }

        $parts = explode(', ', $joined);

        return count($parts) > 1
            ? $parts[0] . ' +' . (count($parts) - 1) . ' more'
            : substr($joined, 0, $maxLength - 3) . '...';
    }

    public static function formatCaseBillingOverviewHtml(array $case): string
    {
        $billing = self::getCaseBilling($case);
        $t       = $billing['totals'];
        $hasNon  = ($billing['non_vat'] ?? []) !== [];
        $hasVat  = ($billing['vat'] ?? []) !== [];

        if (!$hasNon && !$hasVat) {
            return '<p class="text-muted small mb-0">No services listed.</p>';
        }

        $html = '<div class="case-billing-overview">';

        if ($hasNon) {
            $html .= self::formatBillingOverviewSection(
                'Non-VAT services',
                (float) ($billing['non_vat_rate'] ?? 0),
                $billing['non_vat'],
                false,
                (float) ($t['non_vat_subtotal'] ?? 0)
            );
        }

        if ($hasVat) {
            $html .= self::formatBillingOverviewSection(
                'VAT services',
                (float) ($billing['vat_rate'] ?? self::vatRate()),
                $billing['vat'],
                true,
                (float) ($t['vat_gross_subtotal'] ?? 0)
            );
        }

        $html .= '<div class="case-billing-overview-grand">'
            . '<span>Total fee</span>'
            . '<strong>' . formatCurrency((float) ($t['grand_total'] ?? 0)) . '</strong>'
            . '</div></div>';

        return $html;
    }

    /**
     * @param list<array{type:string, net:float}> $lines
     */
    private static function formatBillingOverviewSection(
        string $title,
        float $rate,
        array $lines,
        bool $isVatSection,
        float $sectionTotal
    ): string {
        $rateLabel = e(rtrim(rtrim(number_format($rate, 2), '0'), '.'));
        $addonName = $isVatSection ? 'VAT' : 'Rate';

        $html = '<div class="case-billing-overview-section">';
        $html .= '<div class="case-billing-overview-section-head">';
        $html .= '<span class="case-billing-overview-section-title">' . e($title) . '</span>';
        if ($rate > 0) {
            $html .= '<span class="case-billing-overview-section-rate">' . $addonName . ' ' . $rateLabel . '%</span>';
        }
        $html .= '</div>';
        $html .= '<table class="case-billing-overview-table"><thead><tr>';
        $html .= '<th>Service</th><th class="text-end">Net</th><th class="text-end">Total</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($lines as $row) {
            $net   = (float) ($row['net'] ?? 0);
            $addon = round($net * $rate / 100, 2);
            $gross = round($net + $addon, 2);
            $html .= '<tr><td>' . e((string) ($row['type'] ?? 'Service')) . '</td>';
            $html .= '<td class="text-end text-muted">' . formatCurrency($net) . '</td>';
            $html .= '<td class="text-end fw-semibold">' . formatCurrency($gross) . '</td></tr>';
        }

        $html .= '</tbody><tfoot><tr>';
        $html .= '<td colspan="2">Subtotal</td>';
        $html .= '<td class="text-end">' . formatCurrency($sectionTotal) . '</td>';
        $html .= '</tr></tfoot></table></div>';

        return $html;
    }

    /** @deprecated Use formatCaseBillingOverviewHtml */
    public static function formatCaseServicesHtml(array $case): string
    {
        return '';
    }

    /** @deprecated Use formatCaseBillingOverviewHtml */
    public static function formatCaseServicesTotalsFooter(array $case): string
    {
        return '';
    }

    private static function ensureCasesSchema(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        if (!Database::tableExists('cases') || Database::columnExists('cases', 'services')) {
            return;
        }

        try {
            Database::query('ALTER TABLE cases ADD COLUMN services JSON DEFAULT NULL AFTER service_fee');
        } catch (Throwable $e) {
            // Migration may need to be run manually on restricted hosts.
        }

        foreach ([
            'fee_non_vat'    => 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER service_fee',
            'fee_vat_net'    => 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER fee_non_vat',
            'fee_vat_amount' => 'DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER fee_vat_net',
        ] as $column => $definition) {
            if (!Database::columnExists('cases', $column)) {
                try {
                    Database::query("ALTER TABLE cases ADD COLUMN {$column} {$definition}");
                } catch (Throwable $e) {
                    // Optional columns — totals still stored in service_fee + JSON.
                }
            }
        }
    }

    private static function resolveCaseServices(array $data): array
    {
        $billing = self::parseCaseBillingFromRequest($data);
        $totals  = $billing['totals'];

        return [
            'billing'         => $billing,
            'service_type'    => self::formatCaseServicesLabel($billing),
            'service_fee'     => (float) $totals['grand_total'],
            'fee_non_vat'     => (float) $totals['non_vat_subtotal'],
            'fee_vat_net'     => (float) $totals['vat_net_subtotal'],
            'fee_vat_amount'  => (float) $totals['vat_amount'],
            'services_json'   => json_encode($billing, JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * @return list<array{description:string, quantity:float, unit_price:float, line_total:float}>
     */
    public static function billingToInvoiceLineItems(array $billing): array
    {
        return InvoiceService::billingToInvoiceLineItems($billing);
    }

    private static function insertCaseRow(array $row): int
    {
        $columns = array_keys($row);
        $sql     = sprintf(
            'INSERT INTO cases (%s, created_at, updated_at) VALUES (%s, NOW(), NOW())',
            implode(', ', $columns),
            implode(', ', array_fill(0, count($columns), '?'))
        );

        return Database::insert($sql, array_values($row));
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed>|null $case
     * @return array<string, mixed>
     */
    private static function withCaseCompanyId(array $row, ?array $case, string $table): array
    {
        if (!TenantService::isEnabled() || !Database::columnExists($table, 'company_id')) {
            return $row;
        }

        $companyId = (int) ($case['company_id'] ?? 0);
        if ($companyId <= 0) {
            $companyId = TenantService::id();
        }

        $row['company_id'] = $companyId;

        return $row;
    }

    private static function updateCaseRow(int $id, array $row): void
    {
        $sets   = [];
        $params = [];

        foreach ($row as $column => $value) {
            $sets[]   = "{$column} = ?";
            $params[] = $value;
        }

        $sets[]   = 'updated_at = NOW()';
        $params[] = $id;

        Database::query('UPDATE cases SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
    }

    public static function generateNumber(string $prefix): string
    {
        $year = date('Y');
        $pattern = $prefix . '-' . $year . '-%';
        $tableMap = [
            'CASE' => ['cases', 'case_number'],
            'INV'  => ['invoices', 'invoice_number'],
            'QUO'  => ['quotations', 'quotation_number'],
            'PRO'  => ['proposals', 'proposal_number'],
            'RCP'  => ['receipts', 'receipt_number'],
            'PAY'  => ['payments', 'payment_number'],
        ];

        [$table, $column] = $tableMap[$prefix] ?? ['cases', 'case_number'];

        try {
            $params = [$pattern];
            $scope  = '';
            if (TenantService::isEnabled() && Database::columnExists($table, 'company_id')) {
                $scope = ' AND company_id = ?';
                $params[] = TenantService::id();
            }

            $row = Database::fetch(
                "SELECT MAX(CAST(SUBSTRING_INDEX({$column}, '-', -1) AS UNSIGNED)) AS max_num
                 FROM {$table} WHERE {$column} LIKE ?{$scope}",
                $params
            );
            $count = (int) ($row['max_num'] ?? 0);
        } catch (Throwable $e) {
            $count = random_int(100, 999);
        }

        return sprintf('%s-%s-%04d', $prefix, $year, $count + 1);
    }

    public static function generateInvoiceNumber(?int $companyId = null): string
    {
        $year = (int) date('Y');
        if ($companyId === null || $companyId <= 0) {
            $companyId = TenantService::isEnabled() ? TenantService::id() : 0;
        }

        $sequence = self::nextInvoiceSequence($companyId);

        return self::invoiceNumberFromSequence($companyId, $sequence, $year);
    }

    public static function invoiceNumberFromSequence(int $companyId, int $sequence, int $year): string
    {
        $suffix = self::encodeInvoiceSuffix($companyId, $sequence);
        $number = sprintf('INV-%d-%s', $year, $suffix);

        for ($attempt = 1; $attempt < 20 && self::invoiceNumberExists($number, $companyId); $attempt++) {
            $suffix = self::encodeInvoiceSuffix($companyId, $sequence + $attempt);
            $number = sprintf('INV-%d-%s', $year, $suffix);
        }

        return $number;
    }

    public static function isLegacyInvoiceNumber(string $number): bool
    {
        return (bool) preg_match('/^INV-\d{4}-\d+$/', strtoupper(trim($number)));
    }

    /**
     * @return array{updated: int, skipped: int, details: list<string>}
     */
    public static function migrateLegacyInvoiceNumbers(): array
    {
        $hasCompany = TenantService::isEnabled() && Database::columnExists('invoices', 'company_id');
        $orderBy    = $hasCompany ? 'company_id ASC, id ASC' : 'id ASC';
        $invoices   = Database::fetchAll(
            "SELECT id, case_id, invoice_number, issue_date" . ($hasCompany ? ', company_id' : '') . "
             FROM invoices
             ORDER BY {$orderBy}"
        );

        $sequences = [];
        $updated   = 0;
        $skipped   = 0;
        $details   = [];

        foreach ($invoices as $invoice) {
            $oldNumber = (string) ($invoice['invoice_number'] ?? '');
            if (!self::isLegacyInvoiceNumber($oldNumber)) {
                $skipped++;
                continue;
            }

            $companyId = $hasCompany ? (int) ($invoice['company_id'] ?? 0) : 0;
            $sequences[$companyId] = ($sequences[$companyId] ?? 0) + 1;
            $sequence = $sequences[$companyId];

            $year = (int) date('Y');
            if (preg_match('/^INV-(\d{4})-/i', $oldNumber, $matches)) {
                $year = (int) $matches[1];
            } elseif (!empty($invoice['issue_date'])) {
                $year = (int) date('Y', strtotime((string) $invoice['issue_date']));
            }

            $newNumber = self::invoiceNumberFromSequence($companyId, $sequence, $year);
            Database::query('UPDATE invoices SET invoice_number = ? WHERE id = ?', [$newNumber, (int) $invoice['id']]);

            $caseId = (int) ($invoice['case_id'] ?? 0);
            if ($caseId > 0) {
                try {
                    self::regenerateInvoiceHtml($caseId, (int) $invoice['id']);
                } catch (Throwable $e) {
                    // HTML regeneration is best-effort during migration.
                }
            }

            $details[] = "{$oldNumber} -> {$newNumber}";
            $updated++;
        }

        return ['updated' => $updated, 'skipped' => $skipped, 'details' => $details];
    }

    private static function nextInvoiceSequence(int $companyId): int
    {
        $params = [];
        $scope  = '';

        if ($companyId > 0 && TenantService::isEnabled() && Database::columnExists('invoices', 'company_id')) {
            $scope    = ' WHERE company_id = ?';
            $params[] = $companyId;
        }

        $row = Database::fetch('SELECT COUNT(*) AS cnt FROM invoices' . $scope, $params);

        return (int) ($row['cnt'] ?? 0) + 1;
    }

    private static function encodeInvoiceSuffix(int $companyId, int $sequence): string
    {
        $alphabet = '0123456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $base     = strlen($alphabet);
        $state    = abs(crc32("cn-inv|{$companyId}|{$sequence}"));
        $chars    = [];

        for ($i = 0; $i < 5; $i++) {
            $chars[] = $alphabet[$state % $base];
            $state   = intdiv($state, $base) + ($companyId * 997 + $sequence * 1009 + $i * 7919);
        }

        return implode('', $chars);
    }

    private static function invoiceNumberExists(string $number, int $companyId): bool
    {
        $params = [$number];
        $scope  = '';

        if ($companyId > 0 && TenantService::isEnabled() && Database::columnExists('invoices', 'company_id')) {
            $scope    = ' AND company_id = ?';
            $params[] = $companyId;
        }

        return (bool) Database::fetch(
            'SELECT 1 FROM invoices WHERE invoice_number = ?' . $scope . ' LIMIT 1',
            $params
        );
    }

    public static function generateReceiptNumber(?int $companyId = null): string
    {
        $year = (int) date('Y');
        if ($companyId === null || $companyId <= 0) {
            $companyId = TenantService::isEnabled() ? TenantService::id() : 0;
        }

        $sequence = self::nextReceiptSequence($companyId);

        return self::receiptNumberFromSequence($companyId, $sequence, $year);
    }

    public static function receiptNumberFromSequence(int $companyId, int $sequence, int $year): string
    {
        $suffix = self::encodeReceiptSuffix($companyId, $sequence);
        $number = sprintf('RCP-%d-%s', $year, $suffix);

        for ($attempt = 1; $attempt < 20 && self::receiptNumberExists($number, $companyId); $attempt++) {
            $suffix = self::encodeReceiptSuffix($companyId, $sequence + $attempt);
            $number = sprintf('RCP-%d-%s', $year, $suffix);
        }

        return $number;
    }

    public static function isLegacyReceiptNumber(string $number): bool
    {
        return (bool) preg_match('/^RCP-\d{4}-\d+$/', strtoupper(trim($number)));
    }

    /**
     * @return array{updated: int, skipped: int, details: list<string>}
     */
    public static function migrateLegacyReceiptNumbers(): array
    {
        $hasReceiptCompany = TenantService::isEnabled() && Database::columnExists('receipts', 'company_id');
        $hasInvoiceCompany = Database::columnExists('invoices', 'company_id');
        $companySelect     = $hasReceiptCompany
            ? 'r.company_id'
            : ($hasInvoiceCompany ? 'i.company_id' : '0 AS company_id');
        $orderBy           = ($hasReceiptCompany || $hasInvoiceCompany) ? 'company_id ASC, r.id ASC' : 'r.id ASC';
        $receipts          = Database::fetchAll(
            "SELECT r.id, r.receipt_number,
                    COALESCE(r.issued_at, r.created_at) AS issued_at,
                    {$companySelect}
             FROM receipts r
             LEFT JOIN payments p ON p.id = r.payment_id
             LEFT JOIN invoices i ON i.id = p.invoice_id
             ORDER BY {$orderBy}"
        );

        $sequences = [];
        $updated   = 0;
        $skipped   = 0;
        $details   = [];

        foreach ($receipts as $receipt) {
            $oldNumber = (string) ($receipt['receipt_number'] ?? '');
            if (!self::isLegacyReceiptNumber($oldNumber)) {
                $skipped++;
                continue;
            }

            $companyId = (int) ($receipt['company_id'] ?? 0);
            $sequences[$companyId] = ($sequences[$companyId] ?? 0) + 1;
            $sequence = $sequences[$companyId];

            $year = (int) date('Y');
            if (preg_match('/^RCP-(\d{4})-/i', $oldNumber, $matches)) {
                $year = (int) $matches[1];
            } elseif (!empty($receipt['issued_at'])) {
                $year = (int) date('Y', strtotime((string) $receipt['issued_at']));
            }

            $newNumber = self::receiptNumberFromSequence($companyId, $sequence, $year);
            Database::query('UPDATE receipts SET receipt_number = ? WHERE id = ?', [$newNumber, (int) $receipt['id']]);

            $details[] = "{$oldNumber} -> {$newNumber}";
            $updated++;
        }

        return ['updated' => $updated, 'skipped' => $skipped, 'details' => $details];
    }

    private static function nextReceiptSequence(int $companyId): int
    {
        if ($companyId > 0 && TenantService::isEnabled() && Database::columnExists('receipts', 'company_id')) {
            $row = Database::fetch('SELECT COUNT(*) AS cnt FROM receipts WHERE company_id = ?', [$companyId]);
        } elseif ($companyId > 0 && Database::columnExists('invoices', 'company_id')) {
            $row = Database::fetch(
                'SELECT COUNT(*) AS cnt
                 FROM receipts r
                 JOIN payments p ON p.id = r.payment_id
                 JOIN invoices i ON i.id = p.invoice_id
                 WHERE i.company_id = ?',
                [$companyId]
            );
        } else {
            $row = Database::fetch('SELECT COUNT(*) AS cnt FROM receipts');
        }

        return (int) ($row['cnt'] ?? 0) + 1;
    }

    private static function encodeReceiptSuffix(int $companyId, int $sequence): string
    {
        $alphabet = '0123456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $base     = strlen($alphabet);
        $state    = abs(crc32("cn-rcp|{$companyId}|{$sequence}"));
        $chars    = [];

        for ($i = 0; $i < 5; $i++) {
            $chars[] = $alphabet[$state % $base];
            $state   = intdiv($state, $base) + ($companyId * 997 + $sequence * 1009 + $i * 7919);
        }

        return implode('', $chars);
    }

    private static function receiptNumberExists(string $number, int $companyId): bool
    {
        $params = [$number];
        $scope  = '';

        if ($companyId > 0 && TenantService::isEnabled() && Database::columnExists('receipts', 'company_id')) {
            $scope    = ' AND company_id = ?';
            $params[] = $companyId;
        }

        return (bool) Database::fetch(
            'SELECT 1 FROM receipts WHERE receipt_number = ?' . $scope . ' LIMIT 1',
            $params
        );
    }

    public static function getCaseById(int $id): ?array
    {
        $postalSelect = self::clientPostalSelectSql();

        $case = Database::fetch(
            "SELECT cs.*, cl.first_name, cl.last_name, cl.email, cl.phone, cl.company_name,
                    cl.address, cl.city, cl.state, {$postalSelect}, cl.country,
                    cl.user_id AS client_user_id, adm.name AS admin_name
             FROM cases cs
             JOIN clients cl ON cl.id = cs.client_id
             LEFT JOIN users adm ON adm.id = cs.assigned_admin_id
             WHERE cs.id = ?" . (TenantService::isEnabled() ? ' AND cs.company_id = ?' : ''),
            TenantService::isEnabled() ? [$id, TenantService::id()] : [$id]
        );

        if (!$case) {
            return null;
        }

        if (Auth::restrictsToAssignedCases() && (int) ($case['assigned_admin_id'] ?? 0) !== (int) Auth::id()) {
            return null;
        }

        return $case;
    }

    public static function assertCaseAccess(int $caseId): void
    {
        if ($caseId <= 0 || !self::getCaseById($caseId)) {
            throw new RuntimeException('Case not found or you do not have access to it.');
        }
    }

    public static function getCaseForClient(int $caseId, int $clientId): ?array
    {
        $postalSelect = self::clientPostalSelectSql();

        return Database::fetch(
            "SELECT cs.*, cl.first_name, cl.last_name, cl.email, cl.company_name,
                    cl.address, cl.city, cl.state, {$postalSelect}, cl.country
             FROM cases cs
             JOIN clients cl ON cl.id = cs.client_id
             WHERE cs.id = ? AND cs.client_id = ?" . (TenantService::isEnabled() ? ' AND cs.company_id = ?' : ''),
            TenantService::isEnabled() ? [$caseId, $clientId, TenantService::id()] : [$caseId, $clientId]
        );
    }

    private static function clientPostalSelectSql(): string
    {
        if (self::$clientPostalSelectSql !== null) {
            return self::$clientPostalSelectSql;
        }

        if (Database::columnExists('clients', 'zip_code')) {
            self::$clientPostalSelectSql = 'cl.zip_code';
        } elseif (Database::columnExists('clients', 'zip')) {
            self::$clientPostalSelectSql = 'cl.zip AS zip_code';
        } else {
            self::$clientPostalSelectSql = 'NULL AS zip_code';
        }

        return self::$clientPostalSelectSql;
    }

    public static function getWorkspace(int $caseId): ?array
    {
        $case = self::getCaseById($caseId);
        if (!$case) {
            return null;
        }

        return [
            'case'        => $case,
            'documents'   => self::getDocuments($caseId),
            'quotations'  => self::getQuotations($caseId),
            'proposals'   => self::getProposals($caseId),
            'invoices'    => self::getInvoices($caseId),
            'payments'    => self::getPayments($caseId),
            'receipts'        => self::getReceipts($caseId),
            'client_letters'  => ClientLetterService::listForCase($caseId, false),
            'checklist'       => CaseChecklistService::getChecklist($caseId, (string) ($case['service_type'] ?? '')),
            'status_history'  => self::getStatusHistory($caseId),
            'notes'           => self::getNotes($caseId, true),
            'activity'        => self::getActivity($caseId),
        ];
    }

    private static function resolveAssignedAdminId(array $data, int $adminId): int
    {
        if (Auth::restrictsToAssignedCases()) {
            return $adminId;
        }

        return !empty($data['assigned_admin_id']) ? (int) $data['assigned_admin_id'] : $adminId;
    }

    private static function resolveAssignedAdminIdForUpdate(array $data, array $existing): ?int
    {
        if (Auth::restrictsToAssignedCases()) {
            $assigned = (int) ($existing['assigned_admin_id'] ?? 0);

            return $assigned > 0 ? $assigned : Auth::id();
        }

        return !empty($data['assigned_admin_id']) ? (int) $data['assigned_admin_id'] : null;
    }

    public static function createCase(array $data, int $adminId): int
    {
        self::ensureCasesSchema();
        self::ensureStatusHistorySchema();

        $caseNumber   = self::generateNumber('CASE');
        $instructions = trim($data['client_instructions'] ?? '') ?: null;

        $resolved = self::resolveCaseServices($data);
        $client   = ClientService::getById((int) $data['client_id']);
        if (!$client) {
            throw new RuntimeException('Client not found.');
        }

        $row      = [
            'case_number'       => $caseNumber,
            'title'             => trim($data['title']),
            'description'       => trim($data['description'] ?? '') ?: null,
            'service_type'      => $resolved['service_type'],
            'service_fee'       => $resolved['service_fee'],
            'client_id'         => (int) $data['client_id'],
            'assigned_admin_id' => self::resolveAssignedAdminId($data, $adminId),
            'priority'          => 'medium',
            'deadline'          => null,
            'status'            => 'pending',
        ];

        if (TenantService::isEnabled() && Database::columnExists('cases', 'company_id')) {
            $row['company_id'] = (int) ($client['company_id'] ?? TenantService::id());
        }

        if (Database::columnExists('cases', 'client_instructions')) {
            $row['client_instructions'] = $instructions;
        }

        if (Database::columnExists('cases', 'services')) {
            $row['services'] = $resolved['services_json'];
        }

        foreach (['fee_non_vat', 'fee_vat_net', 'fee_vat_amount'] as $feeCol) {
            if (Database::columnExists('cases', $feeCol)) {
                $row[$feeCol] = $resolved[$feeCol];
            }
        }

        $id = self::insertCaseRow($row);
        CaseChecklistService::ensureCaseChecklist($id, (string) ($resolved['service_type'] ?? ''));
        self::addStatusHistory($id, null, 'pending', 'Case created', $adminId);

        if ($instructions && !Database::columnExists('cases', 'client_instructions') && Database::tableExists('case_notes')) {
            try {
                self::addNote($id, $adminId, 'Client instructions: ' . $instructions, false);
            } catch (Throwable $e) {
                // Optional notes table
            }
        }

        try {
            self::notifyCaseEvent($id, 'case', 'New case created', "Case {$caseNumber} was created.", 'pages/case-view.php?id=' . $id);
        } catch (Throwable $e) {
            // Notifications are optional
        }

        return $id;
    }

    public static function runCreateWorkflow(int $caseId, array $data, int $adminId): array
    {
        $case   = self::getCaseById($caseId);
        $client = ClientService::getById((int) ($case['client_id'] ?? 0));

        if (!$case || !$client) {
            return ['quote_sent' => false, 'client_letter_sent' => false, 'login_sent' => false, 'error' => null];
        }

        if (empty($client['email'])) {
            return ['quote_sent' => false, 'client_letter_sent' => false, 'login_sent' => false, 'error' => null];
        }

        $instructions     = trim($data['client_instructions'] ?? $case['client_instructions'] ?? '');
        $sendQuotation    = !isset($data['send_emails']) || !empty($data['send_emails']);
        $sendClientLetter = !empty($data['send_client_letter']);

        if (!$sendQuotation && !$sendClientLetter) {
            return ['quote_sent' => false, 'client_letter_sent' => false, 'login_sent' => false, 'error' => null];
        }

        $quoteSent        = false;
        $clientLetterSent = false;
        $loginSent        = false;
        $error            = null;

        if ($sendQuotation) {
            try {
                if (!Database::tableExists('quotations')) {
                    throw new RuntimeException(
                        'Quotation tables are not set up yet. Run: php admin/sql/migrate_cases.php'
                    );
                }

                $quotationId = self::generateQuotation($caseId, [
                    'title'  => 'Quotation — ' . $case['title'],
                    'amount' => (float) $case['service_fee'],
                ]);

                $quotation = Database::fetch('SELECT * FROM quotations WHERE id = ?', [$quotationId]);
                $docPath   = null;

                if (!empty($quotation['pdf_path'])) {
                    $config  = require __DIR__ . '/../config/config.php';
                    $docPath = rtrim($config['upload']['path'], '/\\') . '/' . ltrim($quotation['pdf_path'], '/');
                }

                $quoteSent = MailService::sendQuoteEmail(
                    $client,
                    $case,
                    $quotation['quotation_number'] ?? 'QUO',
                    $docPath && is_file($docPath) ? $docPath : null
                );
            } catch (Throwable $e) {
                $error = 'Quotation email could not be sent. You can generate one from the case page.';
            }

            try {
                if (!empty($client['user_id'])) {
                    $loginSent = MailService::sendLoginEmail($client, $instructions);
                }
            } catch (Throwable $e) {
                $error = ($error ? $error . ' ' : '') . 'Portal login email could not be sent.';
            }
        }

        if ($sendClientLetter) {
            try {
                $letterPath = self::generateClientLetter($caseId, $instructions);
                $clientLetterSent = MailService::sendClientLetterEmail(
                    $client,
                    $case,
                    $letterPath && is_file($letterPath) ? $letterPath : null
                );
            } catch (Throwable $e) {
                $error = ($error ? $error . ' ' : '') . 'Client letter email could not be sent.';
            }
        }

        return [
            'quote_sent'         => $quoteSent,
            'client_letter_sent' => $clientLetterSent,
            'login_sent'         => $loginSent,
            'error'              => $error,
        ];
    }

    public static function updateCase(int $id, array $data): void
    {
        self::ensureCasesSchema();

        $existing = self::getCaseById($id);
        if (!$existing) {
            throw new RuntimeException('Case not found.');
        }

        $instructions = trim($data['client_instructions'] ?? '') ?: null;
        $resolved     = self::resolveCaseServices($data);
        $row          = [
            'title'             => trim($data['title']),
            'description'       => trim($data['description'] ?? '') ?: null,
            'service_type'      => $resolved['service_type'],
            'service_fee'       => $resolved['service_fee'],
            'client_id'         => (int) $data['client_id'],
            'assigned_admin_id' => self::resolveAssignedAdminIdForUpdate($data, $existing),
            'priority'          => $existing['priority'] ?? 'medium',
            'deadline'          => $existing['deadline'] ?? null,
            'status'            => $existing['status'] ?? 'pending',
        ];

        if (Database::columnExists('cases', 'client_instructions') && array_key_exists('client_instructions', $data)) {
            $row['client_instructions'] = $instructions;
        }

        if (Database::columnExists('cases', 'services')) {
            $row['services'] = $resolved['services_json'];
        }

        foreach (['fee_non_vat', 'fee_vat_net', 'fee_vat_amount'] as $feeCol) {
            if (Database::columnExists('cases', $feeCol)) {
                $row[$feeCol] = $resolved[$feeCol];
            }
        }

        self::updateCaseRow($id, $row);

        self::notifyCaseEvent($id, 'case', 'Case updated', 'Case details were updated.', 'pages/case-view.php?id=' . $id);
    }

    public static function updateStatus(int $id, string $status, ?int $userId = null): void
    {
        self::ensureStatusHistorySchema();
        $case = self::getCaseById($id);
        if (!$case) {
            throw new RuntimeException('Case not found.');
        }

        if (!self::isValidStatus($status)) {
            throw new RuntimeException('Invalid case status.');
        }

        self::assertStatusTransition($case['status'], $status);

        if ($case['status'] === $status) {
            return;
        }

        Database::query('UPDATE cases SET status = ?, updated_at = NOW() WHERE id = ?', [$status, $id]);
        self::addStatusHistory($id, (string) $case['status'], $status, null, $userId ?? Auth::id());

        self::logCaseEvent($id, 'status_changed', [
            'from' => $case['status'],
            'to'   => $status,
        ], $userId ?? Auth::id());

        $label = self::statusLabel($status);
        self::notifyCaseEvent($id, 'case', 'Case status updated', "Status changed to {$label}.", 'pages/case-view.php?id=' . $id);
    }

    public static function addStatusHistory(
        int $caseId,
        ?string $from,
        string $to,
        ?string $note = null,
        ?int $userId = null
    ): void {
        self::ensureStatusHistorySchema();
        if (!Database::tableExists('case_status_history')) {
            return;
        }
        try {
            Database::insert(
                'INSERT INTO case_status_history (case_id, from_status, to_status, note, changed_by, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())',
                [$caseId, $from, $to, $note, $userId ?? Auth::id()]
            );
        } catch (Throwable $e) {
            // optional
        }
    }

    public static function getStatusHistory(int $caseId, int $limit = 50): array
    {
        self::ensureStatusHistorySchema();
        if (!Database::tableExists('case_status_history')) {
            return [];
        }

        try {
            return Database::fetchAll(
                'SELECT h.*, u.name AS actor_name
                 FROM case_status_history h
                 LEFT JOIN users u ON u.id = h.changed_by
                 WHERE h.case_id = ?
                 ORDER BY h.created_at DESC
                 LIMIT ' . max(1, (int) $limit),
                [$caseId]
            );
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function getDocuments(int $caseId): array
    {
        try {
            return Database::fetchAll(
                "SELECT d.*, u.name AS uploader_name
                 FROM documents d
                 LEFT JOIN users u ON u.id = d.uploaded_by
                 WHERE d.case_id = ?
                 ORDER BY d.created_at DESC",
                [$caseId]
            );
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * @return array{success: bool, message: string}
     */
    private static function persistDocumentRecord(
        int $caseId,
        string $storedName,
        string $originalName,
        string $relativePath,
        string $ext,
        int $size,
        string $mimeType,
        int $userId,
        string $source
    ): array {
        $row = [
            'case_id'       => $caseId,
            'uploaded_by'   => $userId,
            'upload_source' => $source,
            'file_name'     => $storedName,
            'original_name' => $originalName,
            'file_path'     => $relativePath,
            'file_size'     => $size,
            'mime_type'     => $mimeType,
        ];
        $row[documentExtensionColumn()] = $ext;

        try {
            insertTableRow('documents', $row);
        } catch (Throwable $e) {
            error_log('[CaseService] Document insert failed: ' . $e->getMessage());

            return ['success' => false, 'message' => 'Could not save document record. Please contact support.'];
        }

        $label = $source === 'client' ? 'Client uploaded a document' : 'New document uploaded';
        self::notifyCaseEvent($caseId, 'document', $label, $originalName, 'pages/case-view.php?id=' . $caseId . '#documents');

        return ['success' => true, 'message' => 'Document uploaded successfully.'];
    }

    public static function uploadDocument(int $caseId, array $file, int $userId, string $source = 'admin'): array
    {
        $config = require __DIR__ . '/../config/config.php';
        $upload = $config['upload'];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Upload failed. Please try again.'];
        }

        if ($file['size'] > $upload['max_size']) {
            return ['success' => false, 'message' => 'File exceeds maximum size limit.'];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $upload['allowed_types'], true)) {
            return ['success' => false, 'message' => 'File type not allowed.'];
        }

        $caseDir = rtrim($upload['path'], '/\\') . '/cases/' . $caseId;
        if (!is_dir($caseDir)) {
            mkdir($caseDir, 0755, true);
        }

        $storedName = uniqid('doc_', true) . '.' . $ext;
        $destPath   = $caseDir . '/' . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            return ['success' => false, 'message' => 'Could not save uploaded file.'];
        }

        $relativePath = 'cases/' . $caseId . '/' . $storedName;
        $mimeType     = mime_content_type($destPath) ?: $file['type'] ?? 'application/octet-stream';

        return self::persistDocumentRecord(
            $caseId,
            $storedName,
            $file['name'],
            $relativePath,
            $ext,
            (int) $file['size'],
            $mimeType,
            $userId,
            $source
        );
    }

    public static function saveDocumentFromPath(int $caseId, string $sourcePath, string $originalName, int $userId, string $source = 'admin'): array
    {
        $config = require __DIR__ . '/../config/config.php';
        $upload = $config['upload'];

        if (!is_readable($sourcePath)) {
            return ['success' => false, 'message' => 'Staged file is missing. Please upload again.'];
        }

        $size = (int) (filesize($sourcePath) ?: 0);
        if ($size <= 0) {
            return ['success' => false, 'message' => 'Uploaded file is empty.'];
        }

        if ($size > $upload['max_size']) {
            return ['success' => false, 'message' => 'File exceeds maximum size limit.'];
        }

        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, $upload['allowed_types'], true)) {
            return ['success' => false, 'message' => 'File type not allowed.'];
        }

        $caseDir = rtrim($upload['path'], '/\\') . '/cases/' . $caseId;
        if (!is_dir($caseDir)) {
            mkdir($caseDir, 0755, true);
        }

        $storedName = uniqid('doc_', true) . '.' . $ext;
        $destPath = $caseDir . '/' . $storedName;

        if (!copy($sourcePath, $destPath)) {
            return ['success' => false, 'message' => 'Could not save uploaded file.'];
        }

        $relativePath = 'cases/' . $caseId . '/' . $storedName;
        $mimeType = mime_content_type($destPath) ?: 'application/octet-stream';

        return self::persistDocumentRecord(
            $caseId,
            $storedName,
            $originalName,
            $relativePath,
            $ext,
            $size,
            $mimeType,
            $userId,
            $source
        );
    }

    public static function getNotes(int $caseId, bool $internalOnly = false): array
    {
        try {
            $sql = "SELECT n.*, u.name AS author_name
                    FROM case_notes n
                    JOIN users u ON u.id = n.user_id
                    WHERE n.case_id = ?";
            $params = [$caseId];
            if ($internalOnly) {
                $sql .= ' AND n.is_internal = 1';
            }
            $sql .= ' ORDER BY n.created_at DESC';
            return Database::fetchAll($sql, $params);
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function addNote(int $caseId, int $userId, string $note, bool $internal = true): void
    {
        Database::insert(
            'INSERT INTO case_notes (case_id, user_id, note, is_internal, created_at) VALUES (?, ?, ?, ?, NOW())',
            [$caseId, $userId, trim($note), $internal ? 1 : 0]
        );
    }

    public static function getQuotations(int $caseId): array
    {
        try {
            return Database::fetchAll(
                'SELECT * FROM quotations WHERE case_id = ? ORDER BY created_at DESC',
                [$caseId]
            );
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function getProposals(int $caseId): array
    {
        try {
            return Database::fetchAll(
                'SELECT * FROM proposals WHERE case_id = ? ORDER BY created_at DESC',
                [$caseId]
            );
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function getInvoices(int $caseId): array
    {
        $statusCol = invoiceStatusColumn();

        return Database::fetchAll(
            "SELECT i.*, i.{$statusCol} AS payment_status
             FROM invoices i
             WHERE i.case_id = ?
             ORDER BY i.created_at DESC",
            [$caseId]
        );
    }

    public static function getPayments(int $caseId): array
    {
        $statusCol = paymentStatusColumn();

        return Database::fetchAll(
            "SELECT p.*, p.{$statusCol} AS payment_status,
                    i.invoice_number, i.total AS invoice_total
             FROM payments p
             JOIN invoices i ON i.id = p.invoice_id
             WHERE i.case_id = ?
             ORDER BY COALESCE(p.paid_at, p.created_at) DESC",
            [$caseId]
        );
    }

    public static function getReceipts(int $caseId): array
    {
        try {
            if (Database::columnExists('receipts', 'invoice_id')) {
                return Database::fetchAll(
                    "SELECT r.*, i.invoice_number
                     FROM receipts r
                     JOIN invoices i ON i.id = r.invoice_id
                     WHERE i.case_id = ?
                     ORDER BY r.created_at DESC",
                    [$caseId]
                );
            }

            return Database::fetchAll(
                "SELECT r.*, i.invoice_number, p.amount
                 FROM receipts r
                 JOIN payments p ON p.id = r.payment_id
                 JOIN invoices i ON i.id = p.invoice_id
                 WHERE i.case_id = ?
                 ORDER BY COALESCE(r.issued_at, r.created_at) DESC",
                [$caseId]
            );
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function generateQuotation(int $caseId, array $data): int
    {
        $case     = self::getCaseById($caseId);
        $number   = self::generateNumber('QUO');
        $billing  = self::getCaseBilling($case ?: []);
        $totals   = $billing['totals'];
        $subtotal = (float) (($totals['non_vat_net_subtotal'] ?? $totals['non_vat_subtotal']) + $totals['vat_net_subtotal']);
        $taxAmt   = (float) $totals['vat_amount'];
        $total    = (float) ($data['amount'] ?? $totals['grand_total']);
        if ($total <= 0) {
            $total = (float) $totals['grand_total'];
        }
        $taxRate = $subtotal > 0 && $taxAmt > 0 ? round($taxAmt / $subtotal * 100, 2) : (float) ($data['tax_rate'] ?? 0);

        $lineItems = [];
        foreach ($billing['non_vat'] ?? [] as $row) {
            $lineItems[] = ['description' => $row['type'] . ' (Non-VAT)', 'amount' => (float) $row['net']];
        }
        foreach ($billing['vat'] ?? [] as $row) {
            $lineItems[] = ['description' => $row['type'] . ' (VAT net)', 'amount' => (float) $row['net']];
        }

        if ($lineItems === []) {
            $lineItems = [['description' => $case['service_type'] ?? 'Service', 'amount' => $total]];
            $subtotal = $total;
            $taxAmt   = 0;
        }

        $lineItemsJson = json_encode($lineItems, JSON_UNESCAPED_UNICODE);

        $id = insertTableRow('quotations', self::withCaseCompanyId([
            'case_id'          => $caseId,
            'quotation_number' => $number,
            'title'            => $data['title'] ?? 'Quotation for ' . ($case['title'] ?? 'Case'),
            'line_items'       => $lineItemsJson,
            'notes'            => trim((string) ($data['notes'] ?? '')) ?: null,
            'subtotal'         => $subtotal,
            'tax_rate'         => $taxRate,
            'tax_amount'       => $taxAmt,
            'total'            => $total,
            'status'           => 'sent',
            'valid_until'      => $data['valid_until'] ?? date('Y-m-d', strtotime('+30 days')),
        ], $case, 'quotations'));

        self::saveHtmlDocument($caseId, 'quotation', $id);

        self::notifyCaseEvent($caseId, 'document', 'Quotation created', $number, 'pages/case-view.php?id=' . $caseId . '#quotations');

        return $id;
    }

    public static function generateClientLetter(int $caseId, string $instructions = '', ?array $sections = null): string
    {
        $case = self::getCaseById($caseId);
        if (!$case) {
            throw new RuntimeException('Case not found.');
        }

        if ($instructions !== '' && Database::columnExists('cases', 'client_instructions')) {
            Database::query(
                'UPDATE cases SET client_instructions = ?, updated_at = NOW() WHERE id = ?',
                [$instructions, $caseId]
            );
        }

        $sections = $sections ?? ClientLetterService::getSectionsForCase($caseId);

        return ClientLetterService::generateFile($caseId, $sections);
    }

    public static function getClientLetterRelativePath(int $caseId): ?string
    {
        $paths = ClientLetterService::getGeneratedLetterPaths($caseId);

        if ($paths['pdf']) {
            return 'cases/' . $caseId . '/generated/client_letter.pdf';
        }

        if ($paths['html']) {
            return 'cases/' . $caseId . '/generated/client_letter.html';
        }

        return null;
    }

    public static function sendClientLetterToClient(int $caseId): bool
    {
        $case = self::getCaseById($caseId);
        if (!$case) {
            throw new RuntimeException('Case not found.');
        }

        $client = ClientService::getById((int) ($case['client_id'] ?? 0));
        if (!$client || empty($client['email'])) {
            throw new RuntimeException('Client email not found.');
        }

        $instructions = trim($case['client_instructions'] ?? '');
        $saved = ClientLetterService::getCurrentSavedLetter($caseId);
        if ($saved) {
            ClientLetterService::regenerateSavedLetterFile((int) $saved['id']);
            $rel = ClientLetterService::getDownloadPath($saved);
            $letterPath = $rel ? self::documentPath($rel) : null;
        } else {
            $sections   = ClientLetterService::getSectionsForCase($caseId);
            $letterPath = self::generateClientLetter($caseId, $instructions, $sections);
        }

        if (!$letterPath || !is_file($letterPath)) {
            throw new RuntimeException('No letter file available. Generate and save the letter first.');
        }

        return MailService::sendClientLetterEmail($client, $case, $letterPath);
    }

    public static function sendInvoiceToClient(int $caseId, int $invoiceId): bool
    {
        $case = self::getCaseById($caseId);
        if (!$case) {
            throw new RuntimeException('Case not found.');
        }

        $invoice = Database::fetch('SELECT * FROM invoices WHERE id = ? AND case_id = ?', [$invoiceId, $caseId]);
        if (!$invoice) {
            throw new RuntimeException('Invoice not found for this case.');
        }

        $client = ClientService::getById((int) ($invoice['client_id'] ?? $case['client_id'] ?? 0));
        if (!$client || trim((string) ($client['email'] ?? '')) === '') {
            throw new RuntimeException('Client email not found.');
        }

        $documentPath = self::ensureInvoiceDocumentPath($caseId, $invoiceId, $invoice);
        $sent         = MailService::sendInvoiceEmail($client, $case, $invoice, $documentPath);

        if (!$sent) {
            throw new RuntimeException('Invoice email could not be sent. Check SMTP settings under Settings → Email.');
        }

        self::notifyCaseEvent(
            $caseId,
            'invoice',
            'Invoice emailed to client',
            (string) ($invoice['invoice_number'] ?? ''),
            'pages/case-view.php?id=' . $caseId . '#invoices'
        );

        return true;
    }

    public static function sendReceiptToClient(int $caseId, int $receiptId): bool
    {
        $case = self::getCaseById($caseId);
        if (!$case) {
            throw new RuntimeException('Case not found.');
        }

        $receipt = ReceiptService::fetchForAdmin($receiptId);
        if (!$receipt || (int) ($receipt['case_id'] ?? 0) !== $caseId) {
            throw new RuntimeException('Receipt not found for this case.');
        }

        $client = ClientService::getById((int) ($case['client_id'] ?? 0));
        if (!$client || trim((string) ($client['email'] ?? '')) === '') {
            throw new RuntimeException('Client email not found.');
        }

        $documentPath = self::writeReceiptDocument($caseId, $receiptId, $receipt);
        $sent         = MailService::sendReceiptEmail($client, $case, $receipt, $documentPath);

        if (!$sent) {
            throw new RuntimeException('Receipt email could not be sent. Check SMTP settings under Settings → Email.');
        }

        self::notifyCaseEvent(
            $caseId,
            'payment',
            'Receipt emailed to client',
            (string) ($receipt['receipt_number'] ?? ''),
            'pages/case-view.php?id=' . $caseId . '#invoice-payments'
        );

        return true;
    }

    /**
     * @param array<string, mixed> $invoice
     */
    private static function ensureInvoiceDocumentPath(int $caseId, int $invoiceId, array $invoice): ?string
    {
        if (empty($invoice['pdf_path']) || !is_file(self::documentPath((string) $invoice['pdf_path']))) {
            self::regenerateInvoiceHtml($caseId, $invoiceId);
            $invoice = Database::fetch('SELECT * FROM invoices WHERE id = ?', [$invoiceId]) ?: $invoice;
        }

        $relative = trim((string) ($invoice['pdf_path'] ?? ''));
        if ($relative === '') {
            return null;
        }

        $path = self::documentPath($relative);

        return is_file($path) ? $path : null;
    }

    private static function writeReceiptDocument(int $caseId, int $receiptId, array $receipt): ?string
    {
        $html = ReceiptService::renderHtml($receipt);
        if ($html === '') {
            return null;
        }

        $config = require __DIR__ . '/../config/config.php';
        $dir    = rtrim($config['upload']['path'], '/\\') . '/cases/' . $caseId . '/generated';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $path = $dir . '/receipt_' . $receiptId . '.html';
        file_put_contents($path, $html);

        return is_file($path) ? $path : null;
    }

    public static function generateProposal(int $caseId, array $data): int
    {
        $case    = self::getCaseById($caseId);
        $number  = self::generateNumber('PRO');
        $content = trim($data['content'] ?? '') ?: 'Proposal for notary services related to ' . $case['title'];
        $amount  = (float) ($data['amount'] ?? $case['service_fee'] ?? 0);

        $id = insertTableRow('proposals', self::withCaseCompanyId([
            'case_id'         => $caseId,
            'proposal_number' => $number,
            'title'           => $data['title'] ?? 'Proposal — ' . ($case['title'] ?? 'Case'),
            'content'         => $content,
            'amount'          => $amount,
            'total'           => $amount,
            'status'          => 'sent',
        ], $case, 'proposals'));

        self::saveHtmlDocument($caseId, 'proposal', $id);

        self::notifyCaseEvent($caseId, 'document', 'Proposal created', $number, 'pages/case-view.php?id=' . $caseId . '#quotations');

        return $id;
    }

    public static function generateInvoice(int $caseId, array $data): int
    {
        $case      = self::getCaseById($caseId);
        $companyId = (int) ($case['company_id'] ?? 0);
        if ($companyId <= 0 && TenantService::isEnabled()) {
            $companyId = TenantService::id();
        }
        $number = self::generateInvoiceNumber($companyId > 0 ? $companyId : null);
        $lineItems = InvoiceService::parseLineItemsFromRequest($data, $case ?: []);

        if ($lineItems === []) {
            $lineItems = InvoiceService::billingToInvoiceLineItems(self::getCaseBilling($case ?: []));
        }

        $totals = InvoiceService::totalsFromLineItems($lineItems);
        $due    = $data['due_date'] ?? date('Y-m-d', strtotime('+14 days'));
        $bankAccount = SettingsService::normalizeBankAccountChoice($data['bank_account'] ?? null);

        $invoiceRow = self::withCaseCompanyId([
            'invoice_number'  => $number,
            'case_id'         => $caseId,
            'client_id'       => $case['client_id'],
            'amount'          => $totals['subtotal'],
            'line_items'      => json_encode($lineItems, JSON_UNESCAPED_UNICODE),
            'subtotal'        => $totals['subtotal'],
            'tax_rate'        => $totals['tax_rate'],
            'tax_amount'      => $totals['tax_amount'],
            'total'           => $totals['total'],
            'vat_enabled'     => $totals['vat_enabled'] ? 1 : 0,
            'payment_status'  => 'pending',
            'status'          => 'pending',
            'issue_date'      => date('Y-m-d'),
            'due_date'        => $due,
            'notes'           => $data['notes'] ?? null,
            'payment_terms'        => trim((string) ($data['payment_terms'] ?? '')) ?: null,
            'payment_instructions' => trim((string) ($data['payment_instructions'] ?? '')) ?: null,
            'bank_account'         => $bankAccount,
        ], $case, 'invoices');

        $id = insertTableRow('invoices', $invoiceRow);

        if (!empty($data['generate_payment_link'])) {
            try {
                PaymentGatewayService::assignPaymentLink($id);
            } catch (Throwable $e) {
                error_log('[PaymentGateway] Could not create payment link: ' . $e->getMessage());
            }
        }

        self::saveHtmlDocument($caseId, 'invoice', $id);

        self::notifyCaseEvent($caseId, 'invoice', 'Invoice generated', $number . ' — ' . formatCurrency($totals['total']), 'pages/case-view.php?id=' . $caseId . '#invoice-payments');

        return $id;
    }

    public static function getInvoicePaidTotal(int $invoiceId): float
    {
        $statusCol = paymentStatusColumn();

        return (float) (Database::fetch(
            "SELECT COALESCE(SUM(amount), 0) AS total FROM payments
             WHERE invoice_id = ? AND {$statusCol} = 'completed'",
            [$invoiceId]
        )['total'] ?? 0);
    }

    public static function getInvoiceRemainingBalance(array $invoice): float
    {
        return max(0, round((float) ($invoice['total'] ?? 0) - self::getInvoicePaidTotal((int) $invoice['id']), 2));
    }

    public static function updateInvoicePaymentStatus(int $invoiceId): void
    {
        $invoice = Database::fetch('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
        if (!$invoice) {
            return;
        }

        $statusCol = invoiceStatusColumn();
        $paid      = self::getInvoicePaidTotal($invoiceId);
        $total     = (float) ($invoice['total'] ?? 0);

        if ($paid >= $total - 0.009) {
            $newStatus = 'paid';
        } elseif ($paid > 0) {
            $newStatus = 'partially_paid';
        } elseif (!empty($invoice['due_date']) && strtotime($invoice['due_date']) < strtotime('today')) {
            $newStatus = 'overdue';
        } else {
            $current = invoiceStatusValue($invoice);
            $newStatus = $current === 'failed' ? 'failed' : 'pending';
        }

        Database::query(
            "UPDATE invoices SET {$statusCol} = ?, updated_at = NOW()" . (Database::columnExists('invoices', 'amount_paid') ? ', amount_paid = ?' : '') . ' WHERE id = ?',
            Database::columnExists('invoices', 'amount_paid') ? [$newStatus, $paid, $invoiceId] : [$newStatus, $invoiceId]
        );
    }

    public static function recordStripePayment(int $invoiceId, float $amount, string $stripePaymentId): array
    {
        if ($stripePaymentId !== '') {
            $existing = self::findPaymentByTransactionId($stripePaymentId);
            if ($existing) {
                return ['success' => true, 'message' => 'Payment already recorded.', 'payment_id' => (int) $existing['id']];
            }
        }

        return self::recordPayment($invoiceId, [
            'amount'            => $amount,
            'payment_method'    => 'stripe',
            'stripe_payment_id' => $stripePaymentId,
            'transaction_id'    => $stripePaymentId,
            'notes'             => 'Paid via Stripe Checkout',
        ], 0);
    }

    public static function recordPayment(int $invoiceId, array $data, int $adminId): array
    {
        $invoice = Database::fetch('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
        if (!$invoice) {
            return ['success' => false, 'message' => 'Invoice not found.'];
        }

        $remaining = self::getInvoiceRemainingBalance($invoice);
        if ($remaining <= 0) {
            return ['success' => false, 'message' => 'This invoice is already fully paid.'];
        }

        $amount = isset($data['amount']) && $data['amount'] !== ''
            ? (float) $data['amount']
            : $remaining;
        $method = $data['payment_method'] ?? 'bank_transfer';
        $stripeId = trim($data['stripe_payment_id'] ?? '');

        if ($amount <= 0 || $amount > $remaining + 0.009) {
            return [
                'success' => false,
                'message' => 'Invalid payment amount. Remaining balance: ' . formatCurrency($remaining) . '.',
            ];
        }

        if ($stripeId !== '') {
            $existing = self::findPaymentByTransactionId($stripeId);
            if ($existing) {
                return ['success' => true, 'message' => 'Payment already recorded.', 'payment_id' => (int) $existing['id']];
            }
        }

        $statusCol = paymentStatusColumn();
        $txnCol    = paymentTransactionColumn();

        $paymentRow = [
            'invoice_id'          => $invoiceId,
            'client_id'           => $invoice['client_id'] ?? null,
            'payment_number'      => self::generateNumber('PAY'),
            'amount'              => $amount,
            'payment_method'      => $method,
            'stripe_payment_id'   => $stripeId ?: null,
            'transaction_id'      => $stripeId ?: null,
            $statusCol            => 'completed',
            'paid_at'             => date('Y-m-d H:i:s'),
            'notes'               => $data['notes'] ?? null,
            'created_by'          => $adminId > 0 ? $adminId : null,
        ];

        $caseId = (int) ($invoice['case_id'] ?? 0);
        $caseForCompany = $caseId > 0 ? self::getCaseById($caseId) : null;
        $paymentId = insertTableRow(
            'payments',
            self::withCaseCompanyId($paymentRow, $caseForCompany, 'payments'),
            Database::columnExists('payments', 'updated_at')
        );

        self::updateInvoicePaymentStatus($invoiceId);

        $invoice['payment_amount'] = $amount;
        $receiptId = self::generateReceipt($paymentId, $invoice);
        if ($caseId) {
            self::notifyCaseEvent(
                $caseId,
                'payment',
                'Payment received',
                formatCurrency($amount) . ' for ' . ($invoice['invoice_number'] ?? 'invoice'),
                'pages/case-view.php?id=' . $caseId . '#invoice-payments'
            );
        }

        return ['success' => true, 'message' => 'Payment recorded.', 'payment_id' => $paymentId, 'receipt_id' => $receiptId];
    }

    public static function generateReceipt(int $paymentId, ?array $invoice = null): int
    {
        if (!$invoice) {
            $invoice = Database::fetch(
                'SELECT i.*, p.amount AS payment_amount FROM invoices i JOIN payments p ON p.invoice_id = i.id WHERE p.id = ?',
                [$paymentId]
            );
        }

        $receiptCase = !empty($invoice['case_id']) ? self::getCaseById((int) $invoice['case_id']) : null;
        $companyId   = (int) ($invoice['company_id'] ?? $receiptCase['company_id'] ?? 0);
        if ($companyId <= 0 && TenantService::isEnabled()) {
            $companyId = TenantService::id();
        }
        $number = self::generateReceiptNumber($companyId > 0 ? $companyId : null);
        $amount = (float) ($invoice['payment_amount'] ?? $invoice['total'] ?? 0);

        try {
            return insertTableRow('receipts', self::withCaseCompanyId([
                'receipt_number' => $number,
                'payment_id'     => $paymentId,
                'invoice_id'     => $invoice['id'] ?? null,
                'client_id'      => $invoice['client_id'] ?? null,
                'amount'         => $amount,
                'issued_at'      => date('Y-m-d H:i:s'),
            ], $receiptCase, 'receipts'), Database::columnExists('receipts', 'updated_at'));
        } catch (Throwable $e) {
            return 0;
        }
    }

    private static function findPaymentByTransactionId(string $transactionId): ?array
    {
        if ($transactionId === '') {
            return null;
        }

        $column = paymentTransactionColumn();

        return Database::fetch(
            "SELECT id FROM payments WHERE {$column} = ? LIMIT 1",
            [$transactionId]
        );
    }

    public static function getActivity(int $caseId, int $limit = 50): array
    {
        $events = [];

        $case = self::getCaseById($caseId);
        if ($case) {
            $events[] = [
                'type'   => 'case_created',
                'title'  => 'Case created',
                'detail' => $case['case_number'],
                'time'   => $case['created_at'],
                'actor'  => null,
            ];
        }

        foreach (self::getDocuments($caseId) as $doc) {
            $events[] = [
                'type'   => 'document',
                'title'  => ($doc['upload_source'] ?? 'admin') === 'client' ? 'Client uploaded document' : 'Document uploaded',
                'detail' => $doc['original_name'] ?? $doc['file_name'],
                'time'   => $doc['created_at'],
                'actor'  => $doc['uploader_name'] ?? null,
            ];
        }

        foreach (self::getInvoices($caseId) as $inv) {
            $events[] = [
                'type'   => 'invoice',
                'title'  => 'Invoice generated',
                'detail' => ($inv['invoice_number'] ?? '') . ' · ' . formatCurrency((float) ($inv['total'] ?? 0)),
                'time'   => $inv['created_at'],
                'actor'  => null,
            ];
        }

        foreach (self::getPayments($caseId) as $pay) {
            $events[] = [
                'type'   => 'payment',
                'title'  => 'Payment received',
                'detail' => formatCurrency((float) ($pay['amount'] ?? 0)) . ' · ' . ($pay['invoice_number'] ?? ''),
                'time'   => $pay['paid_at'] ?? $pay['created_at'],
                'actor'  => null,
            ];
        }

        foreach (self::getStatusHistory($caseId, 100) as $statusEvent) {
            $from = (string) ($statusEvent['from_status'] ?? '');
            $to   = (string) ($statusEvent['to_status'] ?? '');
            $events[] = [
                'type'   => 'status',
                'title'  => 'Status changed',
                'detail' => ($from !== '' ? self::statusLabel($from) . ' → ' : '') . self::statusLabel($to),
                'time'   => $statusEvent['created_at'],
                'actor'  => $statusEvent['actor_name'] ?? null,
            ];
        }

        foreach (self::getProposals($caseId) as $pro) {
            $events[] = [
                'type'   => 'proposal',
                'title'  => 'Proposal created',
                'detail' => ($pro['proposal_number'] ?? '') . ' · ' . formatCurrency((float) ($pro['amount'] ?? $pro['total'] ?? 0)),
                'time'   => $pro['created_at'],
                'actor'  => null,
            ];
        }

        foreach (self::getQuotations($caseId) as $quo) {
            $events[] = [
                'type'   => 'quotation',
                'title'  => 'Quotation created',
                'detail' => ($quo['quotation_number'] ?? '') . ' · ' . formatCurrency((float) ($quo['total'] ?? 0)),
                'time'   => $quo['created_at'],
                'actor'  => null,
            ];
        }

        foreach (self::getNotes($caseId, true) as $note) {
            $events[] = [
                'type'   => 'note',
                'title'  => 'Internal note added',
                'detail' => mb_substr($note['note'], 0, 120),
                'time'   => $note['created_at'],
                'actor'  => $note['author_name'] ?? null,
            ];
        }

        try {
            foreach (Database::fetchAll(
                "SELECT a.*, u.name AS actor_name
                 FROM appointments a
                 WHERE a.case_id = ?
                 ORDER BY a.created_at DESC",
                [$caseId]
            ) as $appt) {
                $events[] = [
                    'type'   => 'appointment',
                    'title'  => 'Appointment scheduled',
                    'detail' => ($appt['title'] ?? 'Appointment') . ' · ' . formatDateTime($appt['start_time'] ?? $appt['created_at']),
                    'time'   => $appt['created_at'],
                    'actor'  => null,
                ];
            }
        } catch (Throwable $e) {
            // appointments optional
        }

        try {
            foreach (Database::fetchAll(
                "SELECT al.*, u.name AS actor_name
                 FROM audit_logs al
                 LEFT JOIN users u ON u.id = al.user_id
                 WHERE al.entity_type = 'case' AND al.entity_id = ?
                 ORDER BY al.created_at DESC",
                [$caseId]
            ) as $log) {
                $details = json_decode($log['details'] ?? '{}', true) ?: [];
                $event   = self::auditLogToActivityEvent($log['action'], $details, $log['actor_name'] ?? null);
                if ($event) {
                    $events[] = array_merge($event, ['time' => $log['created_at']]);
                }
            }
        } catch (Throwable $e) {
            // audit logs optional
        }

        usort($events, static fn($a, $b) => strtotime($b['time']) <=> strtotime($a['time']));

        return array_slice($events, 0, $limit);
    }

    private static function auditLogToActivityEvent(string $action, array $details, ?string $actor): ?array
    {
        if ($action === 'status_changed') {
            $from = self::statusLabel($details['from'] ?? '');
            $to   = self::statusLabel($details['to'] ?? '');

            return [
                'type'   => 'status',
                'title'  => 'Status changed',
                'detail' => $from . ' → ' . $to,
                'actor'  => $actor,
            ];
        }

        if ($action === 'checklist_toggled') {
            $item = (string) ($details['item_key'] ?? 'Checklist item');
            $completed = !empty($details['completed']);
            return [
                'type'   => 'note',
                'title'  => $completed ? 'Checklist item completed' : 'Checklist item reopened',
                'detail' => $item,
                'actor'  => $actor,
            ];
        }

        return null;
    }

    public static function logCaseEvent(int $caseId, string $action, array $details = [], ?int $userId = null): void
    {
        AuditService::log($action, 'case', $caseId, $details, $userId);
    }

    public static function getAdmins(): array
    {
        $nameExpr = Database::columnExists('users', 'name')
            ? 'COALESCE(NULLIF(TRIM(name), ""), TRIM(CONCAT(COALESCE(first_name, ""), " ", COALESCE(last_name, "")))) AS name'
            : 'TRIM(CONCAT(COALESCE(first_name, ""), " ", COALESCE(last_name, ""))) AS name';

        $companyId = TenantService::isEnabled() ? TenantService::id() : 1;
        $assignSlugs = array_values(array_filter(
            CompanyRoleService::activeSlugsForCompany($companyId),
            static fn(string $slug): bool => $slug !== 'viewer'
        ));
        if ($assignSlugs === []) {
            $assignSlugs = ['admin', 'manager', 'staff'];
        }

        $slugPlaceholders = implode(',', array_fill(0, count($assignSlugs), '?'));
        $where  = ["status = 'active'", "(role = 'super_admin' OR role IN ({$slugPlaceholders}))"];
        $params = $assignSlugs;

        if (Database::columnExists('users', 'is_active')) {
            $where[] = '(is_active IS NULL OR is_active = 1)';
        }

        if (TenantService::isEnabled() && Database::columnExists('users', 'company_id')) {
            $where[] = '(role = \'super_admin\' OR company_id = ?)';
            $params[] = $companyId;
        }

        return Database::fetchAll(
            'SELECT id, ' . $nameExpr . ', email
             FROM users
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY name ASC',
            $params
        );
    }

    public static function notifyCaseEvent(int $caseId, string $type, string $title, string $message, string $link = ''): void
    {
        $case = self::getCaseById($caseId);
        if (!$case) {
            return;
        }

        $companyId = (int) ($case['company_id'] ?? 0);
        $userIds = [];

        if (!empty($case['client_user_id'])) {
            $userIds[] = (int) $case['client_user_id'];
        }

        if (!empty($case['assigned_admin_id'])) {
            $userIds[] = (int) $case['assigned_admin_id'];
        }

        foreach (TenantService::staffNotifierUserIds($companyId, RoleAccess::PERMISSION_NOTIFICATIONS) as $staffId) {
            $userIds[] = $staffId;
        }

        $userIds = array_unique($userIds);
        $resolvedLink = $link ? url($link) : null;

        foreach ($userIds as $userId) {
            createNotification(
                $userId,
                $title,
                $message,
                $type,
                $resolvedLink,
                $companyId > 0 ? $companyId : null
            );
        }
    }

    public static function regenerateInvoiceHtml(int $caseId, int $invoiceId): void
    {
        $inv = Database::fetch('SELECT id, case_id FROM invoices WHERE id = ?', [$invoiceId]);
        if (!$inv || (int) $inv['case_id'] !== $caseId) {
            throw new RuntimeException('Invoice not found for this case.');
        }

        self::saveHtmlDocument($caseId, 'invoice', $invoiceId);
    }

    public static function createInvoicePaymentLink(int $caseId, int $invoiceId): string
    {
        $invoice = Database::fetch(
            'SELECT i.*, c.case_number
             FROM invoices i
             LEFT JOIN cases c ON c.id = i.case_id
             WHERE i.id = ? AND i.case_id = ?',
            [$invoiceId, $caseId]
        );
        if (!$invoice) {
            throw new RuntimeException('Invoice not found for this case.');
        }

        return PaymentGatewayService::assignPaymentLink($invoiceId);
    }

    public static function regenerateQuotationHtml(int $caseId, int $quotationId): void
    {
        $quote = Database::fetch('SELECT * FROM quotations WHERE id = ?', [$quotationId]);
        if (!$quote || (int) $quote['case_id'] !== $caseId) {
            throw new RuntimeException('Quotation not found for this case.');
        }

        if (FinancialDocumentRenderer::isLineItemsJsonPayload((string) ($quote['notes'] ?? ''))) {
            Database::query('UPDATE quotations SET notes = NULL WHERE id = ?', [$quotationId]);
        }

        self::saveHtmlDocument($caseId, 'quotation', $quotationId);
    }

    private static function saveHtmlDocument(int $caseId, string $kind, int $docId): void
    {
        $case = self::getCaseById($caseId);
        if (!$case) {
            return;
        }

        $html = match ($kind) {
            'quotation' => DocumentTemplate::quotation($case, Database::fetch('SELECT * FROM quotations WHERE id = ?', [$docId]) ?: []),
            'proposal'  => DocumentTemplate::proposal($case, Database::fetch('SELECT * FROM proposals WHERE id = ?', [$docId]) ?: []),
            'invoice'   => DocumentTemplate::invoice($case, Database::fetch('SELECT * FROM invoices WHERE id = ?', [$docId]) ?: []),
            default     => '',
        };

        if ($html === '') {
            return;
        }

        $config = require __DIR__ . '/../config/config.php';
        $dir    = rtrim($config['upload']['path'], '/\\') . '/cases/' . $caseId . '/generated';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = $kind . '_' . $docId . '.html';
        file_put_contents($dir . '/' . $filename, $html);

        $relative = 'cases/' . $caseId . '/generated/' . $filename;

        try {
            if ($kind === 'quotation') {
                Database::query('UPDATE quotations SET pdf_path = ? WHERE id = ?', [$relative, $docId]);
            } elseif ($kind === 'proposal') {
                Database::query('UPDATE proposals SET pdf_path = ? WHERE id = ?', [$relative, $docId]);
            } elseif ($kind === 'invoice') {
                Database::query('UPDATE invoices SET pdf_path = ? WHERE id = ?', [$relative, $docId]);
            }
        } catch (Throwable $e) {
            // pdf_path optional
        }
    }

    public static function documentPath(string $relativePath): string
    {
        $config = require __DIR__ . '/../config/config.php';
        return rtrim($config['upload']['path'], '/\\') . '/' . ltrim($relativePath, '/\\');
    }

    public static function deleteCase(int $caseId): void
    {
        $case = self::getCaseById($caseId);
        if (!$case) {
            throw new RuntimeException('Case not found.');
        }

        foreach (self::getDocuments($caseId) as $doc) {
            $path = $doc['file_path'] ?? $doc['stored_path'] ?? null;
            if ($path && is_file(self::documentPath($path))) {
                @unlink(self::documentPath($path));
            }
        }

        Database::query('DELETE FROM cases WHERE id = ?', [$caseId]);
    }

    public static function deleteDocument(int $documentId, int $caseId): void
    {
        $doc = Database::fetch('SELECT * FROM documents WHERE id = ? AND case_id = ?', [$documentId, $caseId]);
        if (!$doc) {
            throw new RuntimeException('Document not found.');
        }

        $path = $doc['file_path'] ?? null;
        if ($path && is_file(self::documentPath($path))) {
            @unlink(self::documentPath($path));
        }

        Database::query('DELETE FROM documents WHERE id = ?', [$documentId]);
    }
}
