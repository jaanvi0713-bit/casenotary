<?php

declare(strict_types=1);

function url(string $path = ''): string
{
    $config = require __DIR__ . '/../config/config.php';
    return rtrim($config['app_url'], '/') . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $relative = 'assets/' . ltrim($path, '/');
    $filePath = __DIR__ . '/../' . $relative;
    $url      = url($relative);

    if (is_file($filePath)) {
        $url .= '?v=' . filemtime($filePath);
    }

    return $url;
}

function adminAsset(string $path): string
{
    $relative = 'assets/' . ltrim($path, '/');
    $filePath = __DIR__ . '/../' . $relative;
    $url      = adminUrl($relative);

    if (is_file($filePath)) {
        $url .= '?v=' . filemtime($filePath);
    }

    return $url;
}

function adminUrl(string $path = ''): string
{
    $config = require __DIR__ . '/../config/config.php';
    return rtrim($config['app_url'], '/') . '/' . ltrim($path, '/');
}

function clientUrl(string $path = ''): string
{
    $config = require __DIR__ . '/../config/config.php';
    return rtrim($config['client_url'], '/') . '/' . ltrim($path, '/');
}

function clientLoginUrl(?int $companyId = null): string
{
    $url = adminUrl('auth/login.php?portal=client');

    if ($companyId !== null && $companyId > 0 && TenantService::isEnabled()) {
        $slug = TenantService::slug($companyId);
        if ($slug !== '') {
            $url .= '&company=' . rawurlencode($slug);
        }
    }

    return $url;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function userFullName(?array $user): string
{
    if (!$user) {
        return '';
    }
    if (!empty($user['name'])) {
        return trim($user['name']);
    }
    return trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
}

function userFirstName(?array $user): string
{
    if (!$user) {
        return '';
    }
    if (!empty($user['name'])) {
        $parts = explode(' ', trim($user['name']), 2);
        return $parts[0];
    }
    $first = trim((string) ($user['first_name'] ?? ''));
    if ($first !== '') {
        return $first;
    }
    $display = trim((string) ($user['display_name'] ?? ''));
    if ($display !== '') {
        $parts = preg_split('/\s+/', $display, 2) ?: [];
        return $parts[0] ?? '';
    }

    return '';
}

function userLastName(?array $user): string
{
    if (!$user) {
        return '';
    }
    $last = trim((string) ($user['last_name'] ?? ''));
    if ($last !== '') {
        return $last;
    }
    $full = trim((string) ($user['name'] ?? ''));
    if ($full !== '') {
        $parts = explode(' ', $full, 2);
        return $parts[1] ?? '';
    }
    $display = trim((string) ($user['display_name'] ?? ''));
    if ($display !== '') {
        $parts = preg_split('/\s+/', $display, 2) ?: [];
        return $parts[1] ?? '';
    }

    return '';
}

function userInitials(?array $user): string
{
    $name = userFullName($user);
    if ($name === '') {
        return 'U';
    }
    $parts = preg_split('/\s+/', $name) ?: [];
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

function clientFullName(array $client): string
{
    return trim(($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? ''));
}

function clientPostalCode(array $client): string
{
    return trim((string) ($client['zip_code'] ?? $client['zip'] ?? ''));
}

function clientPostalSelectSql(string $alias = 'cl'): string
{
    if (Database::columnExists('clients', 'zip_code')) {
        return $alias . '.zip_code';
    }

    if (Database::columnExists('clients', 'zip')) {
        return $alias . '.zip AS zip_code';
    }

    return 'NULL AS zip_code';
}

/**
 * @return list<string>
 */
function clientAddressLines(array $client): array
{
    $street  = trim((string) ($client['address'] ?? ''));
    $city    = trim((string) ($client['city'] ?? ''));
    $state   = trim((string) ($client['state'] ?? ''));
    $postal  = clientPostalCode($client);
    $country = trim((string) ($client['country'] ?? ''));

    $lines = [];

    if ($street !== '') {
        $lines[] = $street;
    }

    $locality = array_values(array_filter([$city, $state, $postal], static fn(string $part): bool => $part !== ''));
    if ($locality !== []) {
        $lines[] = implode(', ', $locality);
    }

    if ($country !== '') {
        $lines[] = $country;
    }

    return $lines;
}

function clientAddressSummary(array $client): string
{
    $lines = clientAddressLines($client);

    return $lines === [] ? '' : implode(', ', $lines);
}

function clientAddressHtml(array $client): string
{
    $lines = clientAddressLines($client);

    if ($lines === []) {
        return '';
    }

    return implode('<br>', array_map(static fn(string $line): string => e($line), $lines));
}

/**
 * @return list<string>
 */
function companyAddressLines(?array $company = null): array
{
    if ($company === null) {
        $company = getCompanySettings();
    }

    $street  = trim((string) ($company['address'] ?? ''));
    $city    = trim((string) ($company['city'] ?? ''));
    $state   = trim((string) ($company['state'] ?? ''));
    $postal  = trim((string) ($company['zip_code'] ?? ''));
    $country = trim((string) ($company['country'] ?? ''));

    $lines = [];

    if ($street !== '') {
        $lines[] = $street;
    }

    $locality = array_values(array_filter([$city, $state, $postal], static fn(string $part): bool => $part !== ''));
    if ($locality !== []) {
        $lines[] = implode(', ', $locality);
    }

    if ($country !== '') {
        $lines[] = $country;
    }

    return $lines;
}

function companyAddressSummary(?array $company = null): string
{
    $lines = companyAddressLines($company);

    return $lines === [] ? '' : implode(', ', $lines);
}

function companyAddressHtml(?array $company = null): string
{
    $lines = companyAddressLines($company);

    if ($lines === []) {
        return '';
    }

    return implode('<br>', array_map(static fn(string $line): string => e($line), $lines));
}

function appointmentDateTimeValue(?string $value): ?string
{
    if ($value === null) {
        return null;
    }

    $value = trim($value);

    if ($value === '' || str_starts_with($value, '0000-00-00')) {
        return null;
    }

    return $value;
}

/** @return list<string> */
function appointmentStatusValues(): array
{
    return ['requested', 'scheduled', 'confirmed', 'rescheduled', 'completed', 'cancelled', 'no_show'];
}

function normalizeAppointmentStatus(?string $status, string $default = 'scheduled'): string
{
    $status = strtolower(trim((string) $status));

    return in_array($status, appointmentStatusValues(), true) ? $status : $default;
}

function appointmentStart(array $appointment): ?string
{
    return appointmentEffectiveStart($appointment);
}

function appointmentEnd(array $appointment): ?string
{
    return appointmentEffectiveEnd($appointment);
}

function appointmentEffectiveStart(array $appointment): ?string
{
    $resolved = appointmentDateTimeValue($appointment['starts_at'] ?? null);
    if ($resolved !== null) {
        return $resolved;
    }

    return appointmentDateTimeValue($appointment['start_time'] ?? null);
}

function appointmentEffectiveEnd(array $appointment): ?string
{
    $resolved = appointmentDateTimeValue($appointment['ends_at'] ?? null);
    if ($resolved !== null) {
        return $resolved;
    }

    return appointmentDateTimeValue($appointment['end_time'] ?? null);
}

function isUpcomingAppointment(array $appointment): bool
{
    $status = normalizeAppointmentStatus($appointment['status'] ?? null);
    if (!in_array($status, ['scheduled', 'confirmed', 'rescheduled'], true)) {
        return false;
    }

    $start = appointmentEffectiveStart($appointment);
    if (!$start) {
        return false;
    }

    $now = time();
    $end = appointmentEffectiveEnd($appointment);

    if ($end && strtotime($end) >= $now) {
        return true;
    }

    return strtotime($start) >= $now;
}

function isPastAppointment(array $appointment): bool
{
    $start = appointmentEffectiveStart($appointment);
    if (!$start) {
        return false;
    }

    $now = time();
    $end = appointmentEffectiveEnd($appointment);

    if ($end) {
        return strtotime($end) < $now;
    }

    return strtotime($start) < $now;
}

function appointmentStatusColors(): array
{
    return [
        'requested'   => '#3aafa9',
        'scheduled'   => '#3aafa9',
        'confirmed'   => '#10b981',
        'rescheduled' => '#8b5cf6',
        'completed'   => '#64748b',
        'cancelled'   => '#ef4444',
        'past'        => '#f59e0b',
    ];
}

function appointmentCalendarEventColors(array $appointment): array
{
    $colors = appointmentStatusColors();
    $status = normalizeAppointmentStatus($appointment['status'] ?? null);

    if (in_array($status, ['cancelled', 'completed'], true)) {
        $color = $colors[$status];
    } elseif (isPastAppointment($appointment)) {
        $color = $colors['past'];
    } else {
        $color = $colors[$status] ?? $colors['scheduled'];
    }

    return [
        'backgroundColor' => $color,
        'borderColor'     => $color,
        'classNames'      => isPastAppointment($appointment) && !in_array($status, ['cancelled', 'completed'], true)
            ? ['fc-event-past']
            : [],
    ];
}

/**
 * Normalize appointment start/end for calendar display.
 * Caps duration so bad end dates cannot span entire weeks.
 *
 * @return array{0: ?string, 1: ?string}
 */
function normalizeAppointmentCalendarRange(string $start, ?string $end, int $maxHours = 8): array
{
    $startTs = strtotime($start);
    if ($startTs === false) {
        return [null, null];
    }

    $endTs = $end ? strtotime($end) : false;
    if ($endTs === false || $endTs <= $startTs) {
        $endTs = strtotime('+1 hour', $startTs);
    }

    $maxEndTs = strtotime("+{$maxHours} hours", $startTs);
    if ($endTs > $maxEndTs) {
        $endTs = strtotime('+1 hour', $startTs);
    }

    return [
        date('Y-m-d H:i:s', $startTs),
        date('Y-m-d H:i:s', $endTs),
    ];
}

function normalizeAppointmentEndTime(string $startsAt, string $endsAt, int $maxHours = 24): string
{
    $startTs = strtotime($startsAt);
    if ($startTs === false) {
        return $endsAt;
    }

    $endTs = strtotime($endsAt);
    if ($endTs === false || $endTs <= $startTs) {
        return date('Y-m-d H:i:s', strtotime('+1 hour', $startTs));
    }

    $maxEndTs = strtotime("+{$maxHours} hours", $startTs);
    if ($endTs > $maxEndTs) {
        return date('Y-m-d H:i:s', $maxEndTs);
    }

    return $endsAt;
}

/**
 * Resolve appointment start/end for calendar rendering.
 * Keeps the real multi-day span instead of collapsing long appointments to one hour.
 *
 * @return array{0: ?string, 1: ?string}
 */
function resolveAppointmentCalendarRange(array $appointment, int $maxDays = 14): array
{
    $start = appointmentEffectiveStart($appointment);
    if (!$start) {
        return [null, null];
    }

    $startTs = strtotime($start);
    if ($startTs === false) {
        return [null, null];
    }

    $end = appointmentEffectiveEnd($appointment) ?: date('Y-m-d H:i:s', strtotime('+1 hour', $startTs));
    $endTs = strtotime($end);
    if ($endTs === false || $endTs <= $startTs) {
        $end = date('Y-m-d H:i:s', strtotime('+1 hour', $startTs));
        $endTs = strtotime($end);
    }

    $maxEndTs = strtotime("+{$maxDays} days", $startTs);
    if ($endTs > $maxEndTs) {
        $end = date('Y-m-d H:i:s', $maxEndTs);
    }

    return [$start, $end];
}

/**
 * Build FullCalendar event(s) for an appointment.
 *
 * @return array<int, array<string, mixed>>
 */
function buildAppointmentCalendarEvents(array $appointment, array $extendedProps = []): array
{
    [$start, $end] = resolveAppointmentCalendarRange($appointment);
    if (!$start || !$end) {
        return [];
    }

    $startTs = strtotime($start);
    $endTs   = strtotime($end);
    if ($startTs === false || $endTs === false) {
        return [];
    }

    $id          = (string) ($appointment['id'] ?? '');
    $groupId     = 'appt-' . $id;
    $title       = $appointment['title'] ?? 'Appointment';
    $status      = normalizeAppointmentStatus($appointment['status'] ?? null);
    $isTerminal  = in_array($status, ['cancelled', 'completed'], true);
    $startDay    = date('Y-m-d', $startTs);
    $endDay      = date('Y-m-d', $endTs);
    $today       = date('Y-m-d');
    $todayStart  = strtotime($today . ' 00:00:00') ?: $startTs;

    $extendedProps['appointmentId'] = $extendedProps['appointmentId'] ?? (int) ($appointment['id'] ?? 0);
    $colors      = appointmentStatusColors();
    $pastColor   = $colors['past'];
    $eventColors = appointmentCalendarEventColors($appointment);
    $activeColor = $eventColors['backgroundColor'];

    if (!$isTerminal && $startDay !== $endDay && $startTs < $todayStart && $endTs > $todayStart) {
        $events     = [];
        $splitProps = array_merge($extendedProps, ['isSplit' => true]);
        $pastEndDay = date('Y-m-d', strtotime($today . ' -1 day'));

        if ($startDay <= $pastEndDay) {
            $events[] = [
                'id'               => $id . '-past',
                'groupId'          => $groupId,
                'title'            => $title,
                'start'            => $startDay,
                'end'              => date('Y-m-d', strtotime($pastEndDay . ' +1 day')),
                'allDay'           => true,
                'displayEventTime' => false,
                'backgroundColor'  => $pastColor,
                'borderColor'      => $pastColor,
                'classNames'       => ['fc-appt-linked', 'fc-appt-segment-past'],
                'extendedProps'    => array_merge($splitProps, [
                    'segmentRole' => 'past',
                    'segmentPart' => 1,
                    'timeLabel'   => formatDateTime($start, 'g:i A'),
                ]),
            ];
        }

        $events[] = [
            'id'               => $id . '-active',
            'groupId'          => $groupId,
            'title'            => $title,
            'start'            => $today,
            'end'              => date('Y-m-d', strtotime($endDay . ' +1 day')),
            'allDay'           => true,
            'displayEventTime' => false,
            'backgroundColor'  => $activeColor,
            'borderColor'      => $activeColor,
            'classNames'       => ['fc-appt-linked', 'fc-appt-segment-active'],
            'extendedProps'    => array_merge($splitProps, [
                'segmentRole' => 'active',
                'segmentPart' => 2,
            ]),
        ];

        return $events;
    }

    if (!$isTerminal && isPastAppointment($appointment) && $startDay !== $endDay) {
        return [[
            'id'              => $id,
            'groupId'         => $groupId,
            'title'           => $title,
            'start'           => $startDay,
            'end'             => date('Y-m-d', strtotime($endDay . ' +1 day')),
            'allDay'          => true,
            'backgroundColor' => $pastColor,
            'borderColor'     => $pastColor,
            'classNames'      => ['fc-event-past'],
            'extendedProps'   => $extendedProps,
        ]];
    }

    return [[
        'id'              => $id,
        'groupId'         => $groupId,
        'title'           => $title,
        'start'           => calendarEventDateTime($start),
        'end'             => calendarEventDateTime($end),
        'backgroundColor' => $eventColors['backgroundColor'],
        'borderColor'     => $eventColors['borderColor'],
        'classNames'      => $eventColors['classNames'],
        'extendedProps'   => $extendedProps,
    ]];
}

function appointmentCalendarInitialDate(array $appointments): string
{
    $timestamps = [];

    foreach ($appointments as $appointment) {
        $start = appointmentEffectiveStart($appointment);
        if ($start === null) {
            continue;
        }

        $ts = strtotime($start);
        if ($ts !== false) {
            $timestamps[] = $ts;
        }
    }

    if ($timestamps === []) {
        return date('Y-m-d');
    }

    $now = time();
    $best = $timestamps[0];
    $bestDistance = abs($best - $now);

    foreach ($timestamps as $timestamp) {
        $distance = abs($timestamp - $now);
        if ($distance < $bestDistance || ($distance === $bestDistance && $timestamp >= $now)) {
            $best = $timestamp;
            $bestDistance = $distance;
        }
    }

    return date('Y-m-d', $best);
}

function isClientScheduledAppointment(array $appointment): bool
{
    $status = normalizeAppointmentStatus($appointment['status'] ?? null);
    if (!in_array($status, ['scheduled', 'confirmed', 'rescheduled'], true)) {
        return false;
    }

    return appointmentEffectiveStart($appointment) !== null;
}

function formatAppointmentScheduleMeta(array $appointment): string
{
    $start = appointmentEffectiveStart($appointment);
    $end   = appointmentEffectiveEnd($appointment);

    if (!$start) {
        return '—';
    }

    if (!$end) {
        return formatDateTime($start, 'M j, Y g:i A');
    }

    if (date('Y-m-d', strtotime($end)) !== date('Y-m-d', strtotime($start))) {
        return formatDateTime($start, 'M j, Y g:i A') . ' – ' . formatDateTime($end, 'M j, Y g:i A');
    }

    return formatDateTime($start, 'M j, g:i A') . ' – ' . formatDateTime($end, 'g:i A');
}

function resolveAppointmentCaseId(int $clientId, ?int $requestedCaseId = null): ?int
{
    if ($clientId <= 0) {
        return null;
    }

    if ($requestedCaseId !== null && $requestedCaseId > 0) {
        $case = Database::fetch(
            'SELECT id FROM cases WHERE id = ? AND client_id = ?',
            [$requestedCaseId, $clientId]
        );

        return $case ? (int) $case['id'] : null;
    }

    $openCase = Database::fetch(
        "SELECT id FROM cases
         WHERE client_id = ?
           AND status NOT IN ('completed', 'closed')
         ORDER BY updated_at DESC
         LIMIT 1",
        [$clientId]
    );

    if ($openCase) {
        return (int) $openCase['id'];
    }

    $recentCase = Database::fetch(
        'SELECT id FROM cases WHERE client_id = ? ORDER BY updated_at DESC LIMIT 1',
        [$clientId]
    );

    return $recentCase ? (int) $recentCase['id'] : null;
}

function appointmentCaseLabel(array $appointment): string
{
    $caseNumber = trim((string) ($appointment['case_number'] ?? ''));
    $caseTitle  = trim((string) ($appointment['case_title'] ?? ''));

    if ($caseNumber !== '') {
        return $caseTitle !== '' ? $caseNumber . ' — ' . $caseTitle : $caseNumber;
    }

    return 'None';
}

function tableRowSearchBlob(array $parts): string
{
    $normalized = [];

    foreach ($parts as $part) {
        $part = trim((string) $part);
        if ($part !== '') {
            $normalized[] = mb_strtolower($part);
        }
    }

    return implode(' ', $normalized);
}

/**
 * @return list<string>
 */
function caseRowSearchTerms(array $row): array
{
    $terms = [];

    foreach (CaseService::getCaseServices($row) as $service) {
        $type = trim((string) ($service['type'] ?? ''));
        if ($type !== '') {
            $terms[] = $type;
        }
    }

    foreach (['service_type', 'case_title', 'title', 'case_number'] as $field) {
        $value = trim((string) ($row[$field] ?? ''));
        if ($value !== '') {
            $terms[] = $value;
        }
    }

    return array_values(array_unique($terms));
}

function caseRowSearchBlob(array $row, array $extra = []): string
{
    return tableRowSearchBlob(array_merge($extra, caseRowSearchTerms($row)));
}

function enrichAppointmentCase(array &$appointment, bool $persistLink = false): void
{
    if (!empty($appointment['case_id'])) {
        return;
    }

    $clientId = (int) ($appointment['client_id'] ?? 0);
    $caseId = resolveAppointmentCaseId($clientId, null);
    if (!$caseId) {
        return;
    }

    $case = Database::fetch(
        'SELECT id, case_number, title, service_type, services FROM cases WHERE id = ? AND client_id = ?',
        [$caseId, $clientId]
    );

    if (!$case) {
        return;
    }

    if ($persistLink && !empty($appointment['id'])) {
        Database::query(
            'UPDATE appointments SET case_id = ? WHERE id = ? AND (case_id IS NULL OR case_id = 0)',
            [$caseId, (int) $appointment['id']]
        );
        $appointment['case_id'] = $caseId;
    } else {
        $appointment['resolved_case_id'] = (int) $case['id'];
    }

    $appointment['case_number'] = $case['case_number'];
    $appointment['case_title']  = $case['title'];
    $appointment['service_type'] = $case['service_type'] ?? null;
    $appointment['services']     = $case['services'] ?? null;
}

function calendarEventDateTime(?string $datetime): ?string
{
    if (!$datetime) {
        return null;
    }

    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d\TH:i:s', $timestamp);
}

function parseFlexibleDateTime(string $value, bool $defaultTimeIfMissing = true): string
{
    $value = trim(preg_replace('/\s+/u', ' ', str_replace('T', ' ', $value)));

    if ($value === '') {
        return '';
    }

    $value = preg_replace('/\b(today|tomorrow|yesterday)\s+at\s+/i', '$1 ', $value) ?? $value;

    if (preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{1,2}):(\d{2})(?::(\d{2}))?$/', $value, $matches)) {
        $hour = str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        $seconds = $matches[4] ?? '00';

        return $matches[1] . ' ' . $hour . ':' . $matches[3] . ':' . $seconds;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $value)) {
        return substr_count($value, ':') === 1 ? $value . ':00' : $value;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        return $value . ($defaultTimeIfMissing ? ' 09:00:00' : ' 00:00:00');
    }

    if (preg_match('/^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{2,4})(?:\s+(.+))?$/', $value, $matches)) {
        $year = strlen($matches[3]) === 2 ? '20' . $matches[3] : $matches[3];
        foreach ([
            sprintf('%s-%02d-%02d', $year, (int) $matches[2], (int) $matches[1]),
            sprintf('%s-%02d-%02d', $year, (int) $matches[1], (int) $matches[2]),
        ] as $isoDate) {
            $candidate = trim($isoDate . ' ' . ($matches[4] ?? ''));
            $timestamp = strtotime($candidate);
            if ($timestamp !== false) {
                return formatParsedTimestamp($timestamp, $candidate, $defaultTimeIfMissing);
            }
        }
    }

    $cleaned = preg_replace('/(\d+)(st|nd|rd|th)\b/i', '$1', $value) ?? $value;
    $cleaned = preg_replace('/^the\s+/i', '', $cleaned) ?? $cleaned;
    $cleaned = preg_replace('/\bof\b/i', '', $cleaned) ?? $cleaned;

    $candidates = array_unique(array_filter([
        $value,
        $cleaned,
        preg_replace('/^(?:at|on)\s+/i', '', $cleaned) ?? '',
        preg_replace('/,\s*/', ' ', $cleaned) ?? '',
    ]));

    foreach ($candidates as $candidate) {
        $candidate = trim((string) $candidate);
        if ($candidate === '') {
            continue;
        }

        $timestamp = strtotime($candidate);
        if ($timestamp === false) {
            continue;
        }

        return formatParsedTimestamp($timestamp, $candidate, $defaultTimeIfMissing);
    }

    return '';
}

