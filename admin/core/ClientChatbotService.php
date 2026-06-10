<?php

declare(strict_types=1);

class ClientChatbotService
{
    public static function reply(string $message): string
    {
        $normalized = strtolower(trim($message));

        if ($normalized === '' || preg_match('/^(hi|hello|hey|help)$/', $normalized)) {
            return self::welcome();
        }

        if (preg_match('/\b(who are you|what can you do|help me)\b/', $normalized)) {
            return self::capabilities();
        }

        $clientId = Auth::clientId();
        if ($clientId === null) {
            return 'Please log in to use the assistant.';
        }

        if (preg_match('/\b(appointment|appointments|schedule|meeting|when is my)\b/', $normalized)) {
            return self::replyAppointments($clientId, $message);
        }

        if (preg_match('/\b(payment|payments|invoice|invoices|bill|owe|due)\b/', $normalized)) {
            return self::replyPayments($clientId);
        }

        if (preg_match('/\b(document|documents|upload|file|bring|need to bring|what do i need)\b/', $normalized)) {
            return self::replyDocuments($clientId);
        }

        if (preg_match('/\b(case|cases|matter|status)\b/', $normalized)) {
            return self::replyCases($clientId);
        }

        if (preg_match('/\b(contact|phone|email|hours|office|address)\b/', $normalized)) {
            return self::replyContact();
        }

        $knowledge = chatbotReplyFromCompanyKnowledge($message);
        if ($knowledge !== null) {
            return $knowledge;
        }

        return self::welcome()
            . "\n\nTry: **When is my appointment?**, **What documents do I need?**, or **Show my invoices**.";
    }

    private static function welcome(): string
    {
        $brand = companyBrandName();

        return "Hello! I'm the **{$brand}** client assistant. "
            . 'I can help with your **appointments**, **cases**, **payments**, and **documents** — not legal advice.';
    }

    private static function capabilities(): string
    {
        return "**I can help you with:**\n\n"
            . "• **Appointments** — upcoming dates and times\n"
            . "• **Cases** — status of your matters\n"
            . "• **Payments** — pending invoices\n"
            . "• **Documents** — what to bring and uploads on file\n"
            . "• **Contact** — office hours and how to reach us\n\n"
            . '_For changes to your matter, use **Contact** or reply to your case coordinator._';
    }

    private static function replyAppointments(int $clientId, string $message): string
    {
        $startSql = appointmentStartSql('a');
        $normalized = strtolower($message);

        if (preg_match('/\b(today|tomorrow)\b/', $normalized)) {
            $dateFilter = str_contains($normalized, 'tomorrow')
                ? "DATE({$startSql}) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)"
                : "DATE({$startSql}) = CURDATE()";
            $rows = Database::fetchAll(
                "SELECT a.title, a.status, {$startSql} AS start_time, a.location
                 FROM appointments a WHERE a.client_id = ? AND {$dateFilter}
                 ORDER BY {$startSql} ASC LIMIT 5",
                [$clientId]
            );
        } else {
            $rows = Database::fetchAll(
                "SELECT a.title, a.status, {$startSql} AS start_time, a.location
                 FROM appointments a
                 WHERE a.client_id = ? AND a.status IN ('scheduled','confirmed','rescheduled','requested')
                   AND {$startSql} >= NOW()
                 ORDER BY {$startSql} ASC LIMIT 5",
                [$clientId]
            );
        }

        if ($rows === []) {
            return 'You have no upcoming appointments. '
                . '[' . 'Request one' . '](' . clientUrl('pages/appointments.php') . ').';
        }

        $lines = ['**Your upcoming appointments:**', ''];
        foreach ($rows as $row) {
            $when = !empty($row['start_time']) ? formatDateTime($row['start_time']) : 'TBD';
            $loc = trim((string) ($row['location'] ?? ''));
            $lines[] = '• **' . ($row['title'] ?? 'Appointment') . '** — ' . $when
                . ' (*' . ucfirst($row['status'] ?? 'scheduled') . '*)'
                . ($loc !== '' ? " — {$loc}" : '');
        }

        return implode("\n", $lines);
    }

