<?php

declare(strict_types=1);

function chatbotReplyForCaseContext(string $message): ?string
{
    if (chatbotIsDashboardOrBriefingQuery($message)) {
        return null;
    }

    if (!preg_match(
        '/\b(summarize|summarise|summary|overview|what.?s missing|whats missing|missing on|status of|checklist for|'
        . 'draft instructions|client instructions for|what does .+ need|case context)\b/i',
        $message
    ) && !preg_match('/\bcase[- ]?\d{4}[- ]?\d+/i', $message)) {
        if (!preg_match('/\b(this case|that case|the case)\b/i', $message)) {
            return null;
        }
    }

    $case = chatbotResolveCaseFromMessage($message);
    if ($case === null) {
        $entity = chatbotGetLastEntity();
        if (($entity['type'] ?? '') === 'case' && (int) ($entity['id'] ?? 0) > 0) {
            $case = chatbotFetchCaseById((int) $entity['id']);
        }
    }

    if ($case === null) {
        $case = chatbotResolveCaseFromClientName($message);
    }

    if ($case === null) {
        return 'Specify a **case number** (e.g. CASE-2026-0001), a **client name** (e.g. summarise Amara Patel case), or open a case first.';
    }

    $normalized = strtolower(trim($message));

    if (preg_match('/\b(what.?s missing|whats missing|missing on|checklist|incomplete|outstanding)\b/', $normalized)) {
        return chatbotFormatCaseMissingItems($case);
    }

    if (preg_match('/\b(draft|write|compose|prepare).*(instruction|letter|email)/', $normalized)) {
        return chatbotDraftCaseInstructions($case, $message);
    }

    return chatbotSummarizeCase($case);
}

function chatbotResolveCaseFromMessage(string $message): ?array
{
    if (preg_match('/case[- ]?(\d{4}[- ]?\d+)/i', $message, $matches)) {
        $raw = 'CASE-' . str_replace(' ', '-', $matches[1]);

        return findCaseByNumberForChatbot($raw);
    }

    if (preg_match('/\bcase[- ]?(\d+)\b/i', $message, $matches)) {
        return chatbotFetchCaseById((int) $matches[1]);
    }

    return null;
}

function chatbotResolveCaseFromClientName(string $message): ?array
{
    $term = strtolower(trim($message));
    $term = preg_replace('/\b(summarize|summarise|summary|overview|case|cases|matter|the|a|for|about|missing|checklist|status|draft|instructions?)\b/', ' ', $term);
    $term = trim(preg_replace('/\s+/', ' ', (string) $term));
    if ($term === '' || strlen($term) < 2) {
        return null;
    }

    $cases = findCasesForChatbot($term, 5);
    if (count($cases) === 1) {
        return chatbotFetchCaseById((int) $cases[0]['id']);
    }

    if (count($cases) > 1) {
        return chatbotFetchCaseById((int) $cases[0]['id']);
    }

    $clients = findClientsForChatbot($term, 3);
    if (count($clients) === 1) {
        $clientId = (int) $clients[0]['id'];
        $where = ['cs.client_id = ?'];
        $params = [$clientId];
        chatbotAppendCaseScope($where, $params, 'cs', 'cl');

        return Database::fetch(
            'SELECT cs.*, cl.first_name, cl.last_name, cl.company_name, cl.email, cl.phone, cl.id AS client_id
             FROM cases cs
             JOIN clients cl ON cl.id = cs.client_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY cs.updated_at DESC
             LIMIT 1',
            $params
        ) ?: null;
    }

    return null;
}

function chatbotSummarizeCase(array $case): string
{
    $caseId = (int) ($case['id'] ?? 0);
    $status = ucwords(str_replace('_', ' ', $case['status'] ?? 'unknown'));
    $client = clientFullName($case);

    $docCount = (int) (Database::fetch(
        'SELECT COUNT(*) AS c FROM documents WHERE case_id = ?',
        [$caseId]
    )['c'] ?? 0);

    $invoiceCol = invoiceStatusColumn();
    $pendingInvoices = (int) (Database::fetch(
        "SELECT COUNT(*) AS c FROM invoices WHERE case_id = ? AND {$invoiceCol} IN ('pending','overdue','partially_paid')",
        [$caseId]
    )['c'] ?? 0);

    $startSql = appointmentStartSql('a');
    $nextAppt = Database::fetch(
        "SELECT a.title, {$startSql} AS start_time, a.status
         FROM appointments a
         WHERE a.case_id = ? AND a.status IN ('scheduled','confirmed','rescheduled')
           AND {$startSql} >= NOW()
         ORDER BY {$startSql} ASC LIMIT 1",
        [$caseId]
    );

    $assignedName = '—';
    if (!empty($case['assigned_admin_id'])) {
        $admin = Database::fetch('SELECT name, first_name, last_name, email FROM users WHERE id = ?', [(int) $case['assigned_admin_id']]);
        if ($admin) {
            $assignedName = userFullName($admin);
        }
    }

    $lines = [
        '**Case summary — ' . ($case['case_number'] ?? 'Case') . '**',
        '',
        '• **Title:** ' . ($case['title'] ?? '—'),
        '• **Client:** ' . $client,
        '• **Status:** *' . $status . '*',
        '• **Service:** ' . ($case['service_type'] ?? '—'),
        '• **Assigned:** ' . $assignedName,
        '• **Documents:** ' . $docCount,
        '• **Pending invoices:** ' . $pendingInvoices,
    ];

    if (!empty($case['deadline'])) {
        $lines[] = '• **Deadline:** ' . formatDate($case['deadline']);
    }

    if ($nextAppt) {
        $when = !empty($nextAppt['start_time']) ? formatDateTime($nextAppt['start_time']) : 'TBD';
        $lines[] = '• **Next appointment:** ' . ($nextAppt['title'] ?? 'Appointment') . ' — ' . $when;
    }

    if (!empty($case['client_instructions'])) {
        $lines[] = '';
        $lines[] = '**Client instructions (excerpt):**';
        $lines[] = mb_strimwidth((string) $case['client_instructions'], 0, 280, '…');
    } elseif (!empty($case['description'])) {
        $lines[] = '';
        $lines[] = '**Notes:** ' . mb_strimwidth((string) $case['description'], 0, 200, '…');
    }

    chatbotSetLastEntity('case', $caseId, (string) ($case['case_number'] ?? 'Case'));
    $_SESSION['chatbot_last_topic'] = 'case_' . $caseId;

    $lines[] = '';
    $lines[] = 'Ask **what\'s missing on this case** for a checklist, or **draft client instructions** for a template.';
    $lines[] = chatbotAdminLink('pages/case-view.php?id=' . $caseId, 'Open case');

    return implode("\n", $lines);
}

