<?php

declare(strict_types=1);

class ProfileService
{
    public static function getById(int $userId): ?array
    {
        return Database::fetch('SELECT * FROM users WHERE id = ?', [$userId]);
    }

    public static function update(int $userId, array $data): void
    {
        $user = self::getById($userId);
        if (!$user) {
            throw new RuntimeException('User not found.');
        }

        $firstName = trim($data['first_name'] ?? '');
        $lastName  = trim($data['last_name'] ?? '');
        $email     = trim($data['email'] ?? '');
        $phone     = trim($data['phone'] ?? '') ?: null;

        if ($firstName === '' || $lastName === '') {
            throw new RuntimeException('First and last name are required.');
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('A valid email address is required.');
        }

        $existing = Database::fetch(
            'SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1',
            [$email, $userId]
        );

        if ($existing) {
            throw new RuntimeException('Another account already uses this email.');
        }

        if (Database::columnExists('users', 'phone')) {
            Database::query(
                'UPDATE users SET first_name = ?, last_name = ?, email = ?, phone = ?, updated_at = NOW() WHERE id = ?',
                [$firstName, $lastName, $email, $phone, $userId]
            );
        } else {
            Database::query(
                'UPDATE users SET first_name = ?, last_name = ?, email = ?, updated_at = NOW() WHERE id = ?',
                [$firstName, $lastName, $email, $userId]
            );
        }

        if ($userId === Auth::id()) {
            $_SESSION['user_email'] = $email;
            $_SESSION['user_name']    = trim($firstName . ' ' . $lastName);
        }
    }

    public static function changePassword(int $userId, string $currentPassword, string $newPassword, string $confirmPassword): void
    {
        $user = Database::fetch('SELECT id, password FROM users WHERE id = ?', [$userId]);
        if (!$user) {
            throw new RuntimeException('User not found.');
        }

        if (!password_verify($currentPassword, $user['password'])) {
            throw new RuntimeException('Current password is incorrect.');
        }

        if ($newPassword !== $confirmPassword) {
            throw new RuntimeException('New password confirmation does not match.');
        }

        $strengthError = passwordStrengthError($newPassword);
        if ($strengthError !== null) {
            throw new RuntimeException($strengthError);
        }

        Database::query(
            'UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?',
            [password_hash($newPassword, PASSWORD_BCRYPT), $userId]
        );
    }
}
