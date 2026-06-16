<?php

declare(strict_types=1);

class AssistantActions
{
    private const DRAFT_SESSION_KEY = 'assistant_drafts';

    /** @return array{content: string, type: string, draft?: array<string, mixed>} */
    public static function handle(string $topic, string $message): array
    {
        return match ($topic) {
            'create_case' => self::draftCreateCase($message),
            'update_case' => self::draftUpdateCase($message),
            'schedule_appointment' => self::draftScheduleAppointment($message),
            'reschedule_appointment' => self::draftRescheduleAppointment($message),
            'cancel_appointment' => self::draftUpdateAppointmentStatus($message, 'cancelled'),
            'confirm_appointment' => self::draftUpdateAppointmentStatus($message, 'confirmed'),
            'complete_appointment' => self::draftUpdateAppointmentStatus($message, 'completed'),
            'mark_appointment_no_show' => self::draftUpdateAppointmentStatus($message, 'no_show'),
            'mark_notifications_read' => self::draftMarkNotificationsRead(),
            default => [
                'content' => 'I could not determine which system action you want. Try **create a case**, **update case status**, **schedule appointment**, **confirm/cancel/reschedule appointment**, or **mark notifications read**.',
                'type' => 'text',
            ],
        };
    }

    /** @return array{content: string, type: string, draft: array<string, mixed>} */
    public static function confirm(string $draftId): array
    {
        if (Auth::isReadOnly()) {
            throw new RuntimeException('Your account is read-only and cannot confirm changes.');
        }

        $draft = self::getDraft($draftId);
        if ($draft === null) {
            throw new RuntimeException('This draft has expired or was already confirmed.');
        }

        $adminId = Auth::id();
        if ($adminId === null) {
            throw new RuntimeException('You must be logged in to confirm changes.');
        }

        $result = match ($draft['action']) {
            'create_case' => self::executeCreateCase($draft['payload'], $adminId),
            'update_case' => self::executeUpdateCase($draft['payload'], $adminId),
            'schedule_appointment' => self::executeScheduleAppointment($draft['payload'], $adminId),
            'update_appointment' => self::executeUpdateAppointment($draft['payload']),
            'mark_notifications_read' => self::executeMarkNotificationsRead($adminId),
            default => throw new RuntimeException('Unknown draft action.'),
        };

        self::removeDraft($draftId);

        return [
            'content' => $result,
            'type' => 'text',
        ];
    }

    /** @return array{content: string, type: string, draft: array<string, mixed>} */
    private static function draftCreateCase(string $message): array
    {
        $extracted = self::extractActionFields($message, [
            'client_name' => 'client or customer full name',
            'title' => 'short case title',
            'service_type' => 'document or deed type',
            'description' => 'case details or notes',
        ]);

        $clientName = trim((string) ($extracted['client_name'] ?? ''));
        if ($clientName === '') {
            $clientName = assistantExtractClientNameFromActionMessage($message);
        }
        if ($clientName === '' && preg_match('/\bfor\s+([A-Z][\w\s\'-]{2,60})/i', $message, $m)) {
            $clientName = trim($m[1]);
        }

        $title = trim((string) ($extracted['title'] ?? ''));
        $serviceType = trim((string) ($extracted['service_type'] ?? ''));
        $description = trim((string) ($extracted['description'] ?? ''));

        if ($title === '' && $serviceType !== '') {
            $title = ucfirst($serviceType) . ' — ' . ($clientName !== '' ? $clientName : 'New matter');
        }
        if ($title === '') {
            $title = 'New notary matter';
        }

        $clientId = $clientName !== '' ? assistantResolveClientId($clientName) : null;
        if ($clientId === null) {
            return [
                'content' => 'I need an existing **client name** to draft a new case. Example: _Create a case for Jean Dupont — deed of sale._',
                'type' => 'text',
            ];
        }

        $client = ClientService::getById($clientId);
        $payload = [
            'title' => $title,
            'description' => $description,
            'client_id' => $clientId,
            'service_type' => $serviceType !== '' ? $serviceType : 'Notarization',
            'service_fee' => 0,
        ];

        $preview = [
            'Client' => clientFullName($client ?? []),
            'Title' => $title,
            'Service' => $payload['service_type'],
            'Description' => $description !== '' ? $description : '—',
            'Status' => 'Pending (new)',
        ];

        return self::buildDraftResponse(
            'create_case',
            $payload,
            $preview,
            'Review the new case draft below. Nothing is saved until you click **Confirm**.'
        );
    }

