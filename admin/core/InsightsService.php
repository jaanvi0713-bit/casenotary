<?php
declare(strict_types=1);

/**
 * Business Insights — analytics, alerts, forecasts, and NLQ over live tenant data.
 */
class InsightsService
{
    public static function getHubData(array $stats): array
    {
        return [
            'health_score'       => self::healthScore($stats),
            'alerts'             => self::getAlerts($stats),
            'recommendations'    => self::getRecommendations($stats),
            'prediction_suite'   => self::getPredictionSuite($stats),
            'revenue_chart'      => getRevenueChartData(),
            'invoice_chart'      => getInvoiceChartData(),
            'client_cohorts'     => self::getClientCohorts(),
            'case_funnel'        => self::getCaseFunnel(),
            'appointment_stats'  => self::getAppointmentBreakdown(),
            'top_clients'        => self::getTopClientsByRevenue(5),
            'client_segments'    => self::getClientSegments($stats),
            'payment_methods'    => self::getPaymentMethodBreakdown(),
            'operational'        => self::getOperationalMetrics(),
            'forecast'           => self::getRevenueForecast(),
            'data_sources'       => self::getDataSourceStatus(),
            'nlq_catalog'        => self::getNlqCatalog($stats),
        ];
    }

    public static function healthScore(array $stats): int
    {
        $score = 70;
        $collection = (float) ($stats['collection_rate'] ?? 0);
        $score += min(15, (int) round($collection / 10));
        if ((int) ($stats['overdue_invoices'] ?? 0) > 0) {
            $score -= min(20, (int) $stats['overdue_invoices'] * 4);
        }
        if ((int) ($stats['urgent_cases'] ?? 0) > 0) {
            $score -= min(15, (int) $stats['urgent_cases'] * 3);
        }
        if ((float) ($stats['monthly_revenue'] ?? 0) > 0) {
            $score += 5;
        }
        return max(0, min(100, $score));
    }

    public static function getAlerts(array $stats): array
    {
        $alerts = [];
        $paymentStatus = paymentStatusColumn();
        $invoiceStatus = invoiceStatusColumn();

        if ((int) ($stats['overdue_invoices'] ?? 0) > 0) {
            $alerts[] = [
                'type'    => 'danger',
                'icon'    => 'bi-exclamation-triangle-fill',
                'title'   => 'Overdue invoices',
                'message' => $stats['overdue_invoices'] . ' invoice(s) past due — follow up to protect cash flow.',
                'link'    => url('pages/payments.php?inv_status=overdue'),
            ];
        }

        if ((int) ($stats['urgent_cases'] ?? 0) > 0) {
            $alerts[] = [
                'type'    => 'warning',
                'icon'    => 'bi-fire',
                'title'   => 'High-priority cases',
                'message' => $stats['urgent_cases'] . ' urgent/high case(s) need attention.',
                'link'    => url('pages/cases.php?priority=urgent'),
            ];
        }

        if ((float) ($stats['collection_rate'] ?? 100) < 60 && ((int) ($stats['paid_invoices'] ?? 0) + (int) ($stats['pending_invoices'] ?? 0)) > 0) {
            $alerts[] = [
                'type'    => 'warning',
                'icon'    => 'bi-cash-coin',
                'title'   => 'Low collection rate',
                'message' => 'Only ' . number_format($stats['collection_rate'], 0) . '% of issued invoices are paid. Review billing workflow.',
                'link'    => url('pages/payments.php'),
            ];
        }

        $revChange = self::monthOverMonthRevenueChange();
        if ($revChange !== null && $revChange < -15) {
            $alerts[] = [
                'type'    => 'danger',
                'icon'    => 'bi-graph-down-arrow',
                'title'   => 'Revenue anomaly',
                'message' => 'Revenue down ' . abs((int) round($revChange)) . '% vs last month — investigate pipeline and collections.',
                'link'    => url('pages/insights.php'),
            ];
        } elseif ($revChange !== null && $revChange > 25) {
            $alerts[] = [
                'type'    => 'success',
                'icon'    => 'bi-graph-up-arrow',
                'title'   => 'Revenue spike',
                'message' => 'Revenue up ' . (int) round($revChange) . '% vs last month — strong performance.',
                'link'    => null,
            ];
        }

        if (Database::columnExists('invoices', 'payment_status') || Database::columnExists('invoices', 'status')) {
            $failedWhere = ['i.' . $invoiceStatus . " = 'failed'"];
            $failedParams = [];
            TenantService::appendClientScope($failedWhere, $failedParams, 'cl');
            $failedCount = (int) (Database::fetch(
                'SELECT COUNT(*) AS c FROM invoices i JOIN clients cl ON cl.id = i.client_id WHERE ' . implode(' AND ', $failedWhere),
                $failedParams
            )['c'] ?? 0);
            if ($failedCount > 0) {
                $alerts[] = [
                    'type'    => 'warning',
                    'icon'    => 'bi-credit-card-2-front',
                    'title'   => 'Failed online payments',
                    'message' => $failedCount . ' invoice(s) with failed gateway payments — clients may retry.',
                    'link'    => url('pages/payments.php?inv_status=failed'),
                ];
            }
        }

        $peakDay = self::detectPaymentSpike();
        if ($peakDay) {
            $alerts[] = [
                'type'    => 'info',
                'icon'    => 'bi-lightning-charge',
                'title'   => 'Payment activity spike',
                'message' => 'Unusually high payments on ' . $peakDay['label'] . ' (' . formatCurrency($peakDay['amount']) . ').',
                'link'    => null,
            ];
        }

        if ($alerts === []) {
            $alerts[] = [
                'type'    => 'success',
                'icon'    => 'bi-check-circle-fill',
                'title'   => 'All clear',
                'message' => 'No critical anomalies detected. Metrics are within normal ranges.',
                'link'    => null,
            ];
        }

        return $alerts;
    }

