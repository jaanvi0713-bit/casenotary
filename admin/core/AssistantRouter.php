<?php

declare(strict_types=1);

class AssistantRouter
{
    public const INTENT_DASHBOARD = 'dashboard';
    public const INTENT_ACTION = 'action';
    public const INTENT_SEARCH = 'search';
    public const INTENT_DOCUMENT = 'document';
    public const INTENT_INTAKE = 'intake';
    public const INTENT_CLIENT_CREATE = 'client_create';
    public const INTENT_COMPLIANCE = 'compliance';
    public const INTENT_KNOWLEDGE = 'knowledge';
    public const INTENT_MESSAGE_DRAFT = 'message_draft';
    public const INTENT_SEND_REMINDER = 'send_reminder';
    public const INTENT_APPOINTMENT_SCHEDULE = 'appointment_schedule';
    public const INTENT_CASE_INFO = 'case_info';
    public const INTENT_GENERAL = 'general';

    public static function route(string $message): array
    {
        $message = assistantNormalizeUserMessage($message);
        $normalized = assistantMatchText($message);

        if ($normalized === '') {
            return ['intent' => self::INTENT_GENERAL, 'topic' => 'empty', 'message' => $message];
        }

        if (AssistantAppointmentSchedule::isActive()) {
            if (preg_match('/\b(cancel|stop|never mind|nevermind|abort)\b/i', $normalized)) {
                AssistantAppointmentSchedule::clear();

                return [
                    'intent' => self::INTENT_GENERAL,
                    'topic' => 'appointment_schedule_cancelled',
                    'message' => $message,
                ];
            }

            return ['intent' => self::INTENT_APPOINTMENT_SCHEDULE, 'topic' => 'wizard', 'message' => $message];
        }

        if (AssistantClientCreate::isActive()) {
            if (preg_match('/\b(cancel|stop|exit|end|abort)\b.*\b(client|wizard)\b/', $normalized)
                || preg_match('/\b(cancel client|stop client)\b/', $normalized)
                || preg_match('/^(cancel|stop|never mind|nevermind)$/i', $normalized)) {
                AssistantClientCreate::clear();

                return [
                    'intent' => self::INTENT_GENERAL,
                    'topic' => 'client_create_cancelled',
                    'message' => $message,
                ];
            }

            return ['intent' => self::INTENT_CLIENT_CREATE, 'topic' => 'wizard', 'message' => $message];
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

        if ($topic = AssistantReminders::detectType($message)) {
            return ['intent' => self::INTENT_SEND_REMINDER, 'topic' => $topic, 'message' => $message];
        }

        if ($topic = AssistantMessageDrafts::detectType($message)) {
            return ['intent' => self::INTENT_MESSAGE_DRAFT, 'topic' => $topic, 'message' => $message];
        }

        if (AssistantCalculations::looksLikeCalculationQuery($normalized)) {
            return ['intent' => self::INTENT_KNOWLEDGE, 'topic' => 'calculation', 'message' => $message];
        }

        if ($topic = self::matchDashboardTopicRules($normalized)) {
            return ['intent' => self::INTENT_DASHBOARD, 'topic' => $topic, 'message' => $message];
        }

        if ($topic = self::matchActionTopic($normalized)) {
            return ['intent' => self::INTENT_ACTION, 'topic' => $topic, 'message' => $message];
        }

        if (AssistantCaseInfo::looksLikeQuery($message)) {
            return ['intent' => self::INTENT_CASE_INFO, 'topic' => 'query', 'message' => $message];
        }

        if (self::looksLikeSearch($normalized)) {
            return ['intent' => self::INTENT_SEARCH, 'topic' => 'universal', 'message' => $message];
        }

        if (AssistantKnowledge::looksLikeSystemQuery($normalized)) {
            return ['intent' => self::INTENT_KNOWLEDGE, 'topic' => 'system_qa', 'message' => $message];
        }

        if (AssistantKnowledge::looksLikeCapabilitiesQuery($normalized)) {
            return ['intent' => self::INTENT_KNOWLEDGE, 'topic' => 'capabilities', 'message' => $message];
        }

        if (AssistantPracticeFaq::matches($normalized)) {
            return ['intent' => self::INTENT_KNOWLEDGE, 'topic' => 'practice_faq', 'message' => $message];
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

    public static function actionTopic(string $message): ?string
    {
        $message = assistantNormalizeUserMessage($message);

        return self::matchActionTopic(assistantMatchText($message));
    }

    public static function looksLikeCaseDocumentUpload(string $message): bool
    {
        return self::matchActionTopic(assistantMatchText($message)) === 'upload_case_document';
    }

    public static function shouldUploadToCase(string $message, bool $hasUpload): bool
    {
        if (!$hasUpload) {
            return false;
        }

        if (self::looksLikeCaseDocumentUpload($message)) {
            return true;
        }

        if (assistantExtractCaseReferenceFromMessage($message) === '') {
            return false;
        }

        if (preg_match(
            '/\b(scan|ocr|read|extract|analy[sz]e|summarize|summary|what does|what is in|how much|who is billed)\b/i',
            $message
        )) {
            return false;
        }

        return (bool) preg_match(
            '/\b(upload|attach|add|save|store|put|send|file|copy|move|this|it|these|them|that|to|for|on|into)\b/i',
            $message
        );
    }

    private static function looksLikeIntakeStart(string $message): bool
    {
        return (bool) preg_match(
            '/\b(start intake|client intake|onboard(?:ing)? (?:a )?client|new client interview|begin onboarding)\b/',
            $message
        );
    }

    private static function looksLikeClientCreateStart(string $message): bool
    {
        if (self::looksLikeIntakeStart($message)) {
            return false;
        }

        return (bool) preg_match(
            '/\b(create|add|register)\b.*\b(?:new\s+)?client\b|\bnew client\b|\bclient signup\b/',
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

        if (AssistantPracticeFaq::matches($message)) {
            return true;
        }

        if (AssistantKnowledge::looksLikeSystemQuery($message)) {
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

    public static function looksLikeDocumentScan(string $message): bool
    {
        $message = strtolower(trim($message));

        if ($message === '') {
            return false;
        }

        if (preg_match('/\b(upload|attach|save|store)\b.*\b(case|matter)\b/i', $message)
            || preg_match('/\b(case|matter)\b.*\b(upload|attach|save|store)\b/i', $message)
            || AssistantRouter::looksLikeCaseDocumentUpload($message)) {
            return false;
        }

        if ((bool) preg_match(
            '/\b(scan|ocr|read|extract|analyse|analyze).*\b(pdf|document|file|image|photo|pic|upload|letter|invoice|attachment)\b/',
            $message
        )) {
            return true;
        }

        if ((bool) preg_match('/\b(scan pdf|scan (?:this )?doc|extract details?|get details?|pull details?|summarize (?:this )?doc(?:ument)?)\b/', $message)) {
            return true;
        }

        if ((bool) preg_match(
            '/\b(summarize|summary of|sum up|give (?:me )?(?:an )?overview of|main points? (?:in|from|of))\b.*\b(it|this|upload|attachment|document|doc|file|letter|pdf|screenshot|image)\b/',
            $message
        )) {
            return true;
        }

        if ((bool) preg_match('/\b(summarize it|summarize this|what(?:\'s| is) in (?:this|the) (?:doc|document|file|upload|attachment))\b/', $message)) {
            return true;
        }

        return (bool) preg_match('/\b(what does (?:this|the) (?:doc|document|pdf|letter) say)\b/', $message);
    }

    public static function looksLikeSearch(string $message): bool
    {
        $lower = assistantMatchText($message);

        return (bool) preg_match(
            '/\b(find|search|look up|lookup|show me|list|who is|where is)\b/',
            $lower
        ) && (bool) preg_match(
            '/\b(clients?|cases?|invoices?|receipts?|payments?|documents?|uploads?)\b/',
            $lower
        );
    }

    public static function matchDashboardTopic(string $message): ?string
    {
        return self::matchDashboardTopicRules(assistantMatchText($message));
    }

    private static function matchDashboardTopicRules(string $message): ?string
    {
        $rules = [
            'client_count' => '/\b(how many|number of|total|count of)\s+clients?\b|\bclient count\b|\bclients?\s+(?:do\s+we\s+have|count)\b/',
            'active_cases' => '/\bactive cases?\b|\b(open|in[- ]progress) cases?\b|\bcases? (?:open|in progress)\b|\blist active cases?\b/',
            'total_revenue' => '/\b(total revenue|our revenue|(?:what about|how is|how\'s)\s+(?:our\s+)?revenue|overall earnings|total earnings|how much (?:have we )?earned|what is our (?:total )?revenue)\b/',
            'upcoming_appointments' => '/\b(upcoming appointments?|next appointments?|appointment schedule|calendar schedule|show upcoming appointments?)\b/',
            'recent_payments' => '/\b(recent payments?|latest payments?|last payments?|list recent payments?)\b/',
            'overdue_invoices' => '/\b(overdue invoices?|unpaid invoices? past due|show overdue invoices?)\b/',
            'outstanding_balance' => '/\b(outstanding balance|accounts receivable|how much (?:is )?outstanding|total outstanding)\b/',
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
        if (preg_match('/\b(delete|remove)\b.*\bpayment\b/i', $message)) {
            return 'delete_payment';
        }
        if (preg_match('/\b(delete|remove)\b.*\binvoice\b/i', $message)) {
            return 'delete_invoice';
        }
        if (preg_match('/\b(delete|remove)\b.*\b(document|file)\b/i', $message)) {
            return 'delete_document';
        }
        if (preg_match('/\b(delete|remove)\b.*\bcase\b/i', $message)) {
            return 'delete_case';
        }

        if (preg_match('/\b(record|log|enter|post)\b.*\bpayment\b/i', $message)
            || preg_match('/\bpayment\b.*\b(for|on|against)\b/i', $message)) {
            return 'record_payment';
        }
        if (preg_match('/\b(send|email)\b.*\binvoice\b/i', $message)) {
            return 'send_invoice';
        }
        if (preg_match('/\b(generate|create|issue|make)\b.*\binvoice\b/i', $message)) {
            return 'generate_invoice';
        }
        if (preg_match('/\b(add|save|write|post)\b.*\b(note|comment)\b/i', $message)
            || preg_match('/\bcase\b.*\b(note|comment)\b/i', $message)) {
            return 'add_case_note';
        }

        if (assistantExtractCaseReferenceFromMessage($message) !== ''
            && preg_match('/\b(upload|attach|add|save|store|put|send|file|copy|move)\b/i', $message)) {
            return 'upload_case_document';
        }

        if (preg_match(
            '/\b(upload|attach|add|save|store|put)\b.*\b(this|it|these|them|that|document|file|pdf|letter|docx?|image|photo|attachment|scan)\b.*\b(case|matter)\b/i',
            $message
        )
            || preg_match(
                '/\b(case|matter)\b.*\b(upload|attach|add|save|store|put)\b.*\b(document|file|pdf|letter|docx?|image|photo|attachment)\b/i',
                $message
            )
            || preg_match('/\b(upload|attach|add|save|store|put)\b.*\b(to|onto|into)\b.*\b(case|matter)\b/i', $message)
            || preg_match('/\bupload\b.*\bto\s+(?:the\s+)?case\b/i', $message)
            || preg_match('/\bsave\b.*\b(?:to|on)\s+(?:the\s+)?case\b/i', $message)
            || preg_match('/\bfile\b.*\b(?:to|on|into)\b.*\b(case|matter)\b/i', $message)) {
            return 'upload_case_document';
        }
        if (self::looksLikeClientCreateStart($message)) {
            return 'create_client';
        }
        if (preg_match('/\b(create|open|start|make)\b.*\b(case|matter)\b/', $message)
            && !preg_match('/\b(document|file|pdf)\b/', $message)) {
            return 'create_case';
        }
        if (preg_match('/\b(?:new|another)\s+(?:case|matter)\b/', $message)
            && preg_match('/\b(create|make|open|start|need|want|add)\b/', $message)) {
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
        if (preg_match('/\b(schedule|book|set up|create|make)\b.*\b(appointment|meeting|slot)\b/', $message)
            || preg_match('/\b(appointment|meeting)\b.*\b(schedule|book|set up|create|make)\b/', $message)
            || preg_match('/\b(can|could)\s+(?:you|u)\s+schedule\b.*\b(appointment|meeting)\b/', $message)
            || preg_match('/\bbook\b.*\bfor\b.+\b(tomorrow|today|monday|tuesday|wednesday|thursday|friday|saturday|sunday|\d{1,2}[:\/]|\d{1,2}\s*(?:am|pm))\b/i', $message)) {
            if (AssistantAppointmentSchedule::isActive()) {
                AssistantAppointmentSchedule::clear();
            }

            return 'schedule_appointment';
        }
        if (preg_match('/\b(mark|clear|dismiss)\b.*\b(notifications?|alerts?)\b.*\b(read|all)?\b/', $message)
            || preg_match('/\bmark all (?:notifications?|alerts?) read\b/', $message)) {
            return 'mark_notifications_read';
        }
        if (preg_match('/\b(draft|generate|write|prepare)\b.*\b(client letter|engagement letter|letter)\b/', $message)
            || preg_match('/\b(client letter|engagement letter)\b.*\b(for|on)\b.*\bcase\b/', $message)) {
            return 'draft_client_letter';
        }

        return null;
    }

    private static function matchKnowledgeTopic(string $message): ?string
    {
        if (preg_match('/\b(calculate|computation|math|average|percent|percentage|increase|decrease|formula)\b/', $message)
            && preg_match('/\d/', $message)) {
            return 'calculation';
        }

        if (preg_match('/\b(where (?:is|are|do i)|navigate|find (?:the )?(?:settings|payments|cases|clients))\b/', $message)) {
            return 'system_qa';
        }

        if (preg_match('/\bhow (?:do i|to)\b/', $message)
            && preg_match('/\b(settings|payments|cases|clients|dashboard|sidebar|portal|navigate)\b/', $message)) {
            return 'system_qa';
        }

        return null;
    }
}