    private static function replyPayments(int $clientId): string
    {
        $statusCol = invoiceStatusColumn();
        $rows = Database::fetchAll(
            "SELECT invoice_number, total, due_date, {$statusCol} AS invoice_status
             FROM invoices WHERE client_id = ?
             AND {$statusCol} IN ('pending','overdue','partially_paid')
             ORDER BY due_date ASC LIMIT 8",
            [$clientId]
        );

        if ($rows === []) {
            return 'You have **no outstanding invoices**. Thank you!';
        }

        $lines = ['**Outstanding invoices:**', ''];
        foreach ($rows as $row) {
            $status = ucwords(str_replace('_', ' ', $row['invoice_status'] ?? 'pending'));
            $due = !empty($row['due_date']) ? formatDate($row['due_date']) : '—';
            $lines[] = '• **' . ($row['invoice_number'] ?? 'Invoice') . '** — '
                . formatCurrency((float) ($row['total'] ?? 0)) . " — due {$due} (*{$status}*)";
        }

        $lines[] = '';
        $lines[] = '[' . 'Pay online' . '](' . clientUrl('pages/payments.php') . ')';

        return implode("\n", $lines);
    }

    private static function replyDocuments(int $clientId): string
    {
        $rows = Database::fetchAll(
            'SELECT d.original_name, d.created_at, cs.case_number, cs.client_instructions
             FROM documents d
             JOIN cases cs ON cs.id = d.case_id
             WHERE cs.client_id = ?
             ORDER BY d.created_at DESC LIMIT 8',
            [$clientId]
        );

        $instructions = Database::fetch(
            'SELECT client_instructions, case_number, title FROM cases
             WHERE client_id = ? AND client_instructions IS NOT NULL AND TRIM(client_instructions) != ""
             ORDER BY updated_at DESC LIMIT 1',
            [$clientId]
        );

        $lines = [];

        if ($instructions && trim((string) ($instructions['client_instructions'] ?? '')) !== '') {
            $lines[] = '**Instructions from your notary:**';
            $lines[] = mb_strimwidth((string) $instructions['client_instructions'], 0, 600, '…');
            $lines[] = '';
        } else {
            $lines[] = '**Generally, please bring:**';
            $lines[] = '• Valid government-issued photo ID';
            $lines[] = '• Original document(s) — unsigned until your appointment';
            $lines[] = '• Any reference numbers or emails we sent you';
            $lines[] = '';
        }

        if ($rows !== []) {
            $lines[] = '**Your uploaded documents:**';
            foreach ($rows as $row) {
                $when = !empty($row['created_at']) ? formatDate($row['created_at']) : '';
                $lines[] = '• **' . ($row['original_name'] ?? 'Document') . '**'
                    . ' — case ' . ($row['case_number'] ?? '') . ($when !== '' ? " ({$when})" : '');
            }
        } else {
            $lines[] = '_No documents uploaded yet — you can add files from your case page._';
        }

        return implode("\n", $lines);
    }

    private static function replyCases(int $clientId): string
    {
        $rows = Database::fetchAll(
            'SELECT case_number, title, status, updated_at FROM cases
             WHERE client_id = ? ORDER BY updated_at DESC LIMIT 6',
            [$clientId]
        );

        if ($rows === []) {
            return 'You have no cases on file yet.';
        }

        $lines = ['**Your cases:**', ''];
        foreach ($rows as $row) {
            $status = ucwords(str_replace('_', ' ', $row['status'] ?? ''));
            $lines[] = '• **' . ($row['case_number'] ?? 'Case') . '** — '
                . ($row['title'] ?? '') . " (*{$status}*)";
        }

        $lines[] = '';
        $lines[] = '[' . 'View cases' . '](' . clientUrl('pages/cases.php') . ')';

        return implode("\n", $lines);
    }

    private static function replyContact(): string
    {
        $settings = getCompanySettings();
        $brand = companyBrandName($settings);

        $lines = ["**Contact {$brand}:**", ''];

        if (!empty($settings['office_email'])) {
            $lines[] = '• Email: ' . $settings['office_email'];
        }
        if (!empty($settings['office_phone'])) {
            $lines[] = '• Phone: ' . $settings['office_phone'];
        }
        if (!empty($settings['business_hours'])) {
            $lines[] = '• Hours: ' . $settings['business_hours'];
        }

        $address = array_filter([
            $settings['address'] ?? '',
            $settings['city'] ?? '',
            $settings['state'] ?? '',
            $settings['zip_code'] ?? '',
        ]);
        if ($address !== []) {
            $lines[] = '• Address: ' . implode(', ', $address);
        }

        $lines[] = '';
        $lines[] = '[' . 'Send a message' . '](' . clientUrl('pages/contact.php') . ')';

        return implode("\n", $lines);
    }
}
