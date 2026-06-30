<?php

declare(strict_types=1);

class SettingsService
{
    private static ?array $cache = null;

    /** @var array<string, string> */
    public const BANK_FIELD_LABELS = [
        'bank_name'      => 'Bank name',
        'account_name'   => 'Account name',
        'account_number' => 'Account number',
        'sort_code'      => 'Sort code',
        'iban'           => 'IBAN',
        'bic'            => 'BIC / SWIFT',
        'reference'      => 'Reference',
    ];

    /** @var list<string> */
    private const BRANDING_FIELDS = [
        'company_name',
        'primary_color',
        'secondary_color',
        'dark_accent',
        'font_family',
        'description',
        'office_email',
        'office_phone',
        'business_hours',
        'address',
        'city',
        'state',
        'zip_code',
        'country',
        'company_website',
        'registration_number',
        'tax_vat_number',
        'bank_account_1',
        'bank_account_2',
        'bank_account_3',
        'invoice_bank_account',
        'facebook_url',
        'instagram_url',
        'linkedin_url',
    ];

    /** @var list<string> */
    private const EMAIL_FIELDS = [
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'smtp_encryption',
    ];

    /** @var list<string> */
    private const PAYMENTS_FIELDS = [
        'invoice_payable_name',
        'bank_account_number',
        'bank_sort_code',
        'bank_iban',
        'bank_bic',
        'default_invoice_payment_terms',
        'stripe_public_key',
        'stripe_secret_key',
    ];

    /** @var list<string> */
    private const BACKUP_FIELDS = [
        'backup_frequency',
        'last_backup_at',
    ];

    /** @var list<string> */
    private const RESTORE_EXCLUDED_COLUMNS = [
        'id',
        'company_id',
        'updated_at',
    ];

    public static function get(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $row = Database::fetch(
            TenantService::isEnabled()
                ? 'SELECT * FROM company_settings WHERE company_id = ? LIMIT 1'
                : 'SELECT * FROM company_settings LIMIT 1',
            TenantService::isEnabled() ? [TenantService::id()] : []
        );
        self::$cache = self::mergeRow($row);

        return self::$cache;
    }

    public static function forCompany(int $companyId): array
    {
        if ($companyId <= 0 || !TenantService::exists($companyId)) {
            return self::neutralClientBranding();
        }

        $row = Database::fetch(
            'SELECT * FROM company_settings WHERE company_id = ? LIMIT 1',
            [$companyId]
        );

        if (!$row) {
            $settings = self::defaultValues();
            $settings['company_id'] = $companyId;
            $settings['company_name'] = TenantService::name($companyId);

            return $settings;
        }

        return self::mergeRow($row);
    }

    public static function neutralClientBranding(): array
    {
        $settings = self::defaultValues();
        $settings['company_name'] = 'Client Portal';
        $settings['logo'] = null;
        $settings['favicon'] = null;

        return $settings;
    }

    public static function clearCache(): void
    {
        self::$cache = null;
    }

    /** @return array{version: string, exported_at: string, company_id: int|null, settings: array<string, mixed>} */
    public static function exportBackup(): array
    {
        return self::exportBackupForCompany(TenantService::id());
    }

    /** @return array{version: string, exported_at: string, company_id: int|null, settings: array<string, mixed>} */
    public static function exportBackupForCompany(int $companyId): array
    {
        return BackupService::exportForCompany($companyId);
    }

    /** @param array<string, mixed> $data */
    public static function restoreBackup(array $data): void
    {
        $current = self::get();
        $id      = (int) ($current['id'] ?? 0);

        if ($id <= 0) {
            throw new RuntimeException('Company settings record not found.');
        }

        $incoming = $data['settings'] ?? [];
        if (!is_array($incoming)) {
            throw new RuntimeException('Backup file is missing settings data.');
        }

        $setParts = [];
        $params   = [];

        foreach ($incoming as $key => $value) {
            if (!is_string($key) || in_array($key, self::RESTORE_EXCLUDED_COLUMNS, true)) {
                continue;
            }
            if (!Database::columnExists('company_settings', $key)) {
                continue;
            }
            $setParts[] = "`{$key}` = ?";
            $params[]   = $value;
        }

        if ($setParts === []) {
            throw new RuntimeException('No valid settings found in backup file.');
        }

        $params[] = $id;
        Database::query(
            'UPDATE company_settings SET ' . implode(', ', $setParts) . ' WHERE id = ?',
            $params
        );
        self::clearCache();
    }

