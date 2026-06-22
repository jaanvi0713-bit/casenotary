<?php

declare(strict_types=1);

class AssistantReminders
{
    public const TYPE_PAYMENT = 'payment';
    public const TYPE_APPOINTMENT = 'appointment';
    public const TYPE_CASE = 'case';

    public static function detectType(string $message): ?string
    {
        $lower = strtolower(trim($message));
        if ($lower === '') {
            return null;
        }

        if (preg_match('/\b(draft|write|compose|prepare)\b/i', $message)) {
            return null;
        }

        $wantsSend = (bool) preg_match(
            '/\b(send|create|issue|trigger|fire)\b.*\b(remind|reminder)\b/i',
            $lower
        ) || (bool) preg_match('/\b(remind|reminder)\b.*\b(send|email|notify|out)\b/i', $lower)
            || (bool) preg_match('/\bcreate\s+(?:a\s+)?(?:payment|appointment|case)?\s*reminder\b/i', $lower);

        if (!$wantsSend) {
            return null;
        }

        if (preg_match('/\b(payment|invoice|unpaid|overdue)\b/i', $lower)
            || assistantExtractInvoiceReferences($message) !== []
            || assistantFindInvoiceFromMessage($message) !== null) {
            return self::TYPE_PAYMENT;
        }

        if (preg_match('/\b(appointment|meeting|visit)\b/i', $lower)
            || assistantResolveAppointment($message) !== null) {
            return self::TYPE_APPOINTMENT;
        }

        if (preg_match('/\b(case|matter)\b/i', $lower)
            || assistantFindCaseByReferenceFromMessage($message) !== null) {
            return self::TYPE_CASE;
        }

        return self::TYPE_PAYMENT;
    }

    /** @return array{content: string, type: string, draft?: array<string, mixed>} */
    public static function handle(string $type, string $message): array
    {
        return match ($type) {
            self::TYPE_PAYMENT => self::draftPaymentReminder($message),
            self::TYPE_APPOINTMENT => self::draftAppointmentReminder($message),
            self::TYPE_CASE => self::draftCaseReminder($message),
            default => [
                'content' => 'I can **send** reminders for **payments**, **appointments**, or **cases**. Example: _Create payment reminder for invoice INV-'
                    . date('Y') . '-0001_. To draft text only, say _Write a reminder for..._.',
                'type' => 'text',
            ],
        };
    }

    /** @return array{content: string, type: string, draft?: array<string, mixed>} */
    private static function draftPaymentReminder(string $message): array
    {
        $refs = assistantExtractInvoiceReferences($message);
        $invoice = assistantFindInvoiceFromMessage($message);

        if ($invoice === null && $refs !== []) {
            return [
                'content' => 'I could not find invoice **' . $refs[0] . '**. Check the number on **Payments**.',
                'type' => 'text',
            ];
        }

        if ($invoice === null) {
            return [
                'content' => 'Which invoice should I send a payment reminder for? Example: _Create payment reminder for invoice INV-'
                    . date('Y') . '-0001_.',
                'type' => 'text',
            ];
        }

        if (!assistantInvoiceIsOutstanding($invoice)) {
            return [
                'content' => 'Invoice **' . ($invoice['invoice_number'] ?? '') . '** is already fully paid — no reminder needed.',
                'type' => 'text',
            ];
        }

        $clientName = clientFullName($invoice);
        $due = CaseService::getInvoiceRemainingBalance($invoice);
        $invoiceNumber = (string) ($invoice['invoice_number'] ?? 'Invoice');
        $email = trim((string) ($invoice['email'] ?? ''));

        if ($email === '') {
            return [
                'content' => '**' . $clientName . '** does not have an email address on file. Add one on the client record first.',
                'type' => 'text',
            ];
        }

        $preview = [
            'Type' => 'Payment reminder email',
            'Invoice' => $invoiceNumber,
            'Client' => $clientName,
            'Email' => $email,
            'Amount due' => formatCurrency($due),
        ];

        return AssistantActions::createDraft(
            'send_reminder',
            [
                'reminder_type' => self::TYPE_PAYMENT,
                'invoice_id' => (int) ($invoice['id'] ?? 0),
            ],
            $preview,
            'This will **email a payment reminder** to **' . $clientName . '** for invoice **' . $invoiceNumber . '**. Click **Confirm** to send.'
        );
    }

