<?php

declare(strict_types=1);

function chatbotTryActionFlow(string $message): ?string
{
    $confirmed = chatbotTryConfirmPendingAction($message);
    if ($confirmed !== null) {
        return $confirmed;
    }

    $scheduleFollowUp = chatbotTryScheduleAppointmentFollowUp($message);
    if ($scheduleFollowUp !== null) {
        return $scheduleFollowUp;
    }

    return chatbotTryStartPendingAction($message);
}

function chatbotTryConfirmPendingAction(string $message): ?string
{
    $pending = $_SESSION['chatbot_pending_action'] ?? null;
    if (!is_array($pending) || empty($pending['type'])) {
        return null;
    }

    if (chatbotIsDraftRequest($message)) {
        unset($_SESSION['chatbot_pending_action']);

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

    $timeAdjusted = chatbotTryAdjustPendingAppointmentTimes($message, $pending);
    if ($timeAdjusted !== null) {
        return $timeAdjusted;
    }

    unset($_SESSION['chatbot_pending_action']);

    return null;
}

function chatbotTryStartPendingAction(string $message): ?string
{
    if (chatbotIsDraftRequest($message)) {
        return null;
    }

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

    if (preg_match('/\bmark\b.*\b(all\s+)?notifications?\b.*\b(read|seen)\b/i', $message)
        || preg_match('/\bmark\s+all\s+notifications?\s+as\s+read\b/i', $message)) {
        return chatbotPrepareMarkNotificationsReadAction($message);
    }

    if (preg_match('/\b(add|create|post)\b.*\b(note|notes)\b/i', $message)) {
        return chatbotPrepareAddCaseNoteAction($message);
    }

    if (preg_match('/\b(set|update|change)\b.*\bpriority\b/i', $message)
        || preg_match('/\bpriority\b.*\b(to|as)\b/i', $message)) {
        return chatbotPrepareUpdateCasePriorityAction($message);
    }

    if (preg_match('/\b(set|update|change)\b.*\bdeadline\b/i', $message)
        || preg_match('/\bdeadline\b.*\b(to|as|for)\b/i', $message)) {
        return chatbotPrepareUpdateCaseDeadlineAction($message);
    }

    if (preg_match('/\bassign\b.*\b(case|matter)\b/i', $message)) {
        return chatbotPrepareAssignCaseAction($message);
    }

    if (preg_match('/\b(confirm)\b.*\bappointment\b/i', $message)) {
        return chatbotPrepareConfirmAppointmentAction($message);
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

    if ((preg_match('/\b(set|update|change|mark)\b.*\b(case|matter|status)\b/i', $message)
            || preg_match('/\b(case|matter)\b.*\bstatus\b/i', $message))
        && !preg_match('/\b(priority|deadline|note|notes)\b/i', $message)) {
        return chatbotPrepareUpdateCaseStatusAction($message);
    }

    if (preg_match('/\b(record payment|log payment|mark\b.*\binvoice\b.*\bpaid)\b/i', $message)) {
        return chatbotPrepareRecordPaymentAction($message);
    }

    return null;
}

function chatbotPrepareAssignCaseAction(string $message): ?string
{
    if (!Auth::canManage(RoleAccess::PERMISSION_CASES)) {
        return 'You do not have permission to assign cases.';
    }

    $case = chatbotResolveCaseForAction($message);
    if ($case === null) {
        return 'Which case? Example: **assign CASE-2026-0001 to Sarah** or **assign CASE-2026-0001 to me**.';
    }

    $caseNo     = (string) ($case['case_number'] ?? 'Case');
    $assignToMe = preg_match('/\bassign\b.*\b(me|myself)\b/i', $message)
        || preg_match('/\bto\s+(me|myself)\b/i', $message);

    if ($assignToMe) {
        $userId   = (int) Auth::id();
        $userName = 'you';

        if ((int) ($case['assigned_admin_id'] ?? 0) === $userId) {
            return "**{$caseNo}** is already assigned to you.";
        }
    } else {
        $admin = chatbotResolveAdminFromMessage($message);
        if ($admin === null) {
            return 'Assign to whom? Example: **assign CASE-2026-0001 to Sarah Mitchell** or **… to me**.';
        }

        $userId   = (int) $admin['id'];
        $userName = trim((string) ($admin['name'] ?? 'staff'));

        if ((int) ($case['assigned_admin_id'] ?? 0) === $userId) {
            return "**{$caseNo}** is already assigned to **{$userName}**.";
        }
    }

    $_SESSION['chatbot_pending_action'] = [
        'type'          => 'assign_case',
        'case_id'       => (int) $case['id'],
        'assignee_id'   => $userId,
        'assignee_name' => $userName,
        'summary'       => "Assign **{$caseNo}** to **{$userName}**",
    ];

    return "Assign **{$caseNo}** — " . ($case['title'] ?? '') . " — to **{$userName}**? Reply **yes** to confirm or **no** to cancel.";
}

function chatbotPrepareScheduleAppointmentAction(string $message): ?string
{
    $term = chatbotExtractClientNameFromScheduleMessage($message);
    if ($term === '') {
        return 'Include a **client name** and **date/time**, e.g. *Schedule appointment for John Smith on 21 June at 2pm*.';
    }

    $clients = findClientsForChatbot($term, 3);
    if (count($clients) > 1) {
        return 'Several clients match **“' . $term . '”**. Be more specific, e.g. **schedule appointment for Emily Chen on 21 June at 2pm**.';
    }
    if ($clients === []) {
        return 'I could not find a client matching **“' . $term . '”**. Check the name and try again.';
    }

    $client = $clients[0];

    $title = 'Appointment';
    if (preg_match('/\bappointment\s+(?:for|with)\s+[^,]+?\s+(?:for|about|re:?)\s+(.+)$/i', $message, $titleMatch)) {
        $title = trim($titleMatch[1]);
    }

    $clientName = clientFullName($client);

    $timeText = chatbotExtractDateTimeTextFromScheduleMessage($message);
    $startsAt = parseFlexibleDateTime($timeText);

    if ($startsAt === '' || strtotime($startsAt) === false) {
        $_SESSION['chatbot_schedule_pending'] = [
            'client_id'   => (int) $client['id'],
            'client_name' => $clientName,
            'title'       => mb_strimwidth($title, 0, 200, '…'),
        ];

        return 'When should the appointment with **' . $clientName . '** be? '
            . 'Reply with a date and time (e.g. **21 June at 5pm**, **Tuesday 2pm**, or **2026-06-21 17:00**).';
    }

    return chatbotFinalizeScheduleAppointment(
        (int) $client['id'],
        $clientName,
        $title,
        $startsAt
    );
}

function chatbotTryScheduleAppointmentFollowUp(string $message): ?string
{
    $pending = $_SESSION['chatbot_schedule_pending'] ?? null;
    if (!is_array($pending) || empty($pending['client_id'])) {
        return null;
    }

    $normalized = strtolower(trim($message));
    if (preg_match('/^(no|nope|cancel|stop|nevermind|never mind)$/i', $normalized)) {
        unset($_SESSION['chatbot_schedule_pending']);

        return 'Scheduling cancelled.';
    }

    if (!chatbotCanExecuteActions() || !Auth::canManage(RoleAccess::PERMISSION_APPOINTMENTS)) {
        return null;
    }

    $startsAt = parseFlexibleDateTime($message);
    if ($startsAt === '' || strtotime($startsAt) === false) {
        if (chatbotMessageLooksLikeDateOrTime($message)) {
            return 'I could not parse that date/time. Try **21 June at 5pm**, **Tuesday 2pm**, or **2026-06-21 17:00**.';
        }

        return null;
    }

    unset($_SESSION['chatbot_schedule_pending']);

    return chatbotFinalizeScheduleAppointment(
        (int) $pending['client_id'],
        (string) ($pending['client_name'] ?? 'client'),
        (string) ($pending['title'] ?? 'Appointment'),
        $startsAt
    );
}

function chatbotFinalizeScheduleAppointment(int $clientId, string $clientName, string $title, string $startsAt, string $endsAt = ''): string
{
    $_SESSION['chatbot_pending_action'] = [
        'type'        => 'schedule_appointment',
        'client_id'   => $clientId,
        'client_name' => $clientName,
        'title'       => mb_strimwidth($title, 0, 200, '…'),
        'starts_at'   => $startsAt,
        'ends_at'     => $endsAt,
        'summary'     => chatbotBuildAppointmentPendingSummary($clientName, $title, $startsAt, $endsAt),
    ];

    return chatbotFormatAppointmentConfirmationReply($_SESSION['chatbot_pending_action']);
}

function chatbotTryAdjustPendingAppointmentTimes(string $message, array $pending): ?string
{
    $type = (string) ($pending['type'] ?? '');
    if (!in_array($type, ['schedule_appointment', 'reschedule_appointment'], true)) {
        return null;
    }

    $existingStarts = $type === 'reschedule_appointment'
        ? (string) ($pending['new_starts_at'] ?? '')
        : (string) ($pending['starts_at'] ?? '');

    if ($existingStarts === '') {
        return null;
    }

    $adjustment = parseAppointmentTimeAdjustment($message, $existingStarts);
    if ($adjustment === null) {
        return null;
    }

    if ($type === 'schedule_appointment') {
        $pending['starts_at'] = $adjustment['starts_at'];
        $pending['ends_at']   = $adjustment['ends_at'];
        $pending['summary']   = chatbotBuildAppointmentPendingSummary(
            (string) ($pending['client_name'] ?? 'client'),
            (string) ($pending['title'] ?? 'Appointment'),
            $adjustment['starts_at'],
            $adjustment['ends_at']
        );
    } else {
        $pending['new_starts_at'] = $adjustment['starts_at'];
        $pending['ends_at']       = $adjustment['ends_at'];
        $pending['summary']       = 'Reschedule **' . ($pending['client_name'] ?? 'client') . '** to '
            . formatDateTime($adjustment['starts_at'])
            . (empty($adjustment['ends_at']) ? '' : ' until ' . formatDateTime($adjustment['ends_at']));
    }

    $_SESSION['chatbot_pending_action'] = $pending;

    return chatbotFormatAppointmentConfirmationReply($pending);
}

function chatbotBuildAppointmentPendingSummary(string $clientName, string $title, string $startsAt, string $endsAt = ''): string
{
    $summary = 'Schedule **' . $title . '** for **' . $clientName . '** on ' . formatDateTime($startsAt);
    if ($endsAt !== '') {
        $summary .= ' until ' . formatDateTime($endsAt);
    }

    return $summary;
}

function chatbotFormatAppointmentConfirmationReply(array $pending): string
{
    $type = (string) ($pending['type'] ?? '');

    if ($type === 'reschedule_appointment') {
        $startsAt   = (string) ($pending['new_starts_at'] ?? '');
        $clientName = (string) ($pending['client_name'] ?? 'client');
        $lines      = [
            'Reschedule **' . ($pending['title'] ?? 'Appointment') . '** for **' . $clientName . '**',
            '',
            '• Start: **' . formatDateTime($startsAt) . '**',
        ];
        if (!empty($pending['ends_at'])) {
            $lines[] = '• End: **' . formatDateTime((string) $pending['ends_at']) . '**';
        }
        $lines[] = '';
        $lines[] = 'Reply **yes** to confirm, **no** to cancel, or send a new time (e.g. **10:00 to 11:00**).';

        return implode("\n", $lines);
    }

    $startsAt   = (string) ($pending['starts_at'] ?? '');
    $clientName = (string) ($pending['client_name'] ?? 'client');
    $title      = (string) ($pending['title'] ?? 'Appointment');
    $lines      = [
        '**Confirm appointment:**',
        '',
        '• Client: **' . $clientName . '**',
        '• Title: ' . $title,
        '• Start: **' . formatDateTime($startsAt) . '**',
    ];
    if (!empty($pending['ends_at'])) {
        $lines[] = '• End: **' . formatDateTime((string) $pending['ends_at']) . '**';
    }
    $lines[] = '';
    $lines[] = 'Reply **yes** to create, **no** to cancel, or send a new time (e.g. **10:00 to 11:00**).';

    return implode("\n", $lines);
}

function chatbotResolveClientFromScheduleMessage(string $message): ?array
{
    $term = chatbotExtractClientNameFromScheduleMessage($message);
    if ($term === '') {
        return null;
    }

    $clients = findClientsForChatbot($term, 3);
    if (count($clients) === 1) {
        return $clients[0];
    }

    return null;
}

function chatbotExtractRescheduleDateTimeText(string $message): string
{
    if (preg_match('/\bto\s+(?:the\s+)?(.+)$/iu', $message, $matches)) {
        return trim($matches[1]);
    }

    if (preg_match('/\bon\s+(?:the\s+)?(.+)$/iu', $message, $matches)) {
        return trim($matches[1]);
    }

    return chatbotExtractDateTimeTextFromScheduleMessage($message);
}

function chatbotExtractClientNameFromScheduleMessage(string $message): string
{
    if (preg_match('/\b(?:for|with)\s+(.+?)\s+\bon\b/iu', $message, $matches)) {
        return trim($matches[1]);
    }

    if (preg_match('/\b(?:for|with)\s+(.+?)\s+\bat\b/iu', $message, $matches)) {
        $term = trim($matches[1]);
        if (!preg_match('/^\d{1,2}(:\d{2})?\s*(am|pm)?$/i', $term)) {
            return $term;
        }
    }

    if (preg_match('/\b(?:for|with)\s+([a-z][a-z\s\'-]{1,50})/iu', $message, $matches)) {
        $term = trim($matches[1]);
        $term = preg_replace(
            '/\s+\b(on|at|tomorrow|today|next|monday|tuesday|wednesday|thursday|friday|saturday|sunday|\d{4}-\d{2}-\d{2}|\d{1,2}(?:st|nd|rd|th)?\s+(?:jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec))\b.*$/iu',
            '',
            $term
        );

        return trim((string) $term);
    }

    return '';
}

function chatbotExtractDateTimeTextFromScheduleMessage(string $message): string
{
    if (preg_match('/\b(?:on|at)\s+(?:the\s+)?(.+)$/iu', $message, $matches)) {
        return trim($matches[1]);
    }

    if (preg_match('/\bto\s+(?:the\s+)?(.+)$/iu', $message, $matches)) {
        return trim($matches[1]);
    }

    if (preg_match('/\b\d{4}-\d{2}-\d{2}(?:[ T]\d{1,2}:\d{2}(?::\d{2})?)?\b/', $message, $matches)) {
        return $matches[0];
    }

    if (preg_match('/\b\d{1,2}(?:st|nd|rd|th)?\s+(?:of\s+)?(?:jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:t(?:ember)?)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)(?:\s+\d{4})?(?:\s+(?:at\s+)?\d{1,2}(?::\d{2})?(?:\s*(?:am|pm))?)?\b/iu', $message, $matches)) {
        return $matches[0];
    }

    if (preg_match('/\b(?:jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|jun(?:e)?|jul(?:y)?|aug(?:ust)?|sep(?:t(?:ember)?)?|oct(?:ober)?|nov(?:ember)?|dec(?:ember)?)\s+\d{1,2}(?:st|nd|rd|th)?(?:\s+\d{4})?(?:\s+(?:at\s+)?\d{1,2}(?::\d{2})?(?:\s*(?:am|pm))?)?\b/iu', $message, $matches)) {
        return $matches[0];
    }

    if (preg_match('/\b\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4}(?:\s+\d{1,2}(?::\d{2})?(?:\s*(?:am|pm))?)?\b/', $message, $matches)) {
        return $matches[0];
    }

    if (preg_match('/\b(?:tomorrow|today|next\s+(?:monday|tuesday|wednesday|thursday|friday|saturday|sunday)|monday|tuesday|wednesday|thursday|friday|saturday|sunday)(?:\s+(?:at\s+)?\d{1,2}(?::\d{2})?(?:\s*(?:am|pm))?)?\b/iu', $message, $matches)) {
        return $matches[0];
    }

    return trim($message);
}

function chatbotExecuteAction(array $pending): string
{
    $type = (string) ($pending['type'] ?? '');

    if ($type === 'assign_case') {
        $caseId = (int) ($pending['case_id'] ?? 0);
        if (!chatbotUserCanAccessCaseId($caseId)) {
            return 'You do not have access to that case.';
        }

        if (!Auth::canManage(RoleAccess::PERMISSION_CASES)) {
            return 'You do not have permission to assign cases.';
        }

        $userId   = (int) ($pending['assignee_id'] ?? Auth::id());
        $userName = (string) ($pending['assignee_name'] ?? 'you');

        Database::query(
            'UPDATE cases SET assigned_admin_id = ?, updated_at = NOW() WHERE id = ?',
            [$userId, $caseId]
        );

        $case = chatbotFetchCaseById($caseId);

        return 'Done — **' . ($case['case_number'] ?? 'Case') . '** is now assigned to **' . $userName . '**. '
            . chatbotAdminLink('pages/case-view.php?id=' . $caseId, 'Open case');
    }

    if ($type === 'add_case_note') {
        $caseId = (int) ($pending['case_id'] ?? 0);
        $note   = trim((string) ($pending['note'] ?? ''));

        if (!chatbotUserCanAccessCaseId($caseId) || $note === '') {
            return 'Could not add note.';
        }

        if (!Auth::canManage(RoleAccess::PERMISSION_CASES)) {
            return 'You do not have permission to add case notes.';
        }

        try {
            CaseService::addNote($caseId, (int) Auth::id(), $note, true);
            $case = chatbotFetchCaseById($caseId);

            return 'Note added to **' . ($case['case_number'] ?? 'Case') . '**. '
                . chatbotAdminLink('pages/case-view.php?id=' . $caseId . '#notes', 'Open notes');
        } catch (Throwable $e) {
            return 'Could not add note: ' . $e->getMessage();
        }
    }

    if ($type === 'update_case_priority') {
        $caseId   = (int) ($pending['case_id'] ?? 0);
        $priority = (string) ($pending['priority'] ?? '');

        if (!chatbotUserCanAccessCaseId($caseId) || !in_array($priority, ['low', 'medium', 'high', 'urgent'], true)) {
            return 'Could not update priority.';
        }

        if (!Auth::canManage(RoleAccess::PERMISSION_CASES)) {
            return 'You do not have permission to update cases.';
        }

        Database::query(
            'UPDATE cases SET priority = ?, updated_at = NOW() WHERE id = ?',
            [$priority, $caseId]
        );

        $case = chatbotFetchCaseById($caseId);

        return '**' . ($case['case_number'] ?? 'Case') . '** priority set to **' . ucfirst($priority) . '**. '
            . chatbotAdminLink('pages/case-view.php?id=' . $caseId, 'Open case');
    }

    if ($type === 'update_case_deadline') {
        $caseId   = (int) ($pending['case_id'] ?? 0);
        $deadline = (string) ($pending['deadline'] ?? '');

        if (!chatbotUserCanAccessCaseId($caseId) || $deadline === '') {
            return 'Could not update deadline.';
        }

        if (!Auth::canManage(RoleAccess::PERMISSION_CASES)) {
            return 'You do not have permission to update cases.';
        }

        Database::query(
            'UPDATE cases SET deadline = ?, updated_at = NOW() WHERE id = ?',
            [$deadline, $caseId]
        );

        $case = chatbotFetchCaseById($caseId);

        return '**' . ($case['case_number'] ?? 'Case') . '** deadline set to **' . formatDate($deadline) . '**. '
            . chatbotAdminLink('pages/case-view.php?id=' . $caseId, 'Open case');
    }

    if ($type === 'confirm_appointment') {
        if (!Auth::canManage(RoleAccess::PERMISSION_APPOINTMENTS)) {
            return 'You do not have permission to manage appointments.';
        }

        $appointmentId = (int) ($pending['appointment_id'] ?? 0);

        try {
            AppointmentService::update($appointmentId, [
                'title'     => (string) ($pending['title'] ?? 'Appointment'),
                'starts_at' => (string) ($pending['starts_at'] ?? ''),
                'ends_at'   => (string) ($pending['ends_at'] ?? ''),
                'status'    => 'confirmed',
            ]);

            return 'Appointment **confirmed** — **' . ($pending['client_name'] ?? 'client') . '**, '
                . ($pending['when'] ?? '') . '. '
                . chatbotAdminLink('pages/appointments.php', 'Open calendar');
        } catch (Throwable $e) {
            return 'Could not confirm appointment: ' . $e->getMessage();
        }
    }

    if ($type === 'mark_notifications_read') {
        if (!Auth::can(RoleAccess::PERMISSION_NOTIFICATIONS)) {
            return 'You do not have permission to manage notifications.';
        }

        $userId = (int) Auth::id();
        if ($userId <= 0) {
            return 'Could not mark notifications as read.';
        }

        markAllNotificationsAsRead($userId);

        return 'All notifications marked as read. '
            . chatbotAdminLink('pages/notifications.php', 'Open notifications');
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
                'ends_at'    => (string) ($pending['ends_at'] ?? ''),
                'status'     => 'scheduled',
            ], (int) Auth::id());

            unset($_SESSION['chatbot_schedule_pending']);

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

    $case = chatbotResolveCaseForAction($message);
    if ($case === null) {
        return 'Which case? Example: **set CASE-2026-0001 status to in progress**.';
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

    $timeText = chatbotExtractRescheduleDateTimeText($message);
    $startsAt = parseFlexibleDateTime($timeText);
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
        . 'Reply **yes** to confirm, **no** to cancel, or send a new time (e.g. **10:00 to 11:00**).';
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

function chatbotPrepareAddCaseNoteAction(string $message): ?string
{
    if (!Auth::canManage(RoleAccess::PERMISSION_CASES)) {
        return 'You do not have permission to add case notes.';
    }

    $case = chatbotResolveCaseForAction($message);
    if ($case === null) {
        return 'Which case? Example: **add note to CASE-2026-0001: Client called back**.';
    }

    $note = chatbotExtractNoteText($message);
    if ($note === null || $note === '') {
        return 'What should the note say? Example: **add note to CASE-2026-0001: Client called back**.';
    }

    $caseNo = (string) ($case['case_number'] ?? 'Case');
    $preview = mb_strimwidth($note, 0, 160, '…');

    $_SESSION['chatbot_pending_action'] = [
        'type'    => 'add_case_note',
        'case_id' => (int) $case['id'],
        'note'    => $note,
        'summary' => "Add note on **{$caseNo}**",
    ];

    return "Add this **internal note** to **{$caseNo}**?\n\n"
        . "> {$preview}\n\n"
        . 'Reply **yes** to confirm or **no** to cancel.';
}

function chatbotPrepareUpdateCasePriorityAction(string $message): ?string
{
    if (!Auth::canManage(RoleAccess::PERMISSION_CASES)) {
        return 'You do not have permission to update cases.';
    }

    $case = chatbotResolveCaseForAction($message);
    if ($case === null) {
        return 'Which case? Example: **set CASE-2026-0001 priority to high**.';
    }

    $priority = chatbotParseCasePriority($message);
    if ($priority === null) {
        return 'What priority? Use **low**, **medium**, **high**, or **urgent**.';
    }

    if ($priority === ($case['priority'] ?? '')) {
        return '**' . ($case['case_number'] ?? 'Case') . '** is already **' . ucfirst($priority) . '** priority.';
    }

    $caseNo = (string) ($case['case_number'] ?? 'Case');

    $_SESSION['chatbot_pending_action'] = [
        'type'     => 'update_case_priority',
        'case_id'  => (int) $case['id'],
        'priority' => $priority,
        'summary'  => "Set **{$caseNo}** priority to **" . ucfirst($priority) . '**',
    ];

    return "Set **{$caseNo}** priority to **" . ucfirst($priority) . '**? Reply **yes** to confirm or **no** to cancel.';
}

function chatbotPrepareUpdateCaseDeadlineAction(string $message): ?string
{
    if (!Auth::canManage(RoleAccess::PERMISSION_CASES)) {
        return 'You do not have permission to update cases.';
    }

    $case = chatbotResolveCaseForAction($message);
    if ($case === null) {
        return 'Which case? Example: **set CASE-2026-0001 deadline to 2026-06-30**.';
    }

    $deadline = chatbotParseCaseDeadline($message);
    if ($deadline === null) {
        return 'What deadline date? Example: **set CASE-2026-0001 deadline to 30 June 2026** or **2026-06-30**.';
    }

    $caseNo = (string) ($case['case_number'] ?? 'Case');

    $_SESSION['chatbot_pending_action'] = [
        'type'     => 'update_case_deadline',
        'case_id'  => (int) $case['id'],
        'deadline' => $deadline,
        'summary'  => 'Set **' . $caseNo . '** deadline to **' . formatDate($deadline) . '**',
    ];

    return 'Set **' . $caseNo . '** deadline to **' . formatDate($deadline) . '**? Reply **yes** to confirm or **no** to cancel.';
}

function chatbotPrepareConfirmAppointmentAction(string $message): ?string
{
    if (!Auth::canManage(RoleAccess::PERMISSION_APPOINTMENTS)) {
        return 'You do not have permission to manage appointments.';
    }

    $appointment = chatbotResolveAppointmentFromMessage($message);
    if ($appointment === null) {
        return 'Which appointment? Example: **confirm appointment for John Smith**.';
    }

    $status = normalizeAppointmentStatus($appointment['status'] ?? null);
    if ($status === 'confirmed') {
        return 'That appointment is already **confirmed**.';
    }
    if (in_array($status, ['cancelled', 'completed'], true)) {
        return 'That appointment is **' . $status . '** and cannot be confirmed.';
    }

    $clientName = clientFullName($appointment);
    $when       = formatDateTime(appointmentStart($appointment) ?? '');

    $_SESSION['chatbot_pending_action'] = [
        'type'           => 'confirm_appointment',
        'appointment_id' => (int) $appointment['id'],
        'title'          => (string) ($appointment['title'] ?? 'Appointment'),
        'starts_at'      => (string) (appointmentStart($appointment) ?? ''),
        'ends_at'        => (string) (appointmentEnd($appointment) ?? ''),
        'client_name'    => $clientName,
        'when'           => $when,
        'summary'        => 'Confirm appointment for **' . $clientName . '** (' . $when . ')',
    ];

    return 'Confirm appointment for **' . $clientName . '** — **' . ($appointment['title'] ?? 'Appointment') . '** on **'
        . $when . '**? Reply **yes** to confirm or **no** to cancel.';
}

function chatbotPrepareMarkNotificationsReadAction(string $message): ?string
{
    if (!Auth::can(RoleAccess::PERMISSION_NOTIFICATIONS)) {
        return 'You do not have permission to manage notifications.';
    }

    $userId = (int) Auth::id();
    $unread = $userId > 0 ? getUnreadNotificationCount($userId) : 0;

    if ($unread <= 0) {
        return 'You have no unread notifications.';
    }

    $_SESSION['chatbot_pending_action'] = [
        'type'    => 'mark_notifications_read',
        'summary' => 'Mark all notifications as read',
    ];

    return "Mark **{$unread}** unread notification(s) as read? Reply **yes** to confirm or **no** to cancel.";
}

function chatbotResolveCaseForAction(string $message): ?array
{
    $case = chatbotResolveCaseFromMessage($message);
    if ($case === null) {
        $entity = chatbotGetLastEntity();
        if (($entity['type'] ?? '') === 'case' && (int) ($entity['id'] ?? 0) > 0) {
            $case = chatbotFetchCaseById((int) $entity['id']);
        }
    }

    if ($case === null || !chatbotUserCanAccessCaseId((int) $case['id'])) {
        return null;
    }

    return $case;
}

function chatbotResolveAdminFromMessage(string $message): ?array
{
    if (!preg_match('/\bto\s+(.+)$/iu', $message, $matches)) {
        return null;
    }

    $term = trim($matches[1]);
    $term = preg_replace('/\b(case[- ]?\d{4}[- ]?\d+|case[- ]?\d+)\b/i', '', $term);
    $term = trim(preg_replace('/\s+/', ' ', $term));

    if ($term === '' || preg_match('/^(me|myself)$/i', $term)) {
        return null;
    }

    $termLower = strtolower($term);
    $best      = null;
    $bestScore = 0;

    foreach (CaseService::getAdmins() as $admin) {
        $name  = trim((string) ($admin['name'] ?? ''));
        $email = strtolower(trim((string) ($admin['email'] ?? '')));
        if ($name === '') {
            continue;
        }

        $nameLower = strtolower($name);
        if ($nameLower === $termLower || $email === $termLower) {
            return $admin;
        }

        if (str_starts_with($nameLower, $termLower) || str_contains($nameLower, $termLower)) {
            $score = similar_text($nameLower, $termLower);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best      = $admin;
            }
        }
    }

    return $best;
}

function chatbotExtractNoteText(string $message): ?string
{
    if (preg_match('/(?:note|notes)\s*[:\-—]\s*(.+)$/iu', $message, $matches)) {
        return trim($matches[1]);
    }

    if (preg_match('/\b(?:note|notes)\s+(?:to|on|for)\s+case[- ]?\S+\s*[:\-—]?\s*(.+)$/iu', $message, $matches)) {
        return trim($matches[1]);
    }

    if (preg_match('/\b(?:note|notes)\s+(?:to|on|for)\s+case[- ]?\S+\s+(.+)$/iu', $message, $matches)) {
        $text = trim($matches[1]);
        if (!preg_match('/^(priority|deadline|status)\b/i', $text)) {
            return $text;
        }
    }

    if (preg_match('/\b(?:saying|that reads?)\s+(.+)$/iu', $message, $matches)) {
        return trim($matches[1]);
    }

    return null;
}

function chatbotParseCasePriority(string $message): ?string
{
    $normalized = strtolower(trim($message));

    foreach (['urgent', 'high', 'medium', 'low'] as $priority) {
        if (preg_match('/\b' . preg_quote($priority, '/') . '\b/', $normalized)) {
            return $priority;
        }
    }

    return null;
}

function chatbotParseCaseDeadline(string $message): ?string
{
    if (preg_match('/\b(\d{4}-\d{2}-\d{2})\b/', $message, $matches)) {
        return $matches[1];
    }

    if (preg_match('/\bdeadline\b.*\bto\b\s+(.+)$/iu', $message, $matches)) {
        $parsed = parseFlexibleDateTime(trim($matches[1]));
        if ($parsed !== '') {
            return date('Y-m-d', strtotime($parsed));
        }
    }

    $extracted = chatbotExtractDateTimeTextFromScheduleMessage($message);
    $parsed    = parseFlexibleDateTime($extracted !== trim($message) ? $extracted : $message);
    if ($parsed !== '') {
        return date('Y-m-d', strtotime($parsed));
    }

    return null;
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