    public static function getRecommendations(array $stats): array
    {
        $items = [];

        if ((int) ($stats['pending_invoices'] ?? 0) > 2) {
            $items[] = [
                'priority' => 'high',
                'action'   => 'Send payment reminders',
                'detail'   => $stats['pending_invoices'] . ' pending invoices — enable payment links on new invoices to speed collection.',
                'link'     => url('pages/payments.php'),
            ];
        }

        if ((int) ($stats['cases_deadline_soon'] ?? 0) > 0) {
            $items[] = [
                'priority' => 'high',
                'action'   => 'Review deadlines this week',
                'detail'   => $stats['cases_deadline_soon'] . ' case(s) due within 7 days.',
                'link'     => url('pages/cases.php'),
            ];
        }

        if ((int) ($stats['new_clients_month'] ?? 0) > 0 && (int) ($stats['new_cases_month'] ?? 0) < (int) $stats['new_clients_month']) {
            $items[] = [
                'priority' => 'medium',
                'action'   => 'Convert new clients to cases',
                'detail'   => 'More new clients than new cases this month — open matters to capture revenue.',
                'link'     => url('pages/case-form.php'),
            ];
        }

        if ((float) ($stats['collection_rate'] ?? 0) >= 80) {
            $items[] = [
                'priority' => 'low',
                'action'   => 'Maintain collection momentum',
                'detail'   => 'Collection rate is healthy. Consider upselling additional services to top clients.',
                'link'     => url('pages/clients.php'),
            ];
        }

        if ($items === []) {
            $items[] = [
                'priority' => 'low',
                'action'   => 'Schedule weekly review',
                'detail'   => 'Export insights report and review KPIs with your team every Monday.',
                'link'     => null,
            ];
        }

        return $items;
    }

    public static function getClientCohorts(): array
    {
        $where = ['cl.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)'];
        $params = [];
        TenantService::appendClientScope($where, $params, 'cl');

        $rows = Database::fetchAll(
            "SELECT DATE_FORMAT(cl.created_at, '%Y-%m') AS ym,
                    DATE_FORMAT(cl.created_at, '%b %Y') AS label,
                    COUNT(*) AS c
             FROM clients cl
             WHERE " . implode(' AND ', $where) . "
             GROUP BY ym, label
             ORDER BY ym ASC",
            $params
        );

        $labels = [];
        $data   = [];
        for ($i = 5; $i >= 0; $i--) {
            $ym = date('Y-m', strtotime("-{$i} months"));
            $labels[] = date('M Y', strtotime("-{$i} months"));
            $data[] = 0;
            foreach ($rows as $row) {
                if ($row['ym'] === $ym) {
                    $data[count($data) - 1] = (int) $row['c'];
                }
            }
        }

        return ['labels' => $labels, 'data' => $data];
    }