    public static function saveSetting(string $key, mixed $value, ?int $companyId = null): void
    {
        if (!Database::columnExists('company_settings', $key)) {
            return;
        }

        $companyId = $companyId ?? TenantService::id();
        $row       = TenantService::isEnabled()
            ? Database::fetch('SELECT id FROM company_settings WHERE company_id = ? LIMIT 1', [$companyId])
            : Database::fetch('SELECT id FROM company_settings LIMIT 1');

        if (!$row) {
            throw new RuntimeException('Company settings record not found.');
        }

        Database::query(
            "UPDATE company_settings SET `{$key}` = ? WHERE id = ?",
            [$value, (int) $row['id']]
        );
        self::clearCache();
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private static function exportableSettings(array $row): array
    {
        $settings = [];
        foreach ($row as $column => $value) {
            if (in_array($column, self::RESTORE_EXCLUDED_COLUMNS, true)) {
                continue;
            }
            $settings[$column] = $value;
        }

        return $settings;
    }

    public static function update(array $data, ?array $logoFile = null, ?array $faviconFile = null, string $tab = 'branding'): void
    {
        $settings = self::get();
        $id       = (int) ($settings['id'] ?? 0);

        if ($id <= 0) {
            throw new RuntimeException('Company settings record not found. Run database seed/migration.');
        }

        $logoPath    = $settings['logo'] ?? null;
        $faviconPath = $settings['favicon'] ?? null;
        $isBranding  = $tab === 'branding';

        if ($isBranding && !empty($data['remove_logo']) && Database::columnExists('company_settings', 'logo')) {
            self::deleteBrandingFile($logoPath);
            $logoPath = null;
        } elseif (
            $isBranding
            && $logoFile
            && !empty($logoFile['name'])
            && ($logoFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
            && Database::columnExists('company_settings', 'logo')
        ) {
            $logoPath = self::storeLogo($logoFile, $logoPath);
        } elseif (
            $isBranding
            && $logoFile
            && !empty($logoFile['name'])
            && ($logoFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
            && !Database::columnExists('company_settings', 'logo')
        ) {
            self::storeLogo($logoFile);
            throw new RuntimeException(
                'Logo uploaded but the database is missing the logo column. Run: php admin/sql/migrate_branding.php'
            );
        }

        if ($isBranding && !empty($data['remove_favicon']) && Database::columnExists('company_settings', 'favicon')) {
            self::deleteBrandingFile($faviconPath);
            $faviconPath = null;
        } elseif (
            $isBranding
            && $faviconFile
            && !empty($faviconFile['name'])
            && ($faviconFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
            && Database::columnExists('company_settings', 'favicon')
        ) {
            $faviconPath = self::storeFavicon($faviconFile, $faviconPath);
        } elseif (
            $isBranding
            && $faviconFile
            && !empty($faviconFile['name'])
            && ($faviconFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
            && !Database::columnExists('company_settings', 'favicon')
        ) {
            self::storeFavicon($faviconFile);
            throw new RuntimeException(
                'Favicon uploaded but the database is missing the favicon column. Run: php admin/sql/migrate_branding.php'
            );
        }

        $smtpPassword = trim($data['new_smtp_password'] ?? $data['smtp_password'] ?? '');
        if ($smtpPassword !== '') {
            $smtpConfirm = trim($data['new_smtp_password_confirmation'] ?? '');
            if ($smtpPassword !== $smtpConfirm) {
                throw new RuntimeException('SMTP password confirmation does not match.');
            }
        } else {
            $smtpPassword = $settings['smtp_password'] ?? null;
        }

        $stripeSecret = trim($data['stripe_secret_key'] ?? '');
        if ($stripeSecret === '') {
            $stripeSecret = $settings['stripe_secret_key'] ?? null;
        }

        $businessHours = trim($data['business_hours'] ?? '') ?: null;

        if ($tab === 'branding' && isset($data['bank_accounts']) && is_array($data['bank_accounts'])) {
            foreach ([1, 2, 3] as $slot) {
                if (!isset($data['bank_accounts'][$slot]) || !is_array($data['bank_accounts'][$slot])) {
                    continue;
                }
                $data['bank_account_' . $slot] = self::formatBankAccountText(
                    self::normalizeBankAccountFields($data['bank_accounts'][$slot])
                );
            }
        }

        $incoming = [
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
            'city'                => self::optionalString($data['city'] ?? null),
            'state'               => self::optionalString($data['state'] ?? null),
            'zip_code'            => self::optionalString($data['zip_code'] ?? null),
            'country'             => self::optionalString($data['country'] ?? null),
            'company_website'     => self::optionalUrl($data['company_website'] ?? null, 'Company website'),
            'registration_number' => self::optionalString($data['registration_number'] ?? null),
            'tax_vat_number'      => self::optionalString($data['tax_vat_number'] ?? null),
            'bank_account_1'      => self::optionalString($data['bank_account_1'] ?? null),
            'bank_account_2'      => self::optionalString($data['bank_account_2'] ?? null),
            'bank_account_3'      => self::optionalString($data['bank_account_3'] ?? null),
            'invoice_bank_account' => self::normalizeBankAccountChoice($data['invoice_bank_account'] ?? null),
            'invoice_payable_name' => self::optionalString($data['invoice_payable_name'] ?? null),
            'bank_account_number' => self::optionalString($data['bank_account_number'] ?? null),
            'bank_sort_code'      => self::optionalString($data['bank_sort_code'] ?? null),
            'bank_iban'           => self::optionalString($data['bank_iban'] ?? null),
            'bank_bic'            => self::optionalString($data['bank_bic'] ?? null),
            'default_invoice_payment_terms' => self::optionalString($data['default_invoice_payment_terms'] ?? null),
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

        $row = self::mergeTabUpdate($tab, $incoming, $settings);

        if ($isBranding && Database::columnExists('company_settings', 'logo')) {
            $row['logo'] = $logoPath;
        }

        if ($isBranding && Database::columnExists('company_settings', 'favicon')) {
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

        if ($isBranding && TenantService::isEnabled()) {
            $companyId = (int) ($settings['company_id'] ?? TenantService::id());
            $displayName = (string) ($row['company_name'] ?? '');

            CompanyService::syncDisplayName($companyId, $displayName);

            $slugInput = trim((string) ($data['company_slug'] ?? ''));
            if ($slugInput === '') {
                $slugInput = $displayName;
            }

            CompanyService::updateSlug($companyId, $slugInput);
        }

        self::clearCache();
    }

    public static function logoUrl(?array $settings = null): ?string
    {
        return companyLogoUrl($settings);
    }

    /**
     * @return array<string, string>
     */
    public static function emptyBankAccountFields(): array
    {
        $fields = [];
        foreach (array_keys(self::BANK_FIELD_LABELS) as $key) {
            $fields[$key] = '';
        }

        return $fields;
    }

    /**
     * @return array<string, string>
     */
    public static function parseBankAccountText(string $text): array
    {
        $fields  = self::emptyBankAccountFields();
        $text    = trim($text);
        if ($text === '') {
            return $fields;
        }

        $aliases = [
            'bank'            => 'bank_name',
            'bank name'       => 'bank_name',
            'account name'    => 'account_name',
            'payable to'      => 'account_name',
            'beneficiary'     => 'account_name',
            'account number'  => 'account_number',
            'account no'      => 'account_number',
            'acc number'      => 'account_number',
            'acc num'         => 'account_number',
            'acc no'          => 'account_number',
            'a/c no'          => 'account_number',
            'a/c number'      => 'account_number',
            'sort code'       => 'sort_code',
            'iban'            => 'iban',
            'bic'             => 'bic',
            'bic / swift'     => 'bic',
            'swift'           => 'bic',
            'reference'       => 'reference',
            'payment reference' => 'reference',
        ];

        foreach (self::BANK_FIELD_LABELS as $key => $label) {
            $aliases[strtolower($label)] = $key;
        }

        $matched = false;
        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            if (!str_contains($line, ':')) {
                if ($fields['account_name'] === '' && !preg_match('/^\d[\d\s\-]+$/', $line)) {
                    $fields['account_name'] = $line;
                    $matched = true;
                }
                continue;
            }

            [$label, $value] = array_map('trim', explode(':', $line, 2));
            $key = $aliases[strtolower($label)] ?? null;
            if ($key !== null && $value !== '') {
                $fields[$key] = $value;
                $matched = true;
            }
        }

        if (!$matched) {
            $fields['account_name'] = $text;
        }

        return $fields;
    }

    /**
     * @param array<string, mixed> $fields
     */
    public static function formatBankAccountText(array $fields): string
    {
        $fields = self::normalizeBankAccountFields($fields);
        $lines  = [];

        foreach (self::BANK_FIELD_LABELS as $key => $label) {
            $value = trim((string) ($fields[$key] ?? ''));
            if ($value !== '') {
                $lines[] = "{$label}: {$value}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, string>
     */
    public static function normalizeBankAccountFields(array $fields): array
    {
        $normalized = self::emptyBankAccountFields();
        foreach (array_keys(self::BANK_FIELD_LABELS) as $key) {
            $normalized[$key] = trim((string) ($fields[$key] ?? ''));
        }

        return $normalized;
    }

    /**
     * @return array<string, string>
     */
    public static function bankAccountFieldsForSlot(?array $settings, int $slot): array
    {
        $settings ??= self::get();
        $slot     = self::normalizeBankAccountChoice($slot);

        return self::parseBankAccountText((string) ($settings['bank_account_' . $slot] ?? ''));
    }

    public static function bankAccountTemplateExample(): string
    {
        return self::formatBankAccountText([
            'bank_name'      => 'Barclays Bank',
            'account_name'   => 'YOUR COMPANY LTD',
            'account_number' => '12345678',
            'sort_code'      => '20-00-00',
            'iban'           => 'GB00 BARC 2000 0012 3456 78',
            'bic'            => 'BARCGB22',
            'reference'      => 'Please quote invoice number',
        ]);
    }

    public static function bankAccountDisplayHtml(string $text, bool $withIcons = true): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $fields = self::parseBankAccountText($text);
        if (self::formatBankAccountText($fields) !== '') {
            $rows = '';
            foreach (self::BANK_FIELD_LABELS as $key => $label) {
                $value = trim((string) ($fields[$key] ?? ''));
                if ($value === '') {
                    continue;
                }
                $icon = $withIcons
                    ? '<span class="bank-detail-row__icon" aria-hidden="true"><i class="bi ' . e(self::bankFieldIcon($key)) . '"></i></span>'
                    : '';
                $rows .= '<div class="bank-detail-row fdoc-bank-line" data-field="' . e($key) . '">'
                    . $icon
                    . '<div class="bank-detail-row__content">'
                    . '<span class="bank-detail-row__label fdoc-bank-label">' . e($label) . '</span>'
                    . '<span class="bank-detail-row__value' . ($key === 'account_number' ? ' bank-detail-row__value--account' : '') . '">'
                    . e(self::formatBankFieldDisplayValue($key, $value))
                    . '</span>'
                    . '</div></div>';
            }

            return $rows === '' ? '' : '<div class="bank-details-panel' . ($withIcons ? '' : ' bank-details-panel--document') . '">' . $rows . '</div>';
        }

        return '<div class="bank-details-panel bank-details-panel--plain' . ($withIcons ? '' : ' bank-details-panel--document') . '">' . nl2br(e($text)) . '</div>';
    }

    /** Compact line markup for invoice / receipt PDFs (Payable To block). */
    public static function bankAccountDocumentHtml(string $text): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $fields = self::parseBankAccountText($text);
        if (self::formatBankAccountText($fields) === '') {
            return '<div class="fdoc-bank-instructions">' . nl2br(e($text)) . '</div>';
        }

        $lines = '';
        foreach (self::BANK_FIELD_LABELS as $key => $label) {
            $value = trim((string) ($fields[$key] ?? ''));
            if ($value === '') {
                continue;
            }
            $lines .= '<p class="fdoc-bank-line">'
                . e(self::bankDocumentFieldLabel($key))
                . ' '
                . e($value)
                . '</p>';
        }

        return $lines;
    }

    public static function bankDocumentFieldLabel(string $key): string
    {
        return match ($key) {
            'account_number' => 'Account number:',
            'sort_code'      => 'Sort code:',
            'iban'           => 'IBAN:',
            'bic'            => 'BIC:',
            'bank_name'      => 'Bank name:',
            'account_name'   => 'Account name:',
            'reference'      => 'Reference:',
            default          => (self::BANK_FIELD_LABELS[$key] ?? ucfirst($key)) . ':',
        };
    }

    public static function formatBankFieldDisplayValue(string $key, string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if ($key === 'account_number') {
            $digits = preg_replace('/\D+/', '', $value) ?? '';
            if ($digits !== '' && strlen($digits) >= 6) {
                return trim(chunk_split($digits, 4, ' '));
            }
        }

        return $value;
    }

    public static function bankFieldIcon(string $key): string
    {
        return match ($key) {
            'bank_name'      => 'bi-bank2',
            'account_name'   => 'bi-building',
            'account_number' => 'bi-credit-card-2-front',
            'sort_code'      => 'bi-hash',
            'iban'           => 'bi-globe2',
            'bic'            => 'bi-shield-check',
            'reference'      => 'bi-bookmark',
            default          => 'bi-dot',
        };
    }

    public static function bankAccountHasDetails(?array $settings, int $slot): bool
    {
        $settings ??= self::get();
        $slot     = self::normalizeBankAccountChoice($slot);

        return trim((string) ($settings['bank_account_' . $slot] ?? '')) !== '';
    }

    /**
     * @return array{1:string, 2:string, 3:string}
     */
    public static function bankAccounts(?array $settings = null): array
    {
        $settings ??= self::get();

        return [
            1 => trim((string) ($settings['bank_account_1'] ?? '')),
            2 => trim((string) ($settings['bank_account_2'] ?? '')),
            3 => trim((string) ($settings['bank_account_3'] ?? '')),
        ];
    }

    public static function defaultBankAccountChoice(?array $settings = null): int
    {
        $settings ??= self::get();

        return self::normalizeBankAccountChoice($settings['invoice_bank_account'] ?? 1);
    }

    public static function resolveBankAccountText(?array $settings = null, ?int $choice = null): string
    {
        $settings ??= self::get();
        $accounts = self::bankAccounts($settings);
        $choice   = self::normalizeBankAccountChoice($choice ?? self::defaultBankAccountChoice($settings));

        $text = $accounts[$choice] ?? '';
        if ($text !== '') {
            return $text;
        }

        foreach ([$choice, 1, 2, 3] as $candidate) {
            $candidate = self::normalizeBankAccountChoice($candidate);
            if (($accounts[$candidate] ?? '') !== '') {
                return $accounts[$candidate];
            }
        }

        return self::legacyBankAccountText($settings);
    }

    public static function bankAccountLabel(string $text, int $number): string
    {
        $fields = self::parseBankAccountText($text);
        $name   = trim((string) ($fields['account_name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($fields['bank_name'] ?? ''));
        }
        if ($name === '') {
            $name = trim(strtok($text, "\n") ?: '');
        }
        if ($name === '') {
            return 'Bank account ' . $number;
        }

        return strlen($name) > 48 ? substr($name, 0, 45) . '…' : $name;
    }

    /**
     * @param array<string, mixed> $invoice
     * @param array<string, mixed>|null $settings
     */
    public static function resolveInvoiceBankHtml(array $invoice, ?array $settings = null): string
    {
        $settings ??= self::get();
        $custom   = trim((string) ($invoice['payment_instructions'] ?? ''));
        if ($custom !== '') {
            return '<div class="fdoc-bank-instructions">' . nl2br(e($custom)) . '</div>';
        }

        $choice = (int) ($invoice['bank_account'] ?? 0);
        if ($choice < 1 || $choice > 3) {
            $choice = self::defaultBankAccountChoice($settings);
        }

        $text = self::resolveBankAccountText($settings, $choice);

        return self::bankAccountDocumentHtml($text);
    }

    /**
     * @param array<string, mixed> $invoice
     * @param array<string, mixed>|null $settings
     */
    public static function resolveInvoiceBankText(array $invoice, ?array $settings = null): string
    {
        $settings ??= self::get();
        $custom   = trim((string) ($invoice['payment_instructions'] ?? ''));
        if ($custom !== '') {
            return $custom;
        }

        $choice = (int) ($invoice['bank_account'] ?? 0);
        if ($choice < 1 || $choice > 3) {
            $choice = self::defaultBankAccountChoice($settings);
        }

        return self::resolveBankAccountText($settings, $choice);
    }

    public static function normalizeBankAccountChoice(mixed $value): int
    {
        $choice = (int) ($value ?? 1);

        return max(1, min(3, $choice > 0 ? $choice : 1));
    }

    /** @param array<string, mixed> $settings */
    private static function legacyBankAccountText(array $settings): string
    {
        $lines = [];
        foreach (
            [
                'bank_account_number' => 'Account number',
                'bank_sort_code'      => 'Sort code',
                'bank_iban'           => 'IBAN',
                'bank_bic'            => 'BIC',
            ] as $column => $label
        ) {
            $value = trim((string) ($settings[$column] ?? ''));
            if ($value !== '') {
                $lines[] = "{$label}: {$value}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $incoming
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    private static function mergeTabUpdate(string $tab, array $incoming, array $settings): array
    {
        $editable = match ($tab) {
            'email'    => self::EMAIL_FIELDS,
            'payments' => self::PAYMENTS_FIELDS,
            'backup'   => self::BACKUP_FIELDS,
            default    => self::BRANDING_FIELDS,
        };

        $row = [];

        foreach ($incoming as $column => $value) {
            if (in_array($column, $editable, true)) {
                $row[$column] = $value;
            } else {
                $row[$column] = $settings[$column] ?? $value;
            }
        }

        return $row;
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
        $dir = rtrim($config['upload']['path'], '/\\') . '/company_' . TenantService::id() . '/branding';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fullPath = $dir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            throw new RuntimeException('Unable to upload file.');
        }

        return 'company_' . TenantService::id() . '/branding/' . $filename;
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

    /** @return array<string, mixed> */
    private static function defaultValues(): array
    {
        return [
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
            'city'                => null,
            'state'               => null,
            'zip_code'            => null,
            'country'             => null,
            'company_website'     => null,
            'registration_number' => null,
            'tax_vat_number'      => null,
            'bank_account_1'      => null,
            'bank_account_2'      => null,
            'bank_account_3'      => null,
            'invoice_bank_account' => 1,
            'invoice_payable_name' => null,
            'bank_account_number' => null,
            'bank_sort_code'      => null,
            'bank_iban'           => null,
            'bank_bic'            => null,
            'default_invoice_payment_terms' => null,
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
            'backup_frequency'  => 'never',
            'last_backup_at'    => null,
        ];
    }

    /** @param array<string, mixed>|null $row */
    private static function mergeRow(?array $row): array
    {
        return $row ? array_merge(self::defaultValues(), $row) : self::defaultValues();
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
