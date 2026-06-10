<?php

declare(strict_types=1);

function chatbotTryActionFlow(string $message): ?string
{
    $confirmed = chatbotTryConfirmPendingAction($message);
    if ($confirmed !== null) {
        return $confirmed;
    }

    return chatbotTryStartPendingAction($message);
}

function chatbotTryConfirmPendingAction(string $message): ?string
{
    $pending = $_SESSION['chatbot_pending_action'] ?? null;
    if (!is_array($pending) || empty($pending['type'])) {
        return null;
    }

    $normalized = strtolower(trim($message));
    if (preg_match('/^(yes|yeah|yep|confirm|ok|okay|do it|go ahead|proceed)$/', $normalized)) {
        unset($_SESSION['chatbot_pending_action']);

        return chatbotExecuteAction($pending);
    }

    if (preg_match('/^(no|nope|cancel|stop|nevermind|never mind)$/', $normalized)) {
        unset($_SESSION['chatbot_pending_action']);

        return 'Action cancelled. Nothing was changed.';
    }

    return 'Reply **yes** to confirm or **no** to cancel: ' . ($pending['summary'] ?? 'this action');
}

function chatbotTryStartPendingAction(string $message): ?string
{
    if (!chatbotCanExecuteActions()) {
        if (chatbotIsReadOnly() && preg_match('/\b(assign|schedule|book|mark|create|update|set)\b/i', $message)) {
            return chatbotReadOnlyNotice();
        }

        return null;
    }

    if (!chatbotLooksLikeActionCommand($message)) {
        return null;
    }

    $normalized = strtolower(trim($message));

    if (preg_match('/\bassign\b.*\b(case|matter)\b.*\b(me|myself)\b/i', $message)
        || preg_match('/\bassign\b.*\b(me|myself)\b.*\b(case|matter)\b/i', $message)) {
        return chatbotPrepareAssignCaseAction($message);
    }

    if (preg_match('/\b(cancel)\b.*\bappointment\b/i', $message)) {
        return chatbotPrepareCancelAppointmentAction($message);
    }

    if (preg_match('/\b(reschedule|move|postpone)\b.*\bappointment\b/i', $message)) {
        return chatbotPrepareRescheduleAppointmentAction($message);
    }

    if (preg_match('/\b(schedule|book|create)\b.*\bappointment\b/i', $message)) {
        return chatbotPrepareScheduleAppointmentAction($message);
    }

    if (preg_match('/\b(save|apply)\b.*\b(draft|instructions?)\b/i', $message)
        || preg_match('/\bsave\b.*\bto\b.*\bcase\b/i', $message)) {
        return chatbotPrepareApplyDraftAction($message);
    }

    if (preg_match('/\b(set|update|change|mark)\b.*\b(case|matter|status)\b/i', $message)
        || preg_match('/\b(case|matter)\b.*\bstatus\b/i', $message)) {
        return chatbotPrepareUpdateCaseStatusAction($message);
    }

    if (preg_match('/\b(record payment|log payment|mark\b.*\binvoice\b.*\bpaid)\b/i', $message)) {
        return chatbotPrepareRecordPaymentAction($message);
    }

    return null;
}

function chatbotPrepareAssignCaseAction(string $message): ?string
{
    $case = chatbotResolveCaseFromMessage($message);
    if ($case === null) {
        $entity = chatbotGetLastEntity();
        if (($entity['type'] ?? '') === 'case') {
            $case = chatbotFetchCaseById((int) ($entity['id'] ?? 0));
        }
    }

    if ($case === null) {
        return 'Which case? Use a case number, e.g. **assign CASE-2026-0001 to me**.';
    }

    if (!chatbotUserCanAccessCaseId((int) $case['id'])) {
        return 'You do not have access to that case.';
    }

    $caseNo = (string) ($case['case_number'] ?? 'Case');
    $userId = (int) Auth::id();

    if ((int) ($case['assigned_admin_id'] ?? 0) === $userId) {
        return "**{$caseNo}** is already assigned to you.";
    }

    $_SESSION['chatbot_pending_action'] = [
        'type'    => 'assign_case',
        'case_id' => (int) $case['id'],
        'summary' => "Assign **{$caseNo}** to you",
    ];

    return "Assign **{$caseNo}** — " . ($case['title'] ?? '') . ' — to **you**? Reply **yes** to confirm or **no** to cancel.';
}