function chatbotFormatCaseMissingItems(array $case): string
{
    $caseId = (int) ($case['id'] ?? 0);
    $missing = [];
    $ok = [];

    if (empty($case['assigned_admin_id'])) {
        $missing[] = 'No **assigned staff** — assign someone on the case page.';
    } else {
        $ok[] = 'Staff assigned';
    }

    if (trim((string) ($case['client_instructions'] ?? '')) === '') {
        $missing[] = 'No **client instructions** — add what the client should bring or do.';
    } else {
        $ok[] = 'Client instructions set';
    }

    $docCount = (int) (Database::fetch(
        'SELECT COUNT(*) AS c FROM documents WHERE case_id = ?',
        [$caseId]
    )['c'] ?? 0);
    if ($docCount === 0) {
        $missing[] = 'No **documents** uploaded yet.';
    } else {
        $ok[] = $docCount . ' document(s) on file';
    }

    $invoiceCol = invoiceStatusColumn();
    $overdue = (int) (Database::fetch(
        "SELECT COUNT(*) AS c FROM invoices WHERE case_id = ? AND {$invoiceCol} = 'overdue'",
        [$caseId]
    )['c'] ?? 0);
    if ($overdue > 0) {
        $missing[] = '**' . $overdue . ' overdue invoice(s)** — follow up on payment.';
    }

    $startSql = appointmentStartSql('a');
    $hasFutureAppt = (bool) Database::fetch(
        "SELECT a.id FROM appointments a
         WHERE a.case_id = ? AND a.status IN ('scheduled','confirmed','rescheduled')
           AND {$startSql} >= NOW() LIMIT 1",
        [$caseId]
    );
    if (!$hasFutureAppt && in_array($case['status'] ?? '', ['pending', 'in_progress', 'waiting_for_client'], true)) {
        $missing[] = 'No **upcoming appointment** scheduled.';
    } elseif ($hasFutureAppt) {
        $ok[] = 'Upcoming appointment booked';
    }

    if (($case['status'] ?? '') === 'waiting_for_client' && trim((string) ($case['client_instructions'] ?? '')) === '') {
        $missing[] = 'Status is **waiting for client** but instructions are empty.';
    }

    $lines = [
        '**Missing / to-do — ' . ($case['case_number'] ?? 'Case') . '** (' . clientFullName($case) . ')',
        '',
    ];

    if ($missing !== []) {
        $lines[] = '**Needs attention:**';
        foreach ($missing as $item) {
            $lines[] = '• ' . $item;
        }
    } else {
        $lines[] = '**Looks complete** for routine workflow — nothing critical flagged.';
    }

    if ($ok !== []) {
        $lines[] = '';
        $lines[] = '**In place:** ' . implode(' · ', $ok);
    }

    chatbotSetLastEntity('case', $caseId, (string) ($case['case_number'] ?? 'Case'));

    $lines[] = '';
    $lines[] = chatbotAdminLink('pages/case-view.php?id=' . $caseId, 'Open case');

    return implode("\n", $lines);
}

function chatbotDraftCaseInstructions(array $case, string $message): string
{
    $brand = companyBrandName();
    $client = clientFullName($case);
    $caseNo = (string) ($case['case_number'] ?? 'your matter');
    $service = (string) ($case['service_type'] ?? 'notarial services');

    $body = chatbotTemplateDraftContent($message);
    if (str_contains($body, 'Draft')) {
        $body = "**Client instructions draft** for **{$caseNo}** — {$client}\n\n"
            . "Dear {$client},\n\n"
            . "Thank you for engaging **{$brand}** for {$service}.\n\n"
            . "**Please bring:**\n"
            . "• Valid government-issued photo ID (passport or driving licence)\n"
            . "• Original document(s) to be notarized — **unsigned** until your appointment\n"
            . "• [Any additional documents specific to this matter]\n\n"
            . "**Appointment:** [Date and time — confirm in portal]\n"
            . "**Location:** [Office address or video link]\n"
            . "**Fees:** [Quote / invoice reference if applicable]\n\n"
            . "Sign in to the **client portal** to upload documents and view updates.\n\n"
            . "Kind regards,\n{$brand}";
    }

    chatbotSetLastEntity('case', (int) ($case['id'] ?? 0), $caseNo);
    chatbotRememberDraft($body);

    return $body . "\n\n_Say **save draft to " . $caseNo . '** to apply as client instructions, or copy from the case page._';
}
