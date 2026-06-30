<?php

declare(strict_types=1);

class ClientService
{
    public static function getById(int $id): ?array
    {
        if (TenantService::isEnabled()) {
            return Database::fetch(
                'SELECT * FROM clients WHERE id = ? AND company_id = ?',
                [$id, TenantService::id()]
            );
        }

        return Database::fetch('SELECT * FROM clients WHERE id = ?', [$id]);
    }

    public static function create(array $data, bool $createLogin = false): array
    {
        $profile = self::validatedProfile($data);

        if (self::emailExists($profile['email'])) {
            throw new RuntimeException('A client with this email already exists.');
        }

        $plainPassword = null;
        $userId        = null;

        if ($createLogin) {
            $plainPassword = self::resolvePortalPassword($data);
            $userId        = self::createUserAccount(
                $profile['email'],
                $plainPassword,
                $profile['first_name'],
                $profile['last_name'],
                $profile['phone']
            );
        }

        $clientId = self::insertClientRow([
            'user_id'      => $userId,
            'first_name'   => $profile['first_name'],
            'last_name'    => $profile['last_name'],
            'email'        => $profile['email'],
            'phone'        => $profile['phone'],
            'company_name' => trim($data['company_name'] ?? '') ?: null,
            'address'      => $profile['address'],
            'city'         => $profile['city'],
            'state'        => $profile['state'],
            'zip_code'     => $profile['zip_code'],
            'country'      => $profile['country'],
            'notes'        => trim($data['notes'] ?? '') ?: null,
        ]);

        if ($userId) {
            self::linkUserToClient($userId, $clientId);
        }

        return [
            'client_id' => $clientId,
            'user_id'   => $userId,
            'password'  => $plainPassword,
        ];
    }

    public static function update(int $id, array $data): ?string
    {
        $client = self::getById($id);
        if (!$client) {
            throw new RuntimeException('Client not found.');
        }

        $profile = self::validateClientProfile($data);

        if (self::emailExists($profile['email'], $id)) {
            throw new RuntimeException('Another client already uses this email.');
        }

        try {
            Database::query(
                'UPDATE clients SET first_name = ?, last_name = ?, email = ?, phone = ?, company_name = ?,
                                    address = ?, city = ?, state = ?, zip_code = ?, country = ?, notes = ?,
                                    status = ?, updated_at = NOW()
                 WHERE id = ?',
                [
                    $profile['first_name'],
                    $profile['last_name'],
                    $profile['email'],
                    $profile['phone'],
                    trim($data['company_name'] ?? '') ?: null,
                    $profile['address'],
                    $profile['city'],
                    $profile['state'],
                    $profile['zip_code'],
                    $profile['country'],
                    trim($data['notes'] ?? '') ?: null,
                    $data['status'] ?? 'active',
                    $id,
                ]
            );
        } catch (Throwable $e) {
            Database::query(
                'UPDATE clients SET first_name = ?, last_name = ?, email = ?, phone = ?, company_name = ?,
                                    address = ?, city = ?, state = ?, zip = ?, country = ?, notes = ?,
                                    status = ?, updated_at = NOW()
                 WHERE id = ?',
                [
                    $profile['first_name'],
                    $profile['last_name'],
                    $profile['email'],
                    $profile['phone'],
                    trim($data['company_name'] ?? '') ?: null,
                    $profile['address'],
                    $profile['city'],
                    $profile['state'],
                    $profile['zip_code'],
                    $profile['country'],
                    trim($data['notes'] ?? '') ?: null,
                    $data['status'] ?? 'active',
                    $id,
                ]
            );
        }

        if (!empty($client['user_id'])) {
            self::updateUserAccount((int) $client['user_id'], $profile['email'], $data);
        } elseif (!empty($data['create_login'])) {
            $plainPassword = self::resolvePortalPassword($data);
            $userId        = self::createUserAccount(
                $profile['email'],
                $plainPassword,
                $profile['first_name'],
                $profile['last_name'],
                $profile['phone']
            );
            Database::query('UPDATE clients SET user_id = ?, updated_at = NOW() WHERE id = ?', [$userId, $id]);
            self::linkUserToClient($userId, $id);

            return $plainPassword;
        }

        return null;
    }

