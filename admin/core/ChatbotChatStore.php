<?php

declare(strict_types=1);

class ChatbotChatStore
{
    public static function isAvailable(): bool
    {
        return Database::tableExists('chatbot_conversations');
    }

    public static function unavailableMessage(): string
    {
        return 'Chat history is not set up yet. Run: php admin/sql/migrate_chatbot.php';
    }

    /** @return array{0: string, 1: list<mixed>} */
    private static function companyScope(): array
    {
        if (!TenantService::hasChatScope()) {
            return ['', []];
        }

        return [' AND company_id = ?', [TenantService::id()]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function listForUser(int $userId, int $limit = 50): array
    {
        if (!self::isAvailable() || $userId <= 0) {
            return [];
        }

        [$scopeSql, $scopeParams] = self::companyScope();

        $rows = Database::fetchAll(
            'SELECT id, title, messages, created_at, updated_at
             FROM chatbot_conversations
             WHERE user_id = ?' . $scopeSql . '
             ORDER BY updated_at DESC
             LIMIT ?',
            array_merge([$userId], $scopeParams, [$limit])
        );

        return array_map([self::class, 'formatListItem'], $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getForUser(int $userId, int $conversationId): ?array
    {
        if (!self::isAvailable() || $userId <= 0 || $conversationId <= 0) {
            return null;
        }

        [$scopeSql, $scopeParams] = self::companyScope();

        $row = Database::fetch(
            'SELECT id, title, messages, created_at, updated_at
             FROM chatbot_conversations
             WHERE id = ? AND user_id = ?' . $scopeSql,
            array_merge([$conversationId, $userId], $scopeParams)
        );

        return $row ? self::formatConversation($row) : null;
    }

    public static function create(int $userId, string $title = 'New chat'): int
    {
        if (!self::isAvailable() || $userId <= 0) {
            return 0;
        }

        $data = [
            'user_id'  => $userId,
            'title'    => self::sanitizeTitle($title),
            'messages' => json_encode([], JSON_UNESCAPED_UNICODE),
        ];

        if (TenantService::hasChatScope()) {
            $data['company_id'] = TenantService::id();
        }

        return insertTableRow('chatbot_conversations', $data);
    }

    /**
     * @param list<array<string, mixed>> $messages
     */
    public static function save(int $userId, int $conversationId, array $messages, ?string $title = null): bool
    {
        if (!self::isAvailable() || $userId <= 0 || $conversationId <= 0) {
            return false;
        }

        $existing = self::getForUser($userId, $conversationId);
        if ($existing === null) {
            return false;
        }

        $normalized = self::normalizeMessages($messages);
        $resolvedTitle = $title !== null && trim($title) !== ''
            ? self::sanitizeTitle($title)
            : self::titleFromMessages($normalized, (string) ($existing['title'] ?? 'New chat'));

        [$scopeSql, $scopeParams] = self::companyScope();

        Database::query(
            'UPDATE chatbot_conversations
             SET title = ?, messages = ?, updated_at = NOW()
             WHERE id = ? AND user_id = ?' . $scopeSql,
            array_merge(
                [$resolvedTitle, json_encode($normalized, JSON_UNESCAPED_UNICODE), $conversationId, $userId],
                $scopeParams
            )
        );

        return true;
    }

    public static function rename(int $userId, int $conversationId, string $title): bool
    {
        if (!self::isAvailable() || $userId <= 0 || $conversationId <= 0) {
            return false;
        }

        $title = self::sanitizeTitle($title);
        if ($title === '') {
            return false;
        }

        [$scopeSql, $scopeParams] = self::companyScope();

        Database::query(
            'UPDATE chatbot_conversations SET title = ?, updated_at = NOW() WHERE id = ? AND user_id = ?' . $scopeSql,
            array_merge([$title, $conversationId, $userId], $scopeParams)
        );

        return true;
    }

    public static function delete(int $userId, int $conversationId): bool
    {
        if (!self::isAvailable() || $userId <= 0 || $conversationId <= 0) {
            return false;
        }

        [$scopeSql, $scopeParams] = self::companyScope();

        Database::query(
            'DELETE FROM chatbot_conversations WHERE id = ? AND user_id = ?' . $scopeSql,
            array_merge([$conversationId, $userId], $scopeParams)
        );

        return true;
    }

    /**
     * @param list<array<string, mixed>> $messages
     * @return array<string, mixed>
     */
    public static function appendExchange(int $userId, int $conversationId, array $messages): array
    {
        if ($conversationId <= 0) {
            $conversationId = self::create($userId);
        }

        $existing = self::getForUser($userId, $conversationId);
        if ($existing === null) {
            $conversationId = self::create($userId);
            $existing = self::getForUser($userId, $conversationId);
        }

        if ($existing === null) {
            return ['id' => 0, 'title' => 'New chat'];
        }

        $merged = array_merge($existing['messages'] ?? [], self::normalizeMessages($messages));
        $title = self::titleFromMessages($merged, (string) ($existing['title'] ?? 'New chat'));
        self::save($userId, $conversationId, $merged, $title);

        return [
            'id'    => $conversationId,
            'title' => $title,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function formatListItem(array $row): array
    {
        $messages = self::decodeMessages($row['messages'] ?? '[]');
        $preview = self::previewFromMessages($messages);

        return [
            'id'         => (int) ($row['id'] ?? 0),
            'title'      => (string) ($row['title'] ?? 'New chat'),
            'preview'    => $preview,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'created_label' => self::formatLabel($row['created_at'] ?? null),
            'updated_label' => self::formatLabel($row['updated_at'] ?? null),
            'message_count' => count($messages),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function formatConversation(array $row): array
    {
        $messages = self::decodeMessages($row['messages'] ?? '[]');

        return [
            'id'         => (int) ($row['id'] ?? 0),
            'title'      => (string) ($row['title'] ?? 'New chat'),
            'messages'   => $messages,
            'preview'    => self::previewFromMessages($messages),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'created_label' => self::formatLabel($row['created_at'] ?? null),
            'updated_label' => self::formatLabel($row['updated_at'] ?? null),
            'message_count' => count($messages),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function decodeMessages(mixed $raw): array
    {
        if (is_array($raw)) {
            return self::normalizeMessages($raw);
        }

        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? self::normalizeMessages($decoded) : [];
    }

    /**
     * @param list<array<string, mixed>> $messages
     * @return list<array<string, mixed>>
     */
    private static function normalizeMessages(array $messages): array
    {
        $normalized = [];

        foreach ($messages as $message) {
            if (!is_array($message)) {
                continue;
            }

            $type = (string) ($message['type'] ?? '');
            if (!in_array($type, ['user', 'bot'], true)) {
                continue;
            }

            $normalized[] = [
                'type'        => $type,
                'text'        => mb_strimwidth(trim((string) ($message['text'] ?? '')), 0, 8000, '…'),
                'attachments' => trim((string) ($message['attachments'] ?? '')),
            ];
        }

        return array_slice($normalized, -80);
    }

    /**
     * @param list<array<string, mixed>> $messages
     */
    private static function previewFromMessages(array $messages): string
    {
        foreach ($messages as $message) {
            if (($message['type'] ?? '') === 'user') {
                $text = trim((string) ($message['text'] ?? ''));
                if ($text !== '') {
                    return mb_strimwidth($text, 0, 72, '…');
                }
            }
        }

        return 'No messages yet';
    }

    /**
     * @param list<array<string, mixed>> $messages
     */
    private static function titleFromMessages(array $messages, string $fallback): string
    {
        if ($fallback !== '' && $fallback !== 'New chat') {
            return self::sanitizeTitle($fallback);
        }

        foreach ($messages as $message) {
            if (($message['type'] ?? '') === 'user') {
                $text = trim((string) ($message['text'] ?? ''));
                if ($text !== '') {
                    return self::sanitizeTitle($text);
                }
            }
        }

        return 'New chat';
    }

    private static function sanitizeTitle(string $title): string
    {
        $title = trim(preg_replace('/\s+/', ' ', $title) ?? '');

        return mb_strimwidth($title, 0, 120, '…');
    }

    private static function formatLabel(?string $datetime): string
    {
        if ($datetime === null || trim($datetime) === '') {
            return '';
        }

        return formatDateTime($datetime);
    }
}
