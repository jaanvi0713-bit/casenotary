<?php

declare(strict_types=1);

class AssistantMessageDrafts
{
    public const TYPE_PAYMENT_REMINDER = 'payment_reminder';
    public const TYPE_QUOTATION_FOLLOWUP = 'quotation_followup';
    public const TYPE_MISSING_DOCUMENT = 'missing_document';
    public const TYPE_CASE_UPDATE = 'case_update_email';
    public const TYPE_APPOINTMENT_CONFIRMATION = 'appointment_confirmation';

    public static function detectType(string $message): ?string
    {
        $lower = strtolower(trim($message));
        if ($lower === '') {
            return null;
        }

        if (AssistantReminders::detectType($message) !== null) {
            return null;
        }

        $hasDraftVerb = (bool) preg_match('/\b(draft|write|compose|prepare|message)\b/i', $message);

        if (($hasDraftVerb && preg_match('/\bpayment reminder\b/i', $lower))
            || ($hasDraftVerb && preg_match('/\b(remind|reminder)\b.*\b(invoice|payment|unpaid|overdue)\b/i', $lower))
            || ($hasDraftVerb && preg_match('/\b(unpaid|overdue)\b.*\b(invoice|payment)\b/i', $lower))) {
            return self::TYPE_PAYMENT_REMINDER;
        }

        if ($hasDraftVerb && (
            preg_match('/\b(follow[- ]?up|chase)\b.*\b(quotation|quote)\b/i', $lower)
            || preg_match('/\b(quotation|quote)\b.*\bfollow[- ]?up\b/i', $lower)
        )) {
            return self::TYPE_QUOTATION_FOLLOWUP;
        }

        if ($hasDraftVerb && (
            preg_match('/\b(ask|request)\b.*\b(client|them)\b.*\b(upload|send|provide|submit)\b/i', $lower)
            || preg_match('/\b(upload|send|provide|submit)\b.*\b(document|documents|id|proof|passport|file|files)\b/i', $lower)
            || preg_match('/\bmissing document\b/i', $lower)
        )) {
            return self::TYPE_MISSING_DOCUMENT;
        }

        if ($hasDraftVerb && preg_match('/\b(case update|update email|status update|progress update)\b/i', $lower)) {
            return self::TYPE_CASE_UPDATE;
        }

        if ($hasDraftVerb && (
            preg_match('/\bappointment confirmation\b/i', $lower)
            || preg_match('/\b(confirmation|confirm)\b.*\b(appointment|meeting)\b.*\b(email|message|letter)\b/i', $lower)
            || preg_match('/\bdraft\b.*\bappointment\b.*\bconfirm/i', $lower)
        )) {
            return self::TYPE_APPOINTMENT_CONFIRMATION;
        }

        return null;
    }

    /** @return array{content: string, type: string} */
    public static function handle(string $type, string $message): array
    {
        return match ($type) {
            self::TYPE_PAYMENT_REMINDER => self::paymentReminder($message),
            self::TYPE_QUOTATION_FOLLOWUP => self::quotationFollowUp($message),
            self::TYPE_MISSING_DOCUMENT => self::missingDocumentRequest($message),
            self::TYPE_CASE_UPDATE => self::caseUpdateEmail($message),
            self::TYPE_APPOINTMENT_CONFIRMATION => self::appointmentConfirmation($message),
            default => [
                'content' => 'I can draft: **payment reminder**, **quotation follow-up**, **missing document request**, **case update email**, or **appointment confirmation**. Example: _Write a reminder for unpaid invoice INV-' . date('Y') . '-0001_.',
                'type' => 'text',
            ],
        };
    }

