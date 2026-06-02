<?php

declare(strict_types=1);

function chatbotPageLink(string $path, string $label): string
{
    return chatbotAdminLink($path, $label);
}

function chatbotExtractAppointmentStatusFilter(string $message): ?string
{
    $normalized = strtolower(trim($message));

    if (preg_match('/\b(cancelled|canceled)\b/', $normalized)) {
        return 'cancelled';
    }
    if (preg_match('/\b(completed|done)\b/', $normalized) && preg_match('/\bappointment/', $normalized)) {
        return 'completed';
    }
    if (preg_match('/\bconfirmed\b/', $normalized)) {
        return 'confirmed';
    }
    if (preg_match('/\bscheduled\b/', $normalized)) {
        return 'scheduled';
    }
    if (preg_match('/\b(requested|pending request)\b/', $normalized)) {
        return 'requested';
    }

    return null;
}

function chatbotFormatAppointmentListWithLinks(array $rows, string $heading): string
{
    if ($rows === []) {
        return str_replace(':**', ' yet:**', $heading);
    }

    $lines = [$heading, ''];
    foreach ($rows as $row) {
        $when = !empty($row['start_time']) ? formatDateTime($row['start_time']) : 'TBD';
        $client = clientFullName($row);
        $status = ucfirst($row['status'] ?? 'scheduled');
        $line = '• **' . ($row['title'] ?? 'Appointment') . '** — '
            . $when . ' — ' . $client . " (*{$status}*)";
        if (!empty($row['id'])) {
            $line .= ' — ' . chatbotAdminLink('pages/appointments.php', 'Open');
        }
        $lines[] = $line;
    }

    $lines[] = '';
    $lines[] = chatbotAdminLink('pages/appointments.php', 'View all appointments');

    return implode("\n", $lines);
}

function chatbotReplyForAppointmentQueries(string $message): ?string
{
    $normalized = strtolower(trim($message));

    if (!preg_match('/\b(appointment|appointments|schedule|meeting|calendar)\b/', $normalized)) {
        return null;
    }

    $statusFilter = chatbotExtractAppointmentStatusFilter($message);
    $startSql = appointmentStartSql('a');
    $endSql = appointmentEndSql('a');
    $params = [];
    $where = ['1=1'];

    if ($statusFilter !== null) {
        $where[] = 'LOWER(a.status) = ?';
        $params[] = $statusFilter;
    }

    if (preg_match('/\b(upcoming|future|next)\b/', $normalized)) {
        $where[] = "a.status IN ('scheduled', 'confirmed')";
        $where[] = "({$startSql} >= NOW() OR ({$endSql} IS NOT NULL AND {$endSql} >= NOW()))";
    }

    $whereSql = implode(' AND ', $where);

    if (chatbotWantsCount($normalized) || preg_match('/\bhow many\b/', $normalized)) {
        $count = (int) (Database::fetch(
            "SELECT COUNT(*) AS c FROM appointments a WHERE {$whereSql}",
            $params
        )['c'] ?? 0);

        $label = $statusFilter !== null
            ? strtolower($statusFilter) . ' appointments'
            : (preg_match('/\bupcoming\b/', $normalized) ? 'upcoming appointments' : 'appointments');

        $lines = ["You have **{$count} {$label}**."];
        $_SESSION['chatbot_last_topic'] = 'appointments';

        if ($count > 0) {
            $rows = Database::fetchAll(
                "SELECT a.id, a.title, a.status, {$startSql} AS start_time,
                        cl.first_name, cl.last_name, cl.company_name
                 FROM appointments a
                 LEFT JOIN clients cl ON cl.id = a.client_id
                 WHERE {$whereSql}
                 ORDER BY {$startSql} ASC
                 LIMIT 8",
                $params
            );
            $lines[] = '';
            foreach ($rows as $row) {
                $when = !empty($row['start_time']) ? formatDateTime($row['start_time']) : 'TBD';
                $client = clientFullName($row);
                $status = ucfirst($row['status'] ?? 'scheduled');
                $lines[] = '• **' . ($row['title'] ?? 'Appointment') . '** — '
                    . $when . ' — ' . $client . " (*{$status}*) — "
                    . chatbotAdminLink('pages/appointments.php', 'Open');
            }
        }

        $lines[] = '';
        $lines[] = chatbotAdminLink('pages/appointments.php', 'View all appointments');

        return implode("\n", $lines);
    }

    if (chatbotWantsList($normalized) || preg_match('/\blist|\bshow|\bupcoming\b/', $normalized)) {
        $rows = Database::fetchAll(
            "SELECT a.id, a.title, a.status, {$startSql} AS start_time,
                    cl.first_name, cl.last_name, cl.company_name
             FROM appointments a
             LEFT JOIN clients cl ON cl.id = a.client_id
             WHERE {$whereSql}
             ORDER BY {$startSql} ASC
             LIMIT 12",
            $params
        );

        $_SESSION['chatbot_last_topic'] = 'appointments';

        return chatbotFormatAppointmentListWithLinks($rows, '**Appointments:**');
    }

    return null;
}

