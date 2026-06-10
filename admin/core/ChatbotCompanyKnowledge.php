<?php

declare(strict_types=1);

class ChatbotCompanyKnowledge
{
    public static function ensureSchema(): void
    {
        if (!Database::tableExists('company_settings')) {
            return;
        }

        if (!Database::columnExists('company_settings', 'ai_knowledge')) {
            try {
                Database::query('ALTER TABLE company_settings ADD COLUMN ai_knowledge MEDIUMTEXT NULL AFTER description');
            } catch (Throwable $e) {
                // permissions or already exists
            }
        }
    }

    public static function get(): string
    {
        self::ensureSchema();

        if (!Database::columnExists('company_settings', 'ai_knowledge')) {
            return '';
        }

        $settings = getCompanySettings();

        return trim((string) ($settings['ai_knowledge'] ?? ''));
    }

    public static function save(string $content): void
    {
        self::ensureSchema();

        if (!Database::columnExists('company_settings', 'ai_knowledge')) {
            throw new RuntimeException('AI knowledge storage is not installed. Run: php admin/sql/migrate_chatbot_knowledge.php');
        }

        $settings = SettingsService::get();
        $id = (int) ($settings['id'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('Company settings not found.');
        }

        Database::query(
            'UPDATE company_settings SET ai_knowledge = ?, updated_at = NOW() WHERE id = ?',
            [trim($content) !== '' ? trim($content) : null, $id]
        );

        SettingsService::clearCache();
    }
}

function chatbotReplyFromCompanyKnowledge(string $message): ?string
{
    $knowledge = ChatbotCompanyKnowledge::get();
    if ($knowledge === '') {
        return null;
    }

    $normalized = strtolower(trim($message));
    if (!preg_match(
        '/\b(fee|fees|price|pricing|cost|hours|office hours|policy|policies|faq|location|address|'
        . 'parking|payment method|how much|what do you charge|our process|company info)\b/',
        $normalized
    )) {
        return null;
    }

    $lines = preg_split('/\R+/', $knowledge) ?: [];
    $matched = [];
    $words = array_filter(preg_split('/\s+/', preg_replace('/[^\w\s]/', ' ', $normalized)) ?: []);

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $lineLower = strtolower($line);
        foreach ($words as $word) {
            if (strlen($word) < 4) {
                continue;
            }
            if (str_contains($lineLower, $word)) {
                $matched[] = $line;
                break;
            }
        }
    }

    if ($matched === []) {
        $excerpt = mb_strimwidth($knowledge, 0, 900, '…');

        return "**From your company knowledge base:**\n\n" . $excerpt
            . "\n\n_Edit this in **Settings → AI Assistant**._";
    }

    return "**From your company knowledge base:**\n\n• " . implode("\n• ", array_slice(array_unique($matched), 0, 8))
        . "\n\n_Edit in **Settings → AI Assistant**._";
}
