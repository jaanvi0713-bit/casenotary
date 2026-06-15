<?php

declare(strict_types=1);

class ChatbotService
{
    private const FALLBACK_MARKER = '__CHATBOT_FALLBACK__';

    public static function reply(string $message): string
    {
        $reply = self::resolveReply($message);
        chatbotRememberTurn($message, $reply);

        return $reply;
    }

    public static function regenerate(string $message): string
    {
        $baseReply = self::resolveReply($message);
        $variant = self::makeRegeneratedVariant($baseReply);

        chatbotRememberTurn($message, $variant);

        return $variant;
    }

    private static function makeRegeneratedVariant(string $reply): string
    {
        $counter = (int) ($_SESSION['chatbot_regen_counter'] ?? 0) + 1;
        $_SESSION['chatbot_regen_counter'] = $counter;

        $trimmed = trim($reply);
        if ($trimmed === '') {
            return $reply;
        }

        // Alternate response style to make regenerate materially different.
        if ($counter % 2 === 1) {
            return self::regenerateConcise($trimmed);
        }

        return self::regenerateDetailed($trimmed);
    }

    private static function regenerateConcise(string $reply): string
    {
        $lines = preg_split('/\R+/', $reply) ?: [];
        $picked = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (str_starts_with($line, '•') || str_starts_with($line, '1.') || str_starts_with($line, '2.')) {
                $picked[] = $line;
            } elseif (count($picked) < 2) {
                $picked[] = $line;
            }

            if (count($picked) >= 5) {
                break;
            }
        }

        if ($picked === []) {
            $picked[] = mb_strimwidth($reply, 0, 420, '...');
        }