function formatParsedTimestamp(int $timestamp, string $candidate, bool $defaultTimeIfMissing): string
{
    $hasExplicitTime = (bool) preg_match(
        '/\b(\d{1,2}:\d{2}(?::\d{2})?|\d{1,2}\s*(?:am|pm)|\bat\s+\d{1,2})/i',
        $candidate
    );

    if (!$hasExplicitTime && $defaultTimeIfMissing) {
        $timestamp = strtotime(date('Y-m-d', $timestamp) . ' 09:00:00');
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function normalizeDateTimeInput(string $value): string
{
    return parseFlexibleDateTime($value);
}

function paymentStatusValue(array $payment): string
{
    return $payment['payment_status'] ?? $payment['status'] ?? 'pending';
}

/**
 * Month options for payment history filters (January–December).
 *
 * @return array<string, string> MM => "Month name"
 */
function paymentHistoryMonthOptions(): array
{
    $months = [];

    for ($m = 1; $m <= 12; $m++) {
        $monthKey = str_pad((string) $m, 2, '0', STR_PAD_LEFT);
        $months[$monthKey] = date('F', mktime(0, 0, 0, $m, 1));
    }

    return $months;
}

function invoiceStatusValue(array $invoice): string
{
    $col = invoiceStatusColumn();

    return $invoice['payment_status'] ?? $invoice[$col] ?? $invoice['status'] ?? 'pending';
}

function effectiveInvoiceStatus(array $invoice): string
{
    $status = invoiceStatusValue($invoice);

    if (in_array($status, ['paid'], true)) {
        return $status;
    }

    if (!empty($invoice['due_date']) && strtotime($invoice['due_date']) < strtotime('today')) {
        if (in_array($status, ['pending', 'partially_paid', 'overdue'], true)) {
            return 'overdue';
        }
    }

    return $status;
}

function syncOverdueInvoices(): int
{
    $statusCol = invoiceStatusColumn();

    $overdueRows = Database::fetchAll(
        "SELECT i.*, cl.user_id AS client_user_id, cl.company_id
         FROM invoices i
         JOIN clients cl ON cl.id = i.client_id
         WHERE i.{$statusCol} IN ('pending', 'partially_paid')
           AND i.due_date < CURDATE()"
    );

    foreach ($overdueRows as $invoice) {
        Database::query(
            "UPDATE invoices SET {$statusCol} = 'overdue', updated_at = NOW() WHERE id = ?",
            [(int) $invoice['id']]
        );

        $message = 'Invoice ' . ($invoice['invoice_number'] ?? '') . ' is overdue. Due ' . formatDate($invoice['due_date']) . '.';
        $caseId  = (int) ($invoice['case_id'] ?? 0);

        if ($caseId) {
            CaseService::notifyCaseEvent(
                $caseId,
                'invoice',
                'Invoice overdue',
                ($invoice['invoice_number'] ?? '') . ' — due ' . formatDate($invoice['due_date']),
                'pages/case-view.php?id=' . $caseId . '#invoice-payments'
            );
        } else {
            $companyId = (int) ($invoice['company_id'] ?? 0);
            if (!empty($invoice['client_user_id'])) {
                createNotification(
                    (int) $invoice['client_user_id'],
                    'Overdue Invoice',
                    $message,
                    'invoice',
                    clientUrl('pages/payments.php'),
                    $companyId > 0 ? $companyId : null
                );
            }

            foreach (TenantService::adminNotifierUserIds($companyId) as $adminId) {
                createNotification(
                    $adminId,
                    'Overdue Invoice',
                    $message . ' (' . formatCurrency((float) ($invoice['total'] ?? 0)) . ')',
                    'invoice',
                    url('pages/payments.php'),
                    $companyId > 0 ? $companyId : null
                );
            }
        }
    }

    return count($overdueRows);
}

function getOverdueInvoices(int $limit = 50): array
{
    syncOverdueInvoices();
    $statusCol = invoiceStatusColumn();
    $where = ["i.{$statusCol} = 'overdue'"];
    $params = [];
    TenantService::appendClientScope($where, $params, 'cl');
    $params[] = $limit;

    return Database::fetchAll(
        "SELECT i.*, i.{$statusCol} AS payment_status, cl.first_name, cl.last_name, cl.company_name,
                cs.case_number, cs.title AS case_title
         FROM invoices i
         JOIN clients cl ON cl.id = i.client_id
         LEFT JOIN cases cs ON cs.id = i.case_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY i.due_date ASC
         LIMIT ?",
        $params
    );
}

function createNotification(int $userId, string $title, string $message, string $type, ?string $link = null, ?int $companyId = null): void
{
    try {
        $normalizedType = NotificationPreferenceService::normalizeType($type);
        $wantsInApp = NotificationPreferenceService::wantsInApp($userId, $normalizedType);
        $wantsEmail = NotificationPreferenceService::wantsEmail($userId, $normalizedType);

        if ($wantsEmail) {
            sendNotificationEmail($userId, $title, $message, $link);
        }

        if (!$wantsInApp) {
            return;
        }

        $companyId = $companyId ?? (TenantService::isEnabled() ? TenantService::id() : null);

        if (TenantService::hasNotificationScope() && $companyId !== null && $companyId > 0) {
            Database::insert(
                'INSERT INTO notifications (user_id, company_id, title, message, type, is_read, link, created_at) VALUES (?, ?, ?, ?, ?, 0, ?, NOW())',
                [$userId, $companyId, $title, $message, $normalizedType, $link]
            );
        } else {
            Database::insert(
                'INSERT INTO notifications (user_id, title, message, type, is_read, link, created_at) VALUES (?, ?, ?, ?, 0, ?, NOW())',
                [$userId, $title, $message, $normalizedType, $link]
            );
        }
    } catch (Throwable $e) {
        // optional
    }
}

function sendNotificationEmail(int $userId, string $title, string $message, ?string $link = null): void
{
    try {
        $user = Database::fetch('SELECT email, first_name, last_name FROM users WHERE id = ? LIMIT 1', [$userId]);
        if (!$user || trim((string) ($user['email'] ?? '')) === '') {
            return;
        }

        $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $greeting = $name !== '' ? e($name) : 'there';
        $body = '<p>Hi ' . $greeting . ',</p>'
            . '<p><strong>' . e($title) . '</strong></p>'
            . '<p>' . nl2br(e($message)) . '</p>';

        if ($link !== null && trim($link) !== '') {
            $href = $link;
            if (!str_starts_with($href, 'http://') && !str_starts_with($href, 'https://')) {
                $href = str_starts_with($href, '/client/')
                    ? rtrim((require __DIR__ . '/../config/config.php')['client_url'], '/') . '/' . ltrim(substr($href, 8), '/')
                    : url(ltrim($href, '/'));
            }
            $body .= '<p><a href="' . e($href) . '">Open in portal</a></p>';
        }

        $body .= '<p style="color:#64748b;font-size:12px;">You can change notification preferences under Settings → Notification Preferences.</p>';

        $html = '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;color:#1e293b;line-height:1.5;">'
            . '<div style="max-width:560px;margin:0 auto;padding:24px;">'
            . '<h2 style="margin:0 0 16px;font-size:18px;">' . e($title) . '</h2>'
            . $body
            . '</div></body></html>';

        MailService::send((string) $user['email'], $title, $html);
    } catch (Throwable $e) {
        // optional
    }
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function redirectReturn(?string $return, string $fallback = 'pages/dashboard.php'): void
{
    redirect(resolveAdminReturn($return, $fallback));
}

function resolveAdminReturn(?string $return = null, string $fallback = 'pages/dashboard.php'): string
{
    $return = trim(html_entity_decode((string) $return, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

    if ($return !== '' && !str_contains($return, '://') && !str_starts_with($return, '//')) {
        $path = ltrim($return, '/');
        if (preg_match('#^pages/[a-z0-9_\-\./?=&%]+#i', $path)) {
            return $path;
        }
    }

    $referer = str_replace('\\', '/', (string) ($_SERVER['HTTP_REFERER'] ?? ''));
    if (preg_match('#/admin/(pages/[a-z0-9_\-\./?=&%]+)#i', $referer, $matches)) {
        return $matches[1];
    }

    return $fallback;
}

/**
 * Return path safe after a company switch (avoids redirecting to records outside the new workspace).
 */
function resolveAdminReturnAfterCompanySwitch(?string $return = null, string $fallback = 'pages/dashboard.php'): string
{
    $path = resolveAdminReturn($return, $fallback);

    if (!preg_match('#^pages/([^?]+)(\?.*)?$#', $path, $matches)) {
        return $fallback;
    }

    $script = basename($matches[1]);
    parse_str((string) parse_url($path, PHP_URL_QUERY), $query);
    $id = (int) ($query['id'] ?? 0);

    if ($id <= 0) {
        return $path;
    }

    switch ($script) {
        case 'case-view.php':
        case 'case-form.php':
            return CaseService::getCaseById($id) ? $path : 'pages/cases.php';

        case 'client-form.php':
            return ClientService::getById($id) ? $path : 'pages/clients.php';

        default:
            return $path;
    }
}

/** Admin-relative path for post-action return (e.g. pages/appointments.php?q=foo). */
function currentAdminReturn(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));

    if (preg_match('#/admin/(.+)$#i', $script, $matches)) {
        $path = $matches[1];
        $query = trim((string) ($_SERVER['QUERY_STRING'] ?? ''));

        return $query !== '' ? $path . '?' . $query : $path;
    }

    $requestUri = str_replace('\\', '/', (string) ($_SERVER['REQUEST_URI'] ?? ''));
    $requestPath = strtok($requestUri, '?') ?: '';
    if (preg_match('#/admin/(.+)$#i', $requestPath, $matches)) {
        $path = ltrim($matches[1], '/');
        $query = trim((string) ($_SERVER['QUERY_STRING'] ?? ''));

        return $query !== '' ? $path . '?' . $query : $path;
    }

    return 'pages/dashboard.php';
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $value;
}

function old(string $key, string $default = ''): string
{
    return e($_SESSION['old'][$key] ?? $default);
}

function setOld(array $data): void
{
    $_SESSION['old'] = $data;
}

function clearOld(): void
{
    unset($_SESSION['old']);
}

function getCurrencySettings(): array
{
    static $currency = null;

    if ($currency === null) {
        $config   = require __DIR__ . '/../config/config.php';
        $currency = $config['currency'] ?? [
            'code'   => 'GBP',
            'symbol' => '£',
            'locale' => 'en-GB',
        ];
    }

    return $currency;
}

function currencySymbol(): string
{
    return getCurrencySettings()['symbol'];
}

function currencyLocale(): string
{
    return getCurrencySettings()['locale'] ?? 'en-GB';
}

function formatCurrency(float $amount): string
{
    return currencySymbol() . ' ' . number_format($amount, 2);
}

function invoiceStatusColumn(): string
{
    static $column = null;

    if ($column === null) {
        $column = Database::columnExists('invoices', 'payment_status') ? 'payment_status' : 'status';
    }

    return $column;
}

function paymentStatusColumn(): string
{
    static $column = null;

    if ($column === null) {
        $column = Database::columnExists('payments', 'payment_status') ? 'payment_status' : 'status';
    }

    return $column;
}

function documentExtensionColumn(): string
{
    static $column = null;

    if ($column === null) {
        if (Database::columnExists('documents', 'file_extension')) {
            $column = 'file_extension';
        } elseif (Database::columnExists('documents', 'file_type')) {
            $column = 'file_type';
        } else {
            $column = 'file_extension';
        }
    }

    return $column;
}

function documentExtensionSql(string $alias = 'd'): string
{
    return rtrim($alias, '.') . '.' . documentExtensionColumn();
}

/**
 * Insert a row using only columns that exist on the table.
 *
 * @param array<string, mixed> $data
 */
function insertTableRow(string $table, array $data, bool $touchTimestamps = true): int
{
    $filtered = [];

    foreach ($data as $column => $value) {
        if (Database::columnExists($table, $column)) {
            $filtered[$column] = $value;
        }
    }

    if ($filtered === []) {
        throw new RuntimeException("No valid columns to insert into {$table}.");
    }

    $columns      = array_keys($filtered);
    $placeholders = array_fill(0, count($filtered), '?');

    if ($touchTimestamps && Database::columnExists($table, 'created_at')) {
        $columns[]      = 'created_at';
        $placeholders[] = 'NOW()';
    }
    if ($touchTimestamps && Database::columnExists($table, 'updated_at')) {
        $columns[]      = 'updated_at';
        $placeholders[] = 'NOW()';
    }

    $sql = sprintf(
        'INSERT INTO %s (%s) VALUES (%s)',
        $table,
        implode(', ', $columns),
        implode(', ', $placeholders)
    );

    return Database::insert($sql, array_values($filtered));
}

function paymentTransactionColumn(): string
{
    static $column = null;

    if ($column === null) {
        if (Database::columnExists('payments', 'stripe_payment_id')) {
            $column = 'stripe_payment_id';
        } elseif (Database::columnExists('payments', 'transaction_id')) {
            $column = 'transaction_id';
        } else {
            $column = 'stripe_payment_id';
        }
    }

    return $column;
}

function appointmentStartColumn(): string
{
    static $column = null;

    if ($column === null) {
        $column = Database::columnExists('appointments', 'starts_at') ? 'starts_at' : 'start_time';
    }

    return $column;
}

function appointmentEndColumn(): string
{
    static $column = null;

    if ($column === null) {
        $column = Database::columnExists('appointments', 'ends_at') ? 'ends_at' : 'end_time';
    }

    return $column;
}

function appointmentStartSql(string $alias = ''): string
{
    return appointmentDateTimeSql('starts_at', 'start_time', $alias);
}

function appointmentEndSql(string $alias = ''): string
{
    return appointmentDateTimeSql('ends_at', 'end_time', $alias);
}

function appointmentDateTimeSql(string $primaryCol, string $fallbackCol, string $alias = ''): string
{
    $prefix = $alias !== '' ? "{$alias}." : '';
    $hasPrimary  = Database::columnExists('appointments', $primaryCol);
    $hasFallback = Database::columnExists('appointments', $fallbackCol);

    $isValid = static function (string $column) use ($prefix): string {
        $col = "{$prefix}{$column}";

        return "({$col} IS NOT NULL AND YEAR({$col}) > 0)";
    };

    if ($hasPrimary && $hasFallback) {
        return "CASE WHEN {$isValid($primaryCol)} THEN {$prefix}{$primaryCol} WHEN {$isValid($fallbackCol)} THEN {$prefix}{$fallbackCol} ELSE NULL END";
    }

    if ($hasPrimary) {
        return "CASE WHEN {$isValid($primaryCol)} THEN {$prefix}{$primaryCol} ELSE NULL END";
    }

    if ($hasFallback) {
        return "CASE WHEN {$isValid($fallbackCol)} THEN {$prefix}{$fallbackCol} ELSE NULL END";
    }

    return 'NULL';
}

function userDisplayNameSql(string $alias = 'u', string $as = 'name'): string
{
    if (Database::columnExists('users', 'name')) {
        return "{$alias}.name AS {$as}";
    }

    return "TRIM(CONCAT({$alias}.first_name, ' ', {$alias}.last_name)) AS {$as}";
}

function formatDate(?string $date, string $format = 'M d, Y'): string
{
    if (!$date) {
        return '—';
    }
    return date($format, strtotime($date));
}

function formatDateTime(?string $datetime, string $format = 'M d, Y g:i A'): string
{
    if (!$datetime) {
        return '—';
    }
    return date($format, strtotime($datetime));
}

function formatDateTimeStacked(?string $datetime): string
{
    if (!$datetime) {
        return '—';
    }

    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return '—';
    }

    return e(date('M d, Y', $timestamp)) . '<br>' . e(date('g:i A', $timestamp));
}

function statusBadge(?string $status): string
{
    $status = strtolower(trim((string) $status));
    if ($status === '') {
        $status = 'pending';
    }

    $map = [
        'pending'            => 'badge-pending',
        'in_progress'        => 'badge-progress',
        'waiting_for_client' => 'badge-waiting',
        'completed'          => 'badge-completed',
        'closed'             => 'badge-closed',
        'paid'               => 'badge-paid',
        'partially_paid'     => 'badge-partial',
        'overdue'            => 'badge-overdue',
        'scheduled'          => 'badge-scheduled',
        'requested'          => 'badge-requested',
        'confirmed'          => 'badge-confirmed',
        'rescheduled'        => 'badge-rescheduled',
        'cancelled'          => 'badge-closed',
        'no_show'            => 'badge-overdue',
        'active'             => 'badge-paid',
        'inactive'           => 'badge-closed',
        'suspended'          => 'badge-overdue',
    ];

    $class = $map[$status] ?? 'badge-default';
    $label = ucwords(str_replace('_', ' ', $status));

    return sprintf('<span class="status-badge %s">%s</span>', $class, e($label));
}

function timeAgo(?string $datetime): string
{
    if (!$datetime) {
        return 'Recently';
    }

    $time  = strtotime($datetime);
    if ($time === false) {
        return 'Recently';
    }

    $diff  = time() - $time;

    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';

    return formatDate($datetime);
}

function getCompanySettings(): array
{
    return SettingsService::get();
}

function companyBrandName(?array $settings = null): string
{
    $settings = $settings ?? getCompanySettings();
    $name     = trim((string) ($settings['company_name'] ?? ''));

    return $name !== '' ? $name : 'Your Company';
}

/**
 * Professional Google Font choices for branding (value => label).
 */
function companyFontCatalog(): array
{
    return [
        'Montserrat' => [
            'label'  => 'Montserrat — modern geometric',
            'google' => 'Montserrat:wght@300;400;500;600;700',
            'stack'  => "'Montserrat', sans-serif",
        ],
        'Inter' => [
            'label'  => 'Inter — clean UI sans',
            'google' => 'Inter:wght@400;500;600;700',
            'stack'  => "'Inter', sans-serif",
        ],
        'Open Sans' => [
            'label'  => 'Open Sans — friendly professional',
            'google' => 'Open+Sans:wght@400;500;600;700',
            'stack'  => "'Open Sans', sans-serif",
        ],
        'Lato' => [
            'label'  => 'Lato — warm corporate',
            'google' => 'Lato:wght@400;700;900',
            'stack'  => "'Lato', sans-serif",
        ],
        'Roboto' => [
            'label'  => 'Roboto — neutral workplace',
            'google' => 'Roboto:wght@400;500;700',
            'stack'  => "'Roboto', sans-serif",
        ],
        'Source Sans 3' => [
            'label'  => 'Source Sans 3 — Adobe clarity',
            'google' => 'Source+Sans+3:wght@400;500;600;700',
            'stack'  => "'Source Sans 3', sans-serif",
        ],
        'Nunito Sans' => [
            'label'  => 'Nunito Sans — soft rounded',
            'google' => 'Nunito+Sans:wght@400;600;700',
            'stack'  => "'Nunito Sans', sans-serif",
        ],
        'Poppins' => [
            'label'  => 'Poppins — bold marketing',
            'google' => 'Poppins:wght@400;500;600;700',
            'stack'  => "'Poppins', sans-serif",
        ],
        'Raleway' => [
            'label'  => 'Raleway — elegant headings',
            'google' => 'Raleway:wght@400;500;600;700',
            'stack'  => "'Raleway', sans-serif",
        ],
        'Work Sans' => [
            'label'  => 'Work Sans — technical neutral',
            'google' => 'Work+Sans:wght@400;500;600;700',
            'stack'  => "'Work Sans', sans-serif",
        ],
        'DM Sans' => [
            'label'  => 'DM Sans — low-contrast UI',
            'google' => 'DM+Sans:wght@400;500;700',
            'stack'  => "'DM Sans', sans-serif",
        ],
        'IBM Plex Sans' => [
            'label'  => 'IBM Plex Sans — enterprise',
            'google' => 'IBM+Plex+Sans:wght@400;500;600;700',
            'stack'  => "'IBM Plex Sans', sans-serif",
        ],
        'Libre Franklin' => [
            'label'  => 'Libre Franklin — classic news',
            'google' => 'Libre+Franklin:wght@400;500;600;700',
            'stack'  => "'Libre Franklin', sans-serif",
        ],
        'Plus Jakarta Sans' => [
            'label'  => 'Plus Jakarta Sans — contemporary SaaS',
            'google' => 'Plus+Jakarta+Sans:wght@400;500;600;700',
            'stack'  => "'Plus Jakarta Sans', sans-serif",
        ],
    ];
}

function resolveCompanyFont(?string $font): string
{
    $font    = trim((string) $font);
    $catalog = companyFontCatalog();

    if ($font !== '' && isset($catalog[$font])) {
        return $font;
    }

    return 'Montserrat';
}

function companyFontFamily(?array $settings = null): string
{
    $settings = $settings ?? getCompanySettings();

    return resolveCompanyFont($settings['font_family'] ?? null);
}

function companyFontCssStack(?array $settings = null): string
{
    $key = companyFontFamily($settings);

    return companyFontCatalog()[$key]['stack'];
}

/** Comma-separated stack for inline HTML/email styles. */
function companyFontInlineStack(?array $settings = null): string
{
    $stack = companyFontCssStack($settings);
    $name  = preg_replace("/^'(.+)'$/", '$1', explode(',', $stack)[0]);

    return trim($name) . ', Arial, sans-serif';
}

function renderCompanyFontStylesheet(?array $settings = null): string
{
    $key    = companyFontFamily($settings);
    $google = companyFontCatalog()[$key]['google'];

    return '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n"
        . '    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n"
        . '    <link href="https://fonts.googleapis.com/css2?family=' . $google . '&display=swap" rel="stylesheet">';
}

function companyLogoUrl(?array $settings = null, ?int $companyId = null): ?string
{
    if ($settings === null && $companyId !== null && $companyId > 0) {
        $settings = SettingsService::forCompany($companyId);
    }

    $settings = $settings ?? getCompanySettings();
    $logo     = trim((string) ($settings['logo'] ?? ''));

    if ($logo === '') {
        return null;
    }

    $config = require __DIR__ . '/../config/config.php';
    $path   = rtrim($config['upload']['path'], '/\\') . '/' . ltrim($logo, '/');

    if (!is_file($path)) {
        return null;
    }

    $resolvedCompanyId = $companyId ?? (int) ($settings['company_id'] ?? 0);
    $query = $resolvedCompanyId > 0
        ? 'company_id=' . $resolvedCompanyId . '&v=' . filemtime($path)
        : 'v=' . filemtime($path);

    return adminUrl('actions/company-logo.php?' . $query);
}

function documentBrandingCandidateCompanyIds(array $config): array
{
    $name     = trim((string) ($config['document_branding']['company_name'] ?? ''));
    $slug     = trim((string) ($config['document_branding']['company_slug'] ?? ''));
    $forcedId = (int) ($config['document_branding']['company_id'] ?? 0);

    if ($forcedId > 0 && TenantService::exists($forcedId)) {
        return [$forcedId];
    }

    if (!TenantService::isEnabled()) {
        return [];
    }

    $ids = [];

    if ($slug !== '') {
        $row = Database::fetch(
            'SELECT id FROM companies WHERE slug = ? AND status = ? LIMIT 1',
            [$slug, 'active']
        );
        if ($row) {
            $ids[] = (int) $row['id'];
        }
    }

    if ($name !== '') {
        $like = '%' . $name . '%';
        foreach (Database::fetchAll(
            'SELECT company_id FROM company_settings WHERE company_name LIKE ?',
            [$like]
        ) as $row) {
            $ids[] = (int) ($row['company_id'] ?? 0);
        }

        $row = Database::fetch(
            'SELECT id FROM companies WHERE name LIKE ? AND status = ? LIMIT 1',
            [$like, 'active']
        );
        if ($row) {
            $ids[] = (int) $row['id'];
        }
    }

    foreach (['%wharf%notar%', '%worth%notar%'] as $pattern) {
        foreach (Database::fetchAll(
            'SELECT company_id FROM company_settings WHERE company_name LIKE ?',
            [$pattern]
        ) as $row) {
            $ids[] = (int) ($row['company_id'] ?? 0);
        }

        $row = Database::fetch(
            'SELECT id FROM companies WHERE name LIKE ? AND status = ? LIMIT 1',
            [$pattern, 'active']
        );
        if ($row) {
            $ids[] = (int) $row['id'];
        }
    }

    foreach (Database::fetchAll(
        "SELECT company_id FROM company_settings
         WHERE invoice_payable_name LIKE '%wharf%'
            OR invoice_payable_name LIKE '%worth%'"
    ) as $row) {
        $ids[] = (int) ($row['company_id'] ?? 0);
    }

    return array_values(array_unique(array_filter(
        $ids,
        static fn(int $id): bool => $id > 0 && TenantService::exists($id)
    )));
}

function documentBrandingCompanyScore(int $companyId, string $configName): int
{
    if ($companyId <= 0 || !TenantService::exists($companyId)) {
        return -1;
    }

    $settings = SettingsService::forCompany($companyId);
    $score    = 0;

    if (companyAddressSummary($settings) !== '') {
        $score += 20;
    }
    if (trim((string) ($settings['logo'] ?? '')) !== '') {
        $score += 10;
    }
    if (trim((string) ($settings['office_email'] ?? '')) !== '') {
        $score += 3;
    }
    if (trim((string) ($settings['office_phone'] ?? '')) !== '') {
        $score += 2;
    }

    $brand  = strtolower(companyBrandName($settings));
    $needle = strtolower($configName);
    if ($needle !== '' && ($brand === $needle || str_contains($brand, $needle) || str_contains($needle, $brand))) {
        $score += 5;
    }

    $payable = strtolower((string) ($settings['invoice_payable_name'] ?? ''));
    if (preg_match('/wharf|worth/', $brand . ' ' . $payable)) {
        $score += 3;
    }

    return $score;
}

function documentBrandingCompanyId(): ?int
{
    static $resolvedId = null;
    static $done       = false;

    if ($done) {
        return $resolvedId;
    }

    $done   = true;
    $config = require __DIR__ . '/../config/config.php';
    $name   = trim((string) ($config['document_branding']['company_name'] ?? 'Wharf Notaries'));
    $slug   = trim((string) ($config['document_branding']['company_slug'] ?? 'wharf-notaries'));

    if ($name === '' && $slug === '' && (int) ($config['document_branding']['company_id'] ?? 0) <= 0) {
        return null;
    }

    $candidates = documentBrandingCandidateCompanyIds($config);
    if ($candidates === []) {
        return null;
    }

    $bestId    = null;
    $bestScore = -1;
    foreach ($candidates as $candidateId) {
        $score = documentBrandingCompanyScore($candidateId, $name);
        if ($score > $bestScore) {
            $bestScore = $score;
            $bestId    = $candidateId;
        } elseif ($score === $bestScore && $bestId !== null && $candidateId < $bestId) {
            $bestId = $candidateId;
        }
    }

    $resolvedId = $bestId;

    return $resolvedId;
}

function documentBrandingSettings(): array
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $config     = require __DIR__ . '/../config/config.php';
    $configName = trim((string) ($config['document_branding']['company_name'] ?? ''));

    $companyId = documentBrandingCompanyId();
    if ($companyId !== null && $companyId > 0) {
        $cache = SettingsService::forCompany($companyId);
    } else {
        $cache     = getCompanySettings();
        $companyId = (int) ($cache['company_id'] ?? 0);
    }

    if ($configName !== '') {
        $brand = companyBrandName($cache);
        if ($brand === '' || strcasecmp($brand, 'Your Company') === 0) {
            $cache['company_name'] = $configName;
        }
    }

    $uploadPath = rtrim((string) ($config['upload']['path'] ?? ''), '/\\');
    if ($companyId > 0 && trim((string) ($cache['logo'] ?? '')) === '' && $uploadPath !== '') {
        $candidate = 'company_' . $companyId . '/branding/logo.png';
        if (is_file($uploadPath . '/' . $candidate)) {
            $cache['logo'] = $candidate;
        }
    }

    if (companyAddressSummary($cache) === '' && TenantService::isEnabled()) {
        $fallbackRow = Database::fetch(
            'SELECT company_id FROM company_settings
             WHERE LENGTH(COALESCE(address, \'\')) > 0
             ORDER BY LENGTH(COALESCE(address, \'\')) DESC, company_id ASC
             LIMIT 1'
        );
        if ($fallbackRow) {
            $richId = (int) ($fallbackRow['company_id'] ?? 0);
            if ($richId > 0 && $richId !== $companyId) {
                $rich = SettingsService::forCompany($richId);
                foreach ([
                    'address',
                    'city',
                    'state',
                    'zip_code',
                    'country',
                    'office_email',
                    'office_phone',
                    'company_website',
                    'registration_number',
                    'tax_vat_number',
                    'invoice_payable_name',
                    'primary_color',
                    'secondary_color',
                ] as $field) {
                    if (trim((string) ($cache[$field] ?? '')) === '' && trim((string) ($rich[$field] ?? '')) !== '') {
                        $cache[$field] = $rich[$field];
                    }
                }
            }
        }
    }

    return $cache;
}

function companyDocumentLogoUrl(): ?string
{
    $branding  = documentBrandingSettings();
    $companyId = documentBrandingCompanyId();
    $logo      = trim((string) ($branding['logo'] ?? ''));

    if ($logo === '') {
        return companyLogoUrl();
    }

    return companyLogoUrl($branding, $companyId > 0 ? $companyId : null);
}

function companyFaviconUrl(?array $settings = null): ?string
{
    $settings = $settings ?? getCompanySettings();
    $favicon  = trim((string) ($settings['favicon'] ?? ''));

    if ($favicon === '') {
        return null;
    }

    $config = require __DIR__ . '/../config/config.php';
    $path   = rtrim($config['upload']['path'], '/\\') . '/' . ltrim($favicon, '/');

    if (!is_file($path)) {
        return null;
    }

    return adminUrl('actions/company-favicon.php?v=' . filemtime($path));
}

/**
 * Favicon link tags for HTML document heads.
 */
function renderFaviconTags(?array $settings = null): string
{
    $url = companyFaviconUrl($settings);

    if ($url === null) {
        return '';
    }

    $settings = $settings ?? getCompanySettings();
    $ext      = strtolower(pathinfo((string) ($settings['favicon'] ?? ''), PATHINFO_EXTENSION));
    $type     = $ext === 'ico' ? 'image/x-icon' : 'image/png';

    return '<link rel="icon" href="' . e($url) . '" type="' . e($type) . '">' . "\n"
        . '    <link rel="shortcut icon" href="' . e($url) . '">';
}

/**
 * Render company logo image or default placeholder icon.
 */
function renderCompanyLogo(string $context = 'sidebar', ?array $settings = null, string $portal = 'admin'): string
{
    $settings = $settings ?? getCompanySettings();
    $url      = companyLogoUrl($settings);
    $name     = companyBrandName($settings);
    $class    = 'brand-logo-mark brand-logo-mark--' . preg_replace('/[^a-z0-9-]/', '', $context);

    if ($url) {
        return '<img src="' . e($url) . '" alt="' . e($name) . '" class="' . e($class) . ' brand-logo-mark--image">';
    }

    $icon = $portal === 'client' ? 'bi-person-badge' : 'bi-shield-check';

    return '<span class="' . e($class) . ' brand-logo-mark--placeholder" aria-hidden="true">'
        . '<i class="bi ' . $icon . '"></i></span>';
}

function clearCompanySettingsCache(): void
{
    SettingsService::clearCache();
}

function getDashboardStats(): array
{
    syncOverdueInvoices();
    $invoiceStatus = invoiceStatusColumn();
    $paymentStatus = paymentStatusColumn();
    $appointmentStart = appointmentStartSql();
    $appointmentEnd   = appointmentEndSql();
    $tenantEnabled = TenantService::isEnabled();
    $companyId = TenantService::id();

    $assignedOnly = Auth::restrictsToAssignedCases();
    $assignedUserId = $assignedOnly ? (int) Auth::id() : 0;

    if ($tenantEnabled) {
        $totalClients = Database::fetch('SELECT COUNT(*) AS count FROM clients WHERE company_id = ?', [$companyId])['count'] ?? 0;

        $activeCaseSql = "SELECT COUNT(*) AS count FROM cases WHERE company_id = ? AND status IN ('pending', 'in_progress', 'waiting_for_client')";
        $activeCaseParams = [$companyId];
        if ($assignedOnly && $assignedUserId > 0) {
            $activeCaseSql .= ' AND assigned_admin_id = ?';
            $activeCaseParams[] = $assignedUserId;
        }
        $activeCases = Database::fetch($activeCaseSql, $activeCaseParams)['count'] ?? 0;

        $invoiceScope = $assignedOnly && $assignedUserId > 0 ? ' AND cs.assigned_admin_id = ?' : '';
        $invoiceScopeParams = $assignedOnly && $assignedUserId > 0 ? [$assignedUserId] : [];

        $pendingInvoices = Database::fetch(
            "SELECT COUNT(*) AS count FROM invoices i
             JOIN cases cs ON cs.id = i.case_id
             WHERE cs.company_id = ? AND i.{$invoiceStatus} IN ('pending', 'overdue', 'partially_paid'){$invoiceScope}",
            array_merge([$companyId], $invoiceScopeParams)
        )['count'] ?? 0;

        $paidInvoices = Database::fetch(
            "SELECT COUNT(*) AS count FROM invoices i
             JOIN cases cs ON cs.id = i.case_id
             WHERE cs.company_id = ? AND i.{$invoiceStatus} = 'paid'{$invoiceScope}",
            array_merge([$companyId], $invoiceScopeParams)
        )['count'] ?? 0;

        $upcomingAppointments = Database::fetch(
            "SELECT COUNT(*) AS count FROM appointments a
             JOIN clients cl ON cl.id = a.client_id
             WHERE cl.company_id = ?
               AND a.status IN ('scheduled', 'confirmed', 'rescheduled')
               AND ({$appointmentStart} >= NOW() OR ({$appointmentEnd} IS NOT NULL AND {$appointmentEnd} >= NOW()))",
            [$companyId]
        )['count'] ?? 0;

        $revenueScope = $invoiceScope;
        $revenueScopeParams = $invoiceScopeParams;

        $totalRevenue = Database::fetch(
            "SELECT COALESCE(SUM(p.amount), 0) AS total FROM payments p
             JOIN invoices i ON i.id = p.invoice_id
             JOIN cases cs ON cs.id = i.case_id
             WHERE cs.company_id = ? AND p.{$paymentStatus} = 'completed'{$revenueScope}",
            array_merge([$companyId], $revenueScopeParams)
        )['total'] ?? 0;

        $monthlyRevenue = Database::fetch(
            "SELECT COALESCE(SUM(p.amount), 0) AS total FROM payments p
             JOIN invoices i ON i.id = p.invoice_id
             JOIN cases cs ON cs.id = i.case_id
             WHERE cs.company_id = ? AND p.{$paymentStatus} = 'completed'
               AND MONTH(p.paid_at) = MONTH(NOW()) AND YEAR(p.paid_at) = YEAR(NOW()){$revenueScope}",
            array_merge([$companyId], $revenueScopeParams)
        )['total'] ?? 0;

        $weeklyRevenue = Database::fetch(
            "SELECT COALESCE(SUM(p.amount), 0) AS total FROM payments p
             JOIN invoices i ON i.id = p.invoice_id
             JOIN cases cs ON cs.id = i.case_id
             WHERE cs.company_id = ? AND p.{$paymentStatus} = 'completed'
               AND p.paid_at >= DATE_SUB(NOW(), INTERVAL 7 DAY){$revenueScope}",
            array_merge([$companyId], $revenueScopeParams)
        )['total'] ?? 0;

        $outstandingBalance = Database::fetch(
            "SELECT COALESCE(SUM(i.total), 0) AS total FROM invoices i
             JOIN cases cs ON cs.id = i.case_id
             WHERE cs.company_id = ? AND i.{$invoiceStatus} IN ('pending', 'overdue', 'partially_paid'){$invoiceScope}",
            array_merge([$companyId], $invoiceScopeParams)
        )['total'] ?? 0;

        $caseScope = $assignedOnly && $assignedUserId > 0 ? ' AND assigned_admin_id = ?' : '';
        $caseScopeParams = $assignedOnly && $assignedUserId > 0 ? [$assignedUserId] : [];

        $newCasesMonth = Database::fetch(
            "SELECT COUNT(*) AS count FROM cases
             WHERE company_id = ? AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01'){$caseScope}",
            array_merge([$companyId], $caseScopeParams)
        )['count'] ?? 0;

        $completedCases = Database::fetch(
            "SELECT COUNT(*) AS count FROM cases
             WHERE company_id = ? AND status IN ('completed', 'closed'){$caseScope}",
            array_merge([$companyId], $caseScopeParams)
        )['count'] ?? 0;

        $urgentCases = Database::fetch(
            "SELECT COUNT(*) AS count FROM cases
             WHERE company_id = ? AND priority IN ('urgent', 'high')
               AND status IN ('pending', 'in_progress', 'waiting_for_client'){$caseScope}",
            array_merge([$companyId], $caseScopeParams)
        )['count'] ?? 0;

        $casesDeadlineSoon = Database::fetch(
            "SELECT COUNT(*) AS count FROM cases
             WHERE company_id = ? AND deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
               AND status NOT IN ('completed', 'closed'){$caseScope}",
            array_merge([$companyId], $caseScopeParams)
        )['count'] ?? 0;

        $newClientsMonth = Database::fetch(
            "SELECT COUNT(*) AS count FROM clients
             WHERE company_id = ? AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')",
            [$companyId]
        )['count'] ?? 0;

        $overdueInvoices = Database::fetch(
            "SELECT COUNT(*) AS count FROM invoices i
             JOIN cases cs ON cs.id = i.case_id
             WHERE cs.company_id = ? AND i.{$invoiceStatus} = 'overdue'{$invoiceScope}",
            array_merge([$companyId], $invoiceScopeParams)
        )['count'] ?? 0;

        $appointmentStartAliased = appointmentStartSql('a');
        $appointmentsMonth = Database::fetch(
            "SELECT COUNT(*) AS count FROM appointments a
             JOIN clients cl ON cl.id = a.client_id
             WHERE cl.company_id = ? AND {$appointmentStartAliased} >= DATE_FORMAT(NOW(), '%Y-%m-01')",
            [$companyId]
        )['count'] ?? 0;

        $avgInvoiceValue = Database::fetch(
            "SELECT COALESCE(AVG(i.total), 0) AS avg FROM invoices i
             JOIN cases cs ON cs.id = i.case_id
             WHERE cs.company_id = ? AND i.{$invoiceStatus} = 'paid'{$invoiceScope}",
            array_merge([$companyId], $invoiceScopeParams)
        )['avg'] ?? 0;
    } else {
        $totalClients = Database::fetch('SELECT COUNT(*) AS count FROM clients')['count'] ?? 0;

        $activeCaseSql = "SELECT COUNT(*) AS count FROM cases WHERE status IN ('pending', 'in_progress', 'waiting_for_client')";
        $activeCaseParams = [];
        if ($assignedOnly && $assignedUserId > 0) {
            $activeCaseSql .= ' AND assigned_admin_id = ?';
            $activeCaseParams[] = $assignedUserId;
        }
        $activeCases = Database::fetch($activeCaseSql, $activeCaseParams)['count'] ?? 0;

        $invoiceScope = $assignedOnly && $assignedUserId > 0 ? ' WHERE cs.assigned_admin_id = ?' : '';
        $invoiceScopeJoin = $assignedOnly && $assignedUserId > 0
            ? ' JOIN cases cs ON cs.id = i.case_id'
            : '';
        $invoiceScopeParams = $assignedOnly && $assignedUserId > 0 ? [$assignedUserId] : [];

        $pendingInvoices = Database::fetch(
            "SELECT COUNT(*) AS count FROM invoices i{$invoiceScopeJoin}"
            . ($invoiceScope !== '' ? $invoiceScope . ' AND' : ' WHERE')
            . " i.{$invoiceStatus} IN ('pending', 'overdue', 'partially_paid')",
            $invoiceScopeParams
        )['count'] ?? 0;

        $paidInvoices = Database::fetch(
            "SELECT COUNT(*) AS count FROM invoices i{$invoiceScopeJoin}"
            . ($invoiceScope !== '' ? $invoiceScope . ' AND' : ' WHERE')
            . " i.{$invoiceStatus} = 'paid'",
            $invoiceScopeParams
        )['count'] ?? 0;

        $upcomingAppointments = Database::fetch(
            "SELECT COUNT(*) AS count FROM appointments
             WHERE status IN ('scheduled', 'confirmed', 'rescheduled')
               AND ({$appointmentStart} >= NOW() OR ({$appointmentEnd} IS NOT NULL AND {$appointmentEnd} >= NOW()))"
        )['count'] ?? 0;

        $revenueJoin = $assignedOnly && $assignedUserId > 0
            ? ' JOIN invoices i ON i.id = p.invoice_id JOIN cases cs ON cs.id = i.case_id'
            : '';
        $revenueWhere = "p.{$paymentStatus} = 'completed'";
        $revenueParams = [];
        if ($assignedOnly && $assignedUserId > 0) {
            $revenueWhere .= ' AND cs.assigned_admin_id = ?';
            $revenueParams[] = $assignedUserId;
        }

        $totalRevenue = Database::fetch(
            "SELECT COALESCE(SUM(p.amount), 0) AS total FROM payments p{$revenueJoin} WHERE {$revenueWhere}",
            $revenueParams
        )['total'] ?? 0;

        $monthlyRevenue = Database::fetch(
            "SELECT COALESCE(SUM(p.amount), 0) AS total FROM payments p{$revenueJoin}
             WHERE {$revenueWhere} AND MONTH(p.paid_at) = MONTH(NOW()) AND YEAR(p.paid_at) = YEAR(NOW())",
            $revenueParams
        )['total'] ?? 0;

        $weeklyRevenue = Database::fetch(
            "SELECT COALESCE(SUM(p.amount), 0) AS total FROM payments p{$revenueJoin}
             WHERE {$revenueWhere} AND p.paid_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            $revenueParams
        )['total'] ?? 0;

        $outstandingSql = "SELECT COALESCE(SUM(i.total), 0) AS total FROM invoices i{$invoiceScopeJoin}"
            . ($invoiceScope !== '' ? $invoiceScope . ' AND' : ' WHERE')
            . " i.{$invoiceStatus} IN ('pending', 'overdue', 'partially_paid')";
        $outstandingBalance = Database::fetch($outstandingSql, $invoiceScopeParams)['total'] ?? 0;

        $caseScope = $assignedOnly && $assignedUserId > 0 ? ' AND assigned_admin_id = ?' : '';
        $caseScopeParams = $assignedOnly && $assignedUserId > 0 ? [$assignedUserId] : [];

        $newCasesMonth = Database::fetch(
            "SELECT COUNT(*) AS count FROM cases
             WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01'){$caseScope}",
            $caseScopeParams
        )['count'] ?? 0;

        $completedCases = Database::fetch(
            "SELECT COUNT(*) AS count FROM cases
             WHERE status IN ('completed', 'closed'){$caseScope}",
            $caseScopeParams
        )['count'] ?? 0;

        $urgentCases = Database::fetch(
            "SELECT COUNT(*) AS count FROM cases
             WHERE priority IN ('urgent', 'high')
               AND status IN ('pending', 'in_progress', 'waiting_for_client'){$caseScope}",
            $caseScopeParams
        )['count'] ?? 0;

        $casesDeadlineSoon = Database::fetch(
            "SELECT COUNT(*) AS count FROM cases
             WHERE deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
               AND status NOT IN ('completed', 'closed'){$caseScope}",
            $caseScopeParams
        )['count'] ?? 0;

        $newClientsMonth = Database::fetch(
            "SELECT COUNT(*) AS count FROM clients WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"
        )['count'] ?? 0;

        $overdueSql = "SELECT COUNT(*) AS count FROM invoices i{$invoiceScopeJoin}"
            . ($invoiceScope !== '' ? $invoiceScope . ' AND' : ' WHERE')
            . " i.{$invoiceStatus} = 'overdue'";
        $overdueInvoices = Database::fetch($overdueSql, $invoiceScopeParams)['count'] ?? 0;

        $appointmentStartAliased = appointmentStartSql('a');
        $appointmentsMonth = Database::fetch(
            "SELECT COUNT(*) AS count FROM appointments a
             WHERE {$appointmentStartAliased} >= DATE_FORMAT(NOW(), '%Y-%m-01')"
        )['count'] ?? 0;

        $avgSql = "SELECT COALESCE(AVG(i.total), 0) AS avg FROM invoices i{$invoiceScopeJoin}"
            . ($invoiceScope !== '' ? $invoiceScope . ' AND' : ' WHERE')
            . " i.{$invoiceStatus} = 'paid'";
        $avgInvoiceValue = Database::fetch($avgSql, $invoiceScopeParams)['avg'] ?? 0;
    }

    $totalBillable = (int) $paidInvoices + (int) $pendingInvoices + (int) $overdueInvoices;
    $collectionRate = $totalBillable > 0
        ? round(((int) $paidInvoices / $totalBillable) * 100, 1)
        : 0.0;

    return [
        'total_clients'         => (int) $totalClients,
        'active_cases'          => (int) $activeCases,
        'pending_invoices'      => (int) $pendingInvoices,
        'paid_invoices'         => (int) $paidInvoices,
        'upcoming_appointments' => (int) $upcomingAppointments,
        'total_revenue'         => (float) $totalRevenue,
        'monthly_revenue'       => (float) $monthlyRevenue,
        'weekly_revenue'        => (float) $weeklyRevenue,
        'outstanding_balance'   => (float) $outstandingBalance,
        'new_cases_month'       => (int) $newCasesMonth,
        'completed_cases'       => (int) $completedCases,
        'urgent_cases'          => (int) $urgentCases,
        'cases_deadline_soon'   => (int) $casesDeadlineSoon,
        'new_clients_month'     => (int) $newClientsMonth,
        'overdue_invoices'      => (int) $overdueInvoices,
        'appointments_month'    => (int) $appointmentsMonth,
        'avg_invoice_value'     => (float) $avgInvoiceValue,
        'collection_rate'       => $collectionRate,
    ];
}

function getCaseStatusBreakdown(): array
{
    $companyId = TenantService::id();
    $rows = TenantService::isEnabled()
        ? Database::fetchAll('SELECT status, COUNT(*) AS c FROM cases WHERE company_id = ? GROUP BY status', [$companyId])
        : Database::fetchAll('SELECT status, COUNT(*) AS c FROM cases GROUP BY status');

    $map = [
        'pending'            => 0,
        'in_progress'        => 0,
        'waiting_for_client' => 0,
        'completed'          => 0,
        'closed'             => 0,
    ];
    foreach ($rows as $row) {
        if (isset($map[$row['status']])) {
            $map[$row['status']] = (int) $row['c'];
        }
    }

    return $map;
}

function getTopServiceTypes(int $limit = 5): array
{
    $companyId = TenantService::id();

    return TenantService::isEnabled()
        ? Database::fetchAll(
            "SELECT service_type, COUNT(*) AS c FROM cases
             WHERE company_id = ? AND service_type != ''
             GROUP BY service_type ORDER BY c DESC LIMIT ?",
            [$companyId, $limit]
        )
        : Database::fetchAll(
            "SELECT service_type, COUNT(*) AS c FROM cases
             WHERE service_type != ''
             GROUP BY service_type ORDER BY c DESC LIMIT ?",
            [$limit]
        );
}

function getRecentActivity(int $limit = 8): array
{
    return Database::fetchAll(
        'SELECT al.*, ' . userDisplayNameSql('u') . '
         FROM audit_logs al
         LEFT JOIN users u ON u.id = al.user_id
         ORDER BY al.created_at DESC
         LIMIT ?',
        [$limit]
    );
}

function businessActivityMeta(string $type): array
{
    $map = [
        'client_added'          => ['icon' => 'bi-person-plus', 'class' => 'act-teal'],
        'invoice_paid'          => ['icon' => 'bi-receipt-cutoff', 'class' => 'act-green'],
        'case_created'          => ['icon' => 'bi-briefcase', 'class' => 'act-purple'],
        'appointment_scheduled' => ['icon' => 'bi-calendar-event', 'class' => 'act-orange'],
        'document_uploaded'     => ['icon' => 'bi-file-earmark-arrow-up', 'class' => 'act-blue'],
        'payment_received'      => ['icon' => 'bi-cash-coin', 'class' => 'act-green'],
        'case_status_updated'   => ['icon' => 'bi-arrow-repeat', 'class' => 'act-purple'],
        'notification_sent'     => ['icon' => 'bi-bell', 'class' => 'act-teal'],
    ];

    return $map[$type] ?? ['icon' => 'bi-activity', 'class' => 'act-teal'];
}

function getBusinessActivityFeed(int $limit = 20): array
{
    $feed = [];

    try {
        $clientWhere = [];
        $clientParams = [];
        TenantService::appendScope($clientWhere, $clientParams, 'c');
        $clientWhereSql = $clientWhere === [] ? '' : (' WHERE ' . implode(' AND ', $clientWhere));
        $clients = Database::fetchAll(
            "SELECT first_name, last_name, company_name, created_at
             FROM clients c
             {$clientWhereSql}
             ORDER BY created_at DESC
             LIMIT 8",
            $clientParams
        );
        foreach ($clients as $row) {
            $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            if (!empty($row['company_name'])) {
                $name = $name !== '' ? "{$name} ({$row['company_name']})" : $row['company_name'];
            }
            $feed[] = [
                'type'       => 'client_added',
                'title'      => 'New client added',
                'detail'     => $name ?: 'Client profile created',
                'created_at' => $row['created_at'],
            ];
        }
    } catch (Throwable $e) {
        // Optional feed source
    }

    try {
        $caseWhere = [];
        $caseParams = [];
        TenantService::appendScope($caseWhere, $caseParams, 'cs');
        $caseWhereSql = $caseWhere === [] ? '' : (' WHERE ' . implode(' AND ', $caseWhere));
        $cases = Database::fetchAll(
            "SELECT case_number, title, created_at
             FROM cases cs
             {$caseWhereSql}
             ORDER BY created_at DESC
             LIMIT 8",
            $caseParams
        );
        foreach ($cases as $row) {
            $feed[] = [
                'type'       => 'case_created',
                'title'      => 'New case created',
                'detail'     => ($row['case_number'] ?? '') . ' · ' . ($row['title'] ?? 'Case'),
                'created_at' => $row['created_at'],
            ];
        }
    } catch (Throwable $e) {
        // Optional feed source
    }

    try {
        $statusCol = invoiceStatusColumn();
        $invWhere = ["i.{$statusCol} = 'paid'"];
        $invParams = [];
        TenantService::appendClientScope($invWhere, $invParams, 'cl');
        $invoices = Database::fetchAll(
            "SELECT i.invoice_number, i.total, i.updated_at, i.created_at
             FROM invoices i
             JOIN clients cl ON cl.id = i.client_id
             WHERE " . implode(' AND ', $invWhere) . "
             ORDER BY i.updated_at DESC
             LIMIT 8",
            $invParams
        );
        foreach ($invoices as $row) {
            $feed[] = [
                'type'       => 'invoice_paid',
                'title'      => 'Invoice paid',
                'detail'     => ($row['invoice_number'] ?? 'Invoice') . ' · ' . formatCurrency((float) ($row['total'] ?? 0)),
                'created_at' => $row['updated_at'] ?? $row['created_at'],
            ];
        }
    } catch (Throwable $e) {
        // Optional feed source
    }

    try {
        $payWhere = ["p.payment_status = 'completed'"];
        $payParams = [];
        TenantService::appendClientScope($payWhere, $payParams, 'cl');
        $payments = Database::fetchAll(
            "SELECT p.amount, p.paid_at, p.created_at, i.invoice_number
             FROM payments p
             JOIN invoices i ON i.id = p.invoice_id
             JOIN clients cl ON cl.id = i.client_id
             WHERE " . implode(' AND ', $payWhere) . "
             ORDER BY COALESCE(p.paid_at, p.created_at) DESC
             LIMIT 8",
            $payParams
        );
        foreach ($payments as $row) {
            $feed[] = [
                'type'       => 'payment_received',
                'title'      => 'Payment received',
                'detail'     => formatCurrency((float) ($row['amount'] ?? 0)) . ' · ' . ($row['invoice_number'] ?? 'Invoice'),
                'created_at' => $row['paid_at'] ?? $row['created_at'],
            ];
        }
    } catch (Throwable $e) {
        // Optional feed source
    }

    try {
        $startSql = appointmentStartSql('a');
        $apptWhere = [];
        $apptParams = [];
        TenantService::appendClientScope($apptWhere, $apptParams, 'c');
        $apptWhereSql = $apptWhere === [] ? '' : (' WHERE ' . implode(' AND ', $apptWhere));
        $appointments = Database::fetchAll(
            "SELECT a.title, {$startSql} AS starts_at, a.created_at, c.first_name, c.last_name
             FROM appointments a
             JOIN clients c ON c.id = a.client_id
             {$apptWhereSql}
             ORDER BY a.created_at DESC
             LIMIT 8",
            $apptParams
        );
        foreach ($appointments as $row) {
            $start = $row['starts_at'] ?? $row['created_at'];
            $feed[] = [
                'type'       => 'appointment_scheduled',
                'title'      => 'Appointment scheduled',
                'detail'     => ($row['title'] ?? 'Appointment') . ' · ' . clientFullName($row) . ' · ' . formatDateTime($start, 'M d, g:i A'),
                'created_at' => $row['created_at'],
            ];
        }
    } catch (Throwable $e) {
        // Optional feed source
    }

    try {
        $docWhere = [];
        $docParams = [];
        TenantService::appendScope($docWhere, $docParams, 'cs');
        $docWhereSql = $docWhere === [] ? '' : (' AND ' . implode(' AND ', $docWhere));
        $documents = Database::fetchAll(
            "SELECT d.original_name, d.file_name, d.created_at, cs.case_number
             FROM documents d
             LEFT JOIN cases cs ON cs.id = d.case_id
             WHERE 1=1{$docWhereSql}
             ORDER BY d.created_at DESC
             LIMIT 8",
            $docParams
        );
        foreach ($documents as $row) {
            $fileName = $row['original_name'] ?? $row['file_name'] ?? 'Document';
            $caseRef  = !empty($row['case_number']) ? ' · ' . $row['case_number'] : '';
            $feed[] = [
                'type'       => 'document_uploaded',
                'title'      => 'Document uploaded',
                'detail'     => $fileName . $caseRef,
                'created_at' => $row['created_at'],
            ];
        }
    } catch (Throwable $e) {
        // Optional feed source
    }

    try {
        $caseWhere = ['updated_at > DATE_ADD(created_at, INTERVAL 2 MINUTE)'];
        $caseParams = [];
        TenantService::appendScope($caseWhere, $caseParams, 'cs');
        $caseUpdates = Database::fetchAll(
            'SELECT case_number, title, status, updated_at, created_at
             FROM cases cs
             WHERE ' . implode(' AND ', $caseWhere) . '
             ORDER BY updated_at DESC
             LIMIT 8',
            $caseParams
        );
        foreach ($caseUpdates as $row) {
            $status = ucwords(str_replace('_', ' ', $row['status'] ?? 'updated'));
            $feed[] = [
                'type'       => 'case_status_updated',
                'title'      => 'Case status updated',
                'detail'     => ($row['case_number'] ?? 'Case') . ' · ' . $status,
                'created_at' => $row['updated_at'] ?? $row['created_at'],
            ];
        }
    } catch (Throwable $e) {
        // Optional feed source
    }

    try {
        $notifWhere = [];
        $notifParams = [];
        TenantService::appendNotificationScope($notifWhere, $notifParams);
        $notifWhereSql = $notifWhere === [] ? '' : (' WHERE ' . implode(' AND ', $notifWhere));
        $notifications = Database::fetchAll(
            "SELECT title, message, created_at
             FROM notifications
             {$notifWhereSql}
             ORDER BY created_at DESC
             LIMIT 8",
            $notifParams
        );
        foreach ($notifications as $row) {
            $feed[] = [
                'type'       => 'notification_sent',
                'title'      => 'New notification sent',
                'detail'     => $row['title'] ?? mb_strimwidth($row['message'] ?? 'Notification', 0, 60, '…'),
                'created_at' => $row['created_at'],
            ];
        }
    } catch (Throwable $e) {
        // Optional feed source
    }

    usort($feed, static function (array $a, array $b): int {
        return strtotime($b['created_at']) <=> strtotime($a['created_at']);
    });

    if ($limit > 0) {
        $feed = array_slice($feed, 0, $limit);
    }

    foreach ($feed as &$item) {
        $item['meta'] = businessActivityMeta($item['type']);
    }
    unset($item);

    return $feed;
}

function getRecentNotifications(int $userId, int $limit = 5, bool $unreadOnly = false): array
{
    $where = ['user_id = ?'];
    $params = [$userId];
    TenantService::appendNotificationScope($where, $params);

    if ($unreadOnly) {
        $where[] = 'is_read = 0';
    }

    $params[] = $limit;

    return Database::fetchAll(
        'SELECT * FROM notifications WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC LIMIT ?',
        $params
    );
}

function markNotificationAsRead(int $id, int $userId): bool
{
    $where = ['id = ?', 'user_id = ?', 'is_read = 0'];
    $params = [$id, $userId];
    TenantService::appendNotificationScope($where, $params);

    $stmt = Database::query(
        'UPDATE notifications SET is_read = 1 WHERE ' . implode(' AND ', $where),
        $params
    );

    return $stmt->rowCount() > 0;
}

function markAllNotificationsAsRead(int $userId): void
{
    $where = ['user_id = ?', 'is_read = 0'];
    $params = [$userId];
    TenantService::appendNotificationScope($where, $params);

    Database::query(
        'UPDATE notifications SET is_read = 1 WHERE ' . implode(' AND ', $where),
        $params
    );
}

function deleteNotification(int $id, int $userId): void
{
    $where = ['id = ?', 'user_id = ?'];
    $params = [$id, $userId];
    TenantService::appendNotificationScope($where, $params);

    Database::query(
        'DELETE FROM notifications WHERE ' . implode(' AND ', $where),
        $params
    );
}

function getAllNotifications(int $userId, int $limit = 100): array
{
    $where = ['user_id = ?'];
    $params = [$userId];
    TenantService::appendNotificationScope($where, $params);
    $params[] = $limit;

    return Database::fetchAll(
        'SELECT * FROM notifications WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC LIMIT ?',
        $params
    );
}

function countNotifications(int $userId, ?string $search = null, ?string $readFilter = null): int
{
    $search = normalizeSearchTerm($search);
    $readFilter = normalizeSearchTerm($readFilter);
    $where = ['user_id = ?'];
    $params = [$userId];
    TenantService::appendNotificationScope($where, $params);

    if ($search !== '') {
        $where[] = 'CONCAT_WS(" ", title, message, type) LIKE ?';
        $params[] = '%' . $search . '%';
    }
    if ($readFilter === 'unread') {
        $where[] = 'is_read = 0';
    } elseif ($readFilter === 'read') {
        $where[] = 'is_read = 1';
    }

    $row = Database::fetch(
        'SELECT COUNT(*) AS c FROM notifications WHERE ' . implode(' AND ', $where),
        $params
    );

    return (int) ($row['c'] ?? 0);
}

function getNotificationsPaginated(int $userId, int $page, int $perPage = 10, ?string $search = null, ?string $readFilter = null): array
{
    $search = normalizeSearchTerm($search);
    $readFilter = normalizeSearchTerm($readFilter);
    $offset = paginationOffset($page, $perPage);
    $where = ['user_id = ?'];
    $params = [$userId];
    TenantService::appendNotificationScope($where, $params);

    if ($search !== '') {
        $where[] = 'CONCAT_WS(" ", title, message, type) LIKE ?';
        $params[] = '%' . $search . '%';
    }
    if ($readFilter === 'unread') {
        $where[] = 'is_read = 0';
    } elseif ($readFilter === 'read') {
        $where[] = 'is_read = 1';
    }

    $params[] = $perPage;
    $params[] = $offset;

    return Database::fetchAll(
        'SELECT * FROM notifications WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC LIMIT ? OFFSET ?',
        $params
    );
}

function getPendingInvoices(): array
{
    syncOverdueInvoices();
    $statusCol = invoiceStatusColumn();
    $where = ["i.{$statusCol} IN ('pending', 'overdue', 'partially_paid')"];
    $params = [];
    TenantService::appendClientScope($where, $params, 'cl');

    return Database::fetchAll(
        "SELECT i.*, i.{$statusCol} AS payment_status, cl.first_name, cl.last_name, cl.company_name, cs.case_number, cs.title AS case_title
         FROM invoices i
         JOIN clients cl ON cl.id = i.client_id
         LEFT JOIN cases cs ON cs.id = i.case_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY i.due_date ASC, i.created_at DESC",
        $params
    );
}

function resolveNotificationRedirect(?string $link): string
{
    if ($link === null || trim($link) === '') {
        return 'pages/dashboard.php';
    }

    $link = trim($link);

    if (str_starts_with($link, 'http://') || str_starts_with($link, 'https://')) {
        $config = require __DIR__ . '/../config/config.php';
        $base   = rtrim($config['app_url'], '/');

        if (str_starts_with($link, $base . '/')) {
            return ltrim(substr($link, strlen($base)), '/');
        }

        return $link;
    }

    $link = ltrim($link, '/');

    if (str_starts_with($link, 'admin/pages/')) {
        return substr($link, 6);
    }

    return $link;
}

function notificationRedirectTarget(array $notif): string
{
    $target = resolveNotificationRedirect($notif['link'] ?? null);
    $type   = $notif['type'] ?? '';

    if ($type === 'invoice') {
        if ($target === 'pages/dashboard.php') {
            return 'pages/payments.php';
        }

        $target = str_replace(['#invoices', '#payments'], '#invoice-payments', $target);

        if (str_contains($target, 'case-view.php') && !str_contains($target, '#')) {
            $target .= '#invoice-payments';
        }
    }

    if ($type === 'payment') {
        $target = str_replace('#payments', '#invoice-payments', $target);
    }

    return $target;
}

function getUnreadNotificationCount(int $userId): int
{
    $where = ['user_id = ?', 'is_read = 0'];
    $params = [$userId];
    TenantService::appendNotificationScope($where, $params);

    return (int) (Database::fetch(
        'SELECT COUNT(*) AS count FROM notifications WHERE ' . implode(' AND ', $where),
        $params
    )['count'] ?? 0);
}

function getUpcomingAppointments(int $limit = 5): array
{
    $startSql = appointmentStartSql('a');
    $endSql   = appointmentEndSql('a');
    $where = [
        "a.status IN ('scheduled', 'confirmed', 'rescheduled')",
        "({$startSql} >= NOW() OR ({$endSql} IS NOT NULL AND {$endSql} >= NOW()))",
    ];
    $params = [];
    TenantService::appendClientScope($where, $params, 'c');
    $params[] = $limit;

    return Database::fetchAll(
        "SELECT a.*, {$startSql} AS start_time, {$endSql} AS end_time,
                c.first_name, c.last_name, c.company_name
         FROM appointments a
         JOIN clients c ON c.id = a.client_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY {$startSql} ASC
         LIMIT ?",
        $params
    );
}

function getRecentCases(int $limit = 5): array
{
    $where = [];
    $params = [];
    appendCaseTenantScope($where, $params, 'cs', 'cl');
    appendAssignedCaseScope($where, $params, 'cs');
    $params[] = $limit;
    $whereSql = $where === [] ? '' : (' WHERE ' . implode(' AND ', $where));

    return Database::fetchAll(
        "SELECT cs.*, cl.first_name, cl.last_name, cl.company_name
         FROM cases cs
         JOIN clients cl ON cl.id = cs.client_id
         {$whereSql}
         ORDER BY cs.updated_at DESC
         LIMIT ?",
        $params
    );
}

function getRevenueChartData(): array
{
    $paymentStatus = paymentStatusColumn();
    $where = ["p.{$paymentStatus} = 'completed'", 'p.paid_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)'];
    $params = [];
    TenantService::appendClientScope($where, $params, 'cl');

    $rows = Database::fetchAll(
        "SELECT DATE_FORMAT(p.paid_at, '%b') AS month_label,
                MONTH(p.paid_at) AS month_num,
                COALESCE(SUM(p.amount), 0) AS total
         FROM payments p
         JOIN invoices i ON i.id = p.invoice_id
         JOIN clients cl ON cl.id = i.client_id
         WHERE " . implode(' AND ', $where) . "
         GROUP BY MONTH(p.paid_at), DATE_FORMAT(p.paid_at, '%b')
         ORDER BY month_num ASC",
        $params
    );

    $months  = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    $data    = array_fill(0, 6, 0);
    $labels  = [];

    for ($i = 5; $i >= 0; $i--) {
        $monthIndex = (int) date('n', strtotime("-{$i} months")) - 1;
        $labels[]   = $months[$monthIndex];
    }

    foreach ($rows as $row) {
        $idx = array_search($row['month_label'], $labels);
        if ($idx !== false) {
            $data[$idx] = (float) $row['total'];
        }
    }

    return ['labels' => $labels, 'data' => $data];
}

function getInvoiceChartData(): array
{
    $where = ['i.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)'];
    $params = [];
    TenantService::appendClientScope($where, $params, 'cl');

    $rows = Database::fetchAll(
        "SELECT DATE_FORMAT(i.created_at, '%b') AS month_label,
                MONTH(i.created_at) AS month_num,
                COALESCE(SUM(i.total), 0) AS total
         FROM invoices i
         JOIN clients cl ON cl.id = i.client_id
         WHERE " . implode(' AND ', $where) . "
         GROUP BY MONTH(i.created_at), DATE_FORMAT(i.created_at, '%b')
         ORDER BY month_num ASC",
        $params
    );

    $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    $data   = array_fill(0, 6, 0);
    $labels = [];

    for ($i = 5; $i >= 0; $i--) {
        $monthIndex = (int) date('n', strtotime("-{$i} months")) - 1;
        $labels[]   = $months[$monthIndex];
    }

    foreach ($rows as $row) {
        $idx = array_search($row['month_label'], $labels);
        if ($idx !== false) {
            $data[$idx] = (float) $row['total'];
        }
    }

    return ['labels' => $labels, 'data' => $data];
}

function getWeeklyPaymentsChartData(): array
{
    $paymentStatus = paymentStatusColumn();
    $labels    = [];
    $payments  = array_fill(0, 7, 0.0);
    $invoices  = array_fill(0, 7, 0.0);

    for ($i = 6; $i >= 0; $i--) {
        $labels[] = date('D j', strtotime("-{$i} days"));
    }

    $payWhere = ["p.{$paymentStatus} = 'completed'", 'p.paid_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)'];
    $payParams = [];
    TenantService::appendClientScope($payWhere, $payParams, 'cl');

    $rows = Database::fetchAll(
        "SELECT DATE(p.paid_at) AS day_date, COALESCE(SUM(p.amount), 0) AS total
         FROM payments p
         JOIN invoices i ON i.id = p.invoice_id
         JOIN clients cl ON cl.id = i.client_id
         WHERE " . implode(' AND ', $payWhere) . "
         GROUP BY DATE(p.paid_at)",
        $payParams
    );

    foreach ($rows as $row) {
        $idx = sparklineDayIndex($row['day_date']);
        if ($idx !== null) {
            $payments[$idx] = (float) $row['total'];
        }
    }

    $invWhere = ['i.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)'];
    $invParams = [];
    TenantService::appendClientScope($invWhere, $invParams, 'cl');

    $invoiceRows = Database::fetchAll(
        "SELECT DATE(i.created_at) AS day_date, COALESCE(SUM(i.total), 0) AS total
         FROM invoices i
         JOIN clients cl ON cl.id = i.client_id
         WHERE " . implode(' AND ', $invWhere) . "
         GROUP BY DATE(i.created_at)",
        $invParams
    );

    foreach ($invoiceRows as $row) {
        $idx = sparklineDayIndex($row['day_date']);
        if ($idx !== null) {
            $invoices[$idx] = (float) $row['total'];
        }
    }

    return [
        'labels'   => $labels,
        'payments' => $payments,
        'invoices' => $invoices,
    ];
}

function chartSeriesHasData(array $series): bool
{
    foreach ($series as $value) {
        if ((float) $value > 0) {
            return true;
        }
    }

    return false;
}

function getDashboardTrends(array $stats): array
{
    $paymentStatus = paymentStatusColumn();
    $companyId = TenantService::id();

    if (TenantService::isEnabled()) {
        $lastMonthRevenue = (float) (Database::fetch(
            "SELECT COALESCE(SUM(p.amount), 0) AS total FROM payments p
             JOIN invoices i ON i.id = p.invoice_id
             JOIN cases cs ON cs.id = i.case_id
             WHERE cs.company_id = ? AND p.{$paymentStatus} = 'completed'
               AND MONTH(p.paid_at) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH))
               AND YEAR(p.paid_at) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH))",
            [$companyId]
        )['total'] ?? 0);

        $lastMonthCases = (int) (Database::fetch(
            "SELECT COUNT(*) AS count FROM cases
             WHERE company_id = ?
               AND MONTH(created_at) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH))
               AND YEAR(created_at) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH))",
            [$companyId]
        )['count'] ?? 0);

        $thisMonthCases = (int) (Database::fetch(
            "SELECT COUNT(*) AS count FROM cases
             WHERE company_id = ?
               AND MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())",
            [$companyId]
        )['count'] ?? 0);
    } else {
        $lastMonthRevenue = (float) (Database::fetch(
            "SELECT COALESCE(SUM(amount), 0) AS total FROM payments
             WHERE {$paymentStatus} = 'completed'
               AND MONTH(paid_at) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH))
               AND YEAR(paid_at) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH))"
        )['total'] ?? 0);

        $lastMonthCases = (int) (Database::fetch(
            "SELECT COUNT(*) AS count FROM cases
             WHERE MONTH(created_at) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH))
               AND YEAR(created_at) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH))"
        )['count'] ?? 0);

        $thisMonthCases = (int) (Database::fetch(
            "SELECT COUNT(*) AS count FROM cases
             WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())"
        )['count'] ?? 0);
    }

    $revenueTrend = $lastMonthRevenue > 0
        ? round((($stats['monthly_revenue'] - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1)
        : ($stats['monthly_revenue'] > 0 ? 100.0 : 0.0);
    $revenueTrend = max(-999.0, min(999.0, $revenueTrend));

    $casesTrend = $lastMonthCases > 0
        ? round((($thisMonthCases - $lastMonthCases) / $lastMonthCases) * 100, 1)
        : ($thisMonthCases > 0 ? 100.0 : 0.0);
    $casesTrend = max(-999.0, min(999.0, $casesTrend));

    return [
        'clients'  => ['value' => 2.5, 'up' => true],
        'cases'    => ['value' => abs($casesTrend), 'up' => $casesTrend >= 0],
        'invoices' => ['value' => 1.2, 'up' => true],
        'revenue'  => ['value' => abs($revenueTrend), 'up' => $revenueTrend >= 0],
    ];
}

function sparklineDayIndex(string $date): ?int
{
    $target = strtotime(date('Y-m-d', strtotime($date)));
    $today  = strtotime('today');
    $diff   = (int) round(($today - $target) / 86400);

    if ($diff < 0 || $diff > 6) {
        return null;
    }

    return 6 - $diff;
}

function getLast7DaysSparklineData(): array
{
    $labels = [];
    for ($i = 6; $i >= 0; $i--) {
        $labels[] = date('M j', strtotime("-{$i} days"));
    }

    $clients      = array_fill(0, 7, 0);
    $cases        = array_fill(0, 7, 0);
    $invoices     = array_fill(0, 7, 0);
    $paidInvoices = array_fill(0, 7, 0);
    $payments     = array_fill(0, 7, 0.0);
    $statusCol    = invoiceStatusColumn();
    $paymentCol   = paymentStatusColumn();

    $clientWhere = ['c.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)'];
    $clientParams = [];
    TenantService::appendScope($clientWhere, $clientParams, 'c');
    foreach (Database::fetchAll(
        'SELECT DATE(c.created_at) AS day_date, COUNT(*) AS total
         FROM clients c
         WHERE ' . implode(' AND ', $clientWhere) . '
         GROUP BY DATE(c.created_at)',
        $clientParams
    ) as $row) {
        $idx = sparklineDayIndex($row['day_date']);
        if ($idx !== null) {
            $clients[$idx] = (int) $row['total'];
        }
    }

    $caseWhere = ['cs.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)'];
    $caseParams = [];
    TenantService::appendScope($caseWhere, $caseParams, 'cs');
    foreach (Database::fetchAll(
        'SELECT DATE(cs.created_at) AS day_date, COUNT(*) AS total
         FROM cases cs
         WHERE ' . implode(' AND ', $caseWhere) . '
         GROUP BY DATE(cs.created_at)',
        $caseParams
    ) as $row) {
        $idx = sparklineDayIndex($row['day_date']);
        if ($idx !== null) {
            $cases[$idx] = (int) $row['total'];
        }
    }

    $invWhere = ['i.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)'];
    $invParams = [];
    TenantService::appendClientScope($invWhere, $invParams, 'cl');
    foreach (Database::fetchAll(
        'SELECT DATE(i.created_at) AS day_date, COUNT(*) AS total
         FROM invoices i
         JOIN clients cl ON cl.id = i.client_id
         WHERE ' . implode(' AND ', $invWhere) . '
         GROUP BY DATE(i.created_at)',
        $invParams
    ) as $row) {
        $idx = sparklineDayIndex($row['day_date']);
        if ($idx !== null) {
            $invoices[$idx] = (int) $row['total'];
        }
    }

    $paidWhere = ["i.{$statusCol} = 'paid'", 'i.updated_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)'];
    $paidParams = [];
    TenantService::appendClientScope($paidWhere, $paidParams, 'cl');
    foreach (Database::fetchAll(
        "SELECT DATE(i.updated_at) AS day_date, COUNT(*) AS total
         FROM invoices i
         JOIN clients cl ON cl.id = i.client_id
         WHERE " . implode(' AND ', $paidWhere) . '
         GROUP BY DATE(i.updated_at)',
        $paidParams
    ) as $row) {
        $idx = sparklineDayIndex($row['day_date']);
        if ($idx !== null) {
            $paidInvoices[$idx] = (int) $row['total'];
        }
    }

    $payWhere = ["p.{$paymentCol} = 'completed'", 'p.paid_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)'];
    $payParams = [];
    TenantService::appendClientScope($payWhere, $payParams, 'cl');
    foreach (Database::fetchAll(
        "SELECT DATE(p.paid_at) AS day_date, COALESCE(SUM(p.amount), 0) AS total
         FROM payments p
         JOIN invoices i ON i.id = p.invoice_id
         JOIN clients cl ON cl.id = i.client_id
         WHERE " . implode(' AND ', $payWhere) . '
         GROUP BY DATE(p.paid_at)',
        $payParams
    ) as $row) {
        $idx = sparklineDayIndex($row['day_date']);
        if ($idx !== null) {
            $payments[$idx] = (float) $row['total'];
        }
    }

    return [
        'labels'        => $labels,
        'clients'       => $clients,
        'cases'         => $cases,
        'invoices'      => $invoices,
        'paid_invoices' => $paidInvoices,
        'payments'      => $payments,
    ];
}

function kpiTrendBadge(array $trend, bool $inline = false): string
{
    $value = (float) ($trend['value'] ?? 0);
    $up    = (bool) ($trend['up'] ?? true);
    $capped = abs($value) >= 999.0;

    if ($value == 0.0) {
        $class = 'neutral';
        $suffix = '→';
    } elseif ($up) {
        $class = 'up';
        $suffix = '+';
    } else {
        $class = 'down';
        $suffix = '↓';
    }

    $inlineClass = $inline ? ' kpi-trend-inline' : '';
    if ($capped) {
        $formatted = '999+';
    } else {
        $formatted = number_format($value, fmod($value, 1.0) === 0.0 ? 0 : 1);
    }

    return sprintf(
        '<span class="kpi-trend %s%s" title="Vs. previous month">%s%% %s</span>',
        $class,
        $inlineClass,
        $formatted,
        $suffix
    );
}

function notificationIcon(string $type): string
{
    $icons = [
        'invoice'     => 'bi-receipt',
        'payment'     => 'bi-credit-card',
        'appointment' => 'bi-calendar-event',
        'document'    => 'bi-file-earmark',
        'case'        => 'bi-briefcase',
        'account'     => 'bi-person-plus',
        'system'      => 'bi-bell',
    ];

    return $icons[$type] ?? 'bi-bell';
}

function priorityBadge(string $priority): string
{
    $map = [
        'low'    => 'badge-default',
        'medium' => 'badge-scheduled',
        'high'   => 'badge-partial',
        'urgent' => 'badge-overdue',
    ];

    $class = $map[$priority] ?? 'badge-default';
    return sprintf('<span class="status-badge %s">%s</span>', $class, e(ucfirst($priority)));
}

function paymentStatusBadge(string $status): string
{
    $map = [
        'pending'   => 'badge-pending',
        'completed' => 'badge-paid',
        'failed'    => 'badge-overdue',
        'refunded'  => 'badge-closed',
    ];

    $labels = [
        'pending'   => 'Pending',
        'completed' => 'Completed',
        'failed'    => 'Failed',
        'refunded'  => 'Refunded',
    ];

    $class = $map[$status] ?? 'badge-default';
    $label = $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));

    return sprintf('<span class="status-badge %s">%s</span>', $class, e($label));
}