    public static function getCaseFunnel(): array
    {
        $breakdown = getCaseStatusBreakdown();
        $total = max(1, array_sum($breakdown));

        return [
            ['stage' => 'Pending', 'count' => $breakdown['pending'] ?? 0, 'pct' => round(($breakdown['pending'] ?? 0) / $total * 100)],
            ['stage' => 'In Progress', 'count' => $breakdown['in_progress'] ?? 0, 'pct' => round(($breakdown['in_progress'] ?? 0) / $total * 100)],
            ['stage' => 'Waiting for Client', 'count' => $breakdown['waiting_for_client'] ?? 0, 'pct' => round(($breakdown['waiting_for_client'] ?? 0) / $total * 100)],
            ['stage' => 'Completed', 'count' => ($breakdown['completed'] ?? 0) + ($breakdown['closed'] ?? 0), 'pct' => round((($breakdown['completed'] ?? 0) + ($breakdown['closed'] ?? 0)) / $total * 100)],
        ];
    }

    public static function getAppointmentBreakdown(): array
    {
        $where = ['1=1'];
        $params = [];
        TenantService::appendClientScope($where, $params, 'cl');

        $rows = Database::fetchAll(
            "SELECT a.status, COUNT(*) AS c
             FROM appointments a
             JOIN clients cl ON cl.id = a.client_id
             WHERE " . implode(' AND ', $where) . "
             GROUP BY a.status",
            $params
        );

        $map = [];
        foreach ($rows as $row) {
            $map[$row['status']] = (int) $row['c'];
        }

        return $map;
    }

    public static function getTopClientsByRevenue(int $limit = 5): array
    {
        $paymentStatus = paymentStatusColumn();
        $where = ["p.{$paymentStatus} = 'completed'"];
        $params = [];
        TenantService::appendClientScope($where, $params, 'cl');

        return Database::fetchAll(
            "SELECT cl.id, cl.first_name, cl.last_name, cl.company_name,
                    COALESCE(SUM(p.amount), 0) AS revenue,
                    COUNT(DISTINCT i.id) AS invoice_count
             FROM payments p
             JOIN invoices i ON i.id = p.invoice_id
             JOIN clients cl ON cl.id = i.client_id
             WHERE " . implode(' AND ', $where) . "
             GROUP BY cl.id, cl.first_name, cl.last_name, cl.company_name
             ORDER BY revenue DESC
             LIMIT " . (int) $limit,
            $params
        );
    }

    public static function getClientSegments(array $stats): array
    {
        $total = (int) ($stats['total_clients'] ?? 0);
        $newMonth = (int) ($stats['new_clients_month'] ?? 0);

        $where = ['1=1'];
        $params = [];
        TenantService::appendClientScope($where, $params, 'cl');

        $withRecentCase = (int) (Database::fetch(
            "SELECT COUNT(DISTINCT cl.id) AS c FROM clients cl
             JOIN cases cs ON cs.client_id = cl.id
             WHERE " . implode(' AND ', $where) . " AND cs.updated_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)",
            $params
        )['c'] ?? 0);

        $dormant = max(0, $total - $withRecentCase - $newMonth);