function chatbotFormatNotificationListWithLinks(array $rows, string $heading, ?int $unreadCount = null): string
{
    if ($rows === []) {
        return 'No notifications yet. ' . chatbotAdminLink('pages/notifications.php', 'Open notifications');
    }

    $lines = [$heading];
    if ($unreadCount !== null) {
        $lines[0] .= ' (' . $unreadCount . ' unread)';
    }
    $lines[] = '';

    foreach ($rows as $row) {
        $read = !empty($row['is_read']) ? '' : ' *(unread)*';
        $title = (string) ($row['title'] ?? 'Notification');
        $target = notificationRedirectTarget($row);
        $linkPath = str_starts_with($target, 'http') ? $target : '../' . ltrim($target, '/');
        $lines[] = '• **' . $title . '**' . $read . ' — [Open](' . $linkPath . ')';
    }

    $lines[] = '';
    $lines[] = chatbotAdminLink('pages/notifications.php', 'View all notifications');

    return implode("\n", $lines);
}

function chatbotReplyForCaseQueries(string $message): ?string
{
    $normalized = strtolower(trim($message));

    if (!preg_match('/\b(case|cases|matter|matters)\b/', $normalized)) {
        return null;
    }

    $stats = getDashboardStats();

    if (preg_match('/\b(active|open|in progress|pending)\b/', $normalized)
        && (chatbotWantsList($normalized) || preg_match('/\blist|\bshow\b/', $normalized))) {
        $_SESSION['chatbot_last_topic'] = 'active_cases';

        return formatChatbotCaseList(getActiveCasesForChat(), '**Active cases:**');
    }

    if (preg_match('/\b(active|open|in progress|pending)\b/', $normalized)
        && (chatbotWantsCount($normalized) || preg_match('/\bhow many\b/', $normalized))) {
        $_SESSION['chatbot_last_topic'] = 'active_cases';

        return 'There are **' . $stats['active_cases'] . ' active cases**. '
            . chatbotAdminLink('pages/cases.php', 'Open cases');
    }

    if (chatbotWantsList($normalized) || preg_match('/\blist|\bshow|\brecent\b/', $normalized)) {
        $_SESSION['chatbot_last_topic'] = 'cases';

        return formatChatbotCaseList(
            Database::fetchAll(
                "SELECT cs.id, cs.case_number, cs.title, cs.status, cl.first_name, cl.last_name, cl.company_name
                 FROM cases cs
                 JOIN clients cl ON cl.id = cs.client_id
                 ORDER BY cs.updated_at DESC
                 LIMIT 10"
            ),
            '**Recent cases:**'
        );
    }

    if (chatbotWantsCount($normalized) || preg_match('/\bhow many\b/', $normalized)) {
        $_SESSION['chatbot_last_topic'] = 'cases';
        $totalCases = (int) (Database::fetch('SELECT COUNT(*) AS c FROM cases')['c'] ?? 0);

        return "You have **{$totalCases} cases** in total, with **{$stats['active_cases']} active**. "
            . chatbotAdminLink('pages/cases.php', 'Open cases');
    }

    return null;
}

