<?php

declare(strict_types=1);

class ClientMessageService
{
    public static function ensureSchema(): void
    {
        try {
            if (!Database::tableExists('client_contact_threads')) {
                Database::query(
                    'CREATE TABLE IF NOT EXISTS client_contact_threads (
                        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        company_id INT UNSIGNED DEFAULT NULL,
                        client_id INT UNSIGNED NOT NULL,
                        subject VARCHAR(255) NOT NULL,
                        status ENUM(\'open\', \'closed\') NOT NULL DEFAULT \'open\',
                        admin_unread TINYINT(1) NOT NULL DEFAULT 1,
                        last_message_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        INDEX idx_cct_client (client_id),
                        INDEX idx_cct_company (company_id),
                        INDEX idx_cct_unread (admin_unread, last_message_at)
                    ) ENGINE=InnoDB'
                );
            }

            if (!Database::tableExists('client_contact_messages')) {
                Database::query(
                    'CREATE TABLE IF NOT EXISTS client_contact_messages (
                        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        thread_id INT UNSIGNED NOT NULL,
                        direction ENUM(\'inbound\', \'outbound\') NOT NULL,
                        body TEXT NOT NULL,
                        admin_user_id INT UNSIGNED DEFAULT NULL,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        edited_at DATETIME DEFAULT NULL,
                        INDEX idx_ccm_thread (thread_id),
                        INDEX idx_ccm_admin (admin_user_id)
                    ) ENGINE=InnoDB'
                );
            }

            if (Database::tableExists('client_contact_messages') && !Database::columnExists('client_contact_messages', 'edited_at')) {
                Database::query('ALTER TABLE client_contact_messages ADD COLUMN edited_at DATETIME DEFAULT NULL AFTER created_at');
            }

            if (Database::tableExists('clients') && !Database::columnExists('clients', 'messaging_blocked')) {
                Database::query('ALTER TABLE clients ADD COLUMN messaging_blocked TINYINT(1) NOT NULL DEFAULT 0 AFTER notes');
            }
        } catch (Throwable $e) {
            error_log('ClientMessageService schema: ' . $e->getMessage());
        }

        Database::clearSchemaCache();
    }

