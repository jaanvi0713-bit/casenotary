<?php

declare(strict_types=1);

class AssistantService
{
    private const SESSION_KEY = 'assistant_messages';
    private const CONVERSATION_KEY = 'assistant_conversation_id';
    private const SESSION_OWNER_KEY = 'assistant_session_user_id';
    private const MAX_HISTORY = 50;

    public static function resetSessionForLogin(): void
    {
        self::startNewChat();
    }

    public static function ensureSessionIntegrity(): void
    {
        $userId = Auth::id();
        if ($userId === null) {
            return;
        }

        $ownerId = isset($_SESSION[self::SESSION_OWNER_KEY]) ? (int) $_SESSION[self::SESSION_OWNER_KEY] : null;
        if ($ownerId !== null && $ownerId !== $userId) {
            self::startNewChat();

            return;
        }

        $conversationId = self::rawConversationId();
        if ($conversationId !== null && AssistantChatStore::isAvailable()) {
            if (AssistantChatStore::getForUser($userId, $conversationId) === null) {
                unset($_SESSION[self::CONVERSATION_KEY]);
            }
        }

        if ($ownerId === null && self::hasSessionData()) {
            if ($conversationId !== null) {
                self::bindSessionToUser($userId);
            } else {
                self::startNewChat();
            }
        }
    }

    private static function rawConversationId(): ?int
    {
        $id = $_SESSION[self::CONVERSATION_KEY] ?? null;

        return is_numeric($id) && (int) $id > 0 ? (int) $id : null;
    }

    private static function hasSessionData(): bool
    {
        $history = $_SESSION[self::SESSION_KEY] ?? [];

        return self::rawConversationId() !== null || (is_array($history) && $history !== []);
    }

    private static function bindSessionToUser(int $userId): void
    {
        $_SESSION[self::SESSION_OWNER_KEY] = $userId;
    }

    public static function systemPrompt(): string
    {
        $company = companyBrandName();

        return 'You are the AI assistant for the '
            . $company
            . ' notary admin portal. You help with dashboard metrics, searches, legal definitions, general calculations, client intake, and document review. '
            . 'Be concise. For data changes, tell the user you will prepare a draft that requires confirmation.';
    }

    /** @return list<array<string, mixed>> */
    public static function history(): array
    {
        self::ensureSessionIntegrity();

        $history = $_SESSION[self::SESSION_KEY] ?? [];

        return is_array($history) ? $history : [];
    }

    public static function conversationId(): ?int
    {
        self::ensureSessionIntegrity();

        return self::rawConversationId();
    }