function chatbotReplyForNotificationQueries(string $message): ?string
{
    $normalized = strtolower(trim($message));

    if (!preg_match('/\b(notification|notifications|alert|alerts)\b/', $normalized)) {
        return null;
    }

    $userId = Auth::id();
    if ($userId === null) {
        return 'Please log in to view notifications. '
            . chatbotAdminLink('pages/notifications.php', 'Open notifications');
    }

    $unread = getUnreadNotificationCount($userId);

    if (chatbotWantsCount($normalized) || preg_match('/\bhow many\b/', $normalized)) {
        $suffix = $unread > 0
            ? chatbotFormatNotificationListWithLinks(getRecentNotifications($userId, 6, false), '**Recent notifications:**', $unread)
            : 'You have **0 unread notifications**. ' . chatbotAdminLink('pages/notifications.php', 'Open notifications');

        if ($unread > 0) {
            return 'You have **' . $unread . " unread notifications**.\n\n" . $suffix;
        }

        return $suffix;
    }

    if (preg_match('/\b(unread|new)\b/', $normalized)) {
        $rows = Database::fetchAll(
            'SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 10',
            [$userId]
        );

        return chatbotFormatNotificationListWithLinks($rows, '**Unread notifications:**', $unread);
    }

    if (chatbotWantsList($normalized) || preg_match('/\blist|\bshow|\brecent\b/', $normalized)) {
        return chatbotFormatNotificationListWithLinks(
            getRecentNotifications($userId, 10, false),
            '**Recent notifications:**',
            $unread
        );
    }

    return 'You have **' . $unread . ' unread notifications**. '
        . chatbotAdminLink('pages/notifications.php', 'Open notifications');
}

function chatbotFormatDocumentList(array $rows, string $heading): string
{
    if ($rows === []) {
        return str_replace(':**', ' yet:**', $heading);
    }

    $lines = [$heading, ''];
    foreach ($rows as $row) {
        $name = (string) ($row['original_name'] ?? $row['file_name'] ?? 'Document');
        $case = (string) ($row['case_number'] ?? '');
        $when = !empty($row['created_at']) ? formatDateTime($row['created_at']) : '';
        $suffix = $case !== '' ? " — case **{$case}**" : '';
        $lines[] = '• **' . $name . '**' . $suffix . ($when !== '' ? " — {$when}" : '');
    }

    return implode("\n", $lines);
}

function chatbotIsProceduralQuery(string $message): bool
{
    $normalized = strtolower(trim($message));

    return (bool) preg_match(
        '/\b(how to|how do i|how should|what should|what do i|next step|proceed|instructions?|waiting for client|prepare|workflow|process|update status|send to client|notify client|what happens|what now|help me with|guide me|walk me through|can i|should i)\b/',
        $normalized
    );
}

function chatbotIsPortalProceduralQuery(string $message): bool
{
    if (!chatbotIsProceduralQuery($message)) {
        return false;
    }

    $normalized = strtolower(trim($message));

    return (bool) preg_match(
        '/\b(portal|admin|case|client|invoice|appointment|document|payment|quotation|letter|settings|upload|email|notify|status)\b/',
        $normalized
    );
}

function chatbotIsAdviceOrHowToQuery(string $message): bool
{
    $normalized = strtolower(trim($message));

    return (bool) preg_match(
        '/\b(how to handle|how should i|what should i|advice|best way|deal with|handle a|handle tough|difficult client|tips for|recommend|suggest|what would you|help me with a|guide me on)\b/',
        $normalized
    );
}

function chatbotIsDocumentDataQuery(string $normalized): bool
{
    return (bool) preg_match(
        '/\b(document|documents|doc|docs|upload|uploaded|uploads|file|files|pdf|attachment)\b/',
        $normalized
    ) && (bool) preg_match(
        '/\b(list|show|any|have|has|uploaded|count|how many|who|which|recent|latest|tell me if|are there)\b/',
        $normalized
    );
}

