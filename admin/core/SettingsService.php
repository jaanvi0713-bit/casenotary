<?php

declare(strict_types=1);

class SettingsService
{
    private static ?array $cache = null;

    public static function get(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $defaults = [
            'company_name'    => 'Notary Management',
            'primary_color'   => '#3aafa9',
            'secondary_color' => '#00182c',
            'dark_accent'     => '#000000',
            'font_family'     => 'Montserrat',
            'logo'            => null,
            'office_email'    => null,
            'office_phone'    => null,
            'business_hours'  => "Monday – Friday: 9:00 AM – 5:00 PM\nSaturday – Sunday: Closed",
            'address'         => null,
            'description'     => null,
            'smtp_host'       => null,
            'smtp_port'       => 587,
            'smtp_username'   => null,
            'smtp_password'   => null,
            'smtp_encryption' => 'tls',
            'stripe_public_key' => null,
            'stripe_secret_key' => null,
            'google_client_id'     => null,
            'google_client_secret' => null,
            'google_calendar_id'   => null,
            'google_access_token'  => null,
            'google_refresh_token' => null,
            'google_token_expires' => null,
            'appointment_reminder_hours' => 24,
        ];

        $row = Database::fetch('SELECT * FROM company_settings LIMIT 1');
        self::$cache = $row ? array_merge($defaults, $row) : $defaults;

        return self::$cache;
    }

    public static function clearCache(): void
    {
        self::$cache = null;
    }

