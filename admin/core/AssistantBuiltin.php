<?php

declare(strict_types=1);

/**
 * Built-in assistant replies — no external LLM required.
 */
class AssistantBuiltin
{
    public static function smartFallback(string $message): string
    {
        $message = assistantNormalizeUserMessage($message);
        if ($message === '') {
            return AssistantKnowledge::outOfScopeMessage();
        }

        if (AssistantKnowledge::looksLikeCapabilitiesQuery($message)) {
            return AssistantKnowledge::capabilitiesMessage();
        }

        $direct = AssistantKnowledge::tryAnswer($message);
        if ($direct !== null && trim((string) ($direct['content'] ?? '')) !== '') {
            return (string) $direct['content'];
        }

        if (AssistantRouter::looksLikeSearch($message)) {
            return AssistantSearch::handle($message)['content'];
        }

        $dashboardTopic = AssistantRouter::matchDashboardTopic($message);
        if ($dashboardTopic !== null) {
            return AssistantDashboard::handle($dashboardTopic)['content'];
        }

        if (class_exists('ChatbotService') && function_exists('chatbotIsDraftRequest') && function_exists('chatbotReplyForDraftRequest')) {
            return ChatbotService::replySmartFallback($message);
        }

        return self::localSmartFallback($message);
    }

    public static function openEndedReply(string $message): ?string
    {
        $message = assistantNormalizeUserMessage($message);
        if ($message === '') {
            return null;
        }

        $subject = function_exists('chatbotExtractQuestionSubject')
            ? chatbotExtractQuestionSubject($message)
            : trim($message);

        if (function_exists('chatbotTemplateDefinition') && function_exists('chatbotLooksLikeKnowledgeQuery')
            && (str_word_count(strtolower($message)) <= 6 || chatbotLooksLikeKnowledgeQuery($message))) {
            return chatbotTemplateDefinition($subject, $message);
        }

        if (function_exists('chatbotTemplateOpenAnswer')) {
            return chatbotTemplateOpenAnswer($subject, $message);
        }

        return self::templateOpenAnswer($subject, $message);
    }

    private static function localSmartFallback(string $message): string
    {
        $normalized = strtolower(trim($message));
        $ctx = getChatbotContext();
        $stats = $ctx['stats'];
        $company = getCompanySettings();

        if (preg_match('/^(thanks|thank you|ty|ok|okay|cool|great|perfect)$/', $normalized)) {
            return "You're welcome! Let me know if you need anything else.";
        }

        if (preg_match('/^(hi|hello|hey|good morning|good afternoon)\b/', $normalized)) {
            return 'Hello! Ask _what can you do?_ for help with this portal, uploaded documents, or notary term definitions.';
        }

        if (function_exists('chatbotIsPortalSystemQuestion') && function_exists('chatbotReplyForPortalSystemQuestion')
            && (chatbotIsPortalSystemQuestion($message) || (function_exists('chatbotIsProceduralQuery') && chatbotIsProceduralQuery($message)))) {
            $portalSystem = chatbotReplyForPortalSystemQuestion($message);
            if ($portalSystem !== null) {
                return $portalSystem;
            }
        }

        if (function_exists('chatbotReplyForGeneralKnowledge')) {
            $general = chatbotReplyForGeneralKnowledge($message);
            if ($general !== null) {
                return $general;
            }
        }

        if (function_exists('chatbotIsGeneralQuestion') && chatbotIsGeneralQuestion($message)) {
            $open = self::openEndedReply($message);
            if ($open !== null && trim($open) !== '') {
                return $open;
            }
        }

        if (preg_match('/\?/', $message) || preg_match('/\b(what|why|when|where|who|how|can|should|is|are|do|does|any|got|have)\b/', $normalized)) {
            $hints = [];

            if (preg_match('/\b(client|customer)\b/', $normalized)) {
                $hints[] = 'You have **' . $stats['total_clients'] . ' clients** — try “list clients” or search by name.';
            }
            if (preg_match('/\b(case|matter)\b/', $normalized)) {
                $hints[] = 'There are **' . $stats['active_cases'] . ' active cases** — try “list active cases”.';
            }
            if (preg_match('/\b(pay|invoice|bill|money|revenue|pending)\b/', $normalized)) {
                $hints[] = 'Total revenue is **' . formatCurrency($stats['total_revenue']) . '** — try “show overdue invoices”.';
            }
            if (preg_match('/\b(appoint|schedule|meeting|calendar)\b/', $normalized)) {
                $hints[] = 'You have **' . $stats['upcoming_appointments'] . ' upcoming appointments** — try “show upcoming appointments”.';
            }
            if (preg_match('/\b(document|upload|file|pdf)\b/', $normalized)) {
                $hints[] = 'Attach a PDF or image with the paperclip, then ask about amounts, dates, or parties.';
            }

            if ($hints === []) {
                $hints[] = 'Try **what can you do?**, **dashboard summary**, or **what is a jurat?**';
            }

            return '**' . ucfirst(rtrim($message, '?')) . "?**\n\n"
                . 'Here are some things that might help for **' . ($company['company_name'] ?? 'your practice') . "**:\n\n"
                . implode("\n\n", array_map(static fn (string $line): string => '• ' . $line, $hints));
        }

        return AssistantKnowledge::outOfScopeMessage();
    }

    private static function templateOpenAnswer(string $subject, string $message): string
    {
        $subject = trim($subject) !== '' ? $subject : 'that';

        return '**' . ucfirst(rtrim($message, '?')) . "**\n\n"
            . 'I do not have a detailed article on **' . $subject . '** in my built-in knowledge, '
            . 'but I can help with **portal data**, **cases and clients**, **appointments**, **payments**, '
            . '**document uploads**, and **notary term definitions**. Try _what can you do?_ for examples.';
    }
}

function chatbotReplyForOpenEndedLocal(string $message): ?string
{
    return AssistantBuiltin::openEndedReply($message);
}