function chatbotPrepareScheduleAppointmentAction(string $message): ?string
{
    $client = null;
    if (preg_match('/\b(?:for|with)\s+([a-z][a-z\s\'-]{1,50})/i', $message, $matches)) {
        $term = trim($matches[1]);
        $term = preg_replace('/\b(on|at|tomorrow|today|next|monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b.*$/i', '', $term);
        $term = trim((string) $term);
        $clients = findClientsForChatbot($term, 3);
        if (count($clients) === 1) {
            $client = $clients[0];
        } elseif (count($clients) > 1) {
            return 'Several clients match. Be more specific, e.g. **schedule appointment for Emily Chen on Tuesday 2pm**.';
        }
    }

    if ($client === null) {
        return 'Include a **client name** and **date/time**, e.g. *Schedule appointment for John Smith tomorrow at 2pm*.';
    }

    $timeText = $message;
    if (preg_match('/\b(on|at|for)\s+(.+)$/i', $message, $timeMatch)) {
        $timeText = $timeMatch[2];
    }

    $startsAt = normalizeDateTimeInput($timeText);
    if ($startsAt === '') {
        $startsAt = normalizeDateTimeInput('tomorrow 10:00');
    }

    if ($startsAt === '' || strtotime($startsAt) === false) {
        return 'I could not parse the date/time. Try **Tuesday 2pm** or **2026-06-15 14:00**.';
    }

    $title = 'Appointment';
    if (preg_match('/\bappointment\s+(?:for|with)\s+[^,]+?\s+(?:for|about|re:?)\s+(.+)$/i', $message, $titleMatch)) {
        $title = trim($titleMatch[1]);
    }

    $_SESSION['chatbot_pending_action'] = [
        'type'       => 'schedule_appointment',
        'client_id'  => (int) $client['id'],
        'client_name'=> clientFullName($client),
        'title'      => mb_strimwidth($title, 0, 200, '…'),
        'starts_at'  => $startsAt,
        'summary'    => 'Schedule **' . $title . '** for **' . clientFullName($client) . '** on ' . formatDateTime($startsAt),
    ];

    return '**Confirm appointment:**\n\n'
        . '• Client: **' . clientFullName($client) . "**\n"
        . '• Title: ' . $title . "\n"
        . '• Start: **' . formatDateTime($startsAt) . "**\n\n"
        . 'Reply **yes** to create or **no** to cancel.';
}

