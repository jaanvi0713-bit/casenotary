<?php

declare(strict_types=1);

/**
 * Per-user notification delivery preferences (in-app bell + optional email).
 */
class NotificationPreferenceService
{
    /** @var list<string> */
    public const TYPES = [
        'case',
        'document',
        'invoice',
        'payment',
        'appointment',
        'account',
        'system',
    ];

    /** @var array<int, array<string, array{in_app: bool, email: bool}>> */
    private static array $cache = [];

    public static function tableExists(): bool
    {
        return Database::tableExists('user_notification_preferences');
    }

    public static function clearCache(?int $userId = null): void
    {
        if ($userId === null) {
            self::$cache = [];

            return;
        }

        unset(self::$cache[$userId]);
    }

    /** @return array<string, array{in_app: bool, email: bool}> */
    public static function defaultPreferences(): array
    {
        $defaults = [];

        foreach (self::TYPES as $type) {
            $defaults[$type] = [
                'in_app' => true,
                'email' => in_array($type, ['appointment', 'invoice', 'payment'], true),
            ];
        }

        return $defaults;
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'case' => 'Cases',
            'document' => 'Documents',
            'invoice' => 'Invoices',
            'payment' => 'Payments',
            'appointment' => 'Appointments',
            'account' => 'Account & access',
            'system' => 'System alerts',
            default => ucwords(str_replace('_', ' ', $type)),
        };
    }

    public static function typeDescription(string $type): string
    {
        return match ($type) {
            'case' => 'New cases, updates, and status changes.',
            'document' => 'Uploads, quotations, proposals, and letters.',
            'invoice' => 'Invoices generated or updated on your cases.',
            'payment' => 'Payments recorded against invoices.',
            'appointment' => 'Scheduling, changes, and reminders.',
            'account' => 'Portal access and account-related notices.',
            'system' => 'Workspace announcements and system messages.',
            default => '',
        };
    }

    /** @return array<string, array{in_app: bool, email: bool}> */
    public static function getForUser(int $userId): array
    {
        if ($userId <= 0) {
            return self::defaultPreferences();
        }

        if (isset(self::$cache[$userId])) {
            return self::$cache[$userId];
        }

        $prefs = self::defaultPreferences();

        if (!self::tableExists()) {
            self::$cache[$userId] = $prefs;

            return $prefs;
        }

        $rows = Database::fetchAll(
            'SELECT type, in_app, email FROM user_notification_preferences WHERE user_id = ?',
            [$userId]
        );

        foreach ($rows as $row) {
            $type = (string) ($row['type'] ?? '');
            if (!isset($prefs[$type])) {
                continue;
            }

            $prefs[$type] = [
                'in_app' => (int) ($row['in_app'] ?? 1) === 1,
                'email' => (int) ($row['email'] ?? 0) === 1,
            ];
        }

        self::$cache[$userId] = $prefs;

        return $prefs;
    }

    public static function allowsInApp(int $userId, string $type): bool
    {
        $type = self::normalizeType($type);
        $prefs = self::getForUser($userId);

        return ($prefs[$type]['in_app'] ?? true);
    }

    public static function allowsEmail(int $userId, string $type): bool
    {
        $type = self::normalizeType($type);
        $prefs = self::getForUser($userId);

        return ($prefs[$type]['email'] ?? false);
    }

    /**
     * @param array<string, array{in_app?: bool, email?: bool}> $input
     */
    public static function saveForUser(int $userId, array $input): void
    {
        if ($userId <= 0) {
            throw new RuntimeException('Invalid user.');
        }

        if (!self::tableExists()) {
            throw new RuntimeException('Notification preferences are not installed. Run: php admin/sql/migrate_notification_preferences.php');
        }

        foreach (self::TYPES as $type) {
            $row = $input[$type] ?? [];
            $inApp = !empty($row['in_app']);
            $email = !empty($row['email']);

            $existing = Database::fetch(
                'SELECT id FROM user_notification_preferences WHERE user_id = ? AND type = ? LIMIT 1',
                [$userId, $type]
            );

            if ($existing) {
                Database::query(
                    'UPDATE user_notification_preferences SET in_app = ?, email = ?, updated_at = NOW() WHERE user_id = ? AND type = ?',
                    [$inApp ? 1 : 0, $email ? 1 : 0, $userId, $type]
                );
            } else {
                Database::insert(
                    'INSERT INTO user_notification_preferences (user_id, type, in_app, email, updated_at) VALUES (?, ?, ?, ?, NOW())',
                    [$userId, $type, $inApp ? 1 : 0, $email ? 1 : 0]
                );
            }
        }

        self::clearCache($userId);
    }

    public static function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));

        return in_array($type, self::TYPES, true) ? $type : 'system';
    }
}
