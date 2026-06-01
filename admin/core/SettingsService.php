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