    public static function startNewChat(): void
    {
        unset($_SESSION[self::SESSION_KEY], $_SESSION[self::CONVERSATION_KEY], $_SESSION[self::SESSION_OWNER_KEY]);
        AssistantActions::clearDrafts();
        AssistantIntake::clear();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function loadConversation(int $conversationId): array
    {
        $userId = Auth::id();
        if ($userId === null) {
            throw new RuntimeException('You must be logged in.');
        }

        $conversation = AssistantChatStore::getForUser($userId, $conversationId);
        if ($conversation === null) {
            throw new RuntimeException('Chat not found.');
        }

        $_SESSION[self::CONVERSATION_KEY] = $conversationId;
        $_SESSION[self::SESSION_KEY] = $conversation['messages'];
        self::bindSessionToUser($userId);
        AssistantIntake::clear();
        AssistantActions::rehydrateDraftsFromHistory($conversation['messages']);

        return $conversation['messages'];
    }

    /** @return list<array{id: int, title: string, preview: string, updated_at: string}> */
    public static function library(): array
    {
        $userId = Auth::id();

        return $userId ? AssistantChatStore::listForUser($userId) : [];
    }

    public static function renameConversation(int $conversationId, string $title): void
    {
        $userId = Auth::id();
        if ($userId === null) {
            throw new RuntimeException('You must be logged in.');
        }

        if (!AssistantChatStore::rename($userId, $conversationId, $title)) {
            throw new RuntimeException('Could not rename chat.');
        }
    }

    public static function deleteConversation(int $conversationId): void
    {
        $userId = Auth::id();
        if ($userId === null) {
            throw new RuntimeException('You must be logged in.');
        }

        if (!AssistantChatStore::delete($userId, $conversationId)) {
            throw new RuntimeException('Could not delete chat.');
        }

        if (self::conversationId() === $conversationId) {
            self::startNewChat();
        }
    }

    public static function clearHistory(): void
    {
        self::startNewChat();
    }

    /** @return list<array<string, mixed>> */
    public static function truncateHistory(int $fromIndex): array
    {
        self::ensureSessionIntegrity();

        $history = $_SESSION[self::SESSION_KEY] ?? [];
        if (!is_array($history) || $fromIndex < 0 || $fromIndex >= count($history)) {
            return is_array($history) ? $history : [];
        }

        if (($history[$fromIndex]['role'] ?? '') !== 'user') {
            throw new InvalidArgumentException('Can only edit user messages.');
        }

        $truncated = array_slice($history, 0, $fromIndex);
        if ($truncated === []) {
            self::startNewChat();

            return [];
        }

        $_SESSION[self::SESSION_KEY] = $truncated;

        $userId = Auth::id();
        if ($userId !== null) {
            self::bindSessionToUser($userId);
        }

        AssistantIntake::clear();
        AssistantActions::rehydrateDraftsFromHistory($truncated);

        self::persistConversation();

        return $truncated;
    }

    /**
     * @return array{content: string, type: string, draft?: array<string, mixed>, alerts?: list<array<string, string>>}
     */
    public static function handle(string $message, ?array $upload = null): array
    {
        $message = assistantNormalizeUserMessage($message);

        if ($upload !== null && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $result = AssistantDocuments::handleUpload($upload, $message);
            $result['type'] = 'text';

            return $result;
        }

        if ($message === '') {
            throw new InvalidArgumentException('Message cannot be empty.');
        }

        $route = AssistantRouter::route($message);

        $result = match ($route['intent']) {
            AssistantRouter::INTENT_DASHBOARD => AssistantDashboard::handle($route['topic']),
            AssistantRouter::INTENT_ACTION => AssistantActions::handle($route['topic'], $message),
            AssistantRouter::INTENT_SEARCH => AssistantSearch::handle($message),
            AssistantRouter::INTENT_DOCUMENT => [
                'content' => 'Attach a **PDF or image** using the paperclip, then ask me to scan or extract information.',
            ],
            AssistantRouter::INTENT_INTAKE => AssistantIntake::handle($message),
            AssistantRouter::INTENT_COMPLIANCE => AssistantCompliance::handle($message),
            AssistantRouter::INTENT_KNOWLEDGE => AssistantKnowledge::handle($route['topic'], $message),
            default => $route['topic'] === 'intake_cancelled'
                ? ['content' => 'Client intake cancelled. You can say **start intake** again anytime, or ask for dashboard metrics, searches, or system actions.']
                : (OllamaService::isReachable()
                    ? self::generalChat($message)
                    : [
                        'content' => 'The AI chat model is offline right now. You can still use **dashboard**, **search**, **calculations**, **appointments**, and **case actions** — try prompts like _How many clients do we have?_ or _List active cases._',
                        'type' => 'text',
                    ]),
        };

        $result['type'] = $result['type'] ?? 'text';

        return $result;
    }

    /** @return array{content: string, type: string} */
    public static function confirmDraft(string $draftId): array
    {
        $result = AssistantActions::confirm($draftId);
        self::markDraftConfirmed($draftId);

        return $result;
    }

    public static function markDraftConfirmed(string $draftId): void
    {
        $history = $_SESSION[self::SESSION_KEY] ?? [];
        if (!is_array($history)) {
            return;
        }

        for ($i = count($history) - 1; $i >= 0; $i--) {
            if (($history[$i]['role'] ?? '') !== 'assistant') {
                continue;
            }

            if ((string) ($history[$i]['draft']['id'] ?? '') !== $draftId) {
                continue;
            }

            unset($history[$i]['draft']);
            $_SESSION[self::SESSION_KEY] = array_values($history);
            self::persistConversation();
            break;
        }

        AssistantActions::forgetDraft($draftId);
    }

    /**
     * @param array{content: string, type?: string, draft?: array<string, mixed>, alerts?: list<array<string, string>>} $result
     */
    public static function rememberExchange(string $userMessage, array $result): void
    {
        $history = self::history();
        $history[] = ['role' => 'user', 'content' => assistantSanitizeUtf8($userMessage)];

        $assistantTurn = [
            'role' => 'assistant',
            'content' => assistantSanitizeUtf8((string) ($result['content'] ?? '')),
            'type' => (string) ($result['type'] ?? 'text'),
        ];

        if (!empty($result['draft']) && is_array($result['draft'])) {
            $assistantTurn['draft'] = $result['draft'];
        }
        if (!empty($result['alerts']) && is_array($result['alerts'])) {
            $assistantTurn['alerts'] = $result['alerts'];
        }

        $history[] = $assistantTurn;
        $_SESSION[self::SESSION_KEY] = array_slice($history, -self::MAX_HISTORY);

        $userId = Auth::id();
        if ($userId !== null) {
            self::bindSessionToUser($userId);
        }

        try {
            self::persistConversation();
        } catch (Throwable $e) {
            error_log('Assistant persist: ' . $e->getMessage());
        }
    }

    private static function persistConversation(): void
    {
        if (!AssistantChatStore::isAvailable()) {
            return;
        }

        $userId = Auth::id();
        if ($userId === null) {
            return;
        }

        self::ensureSessionIntegrity();

        $messages = $_SESSION[self::SESSION_KEY] ?? [];
        if (!is_array($messages) || $messages === []) {
            return;
        }

        $conversationId = isset($_SESSION[self::CONVERSATION_KEY]) && is_numeric($_SESSION[self::CONVERSATION_KEY])
            ? (int) $_SESSION[self::CONVERSATION_KEY]
            : null;

        $title = AssistantChatStore::titleFromMessages($messages);

        if ($conversationId === null || $conversationId <= 0) {
            $conversationId = AssistantChatStore::create($userId, $title);
            $_SESSION[self::CONVERSATION_KEY] = $conversationId;
            self::bindSessionToUser($userId);
        }

        $updateTitle = count($messages) <= 2 ? $title : null;

        if (!AssistantChatStore::save($userId, $conversationId, $messages, $updateTitle)) {
            unset($_SESSION[self::CONVERSATION_KEY]);
            $conversationId = AssistantChatStore::create($userId, $title);
            $_SESSION[self::CONVERSATION_KEY] = $conversationId;
            AssistantChatStore::save($userId, $conversationId, $messages, $updateTitle);
        }
    }

    /** @return array{enabled: bool, model: string, online: bool, ollama_online: bool} */
    public static function status(): array
    {
        $ollamaOnline = OllamaService::isReachable();

        return [
            'enabled'        => OllamaService::isEnabled(),
            'model'          => OllamaService::modelName(),
            'online'         => true,
            'ollama_online'  => $ollamaOnline,
        ];
    }

    /** @return list<array{label: string, prompt: string, icon: string}> */
    public static function quickPrompts(): array
    {
        return [
            ['icon' => 'bi-people', 'label' => 'Client count', 'prompt' => 'How many clients do we have?'],
            ['icon' => 'bi-briefcase', 'label' => 'Active cases', 'prompt' => 'List active cases'],
            ['icon' => 'bi-cash-stack', 'label' => 'Total revenue', 'prompt' => 'What is our total revenue?'],
            ['icon' => 'bi-calendar-event', 'label' => 'Appointments', 'prompt' => 'Show upcoming appointments'],
            ['icon' => 'bi-receipt', 'label' => 'Recent payments', 'prompt' => 'List recent payments'],
            ['icon' => 'bi-exclamation-circle', 'label' => 'Overdue invoices', 'prompt' => 'Show overdue invoices'],
            ['icon' => 'bi-bell', 'label' => 'Notifications', 'prompt' => 'How many unread notifications?'],
            ['icon' => 'bi-bar-chart-line', 'label' => 'Revenue by month', 'prompt' => 'Revenue by month'],
            ['icon' => 'bi-person-plus', 'label' => 'Start intake', 'prompt' => 'Start client intake'],
            ['icon' => 'bi-book', 'label' => 'What is a jurat?', 'prompt' => 'What is a jurat?'],
        ];
    }

    /** @return array{content: string, type: string} */
    private static function generalChat(string $message): array
    {
        $history = self::history();
        $messages = array_merge(
            [['role' => 'system', 'content' => self::systemPrompt()]],
            array_map(static fn (array $turn): array => [
                'role' => (string) ($turn['role'] ?? 'user'),
                'content' => (string) ($turn['content'] ?? ''),
            ], $history),
            [['role' => 'user', 'content' => $message]]
        );

        return [
            'content' => OllamaService::chat($messages),
            'type' => 'text',
        ];
    }
}
