<?php

declare(strict_types=1);

/**
 * Built-in query helpers for the assistant (no external LLM).
 * Bridges legacy chatbot function names to Assistant* services.
 */

function chatbotAdminLink(string $path, string $label): string
{
    return assistantAdminLink($path, $label);
}

function chatbotRememberTurn(string $userMessage, string $botReply): void
{
    $history = $_SESSION['chatbot_history'] ?? [];
    if (!is_array($history)) {
        $history = [];
    }
    $history[] = ['user' => $userMessage, 'bot' => $botReply];
    $_SESSION['chatbot_history'] = array_slice($history, -20);
}

function chatbotRememberDraft(string $body): void
{
    $_SESSION['chatbot_last_draft'] = $body;
}

/** @return array{type?: string, id?: int, label?: string}|null */
function chatbotGetLastEntity(): ?array
{
    $entity = $_SESSION['chatbot_last_entity'] ?? null;

    return is_array($entity) ? $entity : null;
}

function chatbotSetLastEntity(string $type, int $id, string $label): void
{
    $_SESSION['chatbot_last_entity'] = [
        'type'  => $type,
        'id'    => $id,
        'label' => $label,
    ];
}

/** @return array<string, mixed>|null */
function chatbotFetchCaseById(int $id): ?array
{
    return CaseService::getCaseById($id);
}

function chatbotIsDraftRequest(string $message): bool
{
    return (bool) preg_match('/\b(draft|write|compose|prepare)\b/i', $message);
}

function chatbotIsDefinitionRequest(string $message): bool
{
    return AssistantKnowledge::looksLikeDefinitionQuery($message);
}

function chatbotIsContextualFollowUp(string $message): bool
{
    $normalized = strtolower(trim($message));

    return (bool) preg_match(
        '/^(yes|no|yep|nope|ok|okay|that one|this one|same|them|their|it|continue|go on)$/',
        $normalized
    ) || (bool) preg_match('/\b(that client|same client|their case|follow[- ]?up)\b/', $normalized);
}

function chatbotIsSystemDataQuestion(string $message): bool
{
    $lower = strtolower($message);

    if (AssistantRouter::matchDashboardTopic($message) !== null) {
        return true;
    }

    if (AssistantRouter::looksLikeSearch($message)) {
        return true;
    }

    return (bool) preg_match(
        '/\b(how many|number of|count of|list|show me|show all|total|revenue|earnings|clients?|cases?|appointments?|invoices?|payments?|notifications?|overdue|pending|upcoming|recent)\b/',
        $lower
    );
}

function chatbotIsPortalSystemQuestion(string $message): bool
{
    return AssistantKnowledge::looksLikeSystemQuery($message);
}

function chatbotIsProceduralQuery(string $message): bool
{
    return (bool) preg_match('/\bhow (?:do i|to)\b/i', $message)
        || (bool) preg_match('/\bwhere (?:is|are|do i|can i)\b/i', $message);
}

function chatbotIsPortalProceduralQuery(string $message): bool
{
    return chatbotIsProceduralQuery($message) && chatbotIsPortalSystemQuestion($message);
}

function chatbotLooksLikeKnowledgeQuery(string $message): bool
{
    return AssistantKnowledge::looksLikeDefinitionQuery($message)
        || AssistantPracticeFaq::matches($message);
}

function chatbotIsGeneralKnowledgeQuestion(string $message): bool
{
    return chatbotLooksLikeKnowledgeQuery($message)
        || (bool) preg_match('/\bwhat is (?:a |an )?\w+/i', $message);
}

function chatbotIsGeneralQuestion(string $message): bool
{
    return (bool) preg_match('/\?/', $message)
        || (bool) preg_match('/^\s*(what|why|when|where|who|how|can|should|is|are|do|does)\b/i', trim($message));
}

function chatbotIsAdviceOrHowToQuery(string $message): bool
{
    return chatbotIsProceduralQuery($message)
        || (bool) preg_match('/\b(advice|recommend|best practice|should i)\b/i', $message);
}

function chatbotIsDashboardOrBriefingQuery(string $message): bool
{
    return AssistantRouter::matchDashboardTopic($message) !== null
        || (bool) preg_match('/\b(briefing|dashboard overview|morning briefing)\b/i', $message);
}

