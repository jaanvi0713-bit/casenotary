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
            'company_name'    => 'Your Company',
            'primary_color'   => '#3aafa9',
            'secondary_color' => '#00182c',
            'dark_accent'     => '#000000',
            'font_family'     => 'Montserrat',
            'logo'            => null,
            'favicon'         => null,
            'office_email'    => null,
            'office_phone'    => null,
            'business_hours'  => "Monday – Friday: 9:00 AM – 5:00 PM\nSaturday – Sunday: Closed",
            'address'             => null,
            'company_website'     => null,
            'registration_number' => null,
            'tax_vat_number'      => null,
            'facebook_url'        => null,
            'instagram_url'       => null,
            'linkedin_url'        => null,
            'description'         => null,
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

    public static function update(array $data, ?array $logoFile = null, ?array $faviconFile = null): void
    {
        $settings = self::get();
        $id       = (int) ($settings['id'] ?? 0);

        if ($id <= 0) {
            throw new RuntimeException('Company settings record not found. Run database seed/migration.');
        }

        $logoPath    = $settings['logo'] ?? null;
        $faviconPath = $settings['favicon'] ?? null;

        if (!empty($data['remove_logo']) && Database::columnExists('company_settings', 'logo')) {
            self::deleteBrandingFile($logoPath);
            $logoPath = null;
        } elseif (
            $logoFile
            && !empty($logoFile['name'])
            && ($logoFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
            && Database::columnExists('company_settings', 'logo')
        ) {
            $logoPath = self::storeLogo($logoFile, $logoPath);
        } elseif (
            $logoFile
            && !empty($logoFile['name'])
            && ($logoFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
            && !Database::columnExists('company_settings', 'logo')
        ) {
            self::storeLogo($logoFile);
            throw new RuntimeException(
                'Logo uploaded but the database is missing the logo column. Run: php admin/sql/migrate_branding.php'
            );
        }

        if (!empty($data['remove_favicon']) && Database::columnExists('company_settings', 'favicon')) {
            self::deleteBrandingFile($faviconPath);
            $faviconPath = null;
        } elseif (
            $faviconFile
            && !empty($faviconFile['name'])
            && ($faviconFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
            && Database::columnExists('company_settings', 'favicon')
        ) {
            $faviconPath = self::storeFavicon($faviconFile, $faviconPath);
        } elseif (
            $faviconFile
            && !empty($faviconFile['name'])
            && ($faviconFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
            && !Database::columnExists('company_settings', 'favicon')
        ) {
            self::storeFavicon($faviconFile);
            throw new RuntimeException(
                'Favicon uploaded but the database is missing the favicon column. Run: php admin/sql/migrate_branding.php'
            );
        }

        $smtpPassword = trim($data['smtp_password'] ?? '');
        if ($smtpPassword === '') {
            $smtpPassword = $settings['smtp_password'] ?? null;
        }

        $stripeSecret = trim($data['stripe_secret_key'] ?? '');
        if ($stripeSecret === '') {
            $stripeSecret = $settings['stripe_secret_key'] ?? null;
        }

        $businessHours = trim($data['business_hours'] ?? '') ?: null;

        $row = [
            'company_name'        => trim($data['company_name'] ?? '') ?: 'Your Company',
            'primary_color'       => self::normalizeColor($data['primary_color'] ?? '#3aafa9'),
            'secondary_color'     => self::normalizeColor($data['secondary_color'] ?? '#00182c'),
            'dark_accent'         => self::normalizeColor($data['dark_accent'] ?? '#000000'),
            'font_family'         => resolveCompanyFont($data['font_family'] ?? null),
            'description'         => trim($data['description'] ?? '') ?: null,
            'office_email'        => trim($data['office_email'] ?? '') ?: null,
            'office_phone'        => trim($data['office_phone'] ?? '') ?: null,
            'business_hours'      => $businessHours,
            'address'             => self::optionalString($data['address'] ?? null),
            'company_website'     => self::optionalUrl($data['company_website'] ?? null, 'Company website'),
            'registration_number' => self::optionalString($data['registration_number'] ?? null),
            'tax_vat_number'      => self::optionalString($data['tax_vat_number'] ?? null),
            'facebook_url'        => self::optionalUrl($data['facebook_url'] ?? null, 'Facebook URL'),
            'instagram_url'       => self::optionalUrl($data['instagram_url'] ?? null, 'Instagram URL'),
            'linkedin_url'        => self::optionalUrl($data['linkedin_url'] ?? null, 'LinkedIn URL'),
            'smtp_host'           => trim($data['smtp_host'] ?? '') ?: null,
            'smtp_port'           => (int) ($data['smtp_port'] ?? 587),
            'smtp_username'       => trim($data['smtp_username'] ?? '') ?: null,
            'smtp_password'       => $smtpPassword,
            'smtp_encryption'     => in_array($data['smtp_encryption'] ?? 'tls', ['tls', 'ssl', 'none'], true)
                ? $data['smtp_encryption']
                : 'tls',
            'stripe_public_key'   => trim($data['stripe_public_key'] ?? '') ?: null,
            'stripe_secret_key'   => $stripeSecret,
        ];

        if (Database::columnExists('company_settings', 'logo')) {
            $row['logo'] = $logoPath;
        }

        if (Database::columnExists('company_settings', 'favicon')) {
            $row['favicon'] = $faviconPath;
        }

        $setParts = [];
        $params   = [];

        foreach ($row as $column => $value) {
            if (Database::columnExists('company_settings', $column)) {
                $setParts[] = "{$column} = ?";
                $params[]   = $value;
            }
        }

        if ($setParts === []) {
            throw new RuntimeException('No valid company settings columns to update.');
        }

        $setParts[] = 'updated_at = NOW()';
        $params[]   = $id;

        Database::query(
            'UPDATE company_settings SET ' . implode(', ', $setParts) . ' WHERE id = ?',
            $params
        );

        self::clearCache();
    }

    public static function logoUrl(?array $settings = null): ?string
    {
        return companyLogoUrl($settings);
    }

    private static function storeLogo(array $file, ?string $previousPath = null): string
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

        $relative = self::writeBrandingFile($file, 'logo.' . $ext, $config);
        if ($previousPath !== null && $previousPath !== $relative) {
            self::deleteBrandingFile($previousPath);
        }

        return $relative;
    }

    private static function storeFavicon(array $file, ?string $previousPath = null): string
    {
        $config = require __DIR__ . '/../config/config.php';
        $allowed = ['ico', 'png'];
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed, true)) {
            throw new RuntimeException('Favicon must be PNG or ICO.');
        }

        if (($file['size'] ?? 0) > 512 * 1024) {
            throw new RuntimeException('Favicon must be under 512KB.');
        }

        $relative = self::writeBrandingFile($file, 'favicon.' . $ext, $config);
        if ($previousPath !== null && $previousPath !== $relative) {
            self::deleteBrandingFile($previousPath);
        }

        return $relative;
    }

    private static function writeBrandingFile(array $file, string $filename, array $config): string
    {
        $dir = rtrim($config['upload']['path'], '/\\') . '/branding';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fullPath = $dir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            throw new RuntimeException('Unable to upload file.');
        }

        return 'branding/' . $filename;
    }

    private static function deleteBrandingFile(?string $relativePath): void
    {
        if ($relativePath === null || trim($relativePath) === '') {
            return;
        }

        $config = require __DIR__ . '/../config/config.php';
        $full   = rtrim($config['upload']['path'], '/\\') . '/' . ltrim($relativePath, '/');

        if (is_file($full)) {
            @unlink($full);
        }
    }

    private static function normalizeColor(string $color): string
    {
        $color = trim($color);
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
            return strtolower($color);
        }

        return '#3aafa9';
    }

    private static function optionalString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }

    private static function optionalUrl(mixed $value, string $label): ?string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        if (!preg_match('#^https?://#i', $value)) {
            $value = 'https://' . $value;
        }

        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            throw new RuntimeException($label . ' is not a valid URL.');
        }

        return $value;
    }
}