function chatbotReplyForMorningBriefing(string $message): ?string
{
    if (!preg_match('/\b(morning briefing|daily briefing|start my day|today overview|good morning)\b/i', $message)) {
        return null;
    }

    syncOverdueInvoices();
    $ctx = getChatbotContext();
    $stats = $ctx['stats'];
    $brand = companyBrandName();

    $lines = [
        "**Good morning! Here's your briefing for {$brand}:**",
        '',
        '• **Clients:** ' . $stats['total_clients'],
        '• **Active cases:** ' . $stats['active_cases'],
        '• **Pending invoices:** ' . $stats['pending_invoices'],
        '• **Upcoming appointments:** ' . $stats['upcoming_appointments'],
        '• **Total revenue:** ' . formatCurrency($stats['total_revenue']),
    ];

    if (!empty($ctx['next_appointment']['title'])) {
        $appt = $ctx['next_appointment'];
        $when = !empty($appt['start_time']) ? formatDateTime($appt['start_time']) : 'TBD';
        $lines[] = '• **Next appointment:** ' . $appt['title'] . ' — ' . $when;
    }

    $overdue = (int) (Database::fetch(
        'SELECT COUNT(*) AS c FROM invoices WHERE ' . invoiceStatusColumn() . " = 'overdue'"
    )['c'] ?? 0);

    if ($overdue > 0) {
        $lines[] = '• **Overdue invoices:** ' . $overdue . ' — ask “list overdue invoices” for details.';
    }

    if (!empty($ctx['recent_cases'])) {
        $lines[] = '';
        $lines[] = '**Recent case activity:**';
        foreach (array_slice($ctx['recent_cases'], 0, 3) as $case) {
            $status = ucwords(str_replace('_', ' ', $case['status'] ?? ''));
            $lines[] = '• **' . ($case['case_number'] ?? 'Case') . '** — '
                . ($case['title'] ?? '') . " (*{$status}*)";
        }
    }

    $lines[] = '';
    $lines[] = '_Ask about a client name, active cases, payments, or appointments anytime._';

    $_SESSION['chatbot_last_topic'] = 'dashboard';

    return implode("\n", $lines);
}

