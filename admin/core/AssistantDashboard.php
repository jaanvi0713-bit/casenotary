<?php

declare(strict_types=1);

class AssistantDashboard
{
    public static function handle(string $topic): array
    {
        return match ($topic) {
            'client_count' => self::clientCount(),
            'active_cases' => self::activeCases(),
            'total_revenue' => self::totalRevenue(),
            'upcoming_appointments' => self::upcomingAppointments(),
            'recent_payments' => self::recentPayments(),
            'overdue_invoices' => self::overdueInvoices(),
            'unread_notifications' => self::unreadNotifications(),
            'revenue_by_month' => self::revenueByMonth(),
            'overview' => self::overview(),
            default => self::overview(),
        };
    }

    /** @return array{content: string} */
    private static function clientCount(): array
    {
        $stats = getDashboardStats();
        $count = (int) ($stats['total_clients'] ?? 0);

        return [
            'content' => "**Registered clients:** {$count}\n\n"
                . assistantAdminLink('pages/clients.php', 'Open clients'),
        ];
    }

    /** @return array{content: string} */
    private static function activeCases(): array
    {
        $stats = getDashboardStats();
        $where = ["cs.status IN ('pending', 'in_progress', 'waiting_for_client')"];
        $params = [];
        appendCaseTenantScope($where, $params, 'cs', 'cl');
        appendAssignedCaseScope($where, $params, 'cs');
        $params[] = 15;

        $cases = Database::fetchAll(
            'SELECT cs.case_number, cs.title, cs.status, cl.first_name, cl.last_name, cl.company_name
             FROM cases cs
             JOIN clients cl ON cl.id = cs.client_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY cs.updated_at DESC
             LIMIT ?',
            $params
        );

        $lines = [
            '**Active cases:** ' . (int) ($stats['active_cases'] ?? count($cases)),
            '',
        ];

        if ($cases === []) {
            $lines[] = '_No active cases right now._';
        } else {
            foreach ($cases as $case) {
                $status = ucwords(str_replace('_', ' ', (string) ($case['status'] ?? '')));
                $client = clientFullName($case);
                $lines[] = '• **' . ($case['case_number'] ?? 'Case') . '** — '
                    . ($case['title'] ?? 'Untitled') . " (*{$status}*) — {$client}";
            }
        }

        $lines[] = '';
        $lines[] = assistantAdminLink('pages/cases.php', 'Open cases');

        return ['content' => implode("\n", $lines)];
    }

    /** @return array{content: string} */
    private static function totalRevenue(): array
    {
        $stats = getDashboardStats();

        return [
            'content' => "**Revenue summary**\n\n"
                . '• Total revenue: **' . formatCurrency((float) ($stats['total_revenue'] ?? 0)) . "**\n"
                . '• This month: **' . formatCurrency((float) ($stats['monthly_revenue'] ?? 0)) . "**\n"
                . '• Outstanding balance: **' . formatCurrency((float) ($stats['outstanding_balance'] ?? 0)) . "**\n\n"
                . assistantAdminLink('pages/payments.php', 'Open payments'),
        ];
    }

    /** @return array{content: string} */
    private static function upcomingAppointments(): array
    {
        $appointments = getUpcomingAppointments(10);
        $stats = getDashboardStats();

        if ($appointments === []) {
            return [
                'content' => 'No upcoming appointments scheduled. '
                    . assistantAdminLink('pages/appointments.php', 'Open calendar'),
            ];
        }

        $lines = [
            '**Upcoming appointments** (' . (int) ($stats['upcoming_appointments'] ?? count($appointments)) . ' scheduled)',
            '',
        ];

        foreach ($appointments as $row) {
            $start = appointmentStart($row) ?? $row['start_time'] ?? null;
            $when = $start ? formatDateTime($start) : 'TBD';
            $client = clientFullName($row);
            $lines[] = '• **' . ($row['title'] ?? 'Appointment') . '** — ' . $when . " — {$client}";
        }

        $lines[] = '';
        $lines[] = assistantAdminLink('pages/appointments.php', 'Open calendar');

        return ['content' => implode("\n", $lines)];
    }

    /** @return array{content: string} */
    private static function recentPayments(): array
    {
        $payments = array_slice(getAllPayments(), 0, 10);

        if ($payments === []) {
            return [
                'content' => 'No payments recorded yet. '
                    . assistantAdminLink('pages/payments.php', 'Open payments'),
            ];
        }

        $lines = ['**Recent payments**', ''];

        foreach ($payments as $payment) {
            $name = clientFullName($payment);
            $status = ucfirst(paymentStatusValue($payment));
            $lines[] = '• **' . formatCurrency((float) ($payment['amount'] ?? 0)) . '** — '
                . $name . ' — ' . ($payment['invoice_number'] ?? 'Invoice') . " (*{$status}*)";
        }

        $lines[] = '';
        $lines[] = assistantAdminLink('pages/payments.php', 'Open payments');

        return ['content' => implode("\n", $lines)];
    }

