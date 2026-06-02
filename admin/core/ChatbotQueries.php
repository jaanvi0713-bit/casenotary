<?php

declare(strict_types=1);

function chatbotPageLink(string $path, string $label): string
{
    return chatbotAdminLink($path, $label);
}

function getChatbotDefaultQuickPrompts(): array
{
    return [
        ['icon' => 'bi-sunrise', 'label' => 'Morning briefing', 'prompt' => 'Morning briefing'],
        ['icon' => 'bi-grid-1x2', 'label' => 'Dashboard summary', 'prompt' => 'Give me a dashboard summary'],
        ['icon' => 'bi-people', 'label' => 'Client count', 'prompt' => 'How many clients do we have?'],
        ['icon' => 'bi-briefcase', 'label' => 'Active cases', 'prompt' => 'List active cases'],
        ['icon' => 'bi-cash-stack', 'label' => 'Total revenue', 'prompt' => 'What is our total revenue?'],
        ['icon' => 'bi-calendar-event', 'label' => 'Upcoming appointments', 'prompt' => 'Show upcoming appointments'],
        ['icon' => 'bi-credit-card', 'label' => 'Recent payments', 'prompt' => 'List recent payments'],
        ['icon' => 'bi-exclamation-circle', 'label' => 'Overdue invoices', 'prompt' => 'List overdue invoices'],
    ];
}

