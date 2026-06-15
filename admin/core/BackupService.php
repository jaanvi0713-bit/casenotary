<?php

declare(strict_types=1);

class BackupService
{
    public const RETENTION_DAYS = 7;
    public const VERSION        = '2.0';

    /**
     * @return array{json: string, file: string, emailed: int}
     */
    public static function create(int $companyId, string $trigger, ?string $extraRecipient = null): array
    {
        $backup = self::exportForCompany($companyId);
        $json   = json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Could not encode backup as JSON.');
        }

        $file = self::writeFile($companyId, $json);
        self::rotateOldFiles($companyId);

        $recipients = self::recipients($companyId, $extraRecipient);
        $emailed    = self::emailBackup($companyId, $file, $trigger, $recipients);

        return [
            'json'    => $json,
            'file'    => $file,
            'emailed' => $emailed,
        ];
    }

    /** @return array<string, mixed> */
    public static function exportForCompany(int $companyId): array
    {
        $settings = $companyId > 0 ? SettingsService::forCompany($companyId) : SettingsService::get();

        return [
            'version'     => self::VERSION,
            'exported_at' => date('c'),
            'company_id'  => $companyId > 0 ? $companyId : null,
            'scope'       => 'website_database_export',
            'settings'    => self::exportableSettings($settings),
            'data'        => self::exportBusinessData($companyId),
        ];
    }

    public static function backupDir(int $companyId): string
    {
        $dir = __DIR__ . '/../storage/backups/company_' . $companyId;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir;
    }

    public static function writeFile(int $companyId, string $json): string
    {
        $file = self::backupDir($companyId) . '/backup-' . date('Y-m-d-His') . '.json';
        file_put_contents($file, $json);

        return $file;
    }

    public static function rotateOldFiles(int $companyId): void
    {
        $dir   = self::backupDir($companyId);
        $cutoff = time() - (self::RETENTION_DAYS * 86400);
        $files = glob($dir . '/backup-*.json') ?: [];

        foreach ($files as $path) {
            if (is_file($path) && filemtime($path) < $cutoff) {
                @unlink($path);
            }
        }
    }

    /**
     * @return list<string>
     */
    public static function recipients(int $companyId, ?string $extraRecipient = null): array
    {
        $settings = SettingsService::forCompany($companyId);
        $emails   = [];

        if (!empty($settings['office_email'])) {
            $emails[] = trim((string) $settings['office_email']);
        }

        if (Database::columnExists('users', 'company_id')) {
            $roles = Database::columnExists('users', 'role')
                ? "role IN ('admin', 'super_admin')"
                : "role = 'admin'";
            $rows = Database::fetchAll(
                "SELECT email FROM users WHERE company_id = ? AND status = 'active' AND {$roles} AND email <> ''",
                [$companyId]
            );
            foreach ($rows as $row) {
                $emails[] = trim((string) ($row['email'] ?? ''));
            }
        } else {
            $rows = Database::fetchAll(
                "SELECT email FROM users WHERE status = 'active' AND role IN ('admin', 'super_admin') AND email <> ''"
            );
            foreach ($rows as $row) {
                $emails[] = trim((string) ($row['email'] ?? ''));
            }
        }

        if ($extraRecipient !== null && trim($extraRecipient) !== '') {
            $emails[] = trim($extraRecipient);
        }

        return array_values(array_unique(array_filter($emails, static fn(string $e): bool => filter_var($e, FILTER_VALIDATE_EMAIL) !== false)));
    }

    /**
     * @param list<string> $recipients
     */
    public static function emailBackup(int $companyId, string $filePath, string $trigger, array $recipients): int
    {
        if ($recipients === [] || !is_file($filePath)) {
            return 0;
        }

        $settings    = SettingsService::forCompany($companyId);
        $companyName = companyBrandName($settings);
        $triggerLabel = match ($trigger) {
            'manual'  => 'Manual download',
            'weekly'  => 'Weekly schedule',
            'monthly' => 'Monthly schedule',
            default   => ucfirst($trigger),
        };

        $sent = 0;
        foreach ($recipients as $to) {
            if (MailService::sendSystemBackupEmail($to, $companyName, $filePath, $triggerLabel)) {
                $sent++;
            }
        }

        return $sent;
    }

    /** @param array<string, mixed> $row */
    private static function exportableSettings(array $row): array
    {
        $exclude = ['id', 'company_id', 'updated_at'];
        $out     = [];
        foreach ($row as $column => $value) {
            if (!in_array($column, $exclude, true)) {
                $out[$column] = $value;
            }
        }

        return $out;
    }

    /** @return array<string, list<array<string, mixed>>> */
    private static function exportBusinessData(int $companyId): array
    {
        return [
            'clients'             => self::exportClients($companyId),
            'client_users'        => self::exportClientUsers($companyId),
            'staff_users'         => self::exportStaffUsers($companyId),
            'cases'               => self::exportCases($companyId),
            'invoices'            => self::exportInvoices($companyId),
            'payments'            => self::exportPayments($companyId),
            'receipts'            => self::exportReceipts($companyId),
            'appointments'        => self::exportAppointments($companyId),
            'documents'           => self::exportDocuments($companyId),
            'case_client_letters' => self::exportCaseClientLetters($companyId),
            'proposals'           => self::exportProposals($companyId),
            'quotations'          => self::exportQuotations($companyId),
        ];
    }

  /** @return list<array<string, mixed>> */
    private static function exportClients(int $companyId): array
    {
        if ($companyId > 0 && Database::columnExists('clients', 'company_id')) {
            return Database::fetchAll('SELECT * FROM clients WHERE company_id = ?', [$companyId]);
        }

        return Database::fetchAll('SELECT * FROM clients');
    }

    /** @return list<array<string, mixed>> */
    private static function exportClientUsers(int $companyId): array
    {
        $select = self::userExportColumns('u');

        if ($companyId > 0 && Database::columnExists('clients', 'company_id')) {
            return Database::fetchAll(
                "SELECT {$select}
                 FROM users u
                 INNER JOIN clients c ON c.user_id = u.id
                 WHERE c.company_id = ?",
                [$companyId]
            );
        }

        return Database::fetchAll(
            "SELECT {$select}
             FROM users u
             INNER JOIN clients c ON c.user_id = u.id"
        );
    }

    /** @return list<array<string, mixed>> */
    private static function exportStaffUsers(int $companyId): array
    {
        $select = self::userExportColumns();
        $roleFilter = Database::columnExists('users', 'role')
            ? "role IN ('admin', 'super_admin')"
            : "role = 'admin'";

        if ($companyId > 0 && Database::columnExists('users', 'company_id')) {
            return Database::fetchAll(
                "SELECT {$select} FROM users WHERE company_id = ? AND {$roleFilter}",
                [$companyId]
            );
        }

        return Database::fetchAll("SELECT {$select} FROM users WHERE {$roleFilter}");
    }

    private static function userExportColumns(string $alias = ''): string
    {
        $prefix = $alias !== '' ? $alias . '.' : '';
        $cols   = ['id', 'email', 'first_name', 'last_name', 'status', 'role', 'created_at', 'updated_at', 'last_login'];
        if (Database::columnExists('users', 'phone')) {
            $cols[] = 'phone';
        }

        return implode(', ', array_map(static fn(string $c): string => $prefix . $c, $cols));
    }

    /** @return list<array<string, mixed>> */
    private static function exportCases(int $companyId): array
    {
        if ($companyId > 0 && Database::columnExists('cases', 'company_id')) {
            return Database::fetchAll('SELECT * FROM cases WHERE company_id = ?', [$companyId]);
        }

        if ($companyId > 0 && Database::columnExists('clients', 'company_id')) {
            return Database::fetchAll(
                'SELECT cs.* FROM cases cs INNER JOIN clients cl ON cl.id = cs.client_id WHERE cl.company_id = ?',
                [$companyId]
            );
        }

        return Database::fetchAll('SELECT * FROM cases');
    }

    /** @return list<array<string, mixed>> */
    private static function exportInvoices(int $companyId): array
    {
        if ($companyId > 0 && Database::columnExists('clients', 'company_id')) {
            return Database::fetchAll(
                'SELECT i.* FROM invoices i INNER JOIN clients cl ON cl.id = i.client_id WHERE cl.company_id = ?',
                [$companyId]
            );
        }

        return Database::fetchAll('SELECT * FROM invoices');
    }

    /** @return list<array<string, mixed>> */
    private static function exportPayments(int $companyId): array
    {
        if ($companyId > 0 && Database::columnExists('clients', 'company_id')) {
            return Database::fetchAll(
                'SELECT p.* FROM payments p
                 INNER JOIN invoices i ON i.id = p.invoice_id
                 INNER JOIN clients cl ON cl.id = i.client_id
                 WHERE cl.company_id = ?',
                [$companyId]
            );
        }

        return Database::fetchAll('SELECT p.* FROM payments p');
    }

    /** @return list<array<string, mixed>> */
    private static function exportReceipts(int $companyId): array
    {
        if ($companyId > 0 && Database::columnExists('clients', 'company_id')) {
            if (Database::columnExists('receipts', 'client_id')) {
                return Database::fetchAll(
                    'SELECT r.* FROM receipts r INNER JOIN clients cl ON cl.id = r.client_id WHERE cl.company_id = ?',
                    [$companyId]
                );
            }

            if (Database::columnExists('receipts', 'invoice_id')) {
                return Database::fetchAll(
                    'SELECT r.* FROM receipts r
                     INNER JOIN invoices i ON i.id = r.invoice_id
                     INNER JOIN clients cl ON cl.id = i.client_id
                     WHERE cl.company_id = ?',
                    [$companyId]
                );
            }

            return Database::fetchAll(
                'SELECT r.* FROM receipts r
                 INNER JOIN payments p ON p.id = r.payment_id
                 INNER JOIN invoices i ON i.id = p.invoice_id
                 INNER JOIN clients cl ON cl.id = i.client_id
                 WHERE cl.company_id = ?',
                [$companyId]
            );
        }

        return Database::fetchAll('SELECT * FROM receipts');
    }

    /** @return list<array<string, mixed>> */
    private static function exportAppointments(int $companyId): array
    {
        if ($companyId > 0 && Database::columnExists('clients', 'company_id')) {
            return Database::fetchAll(
                'SELECT a.* FROM appointments a INNER JOIN clients cl ON cl.id = a.client_id WHERE cl.company_id = ?',
                [$companyId]
            );
        }

        return Database::fetchAll('SELECT * FROM appointments');
    }

    /** @return list<array<string, mixed>> */
    private static function exportDocuments(int $companyId): array
    {
        if ($companyId > 0 && Database::columnExists('cases', 'company_id')) {
            return Database::fetchAll(
                'SELECT d.* FROM documents d
                 INNER JOIN cases cs ON cs.id = d.case_id
                 WHERE cs.company_id = ?',
                [$companyId]
            );
        }

        if ($companyId > 0 && Database::columnExists('clients', 'company_id')) {
            return Database::fetchAll(
                'SELECT d.* FROM documents d
                 INNER JOIN cases cs ON cs.id = d.case_id
                 INNER JOIN clients cl ON cl.id = cs.client_id
                 WHERE cl.company_id = ?',
                [$companyId]
            );
        }

        return Database::fetchAll('SELECT * FROM documents');
    }

    /** @return list<array<string, mixed>> */
    private static function exportCaseClientLetters(int $companyId): array
    {
        if ($companyId > 0 && Database::columnExists('clients', 'company_id')) {
            return Database::fetchAll(
                'SELECT l.* FROM case_client_letters l INNER JOIN clients cl ON cl.id = l.client_id WHERE cl.company_id = ?',
                [$companyId]
            );
        }

        return Database::tableExists('case_client_letters')
            ? Database::fetchAll('SELECT * FROM case_client_letters')
            : [];
    }

    /** @return list<array<string, mixed>> */
    private static function exportProposals(int $companyId): array
    {
        if (!Database::tableExists('proposals')) {
            return [];
        }

        if ($companyId > 0 && Database::columnExists('cases', 'company_id')) {
            return Database::fetchAll(
                'SELECT p.* FROM proposals p INNER JOIN cases cs ON cs.id = p.case_id WHERE cs.company_id = ?',
                [$companyId]
            );
        }

        if ($companyId > 0 && Database::columnExists('clients', 'company_id')) {
            return Database::fetchAll(
                'SELECT p.* FROM proposals p
                 INNER JOIN cases cs ON cs.id = p.case_id
                 INNER JOIN clients cl ON cl.id = cs.client_id
                 WHERE cl.company_id = ?',
                [$companyId]
            );
        }

        return Database::fetchAll('SELECT * FROM proposals');
    }

    /** @return list<array<string, mixed>> */
    private static function exportQuotations(int $companyId): array
    {
        if (!Database::tableExists('quotations')) {
            return [];
        }

        if ($companyId > 0 && Database::columnExists('cases', 'company_id')) {
            return Database::fetchAll(
                'SELECT q.* FROM quotations q INNER JOIN cases cs ON cs.id = q.case_id WHERE cs.company_id = ?',
                [$companyId]
            );
        }

        if ($companyId > 0 && Database::columnExists('clients', 'company_id')) {
            return Database::fetchAll(
                'SELECT q.* FROM quotations q
                 INNER JOIN cases cs ON cs.id = q.case_id
                 INNER JOIN clients cl ON cl.id = cs.client_id
                 WHERE cl.company_id = ?',
                [$companyId]
            );
        }

        return Database::fetchAll('SELECT * FROM quotations');
    }

    /**
     * @return array{json: string, file: string, emailed: bool}
     */
    public static function createClientBackup(int $clientId): array
    {
        if ($clientId <= 0) {
            throw new RuntimeException('Client profile not found.');
        }

        $client = self::clientRecord($clientId);
        $email  = trim((string) ($client['email'] ?? ''));
        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Your account does not have a valid email address on file.');
        }

        $backup = self::exportForClient($clientId);
        $json   = json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('Could not encode your data backup.');
        }

        $file = self::writeClientFile($clientId, $json);
        self::rotateOldClientFiles($clientId);

        $companyId   = (int) ($client['company_id'] ?? 0);
        $settings    = $companyId > 0 ? SettingsService::forCompany($companyId) : getCompanySettings();
        $companyName = companyBrandName($settings);
        $emailed     = MailService::sendClientDataBackupEmail($client, $companyName, $file);

        return [
            'json'    => $json,
            'file'    => $file,
            'emailed' => $emailed,
        ];
    }

    /** @return array<string, mixed> */
    public static function exportForClient(int $clientId): array
    {
        $client = self::clientRecord($clientId);

        return [
            'version'     => '1.0-client',
            'exported_at' => date('c'),
            'client_id'   => $clientId,
            'scope'       => 'client_portal_data',
            'profile'     => self::clientProfileExport($client),
            'data'        => self::exportClientData($clientId),
        ];
    }

    public static function clientBackupDir(int $clientId): string
    {
        $dir = __DIR__ . '/../storage/backups/clients/client_' . $clientId;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir;
    }

    public static function writeClientFile(int $clientId, string $json): string
    {
        $file = self::clientBackupDir($clientId) . '/client-backup-' . date('Y-m-d-His') . '.json';
        file_put_contents($file, $json);

        return $file;
    }

    public static function rotateOldClientFiles(int $clientId): void
    {
        $dir    = self::clientBackupDir($clientId);
        $cutoff = time() - (self::RETENTION_DAYS * 86400);
        $files  = glob($dir . '/client-backup-*.json') ?: [];

        foreach ($files as $path) {
            if (is_file($path) && filemtime($path) < $cutoff) {
                @unlink($path);
            }
        }
    }

    /** @return array<string, mixed> */
    private static function clientRecord(int $clientId): array
    {
        $userPhone = Database::columnExists('users', 'phone') ? ', u.phone AS user_phone' : '';
        $row = Database::fetch(
            "SELECT c.*, u.email, u.first_name, u.last_name{$userPhone}, u.status AS user_status
             FROM clients c
             LEFT JOIN users u ON u.id = c.user_id
             WHERE c.id = ?
             LIMIT 1",
            [$clientId]
        );

        if (!$row) {
            throw new RuntimeException('Client profile not found.');
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $client
     *
     * @return array<string, mixed>
     */
    private static function clientProfileExport(array $client): array
    {
        $profile = $client;
        unset($profile['password']);

        return $profile;
    }

    /** @return array<string, list<array<string, mixed>>> */
    private static function exportClientData(int $clientId): array
    {
        return [
            'cases'               => Database::fetchAll('SELECT * FROM cases WHERE client_id = ?', [$clientId]),
            'invoices'            => Database::fetchAll('SELECT * FROM invoices WHERE client_id = ?', [$clientId]),
            'payments'            => Database::fetchAll(
                'SELECT p.* FROM payments p
                 INNER JOIN invoices i ON i.id = p.invoice_id
                 WHERE i.client_id = ?',
                [$clientId]
            ),
            'receipts'            => self::exportClientReceipts($clientId),
            'appointments'        => Database::fetchAll('SELECT * FROM appointments WHERE client_id = ?', [$clientId]),
            'documents'           => Database::fetchAll(
                'SELECT d.* FROM documents d
                 INNER JOIN cases cs ON cs.id = d.case_id
                 WHERE cs.client_id = ?',
                [$clientId]
            ),
            'case_client_letters' => Database::tableExists('case_client_letters')
                ? Database::fetchAll('SELECT * FROM case_client_letters WHERE client_id = ?', [$clientId])
                : [],
            'proposals'           => Database::tableExists('proposals')
                ? Database::fetchAll(
                    'SELECT p.* FROM proposals p INNER JOIN cases cs ON cs.id = p.case_id WHERE cs.client_id = ?',
                    [$clientId]
                )
                : [],
            'quotations'          => Database::tableExists('quotations')
                ? Database::fetchAll(
                    'SELECT q.* FROM quotations q INNER JOIN cases cs ON cs.id = q.case_id WHERE cs.client_id = ?',
                    [$clientId]
                )
                : [],
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function exportClientReceipts(int $clientId): array
    {
        if (Database::columnExists('receipts', 'client_id')) {
            return Database::fetchAll('SELECT * FROM receipts WHERE client_id = ?', [$clientId]);
        }

        if (Database::columnExists('receipts', 'invoice_id')) {
            return Database::fetchAll(
                'SELECT r.* FROM receipts r INNER JOIN invoices i ON i.id = r.invoice_id WHERE i.client_id = ?',
                [$clientId]
            );
        }

        return Database::fetchAll(
            'SELECT r.* FROM receipts r
             INNER JOIN payments p ON p.id = r.payment_id
             INNER JOIN invoices i ON i.id = p.invoice_id
             WHERE i.client_id = ?',
            [$clientId]
        );
    }
}