    public static function createFromClient(int $clientId, string $subject, string $body): int
    {
        self::ensureSchema();
        if (!Database::tableExists('client_contact_threads') || !Database::tableExists('client_contact_messages')) {
            throw new RuntimeException('Message storage is not available. Please contact support.');
        }

        $subject = trim($subject);
        $body    = trim($body);
        if ($subject === '' || $body === '') {
            throw new InvalidArgumentException('Subject and message are required.');
        }

        $client = self::getClientRecord($clientId);
        if (!$client) {
            throw new RuntimeException('Client profile not found.');
        }

        if (self::isMessagingBlocked($clientId)) {
            throw new RuntimeException('Messaging is not available for your account.');
        }

        $companyId = self::companyIdForClient($client);

        $threadId = (int) Database::insert(
            'INSERT INTO client_contact_threads (company_id, client_id, subject, status, admin_unread, last_message_at, created_at, updated_at)
             VALUES (?, ?, ?, \'open\', 1, NOW(), NOW(), NOW())',
            [$companyId, $clientId, $subject]
        );

        Database::insert(
            'INSERT INTO client_contact_messages (thread_id, direction, body, created_at) VALUES (?, \'inbound\', ?, NOW())',
            [$threadId, $body]
        );

        self::notifyAdminsNewMessage($threadId, $client, $subject, $body, $companyId);

        return $threadId;
    }

    public static function countAdminUnread(): int
    {
        self::ensureSchema();
        if (!Database::tableExists('client_contact_threads')) {
            return 0;
        }

        $where  = ['t.admin_unread = 1'];
        $params = [];
        self::appendThreadScope($where, $params, 't');

        $row = Database::fetch(
            'SELECT COUNT(*) AS c FROM client_contact_threads t WHERE ' . implode(' AND ', $where),
            $params
        );

        return (int) ($row['c'] ?? 0);
    }

    public static function countThreads(?string $q = null, ?string $status = null): int
    {
        self::ensureSchema();
        if (!Database::tableExists('client_contact_threads')) {
            return 0;
        }

        [$where, $params] = self::adminListFilters($q, $status);

        $row = Database::fetch(
            'SELECT COUNT(*) AS c
             FROM client_contact_threads t
             JOIN clients cl ON cl.id = t.client_id
             JOIN users u ON u.id = cl.user_id
             WHERE ' . implode(' AND ', $where),
            $params
        );

        return (int) ($row['c'] ?? 0);
    }

    /** @return list<array<string, mixed>> */
    public static function listThreadsForAdmin(?string $q, ?string $status, int $page, int $perPage): array
    {
        self::ensureSchema();
        if (!Database::tableExists('client_contact_threads')) {
            return [];
        }

        [$where, $params] = self::adminListFilters($q, $status);
        $offset           = paginationOffset($page, $perPage);
        $params[]         = $perPage;
        $params[]         = $offset;

        return Database::fetchAll(
            'SELECT t.*, cl.company_name, u.first_name, u.last_name, u.email,
                    (SELECT body FROM client_contact_messages m WHERE m.thread_id = t.id ORDER BY m.id DESC LIMIT 1) AS preview
             FROM client_contact_threads t
             JOIN clients cl ON cl.id = t.client_id
             JOIN users u ON u.id = cl.user_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY t.last_message_at DESC
             LIMIT ? OFFSET ?',
            $params
        );
    }

    /** @return list<array<string, mixed>> */
    public static function listThreadsForClient(int $clientId, int $limit = 20): array
    {
        self::ensureSchema();
        if (!Database::tableExists('client_contact_threads')) {
            return [];
        }

        return Database::fetchAll(
            'SELECT t.*,
                    (SELECT body FROM client_contact_messages m WHERE m.thread_id = t.id ORDER BY m.id DESC LIMIT 1) AS preview
             FROM client_contact_threads t
             WHERE t.client_id = ?
             ORDER BY t.last_message_at DESC
             LIMIT ?',
            [$clientId, $limit]
        );
    }

    public static function getThreadForAdmin(int $threadId): ?array
    {
        self::ensureSchema();
        if (!Database::tableExists('client_contact_threads')) {
            return null;
        }

        $where  = ['t.id = ?'];
        $params = [$threadId];
        self::appendThreadScope($where, $params, 't');

        $thread = Database::fetch(
            'SELECT t.*, cl.company_name, cl.user_id AS client_user_id, u.first_name, u.last_name, u.email
             FROM client_contact_threads t
             JOIN clients cl ON cl.id = t.client_id
             JOIN users u ON u.id = cl.user_id
             WHERE ' . implode(' AND ', $where) . '
             LIMIT 1',
            $params
        );

        if (!$thread) {
            return null;
        }

        $thread['messages'] = self::messagesForThread($threadId);

        return $thread;
    }

    public static function getThreadForClient(int $threadId, int $clientId): ?array
    {
        self::ensureSchema();
        if (!Database::tableExists('client_contact_threads')) {
            return null;
        }

        $thread = Database::fetch(
            'SELECT t.*
             FROM client_contact_threads t
             WHERE t.id = ? AND t.client_id = ?
             LIMIT 1',
            [$threadId, $clientId]
        );

        if (!$thread) {
            return null;
        }

        $thread['messages'] = self::messagesForThread($threadId);

        return $thread;
    }

    public static function markAdminRead(int $threadId): void
    {
        self::ensureSchema();

        $where  = ['id = ?'];
        $params = [$threadId];
        self::appendThreadScope($where, $params);

        Database::query(
            'UPDATE client_contact_threads SET admin_unread = 0, updated_at = NOW() WHERE ' . implode(' AND ', $where),
            $params
        );
    }

    public static function replyFromAdmin(int $threadId, int $adminUserId, string $body): bool
    {
        self::ensureSchema();

        $body = trim($body);
        if ($body === '') {
            return false;
        }

        $thread = self::getThreadForAdmin($threadId);
        if (!$thread) {
            return false;
        }

        Database::insert(
            'INSERT INTO client_contact_messages (thread_id, direction, body, admin_user_id, created_at)
             VALUES (?, \'outbound\', ?, ?, NOW())',
            [$threadId, $body, $adminUserId]
        );

        Database::query(
            'UPDATE client_contact_threads
             SET status = \'open\', admin_unread = 0, last_message_at = NOW(), updated_at = NOW()
             WHERE id = ?',
            [$threadId]
        );

        $clientUserId = (int) ($thread['client_user_id'] ?? 0);
        $companyId    = (int) ($thread['company_id'] ?? 0);
        $clientName   = clientFullName($thread) ?: 'Client';
        $clientEmail  = (string) ($thread['email'] ?? '');

        if ($clientEmail !== '') {
            MailService::sendClientMessageReplyEmail(
                $clientName,
                $clientEmail,
                (string) ($thread['subject'] ?? 'Your message'),
                $body
            );
        }

        if ($clientUserId > 0) {
            createNotification(
                $clientUserId,
                'Reply from ' . companyBrandName(getCompanySettings()),
                mb_substr($body, 0, 160),
                'system',
                clientUrl('pages/contact.php?thread=' . $threadId),
                $companyId > 0 ? $companyId : null
            );
        }

        return true;
    }

    public static function replyFromClient(int $threadId, int $clientId, string $body): bool
    {
        self::ensureSchema();

        $body = trim($body);
        if ($body === '') {
            return false;
        }

        $thread = self::getThreadForClient($threadId, $clientId);
        if (!$thread || ($thread['status'] ?? '') === 'closed') {
            return false;
        }

        if (self::isMessagingBlocked($clientId)) {
            return false;
        }

        Database::insert(
            'INSERT INTO client_contact_messages (thread_id, direction, body, created_at)
             VALUES (?, \'inbound\', ?, NOW())',
            [$threadId, $body]
        );

        Database::query(
            'UPDATE client_contact_threads
             SET status = \'open\', admin_unread = 1, last_message_at = NOW(), updated_at = NOW()
             WHERE id = ?',
            [$threadId]
        );

        $client = ClientService::getById($clientId);
        if ($client) {
            $companyId = self::companyIdForClient($client);
            self::notifyAdminsNewMessage(
                $threadId,
                $client,
                (string) ($thread['subject'] ?? 'Client message'),
                $body,
                $companyId,
                true
            );

            $company   = getCompanySettings();
            $officeTo  = trim((string) ($company['office_email'] ?? ''));
            if ($officeTo !== '') {
                $name  = clientFullName($client) ?: 'Client';
                $email = (string) (Database::fetch('SELECT email FROM users WHERE id = ?', [(int) ($client['user_id'] ?? 0)])['email'] ?? '');
                $html  = '<p><strong>From:</strong> ' . e($name) . '<br>'
                    . '<strong>Email:</strong> ' . e($email) . '<br>'
                    . '<strong>Subject:</strong> ' . e((string) ($thread['subject'] ?? '')) . '</p>'
                    . '<hr><p>' . nl2br(e($body)) . '</p>';
                MailService::send($officeTo, 'Client Portal reply: ' . (string) ($thread['subject'] ?? ''), $html);
            }
        }

        return true;
    }

    public static function setStatus(int $threadId, string $status): bool
    {
        self::ensureSchema();
        if (!in_array($status, ['open', 'closed'], true)) {
            return false;
        }

        $where  = ['id = ?'];
        $params = [$threadId];
        self::appendThreadScope($where, $params);

        Database::query(
            'UPDATE client_contact_threads SET status = ?, updated_at = NOW() WHERE ' . implode(' AND ', $where),
            array_merge([$status], $params)
        );

        return true;
    }

    public static function deleteThread(int $threadId, ?int $clientId = null): bool
    {
        self::ensureSchema();
        if (!Database::tableExists('client_contact_threads')) {
            return false;
        }

        if ($clientId !== null) {
            $thread = self::getThreadForClient($threadId, $clientId);
            if (!$thread) {
                return false;
            }
        } else {
            $thread = self::getThreadForAdmin($threadId);
            if (!$thread) {
                return false;
            }
        }

        Database::query('DELETE FROM client_contact_messages WHERE thread_id = ?', [$threadId]);
        Database::query('DELETE FROM client_contact_threads WHERE id = ?', [$threadId]);

        return true;
    }

    public static function isMessagingBlocked(int $clientId): bool
    {
        self::ensureSchema();
        if (!Database::tableExists('clients') || !Database::columnExists('clients', 'messaging_blocked')) {
            return false;
        }

        $row = Database::fetch(
            'SELECT messaging_blocked FROM clients WHERE id = ? LIMIT 1',
            [$clientId]
        );

        return (int) ($row['messaging_blocked'] ?? 0) === 1;
    }

    public static function setMessagingBlocked(int $clientId, bool $blocked): bool
    {
        self::ensureSchema();
        if (!Database::tableExists('clients') || !ClientService::getById($clientId)) {
            return false;
        }

        if (!Database::columnExists('clients', 'messaging_blocked')) {
            return false;
        }

        Database::query(
            'UPDATE clients SET messaging_blocked = ?, updated_at = NOW() WHERE id = ?',
            [$blocked ? 1 : 0, $clientId]
        );

        return true;
    }

    public static function updateMessageForAdmin(int $messageId, int $threadId, string $body): bool
    {
        self::ensureSchema();

        $body = trim($body);
        $thread = self::getThreadForAdmin($threadId);
        if ($body === '' || !$thread) {
            return false;
        }

        $message = self::getMessageInThread($messageId, $threadId);
        if (!$message || ($message['direction'] ?? '') !== 'outbound') {
            return false;
        }

        self::updateMessageBody($messageId, $body);
        self::notifyClientMessageEdited($threadId, $thread, $body);

        return true;
    }

    public static function updateMessageForClient(int $messageId, int $threadId, int $clientId, string $body): bool
    {
        self::ensureSchema();

        $body = trim($body);
        $thread = self::getThreadForClient($threadId, $clientId);
        if ($body === '' || !$thread) {
            return false;
        }

        $message = self::getMessageInThread($messageId, $threadId);
        if (!$message || ($message['direction'] ?? '') !== 'inbound') {
            return false;
        }

        self::updateMessageBody($messageId, $body);

        $client = self::getClientRecord($clientId);
        if ($client) {
            self::notifyAdminsMessageEdited(
                $threadId,
                $client,
                (string) ($thread['subject'] ?? 'Client message'),
                $body,
                self::companyIdForClient($client)
            );
        }

        return true;
    }

    /** @return list<array<string, mixed>> */
    private static function messagesForThread(int $threadId): array
    {
        return Database::fetchAll(
            'SELECT m.*, u.first_name AS admin_first_name, u.last_name AS admin_last_name
             FROM client_contact_messages m
             LEFT JOIN users u ON u.id = m.admin_user_id
             WHERE m.thread_id = ?
             ORDER BY m.id ASC',
            [$threadId]
        );
    }

    /** @return array<string, mixed>|null */
    private static function getMessageInThread(int $messageId, int $threadId): ?array
    {
        return Database::fetch(
            'SELECT * FROM client_contact_messages WHERE id = ? AND thread_id = ? LIMIT 1',
            [$messageId, $threadId]
        );
    }

    private static function updateMessageBody(int $messageId, string $body): void
    {
        if (Database::columnExists('client_contact_messages', 'edited_at')) {
            Database::query(
                'UPDATE client_contact_messages SET body = ?, edited_at = NOW() WHERE id = ?',
                [$body, $messageId]
            );
        } else {
            Database::query(
                'UPDATE client_contact_messages SET body = ? WHERE id = ?',
                [$body, $messageId]
            );
        }
    }

    /** @return array<string, mixed>|null */
    private static function getClientRecord(int $clientId): ?array
    {
        return Database::fetch('SELECT * FROM clients WHERE id = ? LIMIT 1', [$clientId]);
    }

    /** @param array<string, mixed> $client */
    private static function companyIdForClient(array $client): ?int
    {
        if (!TenantService::isEnabled()) {
            return null;
        }

        $companyId = (int) ($client['company_id'] ?? 0);

        return $companyId > 0 ? $companyId : TenantService::id();
    }

    /** @param list<string> $where @param list<mixed> $params */
    private static function appendThreadScope(array &$where, array &$params, string $alias = ''): void
    {
        if (!TenantService::isEnabled()) {
            return;
        }

        $col = ($alias !== '' ? rtrim($alias, '.') . '.' : '') . 'company_id';
        $where[] = "({$col} = ? OR {$col} IS NULL)";
        $params[] = TenantService::id();
    }

    /** @return array{0: list<string>, 1: list<mixed>} */
    private static function adminListFilters(?string $q, ?string $status): array
    {
        $where  = ['1=1'];
        $params = [];
        self::appendThreadScope($where, $params, 't');

        $status = trim((string) $status);
        if ($status === 'unread') {
            $where[] = 't.admin_unread = 1';
        } elseif ($status === 'read') {
            $where[] = 't.admin_unread = 0';
        } elseif ($status === 'open') {
            $where[] = "t.status = 'open'";
        } elseif ($status === 'closed') {
            $where[] = "t.status = 'closed'";
        }

        $q = trim((string) $q);
        if ($q !== '') {
            $where[] = '(t.subject LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR cl.company_name LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }

        return [$where, $params];
    }

    /** @param array<string, mixed> $client */
    private static function notifyAdminsNewMessage(
        int $threadId,
        array $client,
        string $subject,
        string $body,
        ?int $companyId,
        bool $isFollowUp = false
    ): void {
        try {
            $companyId = $companyId ?? 0;
            $name      = clientFullName($client) ?: 'Client';
            $title     = $isFollowUp ? 'Client replied to a message' : 'New client message';
            $message   = $name . ' — ' . $subject . ': ' . mb_substr($body, 0, 120);
            $link      = url('pages/message-view.php?id=' . $threadId);

            foreach (TenantService::adminNotifierUserIds($companyId) as $adminId) {
                createNotification(
                    $adminId,
                    $title,
                    $message,
                    'system',
                    $link,
                    $companyId > 0 ? $companyId : null
                );
            }
        } catch (Throwable $e) {
            error_log('ClientMessageService notify: ' . $e->getMessage());
        }
    }

    /** @param array<string, mixed> $thread */
    private static function notifyClientMessageEdited(int $threadId, array $thread, string $body): void
    {
        try {
            $clientUserId = (int) ($thread['client_user_id'] ?? 0);
            if ($clientUserId <= 0) {
                return;
            }

            $companyId   = (int) ($thread['company_id'] ?? 0);
            $companyName = companyBrandName(getCompanySettings());
            $subject     = (string) ($thread['subject'] ?? 'Your message');

            createNotification(
                $clientUserId,
                'Message updated — ' . $companyName,
                $subject . ': ' . mb_substr($body, 0, 120),
                'system',
                clientUrl('pages/contact.php?thread=' . $threadId),
                $companyId > 0 ? $companyId : null
            );
        } catch (Throwable $e) {
            error_log('ClientMessageService notify edit (client): ' . $e->getMessage());
        }
    }

    /** @param array<string, mixed> $client */
    private static function notifyAdminsMessageEdited(
        int $threadId,
        array $client,
        string $subject,
        string $body,
        ?int $companyId
    ): void {
        try {
            $companyId = $companyId ?? 0;
            $name      = clientFullName($client) ?: 'Client';
            $title     = 'Client edited a message';
            $message   = $name . ' — ' . $subject . ': ' . mb_substr($body, 0, 120);
            $link      = url('pages/message-view.php?id=' . $threadId);

            Database::query(
                'UPDATE client_contact_threads SET admin_unread = 1, updated_at = NOW() WHERE id = ?',
                [$threadId]
            );

            foreach (TenantService::adminNotifierUserIds($companyId) as $adminId) {
                createNotification(
                    $adminId,
                    $title,
                    $message,
                    'system',
                    $link,
                    $companyId > 0 ? $companyId : null
                );
            }
        } catch (Throwable $e) {
            error_log('ClientMessageService notify edit (admin): ' . $e->getMessage());
        }
    }
}
