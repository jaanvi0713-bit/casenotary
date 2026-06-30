<?php

declare(strict_types=1);

class AssistantClientCreate
{
    private const SESSION_KEY = 'assistant_client_create';

    /** @var list<array{key: string, prompt: string, phase: string}> */
    private const CLIENT_STEPS = [
        [
            'key'    => 'full_name',
            'prompt' => 'What is the client’s **full legal name**? (first and last name required)',
            'phase'  => 'client',
        ],
        [
            'key'    => 'email',
            'prompt' => 'What is the client’s **email address**?',
            'phase'  => 'client',
        ],
        [
            'key'    => 'phone',
            'prompt' => 'What is the client’s **phone number**? (include country code if international)',
            'phase'  => 'client',
        ],
        [
            'key'    => 'address_line',
            'prompt' => 'What is the client’s **full postal address**? Enter as: **street, city, state/region, postal code, country**',
            'phase'  => 'client',
        ],
    ];

    /** @var list<array{key: string, prompt: string, phase: string}> */
    private const CASE_STEPS = [
        [
            'key'    => 'case_title',
            'prompt' => 'What **case title** should I use? (e.g. Smith Property Transfer)',
            'phase'  => 'case',
        ],
        [
            'key'    => 'service_type',
            'prompt' => 'What **type of service** is this? (e.g. deed, jurat, POA, notarization)',
            'phase'  => 'case',
        ],
        [
            'key'    => 'case_description',
            'prompt' => 'Add **case notes or description** (what the client needs done). Type **none** if not applicable.',
            'phase'  => 'case',
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
        $caseContext = self::normalizeCaseContext($caseContext);

        if ($prefillName !== '') {
            $names = assistantSplitPersonName($prefillName);
            if ($names['first_name'] !== '' && $names['last_name'] !== '') {
                $data['full_name'] = $prefillName;
                $step = 1;
            }
        }

        $_SESSION[self::SESSION_KEY] = [
            'active' => true,
            'step'   => $step,
            'data'   => $data,
            'case'   => $caseContext,
            'steps'  => self::buildStepPlan($caseContext, $data),
        ];

        return self::promptForStep($step, $caseContext, $prefillName !== '' && $step === 1);
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
        $caseContext = is_array($state['case'] ?? null) ? self::normalizeCaseContext($state['case']) : [];
        $steps = is_array($state['steps'] ?? null) ? $state['steps'] : self::buildStepPlan($caseContext, is_array($state['data'] ?? null) ? $state['data'] : []);
        $current = $steps[$step] ?? null;

        if ($current === null) {
            return self::completeWizard([]);
        }

        if ($answer === '' || strtolower($answer) === 'skip') {
            return [
                'content' => 'This field is **required** before I can create the '
                    . (self::wantsCase($caseContext) ? 'client and case' : 'client')
                    . ".\n\n" . $current['prompt'],
                'type'    => 'onboarding',
            ];
        }

        $saveResult = self::saveStepAnswer($current['key'], $answer);
        if ($saveResult !== null) {
            return $saveResult;
        }

        $alerts = AssistantCompliance::screenText($answer);
        $step++;

        if ($step >= count($steps)) {
            return self::completeWizard($alerts);
        }

        $_SESSION[self::SESSION_KEY]['step'] = $step;

        $response = self::promptForStep($step, $caseContext, false);
        if ($alerts !== []) {
            $response['alerts'] = $alerts;
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $caseContext
     * @param array<string, mixed> $data
     * @return list<array{key: string, prompt: string, phase: string}>
     */
    private static function buildStepPlan(array $caseContext, array $data): array
    {
        $steps = self::CLIENT_STEPS;

        if (!self::wantsCase($caseContext)) {
            return $steps;
        }

        foreach (self::CASE_STEPS as $caseStep) {
            $steps[] = $caseStep;
        }

        return $steps;
    }

    /**
     * @return array{content: string, type: string}|null
     */
    private static function saveStepAnswer(string $key, string $answer): ?array
    {
        if ($key === 'full_name') {
            $names = assistantSplitPersonName($answer);
            if ($names['first_name'] === '' || $names['last_name'] === '') {
                return [
                    'content' => 'Please enter the client’s **first and last name** (e.g. Jane Smith).',
                    'type'    => 'onboarding',
                ];
            }
            $_SESSION[self::SESSION_KEY]['data']['full_name'] = $answer;

            return null;
        }

        if ($key === 'email') {
            $email = assistantExtractEmailFromText($answer);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return [
                    'content' => 'Please enter a valid **email address** (example: `name@example.com`).',
                    'type'    => 'onboarding',
                ];
            }
            if (ClientService::emailExistsPublic($email)) {
                return [
                    'content' => 'A client with email **' . $email . '** already exists. Use a different email or open the existing client.',
                    'type'    => 'onboarding',
                ];
            }
            $_SESSION[self::SESSION_KEY]['data']['email'] = $email;

            return null;
        }

        if ($key === 'phone') {
            $phone = assistantExtractPhoneFromText($answer);
            if ($phone === '') {
                $phone = trim($answer);
            }
            $digits = preg_replace('/\D+/', '', $phone) ?? '';
            if (strlen($digits) < 7) {
                return [
                    'content' => 'Please enter a valid **phone number** (at least 7 digits).',
                    'type'    => 'onboarding',
                ];
            }
            $_SESSION[self::SESSION_KEY]['data']['phone'] = $phone;

            return null;
        }

        if ($key === 'address_line') {
            $parsed = assistantParsePostalAddress($answer);
            if ($parsed === null) {
                return [
                    'content' => 'I need the **complete address** in this format: **street, city, state/region, postal code, country**.',
                    'type'    => 'onboarding',
                ];
            }
            $_SESSION[self::SESSION_KEY]['data'] = array_merge(
                $_SESSION[self::SESSION_KEY]['data'] ?? [],
                $parsed
            );

            return null;
        }

        if ($key === 'case_title') {
            if (mb_strlen($answer) < 3) {
                return [
                    'content' => 'Please enter a **case title** (at least 3 characters).',
                    'type'    => 'onboarding',
                ];
            }
            $_SESSION[self::SESSION_KEY]['data']['case_title'] = $answer;

            return null;
        }

        if ($key === 'service_type') {
            if (mb_strlen($answer) < 2) {
                return [
                    'content' => 'Please enter the **service type** (e.g. deed, jurat, POA).',
                    'type'    => 'onboarding',
                ];
            }
            $_SESSION[self::SESSION_KEY]['data']['service_type'] = $answer;

            return null;
        }

        if ($key === 'case_description') {
            $description = strtolower($answer) === 'none' ? '' : $answer;
            $_SESSION[self::SESSION_KEY]['data']['case_description'] = $description;

            return null;
        }

        $_SESSION[self::SESSION_KEY]['data'][$key] = $answer;

        return null;
    }

    /**
     * @param array<string, mixed> $caseContext
     * @return array{content: string, type: string}
     */
    private static function promptForStep(int $step, array $caseContext, bool $namePrefilled): array
    {
        $state = $_SESSION[self::SESSION_KEY] ?? [];
        $steps = is_array($state['steps'] ?? null)
            ? $state['steps']
            : self::buildStepPlan($caseContext, is_array($state['data'] ?? null) ? $state['data'] : []);
        $wantsCase = self::wantsCase($caseContext);
        $intro = $wantsCase
            ? "**Let’s set up the new client first, then the case.** All fields are required.\n\n"
            : "**Let’s add a new client.** All fields below are required.\n\n";

        if ($namePrefilled && $step === 1) {
            $name = (string) ($_SESSION[self::SESSION_KEY]['data']['full_name'] ?? '');
            $intro = "I don’t have **{$name}** on file yet. {$intro}";
        }

        $current = $steps[$step] ?? null;
        if (($current['phase'] ?? '') === 'case' && $step > 0 && ($steps[$step - 1]['phase'] ?? '') === 'client') {
            $intro = "**Client details complete.** Now for the case:\n\n";
        }

        return [
            'content' => $intro . ($current['prompt'] ?? ''),
            'type'    => 'onboarding',
        ];
    }

    /**
     * @param array<string, mixed> $caseContext
     */
    private static function wantsCase(array $caseContext): bool
    {
        return !empty($caseContext['create_case']);
    }

    /**
     * @param array<string, mixed> $caseContext
     * @return array<string, mixed>
     */
    private static function normalizeCaseContext(array $caseContext): array
    {
        $caseContext['create_case'] = !empty($caseContext['create_case']);

        return $caseContext;
    }

    /**
     * @param list<array<string, string>> $alerts
     * @return array{content: string, type: string, draft?: array<string, mixed>, alerts?: list<array<string, string>>}
     */
    private static function completeWizard(array $alerts): array
    {
        $state = $_SESSION[self::SESSION_KEY] ?? [];
        $data = is_array($state['data'] ?? null) ? $state['data'] : [];
        $caseContext = is_array($state['case'] ?? null) ? self::normalizeCaseContext($state['case']) : [];
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

        try {
            $clientPayload = ClientService::validatedProfile($clientPayload);
        } catch (RuntimeException $e) {
            return [
                'content' => $e->getMessage() . ' Say **create new client** to start again.',
                'type'    => 'text',
            ];
        }

        if (ClientService::emailExistsPublic($clientPayload['email'])) {
            return [
                'content' => 'A client with email **' . $clientPayload['email'] . '** already exists.',
                'type'    => 'text',
            ];
        }

        $preview = [
            'Name'    => trim($clientPayload['first_name'] . ' ' . $clientPayload['last_name']),
            'Email'   => $clientPayload['email'],
            'Phone'   => $clientPayload['phone'],
            'Address' => $clientPayload['address'] . ', ' . $clientPayload['city'] . ', ' . $clientPayload['state'],
            'Postal'  => $clientPayload['zip_code'],
            'Country' => $clientPayload['country'],
        ];

        $wantsCase = self::wantsCase($caseContext);
        if ($wantsCase) {
            $title = trim((string) ($data['case_title'] ?? ''));
            $serviceType = trim((string) ($data['service_type'] ?? ''));
            $description = array_key_exists('case_description', $data)
                ? (string) $data['case_description']
                : '';

            if ($title === '' || $serviceType === '') {
                return [
                    'content' => 'Case **title** and **service type** are required. Say **create a case for [client name]** to start again.',
                    'type'    => 'text',
                ];
            }

            $payload = [
                'client' => $clientPayload,
                'case'   => [
                    'title'        => $title,
                    'description'  => $description,
                    'service_type' => $serviceType,
                    'service_fee'  => 0,
                ],
            ];

            $preview['Case title'] = $title;
            $preview['Service'] = $serviceType;
            $preview['Description'] = $description !== '' ? $description : '—';

            $response = self::storeDraft(
                'create_client_and_case',
                $payload,
                $preview,
                'Review the new **client and case** below. Edit any field in the draft or say _change country to UK_ before you click **Confirm**.'
            );
        } else {
            $response = self::storeDraft(
                'create_client',
                ['client' => $clientPayload],
                $preview,
                'Review the new **client** below. Edit any field in the draft or say _change country to UK_ before you click **Confirm**.'
            );
        }

        if ($alerts !== []) {
            $response['alerts'] = $alerts;
        }

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
            'editable'   => AssistantDraftEdit::editableKeys($action),
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
