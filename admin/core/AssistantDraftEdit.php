<?php

declare(strict_types=1);

class AssistantDraftEdit
{
    /** @var array<string, array<string, list<string>>> */
    private const FIELD_PATHS = [
        'create_client' => [
            'Name'        => ['client', 'name'],
            'Email'       => ['client', 'email'],
            'Phone'       => ['client', 'phone'],
            'Address'     => ['client', 'address_block'],
            'Postal'      => ['client', 'zip_code'],
            'Country'     => ['client', 'country'],
        ],
        'create_client_and_case' => [
            'Name'         => ['client', 'name'],
            'Email'        => ['client', 'email'],
            'Phone'        => ['client', 'phone'],
            'Address'      => ['client', 'address_block'],
            'Postal'       => ['client', 'zip_code'],
            'Country'      => ['client', 'country'],
            'Case title'   => ['case', 'title'],
            'Service'      => ['case', 'service_type'],
            'Description'  => ['case', 'description'],
        ],
        'create_case' => [
            'Title'       => ['title'],
            'Description' => ['description'],
            'Service'     => ['service_type'],
        ],
        'update_case' => [
            'Status'      => ['status'],
            'Description' => ['description'],
        ],
        'schedule_appointment' => [
            'Client'    => ['client_name'],
            'Title'     => ['title'],
            'Starts'    => ['starts_at'],
            'Ends'      => ['ends_at'],
            'Status'    => ['status'],
        ],
        'update_appointment' => [
            'Client' => ['client_name'],
            'Title'  => ['title'],
            'Starts' => ['starts_at'],
            'Ends'   => ['ends_at'],
            'Status' => ['status'],
        ],
        'record_payment' => [
            'Amount' => ['amount'],
            'Method' => ['payment_method'],
        ],
        'add_case_note' => [
            'Note' => ['note'],
        ],
    ];

    /** @var array<string, string> */
    private const FIELD_ALIASES = [
        'country'          => 'Country',
        'email'            => 'Email',
        'e-mail'           => 'Email',
        'phone'            => 'Phone',
        'telephone'        => 'Phone',
        'mobile'           => 'Phone',
        'name'             => 'Name',
        'full name'        => 'Name',
        'client name'      => 'Name',
        'address'          => 'Address',
        'street'           => 'Address',
        'postal'           => 'Postal',
        'postcode'         => 'Postal',
        'zip'              => 'Postal',
        'zip code'         => 'Postal',
        'case title'       => 'Case title',
        'title'            => 'Title',
        'service'          => 'Service',
        'service type'     => 'Service',
        'description'      => 'Description',
        'case description' => 'Description',
        'status'           => 'Status',
        'amount'           => 'Amount',
        'method'           => 'Method',
        'note'             => 'Note',
    ];

    public static function isEditableKey(string $action, string $label): bool
    {
        return isset(self::FIELD_PATHS[$action][$label]);
    }

    /** @return list<string> */
    public static function editableKeys(string $action): array
    {
        return array_keys(self::FIELD_PATHS[$action] ?? []);
    }

    /** @return array<string, mixed>|null */
    public static function latestPendingDraft(): ?array
    {
        $drafts = $_SESSION['assistant_drafts'] ?? [];
        if (!is_array($drafts) || $drafts === []) {
            return null;
        }

        $latest = null;
        $latestAt = 0;

        foreach ($drafts as $draft) {
            if (!is_array($draft) || empty($draft['id'])) {
                continue;
            }

            $created = (int) ($draft['created_at'] ?? 0);
            if ($created >= $latestAt) {
                $latestAt = $created;
                $latest = $draft;
            }
        }

        return $latest;
    }

    /**
     * @param array<string, string> $previewUpdates
     * @return array<string, mixed>
     */
    public static function update(string $draftId, array $previewUpdates): array
    {
        if (Auth::isReadOnly()) {
            throw new RuntimeException('Your account is read-only and cannot edit drafts.');
        }

        $draft = AssistantActions::getDraft($draftId);
        if ($draft === null) {
            throw new RuntimeException('This draft has expired or was already confirmed.');
        }

        $action = (string) ($draft['action'] ?? '');
        $fieldMap = self::FIELD_PATHS[$action] ?? null;
        if ($fieldMap === null) {
            throw new RuntimeException('This draft cannot be edited.');
        }

        $payload = is_array($draft['payload'] ?? null) ? $draft['payload'] : [];

        foreach ($previewUpdates as $label => $value) {
            $label = trim((string) $label);
            $value = trim((string) $value);
            if ($label === '' || !isset($fieldMap[$label])) {
                continue;
            }

            self::applyPreviewValue($payload, $fieldMap[$label], $label, $value);
        }

        self::validatePayload($action, $payload);

        $draft['payload'] = $payload;
        $draft['preview'] = self::previewFromPayload(
            $action,
            $payload,
            is_array($draft['preview'] ?? null) ? $draft['preview'] : []
        );
        $draft['editable'] = self::editableKeys($action);
        $draft['updated_at'] = time();

        $_SESSION['assistant_drafts'][$draftId] = $draft;

        return $draft;
    }