function chatbotReplyForDateFilteredQueries(string $message): ?string
{
    $normalized = strtolower(trim($message));

    if (!preg_match('/\b(today|yesterday|this week|last week|this month|last month|past \d+ days?|recent)\b/', $normalized)) {
        return null;
    }

    $startSql = appointmentStartSql('a');
    $statusCol = invoiceStatusColumn();
    $paymentCol = paymentStatusColumn();

    if (preg_match('/\b(appointment|appointments|schedule|meeting)\b/', $normalized)) {
        $where = match (true) {
            str_contains($normalized, 'today') => "DATE({$startSql}) = CURDATE()",
            str_contains($normalized, 'yesterday') => "DATE({$startSql}) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)",
            str_contains($normalized, 'this week') => "YEARWEEK({$startSql}, 1) = YEARWEEK(CURDATE(), 1)",
            str_contains($normalized, 'last week') => "YEARWEEK({$startSql}, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)",
            str_contains($normalized, 'this month') => "YEAR({$startSql}) = YEAR(CURDATE()) AND MONTH({$startSql}) = MONTH(CURDATE())",
            str_contains($normalized, 'last month') => "YEAR({$startSql}) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH({$startSql}) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))",
            default => "{$startSql} >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
        };

        $rows = Database::fetchAll(
            "SELECT a.title, a.status, {$startSql} AS start_time, cl.first_name, cl.last_name, cl.company_name
             FROM appointments a
             LEFT JOIN clients cl ON cl.id = a.client_id
             WHERE {$where}
             ORDER BY {$startSql} ASC
             LIMIT 12"
        );

        if ($rows === []) {
            return 'No appointments found for that time period.';
        }

        $lines = ['**Appointments for that period:**', ''];
        foreach ($rows as $row) {
            $when = !empty($row['start_time']) ? formatDateTime($row['start_time']) : 'TBD';
            $client = clientFullName($row);
            $lines[] = '• **' . ($row['title'] ?? 'Appointment') . '** — '
                . $when . ' — ' . $client . ' (*' . ucfirst($row['status'] ?? 'scheduled') . '*)';
        }

        $_SESSION['chatbot_last_topic'] = 'appointments';

        return implode("\n", $lines);
    }

    if (preg_match('/\b(payment|payments|paid|receipt|receipts)\b/', $normalized)) {
        $dateExpr = 'COALESCE(p.paid_at, p.created_at)';
        $where = match (true) {
            str_contains($normalized, 'today') => "DATE({$dateExpr}) = CURDATE()",
            str_contains($normalized, 'yesterday') => "DATE({$dateExpr}) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)",
            str_contains($normalized, 'this week') => "YEARWEEK({$dateExpr}, 1) = YEARWEEK(CURDATE(), 1)",
            str_contains($normalized, 'last week') => "YEARWEEK({$dateExpr}, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)",
            str_contains($normalized, 'this month') => "YEAR({$dateExpr}) = YEAR(CURDATE()) AND MONTH({$dateExpr}) = MONTH(CURDATE())",
            str_contains($normalized, 'last month') => "YEAR({$dateExpr}) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH({$dateExpr}) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))",
            default => "{$dateExpr} >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
        };

        $rows = Database::fetchAll(
            "SELECT p.amount, p.paid_at, p.created_at, p.{$paymentCol} AS payment_status,
                    i.invoice_number, cl.first_name, cl.last_name, cl.company_name
             FROM payments p
             JOIN invoices i ON i.id = p.invoice_id
             JOIN clients cl ON cl.id = i.client_id
             WHERE {$where}
             ORDER BY {$dateExpr} DESC
             LIMIT 12"
        );

        if ($rows === []) {
            return 'No payments found for that time period.';
        }

        $lines = ['**Payments for that period:**', ''];
        foreach ($rows as $row) {
            $when = !empty($row['paid_at']) ? formatDateTime($row['paid_at']) : formatDateTime($row['created_at'] ?? '');
            $name = clientFullName($row);
            $lines[] = '• ' . formatCurrency((float) ($row['amount'] ?? 0))
                . " from **{$name}** — " . ($row['invoice_number'] ?? 'Invoice') . " — {$when}";
        }

        $_SESSION['chatbot_last_topic'] = 'payments';

        return implode("\n", $lines);
    }

    if (preg_match('/\b(invoice|invoices)\b/', $normalized)) {
        $where = match (true) {
            str_contains($normalized, 'today') => 'DATE(i.created_at) = CURDATE()',
            str_contains($normalized, 'this week') => 'YEARWEEK(i.created_at, 1) = YEARWEEK(CURDATE(), 1)',
            str_contains($normalized, 'this month') => 'YEAR(i.created_at) = YEAR(CURDATE()) AND MONTH(i.created_at) = MONTH(CURDATE())',
            default => 'i.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)',
        };

        $rows = Database::fetchAll(
            "SELECT i.invoice_number, i.total, i.due_date, i.{$statusCol} AS invoice_status,
                    cl.first_name, cl.last_name, cl.company_name
             FROM invoices i
             JOIN clients cl ON cl.id = i.client_id
             WHERE {$where}
             ORDER BY i.created_at DESC
             LIMIT 12"
        );

        if ($rows === []) {
            return 'No invoices found for that time period.';
        }

        $lines = ['**Invoices for that period:**', ''];
        foreach ($rows as $row) {
            $status = ucwords(str_replace('_', ' ', $row['invoice_status'] ?? 'pending'));
            $name = clientFullName($row);
            $lines[] = '• **' . ($row['invoice_number'] ?? 'Invoice') . '** — '
                . formatCurrency((float) ($row['total'] ?? 0)) . " — {$name} (*{$status}*)";
        }

        $_SESSION['chatbot_last_topic'] = 'payments';

        return implode("\n", $lines);
    }

    return null;
}