function paymentMethodBadge(string $method): string
{
    $map = [
        'stripe'         => ['badge-scheduled', 'Stripe'],
        'bank_transfer'  => ['badge-default', 'Bank Transfer'],
        'cash'           => ['badge-default', 'Cash'],
        'check'          => ['badge-default', 'Check'],
        'other'          => ['badge-default', 'Other'],
    ];

    [$class, $label] = $map[$method] ?? ['badge-default', ucwords(str_replace('_', ' ', $method))];

    return sprintf('<span class="status-badge %s">%s</span>', $class, e($label));
}

function requestPageNumber(string $param = 'page'): int
{
    $page = (int) ($_GET[$param] ?? 1);
    return max(1, $page);
}

function paginationOffset(int $page, int $perPage): int
{
    return max(0, ($page - 1) * $perPage);
}

/**
 * Password input with show/hide eye toggle (wired by password-reveal.js).
 *
 * @param array{
 *   class?: string,
 *   required?: bool,
 *   disabled?: bool,
 *   autocomplete?: string,
 *   placeholder?: string,
 *   minlength?: int,
 *   pattern?: string,
 *   title?: string,
 *   value?: string,
 * } $options
 */
function renderPasswordRevealField(string $id, string $name, array $options = []): void
{
    $inputClass = trim(($options['class'] ?? 'form-control') . ' login-pw-input login-pw-masked');
    $required = !empty($options['required']);
    $disabled = !empty($options['disabled']);
    $autocomplete = (string) ($options['autocomplete'] ?? 'off');
    $placeholder = (string) ($options['placeholder'] ?? '');
    $minlength = isset($options['minlength']) ? (int) $options['minlength'] : 0;
    $pattern = (string) ($options['pattern'] ?? '');
    $title = (string) ($options['title'] ?? '');
    $value = (string) ($options['value'] ?? '');
    $strength = !empty($options['strength']);
    $strengthOptional = !empty($options['strength_optional']);

    if ($strength) {
        if ($minlength === 0) {
            $minlength = 8;
        }
        if ($pattern === '') {
            $pattern = passwordStrengthPattern();
        }
        if ($title === '') {
            $title = passwordStrengthHint();
        }
    }
    ?>
    <div class="login-pw-field">
        <div class="login-pw-input-wrap">
            <input
                type="text"
                id="<?= e($id) ?>"
                name="<?= e($name) ?>"
                class="<?= e($inputClass) ?>"
                spellcheck="false"
                <?= $required ? 'required' : '' ?>
                <?= $disabled ? 'disabled' : '' ?>
                autocomplete="<?= e($autocomplete) ?>"
                <?= $placeholder !== '' ? 'placeholder="' . e($placeholder) . '"' : '' ?>
                <?= $minlength > 0 ? 'minlength="' . $minlength . '"' : '' ?>
                <?= $pattern !== '' ? 'pattern="' . e($pattern) . '"' : '' ?>
                <?= $title !== '' ? 'title="' . e($title) . '"' : '' ?>
                <?= $value !== '' ? 'value="' . e($value) . '"' : '' ?>
                <?= $strength ? 'data-password-strength' : '' ?>
                <?= $strength && $strengthOptional ? 'data-password-strength-optional' : '' ?>
                data-lpignore="true"
                data-1p-ignore="true"
            >
            <button type="button" class="login-pw-reveal" aria-label="Show password" aria-pressed="false" title="Show password">
                <i class="bi bi-eye login-pw-icon-show" aria-hidden="true"></i>
                <i class="bi bi-eye-slash login-pw-icon-hide" aria-hidden="true"></i>
            </button>
        </div>
    </div>
    <?php
}