function chatbotExecuteAction(array $pending): string
{
    $type = (string) ($pending['type'] ?? '');

    if ($type === 'assign_case') {
        $caseId = (int) ($pending['case_id'] ?? 0);
        if (!chatbotUserCanAccessCaseId($caseId)) {
            return 'You do not have access to that case.';
        }

        $userId = (int) Auth::id();
        Database::query(
            'UPDATE cases SET assigned_admin_id = ?, updated_at = NOW() WHERE id = ?',
            [$userId, $caseId]
        );

        $case = chatbotFetchCaseById($caseId);

        return 'Done — **' . ($case['case_number'] ?? 'Case') . '** is now assigned to you. '
            . chatbotAdminLink('pages/case-view.php?id=' . $caseId, 'Open case');
    }

    if ($type === 'schedule_appointment') {
        if (!Auth::canManage(RoleAccess::PERMISSION_APPOINTMENTS)) {
            return 'You do not have permission to create appointments.';
        }

        try {
            AppointmentService::create([
                'client_id'  => (int) ($pending['client_id'] ?? 0),
                'title'      => (string) ($pending['title'] ?? 'Appointment'),
                'starts_at'  => (string) ($pending['starts_at'] ?? ''),
                'status'     => 'scheduled',
            ], (int) Auth::id());

            return 'Appointment created for **' . ($pending['client_name'] ?? 'client') . '**. '
                . chatbotAdminLink('pages/appointments.php', 'Open calendar');
        } catch (Throwable $e) {
            return 'Could not create appointment: ' . $e->getMessage();
        }
    }

    if ($type === 'update_case_status') {
        $caseId = (int) ($pending['case_id'] ?? 0);
        $status = (string) ($pending['status'] ?? '');

        if (!chatbotUserCanAccessCaseId($caseId)) {
            return 'You do not have access to that case.';
        }

        if (!Auth::canManage(RoleAccess::PERMISSION_CASES)) {
            return 'You do not have permission to update cases.';
        }

        try {
            CaseService::updateStatus($caseId, $status, (int) Auth::id());
            $case = chatbotFetchCaseById($caseId);

            return 'Done — **' . ($case['case_number'] ?? 'Case') . '** is now **'
                . CaseService::statusLabel($status) . '**. '
                . chatbotAdminLink('pages/case-view.php?id=' . $caseId, 'Open case');
        } catch (Throwable $e) {
            return 'Could not update status: ' . $e->getMessage();
        }
    }

    if ($type === 'cancel_appointment') {
        if (!Auth::canManage(RoleAccess::PERMISSION_APPOINTMENTS)) {
            return 'You do not have permission to manage appointments.';
        }

        $appointmentId = (int) ($pending['appointment_id'] ?? 0);

        try {
            AppointmentService::update($appointmentId, [
                'title'     => (string) ($pending['title'] ?? 'Appointment'),
                'starts_at' => (string) ($pending['starts_at'] ?? ''),
                'ends_at'   => (string) ($pending['ends_at'] ?? ''),
                'status'    => 'cancelled',
            ]);

            return 'Appointment **cancelled** — **' . ($pending['client_name'] ?? 'client') . '**, '
                . ($pending['when'] ?? '') . '. '
                . chatbotAdminLink('pages/appointments.php', 'Open calendar');
        } catch (Throwable $e) {
            return 'Could not cancel appointment: ' . $e->getMessage();
        }
    }

    if ($type === 'reschedule_appointment') {
        if (!Auth::canManage(RoleAccess::PERMISSION_APPOINTMENTS)) {
            return 'You do not have permission to manage appointments.';
        }

        $appointmentId = (int) ($pending['appointment_id'] ?? 0);
        $startsAt      = (string) ($pending['new_starts_at'] ?? '');

        try {
            AppointmentService::update($appointmentId, [
                'title'     => (string) ($pending['title'] ?? 'Appointment'),
                'starts_at' => $startsAt,
                'ends_at'   => (string) ($pending['ends_at'] ?? ''),
                'status'    => (string) ($pending['current_status'] ?? 'scheduled'),
            ]);

            return 'Appointment **rescheduled** for **' . ($pending['client_name'] ?? 'client') . '** to **'
                . formatDateTime($startsAt) . '**. '
                . chatbotAdminLink('pages/appointments.php', 'Open calendar');
        } catch (Throwable $e) {
            return 'Could not reschedule appointment: ' . $e->getMessage();
        }
    }

    if ($type === 'record_payment') {
        if (!Auth::canManage(RoleAccess::PERMISSION_PAYMENTS)) {
            return 'You do not have permission to record payments.';
        }

        $invoiceId = (int) ($pending['invoice_id'] ?? 0);
        $result    = CaseService::recordPayment($invoiceId, [
            'amount'         => $pending['amount'] ?? null,
            'payment_method' => (string) ($pending['payment_method'] ?? 'bank_transfer'),
            'notes'          => 'Recorded via AI assistant',
        ], (int) Auth::id());

        if (empty($result['success'])) {
            return (string) ($result['message'] ?? 'Could not record payment.');
        }

        return 'Payment recorded for **' . ($pending['invoice_number'] ?? 'invoice') . '** — '
            . formatCurrency((float) ($pending['amount'] ?? 0)) . '. '
            . chatbotAdminLink('pages/payments.php', 'Open payments');
    }

    if ($type === 'apply_draft') {
        $caseId = (int) ($pending['case_id'] ?? 0);

        if (!chatbotUserCanAccessCaseId($caseId)) {
            return 'You do not have access to that case.';
        }

        if (!Auth::canManage(RoleAccess::PERMISSION_CASES)) {
            return 'You do not have permission to update cases.';
        }

        if (!Database::columnExists('cases', 'client_instructions')) {
            return 'Client instructions are not available on cases in this installation.';
        }

        $instructions = trim((string) ($pending['instructions'] ?? ''));
        if ($instructions === '') {
            return 'No draft text to save. Ask me to **draft client instructions** first.';
        }

        Database::query(
            'UPDATE cases SET client_instructions = ?, updated_at = NOW() WHERE id = ?',
            [$instructions, $caseId]
        );

        $case = chatbotFetchCaseById($caseId);
        unset($_SESSION['chatbot_last_draft']);

        return 'Saved client instructions on **' . ($case['case_number'] ?? 'Case') . '**. '
            . chatbotAdminLink('pages/case-view.php?id=' . $caseId, 'Open case');
    }

    return 'Unknown action.';
}