function chatbotReplyForCalculations(string $message): ?string
{
    $normalized = strtolower(trim($message));

    if (preg_match('/(\d+(?:\.\d+)?)\s*%\s*of\s+(?:the\s+)?(?:total\s+)?revenue/u', $normalized, $matches)) {
        $pct = (float) $matches[1];
        $stats = getDashboardStats();
        $total = (float) ($stats['total_revenue'] ?? 0);
        $amount = round($total * ($pct / 100), 2);

        return '**' . $pct . '% of total revenue** (' . formatCurrency($total) . ') = **'
            . formatCurrency($amount) . '**';
    }

    if (preg_match('/(\d+(?:\.\d+)?)\s*%\s*of\s+(?:the\s+)?(?:monthly|this month\'?s?)\s+revenue/u', $normalized, $matches)) {
        $pct = (float) $matches[1];
        $stats = getDashboardStats();
        $total = (float) ($stats['monthly_revenue'] ?? 0);
        $amount = round($total * ($pct / 100), 2);

        return '**' . $pct . '% of this month\'s revenue** (' . formatCurrency($total) . ') = **'
            . formatCurrency($amount) . '**';
    }

    if (preg_match('/(\d+(?:\.\d+)?)\s*%\s*of\s+(\d+(?:\.\d+)?)/u', $normalized, $matches)) {
        $pct = (float) $matches[1];
        $base = (float) $matches[2];
        $amount = round($base * ($pct / 100), 2);

        return '**' . $pct . '% of ' . number_format($base, 2) . '** = **' . number_format($amount, 2) . '**';
    }

    if (preg_match('/^(?:what is\s+)?([\d+\-*\/().\s]+)\??$/u', trim($message), $matches)) {
        $expr = preg_replace('/\s+/', '', $matches[1]);
        if (!preg_match('/^[\d+\-*\/().]+$/', $expr)) {
            return null;
        }

        $result = chatbotEvaluateMathExpression($expr);
        if ($result === null) {
            return null;
        }

        return '**' . trim($matches[1]) . '** = **' . $result . '**';
    }

    if (preg_match('/\b([\d+\-*\/().\s]{3,})\b/u', trim($message), $matches)
        && !preg_match('/\b(client|case|appointment|invoice|payment|revenue|notification)\b/i', $message)) {
        $expr = preg_replace('/\s+/', '', $matches[1]);
        if (!preg_match('/^[\d+\-*\/().]+$/', $expr) || !preg_match('/[\+\-*\/]/', $expr)) {
            return null;
        }

        $result = chatbotEvaluateMathExpression($expr);
        if ($result === null) {
            return null;
        }

        return '**' . trim($matches[1]) . '** = **' . $result . '**';
    }

    return null;
}

function chatbotEvaluateMathExpression(string $expr): ?string
{
    if ($expr === '' || !preg_match('/^[\d+\-*\/().]+$/', $expr)) {
        return null;
    }

    try {
        $result = @eval('return ' . $expr . ';');
    } catch (Throwable) {
        return null;
    }

    if (!is_int($result) && !is_float($result) || !is_finite((float) $result)) {
        return null;
    }

    return is_float($result)
        ? rtrim(rtrim(number_format($result, 4, '.', ''), '0'), '.')
        : (string) $result;
}