function chatbotExtractAppointmentStatusFilter(string $message): ?string
{
    $normalized = strtolower(trim($message));

    if (preg_match('/^(requested|scheduled|confirmed|cancelled|canceled|completed)$/', $normalized)) {
        return $normalized === 'canceled' ? 'cancelled' : $normalized;
    }

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

function chatbotIsAppointmentRelatedMessage(string $message): bool
{
    $normalized = strtolower(trim($message));

    if (preg_match('/\b(appointment|appointments|schedule|meeting|calendar)\b/', $normalized)) {
        return true;
    }

    if (preg_match('/^(requested|scheduled|confirmed|cancelled|canceled|completed)$/', $normalized)) {
        return true;
    }

    if (!empty($_SESSION['chatbot_appointment_pending']) && is_array($_SESSION['chatbot_appointment_pending'])) {
        if (chatbotIsAdviceOrHowToQuery($message) || chatbotIsGeneralQuestion($message)) {
            return false;
        }

        return chatbotWantsAppointmentListFollowUp($normalized)
            || chatbotLooksLikeAppointmentClientRefinement($message);
    }

    return false;
}

function chatbotWantsAppointmentListFollowUp(string $normalized): bool
{
    return (bool) preg_match(
        '/^(yes|yeah|yep|sure|ok|okay|list|list them|list all|show|show them|show all|show me|all of them|every one|go on|continue)$/',
        $normalized
    ) || (bool) preg_match(
        '/\b(list them|list them all|list all|show all|show them|show me all|all appointments|every appointment)\b/',
        $normalized
    );
}

function chatbotLooksLikeAppointmentClientRefinement(string $message): bool
{
    $normalized = strtolower(trim($message));

    if (chatbotIsAdviceOrHowToQuery($message)) {
        return false;
    }

    if (preg_match('/^(how|what|why|when|where|can|should|is|are|do|does)\s+/i', trim($message))) {
        return false;
    }

    if (preg_match('/\b(for|about|from)\s+(.{2,})/', $normalized)) {
        return true;
    }

    if (preg_match('/\b(client|customer)\s+(.{2,})/', $normalized)) {
        return true;
    }

    if (preg_match('/\bwith\s+([a-z][a-z\s\'-]{1,40})$/i', $normalized, $matches)) {
        $fragment = strtolower(trim($matches[1]));
        if (!preg_match('/\b(tough|difficult|angry|problem|challenging|upset|hostile)\b/', $fragment)) {
            return true;
        }
    }

    $term = chatbotNormalizeLookupTerm($message);

    return $term !== '' && strlen($term) >= 2 && str_word_count($term) <= 4
        && !chatbotWantsAppointmentListFollowUp($normalized)
        && !preg_match('/\b(how many|list all|show all|how to|deal with|handle)\b/', $normalized);
}

/**
 * @return array{where_sql: string, params: list<mixed>, label: string, status: ?string}
 */
function chatbotBuildAppointmentFilter(string $message): array
{
    $normalized = strtolower(trim($message));
    $statusFilter = chatbotExtractAppointmentStatusFilter($message);
    $startSql = appointmentStartSql('a');
    $endSql = appointmentEndSql('a');
    $params = [];
    $where = ['1=1'];

    if ($statusFilter !== null) {
        $where[] = 'LOWER(a.status) = ?';
        $params[] = $statusFilter;
    }

    $upcomingOnly = (bool) preg_match('/\b(upcoming|future|next)\b/', $normalized);
    if ($upcomingOnly) {
        $where[] = "a.status IN ('scheduled', 'confirmed')";
        $where[] = "({$startSql} >= NOW() OR ({$endSql} IS NOT NULL AND {$endSql} >= NOW()))";
    }

    $label = match (true) {
        $statusFilter !== null => strtolower($statusFilter) . ' appointments',
        $upcomingOnly => 'upcoming appointments',
        default => 'appointments',
    };

    return [
        'where_sql' => implode(' AND ', $where),
        'params'    => $params,
        'label'     => $label,
        'status'    => $statusFilter,
        'upcoming'  => $upcomingOnly,
    ];
}

/**
 * @param array{where_sql: string, params: list<mixed>, label: string} $filter
 * @return list<array<string, mixed>>
 */
function chatbotFetchAppointmentsForFilter(array $filter, ?int $clientId = null, int $limit = 20): array
{
    $startSql = appointmentStartSql('a');
    $where = [$filter['where_sql']];
    $params = $filter['params'];

    if ($clientId !== null && $clientId > 0) {
        $where[] = 'a.client_id = ?';
        $params[] = $clientId;
    }

    $whereSql = implode(' AND ', $where);

    return Database::fetchAll(
        "SELECT a.id, a.title, a.status, a.client_id, {$startSql} AS start_time,
                cl.first_name, cl.last_name, cl.company_name, cl.email, cl.phone,
                (SELECT COUNT(*) FROM cases cs WHERE cs.client_id = cl.id) AS case_count
         FROM appointments a
         LEFT JOIN clients cl ON cl.id = a.client_id
         WHERE {$whereSql}
         ORDER BY {$startSql} ASC
         LIMIT ?",
        array_merge($params, [$limit])
    );
}

function chatbotCountAppointmentsForFilter(array $filter, ?int $clientId = null): int
{
    $where = [$filter['where_sql']];
    $params = $filter['params'];

    if ($clientId !== null && $clientId > 0) {
        $where[] = 'a.client_id = ?';
        $params[] = $clientId;
    }

    return (int) (Database::fetch(
        'SELECT COUNT(*) AS c FROM appointments a WHERE ' . implode(' AND ', $where),
        $params
    )['c'] ?? 0);
}

function chatbotFormatAppointmentDetailList(array $rows, string $heading): string
{
    if ($rows === []) {
        return str_replace(':**', ' yet:**', $heading);
    }

    $lines = [$heading, ''];

    foreach ($rows as $row) {
        $when = !empty($row['start_time']) ? formatDateTime($row['start_time']) : 'TBD';
        $client = clientFullName($row);
        if ($client === '' || $client === ' ') {
            $client = '— (no client linked)';
        }
        $status = ucwords(str_replace('_', ' ', $row['status'] ?? 'scheduled'));
        $cases = (int) ($row['case_count'] ?? 0);
        $email = trim((string) ($row['email'] ?? ''));
        $phone = trim((string) ($row['phone'] ?? ''));

        $lines[] = '• **' . ($row['title'] ?? 'Appointment') . '** — ' . $when . " (*{$status}*)";
        $lines[] = '  Client: **' . $client . '** · **' . $cases . '** case(s)'
            . ($email !== '' ? ' · ' . $email : '')
            . ($phone !== '' ? ' · ' . $phone : '');
        $lines[] = '  ' . chatbotAdminLink('pages/appointments.php', 'Open in calendar');
        $lines[] = '';
    }

    $lines[] = chatbotAdminLink('pages/appointments.php', 'View all appointments');

    return implode("\n", $lines);
}

function chatbotSetAppointmentPendingContext(array $filter, int $count): void
{
    $_SESSION['chatbot_appointment_pending'] = [
        'where_sql' => $filter['where_sql'],
        'params'    => $filter['params'],
        'label'     => $filter['label'],
        'status'    => $filter['status'] ?? null,
        'count'     => $count,
    ];
    $_SESSION['chatbot_last_topic'] = 'appointments';
}

function chatbotReplyForAppointmentFollowUp(string $message): ?string
{
    $pending = $_SESSION['chatbot_appointment_pending'] ?? null;
    if (!is_array($pending) || empty($pending['where_sql'])) {
        return null;
    }

    $normalized = strtolower(trim($message));
    $filter = [
        'where_sql' => (string) $pending['where_sql'],
        'params'    => is_array($pending['params'] ?? null) ? $pending['params'] : [],
        'label'     => (string) ($pending['label'] ?? 'appointments'),
        'status'    => $pending['status'] ?? null,
    ];

    $clientId = null;
    $clientTerm = '';

    if (preg_match('/\b(?:for|about|from|with|client)\s+(.+)/', $normalized, $matches)) {
        $clientTerm = trim($matches[1]);
    } elseif (chatbotLooksLikeAppointmentClientRefinement($message)) {
        $clientTerm = chatbotNormalizeLookupTerm($message);
    }

    if ($clientTerm !== '') {
        $clients = findClientsForChatbot($clientTerm, 5);
        if (count($clients) === 0) {
            return 'I could not find a client matching **“' . $clientTerm . '”**. '
                . 'Try again with a full name, or say **list all** to see every ' . $filter['label'] . '.';
        }

        if (count($clients) > 1) {
            $lines = ['I found **' . count($clients) . ' clients** matching “' . $clientTerm . '”:', ''];
            foreach ($clients as $client) {
                $name = clientFullName($client);
                $lines[] = '• **' . $name . '** — ' . (int) ($client['case_count'] ?? 0) . ' case(s)';
            }
            $lines[] = '';
            $lines[] = 'Reply with a **full name** to see that client\'s ' . $filter['label'] . '.';

            return implode("\n", $lines);
        }

        $clientId = (int) $clients[0]['id'];
        $name = clientFullName($clients[0]);
        $rows = chatbotFetchAppointmentsForFilter($filter, $clientId, 15);
        $count = count($rows);

        if ($count === 0) {
            return "**{$name}** has no {$filter['label']} matching this filter.";
        }

        unset($_SESSION['chatbot_appointment_pending']);

        return chatbotFormatAppointmentDetailList(
            $rows,
            "**{$filter['label']} for {$name}** ({$count}):"
        );
    }

    if (!chatbotWantsAppointmentListFollowUp($normalized)) {
        return null;
    }

    $rows = chatbotFetchAppointmentsForFilter($filter, null, 25);
    unset($_SESSION['chatbot_appointment_pending']);

    return chatbotFormatAppointmentDetailList(
        $rows,
        '**' . ucfirst($filter['label']) . '** (' . count($rows) . '):'
    );
}

function chatbotReplyForAppointmentQueries(string $message): ?string
{
    if (!chatbotIsAppointmentRelatedMessage($message)) {
        return null;
    }

    $followUp = chatbotReplyForAppointmentFollowUp($message);
    if ($followUp !== null) {
        return $followUp;
    }

    $normalized = strtolower(trim($message));
    $filter = chatbotBuildAppointmentFilter($message);

    if (chatbotWantsList($normalized)
        || preg_match('/\blist all\b|\bshow all\b|\bshow me all\b/', $normalized)) {
        $rows = chatbotFetchAppointmentsForFilter($filter, null, 25);
        unset($_SESSION['chatbot_appointment_pending']);

        return chatbotFormatAppointmentDetailList($rows, '**' . ucfirst($filter['label']) . ':**');
    }

    $count = chatbotCountAppointmentsForFilter($filter);
    chatbotSetAppointmentPendingContext($filter, $count);

    if ($count === 0) {
        unset($_SESSION['chatbot_appointment_pending']);

        return 'You have **0 ' . $filter['label'] . '**. '
            . chatbotAdminLink('pages/appointments.php', 'Open appointments');
    }

    return 'You have **' . $count . ' ' . $filter['label'] . '**.' . "\n\n"
        . 'Would you like me to **list them all** with client details and case counts, '
        . 'or look up a **specific client** (e.g. *“for Emily Chen”* or just *“Emily”*)?';
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

    if (preg_match(
        '/\b(how to|how do i|how should|what should|how can i|ways to|tips for|advice|best way|'
        . 'deal with|handle a|handle tough|difficult client|recommend|suggest|what would you|'
        . 'help me with|guide me|explain|tell me about|what is the best)\b/',
        $normalized
    )) {
        return true;
    }

    return (bool) preg_match('/^(how|what|why|when|where|can|should|is|are|do|does)\s+/i', trim($message))
        && !chatbotIsSystemDataQuestion($message);
}

function chatbotReplyForAdviceAndGeneral(string $message): ?string
{
    if (!chatbotIsAdviceOrHowToQuery($message) && !chatbotIsGeneralQuestion($message)) {
        return null;
    }

    if (chatbotIsSystemDataQuestion($message) && chatbotWantsCount(strtolower($message))) {
        return null;
    }

    unset($_SESSION['chatbot_appointment_pending']);

    $open = chatbotReplyForOpenEndedLocal($message);
    if ($open !== null) {
        return $open;
    }

    $general = chatbotReplyForGeneralKnowledge($message);
    if ($general !== null) {
        return $general;
    }

    $template = chatbotReplyForGeneralizedTemplate($message);
    if ($template !== null) {
        return $template;
    }

    $fused = chatbotFuseKnowledgeByKeywords($message);
    if ($fused !== null) {
        return $fused;
    }

    if (chatbotIsGeneralQuestion($message)) {
        $subject = chatbotExtractQuestionSubject($message);

        return chatbotTemplateOpenAnswer($subject, $message);
    }

    return null;
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