    /**
     * @return array{content: string, type: string, draft_update?: array<string, mixed>}|null
     */
    public static function tryEditFromMessage(string $message): ?array
    {
        $draft = self::latestPendingDraft();
        if ($draft === null || !self::looksLikeEdit($message)) {
            return null;
        }

        $parsed = self::parseFieldUpdate($message);
        if ($parsed === null) {
            return null;
        }

        $action = (string) ($draft['action'] ?? '');
        $label = self::resolvePreviewLabel($parsed['field'], $action);
        if ($label === null) {
            return [
                'content' => 'I could not tell which draft field to change. Try: _change country to UK_ or _update email to name@example.com_.',
                'type'    => 'text',
            ];
        }

        try {
            $updated = self::update((string) $draft['id'], [$label => $parsed['value']]);
            AssistantService::replaceDraftInHistory((string) $draft['id'], $updated);
        } catch (RuntimeException $e) {
            return [
                'content' => $e->getMessage(),
                'type'    => 'text',
            ];
        }

        return [
            'content'      => 'Updated **' . $label . '** to **' . $parsed['value'] . '**. Review the draft and click **Confirm** when ready.',
            'type'         => 'text',
            'draft_update' => $updated,
        ];
    }

    private static function looksLikeEdit(string $message): bool
    {
        if (!preg_match('/\b(change|update|edit|set|correct|fix)\b/i', $message)) {
            return false;
        }

        return (bool) preg_match('/\bto\b/i', $message);
    }

    /**
     * @return array{field: string, value: string}|null
     */
    private static function parseFieldUpdate(string $message): ?array
    {
        if (preg_match(
            '/(?:change|update|edit|set|correct|fix)\s+(?:the\s+)?(.+?)\s+from\s+.+?\s+to\s+(.+)$/iu',
            $message,
            $matches
        )) {
            return [
                'field' => trim($matches[1]),
                'value' => trim($matches[2]),
            ];
        }

        if (preg_match(
            '/(?:change|update|edit|set|correct|fix)\s+(?:the\s+)?(.+?)\s+to\s+(.+)$/iu',
            $message,
            $matches
        )) {
            return [
                'field' => trim($matches[1]),
                'value' => trim($matches[2]),
            ];
        }

        return null;
    }