function chatbotIsDocumentDataQuery(string $message): bool
{
    return (bool) preg_match('/\b(document|upload|pdf|file|scan|ocr|summarize|extract)\b/i', $message);
}

function chatbotLooksLikePersonNameSearch(string $message): bool
{
    if (chatbotIsSystemDataQuestion($message) || chatbotIsDraftRequest($message)) {
        return false;
    }

    return chatbotShouldTryEntityLookup($message);
}

function chatbotExtractDefinitionTerm(string $message): string
{
    if (preg_match('/\bwhat is (?:a |an )?(.+?)\??$/i', trim($message), $matches)) {
        return trim($matches[1]);
    }

    if (preg_match('/\bdefine\s+(.+?)\??$/i', trim($message), $matches)) {
        return trim($matches[1]);
    }

    return '';
}

function chatbotExtractQuestionSubject(string $message): string
{
    $term = chatbotExtractDefinitionTerm($message);
    if ($term !== '') {
        return $term;
    }

    $normalized = chatbotNormalizeLookupTerm($message);

    return $normalized !== '' ? $normalized : trim($message);
}

function chatbotTemplateDefinition(string $subject, string $message): string
{
    $result = AssistantKnowledge::handle('definition', $message !== '' ? $message : ('what is ' . $subject));

    return $result['content'];
}

function chatbotTemplateOpenAnswer(string $subject, string $message): string
{
    return AssistantBuiltin::smartFallback($message !== '' ? $message : $subject);
}

function chatbotTemplateDraftContent(string $message): string
{
    return "Dear [Client Name],\n\n[Your message here]\n\nKind regards,\n" . companyBrandName();
}

function chatbotReplyForPortalSystemQuestion(string $message): ?string
{
    if (!chatbotIsPortalSystemQuestion($message) && !chatbotIsProceduralQuery($message)) {
        return null;
    }

    $result = AssistantKnowledge::handle('system_qa', $message);

    return $result['content'] ?? null;
}

function chatbotReplyForPortalDataQuestion(string $message): ?string
{
    $topic = AssistantRouter::matchDashboardTopic($message);
    if ($topic !== null) {
        return AssistantDashboard::handle($topic)['content'];
    }

    if (AssistantRouter::looksLikeSearch($message)) {
        return AssistantSearch::handle($message)['content'];
    }

    return null;
}

function chatbotReplyForAppointmentQueries(string $message): ?string
{
    if (!preg_match('/\b(appointment|meeting|calendar|visit)\b/i', $message)) {
        return null;
    }

    if (AssistantRouter::actionTopic($message) !== null) {
        return null;
    }

    return AssistantDashboard::handle('upcoming_appointments')['content'];
}

function chatbotReplyForCaseQueries(string $message): ?string
{
    if (!preg_match('/\b(case|matter)\b/i', $message)) {
        return null;
    }

    if (AssistantRouter::actionTopic($message) !== null) {
        return null;
    }

    return AssistantDashboard::handle('active_cases')['content'];
}

function chatbotReplyForNotificationQueries(string $message): ?string
{
    if (!preg_match('/\b(notification|alert)s?\b/i', $message)) {
        return null;
    }

    return AssistantDashboard::handle('unread_notifications')['content'];
}

function chatbotReplyForCalculations(string $message): ?string
{
    if (!AssistantCalculations::looksLikeCalculationQuery($message)) {
        return null;
    }

    return AssistantCalculations::handle($message)['content'];
}

function chatbotReplyForContextualFollowUp(string $message): ?string
{
    return chatbotTryFollowUpReply($message);
}

function chatbotReplyForGeneralKnowledge(string $message): ?string
{
    $answer = AssistantKnowledge::tryAnswer($message);
    if ($answer !== null) {
        return $answer['content'];
    }

    if (AssistantPracticeFaq::matches($message)) {
        return AssistantPracticeFaq::handle($message)['content'];
    }

    return null;
}

function chatbotReplyForUniversalKnowledgeOffline(string $message): ?string
{
    return chatbotReplyForGeneralKnowledge($message);
}

