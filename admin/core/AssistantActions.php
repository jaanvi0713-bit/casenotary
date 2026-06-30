<?php

declare(strict_types=1);

class AssistantActions
{
    private const DRAFT_SESSION_KEY = 'assistant_drafts';

    /** @return array{content: string, type: string, draft?: array<string, mixed>} */
    public static function handle(string $topic, string $message, array $uploads = []): array
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
            'upload_case_document' => self::draftUploadCaseDocument($message, $uploads),
            'create_client' => self::draftCreateClient($message),
            'draft_client_letter' => self::draftClientLetter($message),
            'record_payment' => self::draftRecordPayment($message),
            'generate_invoice' => self::draftGenerateInvoice($message),
            'send_invoice' => self::draftSendInvoice($message),
            'add_case_note' => self::draftAddCaseNote($message),
            'delete_case' => self::draftDeleteCase($message),
            'delete_document' => self::draftDeleteDocument($message),
            'delete_payment' => self::draftDeletePayment($message),
            'delete_invoice' => self::draftDeleteInvoice($message),
            default => [
                'content' => 'I could not determine which system action you want. Try **create a case**, **record payment**, **generate invoice**, **upload document to case**, **case summary**, **schedule appointment**, or _what can you do?_',
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
            'upload_case_document' => self::executeUploadCaseDocument($draft['payload'], $adminId),
            'create_client' => self::executeCreateClient($draft['payload']),
            'create_client_and_case' => self::executeCreateClientAndCase($draft['payload'], $adminId),
            'draft_client_letter' => self::executeDraftClientLetter($draft['payload']),
            'send_reminder' => self::executeSendReminder($draft['payload'], $adminId),
            'record_payment' => self::executeRecordPayment($draft['payload'], $adminId),
            'generate_invoice' => self::executeGenerateInvoice($draft['payload']),
            'send_invoice' => self::executeSendInvoice($draft['payload']),
            'add_case_note' => self::executeAddCaseNote($draft['payload'], $adminId),
            'delete_case' => self::executeDeleteCase($draft['payload']),
            'delete_document' => self::executeDeleteDocument($draft['payload']),
            'delete_payment' => self::executeDeletePayment($draft['payload']),
            'delete_invoice' => self::executeDeleteInvoice($draft['payload']),
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
            $clientName = assistantExtractClientNameForCreateCase($message);
        }
        $clientName = assistantSanitizeExtractedClientName($clientName);

        $title = trim((string) ($extracted['title'] ?? ''));
        $serviceType = trim((string) ($extracted['service_type'] ?? ''));
        $description = trim((string) ($extracted['description'] ?? ''));

        if (preg_match('/\bfor\s+.+?\s*[—\-]\s*(.+)$/iu', $message, $dashMatch)) {
            $afterDash = trim($dashMatch[1]);
            if ($afterDash !== '') {
                if ($serviceType === '') {
                    $serviceType = $afterDash;
                }
                if ($description === '') {
                    $description = $afterDash;
                }
            }
        }

        if ($title === '' && $serviceType !== '') {
            $title = ucfirst($serviceType) . ' — ' . ($clientName !== '' ? $clientName : 'New matter');
        }
        if ($title === '') {
            $title = 'New notary matter';
        }