    public static function deleteClient(int $id): void
    {
        $client = self::getById($id);
        if (!$client) {
            throw new RuntimeException('Client not found.');
        }

        $cases = Database::fetchAll('SELECT id FROM cases WHERE client_id = ?', [$id]);
        foreach ($cases as $case) {
            try {
                foreach (CaseService::getDocuments((int) $case['id']) as $doc) {
                    $path = $doc['file_path'] ?? $doc['stored_path'] ?? null;
                    if ($path && is_file(CaseService::documentPath($path))) {
                        @unlink(CaseService::documentPath($path));
                    }
                }
            } catch (Throwable $e) {
                // Continue removing the client even if a file cleanup fails.
            }
        }

        $userId = (int) ($client['user_id'] ?? 0);

        Database::query('DELETE FROM clients WHERE id = ?', [$id]);

        if ($userId > 0) {
            try {
                Database::query("DELETE FROM users WHERE id = ? AND role = 'client'", [$userId]);
            } catch (Throwable $e) {
                // User may already be removed by FK rules.
            }
        }
    }

    public static function createPortalLogin(int $clientId, ?string $password = null): array
    {
        $client = self::getById($clientId);
        if (!$client) {
            throw new RuntimeException('Client not found.');
        }

        if (!empty($client['user_id'])) {
            throw new RuntimeException('This client already has portal access.');
        }

        if (empty($password)) {
            throw new RuntimeException('Password is required when creating portal login.');
        }

        $plainPassword = trim($password);
        $strengthError = passwordStrengthError($plainPassword);
        if ($strengthError !== null) {
            throw new RuntimeException($strengthError);
        }

        $userId        = self::createUserAccount(
            $client['email'],
            $plainPassword,
            $client['first_name'] ?? '',
            $client['last_name'] ?? '',
            $client['phone'] ?? null
        );

        Database::query('UPDATE clients SET user_id = ?, updated_at = NOW() WHERE id = ?', [$userId, $clientId]);
        self::linkUserToClient($userId, $clientId);

        return ['user_id' => $userId, 'password' => $plainPassword];
    }

    public static function generatePassword(int $length = 10): string
    {
        $length = max(8, $length);
        $upper  = 'ABCDEFGHJKMNPQRSTUVWXYZ';
        $lower  = 'abcdefghjkmnpqrstuvwxyz';
        $digits = '23456789';
        $all    = $upper . $lower . $digits;

        $password = $upper[random_int(0, strlen($upper) - 1)]
            . $lower[random_int(0, strlen($lower) - 1)]
            . $digits[random_int(0, strlen($digits) - 1)];

        for ($i = 3; $i < $length; $i++) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }

