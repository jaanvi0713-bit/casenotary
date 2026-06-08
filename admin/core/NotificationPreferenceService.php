<?php

declare(strict_types=1);

class NotificationPreferenceService
{
    /** @var list<string> */
    public const TYPES = [
        'case',
        'invoice',
        'payment',
        'appointment',
        'document',
        'account',
        'system',
    ];

    /** @var array<string, array{in_app: bool, email: bool}> */
    private const DEFAULTS = [
        'case' => ['in_app' => true, 'email' => false],
        'invoice' => ['in_app' => true, 'email' => true],
        'payment' => ['in_app' => true, 'email' => true],
        'appointment' => ['in_app' => true, 'email' => true],
        'document' => ['in_app' => true, 'email' => false],
        'account' => ['in_app' => true, 'email' => true],
        'system' => ['in_app' => true, 'email' => false],
    ];

    public static function columnExists(): bool
    {
        return Database::columnExists('users', 'notification_preferences');
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'case' => 'Cases',
            'invoice' => 'Invoices',
            'payment' => 'Payments',
            'appointment' => 'Appointments',
            'document' => 'Documents',
            'account' => 'Account & access',
            'system' => 'System alerts',
            default => ucwords(str_replace('_', ' ', $type)),
        };
    }

    /** @return array<string, array{in_app: bool, email: bool}> */
    public static function defaults(): array
    {
        return self::DEFAULTS;
    }

    /** @return array<string, array{in_app: bool, email: bool}> */
    public static function get(int $userId): array
    {
        $merged = self::defaults();

        if ($userId <= 0 || !self::columnExists()) {
            return $merged;
        }

        $row = Database::fetch(
            'SELECT notification_preferences FROM users WHERE id = ? LIMIT 1',
            [$userId]
        );

        if (!$row) {
            return $merged;
        }

        $stored = json_decode((string) ($row['notification_preferences'] ?? ''), true);
        if (!is_array($stored)) {
            return $merged;
        }

        foreach (self::TYPES as $type) {
            if (!isset($stored[$type]) || !is_array($stored[$type])) {
                continue;
            }

            $merged[$type] = [
                'in_app' => !empty($stored[$type]['in_app']),
                'email' => !empty($stored[$type]['email']),
            ];
        }

        return $merged;
    }

    /** @param array<string, mixed> $input */
    public static function save(int $userId, array $input): void
    {
        if ($userId <= 0) {
            throw new RuntimeException('Invalid user.');
        }

        if (!self::columnExists()) {
            throw new RuntimeException('Notification preferences are not installed. Run: php admin/sql/migrate_notification_preferences.php');
        }

        $prefs = self::defaults();

        foreach (self::TYPES as $type) {
            $prefs[$type] = [
                'in_app' => !empty($input[$type]['in_app']),
                'email' => !empty($input[$type]['email']),
            ];

            if (!$prefs[$type]['in_app']) {
                $prefs[$type]['email'] = !empty($input[$type]['email']);
            }
        }

        Database::query(
            'UPDATE users SET notification_preferences = ?, updated_at = NOW() WHERE id = ?',
            [json_encode($prefs, JSON_UNESCAPED_UNICODE), $userId]
        );
    }

    public static function wantsInApp(int $userId, string $type): bool
    {
        $type = self::normalizeType($type);
        $prefs = self::get($userId);

        return !empty($prefs[$type]['in_app']);
    }

    public static function wantsEmail(int $userId, string $type): bool
    {
        $type = self::normalizeType($type);
        $prefs = self::get($userId);

        return !empty($prefs[$type]['email']);
    }

    public static function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));

        return in_array($type, self::TYPES, true) ? $type : 'system';
    }
}