    /** @return array{content: string, type: string, draft: array<string, mixed>} */
    private static function draftUpdateCase(string $message): array
    {
        $extracted = self::extractActionFields($message, [
            'case_reference' => 'case number or id',
            'status' => 'new status if mentioned',
            'description' => 'updated description if mentioned',
        ]);

        $caseRef = trim((string) ($extracted['case_reference'] ?? ''));
        if ($caseRef === '' && preg_match('/case[- ]?#?\s*([A-Z0-9-]+)/i', $message, $m)) {
            $caseRef = $m[1];
        }

        $case = $caseRef !== '' ? assistantFindCaseByReference($caseRef) : null;
        if ($case === null) {
            return [
                'content' => 'Tell me which case to update, e.g. _Update case CASE-2026-0001 status to in progress_.',
                'type' => 'text',
            ];
        }

        $newStatus = strtolower(trim((string) ($extracted['status'] ?? '')));
        $newStatus = str_replace([' ', '-'], '_', $newStatus);
        if ($newStatus === '' && preg_match('/\b(pending|in progress|in_progress|waiting for client|waiting_for_client|completed|closed)\b/i', $message, $m)) {
            $newStatus = strtolower(str_replace(' ', '_', $m[1]));
        }

        $description = trim((string) ($extracted['description'] ?? ''));
        $payload = [
            'case_id' => (int) $case['id'],
            'status' => $newStatus,
            'description' => $description,
            'title' => (string) ($case['title'] ?? ''),
            'client_id' => (int) ($case['client_id'] ?? 0),
            'service_type' => (string) ($case['service_type'] ?? ''),
            'service_fee' => (float) ($case['service_fee'] ?? 0),
        ];

        $preview = [
            'Case' => (string) ($case['case_number'] ?? ''),
            'Client' => clientFullName($case),
            'Current status' => CaseService::statusLabel((string) ($case['status'] ?? 'pending')),
        ];
        if ($newStatus !== '' && CaseService::isValidStatus($newStatus)) {
            $preview['New status'] = CaseService::statusLabel($newStatus);
        }
        if ($description !== '') {
            $preview['New description'] = $description;
        }

        return self::buildDraftResponse(
            'update_case',
            $payload,
            $preview,
            'This will update the case record. Review and click **Confirm** to apply.'
        );
    }