function chatbotPrepareUpdateCaseStatusAction(string $message): ?string
{
    if (!Auth::canManage(RoleAccess::PERMISSION_CASES)) {
        return 'You do not have permission to update case status.';
    }

    $case = chatbotResolveCaseFromMessage($message);
    if ($case === null) {
        $entity = chatbotGetLastEntity();
        if (($entity['type'] ?? '') === 'case') {
            $case = chatbotFetchCaseById((int) ($entity['id'] ?? 0));
        }
    }

    if ($case === null) {
        return 'Which case? Example: **set CASE-2026-0001 status to in progress**.';
    }

    if (!chatbotUserCanAccessCaseId((int) $case['id'])) {
        return 'You do not have access to that case.';
    }

    $status = chatbotParseCaseStatus($message);
    if ($status === null) {
        $allowed = CaseService::getAllowedStatuses((string) ($case['status'] ?? 'pending'));
        $labels  = array_map(static fn(string $s): string => CaseService::statusLabel($s), $allowed);

        return 'What status? For **' . ($case['case_number'] ?? 'this case') . '** you can use: '
            . implode(', ', $labels) . '.';
    }

    if ($status === ($case['status'] ?? '')) {
        return '**' . ($case['case_number'] ?? 'Case') . '** is already **' . CaseService::statusLabel($status) . '**.';
    }

    if (!CaseService::canTransitionStatus((string) ($case['status'] ?? ''), $status)) {
        return 'Cannot change from **' . CaseService::statusLabel((string) $case['status'])
            . '** to **' . CaseService::statusLabel($status) . '**.';
    }

    $caseNo = (string) ($case['case_number'] ?? 'Case');

    $_SESSION['chatbot_pending_action'] = [
        'type'    => 'update_case_status',
        'case_id' => (int) $case['id'],
        'status'  => $status,
        'summary' => "Set **{$caseNo}** to **" . CaseService::statusLabel($status) . '**',
    ];

    return "Set **{$caseNo}** status to **" . CaseService::statusLabel($status) . '**? Reply **yes** to confirm or **no** to cancel.';
}