function buildPaginationUrl(int $page, string $pageParam = 'page', ?string $fragment = null): string
{
    $query = $_GET;
    $query[$pageParam] = $page;

    $url = '?' . http_build_query($query);
    if ($fragment !== null && $fragment !== '') {
        $url .= '#' . ltrim($fragment, '#');
    }

    return $url;
}

function renderPaginationNav(int $page, int $totalPages, string $pageParam = 'page', ?string $fragment = null): string
{
    if ($totalPages <= 1) {
        return '';
    }

    $start = max(1, $page - 2);
    $end = min($totalPages, $page + 2);
    if ($end - $start < 4) {
        if ($start === 1) {
            $end = min($totalPages, $start + 4);
        } elseif ($end === $totalPages) {
            $start = max(1, $end - 4);
        }
    }

    $html = '<nav aria-label="Pagination" class="saas-pagination-nav"><ul class="pagination pagination-sm mb-0">';

    $prevDisabled = $page <= 1 ? ' disabled' : '';
    $prevHref = $page > 1 ? buildPaginationUrl($page - 1, $pageParam, $fragment) : '#';
    $html .= '<li class="page-item' . $prevDisabled . '">'
        . '<a class="page-link" href="' . e($prevHref) . '" aria-label="Previous">&laquo;</a></li>';

    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $page ? ' active' : '';
        $html .= '<li class="page-item' . $active . '">'
            . '<a class="page-link" href="' . e(buildPaginationUrl($i, $pageParam, $fragment)) . '">' . $i . '</a></li>';
    }

    $nextDisabled = $page >= $totalPages ? ' disabled' : '';
    $nextHref = $page < $totalPages ? buildPaginationUrl($page + 1, $pageParam, $fragment) : '#';
    $html .= '<li class="page-item' . $nextDisabled . '">'
        . '<a class="page-link" href="' . e($nextHref) . '" aria-label="Next">&raquo;</a></li>';

    $html .= '</ul></nav>';

    return $html;
}

