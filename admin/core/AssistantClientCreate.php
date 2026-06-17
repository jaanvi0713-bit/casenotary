<?php

declare(strict_types=1);

class AssistantClientCreate
{
    private const SESSION_KEY = 'assistant_client_create';

    /** @var list<array{key: string, prompt: string}> */
    private const STEPS = [
        ['key' => 'full_name', 'prompt' => 'What is the client’s **full legal name**?'],
        ['key' => 'email', 'prompt' => 'What is the client’s **email address**?'],
        ['key' => 'phone', 'prompt' => 'What is the client’s **phone number**?'],
        [
            'key' => 'address_line',
            'prompt' => 'What is the client’s **postal address**? Enter as: **street, city, state/region, postal code, country**',
        ],
    ];

    public static function isActive(): bool
    {
        return !empty($_SESSION[self::SESSION_KEY]['active']);
    }

    public static function clear(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
    }

    /**
     * @param array<string, mixed> $caseContext
     * @return array{content: string, type: string, draft?: array<string, mixed>, alerts?: list<array<string, string>>}
     */
    public static function begin(array $caseContext = [], string $prefillName = ''): array
    {
        $data = [];
        $step = 0;
        $prefillName = trim($prefillName);

        if ($prefillName !== '') {
            $data['full_name'] = $prefillName;
            $step = 1;
        }

        $_SESSION[self::SESSION_KEY] = [
            'active' => true,
            'step'   => $step,
            'data'   => $data,
            'case'   => $caseContext,
        ];

        return self::promptForStep($step, $caseContext, $prefillName !== '');
    }

    /**
     * @return array{content: string, type: string, draft?: array<string, mixed>, alerts?: list<array<string, string>>}
     */
    public static function handle(string $message): array
    {
        $state = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($state) || empty($state['active'])) {
            return self::begin();
        }

        $step = (int) ($state['step'] ?? 0);
        $answer = trim($message);
        $caseContext = is_array($state['case'] ?? null) ? $state['case'] : [];

        if ($answer !== '' && strtolower($answer) !== 'skip') {
            $key = self::STEPS[$step]['key'] ?? '';
            if ($key === 'email') {
                $email = assistantExtractEmailFromText($answer);
                if ($email === '') {
                    return [
                        'content' => 'Please enter a valid **email address** (example: `name@example.com`).',
                        'type' => 'onboarding',
                    ];
                }
                $_SESSION[self::SESSION_KEY]['data']['email'] = $email;
            } elseif ($key === 'phone') {
                $phone = assistantExtractPhoneFromText($answer);
                if ($phone === '') {
                    $phone = $answer;
                }
                $_SESSION[self::SESSION_KEY]['data']['phone'] = $phone;
            } elseif ($key === 'address_line') {
                $parsed = assistantParsePostalAddress($answer);
                if ($parsed === null) {
                    return [
                        'content' => 'I need the full address in this format: **street, city, state, postal code, country**.',
                        'type' => 'onboarding',
                    ];
                }
                $_SESSION[self::SESSION_KEY]['data'] = array_merge($_SESSION[self::SESSION_KEY]['data'] ?? [], $parsed);
            } elseif ($key !== '') {
                $_SESSION[self::SESSION_KEY]['data'][$key] = $answer;
            }
        }

        $alerts = $answer !== '' ? AssistantCompliance::screenText($answer) : [];
        $step++;

        if ($step >= count(self::STEPS)) {
            return self::completeWizard($alerts);
        }

        $_SESSION[self::SESSION_KEY]['step'] = $step;

        $response = self::promptForStep($step, $caseContext, false);
        $response['alerts'] = $alerts;