        return "**Alternative (concise):**\n\n" . implode("\n", $picked);
    }

    private static function regenerateDetailed(string $reply): string
    {
        return "**Alternative:**\n\n" . $reply;
    }

    private static function resolveReply(string $message): string
    {
        $normalized = strtolower(trim($message));

        if ($normalized === '' || preg_match('/^(hi|hello|hey|help)$/', $normalized)) {
            return generateChatbotReply($message);
        }

        $actionFlow = chatbotTryActionFlow($message);
        if ($actionFlow !== null) {
            return $actionFlow;
        }

        $draftReply = chatbotReplyForDraftRequest($message);
        if ($draftReply !== null) {
            return $draftReply;
        }

        $followUp = chatbotTryFollowUpReply($message);
        if ($followUp !== null) {
            return $followUp;
        }

        $companyKnowledge = chatbotReplyFromCompanyKnowledge($message);
        if ($companyKnowledge !== null) {
            return $companyKnowledge;
        }

        $briefing = chatbotReplyForMorningBriefing($message);
        if ($briefing !== null) {
            return $briefing;
        }

        $dashboardSummary = chatbotReplyForDashboardSummary($message);
        if ($dashboardSummary !== null) {
            return $dashboardSummary;
        }

        $caseContext = chatbotReplyForCaseContext($message);
        if ($caseContext !== null) {
            return $caseContext;
        }

        $reports = chatbotReplyForReports($message);
        if ($reports !== null) {
            return $reports;
        }

        $docSearch = chatbotReplyForDocumentSearch($message);
        if ($docSearch !== null) {
            return $docSearch;
        }

        $meta = chatbotReplyForMetaQuestions($message);
        if ($meta !== null) {
            return $meta;
        }

        $dateFiltered = chatbotReplyForDateFilteredQueries($message);
        if ($dateFiltered !== null) {
            return $dateFiltered;
        }

        $calculation = chatbotReplyForCalculations($message);
        if ($calculation !== null) {
            return $calculation;
        }

        $portalClient = chatbotReplyForPortalClientLookup($message);
        if ($portalClient !== null) {
            return $portalClient;
        }

        $focused = chatbotReplyForFocusedQuestion($message);
        if ($focused !== null) {
            return $focused;
        }

        if (chatbotIsPortalSystemQuestion($message) || chatbotIsProceduralQuery($message)) {
            $portalSystem = chatbotReplyForPortalSystemQuestion($message);
            if ($portalSystem !== null) {
                return $portalSystem;
            }
        }

        if (!chatbotIsSystemDataQuestion($message) && !chatbotIsPortalSystemQuestion($message)) {
            $universal = self::replyForUniversalKnowledge($message);
            if ($universal !== null) {
                return $universal;
            }
        }

        if (chatbotIsSystemDataQuestion($message) || chatbotIsPortalSystemQuestion($message)) {
            $portalData = chatbotReplyForPortalDataQuestion($message);
            if ($portalData !== null) {
                return $portalData;
            }

            $systemInsight = chatbotReplyForSystemInsights($message);
            if ($systemInsight !== null) {
                return $systemInsight;
            }

            $appointments = chatbotReplyForAppointmentQueries($message);
            if ($appointments !== null) {
                return $appointments;
            }

            $cases = chatbotReplyForCaseQueries($message);
            if ($cases !== null) {
                return $cases;
            }

            $notifications = chatbotReplyForNotificationQueries($message);
            if ($notifications !== null) {
                return $notifications;
            }

            $followUp = chatbotTryFollowUpReply($message);
            if ($followUp !== null) {
                return $followUp;
            }

            if (chatbotLooksLikePersonNameSearch($message)) {
                $entityReply = chatbotReplyForEntityLookup($message);
                if ($entityReply !== null) {
                    return $entityReply;
                }
            }

            $dataReply = generateChatbotReply($message);
            if (!self::isGenericFallback($dataReply)) {
                return $dataReply;
            }
        }

        $adviceOrGeneral = chatbotReplyForAdviceAndGeneral($message);
        if ($adviceOrGeneral !== null) {
            return $adviceOrGeneral;
        }

        $appointments = chatbotReplyForAppointmentQueries($message);
        if ($appointments !== null) {
            return $appointments;
        }

        $cases = chatbotReplyForCaseQueries($message);
        if ($cases !== null) {
            return $cases;
        }

        $notifications = chatbotReplyForNotificationQueries($message);
        if ($notifications !== null) {
            return $notifications;
        }

        if (chatbotIsFollowUpList($normalized) && !empty($_SESSION['chatbot_last_topic'])) {
            return generateChatbotReply($message);
        }

        $contextual = chatbotReplyForContextualFollowUp($message);
        if ($contextual !== null) {
            return $contextual;
        }

        $namedClient = chatbotReplyForNamedClientFocus($message);
        if ($namedClient !== null) {
            return $namedClient;
        }

        if (!chatbotIsSystemDataQuestion($message) && !chatbotIsPortalSystemQuestion($message)) {
            $universal = self::replyForUniversalKnowledge($message);
            if ($universal !== null) {
                return $universal;
            }
        }

        if (chatbotIsPortalSystemQuestion($message) || chatbotIsProceduralQuery($message)) {
            $portalSystem = chatbotReplyForPortalSystemQuestion($message);
            if ($portalSystem !== null) {
                return $portalSystem;
            }
        }

        if (chatbotIsAdviceOrHowToQuery($message)
            || (chatbotIsGeneralQuestion($message) && !chatbotIsSystemDataQuestion($message))) {
            $open = chatbotReplyForOpenEndedLocal($message);
            if ($open !== null) {
                return $open;
            }
            $general = chatbotReplyForGeneralKnowledge($message);
            if ($general !== null) {
                return $general;
            }
        }

        if (chatbotIsSystemDataQuestion($message)) {
            $systemInsight = chatbotReplyForSystemInsights($message);
            if ($systemInsight !== null) {
                return $systemInsight;
            }
        }

        if (chatbotLooksLikePersonNameSearch($message)) {
            $entityReply = chatbotReplyForEntityLookup($message);
            if ($entityReply !== null) {
                return $entityReply;
            }
        }

        if (chatbotIsPortalSystemQuestion($message) || chatbotIsProceduralQuery($message)) {
            $portalSystem = chatbotReplyForPortalSystemQuestion($message);
            if ($portalSystem !== null) {
                return $portalSystem;
            }
        }

        if (!chatbotIsSystemDataQuestion($message) && !chatbotIsPortalSystemQuestion($message)) {
            $universal = self::replyForUniversalKnowledge($message);
            if ($universal !== null) {
                return $universal;
            }
        } elseif (($portalData = chatbotReplyForPortalDataQuestion($message)) !== null) {
            return $portalData;
        }

        $systemInsight = chatbotReplyForSystemInsights($message);
        if ($systemInsight !== null) {
            return $systemInsight;
        }

        if (!chatbotWantsCount($normalized) && !chatbotWantsList($normalized)) {
            if (chatbotIsPortalSystemQuestion($message) || chatbotIsPortalProceduralQuery($message) || chatbotIsProceduralQuery($message)) {
                $portalSystem = chatbotReplyForPortalSystemQuestion($message);
                if ($portalSystem !== null) {
                    return $portalSystem;
                }
            }

            if (chatbotIsGeneralQuestion($message) && !chatbotIsSystemDataQuestion($message) && !chatbotIsPortalSystemQuestion($message)) {
                $general = chatbotReplyForGeneralKnowledge($message);
                if ($general !== null) {
                    return $general;
                }
            }
        }

        $reply = generateChatbotReply($message);
        if (!self::isGenericFallback($reply)) {
            return $reply;
        }

        $general = chatbotReplyForGeneralKnowledge($message);
        if ($general !== null) {
            return $general;
        }

        $procedural = self::replyForProcedural($message);
        if ($procedural !== null && !chatbotWantsCount($normalized) && !chatbotWantsList($normalized)) {
            return $procedural;
        }

        $knowledge = self::replyFromKnowledgeBase($message);
        if ($knowledge !== null) {
            return $knowledge;
        }

        $openEnded = chatbotReplyForOpenEndedLocal($message);
        if ($openEnded !== null) {
            return $openEnded;
        }

        return self::replySmartFallback($message);
    }

    public static function hasAiKey(): bool
    {
        return self::hasOptionalLlm();
    }

    public static function hasOptionalLlm(): bool
    {
        $config = self::aiConfig();

        if (empty($config['enabled'])) {
            return false;
        }

        $provider = strtolower((string) ($config['provider'] ?? 'openai'));

        if ($provider === 'ollama') {
            return true;
        }

        return trim($config['api_key'] ?? '') !== '';
    }

    /**
     * Broad world knowledge: built-in notary topics → Wikipedia → optional LLM → thoughtful fallback.
     */
    public static function replyForUniversalKnowledge(string $message): ?string
    {
        $offline = chatbotReplyForUniversalKnowledgeOffline($message);
        if ($offline !== null) {
            return $offline;
        }

        if (self::hasOptionalLlm() && chatbotIsGeneralKnowledgeQuestion($message)) {
            $llm = self::replyViaLlm($message);
            if ($llm !== null && trim($llm) !== '') {
                return $llm;
            }
        }

        if (!chatbotIsGeneralKnowledgeQuestion($message)) {
            return null;
        }

        $subject = chatbotExtractDefinitionTerm($message);
        if ($subject === '') {
            $subject = chatbotExtractQuestionSubject($message);
        }

        if (str_word_count(strtolower($message)) <= 6 || chatbotLooksLikeKnowledgeQuery($message)) {
            return chatbotTemplateDefinition($subject, $message);
        }

        return chatbotTemplateOpenAnswer($subject, $message);
    }

    /**
     * @param array<string, mixed>|null $filesRaw $_FILES slice or normalized list
     */
    public static function replyWithAttachments(string $message, ?array $filesRaw): string
    {
        $attachments = self::storeUploadedAttachments($filesRaw);

        if ($attachments === []) {
            return self::reply($message);
        }

        $message = trim($message);
        if ($message === '') {
            $message = 'Please analyze the attached file(s) and answer any questions about them in the context of this notary management system.';
        }

        $textContext = self::buildAttachmentTextContext($attachments);
        $hasImages   = self::attachmentsHaveImages($attachments);

        $enrichedMessage = $message;
        if ($textContext !== '') {
            $enrichedMessage .= "\n\n---\n**Attached files:**\n" . $textContext;
        }

        $reply = self::reply($enrichedMessage);
        if (!self::isGenericFallback($reply) && !self::isEntityNotFoundReply($reply)) {
            self::cleanupAttachments($attachments);
            return self::prependAttachmentAck($reply, $attachments);
        }

        if (self::hasOptionalLlm()) {
            $llmReply = self::replyViaLlmWithAttachments($message, $attachments, $textContext);
            if ($llmReply !== null) {
                self::cleanupAttachments($attachments);
                return $llmReply;
            }
        }

        self::cleanupAttachments($attachments);

        return self::replyLocalAttachmentFallback($message, $attachments, $textContext, $hasImages);
    }

    public static function isEntityNotFoundReply(string $reply): bool
    {
        return str_contains($reply, 'I couldn\'t find')
            || str_contains($reply, 'I could not find anything matching');
    }

    public static function genericFallbackMessage(): string
    {
        return self::FALLBACK_MARKER;
    }

    public static function isGenericFallback(string $reply): bool
    {
        return $reply === self::FALLBACK_MARKER
            || str_contains($reply, "I'm not sure about that")
            || str_contains($reply, 'Try a **client name**');
    }

    public static function replyForProcedural(string $message): ?string
    {
        $focused = chatbotReplyForFocusedQuestion($message);
        if ($focused !== null) {
            return $focused;
        }

        $normalized = strtolower(trim($message));
        $lastTopic  = $_SESSION['chatbot_last_topic'] ?? null;

        $isProcedural = (bool) preg_match(
            '/\b(how to|how do i|how should|what should|what do i|next step|proceed|instructions?|waiting for client|prepare|workflow|process|update status|send to client|notify client|what happens|what now|help me with|guide me|walk me through|can i|should i)\b/',
            $normalized
        );

        if (!$isProcedural) {
            return null;
        }

        if (is_string($lastTopic) && preg_match('/^case_(\d+)$/', $lastTopic, $matches)
            && preg_match('/\binstructions?\b|proceed|next step|what should|what do i|what now/', $normalized)) {
            return self::proceduralReplyForCase((int) $matches[1], $normalized);
        }

        if (is_string($lastTopic) && preg_match('/^client_(\d+)$/', $lastTopic, $matches)
            && preg_match('/\binstructions?\b|proceed|next step|what should|what do i|what now/', $normalized)) {
            return self::proceduralReplyForClient((int) $matches[1], $normalized);
        }

        if (!preg_match('/\b(workflow|overview|all about|everything|in general|explain the)\b/', $normalized)) {
            return null;
        }

        if (preg_match('/\b(appointment|schedule|calendar|meeting|book)\b/', $normalized)) {
            return self::generalAppointmentGuide();
        }

        if (preg_match('/\b(payment|invoice|quotation|quote|client letter|receipt)\b/', $normalized)) {
            return self::generalPaymentGuide();
        }

        if (preg_match('/\b(case|cases)\b/', $normalized)) {
            return self::generalCaseGuide();
        }

        return self::generalAdminGuide();
    }

    public static function replyFromKnowledgeBase(string $message): ?string
    {
        $focused = chatbotReplyForFocusedQuestion($message);
        if ($focused !== null) {
            return $focused;
        }

        $normalized = strtolower(trim($message));

        $topics = [
            '/\b(notary|notaris|notarization|what is a notary)\b/' => self::generalNotaryKnowledge(),
            '/\b(create client|add client|new client|register client)\b/' => "**Adding a client:**\n\nGo to **Clients → Add Client**, enter their details, and optionally send portal login credentials. Clients can then access cases, documents, and appointments in the client portal.",
            '/\b(client portal|portal login|client login)\b/' => "**Client portal:**\n\nClients sign in at the client portal URL. They can view cases, documents, invoices, appointments, and contact your office. You can resend login details from the client profile.",
            '/\b(create case|new case|add case|open case)\b/' => "**Creating a case:**\n\nGo to **Cases → New Case**, select the client, enter service details, fees, and **Instructions for Client** (what they should prepare). You can email a quotation and client letter on creation.",
            '/\b(client letter|quotation|quote pdf|send quote)\b/' => "**Quotations & client letters:**\n\nWhen creating or viewing a case, use **Generate Quotation** or the **Client Letter** tab. Check **Email to client** when creating a case to send documents automatically.",
            '/\b(document|upload|pdf|file)\b/' => chatbotIsDocumentDataQuery($normalized)
                ? null
                : "**Documents:**\n\nOpen a case in the admin portal to upload, generate, or email PDFs (quotations, client letters, receipts). Clients see shared documents on their case view.",
            '/\b(what are notifications|how do notifications work|about notifications)\b/' => "**Notifications:**\n\nAdmins and clients receive in-app alerts for appointments, cases, and payments. Ask **“how many notifications”** or **“list notifications”** for your current counts and recent items.",
            '/\b(settings|company|smtp|email config|office hours|branding)\b/' => "**Settings:**\n\nGo to **Settings** to update company name, colours, office contact details, SMTP email, and calendar integrations.",
            '/\b(requested appointment|appointment request|client request)\b/' => "**Client appointment requests:**\n\nClients can **Request Appointment** from their portal. Requests appear with status **Requested** in **Appointments**. Edit the request, set status to **Scheduled** or **Confirmed**, and save to approve.",
            '/\b(priority)\b/' => "**Priority** is visible to admins on cases only — it is hidden from the client portal case view.",
            '/\b(contact|support|office email|phone)\b/' => "**Contact:**\n\nOffice email and phone are configured in **Settings**. Clients can message you via **Contact** in their portal.",
            '/\b(revenue|profit|money|earnings|income|paid)\b/' => null,
            '/\b(thank|thanks|ty)\b/' => "You're welcome! Ask me anything else about clients, cases, payments, appointments, or how to use the admin portal.",
            '/\b(bye|goodbye|see you)\b/' => 'Goodbye! I\'m here whenever you need help with your notary business.',
        ];

        foreach ($topics as $pattern => $answer) {
            if (!preg_match($pattern, $normalized)) {
                continue;
            }

            if ($answer === null) {
                continue;
            }

            return $answer;
        }

        return null;
    }

    public static function replyViaLlm(string $message, array $attachments = [], string $textContext = ''): ?string
    {
        if ($attachments !== []) {
            return self::replyViaLlmWithAttachments($message, $attachments, $textContext);
        }

        $config = self::aiConfig();
        if (empty($config['enabled'])) {
            return null;
        }

        $provider = strtolower((string) ($config['provider'] ?? 'openai'));
        $systemPrompt = self::buildSystemPrompt();
        $messages = self::buildLlmMessages($systemPrompt, $message);

        if ($provider === 'ollama') {
            return self::callOllamaChat($messages, $config);
        }

        $apiKey = trim($config['api_key'] ?? '');
        if ($apiKey === '') {
            return null;
        }

        $payload = [
            'model'       => $config['model'] ?? 'gpt-4o-mini',
            'messages'    => $messages,
            'max_tokens'  => 900,
            'temperature' => 0.65,
        ];

        return self::callOpenAiChat($payload, $apiKey, $config);
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    private static function buildLlmMessages(string $systemPrompt, string $message): array
    {
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        $history = $_SESSION['chatbot_history'] ?? [];
        if (is_array($history)) {
            foreach (array_slice($history, -6) as $turn) {
                if (!empty($turn['user'])) {
                    $messages[] = ['role' => 'user', 'content' => (string) $turn['user']];
                }
                if (!empty($turn['bot'])) {
                    $messages[] = ['role' => 'assistant', 'content' => (string) $turn['bot']];
                }
            }
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        return $messages;
    }

    /**
     * @param list<array{role: string, content: string}> $messages
     * @param array<string, mixed> $config
     */
    private static function callOllamaChat(array $messages, array $config): ?string
    {
        $baseUrl = rtrim((string) ($config['ollama_url'] ?? 'http://127.0.0.1:11434'), '/');
        $model   = (string) ($config['ollama_model'] ?? 'llama3.2');

        $ch = curl_init($baseUrl . '/api/chat');
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS     => json_encode([
                'model'    => $model,
                'messages' => $messages,
                'stream'   => false,
            ]),
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_CONNECTTIMEOUT => 3,
        ]);

        $response = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($response) || $response === '' || $status < 200 || $status >= 300) {
            return null;
        }

        $decoded = json_decode($response, true);
        $content = trim((string) ($decoded['message']['content'] ?? ''));

        return $content !== '' ? $content : null;
    }

    public static function replyViaLlmWithAttachments(string $message, array $attachments, string $textContext = ''): ?string
    {
        $config = self::aiConfig();
        if (empty($config['enabled'])) {
            return null;
        }

        $systemPrompt = self::buildSystemPrompt();
        $userContent  = self::buildLlmUserContent($message, $attachments, $textContext);
        $provider     = strtolower((string) ($config['provider'] ?? 'openai'));

        if ($provider === 'ollama') {
            $messages = self::buildLlmMessages($systemPrompt, $userContent);

            return self::callOllamaChat($messages, $config);
        }

        $apiKey = trim($config['api_key'] ?? '');
        if ($apiKey === '') {
            return null;
        }

        $payload = [
            'model'       => $config['model'] ?? 'gpt-4o-mini',
            'messages'    => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userContent],
            ],
            'max_tokens'  => 1200,
            'temperature' => 0.65,
        ];

        return self::callOpenAiChat($payload, $apiKey, $config);
    }

    /**
     * @param array<string, mixed>|null $filesRaw
     * @return list<array{path: string, name: string, mime: string, ext: string, kind: string, text: ?string}>
     */
    private static function storeUploadedAttachments(?array $filesRaw): array
    {
        if ($filesRaw === null || $filesRaw === []) {
            return [];
        }

        $appConfig = require __DIR__ . '/../config/config.php';
        $chatCfg   = $appConfig['chatbot'] ?? [];
        $maxFiles  = (int) ($chatCfg['max_attachments'] ?? 5);
        $maxSize   = (int) ($chatCfg['max_size'] ?? 10 * 1024 * 1024);
        $allowed   = array_map('strtolower', $chatCfg['allowed_types'] ?? ['jpg', 'jpeg', 'png', 'pdf', 'txt']);
        $uploadDir = rtrim((string) ($chatCfg['upload_path'] ?? __DIR__ . '/../uploads/chatbot/'), '/\\') . DIRECTORY_SEPARATOR;

        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            return [];
        }

        $normalized = self::normalizeUploadedFiles($filesRaw);
        $stored     = [];

        foreach (array_slice($normalized, 0, $maxFiles) as $file) {
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }

            $originalName = (string) ($file['name'] ?? 'file');
            $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            if ($ext === '' || !in_array($ext, $allowed, true)) {
                continue;
            }

            $size = (int) ($file['size'] ?? 0);
            if ($size <= 0 || $size > $maxSize) {
                continue;
            }

            $tmp = (string) ($file['tmp_name'] ?? '');
            if ($tmp === '' || !is_uploaded_file($tmp)) {
                continue;
            }

            $safeName = bin2hex(random_bytes(8)) . '.' . $ext;
            $dest     = $uploadDir . $safeName;
            if (!move_uploaded_file($tmp, $dest)) {
                continue;
            }

            $mime = mime_content_type($dest) ?: (string) ($file['type'] ?? 'application/octet-stream');
            $kind = self::attachmentKind($ext, $mime);

            $stored[] = [
                'path' => $dest,
                'name' => $originalName,
                'mime' => $mime,
                'ext'  => $ext,
                'kind' => $kind,
                'text' => self::extractAttachmentText($dest, $ext, $kind),
            ];
        }

        return $stored;
    }

    /**
     * @param array<string, mixed> $filesRaw
     * @return list<array{name: string, type: string, tmp_name: string, error: int, size: int}>
     */
    private static function normalizeUploadedFiles(array $filesRaw): array
    {
        if (isset($filesRaw['name']) && is_array($filesRaw['name'])) {
            $out = [];
            foreach ($filesRaw['name'] as $i => $name) {
                $out[] = [
                    'name'     => $name,
                    'type'     => $filesRaw['type'][$i] ?? '',
                    'tmp_name' => $filesRaw['tmp_name'][$i] ?? '',
                    'error'    => $filesRaw['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size'     => $filesRaw['size'][$i] ?? 0,
                ];
            }

            return $out;
        }

        if (isset($filesRaw['attachments'])) {
            return self::normalizeUploadedFiles($filesRaw['attachments']);
        }

        if (isset($filesRaw['name'], $filesRaw['tmp_name'])) {
            return [$filesRaw];
        }

        $out = [];
        foreach ($filesRaw as $file) {
            if (!is_array($file)) {
                continue;
            }
            if (isset($file['name']) && is_array($file['name'])) {
                $out = array_merge($out, self::normalizeUploadedFiles($file));
            } elseif (isset($file['tmp_name'])) {
                $out[] = $file;
            }
        }

        return $out;
    }

    private static function attachmentKind(string $ext, string $mime): string
    {
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) || str_starts_with($mime, 'image/')) {
            return 'image';
        }

        if (in_array($ext, ['txt', 'csv', 'md', 'json', 'log'], true) || str_starts_with($mime, 'text/')) {
            return 'text';
        }

        return 'document';
    }

    private static function extractAttachmentText(string $path, string $ext, string $kind): ?string
    {
        if ($kind !== 'text') {
            return null;
        }

        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return null;
        }

        if (!mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-8');
        }

        return mb_strimwidth(trim($raw), 0, 12000, "\n…(truncated)");
    }

    /**
     * @param list<array{path: string, name: string, mime: string, ext: string, kind: string, text: ?string}> $attachments
     */
    private static function buildAttachmentTextContext(array $attachments): string
    {
        $parts = [];

        foreach ($attachments as $attachment) {
            if (($attachment['text'] ?? null) !== null && $attachment['text'] !== '') {
                $parts[] = '**' . $attachment['name'] . ":**\n```\n" . $attachment['text'] . "\n```";
                continue;
            }

            if ($attachment['kind'] === 'document') {
                $parts[] = '**' . $attachment['name'] . '** (' . strtoupper($attachment['ext']) . ' document — binary content not extracted locally).';
                continue;
            }

            if ($attachment['kind'] === 'image') {
                $parts[] = '**' . $attachment['name'] . '** (' . self::formatAttachmentMeta($attachment) . ')';
            }
        }

        return implode("\n\n", $parts);
    }

    /**
     * @param list<array{path: string, name: string, mime: string, ext: string, kind: string, text: ?string}> $attachments
     */
    private static function attachmentsHaveImages(array $attachments): bool
    {
        foreach ($attachments as $attachment) {
            if (($attachment['kind'] ?? '') === 'image') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array{path: string, name: string, mime: string, ext: string, kind: string, text: ?string}> $attachments
     * @return array<int, array<string, mixed>>|string
     */
    private static function buildLlmUserContent(string $message, array $attachments, string $textContext): array|string
    {
        $blocks   = [];
        $preface  = $message;

        if ($textContext !== '') {
            $preface .= "\n\nAttached file excerpts:\n" . $textContext;
        }

        foreach ($attachments as $attachment) {
            if (($attachment['kind'] ?? '') !== 'document') {
                continue;
            }
            if (($attachment['text'] ?? null) !== null) {
                continue;
            }
            $preface .= "\n\nThe user also attached **" . $attachment['name'] . '** (' . strtoupper($attachment['ext']) . '). Use the question and system context; note if you cannot read the binary file directly.';
        }

        $blocks[] = ['type' => 'text', 'text' => $preface];

        foreach ($attachments as $attachment) {
            if (($attachment['kind'] ?? '') !== 'image') {
                continue;
            }

            $data = base64_encode((string) file_get_contents($attachment['path']));
            $mime = $attachment['mime'] ?: 'image/jpeg';
            $blocks[] = [
                'type'      => 'image_url',
                'image_url' => ['url' => 'data:' . $mime . ';base64,' . $data],
            ];
        }

        return count($blocks) === 1 ? $preface : $blocks;
    }

    /**
     * @param list<array{path: string, name: string, mime: string, ext: string, kind: string, text: ?string}> $attachments
     */
    private static function cleanupAttachments(array $attachments): void
    {
        foreach ($attachments as $attachment) {
            $path = $attachment['path'] ?? '';
            if (is_string($path) && is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * @param list<array{path: string, name: string, mime: string, ext: string, kind: string, text: ?string}> $attachments
     */
    private static function prependAttachmentAck(string $reply, array $attachments): string
    {
        if ($attachments === []) {
            return $reply;
        }

        $names = array_map(static fn(array $a): string => $a['name'], $attachments);
        $ack   = '**Received ' . count($names) . ' file(s):** ' . implode(', ', $names) . "\n\n";

        return $ack . $reply;
    }

    /**
     * @param array{path: string, name: string, mime: string, ext: string, kind: string, text: ?string} $attachment
     */
    private static function formatAttachmentMeta(array $attachment): string
    {
        $path = $attachment['path'] ?? '';
        $size = is_file($path) ? round(filesize($path) / 1024, 1) . ' KB' : 'unknown size';

        if (($attachment['kind'] ?? '') === 'image' && is_file($path)) {
            $info = @getimagesize($path);
            if (is_array($info)) {
                return 'image, ' . $info[0] . '×' . $info[1] . ' px, ' . $size;
            }

            return 'image, ' . $size;
        }

        return strtoupper($attachment['ext'] ?? 'file') . ', ' . $size;
    }

    /**
     * @param list<array{path: string, name: string, mime: string, ext: string, kind: string, text: ?string}> $attachments
     */
    private static function replyLocalAttachmentFallback(string $message, array $attachments, string $textContext, bool $hasImages): string
    {
        $lines = ['**Files received:**'];

        foreach ($attachments as $attachment) {
            $lines[] = '• **' . $attachment['name'] . '** — ' . self::formatAttachmentMeta($attachment);
        }

        $lines[] = '';

        if ($textContext !== '') {
            $lines[] = '**Extracted content:**';
            $lines[] = $textContext;
            $lines[] = '';
        }

        if ($hasImages) {
            $lines[] = '**About images:** I can confirm your image(s) were uploaded. Describe what you need help with (e.g. *“Is this ID format acceptable?”*) and I will answer using **notary best practices**. For document wording, attach a **PDF** or **.txt** file, or paste the text in your message.';
        } elseif ($textContext === '') {
            $lines[] = 'I could not read text from these files locally. Attach **.txt**, **.csv**, or paste content in your message, then ask your question again.';
        }

        $questionReply = self::reply($message);
        if (!self::isGenericFallback($questionReply) && !self::isEntityNotFoundReply($questionReply)) {
            $lines[] = '';
            $lines[] = $questionReply;
        } elseif ($message !== '') {
            $lines[] = '';
            $lines[] = '**Your question:** Try rephrasing with a **portal how-to** (e.g. *“how do I upload a document?”*) or a **notary topic** (e.g. *“what is an affidavit?”*).';
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function callOpenAiChat(array $payload, string $apiKey, array $config): ?string
    {
        $baseUrl = rtrim($config['base_url'] ?? 'https://api.openai.com/v1', '/');
        $ch = curl_init($baseUrl . '/chat/completions');

        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 90,
        ]);

        $response = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($response) || $response === '' || $status < 200 || $status >= 300) {
            return null;
        }

        $decoded = json_decode($response, true);
        $content = trim((string) ($decoded['choices'][0]['message']['content'] ?? ''));

        return $content !== '' ? $content : null;
    }

    public static function replySmartFallback(string $message): string
    {
        $normalized = strtolower(trim($message));
        $ctx        = getChatbotContext();
        $stats      = $ctx['stats'];
        $company    = getCompanySettings();

        if (preg_match('/^(thanks|thank you|ty|ok|okay|cool|great|perfect)$/', $normalized)) {
            return "You're welcome! Let me know if you need anything else.";
        }

        if (chatbotIsPortalSystemQuestion($message) || chatbotIsProceduralQuery($message)) {
            $portalSystem = chatbotReplyForPortalSystemQuestion($message);
            if ($portalSystem !== null) {
                return $portalSystem;
            }
        }

        $systemInsight = chatbotReplyForSystemInsights($message);
        if ($systemInsight !== null) {
            return $systemInsight;
        }

        $entityReply = chatbotReplyForEntityLookup($message);
        if ($entityReply !== null) {
            return $entityReply;
        }

        $general = chatbotReplyForGeneralKnowledge($message);
        if ($general !== null) {
            return $general;
        }

        $template = chatbotReplyForGeneralizedTemplate($message);
        if ($template !== null) {
            return $template;
        }

        if (chatbotIsGeneralQuestion($message)) {
            $open = chatbotReplyForOpenEndedLocal($message);
            if ($open !== null) {
                return $open;
            }

            return chatbotTemplateOpenAnswer(chatbotExtractQuestionSubject($message), $message);
        }

        if (preg_match('/\?/', $message) || preg_match('/\b(what|why|when|where|who|how|can|should|is|are|do|does|any|got|have)\b/', $normalized)) {
            $hints = [];

            if (preg_match('/\b(client|customer)\b/', $normalized)) {
                $hints[] = 'You have **' . $stats['total_clients'] . ' clients** — try “list clients” or a client name.';
            }
            if (preg_match('/\b(case|matter)\b/', $normalized)) {
                $hints[] = 'There are **' . $stats['active_cases'] . ' active cases** — try “list active cases”.';
            }
            if (preg_match('/\b(pay|invoice|bill|money|revenue|pending)\b/', $normalized)) {
                $hints[] = 'Total revenue is **' . formatCurrency($stats['total_revenue']) . '** with **' . $stats['pending_invoices'] . ' pending invoices** — try “any pending payments”.';
            }
            if (preg_match('/\b(appoint|schedule|meeting|calendar)\b/', $normalized)) {
                $hints[] = 'You have **' . $stats['upcoming_appointments'] . ' upcoming appointments** — try “show upcoming appointments”.';
            }

            if (preg_match('/\b(document|upload|file|pdf)\b/', $normalized)) {
                $hints[] = 'Ask **“have clients uploaded documents?”** or **“list documents”** for live file data.';
            }
            if (preg_match('/\b(invoice|bill)\b/', $normalized)) {
                $hints[] = 'You have **' . $stats['pending_invoices'] . ' pending invoices** — try **list invoices** or **overdue invoices**.';
            }

            if ($hints === []) {
                $hints[] = 'Try **dashboard summary**, **what is a notary?**, or **how do I add client instructions?**';
            }

            return "**" . ucfirst(rtrim($message, '?')) . "?**\n\n"
                . "I'm your assistant for **" . ($company['company_name'] ?? 'Notary Management') . "**. "
                . "Here are some things that might help:\n\n"
                . implode("\n\n", array_map(static fn(string $line): string => '• ' . $line, $hints));
        }

        return "**Got it.** I can help with **live business data**, **portal workflows**, **notary & document topics**, and **general business advice**.\n\n"
            . "Try **help**, **what can you do**, **dashboard summary**, or ask any question in plain English.";
    }

    private static function aiConfig(): array
    {
        static $config = null;

        if ($config === null) {
            $appConfig = require __DIR__ . '/../config/config.php';
            $config = $appConfig['ai'] ?? [];
            if (!empty($config['enabled'])) {
                $envKey = getenv('OPENAI_API_KEY');
                if ($envKey && empty($config['api_key'])) {
                    $config['api_key'] = $envKey;
                }
            }
        }

        return $config;
    }

    private static function buildSystemPrompt(): string
    {
        $company = getCompanySettings();
        $ctx     = getChatbotContext();
        $stats   = $ctx['stats'];

        $contextJson = json_encode([
            'company'          => $company['company_name'] ?? 'Notary Management',
            'stats'            => $stats,
            'system_snapshot'  => getChatbotSystemSnapshot(),
            'recent_cases'     => $ctx['recent_cases'],
            'next_appointment' => $ctx['next_appointment'],
            'pending_payments' => $ctx['pending_payments'],
        ], JSON_PRETTY_PRINT);

        return <<<PROMPT
You are Notary Admin AI, the intelligent assistant inside a Notary Management admin portal.

Answer the admin's question directly. Portal/business data, workflows, general knowledge, definitions, advice, and drafting.

Rules:
- **Answer ONLY what was asked.** Do not add unrelated modules, tips, overviews, or "you can also ask…" unless they requested a list or overview.
- **Be concise:** usually 1–5 short bullets or numbered steps; no broad "workflow" dumps for a single how-to question.
- Use live business context below for data questions. Do not invent client names or case numbers not in context.
- For "how do I X" give step-by-step instructions for **X only** (e.g. create a case → only creation steps, not all case features).
- For counts/lists, give the number or list only unless they asked for explanation.
- Do not refuse as "out of scope" unless harmful; prefer a best-effort focused answer.
- Client instructions: set on case create/edit ("Instructions for Client"); clients see them highlighted on their case view.
- Appointment requests: clients submit requests; admins approve by changing status from Requested to Scheduled/Confirmed.
- Be concise, friendly, and use markdown (**bold**, bullet lists) sparingly.
- **Drafting:** When asked to draft/write/compose any document (email, letter, affidavit, quotation, contract, memo, etc.), provide a complete editable draft with [placeholders].
- **Definitions:** Explain terms plainly; add notary context only when it helps.
- If you lack specific portal data, say so and suggest what they can look up in the portal.
- When the admin attaches images or files, analyze them carefully and relate answers to notary workflows when relevant.

Live business context:
{$contextJson}
PROMPT;
    }

    private static function proceduralReplyForCase(int $caseId, string $message): string
    {
        $case = Database::fetch(
            'SELECT cs.*, cl.first_name, cl.last_name, cl.company_name, cl.email
             FROM cases cs
             JOIN clients cl ON cl.id = cs.client_id
             WHERE cs.id = ?',
            [$caseId]
        );

        if (!$case) {
            return self::generalCaseGuide();
        }

        $status = ucwords(str_replace('_', ' ', $case['status'] ?? 'unknown'));
        $lines  = [
            '**Next steps for ' . ($case['case_number'] ?? 'this case') . '** (*' . $status . '*)',
            '',
        ];

        $instructions = trim((string) ($case['client_instructions'] ?? ''));
        if ($instructions !== '') {
            $lines[] = '**Current client instructions:**';
            $lines[] = $instructions;
            $lines[] = '';
        } else {
            $lines[] = '_No client instructions saved yet._';
            $lines[] = '';
        }

        $lines[] = '**Recommended admin actions:**';
        $lines[] = '1. Open **Cases → ' . ($case['case_number'] ?? '') . '** to review details.';
        $lines[] = '2. Add or update **Instructions for Client** (Overview tab → Edit Case).';
        $lines[] = '3. Generate/send **Quotation** or **Client Letter** if the client needs documents.';
        $lines[] = '4. Set status to **In Progress** or **Waiting for Client** as appropriate.';
        $lines[] = '5. Schedule an **Appointment** if a meeting is needed.';

        if (($case['status'] ?? '') === 'waiting_for_client') {
            $lines[] = '';
            $lines[] = 'This case is **Waiting for Client** — ensure instructions are clear, then email the client letter or send a portal notification.';
        }

        return implode("\n", $lines);
    }

    private static function proceduralReplyForClient(int $clientId, string $message): string
    {
        $client = ClientService::getById($clientId);
        if (!$client) {
            return self::generalCaseGuide();
        }

        $cases = Database::fetchAll(
            'SELECT case_number, title, status, client_instructions
             FROM cases
             WHERE client_id = ?
             ORDER BY updated_at DESC
             LIMIT 6',
            [$clientId]
        );

        $lines = [
            '**How to proceed for ' . clientFullName($client) . '**',
            '',
        ];

        if ($cases === []) {
            $lines[] = 'This client has no cases yet. Create one under **Cases → New Case**.';
            return implode("\n", $lines);
        }

        $lines[] = '**Cases & instructions:**';
        foreach ($cases as $case) {
            $caseStatus = ucwords(str_replace('_', ' ', $case['status'] ?? ''));
            $lines[] = '• **' . $case['case_number'] . '** — ' . $case['title'] . ' (*' . $caseStatus . '*)';
            $instr = trim((string) ($case['client_instructions'] ?? ''));
            if ($instr !== '') {
                $lines[] = '  Instructions: ' . mb_strimwidth($instr, 0, 120, '…');
            }
        }

        $lines[] = '';
        $lines[] = '**What to do next:**';
        $lines[] = '1. Open the relevant case and confirm **Instructions for Client** are complete.';
        $lines[] = '2. Email documents (quotation/client letter) if needed.';
        $lines[] = '3. Update case status when the client responds or documents are ready.';
        $lines[] = '4. Book an appointment from **Appointments** if a signing meeting is required.';

        if (preg_match('/\binstructions?\b/', $message)) {
            $waiting = array_filter($cases, static fn(array $c): bool => ($c['status'] ?? '') === 'waiting_for_client');
            if ($waiting !== []) {
                $lines[] = '';
                $lines[] = '_Tip: Cases marked **Waiting for Client** usually need clearer instructions or a follow-up email._';
            }
        }

        return implode("\n", $lines);
    }

    private static function generalInstructionsGuide(): string
    {
        return "**Client instructions — overview:**\n\n"
            . "1. Set them on the case create/edit form.\n"
            . "2. Clients see them on their case view.\n"
            . "3. Update case status when the client completes their part.";
    }

    private static function generalCaseGuide(): string
    {
        return "**Case workflow:**\n\n"
            . "• **New Case** — select client, services, fees, deadline, and client instructions.\n"
            . "• **Case View** — documents, activity, client letter, payments, and status updates.\n"
            . "• **Statuses** — Pending → In Progress → Waiting for Client → Completed/Closed.\n"
            . "• Search a **case number** (e.g. CASE-2026-0003) or ask **list active cases**.";
    }

    private static function generalAppointmentGuide(): string
    {
        return "**Appointments:**\n\n"
            . "• **Schedule** from Appointments → Schedule Appointment (admin sets time and notifies client).\n"
            . "• **Client requests** arrive as **Requested** — edit and set to Scheduled/Confirmed to approve.\n"
            . "• Calendar supports Google, Outlook, and .ics export.\n"
            . "• Ask **upcoming appointments** or **next appointment** for live data.";
    }

    private static function generalPaymentGuide(): string
    {
        return "**Payments & invoices:**\n\n"
            . "• Create invoices from cases; clients pay via the portal (Stripe if configured).\n"
            . "• Ask **total revenue**, **pending invoices**, or **list recent payments** for live figures.\n"
            . "• Quotations and client letters can be emailed when a case is created.";
    }

    private static function generalAdminGuide(): string
    {
        return "**Admin portal overview:**\n\n"
            . "• **Dashboard** — KPIs, charts, upcoming appointments, recent cases.\n"
            . "• **Clients** — profiles, portal access, case history.\n"
            . "• **Cases** — full workflow, documents, instructions, client letters.\n"
            . "• **Payments** — invoices and payment tracking.\n"
            . "• **Appointments** — calendar, scheduling, client requests.\n"
            . "• **Settings** — branding, email, office details.\n\n"
            . "Ask **dashboard summary** or a specific **how to…** question.";
    }

    private static function generalNotaryKnowledge(): string
    {
        return "A **notary** (or notary public) witnesses and authenticates signatures, administers oaths, and certifies documents. "
            . "In this system, you manage **clients**, **cases** (matters requiring notarization or related services), "
            . "**documents**, **appointments** for signings, and **payments** — all from the admin portal while clients track progress online.";
    }
}