        return str_shuffle($password);
    }

    private static function resolvePortalPassword(array $data): string
    {
        $password = trim($data['password'] ?? '');
        $confirm  = trim($data['password_confirmation'] ?? '');

        if ($password === '') {
            throw new RuntimeException('Please enter a portal password.');
        }

        $strengthError = passwordStrengthError($password);
        if ($strengthError !== null) {
            throw new RuntimeException($strengthError);
        }

        if ($password !== $confirm) {
            throw new RuntimeException('Password confirmation does not match.');
        }

        return $password;
    }

    /**
     * @return array{first_name: string, last_name: string, email: string, phone: string, address: string, city: string, state: string, zip_code: string, country: string}
     */
    public static function validatedProfile(array $data): array
    {
        return self::validateClientProfile($data);
    }

    /**
     * @return array{first_name: string, last_name: string, email: string, phone: string, address: string, city: string, state: string, zip_code: string, country: string}
     */
    private static function validateClientProfile(array $data): array
    {
        $firstName = trim($data['first_name'] ?? '');
        $lastName  = trim($data['last_name'] ?? '');
        $email     = trim($data['email'] ?? '');
        $phone     = trim($data['phone'] ?? '');
        $address   = trim($data['address'] ?? '');
        $city      = trim($data['city'] ?? '');
        $state     = trim($data['state'] ?? '');
        $zipCode   = trim($data['zip_code'] ?? '');
        $country   = trim($data['country'] ?? '');

        if ($firstName === '' || $lastName === '' || $email === '') {
            throw new RuntimeException('First name, last name, and email are required.');
        }

        if ($phone === '') {
            throw new RuntimeException('Phone number is required.');
        }

        if ($address === '' || $city === '' || $state === '' || $zipCode === '' || $country === '') {
            throw new RuntimeException('Complete address is required (street, city, state/region, postal code, and country).');
        }

        return [
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'email'      => $email,
            'phone'      => $phone,
            'address'    => $address,
            'city'       => $city,
            'state'      => $state,
            'zip_code'   => $zipCode,
            'country'    => $country,
        ];
    }

    public static function emailExistsPublic(string $email, ?int $excludeId = null): bool
    {
        return self::emailExists($email, $excludeId);
    }

    private static function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql    = 'SELECT id FROM clients WHERE email = ?';
        $params = [$email];

        if (TenantService::isEnabled()) {
            $sql .= ' AND company_id = ?';
            $params[] = TenantService::id();
        }

        if ($excludeId) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }

        return (bool) Database::fetch($sql, $params);
    }

    private static function createUserAccount(string $email, string $password, string $firstName, string $lastName, ?string $phone): int
    {
        if (Database::fetch(
            TenantService::isEnabled()
                ? 'SELECT id FROM users WHERE email = ? AND company_id = ? LIMIT 1'
                : 'SELECT id FROM users WHERE email = ? LIMIT 1',
            TenantService::isEnabled() ? [$email, TenantService::id()] : [$email]
        )) {
            throw new RuntimeException('A user account with this email already exists.');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $name = trim($firstName . ' ' . $lastName);

        try {
            if (TenantService::isEnabled() && Database::columnExists('users', 'company_id')) {
                return Database::insert(
                    "INSERT INTO users (company_id, email, password, role, name, status, is_active, created_at, updated_at)
                     VALUES (?, ?, ?, 'client', ?, 'active', 1, NOW(), NOW())",
                    [TenantService::id(), $email, $hash, $name]
                );
            }

            return Database::insert(
                "INSERT INTO users (email, password, role, name, status, is_active, created_at, updated_at)
                 VALUES (?, ?, 'client', ?, 'active', 1, NOW(), NOW())",
                [$email, $hash, $name]
            );
        } catch (Throwable $e) {
            return Database::insert(
                "INSERT INTO users (email, password, role, name, status, created_at, updated_at)
                 VALUES (?, ?, 'client', ?, 'active', NOW(), NOW())",
                [$email, $hash, $name]
            );
        }
    }

    private static function linkUserToClient(int $userId, int $clientId): void
    {
        try {
            Database::query('UPDATE users SET client_id = ?, updated_at = NOW() WHERE id = ?', [$clientId, $userId]);
        } catch (Throwable $e) {
            // optional column
        }

        try {
            Database::query('UPDATE clients SET login_enabled = 1, updated_at = NOW() WHERE id = ?', [$clientId]);
        } catch (Throwable $e) {
            // optional column
        }
    }

    private static function updateUserAccount(int $userId, string $email, array $data): void
    {
        $name = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));

        Database::query(
            'UPDATE users SET email = ?, name = ?, updated_at = NOW() WHERE id = ?',
            [$email, $name, $userId]
        );
    }

    private static function insertClientRow(array $data): int
    {
        if (TenantService::isEnabled() && Database::columnExists('clients', 'company_id')) {
            $data['company_id'] = TenantService::id();
        }

        try {
            if (!empty($data['company_id'])) {
                return Database::insert(
                    'INSERT INTO clients (company_id, user_id, first_name, last_name, email, phone, company_name, address, city, state, zip_code, country, notes, status, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                    [
                        $data['company_id'],
                        $data['user_id'],
                        $data['first_name'],
                        $data['last_name'],
                        $data['email'],
                        $data['phone'],
                        $data['company_name'],
                        $data['address'],
                        $data['city'],
                        $data['state'],
                        $data['zip_code'],
                        $data['country'],
                        $data['notes'],
                        'active',
                    ]
                );
            }

            return Database::insert(
                'INSERT INTO clients (user_id, first_name, last_name, email, phone, company_name, address, city, state, zip, country, notes, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                [
                    $data['user_id'],
                    $data['first_name'],
                    $data['last_name'],
                    $data['email'],
                    $data['phone'],
                    $data['company_name'],
                    $data['address'],
                    $data['city'],
                    $data['state'],
                    $data['zip_code'],
                    $data['country'],
                    $data['notes'],
                    'active',
                ]
            );
        } catch (Throwable $e) {
            if (!empty($data['company_id'])) {
                return Database::insert(
                    'INSERT INTO clients (company_id, user_id, first_name, last_name, email, phone, company_name, address, city, state, zip, country, notes, status, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                    [
                        $data['company_id'],
                        $data['user_id'],
                        $data['first_name'],
                        $data['last_name'],
                        $data['email'],
                        $data['phone'],
                        $data['company_name'],
                        $data['address'],
                        $data['city'],
                        $data['state'],
                        $data['zip_code'],
                        $data['country'],
                        $data['notes'],
                        'active',
                    ]
                );
            }

            return Database::insert(
                'INSERT INTO clients (user_id, first_name, last_name, email, phone, company_name, address, city, state, zip_code, country, notes, status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                [
                    $data['user_id'],
                    $data['first_name'],
                    $data['last_name'],
                    $data['email'],
                    $data['phone'],
                    $data['company_name'],
                    $data['address'],
                    $data['city'],
                    $data['state'],
                    $data['zip_code'],
                    $data['country'],
                    $data['notes'],
                    'active',
                ]
            );
        }
    }
}