function chatbotPrepareRecordPaymentAction(string $message): ?string
{
    if (!Auth::canManage(RoleAccess::PERMISSION_PAYMENTS)) {
        return 'You do not have permission to record payments.';
    }

    $invoice = chatbotResolveInvoiceFromMessage($message);
    if ($invoice === null) {
        return 'Which invoice? Example: **record payment for INV-2026-0001** or **mark invoice INV-2026-0001 paid**.';
    }

    $remaining = CaseService::getInvoiceRemainingBalance($invoice);
    if ($remaining <= 0) {
        return '**' . ($invoice['invoice_number'] ?? 'Invoice') . '** is already fully paid.';
    }

    $amount = $remaining;
    if (preg_match('/\b(?:£|\$|€)?\s*([\d,]+(?:\.\d{1,2})?)\b/', $message, $matches)) {
        $parsed = (float) str_replace(',', '', $matches[1]);
        if ($parsed > 0 && $parsed <= $remaining + 0.009) {
            $amount = $parsed;
        }
    }

    $method = 'bank_transfer';
    if (preg_match('/\b(cash|card|stripe|cheque|check|bank)\b/i', $message, $methodMatch)) {
        $method = strtolower($methodMatch[1]);
        if (in_array($method, ['check'], true)) {
            $method = 'cheque';
        }
        if ($method === 'bank') {
            $method = 'bank_transfer';
        }
    }

    $invoiceNo = (string) ($invoice['invoice_number'] ?? 'Invoice');

    $_SESSION['chatbot_pending_action'] = [
        'type'           => 'record_payment',
        'invoice_id'     => (int) $invoice['id'],
        'invoice_number' => $invoiceNo,
        'amount'         => $amount,
        'payment_method' => $method,
        'summary'        => 'Record **' . formatCurrency($amount) . '** payment for **' . $invoiceNo . '**',
    ];

    return 'Record payment of **' . formatCurrency($amount) . '** for **' . $invoiceNo . '** (' . $method . ')? '
        . 'Reply **yes** to confirm or **no** to cancel.';
}

function chatbotPrepareCancelAppointmentAction(string $message): ?string
{
    if (!Auth::canManage(RoleAccess::PERMISSION_APPOINTMENTS)) {
        return 'You do not have permission to cancel appointments.';
    }

    $appointment = chatbotResolveAppointmentFromMessage($message);
    if ($appointment === null) {
        return 'Which appointment? Example: **cancel appointment for John Smith** or **cancel appointment #12**.';
    }

    if (normalizeAppointmentStatus($appointment['status'] ?? null) === 'cancelled') {
        return 'That appointment is already **cancelled**.';
    }

    $clientName = clientFullName($appointment);
    $when       = formatDateTime(appointmentStart($appointment) ?? '');

    $_SESSION['chatbot_pending_action'] = [
        'type'           => 'cancel_appointment',
        'appointment_id' => (int) $appointment['id'],
        'title'          => (string) ($appointment['title'] ?? 'Appointment'),
        'starts_at'      => (string) (appointmentStart($appointment) ?? ''),
        'ends_at'        => (string) (appointmentEnd($appointment) ?? ''),
        'client_name'    => $clientName,
        'when'           => $when,
        'summary'        => 'Cancel appointment for **' . $clientName . '** (' . $when . ')',
    ];

    return 'Cancel appointment for **' . $clientName . '** — **' . ($appointment['title'] ?? 'Appointment') . '** on **'
        . $when . '**? Reply **yes** to confirm or **no** to cancel.';
}

