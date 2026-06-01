<?php

declare(strict_types=1);

class GoogleCalendarService
{
    public static function syncAppointment(int $appointmentId, array $client): array
    {
        $appointment = AppointmentService::getById($appointmentId);
        if (!$appointment) {
            return ['success' => false, 'url' => null];
        }

        if (GoogleOAuthService::isConnected()) {
            return self::syncViaApi($appointmentId, $appointment, $client);
        }

        return self::syncViaLink($appointmentId, $appointment, $client);
    }

    public static function removeFromCalendar(int $appointmentId): void
    {
        if (!GoogleOAuthService::isConnected()) {
            return;
        }

        $appointment = AppointmentService::getById($appointmentId);
        if (!$appointment || empty($appointment['google_event_id'])) {
            return;
        }

        $token = GoogleOAuthService::getValidAccessToken();
        if (!$token) {
            return;
        }

        $settings   = getCompanySettings();
        $calendarId = rawurlencode($settings['google_calendar_id'] ?: 'primary');
        $eventId    = rawurlencode($appointment['google_event_id']);
        $url        = "https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events/{$eventId}";

        self::apiRequest('DELETE', $url, $token);

        try {
            Database::query('UPDATE appointments SET google_event_id = NULL, updated_at = NOW() WHERE id = ?', [$appointmentId]);
        } catch (Throwable $e) {
            // optional column
        }
    }

    public static function getCalendarLinks(int $appointmentId, array $appointment, array $client, bool $forClientPortal = false): array
    {
        return [
            'google'  => $appointment['meeting_link'] ?? self::buildAddToCalendarUrl($appointment, $client),
            'outlook' => self::buildOutlookCalendarUrl($appointment, $client),
            'ics'     => $forClientPortal
                ? clientUrl('actions/appointment-ics.php?id=' . $appointmentId)
                : url('actions/appointment-ics.php?id=' . $appointmentId),
        ];
    }

    public static function buildOutlookCalendarUrl(array $appointment, array $client): string
    {
        $start = appointmentStart($appointment);
        if (!$start) {
            return '';
        }

        $end = appointmentEnd($appointment) ?: date('Y-m-d H:i:s', strtotime($start . ' +1 hour'));

        $params = [
            'path'     => '/calendar/action/compose',
            'rru'      => 'addevent',
            'subject'  => $appointment['title'] ?? 'Appointment',
            'startdt'  => date('Y-m-d\TH:i:s', strtotime($start)),
            'enddt'    => date('Y-m-d\TH:i:s', strtotime($end)),
            'body'     => self::eventDescription($appointment, $client),
            'location' => $appointment['location'] ?? '',
        ];

        return 'https://outlook.live.com/calendar/0/deeplink/compose?' . http_build_query($params);
    }

    public static function buildIcsContent(array $appointment, array $client): string
    {
        $start = appointmentStart($appointment);
        $end   = appointmentEnd($appointment) ?: date('Y-m-d H:i:s', strtotime($start . ' +1 hour'));
        $uid   = 'appointment-' . ($appointment['id'] ?? uniqid()) . '@casemanagement';
        $stamp = gmdate('Ymd\THis\Z');
        $settings = getCompanySettings();
        $reminderHours = max(1, min(168, (int) ($settings['appointment_reminder_hours'] ?? 24)));

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Notary Management System//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . $stamp,
            'DTSTART:' . self::toIcsDate($start),
            'DTEND:' . self::toIcsDate($end),
            'SUMMARY:' . self::icsEscape($appointment['title'] ?? 'Appointment'),
            'DESCRIPTION:' . self::icsEscape(self::eventDescription($appointment, $client)),
            'LOCATION:' . self::icsEscape($appointment['location'] ?? ''),
            'STATUS:CONFIRMED',
        ];

        if ($reminderHours >= 24) {
            $lines[] = 'BEGIN:VALARM';
            $lines[] = 'TRIGGER:-P1D';
            $lines[] = 'ACTION:DISPLAY';
            $lines[] = 'DESCRIPTION:Appointment reminder';
            $lines[] = 'END:VALARM';
        }

