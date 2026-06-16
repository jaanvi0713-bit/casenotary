<?php

declare(strict_types=1);

class AssistantIntake
{
    private const SESSION_KEY = 'assistant_intake';

    /** @var list<array{key: string, prompt: string}> */
    private const STEPS = [
        ['key' => 'full_name', 'prompt' => 'What is the client’s **full legal name**?'],
        ['key' => 'document_type', 'prompt' => 'What **document or deed type** is this transaction for?'],
        ['key' => 'id_available', 'prompt' => 'Does the client have a **valid government ID** available? (yes/no)'],
        ['key' => 'witness_status', 'prompt' => 'Will a **witness** be present? (yes/no/not required)'],
        ['key' => 'signing_capacity', 'prompt' => 'Is the client signing **in person for themselves**, or as a **representative** (director, proxy, executor, etc.)?'],
        ['key' => 'notes', 'prompt' => 'Any other details for intake? (or type **skip**)'],
    ];

    public static function isActive(): bool
    {
        return !empty($_SESSION[self::SESSION_KEY]['active']);
    }

    public static function clear(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }

    /** @return array{content: string, type: string, draft?: array<string, mixed>, alerts?: list<array<string, string>>} */
    public static function handle(string $message): array
    {
        $state = $_SESSION[self::SESSION_KEY] ?? null;

        if (!is_array($state) || empty($state['active'])) {
            $_SESSION[self::SESSION_KEY] = [
                'active' => true,
                'step' => 0,
                'data' => [],
            ];

            return [
                'content' => "**Client intake started.** I’ll ask one question at a time.\n\n" . self::STEPS[0]['prompt'],
                'type' => 'onboarding',
            ];
        }

        $step = (int) ($state['step'] ?? 0);
        $answer = trim($message);

        if ($answer !== '' && strtolower($answer) !== 'skip') {
            $_SESSION[self::SESSION_KEY]['data'][self::STEPS[$step]['key']] = $answer;
        }

        $alerts = $answer !== '' ? AssistantCompliance::screenText($answer) : [];
        $step++;

        if ($step >= count(self::STEPS)) {
            return self::completeIntake($alerts);
        }

        $_SESSION[self::SESSION_KEY]['step'] = $step;

        return [
            'content' => self::STEPS[$step]['prompt'],
            'type' => 'onboarding',
            'alerts' => $alerts,
        ];
    }

    /**
     * @param list<array<string, string>> $alerts
     * @return array{content: string, type: string, draft?: array<string, mixed>, alerts?: list<array<string, string>>}
     */
    private static function completeIntake(array $alerts): array
    {
        $data = $_SESSION[self::SESSION_KEY]['data'] ?? [];
        self::clear();

        $summaryText = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '';
        $alerts = array_merge($alerts, AssistantCompliance::screenText($summaryText));

        $lines = [
            '**Intake complete.** Here is the structured summary:',
            '',
            '• **Name:** ' . ($data['full_name'] ?? '—'),
            '• **Document:** ' . ($data['document_type'] ?? '—'),
            '• **ID available:** ' . ($data['id_available'] ?? '—'),
            '• **Witness:** ' . ($data['witness_status'] ?? '—'),
            '• **Capacity:** ' . ($data['signing_capacity'] ?? '—'),
            '• **Notes:** ' . ($data['notes'] ?? '—'),
        ];

        $clientId = !empty($data['full_name']) ? assistantResolveClientId((string) $data['full_name']) : null;
        if ($clientId) {
            $payload = [
                'title' => ucfirst((string) ($data['document_type'] ?? 'Notary matter')),
                'description' => 'Intake notes: ' . json_encode($data, JSON_UNESCAPED_UNICODE),
                'client_id' => $clientId,
                'service_type' => (string) ($data['document_type'] ?? 'Notarization'),
                'service_fee' => 0,
            ];
            $preview = [
                'Client' => (string) $data['full_name'],
                'Document' => (string) ($data['document_type'] ?? '—'),
                'Capacity' => (string) ($data['signing_capacity'] ?? '—'),
                'ID' => (string) ($data['id_available'] ?? '—'),
            ];

            $draftId = bin2hex(random_bytes(8));
            $draft = [
                'id' => $draftId,
                'action' => 'create_case',
                'payload' => $payload,
                'preview' => $preview,
                'created_at' => time(),
            ];
            $_SESSION['assistant_drafts'][$draftId] = $draft;

            return [
                'content' => implode("\n", $lines) . "\n\nI matched an existing client. You can **confirm** to create a case from this intake.",
                'type' => 'draft',
                'draft' => $draft,
                'alerts' => $alerts,
            ];
        }

        return [
            'content' => implode("\n", $lines) . "\n\n_Create the client first, then ask me to **create a case** from this intake._",
            'type' => 'onboarding',
            'alerts' => $alerts,
        ];
    }
}
