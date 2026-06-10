<?php

declare(strict_types=1);

class AppointmentService
{
    /** @var list<string> */
    private const BLOCKING_STATUSES = ['requested', 'scheduled', 'confirmed', 'rescheduled'];

    public static function ensureStatusSchema(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        $checked = true;

        if (!Database::tableExists('appointments')) {
            return;
        }

        try {
            Database::query(
                "ALTER TABLE appointments MODIFY status ENUM('requested', 'scheduled', 'confirmed', 'rescheduled', 'completed', 'cancelled', 'no_show') NOT NULL DEFAULT 'scheduled'"
            );
        } catch (Throwable $e) {
            // Host may restrict ALTER; run admin/sql/migrate_appointment_rescheduled.php manually.
        }

        try {
            Database::query(
                "UPDATE appointments SET status = 'rescheduled'
                 WHERE status = '' AND updated_at > DATE_ADD(created_at, INTERVAL 30 SECOND)"
            );
            Database::query("UPDATE appointments SET status = 'scheduled' WHERE status = '' OR status IS NULL");
        } catch (Throwable $e) {
            // optional repair
        }
    }

    public static function create(array $data, int $adminId): int
    {
        self::ensureStatusSchema();
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

        self::assertScheduleSlotAvailable($startsAt, $endsAt, null, $status);

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

    public static function createClientRequest(array $data, int $clientId): int
    {
        $title = trim($data['title'] ?? '');
        if ($title === '') {
            throw new RuntimeException('Please enter a title for your appointment request.');
        }

        $client = ClientService::getById($clientId);
        if (!$client) {
            throw new RuntimeException('Client profile not found.');
        }

        $startsAt = normalizeDateTimeInput(trim($data['starts_at'] ?? ''));
        $endsAt   = normalizeDateTimeInput(trim($data['ends_at'] ?? ''));

        if ($startsAt === '') {
            throw new RuntimeException('Preferred date and start time are required.');
        }

        $startTs = strtotime($startsAt);
        if ($startTs === false || $startTs < strtotime('-15 minutes')) {
            throw new RuntimeException('Preferred date and time must be in the future.');
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
        $status      = 'requested';

        self::assertScheduleSlotAvailable($startsAt, $endsAt, null, $status);

        try {
            $id = Database::insert(
                'INSERT INTO appointments (case_id, client_id, admin_id, title, description, starts_at, ends_at, location, status, created_at, updated_at)
                 VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                [$caseId, $clientId, $title, $description, $startsAt, $endsAt, $location, $status]
            );
        } catch (Throwable $e) {
            $id = Database::insert(
                'INSERT INTO appointments (case_id, client_id, admin_id, title, description, start_time, end_time, location, status, created_at, updated_at)
                 VALUES (?, ?, NULL, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                [$caseId, $clientId, $title, $description, $startsAt, $endsAt, $location, $status]
            );
        }

        $appointment = self::getById($id);
        self::notifyAppointment($client, $appointment ?? [
            'id'          => $id,
            'title'       => $title,
            'starts_at'   => $startsAt,
            'ends_at'     => $endsAt,
            'location'    => $location,
            'description' => $description,
            'status'      => $status,
        ], [], 'requested');

        return $id;
    }

    public static function update(int $id, array $data): void
    {
        self::ensureStatusSchema();

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
            'status'      => normalizeAppointmentStatus($data['status'] ?? $appointment['status'] ?? null),
            'starts_at'   => $startsAt,
            'ends_at'     => $endsAt,
        ];

        $previousStatus = normalizeAppointmentStatus($appointment['status'] ?? null);
        $newStatus      = normalizeAppointmentStatus((string) $fields['status']);

        if (self::appointmentTimesChanged($appointment, $fields['starts_at'], $fields['ends_at'])) {
            if (!in_array($newStatus, ['cancelled', 'completed', 'requested'], true)
                && !($previousStatus === 'requested' && in_array($newStatus, ['scheduled', 'confirmed'], true))) {
                $fields['status'] = 'rescheduled';
            }
        }

        self::assertScheduleSlotAvailable($fields['starts_at'], $fields['ends_at'], $id, (string) $fields['status']);

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

        if (self::appointmentTimesChanged($appointment, $fields['starts_at'], $fields['ends_at'])) {
            ReminderService::resetReminder($id);
        }

        $client = ClientService::getById((int) ($appointment['client_id'] ?? 0));
        if ($client) {
            $newStatus = strtolower(trim((string) $fields['status']));

            if (!in_array($newStatus, ['requested'], true)) {
                try {
                    $calendar = GoogleCalendarService::syncAppointment($id, $client);
                } catch (Throwable $e) {
                    $calendar = [];
                }
            } else {
                $calendar = [];
            }

            $updated = self::getById($id);
            if ($updated) {
                if (!empty($calendar['url'])) {
                    $updated['meeting_link'] = $calendar['url'];
                }
                try {
                    if ($previousStatus === 'requested' && in_array($newStatus, ['scheduled', 'confirmed'], true)) {
                        self::notifyAppointment($client, $updated, $calendar, 'scheduled');
                    } elseif ($newStatus === 'cancelled' && $previousStatus !== 'cancelled') {
                        self::notifyAppointment($client, $updated, $calendar, 'cancelled');
                    } elseif (self::appointmentTimesChanged($appointment, $fields['starts_at'], $fields['ends_at'])) {
                        self::notifyAppointment($client, $updated, $calendar, 'rescheduled');
                    } elseif ($previousStatus !== 'requested' || $newStatus !== 'requested') {
                        self::notifyAppointment($client, $updated, $calendar, 'updated');
                    }
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

        if (TenantService::isEnabled()) {
            $row = Database::fetch(
                "SELECT a.*, {$startSql} AS start_time, {$endSql} AS end_time
                 FROM appointments a
                 JOIN clients cl ON cl.id = a.client_id
                 WHERE a.id = ? AND cl.company_id = ?",
                [$id, TenantService::id()]
            );
        } else {
            $row = Database::fetch(
                "SELECT a.*, {$startSql} AS start_time, {$endSql} AS end_time FROM appointments a WHERE a.id = ?",
                [$id]
            );
        }

        if (!$row && !TenantService::isEnabled()) {
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

    /**
     * @return list<array<string, mixed>>
     */
    public static function findConflicts(string $startsAt, string $endsAt, ?int $excludeAppointmentId = null): array
    {
        self::ensureStatusSchema();

        $startsAt = normalizeDateTimeInput(trim($startsAt));
        $endsAt   = normalizeDateTimeInput(trim($endsAt));

        if ($startsAt === '') {
            return [];
        }

        if ($endsAt === '' || strtotime($endsAt) <= strtotime($startsAt)) {
            $endsAt = date('Y-m-d H:i:s', strtotime($startsAt . ' +1 hour'));
        }

        $startSql = appointmentStartSql('a');
        $endSql   = self::appointmentEndOrDefaultSql('a');
        $statuses = "'" . implode("','", self::BLOCKING_STATUSES) . "'";

        $where  = [
            "a.status IN ({$statuses})",
            "({$startSql}) IS NOT NULL",
            "({$startSql}) < ?",
            "({$endSql}) > ?",
        ];
        $params = [$endsAt, $startsAt];

        if ($excludeAppointmentId !== null && $excludeAppointmentId > 0) {
            $where[]  = 'a.id != ?';
            $params[] = $excludeAppointmentId;
        }

        TenantService::appendClientScope($where, $params, 'cl');

        return Database::fetchAll(
            "SELECT a.id, a.title, a.status, {$startSql} AS starts_at, {$endSql} AS ends_at,
                    cl.first_name, cl.last_name, cl.company_name
             FROM appointments a
             JOIN clients cl ON cl.id = a.client_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY {$startSql} ASC
             LIMIT 5",
            $params
        );
    }

    public static function assertScheduleSlotAvailable(
        string $startsAt,
        string $endsAt,
        ?int $excludeAppointmentId = null,
        ?string $status = null
    ): void {
        $status = normalizeAppointmentStatus($status ?? 'scheduled');
        if (!in_array($status, self::BLOCKING_STATUSES, true)) {
            return;
        }

        $conflicts = self::findConflicts($startsAt, $endsAt, $excludeAppointmentId);
        if ($conflicts === []) {
            return;
        }

        $messages = [];
        foreach ($conflicts as $conflict) {
            $startLabel = formatDateTime($conflict['starts_at'] ?? null);
            $endLabel   = formatDateTime($conflict['ends_at'] ?? null);
            $range      = ($endLabel !== '' && $endLabel !== $startLabel)
                ? $startLabel . ' – ' . $endLabel
                : $startLabel;
            $messages[] = ($conflict['title'] ?? 'Appointment') . ' (' . $range . ')';
        }

        throw new RuntimeException(
            'That time slot is not available. It overlaps with: ' . implode('; ', $messages) . '.'
        );
    }

    private static function appointmentEndOrDefaultSql(string $alias = 'a'): string
    {
        $startSql = appointmentStartSql($alias);
        $endSql   = appointmentEndSql($alias);

        return "COALESCE({$endSql}, DATE_ADD({$startSql}, INTERVAL 1 HOUR))";
    }

    private static function appointmentTimesChanged(array $appointment, string $startsAt, string $endsAt): bool
    {
        $prevStart = normalizeDateTimeInput(trim((string) (appointmentStart($appointment) ?? '')));
        $prevEnd   = normalizeDateTimeInput(trim((string) (appointmentEnd($appointment) ?? '')));
        $newStart  = normalizeDateTimeInput($startsAt);
        $newEnd    = normalizeDateTimeInput($endsAt);

        return $prevStart !== $newStart || $prevEnd !== $newEnd;
    }

    private static function notifyAppointment(array $client, array $appointment, array $calendar = [], string $event = 'scheduled'): void
    {
        $appointmentId = (int) ($appointment['id'] ?? 0);
        $links = $event === 'requested'
            ? []
            : GoogleCalendarService::getCalendarLinks($appointmentId, $appointment, $client, true);

        if (!empty($client['email'])) {
            if ($event === 'requested') {
                MailService::sendAppointmentRequestEmail($client, $appointment);
            } else {
                MailService::sendAppointmentEmail($client, $appointment, $links, $event);
            }
        }

        $userId = (int) ($client['user_id'] ?? 0);
        $start  = formatDateTime(appointmentStart($appointment));

        $titles = [
            'requested'   => 'Appointment request submitted',
            'scheduled'   => 'Appointment scheduled',
            'rescheduled' => 'Appointment rescheduled',
            'updated'     => 'Appointment updated',
            'cancelled'   => 'Appointment cancelled',
        ];

        $clientMessages = [
            'requested'   => ($appointment['title'] ?? 'Appointment') . ' — pending approval. Preferred time: ' . $start,
            'scheduled'   => ($appointment['title'] ?? 'Appointment') . ' — ' . $start,
            'rescheduled' => ($appointment['title'] ?? 'Appointment') . ' — new time: ' . $start,
            'updated'     => ($appointment['title'] ?? 'Appointment') . ' — ' . $start,
            'cancelled'   => ($appointment['title'] ?? 'Appointment') . ' on ' . $start . ' has been cancelled.',
        ];

        $companyId = (int) ($client['company_id'] ?? 0);

        if ($userId > 0) {
            createNotification(
                $userId,
                $titles[$event] ?? 'Appointment update',
                $clientMessages[$event] ?? (($appointment['title'] ?? 'Appointment') . ' — ' . $start),
                'appointment',
                clientUrl('pages/appointments.php'),
                $companyId > 0 ? $companyId : null
            );
        }

        $adminTitles = [
            'requested'   => 'New appointment request',
            'scheduled'   => 'Appointment scheduled',
            'rescheduled' => 'Appointment rescheduled',
            'updated'     => 'Appointment updated',
            'cancelled'   => 'Appointment cancelled',
        ];

        $adminMessage = clientFullName($client) . ' — ' . ($appointment['title'] ?? 'Appointment') . ' (' . $start . ')';
        if ($event === 'requested') {
            $adminMessage = clientFullName($client) . ' requested "' . ($appointment['title'] ?? 'Appointment') . '" for ' . $start;
        }

        foreach (TenantService::adminNotifierUserIds($companyId) as $adminId) {
            createNotification(
                $adminId,
                $adminTitles[$event] ?? 'Appointment update',
                $adminMessage,
                'appointment',
                url('pages/appointments.php'),
                $companyId > 0 ? $companyId : null
            );
        }

        if ($event === 'requested') {
            MailService::sendAppointmentRequestAdminEmail($client, $appointment);
        }
    }
}
