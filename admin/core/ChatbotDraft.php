<?php

declare(strict_types=1);

function chatbotRememberDraft(string $text): void
{
    $plain = chatbotPlainTextFromDraft($text);
    if ($plain === '') {
        return;
    }

    $_SESSION['chatbot_last_draft'] = [
        'text'       => $plain,
        'created_at' => time(),
    ];
}

function chatbotGetLastDraft(): ?string
{
    $draft = $_SESSION['chatbot_last_draft'] ?? null;
    if (!is_array($draft)) {
        return null;
    }

    $text = trim((string) ($draft['text'] ?? ''));

    return $text !== '' ? $text : null;
}

function chatbotPlainTextFromDraft(string $markdown): string
{
    $text = preg_replace('/\*\*([^*]+)\*\*/', '$1', $markdown) ?? $markdown;
    $text = preg_replace('/\*([^*]+)\*/', '$1', $text) ?? $text;
    $text = preg_replace('/^#+\s*/m', '', $text) ?? $text;
    $text = preg_replace('/^_Copy into.*$/mi', '', $text) ?? $text;
    $text = preg_replace('/^Reply \*\*yes\*\*.*$/mi', '', $text) ?? $text;

    return trim($text);
}
