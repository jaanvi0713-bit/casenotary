<?php

declare(strict_types=1);

class ReminderService
{
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