function normalizeSearchTerm(?string $term): string
{
    return trim((string) $term);
}

function countClients(?string $search = null): int
{
    $search = normalizeSearchTerm($search);
    $sql = 'SELECT COUNT(*) AS c FROM clients c';
    $params = [];
    $where = [];

    TenantService::appendScope($where, $params, 'c');

    if ($search !== '') {
        $where[] = 'CONCAT_WS(" ", c.first_name, c.last_name, c.email, c.phone, c.company_name, c.address, c.city, c.state, c.country) LIKE ?';
        $params[] = '%' . $search . '%';
    }

    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    return (int) (Database::fetch($sql, $params)['c'] ?? 0);
}

function getClientsPaginated(int $page, int $perPage = 10, ?string $search = null): array
{
    $search = normalizeSearchTerm($search);
    $offset = paginationOffset($page, $perPage);
    $params = [];
    $where = [];

    TenantService::appendScope($where, $params, 'c');

    if ($search !== '') {
        $where[] = 'CONCAT_WS(" ", c.first_name, c.last_name, c.email, c.phone, c.company_name, c.address, c.city, c.state, c.country) LIKE ?';
        $params[] = '%' . $search . '%';
    }

    $whereSql = $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';

    $params[] = $perPage;
    $params[] = $offset;

    return Database::fetchAll(
        'SELECT c.*, c.status AS user_status,
                (SELECT COUNT(*) FROM cases cs WHERE cs.client_id = c.id) AS case_count
         FROM clients c
         ' . $whereSql . '
         ORDER BY c.last_name ASC, c.first_name ASC
         LIMIT ? OFFSET ?',
        $params
    );
}