    /** @return array{content: string, type: string, draft: array<string, mixed>} */
    private static function draftScheduleAppointment(string $message): array
    {
        $extracted = self::extractActionFields($message, [
            'client_name' => 'client name',
            'title' => 'appointment title',
            'starts_at' => 'start date and time',
            'ends_at' => 'end date and time if any',
            'case_reference' => 'linked case number if any',
            'status' => 'appointment status if mentioned (scheduled, confirmed, requested)',
        ]);

        $clientName = trim((string) ($extracted['client_name'] ?? ''));
        if ($clientName === '') {
            $clientName = assistantExtractClientNameFromActionMessage($message);
        }

        $clientId = $clientName !== '' ? assistantResolveClientId($clientName) : null;

        if ($clientId === null) {
            return [
                'content' => 'To schedule an appointment I need a **client name** and **date/time**. Example: _Schedule appointment for Marie Curie tomorrow at 2pm — deed signing._',
                'type' => 'text',
            ];
        }

        $scheduleTimes = assistantExtractScheduleTimes($message);
        $startsAt = parseFlexibleDateTime(trim((string) ($extracted['starts_at'] ?? '')));
        if ($startsAt === '') {
            $startsAt = $scheduleTimes['starts_at'];
        }
        if ($startsAt === '') {
            $startsAt = parseFlexibleDateTime($message);
        }
        if ($startsAt === '') {
            return [
                'content' => 'Which **date and time** should I book? Example: _Schedule Jean Dupont on 20 Jun 2026 at 10:00._',
                'type' => 'text',
            ];
        }

        $endsAt = parseFlexibleDateTime(trim((string) ($extracted['ends_at'] ?? '')));
        if ($endsAt === '') {
            $endsAt = $scheduleTimes['ends_at'];
        }
        $title = trim((string) ($extracted['title'] ?? ''));
        if ($title === '') {
            $title = 'Notary appointment';
        }

        $caseRef = trim((string) ($extracted['case_reference'] ?? ''));
        $case = $caseRef !== '' ? assistantFindCaseByReference($caseRef) : null;

        $status = trim((string) ($extracted['status'] ?? ''));
        if ($status === '') {
            $status = assistantExtractAppointmentStatus($message, 'scheduled', true);
        } else {
            $status = normalizeAppointmentStatus($status);
        }

        $client = ClientService::getById($clientId);
        $payload = [
            'client_id' => $clientId,
            'title' => $title,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'case_id' => $case ? (int) $case['id'] : null,
            'status' => $status,
        ];

        $preview = [
            'Client' => clientFullName($client ?? []),
            'Title' => $title,
            'Status' => assistantAppointmentStatusLabel($status),
            'Starts' => formatDateTime($startsAt),
            'Ends' => $endsAt !== '' ? formatDateTime($endsAt) : formatDateTime(date('Y-m-d H:i:s', strtotime($startsAt . ' +1 hour'))),
            'Case' => $case ? (string) $case['case_number'] : '—',
        ];

        return self::buildDraftResponse(
            'schedule_appointment',
            $payload,
            $preview,
            'Appointment draft ready. Click **Confirm** to book on the calendar.'
        );
    }

    /** @return array{content: string, type: string, draft: array<string, mixed>} */
    private static function draftRescheduleAppointment(string $message): array
    {
        $candidates = assistantFindAppointments($message);
        $appointment = assistantResolveAppointment($message);
        if ($appointment === null) {
            if (count($candidates) > 1) {
                return [
                    'content' => assistantDescribeAppointments($candidates),
                    'type' => 'text',
                ];
            }

            return [
                'content' => 'Which appointment should I reschedule? Example: _Reschedule Louis Macwell’s appointment to tomorrow at 3pm._',
                'type' => 'text',
            ];
        }

        $extracted = self::extractActionFields($message, [
            'starts_at' => 'new start date and time',
            'ends_at' => 'new end date and time if any',
            'title' => 'new appointment title if any',
        ]);

        $scheduleTimes = assistantExtractScheduleTimes($message);
        $startsAt = parseFlexibleDateTime(trim((string) ($extracted['starts_at'] ?? '')));
        if ($startsAt === '') {
            $startsAt = $scheduleTimes['starts_at'];
        }
        if ($startsAt === '') {
            $startsAt = parseFlexibleDateTime($message);
        }
        if ($startsAt === '') {
            return [
                'content' => 'What is the **new date and time**? Example: _Reschedule appointment for Louis to tomorrow at 3pm._',
                'type' => 'text',
            ];
        }

        $endsAt = parseFlexibleDateTime(trim((string) ($extracted['ends_at'] ?? '')));
        if ($endsAt === '') {
            $endsAt = $scheduleTimes['ends_at'];
        }

        $title = trim((string) ($extracted['title'] ?? ''));
        if ($title === '') {
            $title = (string) ($appointment['title'] ?? 'Notary appointment');
        }

        $currentStart = appointmentStart($appointment) ?? '';
        $payload = [
            'appointment_id' => (int) $appointment['id'],
            'title' => $title,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => 'rescheduled',
        ];

        $preview = [
            'Client' => clientFullName($appointment),
            'Title' => $title,
            'Current' => $currentStart !== '' ? formatDateTime($currentStart) : '—',
            'New time' => formatDateTime($startsAt),
            'New end' => $endsAt !== '' ? formatDateTime($endsAt) : formatDateTime(date('Y-m-d H:i:s', strtotime($startsAt . ' +1 hour'))),
            'Status' => 'Rescheduled',
        ];

        return self::buildDraftResponse(
            'update_appointment',
            $payload,
            $preview,
            'Reschedule draft ready. Click **Confirm** to update the appointment.'
        );
    }

