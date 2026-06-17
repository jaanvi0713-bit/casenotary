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
            . ' notary admin portal. Answer only about this portal, notary practice, uploaded documents, and built-in glossary terms. '
            . 'If a question is unrelated, say you do not have that information. Be brief. Drafts need user confirmation.';
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
        AssistantDocuments::clearCachedDocumentText();
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
    public static function handle(
        string $message,
        ?array $upload = null,
        string $clientDocumentText = '',
        string $documentSource = '',
        array $uploads = [],
        array $clientDocumentItems = []
    ): array
    {
        $message = assistantNormalizeUserMessage($message);
        $clientDocumentText = assistantSanitizeUtf8(trim($clientDocumentText));
        $documentSource = trim($documentSource);

        if ($uploads === [] && $upload !== null && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $uploads = [$upload];
        }

        if ($clientDocumentItems === [] && $clientDocumentText !== '') {
            $clientDocumentItems = [[
                'name'   => 'Uploaded document',
                'text'   => $clientDocumentText,
                'source' => $documentSource,
            ]];
        }

        $hasUpload = $uploads !== [];
        $hasClientItems = $clientDocumentItems !== [];
        $isMultiDocumentRequest = count($uploads) > 1 || count($clientDocumentItems) > 1;

        $caseDocAnswer = AssistantDocuments::tryIngestCaseDocument($message);
        if ($caseDocAnswer !== null) {
            $caseDocAnswer['type'] = 'text';

            return $caseDocAnswer;
        }

        $directAnswer = AssistantKnowledge::tryAnswer($message);
        if ($directAnswer !== null) {
            $directAnswer['type'] = 'text';

            return $directAnswer;
        }

        if ($hasUpload && AssistantRouter::looksLikeCaseDocumentUpload($message)) {
            $result = AssistantActions::handle('upload_case_document', $message, $uploads);
            $result['type'] = $result['type'] ?? 'text';

            return $result;
        }

        if (!$hasUpload && trim($message) !== '') {
            $cachedItems = AssistantDocuments::cachedDocumentItems();
            $docText = $hasClientItems
                ? AssistantDocuments::cachedDocumentText()
                : ($clientDocumentText !== '' ? $clientDocumentText : AssistantDocuments::cachedDocumentText());

            if ($docText !== '' && AssistantDocuments::shouldAnswerFromDocument($message)) {
                if ($hasClientItems) {
                    AssistantDocuments::addDocumentItems($clientDocumentItems);
                    $cachedItems = AssistantDocuments::cachedDocumentItems();
                } elseif ($clientDocumentText !== '') {
                    AssistantDocuments::cacheDocumentText($clientDocumentText);
                    $cachedItems = AssistantDocuments::cachedDocumentItems();
                }

                if (AssistantDocuments::looksLikeSummarizeRequest($message)) {
                    $result = count($cachedItems) > 1
                        ? AssistantDocuments::summarizeMultipleDocuments($message, $cachedItems)
                        : AssistantDocuments::handleDocument($message, null, $docText, $documentSource);
                } elseif (count($cachedItems) > 1) {
                    $result = AssistantDocuments::answerMultiDocumentQuestion($message, $cachedItems);
                } else {
                    $result = AssistantDocuments::answerDocumentQuestion(
                        $message,
                        $cachedItems[0]['text'] ?? $docText
                    );
                }
                $result['type'] = 'text';

                return $result;
            }
        }

        if ($hasUpload || $hasClientItems) {
            if (!$isMultiDocumentRequest
                && count($uploads) + count($clientDocumentItems) === 1
                && trim($message) !== ''
                && !AssistantDocuments::shouldAnswerFromDocument($message)
                && !AssistantDocuments::looksLikeSummarizeRequest($message)
                && AssistantRouter::actionTopic($message) !== null) {
                $actionTopic = AssistantRouter::actionTopic($message);
                if ($actionTopic !== null && $actionTopic !== 'upload_case_document') {
                    $result = AssistantActions::handle($actionTopic, $message, $uploads);
                    $result['type'] = $result['type'] ?? 'text';

                    return $result;
                }
            }

            if ($isMultiDocumentRequest || count($uploads) + count($clientDocumentItems) > 1) {
                $result = AssistantDocuments::handleDocuments($message, $uploads, $clientDocumentItems);
            } elseif ($hasUpload) {
                $result = AssistantDocuments::handleDocument(
                    $message,
                    $uploads[0],
                    (string) ($clientDocumentItems[0]['text'] ?? ''),
                    (string) ($clientDocumentItems[0]['source'] ?? $documentSource)
                );
            } else {
                $item = $clientDocumentItems[0];
                $result = AssistantDocuments::handleDocument(
                    $message,
                    null,
                    (string) ($item['text'] ?? ''),
                    (string) ($item['source'] ?? $documentSource)
                );
            }
            $result['type'] = 'text';

            return $result;
        }

        if (self::shouldHandleAsDocument($message, false, $clientDocumentText)) {
            $result = AssistantDocuments::handleDocument($message, null, $clientDocumentText, $documentSource);
            $result['type'] = 'text';

            return $result;
        }

        if ($message === '') {
            throw new InvalidArgumentException('Message cannot be empty.');
        }

        $route = AssistantRouter::route($message);

        $result = match ($route['intent']) {
            AssistantRouter::INTENT_DASHBOARD => AssistantDashboard::handle($route['topic']),
            AssistantRouter::INTENT_ACTION => AssistantActions::handle($route['topic'], $message, $uploads),
            AssistantRouter::INTENT_SEARCH => AssistantSearch::handle($message),
            AssistantRouter::INTENT_DOCUMENT => [
                'content' => 'Attach a **PDF, HTML letter, or image** using the paperclip in the **same message**, then ask me to scan or extract details.',
            ],
            AssistantRouter::INTENT_INTAKE => AssistantIntake::handle($message),
            AssistantRouter::INTENT_CLIENT_CREATE => AssistantClientCreate::handle($message),
            AssistantRouter::INTENT_COMPLIANCE => AssistantCompliance::handle($message),
            AssistantRouter::INTENT_KNOWLEDGE => AssistantKnowledge::handle($route['topic'], $message),
            default => $route['topic'] === 'intake_cancelled'
                ? ['content' => 'Client intake cancelled. You can say **start intake** again anytime, or ask for dashboard metrics, searches, or system actions.']
                : ($route['topic'] === 'client_create_cancelled'
                    ? ['content' => 'New client setup cancelled. Say **create new client** or **create a new case for me** to try again.']
                    : self::tryGeneralChat($message)),
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
     * @param list<array{name?: string, kind?: string}> $attachments
     */
    public static function rememberExchange(string $userMessage, array $result, array $attachments = []): void
    {
        $history = self::history();
        $userTurn = [
            'role' => 'user',
            'content' => assistantSanitizeUtf8($userMessage),
        ];

        $normalizedAttachments = [];
        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }

            $name = trim((string) ($attachment['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $kind = trim((string) ($attachment['kind'] ?? ''));
            $normalizedAttachments[] = $kind !== ''
                ? ['name' => $name, 'kind' => $kind]
                : ['name' => $name];
        }

        if ($normalizedAttachments !== []) {
            $userTurn['attachments'] = $normalizedAttachments;
        }

        $history[] = $userTurn;

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
        $ollamaEnabled = OllamaService::isEnabled();

        return [
            'enabled'        => $ollamaEnabled,
            'portal_enabled' => true,
            'model'          => OllamaService::configuredModelName(),
            'online'         => true,
            'ollama_online'  => $ollamaEnabled ? OllamaService::isReachable() : false,
        ];
    }

    private static function shouldHandleAsDocument(string $message, bool $hasUpload, string $clientDocumentText): bool
    {
        if ($clientDocumentText !== '') {
            return AssistantDocuments::shouldAnswerFromDocument($message)
                || AssistantDocuments::looksLikeSummarizeRequest($message);
        }

        if (!$hasUpload) {
            return false;
        }

        if (trim($message) === '') {
            return true;
        }

        return AssistantRouter::looksLikeDocumentScan($message);
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
            ['icon' => 'bi-calendar-plus', 'label' => 'Schedule visit', 'prompt' => 'Schedule appointment for Louis Macwell tomorrow at 2pm'],
            ['icon' => 'bi-book', 'label' => 'What is a jurat?', 'prompt' => 'What is a jurat?'],
            ['icon' => 'bi-file-earmark-check', 'label' => 'Docs to notarize', 'prompt' => 'Which documents require notarization?'],
            ['icon' => 'bi-person-badge', 'label' => 'Accepted ID', 'prompt' => 'What forms of identification are legally accepted?'],
            ['icon' => 'bi-truck', 'label' => 'Mobile notary', 'prompt' => 'How do I book a Mobile Notary in my area?'],
            ['icon' => 'bi-currency-pound', 'label' => 'Notary fees', 'prompt' => 'What are the standard State Notary Fees?'],
            ['icon' => 'bi-clipboard-check', 'label' => 'Prepare document', 'prompt' => 'How do I prepare my document before the appointment?'],
        ];
    }

    /** @return array{content: string, type: string} */
    private static function tryGeneralChat(string $message): array
    {
        if (AssistantKnowledge::looksLikeCapabilitiesQuery($message)) {
            return [
                'content' => AssistantKnowledge::capabilitiesMessage(),
                'type' => 'text',
            ];
        }

        $lower = assistantNormalizeCasualText($message);
        if (preg_match('/^(?:hi|hello|hey|thanks|thank you|good morning|good afternoon)\b/', $lower)) {
            return [
                'content' => 'Hello! Ask _what can you do?_ for help with this portal, uploaded documents, or notary term definitions.',
                'type' => 'text',
            ];
        }

        return [
            'content' => AssistantKnowledge::outOfScopeMessage(),
            'type' => 'text',
        ];
    }

    /** @return array{content: string, type: string} */
    private static function generalChat(string $message): array
    {
        $history = array_slice(self::history(), -6);
        $chatHistory = [];
        foreach ($history as $turn) {
            $role = (string) ($turn['role'] ?? 'user');
            if ($role !== 'user' && $role !== 'assistant') {
                continue;
            }

            $content = trim((string) ($turn['content'] ?? ''));
            if ($content === '') {
                continue;
            }

            if (mb_strlen($content) > 600) {
                $content = mb_substr($content, 0, 600) . '…';
            }

            $chatHistory[] = ['role' => $role, 'content' => $content];
        }

        $messages = array_merge(
            [['role' => 'system', 'content' => self::systemPrompt()]],
            $chatHistory,
            [['role' => 'user', 'content' => $message]]
        );

        return [
            'content' => OllamaService::chat($messages),
            'type' => 'text',
        ];
    }
}
