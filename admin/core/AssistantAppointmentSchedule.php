<?php

declare(strict_types=1);

class AssistantAppointmentSchedule
{
    private const SESSION_KEY = 'assistant_appointment_schedule';

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
     * @param array<string, string> $previewWithoutStatus
     * @return array{content: string, type: string}
     */
    public static function begin(array $payload, array $previewWithoutStatus): array
    {
        $_SESSION[self::SESSION_KEY] = [
            'active'  => true,
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
                'content' => 'No appointment is waiting for a status. Say e.g. _Schedule appointment for Marie Curie tomorrow at 2pm._',
                'type'    => 'text',
            ];
        }

        $status = assistantParseAppointmentStatusChoice($message);
        if ($status === null) {
            $preview = is_array($state['preview'] ?? null) ? $state['preview'] : [];

            return self::statusPrompt($preview, true);
        }

        $payload = is_array($state['payload'] ?? null) ? $state['payload'] : [];
        $preview = is_array($state['preview'] ?? null) ? $state['preview'] : [];
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

        return [
            'content' => implode("\n", $lines),
            'type'    => 'onboarding',
        ];
    }
}
