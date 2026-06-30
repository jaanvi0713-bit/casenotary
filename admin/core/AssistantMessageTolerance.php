<?php

declare(strict_types=1);

/**
 * Makes assistant routing more forgiving of typos, shorthand, and missing trigger words.
 */
class AssistantMessageTolerance
{
    /** @var array<string, string> */
    private const TYPO_MAP = [
        'apointment' => 'appointment',
        'appoitment' => 'appointment',
        'appointmnt' => 'appointment',
        'appointmnet' => 'appointment',
        'appointemnt' => 'appointment',
        'apointments' => 'appointments',
        'appt' => 'appointment',
        'scheduel' => 'schedule',
        'scedule' => 'schedule',
        'schedul' => 'schedule',
        'shedule' => 'schedule',
        'schdule' => 'schedule',
        'tommorow' => 'tomorrow',
        'tommorrow' => 'tomorrow',
        'tomorow' => 'tomorrow',
        'tomo' => 'tomorrow',
        'clinet' => 'client',
        'cleint' => 'client',
        'clents' => 'clients',
        'clint' => 'client',
        'custmer' => 'client',
        'invocie' => 'invoice',
        'invice' => 'invoice',
        'invocies' => 'invoices',
        'reciept' => 'receipt',
        'recipt' => 'receipt',
        'notifcation' => 'notification',
        'notifcations' => 'notifications',
        'notificaton' => 'notification',
        'casses' => 'cases',
        'serch' => 'search',
        'serach' => 'search',
        'seraching' => 'searching',
        'summerize' => 'summarize',
        'summery' => 'summary',
        'sumarize' => 'summarize',
        'overue' => 'overdue',
        'revenu' => 'revenue',
        'revnue' => 'revenue',
        'dashbord' => 'dashboard',
        'dashbaord' => 'dashboard',
        'intke' => 'intake',
        'intak' => 'intake',
        'onboardng' => 'onboarding',
        'mesage' => 'message',
        'remindr' => 'reminder',
        'cancle' => 'cancel',
        'cancell' => 'cancel',
        'confrim' => 'confirm',
        'comfirm' => 'confirm',
        'reschdule' => 'reschedule',
        'documnet' => 'document',
        'docuemnt' => 'document',
        'docment' => 'document',
        'uplaod' => 'upload',
        'attachement' => 'attachment',
        'attch' => 'attach',
        'creat' => 'create',
        'crate' => 'create',
        'updte' => 'update',
        'updat' => 'update',
        'actve' => 'active',
        'activ' => 'active',
        'outstading' => 'outstanding',
        'outstandng' => 'outstanding',
        'apostile' => 'apostille',
        'affadavit' => 'affidavit',
        'meny' => 'many',
        'mny' => 'many',
        'hw' => 'how',
        'wat' => 'what',
        'wht' => 'what',
        'wen' => 'when',
        'lst' => 'list',
        'lis' => 'list',
        'shcedule' => 'schedule',
        'bok' => 'book',
        'boook' => 'book',
        'meting' => 'meeting',
        'meetng' => 'meeting',
        'calender' => 'calendar',
        'calandar' => 'calendar',
        'overdue' => 'overdue',
        'unpaid' => 'unpaid',
        'notif' => 'notification',
        'ntake' => 'intake',
    ];

    /** @var list<string> */
    private const KEYWORDS = [
        'appointment', 'appointments', 'schedule', 'book', 'client', 'clients',
        'case', 'cases', 'invoice', 'invoices', 'payment', 'payments', 'receipt',
        'receipts', 'document', 'documents', 'upload', 'search', 'find', 'list',
        'create', 'cancel', 'confirm', 'reschedule', 'intake', 'dashboard',
        'revenue', 'overdue', 'outstanding', 'notification', 'notifications',
        'reminder', 'summarize', 'summary', 'scan', 'active', 'tomorrow', 'today',
        'meeting', 'attach', 'update', 'unpaid',
    ];

    /** @var list<string> */
    private const PROTECTED_TOKENS = [
        'show', 'get', 'tell', 'give', 'need', 'want', 'please', 'yes', 'no',
        'my', 'all', 'any', 'the', 'this', 'that', 'with', 'from', 'about',
        'have', 'has', 'had', 'are', 'was', 'were', 'will', 'can', 'could',
        'would', 'should', 'may', 'might', 'also', 'just', 'only', 'some',
    ];

