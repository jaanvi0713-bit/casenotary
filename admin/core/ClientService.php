<?php

declare(strict_types=1);

class ClientService
{
    public static function getById(int $id): ?array
    {
        return Database::fetch('SELECT * FROM clients WHERE id = ?', [$id]);
    }

    public static function create(array $data, bool $createLogin = false): array
    {
        $firstName = trim($data['first_name'] ?? '');
        $lastName  = trim($data['last_name'] ?? '');
        $email     = trim($data['email'] ?? '');
        $phone     = trim($data['phone'] ?? '') ?: null;

        if ($firstName === '' || $lastName === '' || $email === '') {
            throw new RuntimeException('First name, last name, and email are required.');
        }

        if (self::emailExists($email)) {
            throw new RuntimeException('A client with this email already exists.');
        }

        $plainPassword = null;
        $userId        = null;

        if ($createLogin) {
            $plainPassword = self::resolvePortalPassword($data);
            $userId        = self::createUserAccount($email, $plainPassword, $firstName, $lastName, $phone);
        }

        $clientId = self::insertClientRow([
            'user_id'      => $userId,
            'first_name'   => $firstName,
            'last_name'    => $lastName,
            'email'        => $email,
            'phone'        => $phone,
            'company_name' => trim($data['company_name'] ?? '') ?: null,
            'address'      => trim($data['address'] ?? '') ?: null,
            'city'         => trim($data['city'] ?? '') ?: null,
            'state'        => trim($data['state'] ?? '') ?: null,
            'zip_code'     => trim($data['zip_code'] ?? '') ?: null,
            'country'      => trim($data['country'] ?? '') ?: 'USA',
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

        $email = trim($data['email'] ?? '');
        if ($email === '') {
            throw new RuntimeException('Email is required.');
        }

        if (self::emailExists($email, $id)) {
            throw new RuntimeException('Another client already uses this email.');
        }

        try {
            Database::query(
                'UPDATE clients SET first_name = ?, last_name = ?, email = ?, phone = ?, company_name = ?,
                                    address = ?, city = ?, state = ?, zip_code = ?, country = ?, notes = ?,
                                    status = ?, updated_at = NOW()
                 WHERE id = ?',
                [
                    trim($data['first_name'] ?? ''),
                    trim($data['last_name'] ?? ''),
                    $email,
                    trim($data['phone'] ?? '') ?: null,
                    trim($data['company_name'] ?? '') ?: null,
                    trim($data['address'] ?? '') ?: null,
                    trim($data['city'] ?? '') ?: null,
                    trim($data['state'] ?? '') ?: null,
                    trim($data['zip_code'] ?? '') ?: null,
                    trim($data['country'] ?? '') ?: 'USA',
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
                    trim($data['first_name'] ?? ''),
                    trim($data['last_name'] ?? ''),
                    $email,
                    trim($data['phone'] ?? '') ?: null,
                    trim($data['company_name'] ?? '') ?: null,
                    trim($data['address'] ?? '') ?: null,
                    trim($data['city'] ?? '') ?: null,
                    trim($data['state'] ?? '') ?: null,
                    trim($data['zip_code'] ?? '') ?: null,
                    trim($data['country'] ?? '') ?: 'USA',
                    trim($data['notes'] ?? '') ?: null,
                    $data['status'] ?? 'active',
                    $id,
                ]
            );
        }

        if (!empty($client['user_id'])) {
            self::updateUserAccount((int) $client['user_id'], $email, $data);
        } elseif (!empty($data['create_login'])) {
            $plainPassword = self::resolvePortalPassword($data);
            $userId        = self::createUserAccount($email, $plainPassword, trim($data['first_name'] ?? ''), trim($data['last_name'] ?? ''), trim($data['phone'] ?? '') ?: null);
            Database::query('UPDATE clients SET user_id = ?, updated_at = NOW() WHERE id = ?', [$userId, $id]);
            self::linkUserToClient($userId, $id);

            return $plainPassword;
        }

        return null;
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

    private static function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql    = 'SELECT id FROM clients WHERE email = ?';
        $params = [$email];

        if ($excludeId) {
            $sql .= ' AND id != ?';
            $params[] = $excludeId;
        }

        return (bool) Database::fetch($sql, $params);
    }

    private static function createUserAccount(string $email, string $password, string $firstName, string $lastName, ?string $phone): int
    {
        if (Database::fetch('SELECT id FROM users WHERE email = ? LIMIT 1', [$email])) {
            throw new RuntimeException('A user account with this email already exists.');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $name = trim($firstName . ' ' . $lastName);

        try {
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
        try {
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