function chatbotPrepareRescheduleAppointmentAction(string $message): ?string
{
    if (!Auth::canManage(RoleAccess::PERMISSION_APPOINTMENTS)) {
        return 'You do not have permission to reschedule appointments.';
    }

    $appointment = chatbotResolveAppointmentFromMessage($message);
    if ($appointment === null) {
        return 'Which appointment? Example: **reschedule John Smith appointment to Friday 3pm**.';
    }

    $timeText = $message;
    if (preg_match('/\bto\s+(.+)$/i', $message, $timeMatch)) {
        $timeText = $timeMatch[1];
    } elseif (preg_match('/\bon\s+(.+)$/i', $message, $timeMatch)) {
        $timeText = $timeMatch[1];
    }

    $startsAt = normalizeDateTimeInput($timeText);
    if ($startsAt === '' || strtotime($startsAt) === false) {
        return 'When should it be rescheduled? Example: **reschedule appointment for John to Tuesday 2pm**.';
    }

    $clientName = clientFullName($appointment);
    $current    = formatDateTime(appointmentStart($appointment) ?? '');

    $_SESSION['chatbot_pending_action'] = [
        'type'            => 'reschedule_appointment',
        'appointment_id'  => (int) $appointment['id'],
        'title'           => (string) ($appointment['title'] ?? 'Appointment'),
        'new_starts_at'   => $startsAt,
        'ends_at'         => (string) (appointmentEnd($appointment) ?? ''),
        'current_status'  => normalizeAppointmentStatus($appointment['status'] ?? null),
        'client_name'     => $clientName,
        'summary'         => 'Reschedule **' . $clientName . '** from ' . $current . ' to ' . formatDateTime($startsAt),
    ];

    return 'Reschedule **' . ($appointment['title'] ?? 'Appointment') . '** for **' . $clientName . "**\n\n"
        . '• From: **' . $current . "**\n"
        . '• To: **' . formatDateTime($startsAt) . "**\n\n"
        . 'Reply **yes** to confirm or **no** to cancel.';
}

function chatbotPrepareApplyDraftAction(string $message): ?string
{
    if (!Auth::canManage(RoleAccess::PERMISSION_CASES)) {
        return 'You do not have permission to update case instructions.';
    }

    $draft = chatbotGetLastDraft();
    if ($draft === null) {
        return 'No draft in this chat yet. Ask me to **draft client instructions** for a case first, then say **save draft to CASE-2026-0001**.';
    }

    $case = chatbotResolveCaseFromMessage($message);
    if ($case === null) {
        $entity = chatbotGetLastEntity();
        if (($entity['type'] ?? '') === 'case') {
            $case = chatbotFetchCaseById((int) ($entity['id'] ?? 0));
        }
    }

    if ($case === null) {
        return 'Which case should I save the draft to? Example: **save draft to CASE-2026-0001 as client instructions**.';
    }

    if (!chatbotUserCanAccessCaseId((int) $case['id'])) {
        return 'You do not have access to that case.';
    }

    $caseNo = (string) ($case['case_number'] ?? 'Case');
    $preview = mb_strimwidth($draft, 0, 200, '…');

    $_SESSION['chatbot_pending_action'] = [
        'type'         => 'apply_draft',
        'case_id'      => (int) $case['id'],
        'instructions' => $draft,
        'summary'      => 'Save client instructions on **' . $caseNo . '**',
    ];

    return "Save the last draft as **client instructions** on **{$caseNo}**?\n\n"
        . "_Preview:_ {$preview}\n\n"
        . 'Reply **yes** to save or **no** to cancel.';
}

function chatbotParseCaseStatus(string $message): ?string
{
    $normalized = strtolower(trim($message));

    $phrases = [
        'waiting for client' => 'waiting_for_client',
        'waiting_for_client' => 'waiting_for_client',
        'in progress'        => 'in_progress',
        'in_progress'        => 'in_progress',
        'completed'          => 'completed',
        'complete'           => 'completed',
        'closed'             => 'closed',
        'close'              => 'closed',
        'pending'            => 'pending',
    ];

    foreach ($phrases as $phrase => $status) {
        $pattern = '/\b' . str_replace(' ', '[-_ ]', preg_quote($phrase, '/')) . '\b/';
        if (preg_match($pattern, $normalized)) {
            return $status;
        }
    }

    if (preg_match('/\b(?:to|as)\s+([a-z][a-z_ ]{2,30})\b/', $normalized, $matches)) {
        $candidate = str_replace(' ', '_', trim($matches[1]));
        if (CaseService::isValidStatus($candidate)) {
            return $candidate;
        }
    }

    return null;
}