    public static function forMatching(string $message): string
    {
        $text = strtolower(trim($message));
        if ($text === '') {
            return '';
        }

        $text = self::expandPhrases($text);
        $text = self::applyTypoMap($text);
        $text = self::fuzzyCorrectTokens($text);
        $text = self::inferMissingIntent($text);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private static function expandPhrases(string $text): string
    {
        $replacements = [
            '/\bcan u\b/' => 'can you',
            '/\bcould u\b/' => 'could you',
            '/\bpls\b/' => 'please',
            '/\bplz\b/' => 'please',
            '/\bwanna\b/' => 'want to',
            '/\bgonna\b/' => 'going to',
            '/\bgimme\b/' => 'give me',
            '/\blook up\b/' => 'search',
            '/\blookup\b/' => 'search',
            '/\bwhats\b/' => 'what is',
            '/\bwhat s\b/' => 'what is',
            '/\bhows\b/' => 'how is',
            '/\bhow s\b/' => 'how is',
            '/\bwhen s\b/' => 'when is',
            '/\bclients count\b/' => 'how many clients',
            '/\bclient count\b/' => 'how many clients',
            '/\bcase count\b/' => 'how many cases',
            '/\bactive case\b/' => 'active cases',
            '/\bover due\b/' => 'overdue',
            '/\bun paid\b/' => 'unpaid',
            '/\bstart onboard\b/' => 'start intake',
            '/\bnew clinet\b/' => 'new client',
            '/\bnew cleint\b/' => 'new client',
        ];

        foreach ($replacements as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text) ?? $text;
        }

        return $text;
    }

    private static function applyTypoMap(string $text): string
    {
        $tokens = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $out = [];

        foreach ($tokens as $token) {
            if (trim($token) === '' || preg_match('/^\s+$/u', $token)) {
                $out[] = $token;
                continue;
            }

            $bare = strtolower(trim($token, ".,!?;:'\""));
            $out[] = self::TYPO_MAP[$bare] ?? $token;
        }

        return implode('', $out);
    }

    private static function fuzzyCorrectTokens(string $text): string
    {
        $tokens = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $out = [];

        foreach ($tokens as $token) {
            if (trim($token) === '' || preg_match('/^\s+$/u', $token)) {
                $out[] = $token;
                continue;
            }

            $bare = strtolower(trim($token, ".,!?;:'\""));
            if ($bare === '' || preg_match('/^\d/', $bare) || str_contains($bare, '@')) {
                $out[] = $token;
                continue;
            }

            if (in_array($bare, self::KEYWORDS, true)
                || isset(self::TYPO_MAP[$bare])
                || in_array($bare, self::PROTECTED_TOKENS, true)) {
                $out[] = $token;
                continue;
            }

            $corrected = self::closestKeyword($bare);
            $out[] = $corrected ?? $token;
        }

        return implode('', $out);
    }

    private static function closestKeyword(string $token): ?string
    {
        $len = strlen($token);
        if ($len < 4) {
            return null;
        }

        $maxDistance = $len <= 5 ? 1 : 2;
        $best = null;
        $bestDistance = $maxDistance + 1;

        foreach (self::KEYWORDS as $keyword) {
            if (abs(strlen($keyword) - $len) > $maxDistance) {
                continue;
            }

            $distance = levenshtein($token, $keyword);
            if ($distance > $maxDistance) {
                continue;
            }

            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $keyword;
            }
        }

        return $bestDistance <= $maxDistance ? $best : null;
    }

    private static function inferMissingIntent(string $text): string
    {
        if (preg_match(
            '/\b(appointment|schedule|book|meeting|cancel|confirm|reschedule|intake|create|search|find|list|how many|dashboard|summarize|scan|upload)\b/',
            $text
        )) {
            return $text;
        }

        if (self::looksLikeScheduleFragment($text)) {
            return 'schedule appointment ' . $text;
        }

        if (preg_match('/\b(named|called)\s+[a-z]/', $text)
            && preg_match('/\b(client|case|invoice|document)s?\b/', $text)) {
            return 'find ' . $text;
        }

        if (preg_match('/\b(count|total)\s+(clients?|cases?|invoices?|appointments?)\b/', $text)) {
            return 'how many ' . preg_replace('/\b(count|total)\s+/', '', $text);
        }

        return $text;
    }

    private static function looksLikeScheduleFragment(string $text): bool
    {
        if (!function_exists('chatbotMessageLooksLikeDateOrTime') || !chatbotMessageLooksLikeDateOrTime($text)) {
            return false;
        }

        if (preg_match('/\bfor\s+[a-z][\w\'-]+/i', $text)) {
            return true;
        }

        if (preg_match('/\b[a-z][\w\'-]+(?:\s+[a-z][\w\'-]+)?\s+(?:for\s+)?(tomorrow|today|next\s+\w+|monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/i', $text)) {
            return true;
        }

        if (preg_match('/\b(tomorrow|today)\s+(?:at\s+)?\d{1,2}/i', $text)) {
            return true;
        }

        $clientName = assistantExtractClientNameFromActionMessage($text);

        return $clientName !== '';
    }
}
