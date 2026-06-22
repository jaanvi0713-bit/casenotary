<?php

declare(strict_types=1);

class AssistantAppointmentSchedule
{
    private const SESSION_KEY = 'assistant_appointment_schedule';

    private const STEP_CLIENT = 'client';
    private const STEP_DATETIME = 'datetime';
    private const STEP_STATUS = 'status';

    /** @var list<string> */
    private const STATUS_OPTIONS = [
        'scheduled',
        'confirmed',
        'rescheduled',
        'cancelled',
        'requested',
    ];

    public static function isActive(): bool
    {
        return !empty($_SESSION[self::SESSION_KEY]['active']);
    }

    public static function clear(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $preview
     * @return array{content: string, type: string}
     */
    public static function beginMissingClient(array $payload, array $preview): array
    {
        $_SESSION[self::SESSION_KEY] = [
            'active'  => true,
            'step'    => self::STEP_CLIENT,
            'payload' => $payload,
            'preview' => $preview,
        ];

        $lines = [
            'I have the **date/time** — which **client** is this appointment for?',
            '',
        ];
        foreach ($preview as $label => $value) {
            if ((string) $value === '') {
                continue;
            }
            $lines[] = '• **' . $label . ':** ' . $value;
        }
        $lines[] = '';
        $lines[] = 'Reply with the client name, e.g. _Louis Macwell_.';
        $lines[] = 'Say **cancel** to stop.';

        return [
            'content' => implode("\n", $lines),
            'type'    => 'onboarding',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $preview
     * @return array{content: string, type: string}
     */
    public static function beginMissingDateTime(array $payload, array $preview): array
    {
        $_SESSION[self::SESSION_KEY] = [
            'active'  => true,
            'step'    => self::STEP_DATETIME,
            'payload' => $payload,
            'preview' => $preview,
        ];

        $lines = [
            'Which **date and time** should I book for **' . ($preview['Client'] ?? 'this client') . '**?',
            '',
        ];
        foreach ($preview as $label => $value) {
            if ((string) $value === '' || $label === 'Client') {
                continue;
            }
            $lines[] = '• **' . $label . ':** ' . $value;
        }
        $lines[] = '';
        $lines[] = 'Example: _tomorrow at 3pm_ or _20 Jun 2026 at 10:00_.';
        $lines[] = 'Say **cancel** to stop.';

        return [
            'content' => implode("\n", $lines),
            'type'    => 'onboarding',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $previewWithoutStatus
     * @return array{content: string, type: string}
     */
    public static function begin(array $payload, array $previewWithoutStatus): array
    {
        $_SESSION[self::SESSION_KEY] = [
            'active'  => true,
            'step'    => self::STEP_STATUS,
            'payload' => $payload,
            'preview' => $previewWithoutStatus,
        ];

        return self::statusPrompt($previewWithoutStatus);
    }

    /**
     * @return array{content: string, type: string, draft?: array<string, mixed>}
     */
    public static function handle(string $message): array
    {
        $state = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($state) || empty($state['active'])) {
            return [
                'content' => 'No appointment setup is in progress. Say e.g. _Schedule appointment for Marie Curie tomorrow at 2pm._',
                'type'    => 'text',
            ];
        }

        $step = (string) ($state['step'] ?? self::STEP_STATUS);
        $payload = is_array($state['payload'] ?? null) ? $state['payload'] : [];
        $preview = is_array($state['preview'] ?? null) ? $state['preview'] : [];

        return match ($step) {
            self::STEP_CLIENT => self::handleClientStep($message, $payload, $preview),
            self::STEP_DATETIME => self::handleDateTimeStep($message, $payload, $preview),
            default => self::handleStatusStep($message, $payload, $preview),
        };
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $preview
     * @return array{content: string, type: string, draft?: array<string, mixed>}
     */
    private static function handleClientStep(string $message, array $payload, array $preview): array
    {
        $clientName = assistantSanitizeExtractedClientName(trim($message));
        if ($clientName === '') {
            $clientName = assistantSanitizeExtractedClientName(assistantExtractClientNameFromActionMessage($message));
        }

        if ($clientName === '') {
            return [
                'content' => 'Please reply with the **client’s full name** (e.g. _Louis Macwell_), or say **cancel**.',
                'type'    => 'onboarding',
            ];
        }

        $clientId = assistantResolveClientId($clientName);
        if ($clientId === null) {
            return [
                'content' => assistantCreateCaseMissingClientMessage($clientName) . "\n\nOr say **cancel** to stop.",
                'type'    => 'onboarding',
            ];
        }

        $client = ClientService::getById($clientId);
        $payload['client_id'] = $clientId;
        $preview['Client'] = clientFullName($client ?? []);
        if (isset($preview['When'])) {
            $preview['Starts'] = $preview['When'];
            unset($preview['When']);
        }

        if (trim((string) ($payload['starts_at'] ?? '')) === '') {
            $_SESSION[self::SESSION_KEY] = [
                'active'  => true,
                'step'    => self::STEP_DATETIME,
                'payload' => $payload,
                'preview' => $preview,
            ];

            return self::beginMissingDateTime($payload, $preview);
        }

        return self::advanceToStatusOrDraft($message, $payload, $preview);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $preview
     * @return array{content: string, type: string, draft?: array<string, mixed>}
     */
    private static function handleDateTimeStep(string $message, array $payload, array $preview): array
    {
        $scheduleTimes = assistantExtractScheduleTimes($message);
        $startsAt = $scheduleTimes['starts_at'] !== '' ? $scheduleTimes['starts_at'] : parseFlexibleDateTime($message);

        if ($startsAt === '') {
            return [
                'content' => 'I couldn’t read that date/time. Try _tomorrow at 3pm_ or _20 Jun 2026 at 10:00_, or say **cancel**.',
                'type'    => 'onboarding',
            ];
        }

        $endsAt = $scheduleTimes['ends_at'];
        if ($endsAt === '' && trim((string) ($payload['ends_at'] ?? '')) !== '') {
            $endsAt = (string) $payload['ends_at'];
        }

        $payload['starts_at'] = $startsAt;
        $payload['ends_at'] = $endsAt;
        $preview['Starts'] = formatDateTime($startsAt);
        $preview['Ends'] = $endsAt !== ''
            ? formatDateTime($endsAt)
            : formatDateTime(date('Y-m-d H:i:s', strtotime($startsAt . ' +1 hour')));

        return self::advanceToStatusOrDraft($message, $payload, $preview);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $preview
     * @return array{content: string, type: string, draft?: array<string, mixed>}
     */
    private static function handleStatusStep(string $message, array $payload, array $preview): array
    {
        $status = assistantParseAppointmentStatusChoice($message);
        if ($status === null) {
            return self::statusPrompt($preview, true);
        }

        self::clear();

        $payload['status'] = $status;
        $preview['Status'] = assistantAppointmentStatusLabel($status);

        return AssistantActions::createDraft(
            'schedule_appointment',
            $payload,
            $preview,
            'Appointment draft ready. Click **Confirm** to book on the calendar.'
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $preview
     * @return array{content: string, type: string, draft?: array<string, mixed>}
     */
    private static function advanceToStatusOrDraft(string $message, array $payload, array $preview): array
    {
        $explicitStatus = assistantParseAppointmentStatusChoice($message);
        if ($explicitStatus !== null) {
            self::clear();
            $payload['status'] = $explicitStatus;
            $preview['Status'] = assistantAppointmentStatusLabel($explicitStatus);

            return AssistantActions::createDraft(
                'schedule_appointment',
                $payload,
                $preview,
                'Appointment draft ready. Click **Confirm** to book on the calendar.'
            );
        }

        return self::begin($payload, $preview);
    }

    /**
     * @param array<string, string> $preview
     * @return array{content: string, type: string}
     */
    private static function statusPrompt(array $preview, bool $invalid = false): array
    {
        $lines = [];
        if ($invalid) {
            $lines[] = 'I didn’t recognize that status. Please reply with one of the options below.';
            $lines[] = '';
        } else {
            $lines[] = 'Almost done — which **status** should this appointment have?';
            $lines[] = '';
            if ($preview !== []) {
                foreach ($preview as $label => $value) {
                    if ((string) $value === '') {
                        continue;
                    }
                    $lines[] = '• **' . $label . ':** ' . $value;
                }
                $lines[] = '';
            }
        }

        $lines[] = 'Reply with one of:';
        foreach (self::STATUS_OPTIONS as $status) {
            $lines[] = '• **' . assistantAppointmentStatusLabel($status) . '**';
        }
        $lines[] = '';
        $lines[] = 'Example: _Confirmed_';
        $lines[] = 'Say **cancel** to stop.';

        return [
            'content' => implode("\n", $lines),
            'type'    => 'onboarding',
        ];
    }
}