function chatbotLooksLikeActionCommand(string $message): bool
{
    $trimmed = trim($message);
    if ($trimmed === '') {
        return false;
    }

    if (preg_match('/^(how|what|where|why|when|who|can|should|is|are|do|does|could|would)\b/i', $trimmed)) {
        return false;
    }

    if (str_ends_with($trimmed, '?')) {
        return false;
    }

    return true;
}

function chatbotResolveInvoiceFromMessage(string $message): ?array
{
    if (preg_match('/\b(INV[- ]?\d{4}[- ]?\d+)\b/i', $message, $matches)) {
        $search = strtoupper(preg_replace('/[^A-Z0-9-]/', '-', trim($matches[1])));
        $search = preg_replace('/-+/', '-', trim($search, '-'));

        $where  = ["UPPER(REPLACE(i.invoice_number, ' ', '-')) LIKE ?"];
        $params = ['%' . $search . '%'];
        if (TenantService::isEnabled()) {
            $where[] = 'i.company_id = ?';
            $params[] = TenantService::id();
        }

        return Database::fetch(
            'SELECT i.* FROM invoices i WHERE ' . implode(' AND ', $where) . ' LIMIT 1',
            $params
        ) ?: null;
    }

    if (preg_match('/\binvoice\s*#?(\d+)\b/i', $message, $matches)) {
        $id = (int) $matches[1];
        $where  = ['i.id = ?'];
        $params = [$id];
        if (TenantService::isEnabled()) {
            $where[] = 'i.company_id = ?';
            $params[] = TenantService::id();
        }

        return Database::fetch(
            'SELECT i.* FROM invoices i WHERE ' . implode(' AND ', $where) . ' LIMIT 1',
            $params
        ) ?: null;
    }

    return null;
}

function chatbotResolveAppointmentFromMessage(string $message): ?array
{
    if (preg_match('/\bappointment\s*#?(\d+)\b/i', $message, $matches)) {
        $row = AppointmentService::getById((int) $matches[1]);

        return is_array($row) ? $row : null;
    }

    $client = null;
    if (preg_match('/\b(?:for|with)\s+([a-z][a-z\s\'-]{1,50})/i', $message, $clientMatch)) {
        $term = trim($clientMatch[1]);
        $term = preg_replace('/\b(on|at|to|tomorrow|today|next|monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b.*$/i', '', $term);
        $term = trim((string) $term);
        $clients = findClientsForChatbot($term, 3);
        if (count($clients) === 1) {
            $client = $clients[0];
        }
    }

    if ($client === null) {
        return null;
    }

    $startSql = appointmentStartSql('a');
    $where    = ['a.client_id = ?', "a.status NOT IN ('cancelled', 'completed')"];
    $params   = [(int) $client['id']];

    if (TenantService::isEnabled()) {
        $where[] = 'a.company_id = ?';
        $params[] = TenantService::id();
    }

    $rows = Database::fetchAll(
        "SELECT a.*, cl.first_name, cl.last_name, cl.company_name
         FROM appointments a
         JOIN clients cl ON cl.id = a.client_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY {$startSql} ASC
         LIMIT 5",
        $params
    );

    if ($rows === []) {
        return null;
    }

    if (count($rows) === 1) {
        return $rows[0];
    }

    foreach ($rows as $row) {
        $start = appointmentStart($row);
        if ($start === null) {
            continue;
        }
        if (preg_match('/\b(today|tomorrow)\b/i', $message)) {
            $day = strtolower(date('Y-m-d', strtotime('tomorrow')));
            if (preg_match('/\btoday\b/i', $message)) {
                $day = date('Y-m-d');
            }
            if (str_starts_with($start, $day)) {
                return $row;
            }
        }
    }

    return $rows[0];
}