        return [
            ['name' => 'Active (90d)', 'count' => $withRecentCase, 'color' => '#10b981', 'desc' => 'Clients with case activity in last 90 days'],
            ['name' => 'New this month', 'count' => $newMonth, 'color' => '#3b82f6', 'desc' => 'Recently registered clients'],
            ['name' => 'Dormant', 'count' => $dormant, 'color' => '#64748b', 'desc' => 'No recent case activity — re-engagement opportunity'],
        ];
    }

    public static function getPaymentMethodBreakdown(): array
    {
        $paymentStatus = paymentStatusColumn();
        $where = ["p.{$paymentStatus} = 'completed'"];
        $params = [];
        TenantService::appendClientScope($where, $params, 'cl');

        $rows = Database::fetchAll(
            "SELECT p.payment_method, COUNT(*) AS c, COALESCE(SUM(p.amount), 0) AS total
             FROM payments p
             JOIN invoices i ON i.id = p.invoice_id
             JOIN clients cl ON cl.id = i.client_id
             WHERE " . implode(' AND ', $where) . "
             GROUP BY p.payment_method
             ORDER BY total DESC",
            $params
        );

        return $rows;
    }

    public static function getOperationalMetrics(): array
    {
        $where = ["cs.status IN ('completed', 'closed')", 'cs.updated_at IS NOT NULL'];
        $params = [];
        if (TenantService::isEnabled()) {
            $where[] = 'cs.company_id = ?';
            $params[] = TenantService::id();
        }

        $avgDays = Database::fetch(
            "SELECT AVG(DATEDIFF(cs.updated_at, cs.created_at)) AS avg_days
             FROM cases cs WHERE " . implode(' AND ', $where),
            $params
        )['avg_days'] ?? null;

        $totalCases = (int) (Database::fetch(
            TenantService::isEnabled()
                ? 'SELECT COUNT(*) AS c FROM cases WHERE company_id = ?'
                : 'SELECT COUNT(*) AS c FROM cases',
            TenantService::isEnabled() ? [TenantService::id()] : []
        )['c'] ?? 0);

        $completed = (int) (Database::fetch(
            TenantService::isEnabled()
                ? "SELECT COUNT(*) AS c FROM cases WHERE company_id = ? AND status IN ('completed', 'closed')"
                : "SELECT COUNT(*) AS c FROM cases WHERE status IN ('completed', 'closed')",
            TenantService::isEnabled() ? [TenantService::id()] : []
        )['c'] ?? 0);

        $completionRate = $totalCases > 0 ? round($completed / $totalCases * 100, 1) : 0;

        $staffRows = [];
        if (Database::columnExists('cases', 'assigned_admin_id')) {
            $staffWhere = ['cs.assigned_admin_id IS NOT NULL'];
            $staffParams = [];
            if (TenantService::isEnabled()) {
                $staffWhere[] = 'cs.company_id = ?';
                $staffParams[] = TenantService::id();
            }
            $staffRows = Database::fetchAll(
                "SELECT u.first_name, u.last_name, COUNT(*) AS case_count,
                        SUM(CASE WHEN cs.status IN ('completed','closed') THEN 1 ELSE 0 END) AS completed
                 FROM cases cs
                 JOIN users u ON u.id = cs.assigned_admin_id
                 WHERE " . implode(' AND ', $staffWhere) . "
                 GROUP BY u.id, u.first_name, u.last_name
                 ORDER BY case_count DESC
                 LIMIT 5",
                $staffParams
            );
        }

        return [
            'avg_case_days'     => $avgDays !== null ? round((float) $avgDays, 1) : null,
            'completion_rate'   => $completionRate,
            'total_cases'       => $totalCases,
            'staff_workload'    => $staffRows,
        ];
    }

    public static function getRevenueForecast(): array
    {
        $chart = getRevenueChartData();
        $data = $chart['data'];
        $nonZero = array_filter($data, static fn ($v) => (float) $v > 0);
        $avg = count($nonZero) > 0 ? array_sum($data) / count($nonZero) : 0;
        $last = (float) ($data[count($data) - 1] ?? 0);
        $trend = count($data) >= 2 ? $last - (float) ($data[count($data) - 2] ?? 0) : 0;

        $nextMonth = max(0, $last + ($trend * 0.5));
        if ($avg > 0 && $nextMonth === 0.0) {
            $nextMonth = $avg;
        }

        return [
            'next_month_estimate' => round($nextMonth, 2),
            'trailing_avg'        => round($avg, 2),
            'trend_direction'     => $trend > 0 ? 'up' : ($trend < 0 ? 'down' : 'flat'),
            'confidence'          => count($nonZero) >= 3 ? 'medium' : 'low',
        ];
    }

    public static function getPredictionSuite(array $stats): array
    {
        $forecast = self::getRevenueForecast();
        $base = (float) ($forecast['next_month_estimate'] ?? 0);
        $collectionRate = (float) ($stats['collection_rate'] ?? 0);
        $overdue = (int) ($stats['overdue_invoices'] ?? 0);
        $urgent = (int) ($stats['urgent_cases'] ?? 0);

        $bestMultiplier = 1.0 + max(0.04, min(0.18, (100 - $collectionRate) * 0.0015 + 0.05));
        $worstMultiplier = 1.0 - max(0.06, min(0.24, $overdue * 0.03 + $urgent * 0.02));
        $best = round($base * $bestMultiplier, 2);
        $worst = round(max(0, $base * $worstMultiplier), 2);

        $risks = [];
        if ($overdue > 0) {
            $risks[] = [
                'title' => 'Cash-flow drag from overdue invoices',
                'impact' => 'high',
                'probability' => min(95, 35 + $overdue * 10),
                'mitigation' => 'Send reminders and prioritize high-value overdue invoices this week.',
            ];
        }
        if ($urgent > 0) {
            $risks[] = [
                'title' => 'Capacity bottleneck from urgent cases',
                'impact' => 'medium',
                'probability' => min(90, 25 + $urgent * 8),
                'mitigation' => 'Rebalance assignments and fast-track critical deadlines.',
            ];
        }
        if ((float) ($stats['collection_rate'] ?? 0) < 70) {
            $risks[] = [
                'title' => 'Collections below healthy threshold',
                'impact' => 'high',
                'probability' => 70,
                'mitigation' => 'Generate payment links by default and automate follow-ups.',
            ];
        }
        if ($risks === []) {
            $risks[] = [
                'title' => 'No major downside signal detected',
                'impact' => 'low',
                'probability' => 20,
                'mitigation' => 'Continue weekly KPI review to catch early changes.',
            ];
        }

        $drivers = [
            ['label' => 'Collection rate', 'value' => number_format($collectionRate, 1) . '%', 'direction' => $collectionRate >= 75 ? 'up' : 'down'],
            ['label' => 'Overdue invoices', 'value' => (string) $overdue, 'direction' => $overdue > 0 ? 'down' : 'up'],
            ['label' => 'Urgent workload', 'value' => (string) $urgent, 'direction' => $urgent > 2 ? 'down' : 'up'],
            ['label' => 'Monthly revenue', 'value' => formatCurrency((float) ($stats['monthly_revenue'] ?? 0)), 'direction' => ((float) ($stats['monthly_revenue'] ?? 0) > 0) ? 'up' : 'flat'],
        ];

        return [
            'model' => 'Adaptive trend + risk-weighted scenario model',
            'confidence' => (string) ($forecast['confidence'] ?? 'low'),
            'base' => $base,
            'best' => $best,
            'worst' => $worst,
            'trend_direction' => (string) ($forecast['trend_direction'] ?? 'flat'),
            'drivers' => $drivers,
            'risks' => $risks,
            'next_actions' => self::predictionActions($stats),
        ];
    }

    private static function predictionActions(array $stats): array
    {
        $actions = [];
        if ((int) ($stats['overdue_invoices'] ?? 0) > 0) {
            $actions[] = 'Target top overdue invoices first and request immediate settlement.';
        }
        if ((float) ($stats['collection_rate'] ?? 0) < 75) {
            $actions[] = 'Enable payment links on every new invoice and send reminders on day 3 and day 7.';
        }
        if ((int) ($stats['cases_deadline_soon'] ?? 0) > 0) {
            $actions[] = 'Allocate a deadline sprint block to protect SLA and avoid spillover delays.';
        }
        if ($actions === []) {
            $actions[] = 'Maintain current cadence; no urgent corrective action required this week.';
        }

        return $actions;
    }

    /** @return array<string, mixed> */
    public static function getLivePredictionPayload(array $stats): array
    {
        $suite = self::getPredictionSuite($stats);
        $base = (float) ($suite['base'] ?? 0);
        $daily = $base > 0 ? $base / 30 : (float) ($stats['weekly_revenue'] ?? 0) / 7;

        $labels = [];
        $baseSeries = [];
        $bestSeries = [];
        $worstSeries = [];
        $cumulative = 0.0;

        for ($i = 0; $i < 30; $i++) {
            $labels[] = date('M j', strtotime("+{$i} days"));
            $dayFactor = 1 + (0.06 * sin($i / 2.8)) + ($i * 0.004);
            $cumulative += max(0, $daily * $dayFactor);
            $baseSeries[] = round($cumulative, 2);
            $bestSeries[] = round($cumulative * (1 + 0.14 * ($i / 29)), 2);
            $worstSeries[] = round(max(0, $cumulative * (1 - 0.11 * ($i / 29))), 2);
        }

        $mom = self::monthOverMonthRevenueChange();

        return array_merge($suite, [
            'updated_at'      => date('c'),
            'revenue_mom_pct' => $mom,
            'forecast'        => [
                'labels' => $labels,
                'base'   => $baseSeries,
                'best'   => $bestSeries,
                'worst'  => $worstSeries,
            ],
            'live_metrics' => [
                'monthly_revenue'   => (float) ($stats['monthly_revenue'] ?? 0),
                'weekly_revenue'    => (float) ($stats['weekly_revenue'] ?? 0),
                'active_cases'      => (int) ($stats['active_cases'] ?? 0),
                'outstanding'       => (float) ($stats['outstanding_balance'] ?? 0),
                'collection_rate'   => (float) ($stats['collection_rate'] ?? 0),
                'overdue_invoices'  => (int) ($stats['overdue_invoices'] ?? 0),
            ],
            'signals' => self::buildLiveSignals($stats, $mom),
        ]);
    }

    /** @return list<array{time: string, text: string, type: string}> */
    private static function buildLiveSignals(array $stats, ?float $mom): array
    {
        $signals = [];
        $signals[] = [
            'time' => date('H:i:s'),
            'text' => 'Synced ' . number_format((int) $stats['active_cases']) . ' active cases and '
                . formatCurrency((float) $stats['monthly_revenue']) . ' month-to-date revenue.',
            'type' => 'info',
        ];

        if ($mom !== null) {
            $signals[] = [
                'time' => date('H:i:s'),
                'text' => 'Revenue is ' . ($mom >= 0 ? 'up' : 'down') . ' '
                    . abs((int) round($mom)) . '% vs last month.',
                'type' => $mom >= 0 ? 'success' : 'warning',
            ];
        }

        if ((int) ($stats['overdue_invoices'] ?? 0) > 0) {
            $signals[] = [
                'time' => date('H:i:s'),
                'text' => (int) $stats['overdue_invoices'] . ' overdue invoice(s) detected — collections risk elevated.',
                'type' => 'danger',
            ];
        }

        if ((int) ($stats['urgent_cases'] ?? 0) > 0) {
            $signals[] = [
                'time' => date('H:i:s'),
                'text' => (int) $stats['urgent_cases'] . ' urgent case(s) may slow forecast conversion.',
                'type' => 'warning',
            ];
        }

        return $signals;
    }

    public static function getDataSourceStatus(): array
    {
        return [
            ['name' => 'Cases & CRM', 'status' => 'live', 'icon' => 'bi-folder2-open'],
            ['name' => 'Invoices & Payments', 'status' => 'live', 'icon' => 'bi-receipt'],
            ['name' => 'Appointments', 'status' => 'live', 'icon' => 'bi-calendar-event'],
            ['name' => 'Clients', 'status' => 'live', 'icon' => 'bi-people'],
            ['name' => 'External ads / web', 'status' => 'connect', 'icon' => 'bi-plug'],
            ['name' => 'ERP / accounting', 'status' => 'connect', 'icon' => 'bi-building'],
        ];
    }

    public static function getNlqCatalog(array $stats): array
    {
        $topServices = getTopServiceTypes(1);
        $topSvc = $topServices[0]['service_type'] ?? 'N/A';

        return [
            ['q' => 'revenue this month', 'a' => 'Monthly revenue is ' . formatCurrency((float) $stats['monthly_revenue']) . '.', 'icon' => 'bi-cash-stack'],
            ['q' => 'outstanding balance', 'a' => 'Outstanding balance is ' . formatCurrency((float) $stats['outstanding_balance']) . ' across pending and overdue invoices.', 'icon' => 'bi-wallet2'],
            ['q' => 'active cases', 'a' => 'You have ' . number_format((int) $stats['active_cases']) . ' active cases right now.', 'icon' => 'bi-folder2-open'],
            ['q' => 'collection rate', 'a' => 'Collection rate is ' . number_format((float) $stats['collection_rate'], 1) . '% (' . $stats['paid_invoices'] . ' paid invoices).', 'icon' => 'bi-percent'],
            ['q' => 'top service', 'a' => 'Top service type by volume: ' . $topSvc . '.', 'icon' => 'bi-star'],
            ['q' => 'new clients', 'a' => number_format((int) $stats['new_clients_month']) . ' new clients joined in ' . date('F Y') . '.', 'icon' => 'bi-person-plus'],
            ['q' => 'overdue invoices', 'a' => (int) $stats['overdue_invoices'] . ' invoice(s) are overdue.', 'icon' => 'bi-clock-history'],
            ['q' => 'appointments', 'a' => number_format((int) $stats['upcoming_appointments']) . ' upcoming appointments scheduled.', 'icon' => 'bi-calendar-check'],
        ];
    }

    public static function answerQuery(string $query, array $stats): ?array
    {
        $q = strtolower(trim($query));
        if ($q === '') {
            return null;
        }

        foreach (self::getNlqCatalog($stats) as $item) {
            $keywords = explode(' ', $item['q']);
            $match = true;
            foreach ($keywords as $kw) {
                if ($kw !== '' && !str_contains($q, $kw)) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                return $item;
            }
        }

        if (str_contains($q, 'forecast') || str_contains($q, 'predict')) {
            $f = self::getRevenueForecast();
            return [
                'q' => $query,
                'a' => 'Forecasted next-month revenue: ' . formatCurrency($f['next_month_estimate']) . ' (confidence: ' . $f['confidence'] . ').',
                'icon' => 'bi-graph-up',
            ];
        }

        return [
            'q' => $query,
            'a' => 'Try asking about revenue, outstanding balance, active cases, collection rate, or overdue invoices.',
            'icon' => 'bi-chat-dots',
        ];
    }

    private static function monthOverMonthRevenueChange(): ?float
    {
        $paymentStatus = paymentStatusColumn();
        $where = ["p.{$paymentStatus} = 'completed'"];
        $params = [];
        TenantService::appendClientScope($where, $params, 'cl');

        $thisMonth = (float) (Database::fetch(
            "SELECT COALESCE(SUM(p.amount), 0) AS t FROM payments p
             JOIN invoices i ON i.id = p.invoice_id JOIN clients cl ON cl.id = i.client_id
             WHERE " . implode(' AND ', $where) . " AND MONTH(p.paid_at)=MONTH(NOW()) AND YEAR(p.paid_at)=YEAR(NOW())",
            $params
        )['t'] ?? 0);

        $lastMonth = (float) (Database::fetch(
            "SELECT COALESCE(SUM(p.amount), 0) AS t FROM payments p
             JOIN invoices i ON i.id = p.invoice_id JOIN clients cl ON cl.id = i.client_id
             WHERE " . implode(' AND ', $where) . " AND MONTH(p.paid_at)=MONTH(DATE_SUB(NOW(),INTERVAL 1 MONTH)) AND YEAR(p.paid_at)=YEAR(DATE_SUB(NOW(),INTERVAL 1 MONTH))",
            $params
        )['t'] ?? 0);

        if ($lastMonth <= 0) {
            return null;
        }

        return round(($thisMonth - $lastMonth) / $lastMonth * 100, 1);
    }

    private static function detectPaymentSpike(): ?array
    {
        $weekly = getWeeklyPaymentsChartData();
        $payments = $weekly['payments'];
        if (count($payments) < 3) {
            return null;
        }
        $avg = array_sum($payments) / count($payments);
        if ($avg <= 0) {
            return null;
        }
        $max = max($payments);
        $idx = array_search($max, $payments, true);
        if ($max > $avg * 2.5 && $idx !== false) {
            return ['label' => $weekly['labels'][$idx] ?? '', 'amount' => $max];
        }
        return null;
    }
}
