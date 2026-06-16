<?php

declare(strict_types=1);

class AssistantRouter
{
    public const INTENT_DASHBOARD = 'dashboard';
    public const INTENT_ACTION = 'action';
    public const INTENT_SEARCH = 'search';
    public const INTENT_DOCUMENT = 'document';
    public const INTENT_INTAKE = 'intake';
    public const INTENT_COMPLIANCE = 'compliance';
    public const INTENT_KNOWLEDGE = 'knowledge';
    public const INTENT_GENERAL = 'general';

    /** @return array{intent: string, topic: string, message: string} */
    public static function route(string $message): array
    {
        $message = assistantNormalizeUserMessage($message);
        $normalized = strtolower($message);

        if ($normalized === '') {
            return ['intent' => self::INTENT_GENERAL, 'topic' => 'empty', 'message' => $message];
        }

        if (AssistantIntake::isActive()) {
            if (preg_match('/\b(cancel|stop|exit|end|abort)\b.*\b(intake|onboarding)\b/', $normalized)
                || preg_match('/\b(cancel intake|stop intake|exit intake)\b/', $normalized)
                || preg_match('/^(cancel|stop|never mind|nevermind|skip)$/i', $normalized)) {
                AssistantIntake::clear();

                return [
                    'intent' => self::INTENT_GENERAL,
                    'topic' => 'intake_cancelled',
                    'message' => $message,
                ];
            }

            if ($topic = self::matchActionTopic($normalized)) {
                AssistantIntake::clear();

                return ['intent' => self::INTENT_ACTION, 'topic' => $topic, 'message' => $message];
            }

            if (self::looksLikeIntakeInterrupt($normalized)) {
                AssistantIntake::clear();
            } else {
                return ['intent' => self::INTENT_INTAKE, 'topic' => 'onboarding', 'message' => $message];
            }
        }

        if (self::looksLikeIntakeStart($normalized)) {
            return ['intent' => self::INTENT_INTAKE, 'topic' => 'onboarding', 'message' => $message];
        }

        if (self::looksLikeDocumentScan($normalized)) {
            return ['intent' => self::INTENT_DOCUMENT, 'topic' => 'scan', 'message' => $message];
        }

        if (AssistantCalculations::looksLikeCalculationQuery($normalized)) {
            return ['intent' => self::INTENT_KNOWLEDGE, 'topic' => 'calculation', 'message' => $message];
        }

        if ($topic = self::matchDashboardTopic($normalized)) {
            return ['intent' => self::INTENT_DASHBOARD, 'topic' => $topic, 'message' => $message];
        }

        if ($topic = self::matchActionTopic($normalized)) {
            return ['intent' => self::INTENT_ACTION, 'topic' => $topic, 'message' => $message];
        }

        if (self::looksLikeSearch($normalized)) {
            return ['intent' => self::INTENT_SEARCH, 'topic' => 'universal', 'message' => $message];
        }

        if (AssistantKnowledge::looksLikeDefinitionQuery($normalized)) {
            return ['intent' => self::INTENT_KNOWLEDGE, 'topic' => 'definition', 'message' => $message];
        }

        if ($topic = self::matchKnowledgeTopic($normalized)) {
            return ['intent' => self::INTENT_KNOWLEDGE, 'topic' => $topic, 'message' => $message];
        }

        if (AssistantCompliance::messageNeedsScreening($normalized)) {
            return ['intent' => self::INTENT_COMPLIANCE, 'topic' => 'screen', 'message' => $message];
        }

        return ['intent' => self::INTENT_GENERAL, 'topic' => 'chat', 'message' => $message];
    }

    private static function looksLikeIntakeStart(string $message): bool
    {
        return (bool) preg_match(
            '/\b(start intake|client intake|onboard(?:ing)? (?:a )?client|new client interview|begin onboarding)\b/',
            $message
        );
    }

    private static function looksLikeIntakeInterrupt(string $message): bool
    {
        if (self::looksLikeDocumentScan($message)) {
            return true;
        }

        if (self::matchDashboardTopic($message) !== null) {
            return true;
        }

        if (AssistantCalculations::looksLikeCalculationQuery($message)) {
            return true;
        }

        if (AssistantKnowledge::looksLikeDefinitionQuery($message)) {
            return true;
        }

        if (self::matchKnowledgeTopic($message) !== null) {
            return true;
        }

        return self::looksLikeSearch($message);
    }

    private static function looksLikeDocumentScan(string $message): bool
    {
        return (bool) preg_match(
            '/\b(scan|ocr|read|extract|analyse|analyze).*\b(pdf|document|file|image|photo|pic|upload)\b/',
            $message
        ) || (bool) preg_match('/\b(scan pdf|scan (?:this )?doc)/', $message);
    }

