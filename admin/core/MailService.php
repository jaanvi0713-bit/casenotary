<?php

declare(strict_types=1);

class MailService
{
    public static function send(string $to, string $subject, string $htmlBody, array $attachments = []): bool
    {
        $to = trim($to);
        if ($to === '') {
            return false;
        }

        $company = getCompanySettings();
        $from    = $company['office_email'] ?? 'noreply@localhost';
        $fromName = companyBrandName($company);

        self::logMail($to, $subject, $htmlBody, $attachments);

        if (!empty($company['smtp_host'])) {
            try {
                return self::sendViaSmtp($company, $to, $subject, $htmlBody, $from, $fromName);
            } catch (Throwable $e) {
                self::logMail($to, 'SMTP ERROR: ' . $e->getMessage(), '', []);
            }
        }

        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . self::encodeAddress($fromName, $from),
            'Reply-To: ' . $from,
            'X-Mailer: PHP/' . PHP_VERSION,
        ];

        $sent = @mail($to, self::encodeSubject($subject), $htmlBody, implode("\r\n", $headers));

        return $sent || self::isDebugMode();
    }

    public static function sendQuoteEmail(array $client, array $case, string $quotationNumber, ?string $documentPath = null): bool
    {
        $name     = clientFullName($client) ?: 'Client';
        $services = CaseService::getCaseServices($case);
        $serviceHtml = '';

        foreach ($services as $service) {
            $serviceHtml .= e($service['type']) . ' — ' . formatCurrency((float) $service['fee']) . '<br>';
        }

        $body = self::wrapTemplate(
            'Quotation — ' . e($case['title']),
            '<p>Dear ' . e($name) . ',</p>'
            . '<p>Please find your quotation <strong>' . e($quotationNumber) . '</strong> for case '
            . '<strong>' . e($case['case_number']) . '</strong>.</p>'
            . '<p><strong>Services:</strong><br>' . $serviceHtml
            . '<strong>Total:</strong> ' . formatCurrency((float) $case['service_fee']) . '</p>'
            . '<p>Log in to your client portal to review documents and next steps.</p>'
            . '<p><a href="' . e(clientUrl('auth/login.php')) . '" style="color:#3aafa9;">Open Client Portal</a></p>'
        );

        $attachments = $documentPath && is_file($documentPath) ? [$documentPath] : [];

        return self::send($client['email'], 'Your Quotation — ' . $case['case_number'], $body, $attachments);
    }

    public static function sendClientLetterEmail(array $client, array $case, ?string $documentPath = null): bool
    {
        $name = clientFullName($client) ?: 'Client';

        $body = self::wrapTemplate(
            'Client Letter — ' . e($case['title']),
            '<p>Dear ' . e($name) . ',</p>'
            . '<p>Please find your client letter for case <strong>' . e($case['case_number']) . '</strong> attached.</p>'
            . '<p>This letter confirms your case details and accompanies your quotation. Log in to your client portal to review documents and next steps.</p>'
            . '<p><a href="' . e(clientUrl('auth/login.php')) . '" style="color:#3aafa9;">Open Client Portal</a></p>'
        );

        $attachments = $documentPath && is_file($documentPath) ? [$documentPath] : [];

        return self::send($client['email'], 'Client Letter — ' . $case['case_number'], $body, $attachments);
    }

    public static function sendLoginEmail(array $client, string $instructions, ?string $plainPassword = null): bool
    {
        $name = clientFullName($client) ?: 'Client';
        $loginUrl = clientUrl('auth/login.php');

        $credentials = $plainPassword
            ? '<p><strong>Email:</strong> ' . e($client['email']) . '<br><strong>Temporary password:</strong> ' . e($plainPassword) . '</p>'
            : '<p>Use your existing portal password with email <strong>' . e($client['email']) . '</strong>.</p>';

        $body = self::wrapTemplate(
            'Client Portal Access',
            '<p>Dear ' . e($name) . ',</p>'
            . '<p>Your client portal account is ready.</p>'
            . $credentials
            . '<p><a href="' . e($loginUrl) . '" style="color:#3aafa9;">Sign in to Client Portal</a></p>'
            . ($instructions !== '' ? '<h3 style="font-size:15px;margin-top:20px;">Instructions</h3><p>' . nl2br(e($instructions)) . '</p>' : '')
        );

        return self::send($client['email'], 'Your Client Portal Login', $body);
    }

    public static function sendAppointmentEmail(array $client, array $appointment, ?array $calendarLinks = null, string $event = 'scheduled'): bool
    {
        $name  = clientFullName($client) ?: 'Client';
        $start = appointmentStart($appointment);
        $appointmentId = (int) ($appointment['id'] ?? 0);

        $links = $calendarLinks ?: GoogleCalendarService::getCalendarLinks($appointmentId, $appointment, $client, true);

        $headings = [
            'scheduled' => 'Appointment Scheduled',
            'updated'   => 'Appointment Updated',
            'cancelled' => 'Appointment Cancelled',
        ];

        $intros = [
            'scheduled' => 'An appointment has been scheduled for you.',
            'updated'   => 'Your appointment details have been updated.',
            'cancelled' => 'Your appointment has been cancelled.',
        ];

        $calendarButtons = '';
        if ($event !== 'cancelled') {
            $calendarButtons = '<p style="margin:20px 0;">'
                . '<a href="' . e($links['google']) . '" style="display:inline-block;background:#3aafa9;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none;font-weight:600;margin:0 8px 8px 0;">Add to Google Calendar</a>'
                . '<a href="' . e($links['outlook']) . '" style="display:inline-block;background:#0078d4;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none;font-weight:600;margin:0 8px 8px 0;">Add to Outlook Calendar</a>'
                . '<a href="' . e($links['ics']) . '" style="display:inline-block;background:#00182c;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none;font-weight:600;margin:0 8px 8px 0;">Download Calendar File</a>'
                . '</p>';
        }

        $body = self::wrapTemplate(
            $headings[$event] ?? 'Appointment Update',
            '<p>Dear ' . e($name) . ',</p>'
            . '<p>' . e($intros[$event] ?? 'There is an update to your appointment.') . '</p>'
            . '<p><strong>' . e($appointment['title']) . '</strong><br>'
            . '<strong>When:</strong> ' . e(formatDateTime($start)) . '<br>'
            . '<strong>Location:</strong> ' . e($appointment['location'] ?: 'To be confirmed') . '</p>'
            . (!empty($appointment['description']) ? '<p>' . nl2br(e($appointment['description'])) . '</p>' : '')
            . $calendarButtons
            . '<p><a href="' . e(clientUrl('pages/appointments.php')) . '" style="color:#3aafa9;">View in Client Portal</a></p>'
        );

        $subjects = [
            'scheduled' => 'Appointment: ',
            'updated'   => 'Updated appointment: ',
            'cancelled' => 'Cancelled appointment: ',
        ];

        return self::send($client['email'], ($subjects[$event] ?? 'Appointment: ') . $appointment['title'], $body);
    }

    public static function sendAppointmentRequestEmail(array $client, array $appointment): bool
    {
        $name  = clientFullName($client) ?: 'Client';
        $start = appointmentStart($appointment);

        $body = self::wrapTemplate(
            'Appointment Request Received',
            '<p>Dear ' . e($name) . ',</p>'
            . '<p>We received your appointment request and will review it shortly. You will be notified once it is confirmed.</p>'
            . '<p><strong>' . e($appointment['title']) . '</strong><br>'
            . '<strong>Preferred time:</strong> ' . e(formatDateTime($start)) . '<br>'
            . '<strong>Location:</strong> ' . e($appointment['location'] ?: 'To be confirmed') . '</p>'
            . (!empty($appointment['description']) ? '<p>' . nl2br(e($appointment['description'])) . '</p>' : '')
            . '<p><a href="' . e(clientUrl('pages/appointments.php')) . '" style="color:#3aafa9;">View in Client Portal</a></p>'
        );

        return self::send($client['email'], 'Appointment request received — ' . $appointment['title'], $body);
    }

    public static function sendAppointmentRequestAdminEmail(array $client, array $appointment): bool
    {
        $company = getCompanySettings();
        $to      = trim($company['office_email'] ?? '');
        if ($to === '') {
            return false;
        }

        $start = appointmentStart($appointment);
        $body  = self::wrapTemplate(
            'New Appointment Request',
            '<p><strong>Client:</strong> ' . e(clientFullName($client)) . '<br>'
            . '<strong>Email:</strong> ' . e($client['email'] ?? '') . '</p>'
            . '<p><strong>' . e($appointment['title']) . '</strong><br>'
            . '<strong>Preferred time:</strong> ' . e(formatDateTime($start)) . '<br>'
            . '<strong>Location:</strong> ' . e($appointment['location'] ?: 'Not specified') . '</p>'
            . (!empty($appointment['description']) ? '<p>' . nl2br(e($appointment['description'])) . '</p>' : '')
            . '<p><a href="' . e(url('pages/appointments.php')) . '" style="color:#3aafa9;">Review in Admin Portal</a></p>'
        );

        return self::send($to, 'New appointment request — ' . clientFullName($client), $body);
    }

    private static function wrapTemplate(string $title, string $content): string
    {
        $company     = getCompanySettings();
        $companyName = e(companyBrandName($company));
        $logoUrl     = companyLogoUrl($company);
        $logoHtml    = $logoUrl
            ? '<p style="margin:0 0 12px;"><img src="' . e($logoUrl) . '" alt="' . $companyName . '" style="max-height:48px;max-width:200px;width:auto;height:auto;object-fit:contain;"></p>'
            : '';

        return '<!DOCTYPE html><html><body style="font-family:Montserrat,Arial,sans-serif;color:#1e293b;line-height:1.6;">'
            . '<div style="max-width:560px;margin:0 auto;padding:24px;">'
            . $logoHtml
            . '<h2 style="color:#00182c;margin:0 0 16px;">' . $companyName . '</h2>'
            . '<h3 style="color:#3aafa9;margin:0 0 12px;">' . e($title) . '</h3>'
            . $content
            . '<hr style="border:none;border-top:1px solid #e2e8f0;margin:24px 0;">'
            . '<p style="font-size:12px;color:#64748b;">' . $companyName . '</p>'
            . '</div></body></html>';
    }

    private static function logMail(string $to, string $subject, string $body, array $attachments): void
    {
        $dir = __DIR__ . '/../storage/logs';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $entry = str_repeat('-', 60) . "\n"
            . date('Y-m-d H:i:s') . " | To: {$to}\n"
            . "Subject: {$subject}\n"
            . strip_tags($body) . "\n";

        if ($attachments) {
            $entry .= 'Attachments: ' . implode(', ', $attachments) . "\n";
        }

        file_put_contents($dir . '/mail.log', $entry, FILE_APPEND);
    }

    private static function isDebugMode(): bool
    {
        $config = require __DIR__ . '/../config/config.php';
        return !empty($config['debug']);
    }

    private static function encodeSubject(string $subject): string
    {
        return '=?UTF-8?B?' . base64_encode($subject) . '?=';
    }

    private static function encodeAddress(string $name, string $email): string
    {
        return '=?UTF-8?B?' . base64_encode($name) . '?= <' . $email . '>';
    }

    private static function sendViaSmtp(array $company, string $to, string $subject, string $htmlBody, string $from, string $fromName): bool
    {
        $host = $company['smtp_host'];
        $port = (int) ($company['smtp_port'] ?? 587);
        $user = $company['smtp_username'] ?? '';
        $pass = $company['smtp_password'] ?? '';
        $enc  = $company['smtp_encryption'] ?? 'tls';

        $remote = ($enc === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $socket = @stream_socket_client($remote, $errno, $errstr, 20);

        if (!$socket) {
            throw new RuntimeException("SMTP connect failed: {$errstr}");
        }

        stream_set_timeout($socket, 20);
        self::smtpExpect($socket, [220]);
        self::smtpCommand($socket, 'EHLO localhost', [250]);

        if ($enc === 'tls') {
            self::smtpCommand($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('SMTP STARTTLS failed.');
            }
            self::smtpCommand($socket, 'EHLO localhost', [250]);
        }

        if ($user !== '') {
            self::smtpCommand($socket, 'AUTH LOGIN', [334]);
            self::smtpCommand($socket, base64_encode($user), [334]);
            self::smtpCommand($socket, base64_encode($pass), [235]);
        }

        self::smtpCommand($socket, 'MAIL FROM:<' . $from . '>', [250]);
        self::smtpCommand($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
        self::smtpCommand($socket, 'DATA', [354]);

        $message = 'From: ' . self::encodeAddress($fromName, $from) . "\r\n"
            . 'To: <' . $to . ">\r\n"
            . 'Subject: ' . self::encodeSubject($subject) . "\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "\r\n"
            . $htmlBody . "\r\n.";

        self::smtpCommand($socket, $message, [250]);
        self::smtpCommand($socket, 'QUIT', [221]);
        fclose($socket);

        return true;
    }

    private static function smtpCommand($socket, string $command, array $okCodes): void
    {
        fwrite($socket, $command . "\r\n");
        self::smtpExpect($socket, $okCodes);
    }

    private static function smtpExpect($socket, array $okCodes): void
    {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $okCodes, true)) {
            throw new RuntimeException('SMTP error: ' . trim($response));
        }
    }
}
