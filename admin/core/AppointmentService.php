<?php

declare(strict_types=1);

class AppointmentService
{
    public static function create(array $data, int $adminId): int
    {
        $clientId = (int) ($data['client_id'] ?? 0);
        $title    = trim($data['title'] ?? '');

        if ($clientId <= 0 || $title === '') {
            throw new RuntimeException('Client and appointment title are required.');
        }

        $client = ClientService::getById($clientId);
        if (!$client) {
            throw new RuntimeException('Client not found.');
        }

        $startsAt = normalizeDateTimeInput(trim($data['starts_at'] ?? ''));
        $endsAt   = normalizeDateTimeInput(trim($data['ends_at'] ?? ''));

        if ($startsAt === '') {
            throw new RuntimeException('Start date and time are required.');
        }

        if ($endsAt === '') {
            $endsAt = date('Y-m-d H:i:s', strtotime($startsAt . ' +1 hour'));
        } else {
            $endsAt = normalizeAppointmentEndTime($startsAt, $endsAt);
        }

        $caseId      = resolveAppointmentCaseId(
            $clientId,
            !empty($data['case_id']) ? (int) $data['case_id'] : null
        );
        $description = trim($data['description'] ?? '') ?: null;
        $location    = trim($data['location'] ?? '') ?: null;
        $status      = $data['status'] ?? 'scheduled';

        try {
            $id = Database::insert(
                'INSERT INTO appointments (case_id, client_id, admin_id, title, description, starts_at, ends_at, location, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                [$caseId, $clientId, $adminId, $title, $description, $startsAt, $endsAt, $location, $status]
            );
        } catch (Throwable $e) {
            $id = Database::insert(
                'INSERT INTO appointments (case_id, client_id, admin_id, title, description, start_time, end_time, location, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                [$caseId, $clientId, $adminId, $title, $description, $startsAt, $endsAt, $location, $status]
            );
        }

        $appointment = self::getById($id);
        $calendar    = GoogleCalendarService::syncAppointment($id, $client);

        if (!empty($calendar['url'])) {
            $appointment['meeting_link'] = $calendar['url'];
        }
        if (!empty($calendar['ics_url'])) {
            $appointment['ics_url'] = $calendar['ics_url'];
        }

        self::notifyAppointment($client, $appointment ?? ['title' => $title, 'starts_at' => $startsAt, 'ends_at' => $endsAt, 'location' => $location, 'description' => $description], $calendar, 'scheduled');

        return $id;
    }

    public static function update(int $id, array $data): void
    {
        $appointment = self::getById($id);
        if (!$appointment) {
            throw new RuntimeException('Appointment not found.');
        }

        $title = trim($data['title'] ?? $appointment['title'] ?? '');
        if ($title === '') {
            throw new RuntimeException('Title is required.');
        }

        $startsAt = normalizeDateTimeInput(trim($data['starts_at'] ?? appointmentStart($appointment) ?? ''));
        $endsAt   = normalizeDateTimeInput(trim($data['ends_at'] ?? appointmentEnd($appointment) ?? ''));

        if ($startsAt === '') {
            throw new RuntimeException('Start date and time are required.');
        }

        if ($endsAt === '') {
            $endsAt = date('Y-m-d H:i:s', strtotime($startsAt . ' +1 hour'));
        } else {
            $endsAt = normalizeAppointmentEndTime($startsAt, $endsAt);
        }

        $fields = [
            'title'       => $title,
            'description' => trim($data['description'] ?? '') ?: null,
            'location'    => trim($data['location'] ?? '') ?: null,
            'status'      => $data['status'] ?? $appointment['status'] ?? 'scheduled',
            'starts_at'   => $startsAt,
            'ends_at'     => $endsAt,
        ];

        $setParts = [
            'title = ?',
            'description = ?',
            'location = ?',
            'status = ?',
            'updated_at = NOW()',
        ];
        $params = [
            $fields['title'],
            $fields['description'],
            $fields['location'],
            $fields['status'],
        ];

        if (Database::columnExists('appointments', 'starts_at')) {
            $setParts[] = 'starts_at = ?';
            $params[] = $fields['starts_at'];
        }
        if (Database::columnExists('appointments', 'start_time')) {
            $setParts[] = 'start_time = ?';
            $params[] = $fields['starts_at'];
        }
        if (Database::columnExists('appointments', 'ends_at')) {
            $setParts[] = 'ends_at = ?';
            $params[] = $fields['ends_at'];
        }
        if (Database::columnExists('appointments', 'end_time')) {
            $setParts[] = 'end_time = ?';
            $params[] = $fields['ends_at'];
        }

        $clientId = (int) ($appointment['client_id'] ?? 0);
        $existingCaseId = (int) ($appointment['case_id'] ?? 0);
        if ($existingCaseId <= 0 && $clientId > 0) {
            $linkedCaseId = resolveAppointmentCaseId(
                $clientId,
                !empty($data['case_id']) ? (int) $data['case_id'] : null
            );
            if ($linkedCaseId) {
                $setParts[] = 'case_id = ?';
                $params[] = $linkedCaseId;
            }
        }

        $params[] = $id;

        Database::query(
            'UPDATE appointments SET ' . implode(', ', $setParts) . ' WHERE id = ?',
            $params
        );

        $client = ClientService::getById((int) ($appointment['client_id'] ?? 0));
        if ($client) {
            try {
                $calendar = GoogleCalendarService::syncAppointment($id, $client);
            } catch (Throwable $e) {
                $calendar = [];
            }
            $updated  = self::getById($id);
            if ($updated) {
                if (!empty($calendar['url'])) {
                    $updated['meeting_link'] = $calendar['url'];
                }
                try {
                    self::notifyAppointment($client, $updated, $calendar, 'updated');
                } catch (Throwable $e) {
                    // Appointment saved; notification failure should not block the update
                }
            }
        }
    }