function appendAssignedCaseScope(array &$where, array &$params, string $alias = 'cs'): void
{
    if (!Auth::restrictsToAssignedCases()) {
        return;
    }

    $userId = Auth::id();
    if ($userId) {
        $where[] = $alias . '.assigned_admin_id = ?';
        $params[] = $userId;
    }
}

function appendCaseTenantScope(array &$where, array &$params, string $caseAlias = 'cs', string $clientAlias = 'cl'): void
{
    if (!TenantService::isEnabled()) {
        return;
    }

    $companyId = TenantService::id();
    $caseCol = rtrim($caseAlias, '.') . '.company_id';
    $clientCol = rtrim($clientAlias, '.') . '.company_id';
    $where[] = "({$caseCol} = ? OR (COALESCE({$caseCol}, 0) = 0 AND {$clientCol} = ?))";
    $params[] = $companyId;
    $params[] = $companyId;
}

function countCases(?string $search = null): int
{
    $search = normalizeSearchTerm($search);
    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = 'CONCAT_WS(" ", cs.case_number, cs.title, cs.service_type, cl.first_name, cl.last_name, cl.company_name) LIKE ?';
        $params[] = '%' . $search . '%';
    }

    appendCaseTenantScope($where, $params, 'cs', 'cl');
    appendAssignedCaseScope($where, $params, 'cs');

    $sql = 'SELECT COUNT(*) AS c FROM cases cs JOIN clients cl ON cl.id = cs.client_id';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    return (int) (Database::fetch($sql, $params)['c'] ?? 0);
}

