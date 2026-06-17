<?php

declare(strict_types=1);

class AssistantCompliance
{
    public static function messageNeedsScreening(string $message): bool
    {
        if (AssistantKnowledge::looksLikeDefinitionQuery($message)) {
            return false;
        }

        return (bool) preg_match(
            '/\b(birth\s*date|date of birth|dob|minor|under 18|director|proxy|executor|representative|on behalf|corporate resolution|power of attorney)\b/i',
            $message
        );
    }

    /**
     * @return list<array{level: string, title: string, message: string}>
     */
    public static function screenText(string $text): array
    {
        $alerts = [];

        if ($minor = self::detectMinor($text)) {
            $alerts[] = [
                'level' => 'critical',
                'title' => 'Minor alert',
                'message' => $minor,
            ];
        }

        if ($capacity = self::detectRepresentativeCapacity($text)) {
            $alerts[] = [
                'level' => 'warning',
                'title' => 'Capacity verification',
                'message' => $capacity,
            ];
        }

        return $alerts;
    }

    /** @return array{content: string, alerts: list<array<string, string>>} */
    public static function handle(string $message): array
    {
        $alerts = self::screenText($message);

        if ($alerts === []) {
            return [
                'content' => 'No compliance risks detected in that message. Upload a document or continue intake if you need deeper screening.',
                'alerts' => [],
            ];
        }

        $lines = ['**Compliance screening results**', ''];
        foreach ($alerts as $alert) {
            $lines[] = '• **' . $alert['title'] . '** — ' . $alert['message'];
        }

        return [
            'content' => implode("\n", $lines),
            'alerts' => $alerts,
        ];
    }

    private static function detectMinor(string $text): ?string
    {
        if (!preg_match('/\b(?:dob|date of birth|born(?:\s+on)?|birth\s*date)\b/i', $text)) {
            return null;
        }

        if (!preg_match('/\b(?:dob|date of birth|born(?:\s+on)?|birth\s*date)\b[:\s]*([^\n,.;]{4,40})/i', $text, $matches)) {
            return null;
        }

        $candidate = trim($matches[1] ?? '');
        $parsed = parseFlexibleDateTime($candidate, false);
        if ($parsed === '') {
            return null;
        }

        $age = (int) floor((time() - strtotime($parsed)) / (365.25 * 86400));
        if ($age >= 0 && $age < 18) {
            return "A birth date suggests the signer may be **{$age} years old**. Verify age and ID before notarization.";
        }

        return null;
    }

    private static function detectRepresentativeCapacity(string $text): ?string
    {
        if (!preg_match(
            '/\b(as (?:a )?)?(director|proxy|executor|attorney[- ]in[- ]fact|representative|agent|on behalf of|for and on behalf|company signatory|corporate officer)\b/i',
            $text,
            $matches
        )) {
            return null;
        }

        $role = ucfirst(strtolower($matches[2] ?? $matches[1] ?? 'representative'));

        return "Client may be signing as **{$role}**. Request supporting authorization (e.g. corporate resolution, power of attorney, letters of executorship) before proceeding.";
    }
}
