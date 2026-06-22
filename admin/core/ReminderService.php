<?php

declare(strict_types=1);

class ReminderService
{
    public static function sendCaseWorkflowReminders(): int
    {
        $sent = 0;
        $today = date('Y-m-d');

        $cases = Database::fetchAll(
            "SELECT c.*
             FROM cases c
             WHERE c.status IN ('pending','in_progress','waiting_for_client')
             ORDER BY c.updated_at ASC"
        );

        foreach ($cases as $case) {
            $caseId = (int) ($case['id'] ?? 0);
            if ($caseId <= 0) {
                continue;
            }

            $reminders = [];
            if (!empty($case['deadline']) && (string) $case['deadline'] < $today) {
                $reminders[] = 'Case deadline has passed.';
            }

            $checklist = CaseChecklistService::getChecklist($caseId, (string) ($case['service_type'] ?? ''));
            $missing = CaseChecklistService::missingRequiredLabels($checklist);
            if ($missing !== []) {
                $reminders[] = 'Missing required checklist items: ' . implode(', ', array_slice($missing, 0, 3));
            }

            $openInvoices = Database::fetch(
                "SELECT COUNT(*) AS c FROM invoices
                 WHERE case_id = ? AND " . invoiceStatusColumn() . " IN ('pending','overdue','partially_paid')",
                [$caseId]
            );
            if ((int) ($openInvoices['c'] ?? 0) > 0) {
                $reminders[] = 'There are unpaid invoices for this case.';
            }

            if ($reminders === []) {
                continue;
            }

            $message = implode(' ', $reminders);
            $title = 'Case reminder: ' . ($case['case_number'] ?? ('Case #' . $caseId));
            CaseService::notifyCaseEvent($caseId, 'case', $title, $message, 'pages/case-view.php?id=' . $caseId);
            AuditService::log('case_reminder_sent', 'case', $caseId, ['message' => $message], Auth::id());
            $sent++;
        }

        return $sent;
    }

    public static function sendDueReminders(): int
    {
        if (!Database::columnExists('appointments', 'reminder_sent')) {
            return 0;
        }

        $settings = getCompanySettings();
        $hours    = max(1, min(168, (int) ($settings['appointment_reminder_hours'] ?? 24)));
        $startCol = appointmentStartColumn();
        $endCol   = appointmentEndColumn();

        $rows = Database::fetchAll(
            "SELECT a.*, a.{$startCol} AS starts_at, a.{$endCol} AS ends_at,
                    cl.first_name, cl.last_name, cl.email, cl.user_id
             FROM appointments a
             JOIN clients cl ON cl.id = a.client_id
             WHERE a.reminder_sent = 0
               AND a.status IN ('scheduled', 'confirmed', 'rescheduled')
               AND a.{$startCol} > NOW()
               AND a.{$startCol} <= DATE_ADD(NOW(), INTERVAL ? HOUR)
             ORDER BY a.{$startCol} ASC",
            [$hours]
        );

        $sent = 0;

        foreach ($rows as $row) {
            if (empty($row['email'])) {
                continue;
            }

            $calendar = GoogleCalendarService::syncAppointment((int) $row['id'], $row);

            if (!empty($calendar['url'])) {
                $row['meeting_link'] = $calendar['url'];
            }
            if (!empty($calendar['ics_url'])) {
                $row['ics_url'] = clientUrl('actions/appointment-ics.php?id=' . (int) $row['id']);
            }

            if (MailService::sendAppointmentReminderEmail($row, $row, $calendar['url'] ?? null)) {
                Database::query('UPDATE appointments SET reminder_sent = 1, updated_at = NOW() WHERE id = ?', [(int) $row['id']]);
                self::notifyReminder($row, $calendar['url'] ?? null);
                $sent++;
            }
        }

        return $sent;
    }

    public static function resetReminder(int $appointmentId): void
    {
        if (!Database::columnExists('appointments', 'reminder_sent')) {
            return;
        }

        try {
            Database::query('UPDATE appointments SET reminder_sent = 0, updated_at = NOW() WHERE id = ?', [$appointmentId]);
        } catch (Throwable $e) {
            // optional
        }
    }

    /** @return array{success: bool, message: string} */
    public static function sendPaymentReminderForInvoice(int $invoiceId, ?int $adminId = null): array
    {
        $invoice = assistantFetchInvoiceScoped('i.id = ?', [$invoiceId]);
        if (!$invoice) {
            return ['success' => false, 'message' => 'Invoice not found.'];
        }

        if (!assistantInvoiceIsOutstanding($invoice)) {
            return ['success' => false, 'message' => 'That invoice is already fully paid.'];
        }

        $clientId = (int) ($invoice['client_id'] ?? 0);
        $client = $clientId > 0 ? ClientService::getById($clientId) : null;
        if (!$client || trim((string) ($client['email'] ?? '')) === '') {
            return ['success' => false, 'message' => 'Client email address is not on file.'];
        }

        $caseId = (int) ($invoice['case_id'] ?? 0);
        $case = $caseId > 0 ? (CaseService::getCaseById($caseId) ?? []) : [];

        if (!MailService::sendPaymentReminderEmail($client, $case, $invoice)) {
            return ['success' => false, 'message' => 'Could not send email. Check SMTP settings in Settings.'];
        }

        if ($caseId > 0) {
            CaseService::notifyCaseEvent(
                $caseId,
                'payment',
                'Payment reminder sent',
                'Reminder emailed for invoice ' . ($invoice['invoice_number'] ?? '') . ' — ' . formatCurrency(CaseService::getInvoiceRemainingBalance($invoice)) . ' due',
                'pages/case-view.php?id=' . $caseId . '#invoice-payments'
            );
        }

        AuditService::log('payment_reminder_sent', 'invoice', $invoiceId, [
            'invoice_number' => $invoice['invoice_number'] ?? '',
        ], $adminId);

        return [
            'success' => true,
            'message' => '**Payment reminder sent** to ' . clientFullName($client)
                . ' for invoice **' . ($invoice['invoice_number'] ?? '') . '**.',
        ];
    }