function getCasesPaginated(int $page, int $perPage = 10, ?string $search = null): array
{
    $search = normalizeSearchTerm($search);
    $offset = paginationOffset($page, $perPage);
    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = 'CONCAT_WS(" ", cs.case_number, cs.title, cs.service_type, cl.first_name, cl.last_name, cl.company_name) LIKE ?';
        $params[] = '%' . $search . '%';
    }

    appendCaseTenantScope($where, $params, 'cs', 'cl');
    appendAssignedCaseScope($where, $params, 'cs');

    $whereSql = $where === [] ? '' : (' WHERE ' . implode(' AND ', $where));
    $params[] = $perPage;
    $params[] = $offset;

    return Database::fetchAll(
        "SELECT cs.*, cl.first_name, cl.last_name, cl.email, cl.company_name,
                adm.name AS admin_name
         FROM cases cs
         JOIN clients cl ON cl.id = cs.client_id
         LEFT JOIN users adm ON adm.id = cs.assigned_admin_id
         {$whereSql}
         ORDER BY cs.updated_at DESC
         LIMIT ? OFFSET ?",
        $params
    );
}

function countPayments(?string $search = null, ?string $status = null, ?string $method = null, ?string $month = null): int
{
    syncOverdueInvoices();
    $paymentCol = paymentStatusColumn();
    $search = normalizeSearchTerm($search);
    $status = normalizeSearchTerm($status);
    $method = normalizeSearchTerm($method);
    $month = normalizeSearchTerm($month);
    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = 'CONCAT_WS(" ", i.invoice_number, cl.first_name, cl.last_name, cl.company_name, p.payment_method, r.receipt_number) LIKE ?';
        $params[] = '%' . $search . '%';
    }
    if ($status !== '') {
        $where[] = "p.{$paymentCol} = ?";
        $params[] = $status;
    }
    if ($method !== '') {
        $where[] = 'p.payment_method = ?';
        $params[] = $method;
    }
    if ($month !== '' && preg_match('/^(0?[1-9]|1[0-2])$/', $month)) {
        $where[] = 'MONTH(COALESCE(p.paid_at, p.created_at)) = ?';
        $params[] = (int) $month;
    }
    TenantService::appendClientScope($where, $params, 'cl');

    $sql = "SELECT COUNT(*) AS c
            FROM payments p
            JOIN invoices i ON i.id = p.invoice_id
            JOIN clients cl ON cl.id = i.client_id
            LEFT JOIN receipts r ON r.payment_id = p.id";
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    return (int) (Database::fetch($sql, $params)['c'] ?? 0);
}

