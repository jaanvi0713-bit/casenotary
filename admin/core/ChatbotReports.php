<?php

declare(strict_types=1);

function chatbotReplyForReports(string $message): ?string
{
    $normalized = strtolower(trim($message));

    if (!preg_match(
        '/\b(report|revenue by|revenue per|monthly revenue|cases closed|closed cases|quarter|'
        . 'export summary|business report|payment trend|income by)\b/',
        $normalized
    )) {
        return null;
    }

    if (preg_match('/\b(revenue|income|payments?)\b.*\b(month|monthly|by month)\b/', $normalized)
        || preg_match('/\bmonthly revenue\b/', $normalized)) {
        return chatbotReportRevenueByMonth();
    }

    if (preg_match('/\b(cases closed|closed cases|completed cases)\b/', $normalized)) {
        return chatbotReportCasesClosed();
    }

    if (preg_match('/\b(quarter|quarterly)\b/', $normalized)) {
        return chatbotReportQuarterlySummary();
    }

    return chatbotReportQuarterlySummary();
}

function chatbotReportRevenueByMonth(): string
{
    $paymentCol = paymentStatusColumn();
    $where = ["p.{$paymentCol} = 'completed'", 'p.paid_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)'];
    $params = [];
    TenantService::appendClientScope($where, $params, 'cl');

    $rows = Database::fetchAll(
        "SELECT DATE_FORMAT(p.paid_at, '%Y-%m') AS month_key,
                DATE_FORMAT(p.paid_at, '%b %Y') AS month_label,
                SUM(p.amount) AS total,
                COUNT(*) AS payment_count
         FROM payments p
         JOIN invoices i ON i.id = p.invoice_id
         JOIN clients cl ON cl.id = i.client_id
         WHERE " . implode(' AND ', $where) . "
         GROUP BY month_key, month_label
         ORDER BY month_key DESC
         LIMIT 6",
        $params
    );

    if ($rows === []) {
        return 'No completed payments in the last 6 months.';
    }

    $lines = ['**Revenue by month** (last 6 months):', ''];
    $grand = 0.0;

    foreach ($rows as $row) {
        $amount = (float) ($row['total'] ?? 0);
        $grand += $amount;
        $lines[] = '• **' . ($row['month_label'] ?? '') . '** — '
            . formatCurrency($amount) . ' (' . (int) ($row['payment_count'] ?? 0) . ' payments)';
    }

    $lines[] = '';
    $lines[] = '**Total:** ' . formatCurrency($grand);
    $lines[] = chatbotAdminLink('pages/payments.php', 'Open payments');

    $_SESSION['chatbot_last_topic'] = 'reports';

    return implode("\n", $lines);
}

function chatbotReportCasesClosed(): string
{
    $where = ["cs.status IN ('completed', 'closed')", 'cs.updated_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)'];
    $params = [];
    chatbotAppendCaseScope($where, $params, 'cs', 'cl');

    $rows = Database::fetchAll(
        "SELECT DATE_FORMAT(cs.updated_at, '%Y-%m') AS month_key,
                DATE_FORMAT(cs.updated_at, '%b %Y') AS month_label,
                COUNT(*) AS case_count
         FROM cases cs
         JOIN clients cl ON cl.id = cs.client_id
         WHERE " . implode(' AND ', $where) . "
         GROUP BY month_key, month_label
         ORDER BY month_key DESC",
        $params
    );

    $total = array_sum(array_map(static fn(array $r): int => (int) ($r['case_count'] ?? 0), $rows));

    if ($rows === []) {
        return 'No cases closed or completed in the last 3 months.';
    }

    $lines = ['**Cases closed / completed** (last 3 months):', ''];
    foreach ($rows as $row) {
        $lines[] = '• **' . ($row['month_label'] ?? '') . '** — ' . (int) ($row['case_count'] ?? 0) . ' cases';
    }

    $lines[] = '';
    $lines[] = '**Total:** ' . $total . ' cases';
    $lines[] = chatbotAdminLink('pages/cases.php', 'Open cases');

    $_SESSION['chatbot_last_topic'] = 'reports';

    return implode("\n", $lines);
}

function chatbotReportQuarterlySummary(): string
{
    $stats = getDashboardStats();
    $paymentCol = paymentStatusColumn();

    $where = ["p.{$paymentCol} = 'completed'", 'p.paid_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)'];
    $params = [];
    TenantService::appendClientScope($where, $params, 'cl');

    $quarterRevenue = (float) (Database::fetch(
        'SELECT COALESCE(SUM(p.amount), 0) AS total FROM payments p
         JOIN invoices i ON i.id = p.invoice_id
         JOIN clients cl ON cl.id = i.client_id
         WHERE ' . implode(' AND ', $where),
        $params
    )['total'] ?? 0);

    $newWhere = ['cs.created_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)'];
    $newParams = [];
    chatbotAppendCaseScope($newWhere, $newParams, 'cs', 'cl');
    $newCases = (int) (Database::fetch(
        'SELECT COUNT(*) AS c FROM cases cs JOIN clients cl ON cl.id = cs.client_id WHERE ' . implode(' AND ', $newWhere),
        $newParams
    )['c'] ?? 0);

    $closedWhere = ["cs.status IN ('completed', 'closed')", 'cs.updated_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)'];
    $closedParams = [];
    chatbotAppendCaseScope($closedWhere, $closedParams, 'cs', 'cl');
    $closedCases = (int) (Database::fetch(
        'SELECT COUNT(*) AS c FROM cases cs JOIN clients cl ON cl.id = cs.client_id WHERE ' . implode(' AND ', $closedWhere),
        $closedParams
    )['c'] ?? 0);

    $lines = [
        '**Quarterly business summary** (rolling 3 months)',
        '',
        '• **Clients (all time):** ' . $stats['total_clients'],
        '• **Active cases now:** ' . $stats['active_cases'],
        '• **New cases (3 mo):** ' . $newCases,
        '• **Closed / completed (3 mo):** ' . $closedCases,
        '• **Revenue (3 mo):** ' . formatCurrency($quarterRevenue),
        '• **Total revenue (all time):** ' . formatCurrency($stats['total_revenue']),
        '• **Pending invoices:** ' . $stats['pending_invoices'],
        '',
        '_Ask **revenue by month** or **cases closed** for a breakdown._',
    ];

    $_SESSION['chatbot_last_topic'] = 'reports';

    return implode("\n", $lines);
}