    private static function resolvePreviewLabel(string $fieldHint, string $action): ?string
    {
        $hint = strtolower(trim($fieldHint));
        $map = self::FIELD_PATHS[$action] ?? null;
        if ($map === null) {
            return null;
        }

        foreach (array_keys($map) as $label) {
            if (strtolower($label) === $hint) {
                return $label;
            }
        }

        if (isset(self::FIELD_ALIASES[$hint]) && isset($map[self::FIELD_ALIASES[$hint]])) {
            return self::FIELD_ALIASES[$hint];
        }

        foreach (array_keys($map) as $label) {
            $labelLower = strtolower($label);
            if (str_contains($hint, $labelLower) || str_contains($labelLower, $hint)) {
                return $label;
            }
        }

        foreach (self::FIELD_ALIASES as $alias => $label) {
            if (!isset($map[$label])) {
                continue;
            }
            if ($hint === $alias || str_contains($hint, $alias)) {
                return $label;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $path
     */
    private static function applyPreviewValue(array &$payload, array $path, string $label, string $value): void
    {
        if ($path === ['client', 'name']) {
            $names = assistantSplitPersonName($value);
            if (!is_array($payload['client'] ?? null)) {
                $payload['client'] = [];
            }
            $payload['client']['first_name'] = $names['first_name'];
            $payload['client']['last_name'] = $names['last_name'];

            return;
        }

        if ($path === ['client', 'address_block']) {
            if (!is_array($payload['client'] ?? null)) {
                $payload['client'] = [];
            }
            $parsed = assistantParsePostalAddress($value);
            if ($parsed !== null) {
                $payload['client']['address'] = $parsed['address'];
                $payload['client']['city'] = $parsed['city'];
                $payload['client']['state'] = $parsed['state'];
                $payload['client']['zip_code'] = $parsed['zip_code'];
                $payload['client']['country'] = $parsed['country'];

                return;
            }

            $parts = array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $p): bool => $p !== ''));
            $payload['client']['address'] = $parts[0] ?? $value;
            if (isset($parts[1])) {
                $payload['client']['city'] = $parts[1];
            }
            if (isset($parts[2])) {
                $payload['client']['state'] = $parts[2];
            }

            return;
        }

        if ($path === ['case', 'description'] || $path === ['description']) {
            $value = strtolower($value) === '—' ? '' : $value;
        }

        if ($path === ['amount']) {
            $payload['amount'] = (float) str_replace(',', '', $value);

            return;
        }

        $ref = &$payload;
        $lastKey = array_pop($path);
        foreach ($path as $segment) {
            if (!is_array($ref[$segment] ?? null)) {
                $ref[$segment] = [];
            }
            $ref = &$ref[$segment];
        }
        $ref[$lastKey] = $value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function validatePayload(string $action, array $payload): void
    {
        if (in_array($action, ['create_client', 'create_client_and_case'], true)) {
            $client = is_array($payload['client'] ?? null) ? $payload['client'] : [];
            ClientService::validatedProfile($client);

            if (ClientService::emailExistsPublic((string) ($client['email'] ?? ''))) {
                throw new RuntimeException('A client with email **' . ($client['email'] ?? '') . '** already exists. Use a different email.');
            }
        }

        if ($action === 'create_client_and_case') {
            $case = is_array($payload['case'] ?? null) ? $payload['case'] : [];
            if (trim((string) ($case['title'] ?? '')) === '' || trim((string) ($case['service_type'] ?? '')) === '') {
                throw new RuntimeException('Case **title** and **service type** are required.');
            }
        }

        if ($action === 'create_case') {
            if (trim((string) ($payload['title'] ?? '')) === '') {
                throw new RuntimeException('Case **title** is required.');
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    private static function buildClientPreview(string $action, array $payload): array
    {
        $client = is_array($payload['client'] ?? null) ? $payload['client'] : [];
        $preview = [
            'Name'    => trim(($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? '')),
            'Email'   => (string) ($client['email'] ?? ''),
            'Phone'   => (string) ($client['phone'] ?? ''),
            'Address' => trim(($client['address'] ?? '') . ', ' . ($client['city'] ?? '') . ', ' . ($client['state'] ?? '')),
            'Postal'  => (string) ($client['zip_code'] ?? ''),
            'Country' => (string) ($client['country'] ?? ''),
        ];

        if ($action === 'create_client_and_case') {
            $case = is_array($payload['case'] ?? null) ? $payload['case'] : [];
            $description = trim((string) ($case['description'] ?? ''));
            $preview['Case title'] = (string) ($case['title'] ?? '');
            $preview['Service'] = (string) ($case['service_type'] ?? '');
            $preview['Description'] = $description !== '' ? $description : '—';
        }

        return $preview;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    private static function previewFromPayload(string $action, array $payload, array $existingPreview): array
    {
        if ($action === 'create_client' || $action === 'create_client_and_case') {
            return self::buildClientPreview($action, $payload);
        }

        $preview = $existingPreview;

        foreach (self::FIELD_PATHS[$action] ?? [] as $label => $path) {
            $value = self::readPayloadValue($payload, $path);
            if ($value === null) {
                continue;
            }
            if ($label === 'Description' && $value === '') {
                $value = '—';
            }
            $preview[$label] = (string) $value;
        }

        return $preview;
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $path
     */
    private static function readPayloadValue(array $payload, array $path): ?string
    {
        if ($path === ['client', 'name']) {
            $client = is_array($payload['client'] ?? null) ? $payload['client'] : [];

            return trim(($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? ''));
        }

        if ($path === ['client', 'address_block']) {
            $client = is_array($payload['client'] ?? null) ? $payload['client'] : [];

            return trim(($client['address'] ?? '') . ', ' . ($client['city'] ?? '') . ', ' . ($client['state'] ?? ''));
        }

        $ref = $payload;
        foreach ($path as $segment) {
            if (!is_array($ref) || !array_key_exists($segment, $ref)) {
                return null;
            }
            $ref = $ref[$segment];
        }

        return is_scalar($ref) ? (string) $ref : null;
    }
}