        $clientId = $clientName !== '' ? assistantResolveClientId($clientName) : null;
        if ($clientId === null) {
            $caseContext = [
                'create_case'  => true,
                'title'        => $title,
                'service_type' => $serviceType,
                'description'  => $description,
            ];

            if ($clientName !== '' || preg_match('/\b(for me|new case|new matter)\b/i', $message)) {
                return AssistantClientCreate::begin($caseContext, $clientName);
            }

            return [
                'content' => assistantCreateCaseMissingClientMessage($clientName !== '' ? $clientName : null),
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

    /** @return array{content: string, type: string, draft?: array<string, mixed>} */
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
        $clientName = assistantSanitizeExtractedClientName($clientName);

        $scheduleTimes = assistantExtractScheduleTimes($message);
        $startsAt = parseFlexibleDateTime(trim((string) ($extracted['starts_at'] ?? '')));
        if ($startsAt === '') {
            $startsAt = $scheduleTimes['starts_at'];
        }
        if ($startsAt === '') {
            $startsAt = parseFlexibleDateTime($message);
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

        $clientId = $clientName !== '' ? assistantResolveClientId($clientName) : null;

        if ($clientId === null) {
            if ($startsAt !== '') {
                $preview = [
                    'When'  => formatDateTime($startsAt),
                    'Title' => $title,
                    'Case'  => $case ? (string) $case['case_number'] : '—',
                ];

                return AssistantAppointmentSchedule::beginMissingClient([
                    'title'     => $title,
                    'starts_at' => $startsAt,
                    'ends_at'   => $endsAt,
                    'case_id'   => $case ? (int) $case['id'] : null,
                ], $preview);
            }

            return [
                'content' => 'To schedule an appointment I need a **client name** and **date/time**. Example: _Schedule appointment for Marie Curie tomorrow at 2pm — deed signing._',
                'type' => 'text',
            ];
        }

        if ($startsAt === '') {
            $client = ClientService::getById($clientId);
            $preview = [
                'Client' => clientFullName($client ?? []),
                'Title'  => $title,
                'Case'   => $case ? (string) $case['case_number'] : '—',
            ];

            return AssistantAppointmentSchedule::beginMissingDateTime([
                'client_id' => $clientId,
                'title'     => $title,
                'case_id'   => $case ? (int) $case['id'] : null,
            ], $preview);
        }

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
            'Starts' => formatDateTime($startsAt),
            'Ends' => $endsAt !== '' ? formatDateTime($endsAt) : formatDateTime(date('Y-m-d H:i:s', strtotime($startsAt . ' +1 hour'))),
            'Case' => $case ? (string) $case['case_number'] : '—',
        ];

        $explicitStatus = assistantParseAppointmentStatusChoice($message);
        if ($explicitStatus !== null) {
            $payload['status'] = $explicitStatus;
            $preview['Status'] = assistantAppointmentStatusLabel($explicitStatus);

            return self::buildDraftResponse(
                'schedule_appointment',
                $payload,
                $preview,
                'Appointment draft ready. Click **Confirm** to book on the calendar.'
            );
        }

        return AssistantAppointmentSchedule::begin($payload, $preview);
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

    /** @return array{content: string, type: string, draft: array<string, mixed>} */
    private static function draftCreateClient(string $message): array
    {
        $name = assistantSanitizeExtractedClientName(assistantExtractClientNameForCreateCase($message));
        if ($name === '') {
            $name = assistantSanitizeExtractedClientName(assistantExtractClientNameFromActionMessage($message));
        }

        if (preg_match('/\bclient\s+(?:named|called)\s+["\']?([^"\']+)["\']?/i', $message, $matches)) {
            $name = assistantSanitizeExtractedClientName(trim($matches[1]));
        }

        $caseContext = [];
        if (preg_match('/\b(case|matter)\b/i', $message)) {
            $caseContext['create_case'] = true;
            $extracted = self::extractActionFieldsHeuristic($message, [
                'service_type' => 'document or deed type',
                'description' => 'case details or notes',
            ]);
            $serviceType = trim((string) ($extracted['service_type'] ?? ''));
            if ($serviceType !== '') {
                $caseContext['service_type'] = $serviceType;
                $caseContext['title'] = ucfirst($serviceType) . ($name !== '' ? ' — ' . $name : '');
            }
        }

        return AssistantClientCreate::begin($caseContext, $name);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $preview
     * @return array{content: string, type: string, draft: array<string, mixed>}
     */
    public static function createDraft(string $action, array $payload, array $preview, string $intro): array
    {
        return self::buildDraftResponse($action, $payload, $preview, $intro);
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
        return self::extractActionFieldsHeuristic($message, $fields);
    }

    /** @param array<string, string> $fields @return array<string, string> */
    private static function extractActionFieldsHeuristic(string $message, array $fields): array
    {
        $result = [];
        foreach (array_keys($fields) as $key) {
            $result[$key] = '';
        }

        if (isset($fields['case_reference'])) {
            $result['case_reference'] = assistantExtractCaseReferenceFromMessage($message);
        }

        if (isset($fields['client_name'])) {
            $clientName = assistantSanitizeExtractedClientName(assistantExtractClientNameFromActionMessage($message));
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
    private static function executeCreateClient(array $payload): string
    {
        if (!Auth::can(RoleAccess::PERMISSION_CLIENTS)) {
            throw new RuntimeException('You do not have permission to create clients.');
        }

        $clientData = is_array($payload['client'] ?? null) ? $payload['client'] : $payload;
        $result = ClientService::create($clientData, false);
        $client = ClientService::getById((int) $result['client_id']);

        return '**Client created:** ' . clientFullName($client ?? []) . "\n\n"
            . assistantAdminLink('pages/client-form.php?id=' . (int) $result['client_id'], 'View client');
    }

    /** @param array<string, mixed> $payload */
    private static function executeCreateClientAndCase(array $payload, int $adminId): string
    {
        if (!Auth::can(RoleAccess::PERMISSION_CLIENTS)) {
            throw new RuntimeException('You do not have permission to create clients.');
        }
        if (!Auth::can(RoleAccess::PERMISSION_CASES)) {
            throw new RuntimeException('You do not have permission to create cases.');
        }

        $clientData = is_array($payload['client'] ?? null) ? $payload['client'] : [];
        $caseData = is_array($payload['case'] ?? null) ? $payload['case'] : [];

        $result = ClientService::create($clientData, false);
        $clientId = (int) $result['client_id'];
        $client = ClientService::getById($clientId);

        $caseId = CaseService::createCase([
            'title'        => (string) ($caseData['title'] ?? 'New notary matter'),
            'description'  => (string) ($caseData['description'] ?? ''),
            'client_id'    => $clientId,
            'service_type' => (string) ($caseData['service_type'] ?? 'Notarization'),
            'service_fee'  => (float) ($caseData['service_fee'] ?? 0),
        ], $adminId);
        $case = CaseService::getCaseById($caseId);

        return '**Client created:** ' . clientFullName($client ?? []) . "\n"
            . '**Case created:** ' . ($case['case_number'] ?? '#' . $caseId) . ' — ' . ($case['title'] ?? '') . "\n\n"
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

    /**
     * @param array<string, mixed> $payload
     */
    private static function executeSendReminder(array $payload, int $adminId): string
    {
        $type = (string) ($payload['reminder_type'] ?? '');

        $result = match ($type) {
            AssistantReminders::TYPE_PAYMENT => ReminderService::sendPaymentReminderForInvoice(
                (int) ($payload['invoice_id'] ?? 0),
                $adminId
            ),
            AssistantReminders::TYPE_APPOINTMENT => ReminderService::sendAppointmentReminderNow(
                (int) ($payload['appointment_id'] ?? 0)
            ),
            AssistantReminders::TYPE_CASE => ReminderService::sendCaseReminderNow(
                (int) ($payload['case_id'] ?? 0),
                $adminId
            ),
            default => ['success' => false, 'message' => 'Unknown reminder type.'],
        };

        if (!$result['success']) {
            throw new RuntimeException($result['message']);
        }

        return $result['message'];
    }

    /**
     * @param list<array<string, mixed>> $uploads
     * @return array{content: string, type: string, draft?: array<string, mixed>}
     */
    private static function draftUploadCaseDocument(string $message, array $uploads): array
    {
        $validUploads = array_values(array_filter($uploads, static function (array $upload): bool {
            return ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
        }));

        if ($validUploads === []) {
            return [
                'content' => 'Attach the **document** with the paperclip, then say e.g. _Upload this to case CASE-2026-0001_.',
                'type' => 'text',
            ];
        }

        $extracted = self::extractActionFields($message, [
            'case_reference' => 'case number or id',
            'client_name' => 'client name if case not specified',
        ]);

        $case = assistantFindCaseByReferenceFromMessage($message);

        if ($case === null) {
            $caseRef = trim((string) ($extracted['case_reference'] ?? ''));
            if ($caseRef !== '') {
                $case = assistantFindCaseByReference($caseRef);
            }
        }

        if ($case === null) {
            $clientName = trim((string) ($extracted['client_name'] ?? ''));
            if ($clientName === '') {
                $clientName = assistantExtractClientNameFromActionMessage($message);
            }

            $clientId = $clientName !== '' ? assistantResolveClientId($clientName) : null;
            if ($clientId !== null) {
                $cases = Database::fetchAll(
                    "SELECT cs.*, cl.first_name, cl.last_name, cl.company_name
                     FROM cases cs
                     JOIN clients cl ON cl.id = cs.client_id
                     WHERE cs.client_id = ?
                     ORDER BY cs.updated_at DESC
                     LIMIT 1",
                    [$clientId]
                );
                $case = $cases[0] ?? null;
            }
        }

        if ($case === null) {
            return [
                'content' => 'Which **case** should receive this file? Example: _Upload to case CASE-2026-0001_ or _Save document on Louis Macwell\'s case._',
                'type' => 'text',
            ];
        }

        $stagedFiles = self::stageUploadFiles($validUploads);
        if ($stagedFiles === []) {
            return [
                'content' => 'Could not stage the uploaded file. Please try again.',
                'type' => 'text',
            ];
        }

        $fileNames = array_map(static fn (array $file): string => (string) ($file['original_name'] ?? 'file'), $stagedFiles);
        $preview = [
            'Case' => (string) ($case['case_number'] ?? ''),
            'Client' => clientFullName($case),
            'Files' => implode(', ', $fileNames),
            'Count' => (string) count($stagedFiles),
        ];

        $payload = [
            'case_id' => (int) $case['id'],
            'case_number' => (string) ($case['case_number'] ?? ''),
            'files' => $stagedFiles,
        ];

        return self::buildDraftResponse(
            'upload_case_document',
            $payload,
            $preview,
            'Document upload draft ready. Click **Confirm** to save ' . count($stagedFiles) . ' file(s) to the case.'
        );
    }

    /** @param array<string, mixed> $payload */
    private static function executeUploadCaseDocument(array $payload, int $adminId): string
    {
        $caseId = (int) ($payload['case_id'] ?? 0);
        $case = CaseService::getCaseById($caseId);
        if (!$case) {
            throw new RuntimeException('Case not found.');
        }

        $files = $payload['files'] ?? [];
        if (!is_array($files) || $files === []) {
            throw new RuntimeException('No staged files found. Please upload again.');
        }

        $saved = [];
        $errors = [];

        foreach ($files as $file) {
            if (!is_array($file)) {
                continue;
            }

            $path = (string) ($file['staged_path'] ?? '');
            $name = (string) ($file['original_name'] ?? 'document');
            if ($path === '' || !is_readable($path)) {
                $errors[] = $name;
                continue;
            }

            $result = CaseService::saveDocumentFromPath($caseId, $path, $name, $adminId);
            if (!empty($result['success'])) {
                $saved[] = $name;
                @unlink($path);
            } else {
                $errors[] = $name . ': ' . (string) ($result['message'] ?? 'failed');
            }
        }

        if ($saved === []) {
            throw new RuntimeException('Could not save any files. ' . implode('; ', $errors));
        }

        $lines = [
            '**Document(s) saved to case ' . ($case['case_number'] ?? '#' . $caseId) . ':**',
            '',
        ];

        foreach ($saved as $name) {
            $lines[] = '• ' . $name;
        }

        if ($errors !== []) {
            $lines[] = '';
            $lines[] = '_Some files could not be saved:_ ' . implode('; ', $errors);
        }

        $lines[] = '';
        $lines[] = assistantAdminLink('pages/case-view.php?id=' . $caseId . '#documents', 'Open case documents');

        return implode("\n", $lines);
    }

    /** @param list<array<string, mixed>> $uploads
     * @return list<array{staged_path: string, original_name: string}>
     */
    private static function stageUploadFiles(array $uploads): array
    {
        $dir = self::stagingDir();
        $staged = [];

        foreach ($uploads as $upload) {
            if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }

            $originalName = (string) ($upload['name'] ?? 'document');
            $ext = pathinfo($originalName, PATHINFO_EXTENSION);
            $stagedName = bin2hex(random_bytes(8)) . ($ext !== '' ? '.' . $ext : '');
            $dest = $dir . '/' . $stagedName;

            if (!move_uploaded_file((string) ($upload['tmp_name'] ?? ''), $dest)) {
                continue;
            }

            $staged[] = [
                'staged_path' => $dest,
                'original_name' => $originalName,
            ];
        }

        return $staged;
    }

    private static function stagingDir(): string
    {
        $dir = dirname(__DIR__) . '/storage/assistant-staging';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir;
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

    /** @return array{content: string, type: string, draft?: array<string, mixed>} */
    private static function draftClientLetter(string $message): array
    {
        $case = self::resolveCaseFromMessage($message);
        if ($case === null) {
            return [
                'content' => 'Specify which case — e.g. **draft client letter for case CASE-2026-ABC12**.',
                'type' => 'text',
            ];
        }

        $caseId = (int) $case['id'];
        $sections = ClientLetterService::getSectionsForCase($caseId);
        $client = ClientService::getById((int) $case['client_id']);
        $billing = CaseService::getCaseBilling($case);

        if (trim((string) ($sections['additional_notes'] ?? '')) === '') {
            $fee = formatCurrency((float) ($billing['totals']['grand_total'] ?? $case['service_fee'] ?? 0));
            $desc = trim((string) ($case['description'] ?? ''));
            $descSnippet = $desc !== '' ? htmlspecialchars(mb_strimwidth($desc, 0, 280, '…'), ENT_QUOTES, 'UTF-8') : '';

            $sections['additional_notes'] = '<p>This engagement relates to <strong>'
                . htmlspecialchars((string) ($case['title'] ?? 'your matter'), ENT_QUOTES, 'UTF-8')
                . '</strong> (' . htmlspecialchars((string) ($case['service_type'] ?? 'notarial services'), ENT_QUOTES, 'UTF-8') . ').</p>'
                . '<p>The quoted fee for this matter is <strong>' . htmlspecialchars($fee, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
                . ($descSnippet !== '' ? '<p>' . $descSnippet . '</p>' : '');
        }

        $preview = [
            'Case' => (string) ($case['case_number'] ?? ''),
            'Client' => $client ? clientFullName($client) : '—',
            'Service' => (string) ($case['service_type'] ?? '—'),
        ];

        return self::buildDraftResponse(
            'draft_client_letter',
            ['case_id' => $caseId, 'sections' => $sections],
            $preview,
            'Client letter draft prepared from case data. **Confirm** to save sections to the Client Letter tab (you can edit before generating PDF).'
        );
    }

    /** @param array<string, mixed> $payload */
    private static function executeDraftClientLetter(array $payload): string
    {
        $caseId = (int) ($payload['case_id'] ?? 0);
        $sections = $payload['sections'] ?? [];
        if ($caseId <= 0 || !is_array($sections)) {
            throw new RuntimeException('Invalid letter draft.');
        }

        ClientLetterService::saveCaseSections($caseId, $sections);
        CaseService::logCaseEvent($caseId, 'letter_drafted', ['via' => 'assistant'], Auth::id());

        $case = CaseService::getCaseById($caseId);

        return 'Client letter sections saved for case **' . ($case['case_number'] ?? $caseId) . '**. Open the **Client Letter** tab to review and generate the PDF.';
    }

    /** @return array{content: string, type: string, draft?: array<string, mixed>} */
    private static function draftRecordPayment(string $message): array
    {
        if (!Auth::can(RoleAccess::PERMISSION_PAYMENTS)) {
            return ['content' => 'Your role cannot record payments.', 'type' => 'text'];
        }

        $invoice = assistantFindInvoiceFromMessage($message);
        if ($invoice === null) {
            return [
                'content' => 'Which invoice should I record payment for? Example: _Record £500 payment for invoice INV-'
                    . date('Y') . '-0001_ or _Record payment for case CASE-2026-0006_.',
                'type' => 'text',
            ];
        }

        $remaining = CaseService::getInvoiceRemainingBalance($invoice);
        if ($remaining <= 0) {
            return [
                'content' => 'Invoice **' . ($invoice['invoice_number'] ?? '') . '** is already fully paid.',
                'type' => 'text',
            ];
        }

        $amount = assistantExtractMoneyAmount($message) ?? $remaining;
        $method = 'bank_transfer';
        if (preg_match('/\b(cash|card|stripe|cheque|check|bank|transfer)\b/i', $message, $matches)) {
            $method = strtolower($matches[1]);
            if ($method === 'check') {
                $method = 'cheque';
            }
            if ($method === 'transfer') {
                $method = 'bank_transfer';
            }
        }

        $preview = [
            'Invoice' => (string) ($invoice['invoice_number'] ?? ''),
            'Client' => clientFullName($invoice),
            'Amount' => formatCurrency($amount),
            'Method' => ucwords(str_replace('_', ' ', $method)),
            'Balance before' => formatCurrency($remaining),
        ];

        return self::buildDraftResponse(
            'record_payment',
            [
                'invoice_id' => (int) $invoice['id'],
                'amount' => $amount,
                'payment_method' => $method,
            ],
            $preview,
            'Payment draft ready. **Confirm** to record payment and generate a receipt.'
        );
    }

    /** @param array<string, mixed> $payload */
    private static function executeRecordPayment(array $payload, int $adminId): string
    {
        $invoiceId = (int) ($payload['invoice_id'] ?? 0);
        $result = CaseService::recordPayment($invoiceId, [
            'amount' => $payload['amount'] ?? null,
            'payment_method' => $payload['payment_method'] ?? 'bank_transfer',
        ], $adminId);

        if (empty($result['success'])) {
            throw new RuntimeException((string) ($result['message'] ?? 'Could not record payment.'));
        }

        $invoice = Database::fetch('SELECT invoice_number, case_id FROM invoices WHERE id = ?', [$invoiceId]);

        return '**Payment recorded** for invoice **' . ($invoice['invoice_number'] ?? $invoiceId) . '**. '
            . assistantAdminLink(
                'pages/case-view.php?id=' . (int) ($invoice['case_id'] ?? 0) . '#invoice-payments',
                'View case billing'
            );
    }

    /** @return array{content: string, type: string, draft?: array<string, mixed>} */
    private static function draftGenerateInvoice(string $message): array
    {
        if (!Auth::can(RoleAccess::PERMISSION_PAYMENTS)) {
            return ['content' => 'Your role cannot generate invoices.', 'type' => 'text'];
        }

        $case = self::resolveCaseFromMessage($message);
        if ($case === null) {
            return [
                'content' => 'Which case should I invoice? Example: _Generate invoice for case CASE-2026-0006_.',
                'type' => 'text',
            ];
        }

        $billing = CaseService::getCaseBilling($case);
        $total = (float) ($billing['totals']['grand_total'] ?? 0);
        $withLink = (bool) preg_match('/\b(payment link|pay link|online pay)\b/i', $message);

        $preview = [
            'Case' => (string) ($case['case_number'] ?? ''),
            'Client' => clientFullName($case),
            'Amount' => formatCurrency($total),
            'Due' => date('Y-m-d', strtotime('+14 days')),
            'Payment link' => $withLink ? 'Yes' : 'No',
        ];

        return self::buildDraftResponse(
            'generate_invoice',
            [
                'case_id' => (int) $case['id'],
                'generate_payment_link' => $withLink,
            ],
            $preview,
            'Invoice draft ready from case billing. **Confirm** to generate the invoice PDF.'
        );
    }

    /** @param array<string, mixed> $payload */
    private static function executeGenerateInvoice(array $payload): string
    {
        $caseId = (int) ($payload['case_id'] ?? 0);
        $invoiceId = CaseService::generateInvoice($caseId, [
            'generate_payment_link' => !empty($payload['generate_payment_link']),
        ]);
        $invoice = Database::fetch('SELECT invoice_number FROM invoices WHERE id = ?', [$invoiceId]);

        return '**Invoice generated:** **' . ($invoice['invoice_number'] ?? $invoiceId) . '** for case **'
            . (CaseService::getCaseById($caseId)['case_number'] ?? $caseId) . '**. '
            . assistantAdminLink('pages/case-view.php?id=' . $caseId . '#invoice-payments', 'Open billing');
    }

    /** @return array{content: string, type: string, draft?: array<string, mixed>} */
    private static function draftSendInvoice(string $message): array
    {
        if (!Auth::can(RoleAccess::PERMISSION_PAYMENTS)) {
            return ['content' => 'Your role cannot email invoices.', 'type' => 'text'];
        }

        $invoice = assistantFindInvoiceFromMessage($message);
        if ($invoice === null) {
            return [
                'content' => 'Which invoice should I email? Example: _Send invoice INV-' . date('Y') . '-0001 to client_.',
                'type' => 'text',
            ];
        }

        $caseId = (int) ($invoice['case_id'] ?? 0);
        $preview = [
            'Invoice' => (string) ($invoice['invoice_number'] ?? ''),
            'Client' => clientFullName($invoice),
            'Email' => trim((string) ($invoice['email'] ?? '')) ?: '—',
            'Total' => formatCurrency((float) ($invoice['total'] ?? 0)),
        ];

        return self::buildDraftResponse(
            'send_invoice',
            ['case_id' => $caseId, 'invoice_id' => (int) $invoice['id']],
            $preview,
            'Ready to email this invoice to the client. **Confirm** to send (requires SMTP).'
        );
    }

    /** @param array<string, mixed> $payload */
    private static function executeSendInvoice(array $payload): string
    {
        $caseId = (int) ($payload['case_id'] ?? 0);
        $invoiceId = (int) ($payload['invoice_id'] ?? 0);
        CaseService::sendInvoiceToClient($caseId, $invoiceId);

        return '**Invoice emailed** to the client successfully.';
    }

    /** @return array{content: string, type: string, draft?: array<string, mixed>} */
    private static function draftAddCaseNote(string $message): array
    {
        $case = self::resolveCaseFromMessage($message);
        if ($case === null) {
            return [
                'content' => 'Which case should I add a note to? Example: _Add note to case CASE-2026-0006: Client called back_.',
                'type' => 'text',
            ];
        }

        $note = assistantExtractNoteBody($message);
        if ($note === '') {
            return [
                'content' => 'What should the note say? Example: _Add note to case CASE-2026-0006: Client will bring ID tomorrow_.',
                'type' => 'text',
            ];
        }

        $preview = [
            'Case' => (string) ($case['case_number'] ?? ''),
            'Note' => $note,
            'Visibility' => 'Internal',
        ];

        return self::buildDraftResponse(
            'add_case_note',
            ['case_id' => (int) $case['id'], 'note' => $note],
            $preview,
            'Case note draft ready. **Confirm** to save it on the case.'
        );
    }

    /** @param array<string, mixed> $payload */
    private static function executeAddCaseNote(array $payload, int $adminId): string
    {
        $caseId = (int) ($payload['case_id'] ?? 0);
        $note = trim((string) ($payload['note'] ?? ''));
        if ($caseId <= 0 || $note === '') {
            throw new RuntimeException('Invalid note draft.');
        }

        CaseService::addNote($caseId, $adminId, $note, true);
        $case = CaseService::getCaseById($caseId);

        return '**Note added** to case **' . ($case['case_number'] ?? $caseId) . '**.';
    }

    /** @return array{content: string, type: string, draft?: array<string, mixed>} */
    private static function draftDeleteCase(string $message): array
    {
        if (!Auth::canManage(RoleAccess::PERMISSION_CASES)) {
            return ['content' => 'Your role cannot delete cases.', 'type' => 'text'];
        }

        $case = self::resolveCaseFromMessage($message);
        if ($case === null) {
            return [
                'content' => 'Which case should I delete? Example: _Delete case CASE-2026-0006_.',
                'type' => 'text',
            ];
        }

        $preview = [
            'Case' => (string) ($case['case_number'] ?? ''),
            'Client' => clientFullName($case),
            'Title' => (string) ($case['title'] ?? '—'),
            'Warning' => 'Permanent — cannot be undone',
        ];

        return self::buildDraftResponse(
            'delete_case',
            ['case_id' => (int) $case['id']],
            $preview,
            '**Delete case** draft — this permanently removes the case and its files. Click **Confirm** only if you are sure.'
        );
    }

    /** @param array<string, mixed> $payload */
    private static function executeDeleteCase(array $payload): string
    {
        $caseId = (int) ($payload['case_id'] ?? 0);
        $case = CaseService::getCaseById($caseId);
        if (!$case) {
            throw new RuntimeException('Case not found.');
        }

        $number = (string) ($case['case_number'] ?? $caseId);
        CaseService::deleteCase($caseId);

        return '**Case deleted:** ' . $number . '.';
    }

    /** @return array{content: string, type: string, draft?: array<string, mixed>} */
    private static function draftDeleteDocument(string $message): array
    {
        if (!Auth::can(RoleAccess::PERMISSION_CASES)) {
            return ['content' => 'Your role cannot delete documents.', 'type' => 'text'];
        }

        $case = self::resolveCaseFromMessage($message);
        if ($case === null) {
            return [
                'content' => 'Specify the case and file. Example: _Delete document invoice.pdf from case CASE-2026-0006_.',
                'type' => 'text',
            ];
        }

        $caseId = (int) $case['id'];
        $docs = CaseService::getDocuments($caseId);
        $needle = '';
        if (preg_match('/\b(?:document|file)\s+["\']?([^"\']+?)["\']?(?:\s+from|\s+on|$)/i', $message, $matches)) {
            $needle = trim($matches[1]);
        }

        $document = null;
        if ($needle !== '') {
            foreach ($docs as $doc) {
                if (stripos((string) ($doc['original_name'] ?? ''), $needle) !== false) {
                    $document = $doc;
                    break;
                }
            }
        } elseif (count($docs) === 1) {
            $document = $docs[0];
        }

        if ($document === null) {
            $names = array_map(static fn (array $d): string => (string) ($d['original_name'] ?? 'file'), array_slice($docs, 0, 8));

            return [
                'content' => 'Which document on **' . ($case['case_number'] ?? '') . '**? '
                    . ($names !== [] ? 'Available: ' . implode(', ', $names) : 'No documents on this case.'),
                'type' => 'text',
            ];
        }

        $preview = [
            'Case' => (string) ($case['case_number'] ?? ''),
            'Document' => (string) ($document['original_name'] ?? 'File'),
        ];

        return self::buildDraftResponse(
            'delete_document',
            ['case_id' => $caseId, 'document_id' => (int) $document['id']],
            $preview,
            '**Delete document** draft. **Confirm** to remove this file from the case.'
        );
    }

    /** @param array<string, mixed> $payload */
    private static function executeDeleteDocument(array $payload): string
    {
        $caseId = (int) ($payload['case_id'] ?? 0);
        $documentId = (int) ($payload['document_id'] ?? 0);
        CaseService::deleteDocument($documentId, $caseId);

        return '**Document removed** from the case.';
    }

    /** @return array{content: string, type: string, draft?: array<string, mixed>} */
    private static function draftDeletePayment(string $message): array
    {
        if (!Auth::can(RoleAccess::PERMISSION_PAYMENTS)) {
            return ['content' => 'Your role cannot delete payments.', 'type' => 'text'];
        }

        $payment = null;
        if (preg_match('/\bPAY[-\s]?([A-Z0-9-]+)\b/i', $message, $matches)) {
            $number = 'PAY-' . strtoupper(str_replace(' ', '-', trim($matches[1])));
            $payment = Database::fetch('SELECT * FROM payments WHERE payment_number = ?', [$number]);
        }

        if ($payment === null && preg_match('/\bpayment\s+#?(\d+)\b/i', $message, $matches)) {
            $payment = Database::fetch('SELECT * FROM payments WHERE id = ?', [(int) $matches[1]]);
        }

        if ($payment === null) {
            return [
                'content' => 'Which payment should I delete? Use the payment number (e.g. **PAY-2026-0001**) or payment ID.',
                'type' => 'text',
            ];
        }

        $preview = [
            'Payment' => (string) ($payment['payment_number'] ?? $payment['id']),
            'Amount' => formatCurrency((float) ($payment['amount'] ?? 0)),
            'Warning' => 'Permanent',
        ];

        return self::buildDraftResponse(
            'delete_payment',
            ['payment_id' => (int) $payment['id']],
            $preview,
            '**Delete payment** draft. **Confirm** to remove this payment record.'
        );
    }

    /** @param array<string, mixed> $payload */
    private static function executeDeletePayment(array $payload): string
    {
        CaseService::deletePayment((int) ($payload['payment_id'] ?? 0));

        return '**Payment deleted.** Invoice balances have been updated.';
    }

    /** @return array{content: string, type: string, draft?: array<string, mixed>} */
    private static function draftDeleteInvoice(string $message): array
    {
        if (!Auth::can(RoleAccess::PERMISSION_PAYMENTS)) {
            return ['content' => 'Your role cannot delete invoices.', 'type' => 'text'];
        }

        $invoice = assistantFindInvoiceFromMessage($message);
        if ($invoice === null) {
            return [
                'content' => 'Which invoice should I delete? Example: _Delete invoice INV-' . date('Y') . '-0001_.',
                'type' => 'text',
            ];
        }

        $preview = [
            'Invoice' => (string) ($invoice['invoice_number'] ?? ''),
            'Client' => clientFullName($invoice),
            'Total' => formatCurrency((float) ($invoice['total'] ?? 0)),
            'Warning' => 'Permanent',
        ];

        return self::buildDraftResponse(
            'delete_invoice',
            ['invoice_id' => (int) $invoice['id']],
            $preview,
            '**Delete invoice** draft. **Confirm** only if this invoice should be removed permanently.'
        );
    }

    /** @param array<string, mixed> $payload */
    private static function executeDeleteInvoice(array $payload): string
    {
        $invoiceId = (int) ($payload['invoice_id'] ?? 0);
        $invoice = Database::fetch('SELECT invoice_number FROM invoices WHERE id = ?', [$invoiceId]);
        if (!$invoice) {
            throw new RuntimeException('Invoice not found.');
        }
        $number = (string) ($invoice['invoice_number'] ?? $invoiceId);
        CaseService::deleteInvoice($invoiceId);

        return '**Invoice deleted:** ' . $number . '.';
    }

    /** @return ?array<string, mixed> */
    private static function resolveCaseFromMessage(string $message): ?array
    {
        $case = assistantFindCaseByReferenceFromMessage($message);
        if ($case !== null) {
            return $case;
        }

        $ref = assistantExtractCaseReferenceFromMessage($message);
        if ($ref !== '') {
            return assistantFindCaseByReference($ref);
        }

        if (preg_match('/\bcase\s+id\s+(\d+)\b/i', $message, $m)) {
            return CaseService::getCaseById((int) $m[1]);
        }

        return null;
    }
}