    /** @return array{content: string} */
    private static function overdueInvoices(): array
    {
        syncOverdueInvoices();
        $statusCol = invoiceStatusColumn();
        $where = ["i.{$statusCol} = 'overdue'"];
        $params = [];
        TenantService::appendClientScope($where, $params, 'cl');

        $rows = Database::fetchAll(
            'SELECT i.invoice_number, i.total, i.due_date, cl.first_name, cl.last_name, cl.company_name, cs.case_number
             FROM invoices i
             JOIN clients cl ON cl.id = i.client_id
             LEFT JOIN cases cs ON cs.id = i.case_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY i.due_date ASC
             LIMIT 15',
            $params
        );

        if ($rows === []) {
            return ['content' => '**No overdue invoices** — all caught up.'];
        }

        $lines = ['**Overdue invoices** (' . count($rows) . ')', ''];

        foreach ($rows as $row) {
            $client = clientFullName($row);
            $due = !empty($row['due_date']) ? formatDate($row['due_date']) : '—';
            $case = !empty($row['case_number']) ? ' — ' . $row['case_number'] : '';
            $lines[] = '• **' . ($row['invoice_number'] ?? 'Invoice') . '** — '
                . formatCurrency((float) ($row['total'] ?? 0)) . " — {$client}{$case} (due {$due})";
        }

        $lines[] = '';
        $lines[] = assistantAdminLink('pages/payments.php', 'Open payments');

        return ['content' => implode("\n", $lines)];
    }

    /** @return array{content: string} */
    private static function unreadNotifications(): array
    {
        $userId = Auth::id();
        if ($userId === null) {
            return ['content' => 'Please log in to view notifications.'];
        }

        $unread = getUnreadNotificationCount($userId);
        $rows = getRecentNotifications($userId, 8, true);

        if ($rows === []) {
            return [
                'content' => "**Unread notifications:** {$unread}\n\n_No unread alerts._ "
                    . assistantAdminLink('pages/notifications.php', 'Open notifications'),
            ];
        }

        $lines = ["**Unread notifications:** {$unread}", ''];

        foreach ($rows as $row) {
            $lines[] = '• **' . ($row['title'] ?? 'Alert') . '** — '
                . mb_strimwidth((string) ($row['message'] ?? ''), 0, 80, '…');
        }

        $lines[] = '';
        $lines[] = assistantAdminLink('pages/notifications.php', 'Open notifications');

        return ['content' => implode("\n", $lines)];
    }

    /** @return array{content: string} */
    private static function revenueByMonth(): array
    {
        $chart = getRevenueChartData();
        $labels = $chart['labels'] ?? [];
        $data = $chart['data'] ?? [];

        $lines = ['**Revenue by month** (last 6 months)', ''];

        foreach ($labels as $index => $label) {
            $amount = (float) ($data[$index] ?? 0);
            $lines[] = '• **' . $label . ':** ' . formatCurrency($amount);
        }

        $lines[] = '';
        $lines[] = assistantAdminLink('pages/dashboard.php', 'Open dashboard');

        return ['content' => implode("\n", $lines)];
    }

    /** @return array{content: string} */
    private static function overview(): array
    {
        $stats = getDashboardStats();

        return [
            'content' => "**Operations dashboard**\n\n"
                . '• Clients: **' . (int) ($stats['total_clients'] ?? 0) . "**\n"
                . '• Active cases: **' . (int) ($stats['active_cases'] ?? 0) . "**\n"
                . '• Total revenue: **' . formatCurrency((float) ($stats['total_revenue'] ?? 0)) . "**\n"
                . '• Monthly revenue: **' . formatCurrency((float) ($stats['monthly_revenue'] ?? 0)) . "**\n"
                . '• Pending invoices: **' . (int) ($stats['pending_invoices'] ?? 0) . "**\n"
                . '• Overdue invoices: **' . (int) ($stats['overdue_invoices'] ?? 0) . "**\n"
                . '• Upcoming appointments: **' . (int) ($stats['upcoming_appointments'] ?? 0) . "**\n\n"
                . 'Ask for **client count**, **active cases**, **recent payments**, **overdue invoices**, or **revenue by month** for details.',
        ];
    }
}