function chatbotReplyForSystemInsights(string $message): ?string
{
    $normalized = strtolower(trim($message));
    syncOverdueInvoices();
    $stats = getDashboardStats();
    $statusCol = invoiceStatusColumn();

    if (preg_match('/\b(notification|notifications)\b/', $normalized)) {
        $notificationReply = chatbotReplyForNotificationQueries($message);
        if ($notificationReply !== null) {
            return $notificationReply;
        }
    }

    if (preg_match('/\b(case|cases)\b/', $normalized)
        && (chatbotWantsCount($normalized) || chatbotWantsList($normalized) || preg_match('/\bhow many|\blist|\bshow|\bactive\b/', $normalized))) {
        $caseReply = chatbotReplyForCaseQueries($message);
        if ($caseReply !== null) {
            return $caseReply;
        }
    }

    if (preg_match('/\b(appointment|appointments|schedule|meeting|calendar)\b/', $normalized)) {
        $appointmentReply = chatbotReplyForAppointmentQueries($message);
        if ($appointmentReply !== null) {
            return $appointmentReply;
        }
    }

    if (preg_match('/\b(overdue invoice|overdue invoices|past due)\b/', $normalized)
        && (chatbotWantsList($normalized) || preg_match('/\blist|\bshow|\bany\b/', $normalized))) {
        $rows = Database::fetchAll(
            "SELECT i.invoice_number, i.total, i.due_date, i.{$statusCol} AS invoice_status,
                    cl.first_name, cl.last_name, cl.company_name
             FROM invoices i
             JOIN clients cl ON cl.id = i.client_id
             WHERE i.{$statusCol} = 'overdue'
             ORDER BY i.due_date ASC
             LIMIT 12"
        );

        if ($rows === []) {
            return 'No overdue invoices — great work!';
        }

        $lines = ['**Overdue invoices:**', ''];
        foreach ($rows as $row) {
            $name = clientFullName($row);
            $due = !empty($row['due_date']) ? formatDate($row['due_date']) : '—';
            $lines[] = '• **' . ($row['invoice_number'] ?? 'Invoice') . '** — '
                . formatCurrency((float) ($row['total'] ?? 0)) . " — {$name} — due {$due}";
        }

        $_SESSION['chatbot_last_topic'] = 'payments';

        return implode("\n", $lines);
    }

    if (preg_match('/\b(receipt|receipts)\b/', $normalized)
        && (chatbotWantsList($normalized) || preg_match('/\blist|\bshow|\brecent\b/', $normalized))) {
        $payments = getAllPayments();
        if ($payments === []) {
            return 'No receipts / payments recorded yet.';
        }

        $lines = ['**Recent receipts / payments:**', ''];
        foreach (array_slice($payments, 0, 10) as $payment) {
            $name = clientFullName($payment);
            $status = ucfirst(paymentStatusValue($payment));
            $lines[] = '• ' . formatCurrency((float) $payment['amount'])
                . " from **{$name}** — " . ($payment['invoice_number'] ?? 'Invoice') . " (*{$status}*)";
        }

        $_SESSION['chatbot_last_topic'] = 'payments';

        return implode("\n", $lines);
    }

    if (chatbotIsDocumentDataQuery($normalized)) {
        if (preg_match('/\b(how many|count|number of)\b/', $normalized)) {
            $count = (int) (Database::fetch('SELECT COUNT(*) AS c FROM documents')['c'] ?? 0);

            return 'There are **' . $count . ' documents** uploaded across all cases.';
        }

        $clients = findClientsForChatbot(chatbotNormalizeLookupTerm($message));
        if (count($clients) === 1) {
            return chatbotReplyForClientFocus((int) $clients[0]['id'], $message);
        }

        $rows = Database::fetchAll(
            'SELECT d.original_name, d.file_name, d.created_at, cs.case_number, cl.first_name, cl.last_name, cl.company_name
             FROM documents d
             JOIN cases cs ON cs.id = d.case_id
             JOIN clients cl ON cl.id = cs.client_id
             ORDER BY d.created_at DESC
             LIMIT 12'
        );

        if ($rows === []) {
            return 'No documents have been uploaded yet.';
        }

        $_SESSION['chatbot_last_topic'] = 'documents';

        return chatbotFormatDocumentList($rows, '**Recent documents:**');
    }

    if (preg_match('/\b(pending payment|pending payments|any pending|awaiting payment)\b/', $normalized)) {
        $count = (int) (Database::fetch(
            "SELECT COUNT(*) AS c FROM invoices WHERE {$statusCol} IN ('pending','overdue','partially_paid')"
        )['c'] ?? 0);

        return 'You have **' . $count . ' invoices** awaiting payment (pending, partial, or overdue).';
    }

    if (preg_match('/\b(dashboard|summary|overview|snapshot)\b/', $normalized)) {
        $_SESSION['chatbot_last_topic'] = 'dashboard';

        return "**Dashboard overview:**\n\n"
            . "• Clients: {$stats['total_clients']}\n"
            . "• Active cases: {$stats['active_cases']}\n"
            . "• Pending invoices: {$stats['pending_invoices']}\n"
            . "• Upcoming appointments: {$stats['upcoming_appointments']}\n"
            . "• Total revenue: " . formatCurrency($stats['total_revenue']);
    }

    return null;
}