function chatbotReplyForSystemInsights(string $message): ?string
{
    if (!preg_match('/\b(insight|alert|attention|heads up|what needs|overdue|stale)\b/i', $message)) {
        return null;
    }

    if (!function_exists('chatbotGetProactiveInsights')) {
        return null;
    }

    $insights = chatbotGetProactiveInsights();
    if ($insights === []) {
        return null;
    }

    return chatbotFormatInsightsMessage($insights);
}

function chatbotReplyForFocusedQuestion(string $message): ?string
{
    $answer = AssistantKnowledge::tryAnswer($message);

    return $answer !== null ? ($answer['content'] ?? null) : null;
}

function chatbotReplyForGeneralizedTemplate(string $message): ?string
{
    if (!chatbotIsGeneralQuestion($message)) {
        return null;
    }

    return chatbotTemplateOpenAnswer(chatbotExtractQuestionSubject($message), $message);
}

function chatbotReplyForAdviceAndGeneral(string $message): ?string
{
    if (!chatbotIsAdviceOrHowToQuery($message)) {
        return null;
    }

    return chatbotReplyForPortalSystemQuestion($message) ?? chatbotReplyForGeneralKnowledge($message);
}

function chatbotReplyForNamedClientFocus(string $message): ?string
{
    return chatbotReplyForPortalClientLookup($message);
}

function chatbotReplyForDashboardSummary(string $message): ?string
{
    if (!preg_match('/\b(dashboard|overview|summary)\b/i', $message)) {
        return null;
    }

    return AssistantDashboard::handle('overview')['content'];
}

function chatbotReplyForMorningBriefing(string $message): ?string
{
    if (!preg_match('/\b(briefing|morning summary|start my day)\b/i', $message)) {
        return null;
    }

    return AssistantDashboard::handle('overview')['content'];
}

function chatbotReplyForMetaQuestions(string $message): ?string
{
    if (!AssistantKnowledge::looksLikeCapabilitiesQuery($message)) {
        return null;
    }

    return AssistantKnowledge::capabilitiesMessage();
}

function chatbotReplyForDateFilteredQueries(string $message): ?string
{
    return null;
}

function chatbotReplyForDocumentSearch(string $message): ?string
{
    if (!preg_match('/\b(find|search|list).*\b(document|upload|file)\b/i', $message)) {
        return null;
    }

    return AssistantSearch::handle($message)['content'];
}

function chatbotReplyForReports(string $message): ?string
{
    if (!preg_match('/\b(report|analytics|breakdown)\b/i', $message)) {
        return null;
    }

    $topic = AssistantRouter::matchDashboardTopic($message);

    return $topic !== null ? AssistantDashboard::handle($topic)['content'] : null;
}

function chatbotReplyFromCompanyKnowledge(string $message): ?string
{
    return null;
}

function chatbotReplyForDraftRequest(string $message): ?string
{
    if (!chatbotIsDraftRequest($message)) {
        return null;
    }

    $type = AssistantMessageDrafts::detectType($message);
    if ($type !== null) {
        return AssistantMessageDrafts::handle($type, $message)['content'];
    }

    return null;
}

function chatbotTryActionFlow(string $message): ?string
{
    $topic = AssistantRouter::actionTopic($message);
    if ($topic === null) {
        return null;
    }

    return AssistantActions::handle($topic, $message)['content'] ?? null;
}

function chatbotIsAppointmentRelatedMessage(string $message): bool
{
    return (bool) preg_match('/\b(appointment|meeting|schedule|calendar|visit)\b/i', $message);
}

/**
 * @param list<array<string, mixed>> $rows
 */
function chatbotFormatNotificationListWithLinks(array $rows, string $heading, int $unreadCount): string
{
    $lines = [$heading, ''];

    if ($rows === []) {
        $lines[] = '_No notifications to show._';
    } else {
        foreach ($rows as $row) {
            $title = (string) ($row['title'] ?? 'Notification');
            $body = (string) ($row['message'] ?? '');
            $lines[] = '• **' . $title . '**' . ($body !== '' ? ' — ' . $body : '');
        }
    }

    $lines[] = '';
    $lines[] = '**Unread:** ' . $unreadCount;
    $lines[] = chatbotAdminLink('pages/notifications.php', 'Open notifications');

    return implode("\n", $lines);
}