    /** @return array{content: string, type: string, draft?: array<string, mixed>} */
    private static function draftAppointmentReminder(string $message): array
    {
        $appointment = assistantResolveAppointment($message);

        if ($appointment === null) {
            $candidates = assistantFindAppointments($message);
            if (count($candidates) > 1) {
                return [
                    'content' => assistantDescribeAppointments($candidates),
                    'type' => 'text',
                ];
            }

            return [
                'content' => 'Which appointment should I remind the client about? Example: _Create appointment reminder for Louis Macwell_ or include the **date/time**.',
                'type' => 'text',
            ];
        }

        $status = normalizeAppointmentStatus((string) ($appointment['status'] ?? 'scheduled'));
        if (!in_array($status, ['scheduled', 'confirmed', 'rescheduled'], true)) {
            return [
                'content' => 'That appointment is **' . assistantAppointmentStatusLabel($status) . '** — reminders can only be sent for scheduled, confirmed, or rescheduled appointments.',
                'type' => 'text',
            ];
        }

        $clientName = clientFullName($appointment);
        $email = trim((string) ($appointment['email'] ?? ''));
        if ($email === '') {
            return [
                'content' => '**' . $clientName . '** does not have an email address on file.',
                'type' => 'text',
            ];
        }

        $start = appointmentStart($appointment);
        $when = $start !== null ? formatDateTime($start) : 'Unknown time';
        $title = (string) ($appointment['title'] ?? 'Appointment');

        $preview = [
            'Type' => 'Appointment reminder email',
            'Client' => $clientName,
            'Email' => $email,
            'Appointment' => $title,
            'When' => $when,
        ];

        return AssistantActions::createDraft(
            'send_reminder',
            [
                'reminder_type' => self::TYPE_APPOINTMENT,
                'appointment_id' => (int) ($appointment['id'] ?? 0),
            ],
            $preview,
            'This will **email an appointment reminder** to **' . $clientName . '** for **' . $title . '** (' . $when . '). Click **Confirm** to send.'
        );
    }

    /** @return array{content: string, type: string, draft?: array<string, mixed>} */
    private static function draftCaseReminder(string $message): array
    {
        $case = assistantFindCaseByReferenceFromMessage($message);

        if ($case === null) {
            return [
                'content' => 'Which case should I send a reminder for? Example: _Create case reminder for CASE-' . date('Y') . '-0001_.',
                'type' => 'text',
            ];
        }

        $caseNumber = (string) ($case['case_number'] ?? '');
        $clientName = clientFullName($case);
        $caseId = (int) ($case['id'] ?? 0);

        $preview = [
            'Type' => 'Case workflow reminder (portal notification)',
            'Case' => $caseNumber !== '' ? $caseNumber : ('Case #' . $caseId),
            'Client' => $clientName,
            'Title' => (string) ($case['title'] ?? ''),
        ];

        return AssistantActions::createDraft(
            'send_reminder',
            [
                'reminder_type' => self::TYPE_CASE,
                'case_id' => $caseId,
            ],
            $preview,
            'This will **notify the client and assigned staff** about outstanding items on case **' . ($caseNumber !== '' ? $caseNumber : $caseId) . '**. Click **Confirm** to send.'
        );
    }
}

/**
 * @return array<string, mixed>|null
 */
function assistantFindCaseByReferenceFromMessage(string $message): ?array
{
    $ref = assistantExtractCaseReferenceFromMessage($message);
    if ($ref !== '') {
        return assistantFindCaseByReference($ref);
    }

    if (preg_match('/\bcase\s+#?(\d+)\b/i', $message, $m)) {
        return assistantFindCaseByReference('CASE-' . date('Y') . '-' . str_pad($m[1], 4, '0', STR_PAD_LEFT))
            ?? Database::fetch('SELECT cs.*, cl.first_name, cl.last_name, cl.company_name FROM cases cs JOIN clients cl ON cl.id = cs.client_id WHERE cs.id = ?', [(int) $m[1]]);
    }

    $clientName = assistantExtractClientNameFromActionMessage($message);
    if ($clientName !== '') {
        $clientId = assistantResolveClientId($clientName);
        if ($clientId !== null) {
            return Database::fetch(
                'SELECT cs.*, cl.first_name, cl.last_name, cl.company_name
                 FROM cases cs
                 JOIN clients cl ON cl.id = cs.client_id
                 WHERE cs.client_id = ?
                 ORDER BY cs.updated_at DESC LIMIT 1',
                [$clientId]
            ) ?: null;
        }
    }

    return null;
}
