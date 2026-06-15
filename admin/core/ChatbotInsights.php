<?php

declare(strict_types=1);

/**
 * @return list<array{icon: string, title: string, message: string, prompt?: string}>
 */
function chatbotGetProactiveInsights(): array
{
    if (!Auth::check() || !Auth::can(RoleAccess::PERMISSION_CHATBOT)) {
        return [];
    }

    syncOverdueInvoices();
    $insights = [];

    $statusCol = invoiceStatusColumn();
    $overdueWhere = ["i.{$statusCol} = 'overdue'"];
    $overdueParams = [];
    TenantService::appendClientScope($overdueWhere, $overdueParams, 'cl');
    $overdueCount = (int) (Database::fetch(
        'SELECT COUNT(*) AS c FROM invoices i JOIN clients cl ON cl.id = i.client_id WHERE ' . implode(' AND ', $overdueWhere),
        $overdueParams
    )['c'] ?? 0);

    if ($overdueCount > 0 && Auth::can(RoleAccess::PERMISSION_PAYMENTS)) {
        $insights[] = [
            'icon'    => 'bi-exclamation-circle',
            'title'   => 'Overdue invoices',
            'message' => $overdueCount . ' invoice(s) are overdue.',
            'prompt'  => 'List overdue invoices',
        ];
    }

    $startSql = appointmentStartSql('a');
    $todayWhere = [
        "DATE({$startSql}) = CURDATE()",
        "a.status IN ('scheduled', 'confirmed', 'rescheduled')",
    ];
    $todayParams = [];
    TenantService::appendClientScope($todayWhere, $todayParams, 'cl');
    $todayCount = (int) (Database::fetch(
        "SELECT COUNT(*) AS c FROM appointments a
         JOIN clients cl ON cl.id = a.client_id
         WHERE " . implode(' AND ', $todayWhere),
        $todayParams
    )['c'] ?? 0);

    if ($todayCount > 0 && Auth::can(RoleAccess::PERMISSION_APPOINTMENTS)) {
        $insights[] = [
            'icon'    => 'bi-calendar-event',
            'title'   => "Today's calendar",
            'message' => $todayCount . ' appointment(s) scheduled today.',
            'prompt'  => 'Appointments today',
        ];
    }

    if (Auth::can(RoleAccess::PERMISSION_CASES) && !Auth::restrictsToAssignedCases()) {
        $unassignedWhere = ['(cs.assigned_admin_id IS NULL OR cs.assigned_admin_id = 0)', "cs.status IN ('pending', 'in_progress', 'waiting_for_client')"];
        $unassignedParams = [];
        chatbotAppendCaseScope($unassignedWhere, $unassignedParams, 'cs', 'cl');
        $unassigned = (int) (Database::fetch(
            'SELECT COUNT(*) AS c FROM cases cs JOIN clients cl ON cl.id = cs.client_id WHERE ' . implode(' AND ', $unassignedWhere),
            $unassignedParams
        )['c'] ?? 0);

        if ($unassigned > 0) {
            $insights[] = [
                'icon'    => 'bi-person-dash',
                'title'   => 'Unassigned cases',
                'message' => $unassigned . ' active case(s) have no assigned staff.',
                'prompt'  => 'List active cases',
            ];
        }
    }

    $userId = Auth::id();
    if ($userId && Auth::can(RoleAccess::PERMISSION_NOTIFICATIONS)) {
        $unread = getUnreadNotificationCount($userId);
        if ($unread > 0) {
            $insights[] = [
                'icon'    => 'bi-bell',
                'title'   => 'Unread alerts',
                'message' => $unread . ' unread notification(s).',
                'prompt'  => 'Show unread notifications',
            ];
        }
    }

    if (Auth::can(RoleAccess::PERMISSION_INSIGHTS) && Auth::can(RoleAccess::PERMISSION_PAYMENTS)) {
        $stats = getDashboardStats();
        $outstanding = (float) ($stats['outstanding_balance'] ?? 0);
        if ($outstanding > 0) {
            $insights[] = [
                'icon'    => 'bi-currency-dollar',
                'title'   => 'Outstanding balance',
                'message' => formatCurrency($outstanding) . ' in unpaid invoices.',
                'prompt'  => 'Show unpaid invoices summary',
            ];
        }
    }

    return array_slice($insights, 0, 4);
}

function chatbotFormatInsightsMessage(array $insights): string
{
    if ($insights === []) {
        return '';
    }

    $lines = ['**Heads up:**', ''];
    foreach ($insights as $item) {
        $lines[] = '• **' . ($item['title'] ?? 'Alert') . '** — ' . ($item['message'] ?? '');
    }
    $lines[] = '';
    $lines[] = '_Tap a quick prompt or ask for details (e.g. “list overdue invoices”)._';

    return implode("\n", $lines);
}
