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
        AssistantAppointmentSchedule::clear();
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

        if (self::messageOverridesActiveWizards($message)) {
            self::clearActiveWizards();
        }

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

        if ($hasUpload && AssistantRouter::shouldUploadToCase($message, true)) {
            $result = AssistantActions::handle('upload_case_document', $message, $uploads);
            $result['type'] = $result['type'] ?? 'text';

            return $result;
        }

        $caseInfo = AssistantCaseInfo::tryAnswer($message);
        if ($caseInfo !== null) {
            $caseInfo['type'] = $caseInfo['type'] ?? 'text';

            return $caseInfo;
        }

        $caseDocAnswer = AssistantDocuments::tryIngestCaseDocument($message);
        if ($caseDocAnswer !== null) {
            $caseDocAnswer['type'] = 'text';

            return $caseDocAnswer;
        }

        if (AssistantAppointmentSchedule::isActive()) {
            if (preg_match('/\b(cancel|stop|never mind|nevermind|abort)\b/i', strtolower($message))) {
                AssistantAppointmentSchedule::clear();

                return [
                    'content' => 'Appointment scheduling cancelled. Say **schedule appointment for…** when you are ready.',
                    'type' => 'text',
                ];
            }

            $scheduleResult = AssistantAppointmentSchedule::handle($message);
            $scheduleResult['type'] = $scheduleResult['type'] ?? 'text';

            return $scheduleResult;
        }

        if (AssistantClientCreate::isActive()) {
            if (preg_match('/\b(cancel|stop|never mind|nevermind|abort)\b/i', strtolower($message))) {
                AssistantClientCreate::clear();

                return [
                    'content' => 'New client setup cancelled. Say **create new client** or **create a case for [name]** when you are ready.',
                    'type' => 'text',
                ];
            }

            $wizardResult = AssistantClientCreate::handle($message);
            $wizardResult['type'] = $wizardResult['type'] ?? 'onboarding';

            return $wizardResult;
        }

        $draftEdit = AssistantDraftEdit::tryEditFromMessage($message);
        if ($draftEdit !== null) {
            $draftEdit['type'] = $draftEdit['type'] ?? 'text';

            return $draftEdit;
        }

        $sendReminderType = AssistantReminders::detectType($message);
        if ($sendReminderType !== null) {
            $reminderResult = AssistantReminders::handle($sendReminderType, $message);
            $reminderResult['type'] = $reminderResult['type'] ?? 'text';

            return $reminderResult;
        }

        $messageDraftType = AssistantMessageDrafts::detectType($message);
        if ($messageDraftType !== null) {
            $draftResult = AssistantMessageDrafts::handle($messageDraftType, $message);
            $draftResult['type'] = $draftResult['type'] ?? 'text';

            return $draftResult;
        }

        $directAnswer = AssistantKnowledge::tryAnswer($message);
        if ($directAnswer !== null) {
            $directAnswer['type'] = 'text';

            return $directAnswer;
        }

        if ($hasUpload && AssistantRouter::shouldUploadToCase($message, true)) {
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
            if ($hasUpload && AssistantRouter::shouldUploadToCase($message, true)) {
                $result = AssistantActions::handle('upload_case_document', $message, $uploads);
                $result['type'] = $result['type'] ?? 'text';

                return $result;
            }

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
            AssistantRouter::INTENT_MESSAGE_DRAFT => AssistantMessageDrafts::handle($route['topic'], $message),
            AssistantRouter::INTENT_SEND_REMINDER => AssistantReminders::handle($route['topic'], $message),
            AssistantRouter::INTENT_APPOINTMENT_SCHEDULE => AssistantAppointmentSchedule::handle($message),
            AssistantRouter::INTENT_CASE_INFO => AssistantCaseInfo::tryAnswer($message) ?? ['content' => 'Specify a case number or client name.'],
            default => $route['topic'] === 'intake_cancelled'
                ? ['content' => 'Client intake cancelled. You can say **start intake** again anytime, or ask for dashboard metrics, searches, or system actions.']
                : ($route['topic'] === 'client_create_cancelled'
                    ? ['content' => 'New client setup cancelled. Say **create new client** or **create a new case for me** to try again.']
                    : ($route['topic'] === 'appointment_schedule_cancelled'
                        ? ['content' => 'Appointment scheduling cancelled. Say **schedule appointment for…** when you are ready.']
                        : self::tryGeneralChat($message))),
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

    /** @param array<string, mixed> $draft */
    public static function replaceDraftInHistory(string $draftId, array $draft): void
    {
        $_SESSION['assistant_drafts'][$draftId] = $draft;

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

            $history[$i]['draft'] = $draft;
            $_SESSION[self::SESSION_KEY] = array_values($history);
            self::persistConversation();
            break;
        }
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

    /** @return array{enabled: bool, portal_enabled: bool, online: bool} */
    public static function status(): array
    {
        $config = require __DIR__ . '/../config/config.php';
        $enabled = (bool) ($config['assistant']['enabled'] ?? true);

        return [
            'enabled'        => $enabled,
            'portal_enabled' => true,
            'online'         => true,
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
        $scheduleClient = self::quickPromptScheduleClientName();

        return [
            ['icon' => 'bi-people', 'label' => 'Client count', 'prompt' => 'How many clients do we have?'],
            ['icon' => 'bi-briefcase', 'label' => 'Active cases', 'prompt' => 'List active cases'],
            ['icon' => 'bi-cash-stack', 'label' => 'Total revenue', 'prompt' => 'What is our total revenue?'],
            ['icon' => 'bi-calendar-event', 'label' => 'Appointments', 'prompt' => 'Show upcoming appointments'],
            ['icon' => 'bi-receipt', 'label' => 'Recent payments', 'prompt' => 'List recent payments'],
            ['icon' => 'bi-exclamation-circle', 'label' => 'Overdue invoices', 'prompt' => 'Show overdue invoices'],
            ['icon' => 'bi-bell', 'label' => 'Notifications', 'prompt' => 'How many unread notifications?'],
            ['icon' => 'bi-bar-chart-line', 'label' => 'Revenue by month', 'prompt' => 'Revenue by month'],
            ['icon' => 'bi-grid', 'label' => 'Dashboard overview', 'prompt' => 'Dashboard overview'],
            ['icon' => 'bi-search', 'label' => 'What can you do?', 'prompt' => 'What can you do?'],
            ['icon' => 'bi-journal-text', 'label' => 'Case summary', 'prompt' => 'Summarize case ' . self::quickPromptCaseReference()],
            ['icon' => 'bi-list-check', 'label' => 'Case checklist', 'prompt' => 'What\'s missing on case ' . self::quickPromptCaseReference()],
            ['icon' => 'bi-credit-card', 'label' => 'Record payment', 'prompt' => 'Record payment for the latest overdue invoice'],
            ['icon' => 'bi-file-earmark-plus', 'label' => 'Generate invoice', 'prompt' => 'Generate invoice for case ' . self::quickPromptCaseReference()],
            ['icon' => 'bi-person-plus', 'label' => 'Start intake', 'prompt' => 'Start client intake'],
            ['icon' => 'bi-calendar-plus', 'label' => 'Schedule visit', 'prompt' => 'Schedule appointment for ' . $scheduleClient . ' tomorrow at 2pm confirmed'],
            ['icon' => 'bi-book', 'label' => 'What is a jurat?', 'prompt' => 'What is a jurat?'],
            ['icon' => 'bi-file-earmark-check', 'label' => 'Docs to notarize', 'prompt' => 'Which documents require notarization?'],
            ['icon' => 'bi-person-badge', 'label' => 'Accepted ID', 'prompt' => 'What forms of identification are legally accepted?'],
            ['icon' => 'bi-truck', 'label' => 'Mobile notary', 'prompt' => 'How do I book a Mobile Notary in my area?'],
            ['icon' => 'bi-currency-pound', 'label' => 'Notary fees', 'prompt' => 'What are the standard State Notary Fees?'],
            ['icon' => 'bi-clipboard-check', 'label' => 'Prepare document', 'prompt' => 'How do I prepare my document before the appointment?'],
        ];
    }

    public static function clearActiveWizards(): void
    {
        AssistantAppointmentSchedule::clear();
        AssistantIntake::clear();
        AssistantClientCreate::clear();
    }

    public static function messageOverridesActiveWizards(string $message): bool
    {
        if (AssistantClientCreate::isActive() || AssistantAppointmentSchedule::isActive()) {
            return false;
        }

        $message = assistantNormalizeUserMessage($message);
        if ($message === '') {
            return false;
        }

        foreach (self::quickPrompts() as $prompt) {
            if (strcasecmp($message, (string) ($prompt['prompt'] ?? '')) === 0) {
                return true;
            }
        }

        if (AssistantRouter::matchDashboardTopic($message) !== null) {
            return true;
        }

        if (AssistantRouter::looksLikeSearch($message)) {
            return true;
        }

        if (AssistantKnowledge::looksLikeCapabilitiesQuery($message)) {
            return true;
        }

        if (AssistantPracticeFaq::matches($message)) {
            return true;
        }

        if (AssistantKnowledge::looksLikeDefinitionQuery($message)) {
            return true;
        }

        if (AssistantCalculations::looksLikeCalculationQuery($message)) {
            return true;
        }

        if (AssistantReminders::detectType($message) !== null) {
            return true;
        }

        if (AssistantMessageDrafts::detectType($message) !== null) {
            return true;
        }

        if (preg_match('/\b(start intake|client intake|begin onboarding)\b/i', $message)) {
            return true;
        }

        if (AssistantRouter::actionTopic($message) === 'schedule_appointment'
            && preg_match('/\b(schedule|book)\b.*\b(appointment|meeting)\b/i', $message)) {
            return true;
        }

        if (preg_match(
            '/\b(how many|how much|list|show|find|search|what is|what are|which|revenue|notifications?|overdue|unread|active cases?|total revenue|upcoming appointments?|recent payments?|dashboard overview|outstanding balance|summarize case|what.?s missing|record payment|generate invoice|what can you do)\b/i',
            $message
        )) {
            return true;
        }

        return false;
    }

    public static function exampleClientName(): string
    {
        return self::quickPromptScheduleClientName();
    }

    private static function quickPromptScheduleClientName(): string
    {
        $where = [];
        $params = [];
        TenantService::appendClientScope($where, $params, 'cl');

        $whereSql = $where === [] ? '' : (' WHERE ' . implode(' AND ', $where));
        $row = Database::fetch(
            'SELECT cl.first_name, cl.last_name FROM clients cl' . $whereSql . ' ORDER BY cl.updated_at DESC LIMIT 1',
            $params
        );

        $name = $row ? trim(trim((string) ($row['first_name'] ?? '')) . ' ' . trim((string) ($row['last_name'] ?? ''))) : '';

        return $name !== '' ? $name : 'Louis Macwell';
    }

    private static function quickPromptCaseReference(): string
    {
        $where = ["cs.status IN ('pending', 'in_progress', 'waiting_for_client')"];
        $params = [];
        appendCaseTenantScope($where, $params, 'cs', 'cl');
        appendAssignedCaseScope($where, $params, 'cs');

        $row = Database::fetch(
            'SELECT cs.case_number FROM cases cs
             JOIN clients cl ON cl.id = cs.client_id
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY cs.updated_at DESC
             LIMIT 1',
            $params
        );

        return $row ? (string) $row['case_number'] : 'CASE-' . date('Y') . '-0001';
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
            'content' => AssistantBuiltin::smartFallback($message),
            'type' => 'text',
        ];
    }
}