function getPaymentsPaginated(int $page, int $perPage = 10, ?string $search = null, ?string $status = null, ?string $method = null, ?string $month = null): array
{
    syncOverdueInvoices();
    $paymentCol = paymentStatusColumn();
    $search = normalizeSearchTerm($search);
    $status = normalizeSearchTerm($status);
    $method = normalizeSearchTerm($method);
    $month = normalizeSearchTerm($month);
    $offset = paginationOffset($page, $perPage);
    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = 'CONCAT_WS(" ", i.invoice_number, cl.first_name, cl.last_name, cl.company_name, p.payment_method, r.receipt_number) LIKE ?';
        $params[] = '%' . $search . '%';
    }
    if ($status !== '') {
        $where[] = "p.{$paymentCol} = ?";
        $params[] = $status;
    }
    if ($method !== '') {
        $where[] = 'p.payment_method = ?';
        $params[] = $method;
    }
    if ($month !== '' && preg_match('/^(0?[1-9]|1[0-2])$/', $month)) {
        $where[] = 'MONTH(COALESCE(p.paid_at, p.created_at)) = ?';
        $params[] = (int) $month;
    }
    TenantService::appendClientScope($where, $params, 'cl');
    $whereSql = $where === [] ? '' : (' WHERE ' . implode(' AND ', $where));
    $params[] = $perPage;
    $params[] = $offset;

    return Database::fetchAll(
        "SELECT p.*, p.{$paymentCol} AS payment_status, i.invoice_number, i.total AS invoice_total, i.case_id,
                cl.first_name, cl.last_name, cl.company_name,
                cs.service_type, cs.title AS case_title, cs.case_number, cs.services,
                r.id AS receipt_id, r.receipt_number
         FROM payments p
         JOIN invoices i ON i.id = p.invoice_id
         JOIN clients cl ON cl.id = i.client_id
         LEFT JOIN cases cs ON cs.id = i.case_id
         LEFT JOIN receipts r ON r.payment_id = p.id
         {$whereSql}
         ORDER BY COALESCE(p.paid_at, p.created_at) DESC
         LIMIT ? OFFSET ?",
        $params
    );
}

function countInvoices(?string $search = null, ?string $status = null, ?string $month = null): int
{
    syncOverdueInvoices();
    $statusCol = invoiceStatusColumn();
    $search = normalizeSearchTerm($search);
    $status = normalizeSearchTerm($status);
    $month = normalizeSearchTerm($month);
    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = 'CONCAT_WS(" ", i.invoice_number, cl.first_name, cl.last_name, cl.company_name, cs.case_number, cs.title) LIKE ?';
        $params[] = '%' . $search . '%';
    }
    if ($status !== '') {
        $where[] = "i.{$statusCol} = ?";
        $params[] = $status;
    }
    if ($month !== '' && preg_match('/^(0?[1-9]|1[0-2])$/', $month)) {
        $where[] = 'MONTH(COALESCE(i.issue_date, i.created_at)) = ?';
        $params[] = (int) $month;
    }
    TenantService::appendClientScope($where, $params, 'cl');

    $sql = 'SELECT COUNT(*) AS c
            FROM invoices i
            JOIN clients cl ON cl.id = i.client_id
            LEFT JOIN cases cs ON cs.id = i.case_id';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    return (int) (Database::fetch($sql, $params)['c'] ?? 0);
}

function getInvoicesPaginated(int $page, int $perPage = 10, ?string $search = null, ?string $status = null, ?string $month = null): array
{
    syncOverdueInvoices();
    $statusCol = invoiceStatusColumn();
    $search = normalizeSearchTerm($search);
    $status = normalizeSearchTerm($status);
    $month = normalizeSearchTerm($month);
    $offset = paginationOffset($page, $perPage);
    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = 'CONCAT_WS(" ", i.invoice_number, cl.first_name, cl.last_name, cl.company_name, cs.case_number, cs.title) LIKE ?';
        $params[] = '%' . $search . '%';
    }
    if ($status !== '') {
        $where[] = "i.{$statusCol} = ?";
        $params[] = $status;
    }
    if ($month !== '' && preg_match('/^(0?[1-9]|1[0-2])$/', $month)) {
        $where[] = 'MONTH(COALESCE(i.issue_date, i.created_at)) = ?';
        $params[] = (int) $month;
    }
    TenantService::appendClientScope($where, $params, 'cl');
    $whereSql = $where === [] ? '' : (' WHERE ' . implode(' AND ', $where));
    $params[] = $perPage;
    $params[] = $offset;

    return Database::fetchAll(
        "SELECT i.*, i.{$statusCol} AS payment_status,
                cl.first_name, cl.last_name, cl.company_name,
                cs.case_number, cs.title AS case_title
         FROM invoices i
         JOIN clients cl ON cl.id = i.client_id
         LEFT JOIN cases cs ON cs.id = i.case_id
         {$whereSql}
         ORDER BY COALESCE(i.issue_date, i.created_at) DESC, i.id DESC
         LIMIT ? OFFSET ?",
        $params
    );
}

function getAllClients(): array
{
    return getClientsPaginated(1, max(1, countClients()));
}

function getAllCases(): array
{
    return getCasesPaginated(1, max(1, countCases()));
}

function getAllPayments(): array
{
    return getPaymentsPaginated(1, max(1, countPayments()));
}

function getAllAppointments(): array
{
    $startSql = appointmentStartSql('a');
    $endSql   = appointmentEndSql('a');
    $where = [];
    $params = [];
    TenantService::appendClientScope($where, $params, 'cl');
    $whereSql = $where === [] ? '' : (' WHERE ' . implode(' AND ', $where));

    $appointments = Database::fetchAll(
        "SELECT a.*, {$startSql} AS start_time, {$endSql} AS end_time,
                cl.first_name, cl.last_name, cl.company_name,
                cs.case_number, cs.title AS case_title, cs.service_type, cs.services
         FROM appointments a
         JOIN clients cl ON cl.id = a.client_id
         LEFT JOIN cases cs ON cs.id = a.case_id
         {$whereSql}
         ORDER BY {$startSql} DESC",
        $params
    );

    foreach ($appointments as &$appointment) {
        enrichAppointmentCase($appointment, true);
    }
    unset($appointment);

    return $appointments;
}

function getClientDashboardStats(int $clientId): array
{
    $activeCases = (int) (Database::fetch(
        "SELECT COUNT(*) AS c FROM cases WHERE client_id = ? AND status IN ('pending','in_progress','waiting_for_client')",
        [$clientId]
    )['c'] ?? 0);

    $pendingInvoices = (int) (Database::fetch(
        'SELECT COUNT(*) AS c FROM invoices WHERE client_id = ? AND ' . invoiceStatusColumn() . " IN ('pending','overdue','partially_paid')",
        [$clientId]
    )['c'] ?? 0);

    $documents = (int) (Database::fetch(
        'SELECT COUNT(*) AS c FROM documents d JOIN cases cs ON cs.id = d.case_id WHERE cs.client_id = ?',
        [$clientId]
    )['c'] ?? 0);

    return [
        'active_cases'          => $activeCases,
        'pending_invoices'      => $pendingInvoices,
        'documents'             => $documents,
        'upcoming_appointments' => count(getClientUpcomingAppointments($clientId, 1000)),
    ];
}

function getClientCases(int $clientId): array
{
    return Database::fetchAll(
        'SELECT c.*,
                (SELECT COUNT(*) FROM documents d WHERE d.case_id = c.id) AS document_count
         FROM cases c
         WHERE c.client_id = ?
         ORDER BY c.updated_at DESC',
        [$clientId]
    );
}

function getClientRecentCases(int $clientId, int $limit = 5): array
{
    return Database::fetchAll(
        'SELECT * FROM cases WHERE client_id = ? ORDER BY updated_at DESC LIMIT ?',
        [$clientId, $limit]
    );
}

function getClientUpcomingAppointments(int $clientId, int $limit = 5): array
{
    $appointments = getClientAppointments($clientId);
    $visible = [];

    foreach ($appointments as $appointment) {
        if (!isClientScheduledAppointment($appointment)) {
            continue;
        }

        $appointment['start_time'] = appointmentEffectiveStart($appointment);
        $appointment['end_time']   = appointmentEffectiveEnd($appointment);
        $visible[] = $appointment;
    }

    usort($visible, static function (array $a, array $b): int {
        $aStart = strtotime(appointmentEffectiveStart($a) ?? '');
        $bStart = strtotime(appointmentEffectiveStart($b) ?? '');
        $now    = time();

        $aActive = isUpcomingAppointment($a);
        $bActive = isUpcomingAppointment($b);

        if ($aActive !== $bActive) {
            return $bActive <=> $aActive;
        }

        return $aStart <=> $bStart;
    });

    return array_slice($visible, 0, $limit);
}

function getClientAppointments(int $clientId): array
{
    $startSql = appointmentStartSql('a');
    $endSql   = appointmentEndSql('a');

    $appointments = Database::fetchAll(
        "SELECT a.*, {$startSql} AS start_time, {$endSql} AS end_time,
                cs.case_number, cs.title AS case_title
         FROM appointments a
         LEFT JOIN cases cs ON cs.id = a.case_id
         WHERE a.client_id = ?
         ORDER BY {$startSql} DESC",
        [$clientId]
    );

    foreach ($appointments as &$appointment) {
        enrichAppointmentCase($appointment, true);
    }
    unset($appointment);

    return $appointments;
}

function getClientInvoices(int $clientId): array
{
    syncOverdueInvoices();
    $statusCol = invoiceStatusColumn();

    return Database::fetchAll(
        "SELECT i.*, i.{$statusCol} AS payment_status, cs.case_number, cs.title AS case_title
         FROM invoices i
         LEFT JOIN cases cs ON cs.id = i.case_id
         WHERE i.client_id = ?
         ORDER BY i.created_at DESC",
        [$clientId]
    );
}

function getClientPayments(int $clientId): array
{
    $paymentStatus = paymentStatusColumn();

    return Database::fetchAll(
        "SELECT p.*, p.{$paymentStatus} AS payment_status, i.invoice_number, i.total AS invoice_total,
                r.id AS receipt_id, r.receipt_number
         FROM payments p
         JOIN invoices i ON i.id = p.invoice_id
         LEFT JOIN receipts r ON r.payment_id = p.id
         WHERE i.client_id = ?
         ORDER BY COALESCE(p.paid_at, p.created_at) DESC",
        [$clientId]
    );
}

function clientNotificationRedirectTarget(array $notif): string
{
    $target = resolveNotificationRedirect($notif['link'] ?? null);

    if (str_starts_with($target, 'pages/case-view.php')) {
        return $target;
    }

    if (str_starts_with($target, 'pages/appointments.php')) {
        return 'pages/appointments.php';
    }

    if (str_starts_with($target, 'pages/payments.php') || ($notif['type'] ?? '') === 'invoice' || ($notif['type'] ?? '') === 'payment') {
        return 'pages/payments.php';
    }

    if (str_starts_with($target, 'http://') || str_starts_with($target, 'https://')) {
        return $target;
    }

    return 'pages/dashboard.php';
}

function caseActivityIcon(string $type): string
{
    $map = [
        'case_created' => 'bi-briefcase',
        'document'     => 'bi-file-earmark-arrow-up',
        'invoice'      => 'bi-receipt',
        'payment'      => 'bi-cash-coin',
        'proposal'     => 'bi-file-text',
        'quotation'    => 'bi-file-earmark-text',
        'note'         => 'bi-journal-text',
        'status'       => 'bi-arrow-repeat',
        'appointment'  => 'bi-calendar-event',
        'update'       => 'bi-pencil-square',
    ];

    return $map[$type] ?? 'bi-activity';
}

function caseActivityTone(string $type): string
{
    $map = [
        'case_created' => 'tone-teal',
        'document'     => 'tone-blue',
        'invoice'      => 'tone-orange',
        'payment'      => 'tone-green',
        'proposal'     => 'tone-purple',
        'quotation'    => 'tone-teal',
        'note'         => 'tone-gray',
        'status'       => 'tone-indigo',
        'appointment'  => 'tone-blue',
        'update'       => 'tone-gray',
    ];

    return $map[$type] ?? 'tone-gray';
}

function caseActivityDateLabel(string $datetime): string
{
    $time = strtotime($datetime);
    $today = strtotime('today');
    $yesterday = strtotime('yesterday');

    if ($time >= $today) {
        return 'Today';
    }

    if ($time >= $yesterday) {
        return 'Yesterday';
    }

    return date('F j, Y', $time);
}

function passwordStrengthHint(): string
{
    return 'Password must be at least 8 characters, including uppercase(s), lowercase(s), and number(s).';
}

function passwordStrengthPattern(): string
{
    return '(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}';
}

function passwordStrengthError(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        return 'Password must contain at least one uppercase letter.';
    }

    if (!preg_match('/[a-z]/', $password)) {
        return 'Password must contain at least one lowercase letter.';
    }

    if (!preg_match('/[0-9]/', $password)) {
        return 'Password must contain at least one number.';
    }

    return null;
}

function renderPasswordStrengthHint(string $class = 'form-text mb-0', bool $optional = false): void
{
    $prefix = $optional ? 'If setting a new password: ' : '';
    echo '<p class="' . e($class) . '">' . e($prefix . passwordStrengthHint()) . '</p>';
}