    /** @return array{content: string, type: string, draft: array<string, mixed>} */
    private static function draftUpdateAppointmentStatus(string $message, string $status): array
    {
        $status = normalizeAppointmentStatus($status);
        $candidates = assistantFindAppointments($message, true);
        $appointment = assistantResolveAppointment($message, true);
        if ($appointment === null) {
            if (count($candidates) > 1) {
                return [
                    'content' => assistantDescribeAppointments($candidates),
                    'type' => 'text',
                ];
            }

            $verb = match ($status) {
                'cancelled' => 'cancel',
                'confirmed' => 'confirm',
                'completed' => 'complete',
                'no_show' => 'mark as no-show',
                default => 'update',
            };

            return [
                'content' => 'Which appointment should I ' . $verb . '? Example: _' . ucfirst($verb) . ' appointment for Louis Macwell tomorrow._',
                'type' => 'text',
            ];
        }

        $payload = [
            'appointment_id' => (int) $appointment['id'],
            'title' => (string) ($appointment['title'] ?? 'Notary appointment'),
            'starts_at' => appointmentStart($appointment) ?? '',
            'ends_at' => appointmentEnd($appointment) ?? '',
            'status' => $status,
        ];

        $preview = [
            'Client' => clientFullName($appointment),
            'Title' => (string) ($appointment['title'] ?? 'Appointment'),
            'When' => $payload['starts_at'] !== '' ? formatDateTime($payload['starts_at']) : '—',
            'Current status' => assistantAppointmentStatusLabel((string) ($appointment['status'] ?? 'scheduled')),
            'New status' => assistantAppointmentStatusLabel($status),
        ];

        $intro = match ($status) {
            'cancelled' => 'Cancellation draft ready. Click **Confirm** to cancel this appointment.',
            'confirmed' => 'Confirmation draft ready. Click **Confirm** to mark this appointment as confirmed.',
            'completed' => 'Completion draft ready. Click **Confirm** to mark this appointment as completed.',
            'no_show' => 'No-show draft ready. Click **Confirm** to update the appointment.',
            default => 'Appointment update draft ready. Click **Confirm** to apply.',
        };

        return self::buildDraftResponse('update_appointment', $payload, $preview, $intro);
    }