    /** @return array{content: string, type: string} */
    private static function paymentReminder(string $message): array
    {
        $refs = assistantExtractInvoiceReferences($message);
        $invoice = assistantFindInvoiceFromMessage($message);

        if ($invoice === null && $refs !== []) {
            $example = 'INV-' . date('Y') . '-0001';

            return [
                'content' => 'I could not find invoice **' . $refs[0] . '**. Check the number on **Payments** — invoices are usually formatted like **'
                    . $example . '**. You can also include a **client name** or **case number**.',
                'type' => 'text',
            ];
        }

        if ($invoice === null) {
            return [
                'content' => 'Which invoice should I remind them about? Example: _Write a reminder for unpaid invoice INV-'
                    . date('Y') . '-0001_ or include a **case number** / **client name**.',
                'type' => 'text',
            ];
        }

        $clientName = clientFullName($invoice);
        $total = (float) ($invoice['total'] ?? 0);
        $due = CaseService::getInvoiceRemainingBalance($invoice);
        $dueDate = !empty($invoice['due_date']) ? formatDate($invoice['due_date']) : 'as agreed';
        $invoiceNumber = (string) ($invoice['invoice_number'] ?? 'Invoice');
        $caseRef = !empty($invoice['case_number']) ? ' (case ' . $invoice['case_number'] . ')' : '';
        $payInfo = PaymentGatewayService::paymentInfoSummary($invoice);
        $payLine = $payInfo['payment_link'] !== ''
            ? "\nYou can pay securely online here: " . $payInfo['payment_link']
            : '';

        $body = "Subject: Payment reminder — {$invoiceNumber}\n\n"
            . "Dear {$clientName},\n\n"
            . "We hope you are well. This is a friendly reminder that invoice **{$invoiceNumber}**{$caseRef} for **"
            . formatCurrency($total) . "** has an outstanding balance of **" . formatCurrency($due) . "**.\n\n"
            . "• Invoice total: " . formatCurrency($total) . "\n"
            . "• Amount due: " . formatCurrency($due) . "\n"
            . "• Due date: {$dueDate}"
            . $payLine . "\n\n"
            . "If you have already sent payment, please disregard this message. Otherwise, we would appreciate settlement at your earliest convenience.\n\n"
            . "Please contact us if you have any questions about this invoice.\n\n"
            . self::signatureBlock();

        return self::wrapDraft('Payment reminder', $body, [
            'Invoice' => $invoiceNumber,
            'Client' => $clientName,
            'Amount due' => formatCurrency($due),
        ]);
    }

    /** @return array{content: string, type: string} */
    private static function quotationFollowUp(string $message): array
    {
        $case = self::findCase($message);
        $quotation = null;

        if ($case !== null) {
            $quotations = CaseService::getQuotations((int) $case['id']);
            $quotation = $quotations[0] ?? null;
        }

        if ($case === null) {
            $clientName = assistantExtractClientNameFromActionMessage($message);
            $clientId = $clientName !== '' ? assistantResolveClientId($clientName) : null;
            if ($clientId !== null) {
                $case = Database::fetch(
                    'SELECT cs.*, cl.first_name, cl.last_name, cl.company_name
                     FROM cases cs
                     JOIN clients cl ON cl.id = cs.client_id
                     WHERE cs.client_id = ?
                     ORDER BY cs.updated_at DESC LIMIT 1',
                    [$clientId]
                ) ?: null;
                if ($case !== null) {
                    $quotations = CaseService::getQuotations((int) $case['id']);
                    $quotation = $quotations[0] ?? null;
                }
            }
        }

        if ($case === null) {
            return [
                'content' => 'Which **case** or **client** is the quotation for? Example: _Draft quotation follow-up for case CASE-2026-0001_.',
                'type' => 'text',
            ];
        }

        $clientName = clientFullName($case);
        $caseNumber = (string) ($case['case_number'] ?? '');
        $quoteNumber = (string) ($quotation['quotation_number'] ?? 'our recent quotation');
        $quoteTotal = isset($quotation['total']) ? formatCurrency((float) $quotation['total']) : 'the quoted fee';

        $body = "Subject: Following up — quotation {$quoteNumber}\n\n"
            . "Dear {$clientName},\n\n"
            . "I am following up regarding quotation **{$quoteNumber}** for your matter **{$caseNumber}** — {$case['title']}.\n\n"
            . "The quoted fee is **{$quoteTotal}**. If you would like to proceed, please let us know and we can confirm next steps, scheduling, and any documents you should bring.\n\n"
            . "If you have questions about the scope of work or need a revised quote, reply to this message and we will be happy to help.\n\n"
            . self::signatureBlock();

        return self::wrapDraft('Quotation follow-up', $body, [
            'Case' => $caseNumber,
            'Client' => $clientName,
            'Quotation' => $quoteNumber,
        ]);
    }

