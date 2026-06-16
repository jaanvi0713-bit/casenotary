<?php

declare(strict_types=1);

function assistantSanitizeUtf8(string $text): string
{
    if ($text === '') {
        return '';
    }

    if (mb_check_encoding($text, 'UTF-8')) {
        return $text;
    }

    $clean = iconv('UTF-8', 'UTF-8//IGNORE', $text);

    return is_string($clean) ? $clean : '';
}

function assistantAdminLink(string $path, string $label): string
{
    return '[' . $label . '](' . url($path) . ')';
}

function formatAssistantRs(float $amount): string
{
    return 'Rs ' . number_format($amount, 2, '.', ',');
}

/**
 * @return list<array<string, mixed>>
 */
function assistantFindClients(string $term, int $limit = 8): array
{
    $term = trim($term);
    if ($term === '') {
        return [];
    }

    $like = '%' . $term . '%';
    $where = [
        '(LOWER(c.first_name) LIKE LOWER(?)
            OR LOWER(c.last_name) LIKE LOWER(?)
            OR LOWER(CONCAT(c.first_name, \' \', c.last_name)) LIKE LOWER(?)
            OR LOWER(c.company_name) LIKE LOWER(?)
            OR LOWER(c.email) LIKE LOWER(?))',
    ];
    $params = [$like, $like, $like, $like, $like];
    TenantService::appendClientScope($where, $params, 'c');
    $params[] = $limit;

    return Database::fetchAll(
        'SELECT c.* FROM clients c WHERE ' . implode(' AND ', $where) . ' ORDER BY c.updated_at DESC LIMIT ?',
        $params
    );
}

function assistantResolveClientId(string $name): ?int
{
    $clients = assistantFindClients($name, 5);
    if ($clients === []) {
        return null;
    }

    return (int) ($clients[0]['id'] ?? 0) ?: null;
}

function assistantExtractClientNameFromActionMessage(string $message): string
{
    $message = trim($message);
    if ($message === '') {
        return '';
    }

    $patterns = [
        '/\bfor\s+([a-z][\w\'-]+(?:\s+[a-z][\w\'-]+)?)\s+for\s+(?:tomorrow|today|next\s+\w)/iu',
        '/\b(?:cancel|confirm|reschedule|complete|mark)\b.*\bfor\s+([a-z][\w\'-]+(?:\s+[a-z][\w\'-]+)?)/iu',
        '/\b(?:appointment|meeting|case)\s+for\s+([a-z][\w\'-]+(?:\s+[a-z][\w\'-]+)?)\s+(?:for\s+)?(?:tomorrow|today|on\b|at\b|next\b|—|-)/iu',
        '/\bfor\s+([a-z][\w\'-]+(?:\s+[a-z][\w\'-]+)?)\s+(?:for\s+)?(?:tomorrow|today|on\b|at\b|next\b)/iu',
        '/\bfor\s+([a-z][\w\'-]+(?:\s+[a-z][\w\'-]+)?)(?:\s+—|\s+-|\s*$)/iu',
    ];

    foreach ($patterns as $pattern) {
        if (!preg_match($pattern, $message, $matches)) {
            continue;
        }

        $name = trim(preg_replace('/\s+/', ' ', $matches[1]) ?? $matches[1]);
        if ($name !== '') {
            return $name;
        }
    }

    return '';
}

/** @return array{starts_at: string, ends_at: string} */
function assistantExtractScheduleTimes(string $message): array
{
    $startPart = trim($message);
    $endTimeRaw = '';

    if (preg_match('/\bto\s+(\d{1,2}(?::\d{2})?\s*(?:am|pm)?)\b/i', $message, $endMatch, PREG_OFFSET_CAPTURE)) {
        $endTimeRaw = trim($endMatch[1][0]);
        $startPart = trim(substr($message, 0, (int) $endMatch[0][1]));
    }

    $startsAt = parseFlexibleDateTime($startPart);
    if ($startsAt === '' && preg_match('/\b(tomorrow|today)\b/i', $message, $dayMatch)) {
        $timeSuffix = '';
        if (preg_match('/(\d{1,2})(?::(\d{2}))?\s*(am|pm)\b/i', $startPart, $timeMatch)) {
            $timeSuffix = $timeMatch[1];
            if (!empty($timeMatch[2])) {
                $timeSuffix .= ':' . $timeMatch[2];
            }
            $timeSuffix .= ' ' . strtolower($timeMatch[3]);
        }

        $startsAt = parseFlexibleDateTime(trim($dayMatch[1] . ' ' . $timeSuffix));
    }

    $endsAt = '';
    if ($startsAt !== '' && $endTimeRaw !== '') {
        $datePrefix = substr($startsAt, 0, 10);
        $endsAt = parseFlexibleDateTime($datePrefix . ' ' . $endTimeRaw);
    }

    return ['starts_at' => $startsAt, 'ends_at' => $endsAt];
}

function assistantAppointmentStatusLabel(string $status): string
{
    return ucwords(str_replace('_', ' ', normalizeAppointmentStatus($status)));
}

function assistantExtractAppointmentStatus(string $message, string $default = 'scheduled', bool $isNewBooking = false): string
{
    $normalized = strtolower(trim($message));

    if ($isNewBooking) {
        if (preg_match('/\b(confirmed)\s+(appointment|meeting)\b/', $normalized)) {
            return 'confirmed';
        }
        if (preg_match('/\b(requested)\s+(appointment|meeting)\b/', $normalized)) {
            return 'requested';
        }
        if (preg_match('/\b(scheduled)\s+(appointment|meeting)\b/', $normalized)) {
            return 'scheduled';
        }

        return normalizeAppointmentStatus($default);
    }

    if (preg_match('/\bno[- ]?show\b/', $normalized)) {
        return 'no_show';
    }
    if (preg_match('/\b(cancelled|canceled)\b/', $normalized)) {
        return 'cancelled';
    }
    if (preg_match('/\b(completed|complete)\b/', $normalized)) {
        return 'completed';
    }
    if (preg_match('/\b(confirmed|confirm)\b/', $normalized)) {
        return 'confirmed';
    }
    if (preg_match('/\b(rescheduled|reschedule)\b/', $normalized)) {
        return 'rescheduled';
    }
    if (preg_match('/\b(requested|request)\b/', $normalized)) {
        return 'requested';
    }
    if (preg_match('/\bscheduled\b/', $normalized)) {
        return 'scheduled';
    }

    return normalizeAppointmentStatus($default);
}

/**
 * @return list<array<string, mixed>>
 */
function assistantFindAppointments(string $message, bool $includeTerminal = false): array
{
    if (preg_match('/\bappointment\s*#?(\d+)\b/i', $message, $match)) {
        $appointment = AppointmentService::getById((int) $match[1]);

        return $appointment ? [$appointment] : [];
    }

    $clientName = assistantExtractClientNameFromActionMessage($message);
    $clientId = $clientName !== '' ? assistantResolveClientId($clientName) : null;
    if ($clientId === null) {
        return [];
    }

    $where = ['a.client_id = ?'];
    $params = [$clientId];

    if (!$includeTerminal) {
        $where[] = "a.status NOT IN ('cancelled', 'completed', 'no_show')";
    }

    if (TenantService::isEnabled()) {
        $where[] = 'cl.company_id = ?';
        $params[] = TenantService::id();
    }

    $startSql = appointmentStartSql('a');
    $times = assistantExtractScheduleTimes($message);

    if ($times['starts_at'] !== '') {
        $where[] = 'DATE(' . $startSql . ') = ?';
        $params[] = substr($times['starts_at'], 0, 10);
    }

    $params[] = 8;

    return Database::fetchAll(
        'SELECT a.*, cl.first_name, cl.last_name, cl.company_name
         FROM appointments a
         JOIN clients cl ON cl.id = a.client_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY ' . $startSql . ' ASC
         LIMIT ?',
        $params
    );
}

/**
 * @return array<string, mixed>|null
 */
function assistantResolveAppointment(string $message, bool $includeTerminal = false): ?array
{
    $appointments = assistantFindAppointments($message, $includeTerminal);
    if ($appointments === []) {
        return null;
    }

    if (count($appointments) === 1) {
        return $appointments[0];
    }

    $times = assistantExtractScheduleTimes($message);
    if ($times['starts_at'] !== '') {
        $needle = substr($times['starts_at'], 0, 16);
        foreach ($appointments as $appointment) {
            $start = appointmentStart($appointment);
            if ($start !== null && str_starts_with($start, $needle)) {
                return $appointment;
            }
        }

        return null;
    }

    return null;
}

/** @param list<array<string, mixed>> $appointments */
function assistantDescribeAppointments(array $appointments): string
{
    $lines = ['I found multiple matching appointments:', ''];

    foreach ($appointments as $appointment) {
        $start = appointmentStart($appointment);
        $when = $start !== null ? formatDateTime($start) : 'Unknown time';
        $lines[] = '• **' . clientFullName($appointment) . '** — '
            . ($appointment['title'] ?? 'Appointment')
            . ' — ' . $when
            . ' (*' . assistantAppointmentStatusLabel((string) ($appointment['status'] ?? 'scheduled')) . '*)';
    }

    $lines[] = '';
    $lines[] = 'Please include the **date/time** or say **appointment #123**.';

    return implode("\n", $lines);
}

/**
 * @return array<string, mixed>|null
 */
function assistantFindCaseByReference(string $raw): ?array
{
    $raw = trim($raw);
    if ($raw === '') {
        return null;
    }

    if (preg_match('/\d+/', $raw, $matches)) {
        $search = strtoupper(preg_replace('/[^A-Z0-9-]/', '-', $matches[0]));
        $where = ["UPPER(REPLACE(cs.case_number, ' ', '-')) LIKE ?"];
        $params = ['%' . $search . '%'];
        appendCaseTenantScope($where, $params, 'cs', 'cl');
        appendAssignedCaseScope($where, $params, 'cs');

        $case = Database::fetch(
            'SELECT cs.*, cl.first_name, cl.last_name, cl.company_name
             FROM cases cs
             JOIN clients cl ON cl.id = cs.client_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY cs.updated_at DESC LIMIT 1',
            $params
        );

        if ($case) {
            return $case;
        }
    }

    if (ctype_digit($raw)) {
        $where = ['cs.id = ?'];
        $params = [(int) $raw];
        appendCaseTenantScope($where, $params, 'cs', 'cl');
        appendAssignedCaseScope($where, $params, 'cs');

        return Database::fetch(
            'SELECT cs.*, cl.first_name, cl.last_name, cl.company_name
             FROM cases cs JOIN clients cl ON cl.id = cs.client_id
             WHERE ' . implode(' AND ', $where),
            $params
        ) ?: null;
    }

    return null;
}

function assistantNormalizeUserMessage(string $message): string
{
    $message = assistantSanitizeUtf8($message);
    $message = str_replace(["\xc2\xa0", "\xe2\x80\xaf", '％', '﹪'], [' ', ' ', '%', '%'], $message);
    $message = preg_replace('/^[\s_*`"\'“”‘’]+|[\s_*`"\'“”‘’]+$/u', '', $message) ?? $message;
    $message = preg_replace('/\s+/u', ' ', $message) ?? $message;

    return trim($message);
}
