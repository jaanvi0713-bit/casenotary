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