        if ($reminderHours <= 1) {
            $lines[] = 'BEGIN:VALARM';
            $lines[] = 'TRIGGER:-PT1H';
            $lines[] = 'ACTION:DISPLAY';
            $lines[] = 'DESCRIPTION:Appointment reminder';
            $lines[] = 'END:VALARM';
        } elseif ($reminderHours < 24) {
            $lines[] = 'BEGIN:VALARM';
            $lines[] = 'TRIGGER:-PT' . ($reminderHours * 60) . 'M';
            $lines[] = 'ACTION:DISPLAY';
            $lines[] = 'DESCRIPTION:Appointment reminder';
            $lines[] = 'END:VALARM';
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines) . "\r\n";
    }

    public static function saveIcsFile(int $appointmentId, string $content): ?string
    {
        $config = require __DIR__ . '/../config/config.php';
        $dir    = rtrim($config['upload']['path'], '/\\') . '/calendar';

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = 'appointment-' . $appointmentId . '.ics';
        $fullPath = $dir . '/' . $filename;

        file_put_contents($fullPath, $content);

        return 'calendar/' . $filename;
    }

    public static function getIcsFilePath(int $appointmentId): ?string
    {
        $config   = require __DIR__ . '/../config/config.php';
        $relative = 'calendar/appointment-' . $appointmentId . '.ics';
        $fullPath = rtrim($config['upload']['path'], '/\\') . '/' . $relative;

        if (is_file($fullPath)) {
            return $fullPath;
        }

        $appointment = AppointmentService::getById($appointmentId);
        if (!$appointment) {
            return null;
        }

        $client = ClientService::getById((int) ($appointment['client_id'] ?? 0)) ?? $appointment;
        self::saveIcsFile($appointmentId, self::buildIcsContent($appointment, $client));

        return is_file($fullPath) ? $fullPath : null;
    }

    public static function buildAddToCalendarUrl(array $appointment, array $client): string
    {
        $start = appointmentStart($appointment);
        $end   = appointmentEnd($appointment) ?: date('Y-m-d H:i:s', strtotime($start . ' +1 hour'));

        $params = [
            'action'   => 'TEMPLATE',
            'text'     => $appointment['title'] ?? 'Appointment',
            'dates'    => self::formatGoogleDates($start, $end),
            'details'  => self::eventDescription($appointment, $client),
            'location' => $appointment['location'] ?? '',
        ];

        if (!empty($client['email'])) {
            $params['add'] = $client['email'];
        }

        return 'https://calendar.google.com/calendar/render?' . http_build_query($params);
    }

    private static function syncViaLink(int $appointmentId, array $appointment, array $client): array
    {
        $addUrl  = self::buildAddToCalendarUrl($appointment, $client);
        $icsPath = self::saveIcsFile($appointmentId, self::buildIcsContent($appointment, $client));

        self::storeMeetingLink($appointmentId, $addUrl);

        return [
            'success'  => true,
            'url'      => $addUrl,
            'ics_url'  => url('actions/appointment-ics.php?id=' . $appointmentId),
            'ics_path' => $icsPath,
            'message'  => 'Google Calendar link ready.',
            'mode'     => 'link',
        ];
    }

    private static function syncViaApi(int $appointmentId, array $appointment, array $client): array
    {
        $token = GoogleOAuthService::getValidAccessToken();
        if (!$token) {
            return self::syncViaLink($appointmentId, $appointment, $client);
        }

        $settings   = getCompanySettings();
        $calendarId = rawurlencode($settings['google_calendar_id'] ?: 'primary');
        $payload    = self::buildApiEventPayload($appointment, $client);
        $eventId    = $appointment['google_event_id'] ?? null;

        if ($eventId) {
            $url      = "https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events/" . rawurlencode($eventId);
            $response = self::apiRequest('PUT', $url, $token, $payload);
        } else {
            $url      = "https://www.googleapis.com/calendar/v3/calendars/{$calendarId}/events";
            $response = self::apiRequest('POST', $url, $token, $payload);
            $eventId  = $response['id'] ?? null;

            if ($eventId) {
                try {
                    Database::query('UPDATE appointments SET google_event_id = ?, updated_at = NOW() WHERE id = ?', [$eventId, $appointmentId]);
                } catch (Throwable $e) {
                    // optional column
                }
            }
        }

        $addUrl  = $response['htmlLink'] ?? self::buildAddToCalendarUrl($appointment, $client);
        $icsPath = self::saveIcsFile($appointmentId, self::buildIcsContent($appointment, $client));
        self::storeMeetingLink($appointmentId, $addUrl);

        return [
            'success'  => true,
            'url'      => $addUrl,
            'ics_url'  => url('actions/appointment-ics.php?id=' . $appointmentId),
            'ics_path' => $icsPath,
            'message'  => 'Synced to Google Calendar.',
            'mode'     => 'api',
            'event_id' => $eventId,
        ];
    }

    private static function buildApiEventPayload(array $appointment, array $client): array
    {
        $start = appointmentStart($appointment);
        $end   = appointmentEnd($appointment) ?: date('Y-m-d H:i:s', strtotime($start . ' +1 hour'));
        $settings = getCompanySettings();
        $reminderMinutes = max(60, min(10080, (int) ($settings['appointment_reminder_hours'] ?? 24) * 60));

        $payload = [
            'summary'     => $appointment['title'] ?? 'Appointment',
            'description' => self::eventDescription($appointment, $client),
            'location'    => $appointment['location'] ?? '',
            'start'       => [
                'dateTime' => date('c', strtotime($start)),
                'timeZone' => date_default_timezone_get(),
            ],
            'end' => [
                'dateTime' => date('c', strtotime($end)),
                'timeZone' => date_default_timezone_get(),
            ],
            'reminders' => [
                'useDefault' => false,
                'overrides'  => [
                    ['method' => 'email', 'minutes' => $reminderMinutes],
                    ['method' => 'popup', 'minutes' => min(60, $reminderMinutes)],
                ],
            ],
        ];

        if (!empty($client['email'])) {
            $payload['attendees'] = [['email' => $client['email']]];
        }

        return $payload;
    }

    private static function apiRequest(string $method, string $url, string $token, ?array $payload = null): array
    {
        $ch = curl_init($url);
        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ];

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => $method,
        ];

        if ($payload !== null && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $options[CURLOPT_POSTFIELDS] = json_encode($payload);
        }

        curl_setopt_array($ch, $options);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($method === 'DELETE' && ($code === 204 || $code === 410)) {
            return [];
        }

        $data = json_decode((string) $body, true);

        if ($code >= 400 || !is_array($data)) {
            $message = is_array($data) ? ($data['error']['message'] ?? 'Google Calendar API request failed.') : 'Google Calendar API request failed.';
            throw new RuntimeException((string) $message);
        }

        return $data;
    }

    private static function toIcsDate(string $datetime): string
    {
        return date('Ymd\THis', strtotime($datetime));
    }

    private static function icsEscape(string $value): string
    {
        return str_replace(["\\", ';', ',', "\n", "\r"], ['\\\\', '\;', '\,', '\n', ''], $value);
    }

    private static function storeMeetingLink(int $appointmentId, string $url): void
    {
        try {
            Database::query(
                'UPDATE appointments SET meeting_link = ?, updated_at = NOW() WHERE id = ?',
                [$url, $appointmentId]
            );
        } catch (Throwable $e) {
            // optional column
        }
    }

    private static function eventDescription(array $appointment, array $client): string
    {
        $parts = [];
        $parts[] = 'Client: ' . clientFullName($client);
        if (!empty($client['email'])) {
            $parts[] = 'Email: ' . $client['email'];
        }
        if (!empty($appointment['description'])) {
            $parts[] = $appointment['description'];
        }

        return implode("\n", $parts);
    }

    private static function formatGoogleDates(string $start, string $end): string
    {
        return self::toGoogleDate($start) . '/' . self::toGoogleDate($end);
    }

    private static function toGoogleDate(string $datetime): string
    {
        return date('Ymd\THis', strtotime($datetime));
    }
}