    /** @return array{content: string, type: string} */
    private static function missingDocumentRequest(string $message): array
    {
        $case = self::findCase($message);
        $clientName = assistantExtractClientNameFromActionMessage($message);
        $client = null;

        if ($case !== null) {
            $client = ClientService::getById((int) $case['client_id']);
        } elseif ($clientName !== '') {
            $clientId = assistantResolveClientId($clientName);
            $client = $clientId ? ClientService::getById($clientId) : null;
            if ($clientId) {
                $case = Database::fetch(
                    'SELECT cs.*, cl.first_name, cl.last_name, cl.company_name
                     FROM cases cs JOIN clients cl ON cl.id = cs.client_id
                     WHERE cs.client_id = ? ORDER BY cs.updated_at DESC LIMIT 1',
                    [$clientId]
                ) ?: null;
            }
        }

        $documentLabel = self::extractDocumentLabel($message);
        $greetingName = $client ? clientFullName($client) : ($clientName !== '' ? $clientName : '[Client name]');
        $caseLine = $case
            ? ' for case **' . ($case['case_number'] ?? '') . '** — ' . ($case['title'] ?? 'your matter')
            : '';

        $body = "Subject: Documents needed{$caseLine}\n\n"
            . "Dear {$greetingName},\n\n"
            . "Thank you for your instruction. To move your matter forward, please **upload or send** the following:\n\n"
            . "• **{$documentLabel}**\n\n"
            . "You can upload files through the **client portal** on your case page, or reply to this email with secure attachments if that is easier.\n\n"
            . "If anything is unclear or you need help obtaining a document, let us know and we will guide you.\n\n"
            . self::signatureBlock();

        $preview = ['Document requested' => $documentLabel];
        if ($case) {
            $preview['Case'] = (string) ($case['case_number'] ?? '');
        }
        if ($greetingName !== '[Client name]') {
            $preview['Client'] = $greetingName;
        }

        return self::wrapDraft('Missing document request', $body, $preview);
    }

    /** @return array{content: string, type: string} */
    private static function caseUpdateEmail(string $message): array
    {
        $case = self::findCase($message);
        if ($case === null) {
            return [
                'content' => 'Which **case** is this update for? Example: _Draft case update email for CASE-2026-0001_.',
                'type' => 'text',
            ];
        }

        $clientName = clientFullName($case);
        $status = CaseService::statusLabel((string) ($case['status'] ?? 'pending'));
        $instructions = trim((string) ($case['client_instructions'] ?? ''));
        $instructionsBlock = $instructions !== ''
            ? "\n\n**Next steps for you:**\n" . $instructions
            : "\n\nWe will contact you if any further documents or action are required.";

        $body = "Subject: Update on your case — " . ($case['case_number'] ?? '') . "\n\n"
            . "Dear {$clientName},\n\n"
            . "We are writing with a brief update on your matter **" . ($case['case_number'] ?? '') . "** — "
            . ($case['title'] ?? 'your case') . ".\n\n"
            . "• **Current status:** {$status}\n"
            . "• **Service:** " . ($case['service_type'] ?? 'Notarial services')
            . $instructionsBlock . "\n\n"
            . "You can view documents and messages at any time in your **client portal**. Reply to this email if you have questions.\n\n"
            . self::signatureBlock();

        return self::wrapDraft('Case update email', $body, [
            'Case' => (string) ($case['case_number'] ?? ''),
            'Client' => $clientName,
            'Status' => $status,
        ]);
    }