    /** @return array{success: bool, message: string} */
    public static function sendAppointmentReminderNow(int $appointmentId): array
    {
        $appointment = AppointmentService::getById($appointmentId);
        if (!$appointment) {
            return ['success' => false, 'message' => 'Appointment not found.'];
        }

        $status = normalizeAppointmentStatus((string) ($appointment['status'] ?? 'scheduled'));
        if (!in_array($status, ['scheduled', 'confirmed', 'rescheduled'], true)) {
            return ['success' => false, 'message' => 'Reminders can only be sent for scheduled, confirmed, or rescheduled appointments.'];
        }

        $clientId = (int) ($appointment['client_id'] ?? 0);
        $client = $clientId > 0 ? ClientService::getById($clientId) : null;
        if (!$client || trim((string) ($client['email'] ?? '')) === '') {
            return ['success' => false, 'message' => 'Client email address is not on file.'];
        }

        $calendar = GoogleCalendarService::syncAppointment($appointmentId, $client);
        $row = array_merge($appointment, $client);
        if (!empty($calendar['url'])) {
            $row['meeting_link'] = $calendar['url'];
        }
        if (!empty($calendar['ics_url'])) {
            $row['ics_url'] = clientUrl('actions/appointment-ics.php?id=' . $appointmentId);
        }

        if (!MailService::sendAppointmentReminderEmail($client, $row, $calendar['url'] ?? null)) {
            return ['success' => false, 'message' => 'Could not send email. Check SMTP settings in Settings.'];
        }

        if (Database::columnExists('appointments', 'reminder_sent')) {
            Database::query('UPDATE appointments SET reminder_sent = 1, updated_at = NOW() WHERE id = ?', [$appointmentId]);
        }

        self::notifyReminder($row, $calendar['url'] ?? null);
        AuditService::log('appointment_reminder_sent', 'appointment', $appointmentId, [], Auth::id());

        $when = formatDateTime(appointmentStart($appointment));

        return [
            'success' => true,
            'message' => '**Appointment reminder sent** to ' . clientFullName($client)
                . ' for **' . ($appointment['title'] ?? 'Appointment') . '** (' . $when . ').',
        ];
    }

    /** @return array{success: bool, message: string} */
    public static function sendCaseReminderNow(int $caseId, ?int $adminId = null): array
    {
        $case = CaseService::getCaseById($caseId);
        if (!$case) {
            return ['success' => false, 'message' => 'Case not found.'];
        }

        $today = date('Y-m-d');
        $reminders = [];

        if (!empty($case['deadline']) && (string) $case['deadline'] < $today) {
            $reminders[] = 'Case deadline has passed.';
        }

        $checklist = CaseChecklistService::getChecklist($caseId, (string) ($case['service_type'] ?? ''));
        $missing = CaseChecklistService::missingRequiredLabels($checklist);
        if ($missing !== []) {
            $reminders[] = 'Missing required checklist items: ' . implode(', ', array_slice($missing, 0, 3)) . '.';
        }

        $openInvoices = Database::fetch(
            'SELECT COUNT(*) AS c FROM invoices WHERE case_id = ? AND ' . invoiceStatusColumn() . " IN ('pending','overdue','partially_paid')",
            [$caseId]
        );
        if ((int) ($openInvoices['c'] ?? 0) > 0) {
            $reminders[] = 'There are unpaid invoices for this case.';
        }

        if ($reminders === []) {
            $reminders[] = 'Please review your case in the client portal for any outstanding actions.';
        }

        $message = implode(' ', $reminders);
        $title = 'Case reminder: ' . ($case['case_number'] ?? ('Case #' . $caseId));
        CaseService::notifyCaseEvent($caseId, 'case', $title, $message, 'pages/case-view.php?id=' . $caseId);
        AuditService::log('case_reminder_sent', 'case', $caseId, ['message' => $message], $adminId);

        return [
            'success' => true,
            'message' => '**Case reminder sent** for **' . ($case['case_number'] ?? $caseId) . '**. The client and assigned staff were notified in the portal.',
        ];
    }

    private static function notifyReminder(array $client, ?string $calendarUrl): void
    {
        $userId = (int) ($client['user_id'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        $start = formatDateTime(appointmentStart($client));
        $link  = $calendarUrl ?: clientUrl('pages/appointments.php');

        try {
            Database::insert(
                'INSERT INTO notifications (user_id, title, message, type, is_read, link, created_at) VALUES (?, ?, ?, ?, 0, ?, NOW())',
                [
                    $userId,
                    'Appointment reminder',
                    ($client['title'] ?? 'Appointment') . ' — ' . $start,
                    'appointment',
                    $link,
                ]
            );
        } catch (Throwable $e) {
            // optional
        }
    }
}
