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

        $addUrl  = self::buildAddToCalendarUrl($appointment, $client);
        $icsPath = self::saveIcsFile($appointmentId, self::buildIcsContent($appointment, $client));

        self::storeMeetingLink($appointmentId, $addUrl);

        return [
            'success'     => true,
            'url'         => $addUrl,
            'outlook_url' => self::buildOutlookCalendarUrl($appointment, $client),
            'ics_url'     => url('actions/appointment-ics.php?id=' . $appointmentId),
            'ics_path'    => $icsPath,
            'message'     => 'Google Calendar link ready.',
        ];
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

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//' . preg_replace('/[^A-Za-z0-9 ]/', '', companyBrandName()) . '//EN',
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
            'END:VEVENT',
            'END:VCALENDAR',
        ];

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

    private static function toIcsDate(string $datetime): string
    {
        return date('Ymd\THis', strtotime($datetime));
    }

    private static function icsEscape(string $value): string
    {
        $value = str_replace(["\\", ';', ',', "\n", "\r"], ['\\\\', '\;', '\,', '\n', ''], $value);

        return $value;
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