    /** @return array{content: string, type: string, draft: array<string, mixed>} */
    private static function draftMarkNotificationsRead(): array
    {
        $userId = Auth::id();
        $unread = $userId ? getUnreadNotificationCount($userId) : 0;

        $preview = [
            'Unread notifications' => (string) $unread,
            'Action' => 'Mark all as read',
        ];

        return self::buildDraftResponse(
            'mark_notifications_read',
            [],
            $preview,
            $unread > 0
                ? "This will mark **{$unread}** notification(s) as read. Click **Confirm** to proceed."
                : 'There are no unread notifications, but you can still confirm to run the action.'
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $preview
     * @return array{content: string, type: string, draft: array<string, mixed>}
     */
    private static function buildDraftResponse(string $action, array $payload, array $preview, string $intro): array
    {
        $draftId = bin2hex(random_bytes(8));
        $draft = [
            'id' => $draftId,
            'action' => $action,
            'payload' => $payload,
            'preview' => $preview,
            'created_at' => time(),
        ];

        $_SESSION[self::DRAFT_SESSION_KEY][$draftId] = $draft;

        return [
            'content' => $intro,
            'type' => 'draft',
            'draft' => $draft,
        ];
    }

    /** @param array<string, string> $fields */
    private static function extractActionFields(string $message, array $fields): array
    {
        if (OllamaService::isEnabled() && OllamaService::isReachable()) {
            try {
                $fieldList = [];
                foreach ($fields as $key => $label) {
                    $fieldList[] = $key . ' (' . $label . ')';
                }

                $prompt = "Extract these fields from the user message as JSON only. Use empty string if unknown.\n"
                    . 'Fields: ' . implode(', ', $fieldList) . "\n"
                    . "Message: {$message}\n"
                    . 'Respond with JSON object only, no markdown.';

                $raw = OllamaService::chat([
                    ['role' => 'system', 'content' => 'You extract structured data. Reply with valid JSON only.'],
                    ['role' => 'user', 'content' => $prompt],
                ]);

                $raw = trim(preg_replace('/^```json\s*|\s*```$/', '', $raw) ?? $raw);
                $decoded = json_decode($raw, true);

                if (is_array($decoded) && $decoded !== []) {
                    return $decoded;
                }
            } catch (Throwable) {
                // Fall back to rule-based extraction.
            }
        }

        return self::extractActionFieldsHeuristic($message, $fields);
    }

    /** @param array<string, string> $fields @return array<string, string> */
    private static function extractActionFieldsHeuristic(string $message, array $fields): array
    {
        $result = [];
        foreach (array_keys($fields) as $key) {
            $result[$key] = '';
        }

        if (isset($fields['case_reference']) && preg_match('/case[- ]?#?\s*([A-Z0-9-]+)/i', $message, $matches)) {
            $result['case_reference'] = $matches[1];
        }

        if (isset($fields['client_name'])) {
            $clientName = assistantExtractClientNameFromActionMessage($message);
            if ($clientName === '' && preg_match('/\bfor\s+([A-Z][\w\s\'-]{2,60})/i', $message, $matches)) {
                $clientName = trim($matches[1]);
            }
            if ($clientName !== '') {
                $result['client_name'] = $clientName;
            }
        }

        if (isset($fields['status']) && preg_match(
            '/\b(pending|in progress|in_progress|waiting for client|waiting_for_client|completed|closed|confirmed|cancelled|scheduled|no_show|no show)\b/i',
            $message,
            $matches
        )) {
            $result['status'] = strtolower(str_replace(' ', '_', $matches[1]));
        }

        if (isset($fields['title']) && preg_match('/\b(?:titled|title)\s+["\']?([^"\']+)["\']?/i', $message, $matches)) {
            $result['title'] = trim($matches[1]);
        }

        if (isset($fields['service_type']) && preg_match(
            '/\b(deed(?: of sale)?|jurat|acknowledg(?:e)?ment|affidavit|poa|power of attorney|notarization)\b/i',
            $message,
            $matches
        )) {
            $result['service_type'] = trim($matches[1]);
        }

        if (isset($fields['description']) && preg_match('/\b(?:notes?|details?|description)\s*[:\-—]\s*(.+)$/i', $message, $matches)) {
            $result['description'] = trim($matches[1]);
        }

        if (isset($fields['starts_at']) || isset($fields['ends_at'])) {
            $times = assistantExtractScheduleTimes($message);
            if ($times['starts_at'] !== '') {
                $result['starts_at'] = $times['starts_at'];
            }
            if ($times['ends_at'] !== '') {
                $result['ends_at'] = $times['ends_at'];
            }
        }

        return $result;
    }

    /** @param array<string, mixed> $payload */
    private static function executeCreateCase(array $payload, int $adminId): string
    {
        $data = [
            'title' => (string) ($payload['title'] ?? 'New case'),
            'description' => (string) ($payload['description'] ?? ''),
            'client_id' => (int) ($payload['client_id'] ?? 0),
            'service_type' => (string) ($payload['service_type'] ?? 'Notarization'),
            'service_fee' => (float) ($payload['service_fee'] ?? 0),
        ];

        $caseId = CaseService::createCase($data, $adminId);
        $case = CaseService::getCaseById($caseId);

        return '**Case created:** ' . ($case['case_number'] ?? '#' . $caseId) . ' — '
            . ($case['title'] ?? '') . "\n\n"
            . assistantAdminLink('pages/case-view.php?id=' . $caseId, 'Open case');
    }

    /** @param array<string, mixed> $payload */
    private static function executeUpdateCase(array $payload, int $adminId): string
    {
        $caseId = (int) ($payload['case_id'] ?? 0);
        $case = CaseService::getCaseById($caseId);
        if (!$case) {
            throw new RuntimeException('Case not found.');
        }

        $newStatus = (string) ($payload['status'] ?? '');
        if ($newStatus !== '' && CaseService::isValidStatus($newStatus) && $newStatus !== ($case['status'] ?? '')) {
            CaseService::updateStatus($caseId, $newStatus, $adminId);
        }

        $updateData = [
            'title' => (string) ($payload['title'] ?? $case['title'] ?? ''),
            'description' => (string) (($payload['description'] ?? '') !== '' ? $payload['description'] : ($case['description'] ?? '')),
            'client_id' => (int) ($payload['client_id'] ?? $case['client_id'] ?? 0),
            'service_type' => (string) ($payload['service_type'] ?? $case['service_type'] ?? ''),
            'service_fee' => (float) ($payload['service_fee'] ?? $case['service_fee'] ?? 0),
        ];
        CaseService::updateCase($caseId, $updateData);

        return '**Case updated:** ' . ($case['case_number'] ?? '#' . $caseId) . "\n\n"
            . assistantAdminLink('pages/case-view.php?id=' . $caseId, 'Open case');
    }

    /** @param array<string, mixed> $payload */
    private static function executeScheduleAppointment(array $payload, int $adminId): string
    {
        $status = normalizeAppointmentStatus((string) ($payload['status'] ?? 'scheduled'));
        $payload['status'] = $status;
        $id = AppointmentService::create($payload, $adminId);

        return '**Appointment ' . assistantAppointmentStatusLabel($status) . '** for '
            . formatDateTime((string) ($payload['starts_at'] ?? '')) . ".\n\n"
            . assistantAdminLink('pages/appointments.php', 'Open calendar');
    }

    /** @param array<string, mixed> $payload */
    private static function executeUpdateAppointment(array $payload): string
    {
        $appointmentId = (int) ($payload['appointment_id'] ?? 0);
        if ($appointmentId <= 0) {
            throw new RuntimeException('Appointment not found.');
        }

        $status = normalizeAppointmentStatus((string) ($payload['status'] ?? 'scheduled'));
        AppointmentService::update($appointmentId, [
            'title' => (string) ($payload['title'] ?? 'Notary appointment'),
            'starts_at' => (string) ($payload['starts_at'] ?? ''),
            'ends_at' => (string) ($payload['ends_at'] ?? ''),
            'status' => $status,
        ]);

        $label = assistantAppointmentStatusLabel($status);
        $timeNote = $status === 'rescheduled' && ($payload['starts_at'] ?? '') !== ''
            ? ' New time: ' . formatDateTime((string) $payload['starts_at']) . '.'
            : '';

        return '**Appointment updated** — status: **' . $label . '**.' . $timeNote
            . "\n\n"
            . assistantAdminLink('pages/appointments.php', 'Open calendar');
    }

    private static function executeMarkNotificationsRead(int $adminId): string
    {
        markAllNotificationsAsRead($adminId);

        return '**All notifications marked as read.** '
            . assistantAdminLink('pages/notifications.php', 'Open notifications');
    }

    /** @return array<string, mixed>|null */
    public static function getDraft(string $draftId): ?array
    {
        $drafts = $_SESSION[self::DRAFT_SESSION_KEY] ?? [];
        if (!is_array($drafts)) {
            return null;
        }

        $draft = $drafts[$draftId] ?? null;

        return is_array($draft) ? $draft : null;
    }

    private static function removeDraft(string $draftId): void
    {
        unset($_SESSION[self::DRAFT_SESSION_KEY][$draftId]);
    }

    public static function forgetDraft(string $draftId): void
    {
        self::removeDraft($draftId);
    }

    public static function clearDrafts(): void
    {
        unset($_SESSION[self::DRAFT_SESSION_KEY]);
    }

    /** @param list<array<string, mixed>> $history */
    public static function rehydrateDraftsFromHistory(array $history): void
    {
        self::clearDrafts();

        foreach ($history as $turn) {
            if (($turn['role'] ?? '') !== 'assistant') {
                continue;
            }

            $draft = $turn['draft'] ?? null;
            if (!is_array($draft) || empty($draft['id'])) {
                continue;
            }

            $_SESSION[self::DRAFT_SESSION_KEY][(string) $draft['id']] = $draft;
        }
    }
}