    public static function update(array $data, ?array $logoFile = null): void
    {
        $settings = self::get();
        $id       = (int) ($settings['id'] ?? 0);

        if ($id <= 0) {
            throw new RuntimeException('Company settings record not found. Run database seed/migration.');
        }

        $logoPath = $settings['logo'] ?? null;

        if ($logoFile && !empty($logoFile['name']) && ($logoFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $logoPath = self::storeLogo($logoFile);
        }

        $smtpPassword = trim($data['smtp_password'] ?? '');
        if ($smtpPassword === '') {
            $smtpPassword = $settings['smtp_password'] ?? null;
        }

        $stripeSecret = trim($data['stripe_secret_key'] ?? '');
        if ($stripeSecret === '') {
            $stripeSecret = $settings['stripe_secret_key'] ?? null;
        }

        $params = [
            trim($data['company_name'] ?? 'Notary Management'),
            $logoPath,
            self::normalizeColor($data['primary_color'] ?? '#3aafa9'),
            self::normalizeColor($data['secondary_color'] ?? '#00182c'),
            self::normalizeColor($data['dark_accent'] ?? '#000000'),
            trim($data['font_family'] ?? 'Montserrat'),
            trim($data['description'] ?? '') ?: null,
            trim($data['office_email'] ?? '') ?: null,
            trim($data['office_phone'] ?? '') ?: null,
            trim($data['address'] ?? '') ?: null,
            trim($data['smtp_host'] ?? '') ?: null,
            (int) ($data['smtp_port'] ?? 587),
            trim($data['smtp_username'] ?? '') ?: null,
            $smtpPassword,
            in_array($data['smtp_encryption'] ?? 'tls', ['tls', 'ssl', 'none'], true)
                ? $data['smtp_encryption']
                : 'tls',
            trim($data['stripe_public_key'] ?? '') ?: null,
            $stripeSecret,
            $id,
        ];

        $businessHours = trim($data['business_hours'] ?? '') ?: null;

        if (Database::columnExists('company_settings', 'business_hours')) {
            array_splice($params, 9, 0, [$businessHours]);
            Database::query(
                'UPDATE company_settings SET
                    company_name = ?, logo = ?, primary_color = ?, secondary_color = ?, dark_accent = ?,
                    font_family = ?, description = ?, office_email = ?, office_phone = ?, business_hours = ?, address = ?,
                    smtp_host = ?, smtp_port = ?, smtp_username = ?, smtp_password = ?, smtp_encryption = ?,
                    stripe_public_key = ?, stripe_secret_key = ?, updated_at = NOW()
                 WHERE id = ?',
                $params
            );
        } else {
            Database::query(
                'UPDATE company_settings SET
                    company_name = ?, logo = ?, primary_color = ?, secondary_color = ?, dark_accent = ?,
                    font_family = ?, description = ?, office_email = ?, office_phone = ?, address = ?,
                    smtp_host = ?, smtp_port = ?, smtp_username = ?, smtp_password = ?, smtp_encryption = ?,
                    stripe_public_key = ?, stripe_secret_key = ?, updated_at = NOW()
                 WHERE id = ?',
                $params
            );
        }

        self::clearCache();
    }

    public static function updateCalendar(array $data): void
    {
        $settings = self::get();
        $id       = (int) ($settings['id'] ?? 0);

        if ($id <= 0) {
            throw new RuntimeException('Company settings record not found. Run database seed/migration.');
        }

        $clientSecret = trim($data['google_client_secret'] ?? '');
        if ($clientSecret === '') {
            $clientSecret = $settings['google_client_secret'] ?? null;
        }

        $hours = max(1, min(168, (int) ($data['appointment_reminder_hours'] ?? 24)));

        if (Database::columnExists('company_settings', 'appointment_reminder_hours')) {
            Database::query(
                'UPDATE company_settings SET google_client_id = ?, google_client_secret = ?, google_calendar_id = ?, appointment_reminder_hours = ?, updated_at = NOW() WHERE id = ?',
                [
                    trim($data['google_client_id'] ?? '') ?: null,
                    $clientSecret,
                    trim($data['google_calendar_id'] ?? '') ?: null,
                    $hours,
                    $id,
                ]
            );
        } else {
            Database::query(
                'UPDATE company_settings SET google_client_id = ?, google_client_secret = ?, google_calendar_id = ?, updated_at = NOW() WHERE id = ?',
                [
                    trim($data['google_client_id'] ?? '') ?: null,
                    $clientSecret,
                    trim($data['google_calendar_id'] ?? '') ?: null,
                    $id,
                ]
            );
        }

        self::clearCache();
    }

    public static function exportBackup(): array
    {
        $settings = self::get();
        $payload  = [];

        foreach (self::backupKeys() as $key) {
            if (array_key_exists($key, $settings) && Database::columnExists('company_settings', $key)) {
                $payload[$key] = $settings[$key];
            }
        }

        return [
            'version'     => 1,
            'app'         => 'casenotary',
            'exported_at' => date('c'),
            'settings'    => $payload,
        ];
    }

    public static function restoreBackup(array $backup): void
    {
        if (($backup['app'] ?? '') !== 'casenotary') {
            throw new RuntimeException('Invalid backup file for this application.');
        }

        if ((int) ($backup['version'] ?? 0) !== 1) {
            throw new RuntimeException('Unsupported backup version.');
        }

        $incoming = $backup['settings'] ?? null;
        if (!is_array($incoming)) {
            throw new RuntimeException('Backup file is missing settings data.');
        }

        $current = self::get();
        $id      = (int) ($current['id'] ?? 0);

        if ($id <= 0) {
            throw new RuntimeException('Company settings record not found.');
        }

        $data = [];
        foreach (self::backupKeys() as $key) {
            if (!array_key_exists($key, $incoming) || !Database::columnExists('company_settings', $key)) {
                continue;
            }
            $data[$key] = $incoming[$key];
        }

        if (!$data) {
            throw new RuntimeException('No restorable settings found in backup file.');
        }

        $columns = array_keys($data);
        $sets    = array_map(static fn ($column) => "{$column} = ?", $columns);
        $values  = array_values($data);
        $values[] = $id;

        Database::query(
            'UPDATE company_settings SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = ?',
            $values
        );

        self::clearCache();
    }

    private static function backupKeys(): array
    {
        return [
            'company_name',
            'logo',
            'primary_color',
            'secondary_color',
            'dark_accent',
            'font_family',
            'description',
            'office_email',
            'office_phone',
            'address',
            'smtp_host',
            'smtp_port',
            'smtp_username',
            'smtp_password',
            'smtp_encryption',
            'stripe_public_key',
            'stripe_secret_key',
            'google_calendar_id',
            'google_client_id',
            'google_client_secret',
            'google_access_token',
            'google_refresh_token',
            'google_token_expires',
            'appointment_reminder_hours',
        ];
    }

    public static function logoUrl(?array $settings = null): ?string
    {
        $settings = $settings ?? self::get();
        $logo     = $settings['logo'] ?? null;

        if (!$logo) {
            return null;
        }

        $config = require __DIR__ . '/../config/config.php';
        $path   = rtrim($config['upload']['path'], '/\\') . '/' . ltrim($logo, '/');

        if (!is_file($path)) {
            return null;
        }

        return url('actions/company-logo.php');
    }

    private static function storeLogo(array $file): string
    {
        $config = require __DIR__ . '/../config/config.php';
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'svg'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed, true)) {
            throw new RuntimeException('Logo must be JPG, PNG, WEBP, or SVG.');
        }

        if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
            throw new RuntimeException('Logo must be under 2MB.');
        }

        $dir = rtrim($config['upload']['path'], '/\\') . '/branding';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = 'logo.' . $ext;
        $fullPath = $dir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            throw new RuntimeException('Unable to upload logo.');
        }

        return 'branding/' . $filename;
    }

    private static function normalizeColor(string $color): string
    {
        $color = trim($color);
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            return strtolower($color);
        }

        return '#3aafa9';
    }
}