        return $response;
    }

    /**
     * @param array<string, mixed> $caseContext
     * @return array{content: string, type: string}
     */
    private static function promptForStep(int $step, array $caseContext, bool $namePrefilled): array
    {
        $wantsCase = self::wantsCase($caseContext);
        $intro = $wantsCase
            ? "**Let’s add a new client and draft a case.** I’ll ask a few quick questions.\n\n"
            : "**Let’s add a new client.** I’ll ask a few quick questions.\n\n";

        if ($namePrefilled && $step === 1) {
            $name = (string) ($_SESSION[self::SESSION_KEY]['data']['full_name'] ?? '');
            $intro = "I don’t have **{$name}** on file yet. {$intro}";
        }

        return [
            'content' => $intro . (self::STEPS[$step]['prompt'] ?? ''),
            'type'    => 'onboarding',
        ];
    }

    /**
     * @param array<string, mixed> $caseContext
     */
    private static function wantsCase(array $caseContext): bool
    {
        return !empty($caseContext['create_case'])
            || trim((string) ($caseContext['title'] ?? '')) !== ''
            || trim((string) ($caseContext['service_type'] ?? '')) !== '';
    }

    /**
     * @param list<array<string, string>> $alerts
     * @return array{content: string, type: string, draft?: array<string, mixed>, alerts?: list<array<string, string>>}
     */
    private static function completeWizard(array $alerts): array
    {
        $state = $_SESSION[self::SESSION_KEY] ?? [];
        $data = is_array($state['data'] ?? null) ? $state['data'] : [];
        $caseContext = is_array($state['case'] ?? null) ? $state['case'] : [];
        self::clear();

        $names = assistantSplitPersonName((string) ($data['full_name'] ?? ''));
        $clientPayload = [
            'first_name' => $names['first_name'],
            'last_name'  => $names['last_name'],
            'email'      => (string) ($data['email'] ?? ''),
            'phone'      => (string) ($data['phone'] ?? ''),
            'address'    => (string) ($data['address'] ?? ''),
            'city'       => (string) ($data['city'] ?? ''),
            'state'      => (string) ($data['state'] ?? ''),
            'zip_code'   => (string) ($data['zip_code'] ?? ''),
            'country'    => (string) ($data['country'] ?? assistantDefaultClientCountry()),
        ];

        $preview = [
            'Name'    => trim($clientPayload['first_name'] . ' ' . $clientPayload['last_name']),
            'Email'   => $clientPayload['email'],
            'Phone'   => $clientPayload['phone'],
            'Address' => $clientPayload['address'] . ', ' . $clientPayload['city'],
            'Country' => $clientPayload['country'],
        ];

        $wantsCase = self::wantsCase($caseContext);
        if ($wantsCase) {
            $title = trim((string) ($caseContext['title'] ?? ''));
            if ($title === '') {
                $title = 'New notary matter';
            }
            $serviceType = trim((string) ($caseContext['service_type'] ?? ''));
            if ($serviceType === '') {
                $serviceType = 'Notarization';
            }

            $payload = [
                'client' => $clientPayload,
                'case'   => [
                    'title'        => $title,
                    'description'  => trim((string) ($caseContext['description'] ?? '')),
                    'service_type' => $serviceType,
                    'service_fee'  => 0,
                ],
            ];

            $preview['Case title'] = $title;
            $preview['Service'] = $serviceType;

            $response = self::storeDraft(
                'create_client_and_case',
                $payload,
                $preview,
                'Review the new **client and case** draft below. Nothing is saved until you click **Confirm**.'
            );
        } else {
            $response = self::storeDraft(
                'create_client',
                ['client' => $clientPayload],
                $preview,
                'Review the new **client** draft below. Nothing is saved until you click **Confirm**.'
            );
        }

        $response['alerts'] = $alerts;

        return $response;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $preview
     * @return array{content: string, type: string, draft: array<string, mixed>}
     */
    private static function storeDraft(string $action, array $payload, array $preview, string $intro): array
    {
        $draftId = bin2hex(random_bytes(8));
        $draft = [
            'id'         => $draftId,
            'action'     => $action,
            'payload'    => $payload,
            'preview'    => $preview,
            'created_at' => time(),
        ];

        $_SESSION['assistant_drafts'][$draftId] = $draft;

        return [
            'content' => $intro,
            'type'    => 'draft',
            'draft'   => $draft,
        ];
    }
}