    public static function delete(int $id): void
    {
        $appointment = self::getById($id);
        if (!$appointment) {
            throw new RuntimeException('Appointment not found.');
        }

        $client = ClientService::getById((int) ($appointment['client_id'] ?? 0));
        if ($client) {
            self::notifyAppointment($client, $appointment, [], 'cancelled');
        }

        $icsPath = GoogleCalendarService::getIcsFilePath($id);
        if ($icsPath && is_file($icsPath)) {
            @unlink($icsPath);
        }

        try {
            Database::query('DELETE FROM appointments WHERE id = ?', [$id]);
        } catch (Throwable $e) {
            throw new RuntimeException('Unable to delete appointment.');
        }
    }

    public static function getCalendarResultMessage(array $calendar): string
    {
        return $calendar['message'] ?? ($calendar['success'] ? 'Synced to Google Calendar.' : '');
    }

    public static function getById(int $id): ?array
    {
        $startSql = appointmentStartSql('a');
        $endSql   = appointmentEndSql('a');

        $row = Database::fetch(
            "SELECT a.*, {$startSql} AS start_time, {$endSql} AS end_time FROM appointments a WHERE a.id = ?",
            [$id]
        );

        if (!$row) {
            $row = Database::fetch('SELECT * FROM appointments WHERE id = ?', [$id]);
        }

        return $row;
    }

    public static function getCasesForClient(int $clientId): array
    {
        return Database::fetchAll(
            "SELECT id, case_number, title FROM cases WHERE client_id = ? ORDER BY updated_at DESC",
            [$clientId]
        );
    }

    private static function notifyAppointment(array $client, array $appointment, array $calendar = [], string $event = 'scheduled'): void
    {
        $appointmentId = (int) ($appointment['id'] ?? 0);
        $links = GoogleCalendarService::getCalendarLinks($appointmentId, $appointment, $client, true);

        if (!empty($client['email'])) {
            MailService::sendAppointmentEmail($client, $appointment, $links, $event);
        }

        $userId = (int) ($client['user_id'] ?? 0);
        $start  = formatDateTime(appointmentStart($appointment));

        $titles = [
            'scheduled' => 'Appointment scheduled',
            'updated'   => 'Appointment updated',
            'cancelled' => 'Appointment cancelled',
        ];

        $clientMessage = ($appointment['title'] ?? 'Appointment') . ' — ' . $start;
        if ($event === 'cancelled') {
            $clientMessage = ($appointment['title'] ?? 'Appointment') . ' on ' . $start . ' has been cancelled.';
        }

        if ($userId > 0) {
            createNotification(
                $userId,
                $titles[$event] ?? 'Appointment update',
                $clientMessage,
                'appointment',
                clientUrl('pages/appointments.php')
            );
        }

        $adminTitles = [
            'scheduled' => 'Appointment scheduled',
            'updated'   => 'Appointment updated',
            'cancelled' => 'Appointment cancelled',
        ];

        foreach (Database::fetchAll("SELECT id FROM users WHERE role = 'admin' AND status = 'active'") as $admin) {
            createNotification(
                (int) $admin['id'],
                $adminTitles[$event] ?? 'Appointment update',
                clientFullName($client) . ' — ' . ($appointment['title'] ?? 'Appointment') . ' (' . $start . ')',
                'appointment',
                url('pages/appointments.php')
            );
        }
    }
}