    private static function looksLikeSearch(string $message): bool
    {
        return (bool) preg_match(
            '/\b(find|search|look up|lookup|show me|list|who is|where is)\b/',
            $message
        ) && (bool) preg_match(
            '/\b(client|case|invoice|receipt|payment|document|upload)\b/',
            $message
        );
    }

    private static function matchDashboardTopic(string $message): ?string
    {
        $rules = [
            'client_count' => '/\b(how many|number of|total|count of)\s+clients?\b|\bclient count\b|\bclients?\s+(?:do\s+we\s+have|count)\b/',
            'active_cases' => '/\bactive cases?\b|\b(open|in[- ]progress) cases?\b|\bcases? (?:open|in progress)\b|\blist active cases?\b/',
            'total_revenue' => '/\b(total revenue|our revenue|overall earnings|total earnings|how much (?:have we )?earned|what is our (?:total )?revenue)\b/',
            'upcoming_appointments' => '/\b(upcoming appointments?|next appointments?|appointment schedule|calendar schedule|show upcoming appointments?)\b/',
            'recent_payments' => '/\b(recent payments?|latest payments?|last payments?|list recent payments?)\b/',
            'overdue_invoices' => '/\b(overdue invoices?|outstanding invoices?|unpaid invoices? past due|show overdue invoices?)\b/',
            'unread_notifications' => '/\b(unread notifications?|new alerts?|unread alerts?|how many unread notifications?)\b/',
            'revenue_by_month' => '/\b(revenue by month|monthly revenue|month[- ]by[- ]month|financial breakdown)\b/',
        ];

        foreach ($rules as $topic => $pattern) {
            if (preg_match($pattern, $message)) {
                return $topic;
            }
        }

        if (preg_match('/\bdashboard\b|\bsummary\b|\boverview\b/', $message)) {
            return 'overview';
        }

        return null;
    }

    private static function matchActionTopic(string $message): ?string
    {
        if (preg_match('/\b(create|open|start|add)\b.*\b(case|matter|file)\b/', $message)) {
            return 'create_case';
        }
        if (preg_match('/\b(update|change|modify|edit)\b.*\b(case|matter|status|description)\b/', $message)) {
            return 'update_case';
        }
        if (preg_match('/\b(cancel)\b.*\b(appointment|meeting)\b/', $message)
            || preg_match('/\b(appointment|meeting)\b.*\b(cancel)\b/', $message)) {
            return 'cancel_appointment';
        }
        if (preg_match('/\b(confirm)\b.*\b(appointment|meeting)\b/', $message)
            || preg_match('/\b(appointment|meeting)\b.*\b(confirm)\b/', $message)) {
            return 'confirm_appointment';
        }
        if (preg_match('/\b(reschedule|rebook|move)\b.*\b(appointment|meeting)\b/', $message)
            || preg_match('/\b(appointment|meeting)\b.*\b(reschedule|rebook|move)\b/', $message)) {
            return 'reschedule_appointment';
        }
        if (preg_match('/\b(complete|mark.*complete|finished)\b.*\b(appointment|meeting)\b/', $message)
            || preg_match('/\b(appointment|meeting)\b.*\b(complete|finished)\b/', $message)) {
            return 'complete_appointment';
        }
        if (preg_match('/\bno[- ]?show\b.*\b(appointment|meeting)\b/', $message)
            || preg_match('/\b(appointment|meeting)\b.*\bno[- ]?show\b/', $message)) {
            return 'mark_appointment_no_show';
        }
        if (preg_match('/\b(schedule|book|set up|create)\b.*\b(appointment|meeting|slot)\b/', $message)
            || preg_match('/\b(appointment|meeting)\b.*\b(schedule|book|set up|create)\b/', $message)) {
            return 'schedule_appointment';
        }
        if (preg_match('/\b(mark|clear|dismiss)\b.*\b(notifications?|alerts?)\b.*\b(read|all)?\b/', $message)
            || preg_match('/\bmark all (?:notifications?|alerts?) read\b/', $message)) {
            return 'mark_notifications_read';
        }

        return null;
    }

    private static function matchKnowledgeTopic(string $message): ?string
    {
        if (preg_match('/\b(calculate|computation|math|average|percent|percentage|increase|decrease|formula)\b/', $message)
            && preg_match('/\d/', $message)) {
            return 'calculation';
        }

        if (preg_match('/\b(where (?:is|are|do i)|how (?:do i|to)|navigate|find (?:the )?(?:settings|payments|cases|clients))\b/', $message)) {
            return 'system_qa';
        }

        return null;
    }
}
