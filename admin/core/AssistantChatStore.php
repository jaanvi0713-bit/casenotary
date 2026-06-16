<?php

declare(strict_types=1);

class AssistantChatStore
{
    private const TABLE = 'chatbot_conversations';

    public static function isAvailable(): bool
    {
        return Database::tableExists(self::TABLE);
    }

    public static function unavailableMessage(): string
    {
        return 'Chat library is not set up yet. Run: php admin/sql/migrate_assistant_chats.php';
    }

    /** @return list<array{id: int, title: string, preview: string, updated_at: string}> */
    public static function listForUser(int $userId): array
    {
        if (!self::isAvailable()) {
            return [];
        }

        $where = ['user_id = ?'];
        $params = [$userId];
        self::appendCompanyScope($where, $params);

        $rows = Database::fetchAll(
            'SELECT id, title, messages, updated_at
             FROM ' . self::TABLE . '
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY updated_at DESC
             LIMIT 100',
            $params
        );

        $items = [];
        foreach ($rows as $row) {
            $messages = self::decodeMessages((string) ($row['messages'] ?? '[]'));
            $items[] = [
                'id'         => (int) $row['id'],
                'title'      => (string) ($row['title'] ?? 'New chat'),
                'preview'    => self::previewFromMessages($messages),
                'updated_at' => (string) ($row['updated_at'] ?? ''),
            ];
        }

        return $items;
    }

    /** @return array{id: int, title: string, messages: list<array<string, mixed>>}|null */
    public static function getForUser(int $userId, int $id): ?array
    {
        if (!self::isAvailable() || $id <= 0) {
            return null;
        }

        $where = ['id = ?', 'user_id = ?'];
        $params = [$id, $userId];
        self::appendCompanyScope($where, $params);

        $row = Database::fetch(
            'SELECT id, title, messages FROM ' . self::TABLE . ' WHERE ' . implode(' AND ', $where) . ' LIMIT 1',
            $params
        );

        if (!$row) {
            return null;
        }

        return [
            'id'       => (int) $row['id'],
            'title'    => (string) ($row['title'] ?? 'New chat'),
            'messages' => self::decodeMessages((string) ($row['messages'] ?? '[]')),
        ];
    }

    public static function create(int $userId, string $title = 'New chat'): int
    {
        if (!self::isAvailable()) {
            throw new RuntimeException(self::unavailableMessage());
        }

        $data = [
            'user_id'  => $userId,
            'title'    => self::sanitizeTitle($title),
            'messages' => self::encodeMessages([]),
        ];

        if (TenantService::isEnabled() && Database::columnExists(self::TABLE, 'company_id')) {
            $data['company_id'] = TenantService::id();
        }

        return insertTableRow(self::TABLE, $data);
    }

    /**
     * @param list<array<string, mixed>> $messages
     */
    public static function save(int $userId, int $id, array $messages, ?string $title = null): bool
    {
        if (!self::isAvailable() || $id <= 0) {
            return false;
        }

        $where = ['id = ?', 'user_id = ?'];
        $params = [self::encodeMessages($messages), $id, $userId];
        $setTitle = '';

        if ($title !== null && $title !== '') {
            $setTitle = ', title = ?';
            array_splice($params, 1, 0, [self::sanitizeTitle($title)]);
        }

        self::appendCompanyScope($where, $params, '');

        $stmt = Database::query(
            'UPDATE ' . self::TABLE . ' SET messages = ?, updated_at = NOW()' . $setTitle
            . ' WHERE ' . implode(' AND ', $where),
            $params
        );

        return $stmt->rowCount() > 0;
    }

    public static function rename(int $userId, int $id, string $title): bool
    {
        if (!self::isAvailable() || $id <= 0) {
            return false;
        }

        $where = ['id = ?', 'user_id = ?'];
        $params = [self::sanitizeTitle($title), $id, $userId];
        self::appendCompanyScope($where, $params, '');

        $stmt = Database::query(
            'UPDATE ' . self::TABLE . ' SET title = ?, updated_at = NOW() WHERE ' . implode(' AND ', $where),
            $params
        );

        return $stmt->rowCount() > 0;
    }

    public static function delete(int $userId, int $id): bool
    {
        if (!self::isAvailable() || $id <= 0) {
            return false;
        }

        $where = ['id = ?', 'user_id = ?'];
        $params = [$id, $userId];
        self::appendCompanyScope($where, $params, '');

        $stmt = Database::query('DELETE FROM ' . self::TABLE . ' WHERE ' . implode(' AND ', $where), $params);

        return $stmt->rowCount() > 0;
    }

    /** @param list<array<string, mixed>> $messages */
    public static function titleFromMessages(array $messages, string $fallback = 'New chat'): string
    {
        foreach ($messages as $turn) {
            if (($turn['role'] ?? '') !== 'user') {
                continue;
            }

            $text = trim((string) ($turn['content'] ?? ''));
            if ($text === '' || $text === '[Document upload]' || $text === 'Confirm action') {
                continue;
            }

            return self::sanitizeTitle(mb_strimwidth(assistantSanitizeUtf8($text), 0, 60, '…'));
        }

        return $fallback;
    }

    /** @return list<array<string, mixed>> */
    private static function decodeMessages(string $json): array
    {
        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @param list<array<string, mixed>> $messages */
    private static function previewFromMessages(array $messages): string
    {
        for ($i = count($messages) - 1; $i >= 0; $i--) {
            $text = trim((string) ($messages[$i]['content'] ?? ''));
            if ($text !== '') {
                return mb_strimwidth(
                    preg_replace('/\s+/', ' ', assistantSanitizeUtf8($text)) ?? $text,
                    0,
                    72,
                    '…'
                );
            }
        }

        return 'Empty chat';
    }

    private static function sanitizeTitle(string $title): string
    {
        $title = trim(preg_replace('/\s+/', ' ', assistantSanitizeUtf8($title)) ?? '');

        return $title !== '' ? mb_substr($title, 0, 255) : 'New chat';
    }

    /**
     * @param list<array<string, mixed>> $messages
     */
    private static function encodeMessages(array $messages): string
    {
        $clean = [];
        foreach ($messages as $turn) {
            if (!is_array($turn)) {
                continue;
            }

            $row = $turn;
            if (isset($row['content']) && is_string($row['content'])) {
                $row['content'] = assistantSanitizeUtf8($row['content']);
            }

            $clean[] = $row;
        }

        $json = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        return is_string($json) ? $json : '[]';
    }

    /** @param list<mixed> $params */
    private static function appendCompanyScope(array &$where, array &$params, string $prefix = ''): void
    {
        if (!TenantService::isEnabled() || !Database::columnExists(self::TABLE, 'company_id')) {
            return;
        }

        $col = ($prefix !== '' ? rtrim($prefix, '.') . '.' : '') . 'company_id';
        $where[] = "{$col} = ?";
        $params[] = TenantService::id();
    }
}