    /** @return array{content: string, type: string} */
    private static function appointmentConfirmation(string $message): array
    {
        $appointment = assistantResolveAppointment($message);
        if ($appointment === null) {
            $clientName = assistantExtractClientNameFromActionMessage($message);
            $clientId = $clientName !== '' ? assistantResolveClientId($clientName) : null;
            if ($clientId !== null) {
                $rows = assistantFindAppointments($message);
                $appointment = $rows[0] ?? null;
            }
        }

        if ($appointment === null) {
            return [
                'content' => 'Which **appointment** should I confirm? Example: _Draft appointment confirmation for Louis Macwell tomorrow at 2pm_ or include an **appointment ID**.',
                'type' => 'text',
            ];
        }

        $client = ClientService::getById((int) ($appointment['client_id'] ?? 0));
        $clientName = $client ? clientFullName($client) : clientFullName($appointment);
        $start = appointmentStart($appointment);
        $when = $start ? formatDateTime($start) : '[date and time]';
        $location = trim((string) ($appointment['location'] ?? ''));
        $locationLine = $location !== '' ? $location : 'To be confirmed — we will send details if needed.';
        $title = (string) ($appointment['title'] ?? 'Notary appointment');

        $body = "Subject: Appointment confirmation — {$title}\n\n"
            . "Dear {$clientName},\n\n"
            . "This confirms your appointment with us:\n\n"
            . "• **Purpose:** {$title}\n"
            . "• **When:** {$when}\n"
            . "• **Where:** {$locationLine}\n\n"
            . "Please bring valid **photo ID** and any documents we have asked you to prepare. If you need to reschedule, contact us as soon as possible.\n\n"
            . self::signatureBlock();

        return self::wrapDraft('Appointment confirmation', $body, [
            'Client' => $clientName,
            'When' => $when,
            'Title' => $title,
        ]);
    }

    /**
     * @param array<string, string> $meta
     * @return array{content: string, type: string}
     */
    private static function wrapDraft(string $label, string $body, array $meta = []): array
    {
        $lines = ["**{$label} draft** _(copy and edit before sending)_", ''];

        if ($meta !== []) {
            foreach ($meta as $key => $value) {
                if ((string) $value !== '') {
                    $lines[] = '• **' . $key . ':** ' . $value;
                }
            }
            $lines[] = '';
        }

        $lines[] = "```";
        $lines[] = trim($body);
        $lines[] = "```";
        $lines[] = '';
        $lines[] = '_Send via your email client or client portal messaging after reviewing._';

        return [
            'content' => implode("\n", $lines),
            'type' => 'text',
        ];
    }

    private static function signatureBlock(): string
    {
        $settings = getCompanySettings();
        $lines = ['Kind regards,', (string) ($settings['company_name'] ?? companyBrandName())];

        $email = trim((string) ($settings['email'] ?? ''));
        if ($email !== '') {
            $lines[] = $email;
        }

        $phone = trim((string) ($settings['phone'] ?? ''));
        if ($phone !== '') {
            $lines[] = $phone;
        }

        return implode("\n", $lines);
    }

  private static function extractDocumentLabel(string $message): string
    {
        if (preg_match('/\b(id proof|id document|identification|photo id|government id)\b/i', $message)) {
            return 'Valid government-issued photo ID';
        }
        if (preg_match('/\b(passport)\b/i', $message)) {
            return 'Passport (original)';
        }
        if (preg_match('/\b(proof of address|utility bill|bank statement)\b/i', $message)) {
            return 'Proof of address (recent utility bill or bank statement)';
        }
        if (preg_match('/\bunsigned (document|documents|deed|contract)\b/i', $message, $m)) {
            return 'Unsigned ' . strtolower($m[1]);
        }
        if (preg_match('/\b(upload|send|provide)\s+(?:the\s+)?(.+?)(?:\s+for|\s+to|\s*$)/i', $message, $m)) {
            $label = trim($m[1]);
            if ($label !== '' && mb_strlen($label) < 80) {
                return ucfirst($label);
            }
        }

        return 'Requested document(s)';
    }

    /** @return ?array<string, mixed> */
    private static function findCase(string $message): ?array
    {
        if (preg_match('/case[- ]?#?\s*([A-Z0-9-]+)/i', $message, $m)) {
            $case = assistantFindCaseByReference(trim($m[1]));
            if ($case) {
                return $case;
            }
        }

        return null;
    }
}
