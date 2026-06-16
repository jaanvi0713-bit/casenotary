<?php

declare(strict_types=1);

class OllamaService
{
    private static ?string $resolvedModel = null;

    /** @var list<string>|null */
    private static ?array $availableModels = null;

    /** @return array{base_url: string, model: string, vision_model: string, timeout: int, enabled: bool} */
    private static function config(): array
    {
        $config = require __DIR__ . '/../config/config.php';
        $assistant = $config['assistant'] ?? [];

        return [
            'enabled'      => (bool) ($assistant['enabled'] ?? true),
            'base_url'     => rtrim((string) ($assistant['base_url'] ?? 'http://127.0.0.1:11434'), '/'),
            'model'        => (string) ($assistant['model'] ?? 'qwen2.5:0.5b'),
            'vision_model' => (string) ($assistant['vision_model'] ?? 'llava'),
            'timeout'      => max(30, (int) ($assistant['timeout'] ?? 120)),
        ];
    }

    public static function isEnabled(): bool
    {
        return self::config()['enabled'];
    }

    public static function modelName(): string
    {
        return self::resolveModel();
    }

    public static function configuredModelName(): string
    {
        return self::config()['model'];
    }

    /**
     * @param list<array{role: string, content: string, images?: list<string>}> $messages
     */
    public static function chat(array $messages): string
    {
        if (!self::isEnabled()) {
            throw new RuntimeException('The AI assistant is disabled.');
        }

        if ($messages === []) {
            throw new InvalidArgumentException('At least one message is required.');
        }

        $tried = [];
        $lastError = null;

        while (count($tried) < max(1, count(self::availableModels()))) {
            $model = self::resolveModel();
            if (in_array($model, $tried, true)) {
                break;
            }
            $tried[] = $model;

            try {
                return self::chatWithModel($model, $messages);
            } catch (RuntimeException $e) {
                $lastError = $e;
                if (!self::isRecoverableModelError($e) || !self::advanceToNextModel($model)) {
                    throw $e;
                }
            }
        }

        throw $lastError ?? new RuntimeException('Ollama request failed.');
    }

    /**
     * @param list<array{role: string, content: string, images?: list<string>}> $messages
     */
    private static function chatWithModel(string $model, array $messages): string
    {
        $payload = [
            'model'    => $model,
            'messages' => $messages,
            'stream'   => false,
        ];

        $response = self::request('POST', '/api/chat', $payload);
        $content = trim((string) ($response['message']['content'] ?? ''));

        if ($content === '') {
            throw new RuntimeException('Ollama returned an empty response.');
        }

        return $content;
    }

    private static function isRecoverableModelError(RuntimeException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'not found')
            || str_contains($message, 'out-of-memory')
            || str_contains($message, 'allocate')
            || str_contains($message, 'startup failed')
            || str_contains($message, 'image')
            || str_contains($message, 'vision')
            || str_contains($message, 'multimodal');
    }

    private static function advanceToNextModel(string $failedModel): bool
    {
        $available = self::availableModels();
        if ($available === []) {
            return false;
        }

        usort($available, static fn (string $a, string $b): int => self::modelPreferenceScore($a) <=> self::modelPreferenceScore($b));

        $index = array_search($failedModel, $available, true);
        if ($index === false) {
            self::$resolvedModel = $available[0];

            return $available[0] !== $failedModel;
        }

        for ($i = (int) $index - 1; $i >= 0; $i--) {
            self::$resolvedModel = $available[$i];

            return true;
        }

        for ($i = (int) $index + 1; $i < count($available); $i++) {
            self::$resolvedModel = $available[$i];

            return true;
        }

        return false;
    }

    public static function summarizeDocument(string $text, string $question, array $file): string
    {
        $path = (string) ($file['tmp_name'] ?? '');
        $name = strtolower((string) ($file['name'] ?? ''));
        $mime = strtolower((string) ($file['type'] ?? ''));

        if ($path !== '' && is_readable($path)
            && (preg_match('/\.(jpe?g|png|gif|webp)$/i', $name) || str_starts_with($mime, 'image/'))) {
            $binary = file_get_contents($path);
            if ($binary === false) {
                throw new RuntimeException('Could not read uploaded image.');
            }

            return self::chatWithVision([
                [
                    'role' => 'system',
                    'content' => 'You extract structured facts from document images for a notary office: names, dates, IDs, clauses, amounts.',
                ],
                [
                    'role'    => 'user',
                    'content' => $question,
                    'images'  => [base64_encode($binary)],
                ],
            ]);
        }

        $snippet = mb_substr(assistantSanitizeUtf8($text), 0, 12000);
        if (trim($snippet) === '') {
            throw new RuntimeException('No text could be extracted from this document.');
        }

        return self::chat([
            ['role' => 'system', 'content' => 'You analyze legal/notary documents and return clear bullet points.'],
            ['role' => 'user', 'content' => $question . "\n\nDocument text:\n" . $snippet],
        ]);
    }

    /** @param list<string> $imagePaths */
    public static function summarizeDocumentImages(array $imagePaths, string $question): string
    {
        $images = [];
        foreach ($imagePaths as $path) {
            if (!is_readable($path)) {
                continue;
            }

            $binary = file_get_contents($path);
            if ($binary !== false && $binary !== '') {
                $images[] = base64_encode($binary);
            }
        }

        if ($images === []) {
            throw new RuntimeException('Could not read rendered PDF pages for vision extraction.');
        }

        return self::chatWithVision([
            [
                'role' => 'system',
                'content' => 'You extract structured facts from document images for a notary office: names, dates, IDs, invoice numbers, amounts, clauses, and parties. Use bullet points.',
            ],
            [
                'role' => 'user',
                'content' => $question,
                'images' => $images,
            ],
        ]);
    }

    /**
     * @param list<array{role: string, content: string, images?: list<string>}> $messages
     */
    public static function chatWithVision(array $messages): string
    {
        if (!self::isEnabled()) {
            throw new RuntimeException('The AI assistant is disabled.');
        }

        $candidates = self::visionModelCandidates();
        if ($candidates === []) {
            throw new RuntimeException(
                'No vision-capable Ollama model is installed. Run `ollama pull llava` (or set OLLAMA_VISION_MODEL), then try again.'
            );
        }

        $lastError = null;
        foreach ($candidates as $model) {
            try {
                return self::chatWithModel($model, $messages);
            } catch (RuntimeException $e) {
                $lastError = $e;
                if (!self::isRecoverableModelError($e)) {
                    throw $e;
                }
            }
        }

        throw $lastError ?? new RuntimeException(
            'Could not read this image with any installed vision model. Try `ollama pull llava`.'
        );
    }

    /** @return list<string> */
    public static function visionModelCandidates(): array
    {
        $available = self::availableModels();
        if ($available === []) {
            return [];
        }

        $wanted = self::config()['vision_model'];
        $candidates = [];

        if (in_array($wanted, $available, true)) {
            $candidates[] = $wanted;
        }

        foreach ($available as $name) {
            if (str_starts_with($name, $wanted . ':') || $name === $wanted) {
                $candidates[] = $name;
            }
        }

        foreach ($available as $name) {
            if (self::looksLikeVisionModel($name)) {
                $candidates[] = $name;
            }
        }

        return array_values(array_unique($candidates));
    }

    public static function hasVisionModel(): bool
    {
        return self::visionModelCandidates() !== [];
    }

    private static function looksLikeVisionModel(string $name): bool
    {
        $lower = strtolower($name);

        foreach (['llava', 'moondream', 'bakllava', 'minicpm-v', 'vision', 'llama3.2-vision'] as $needle) {
            if (str_contains($lower, $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function isReachable(): bool
    {
        if (!self::isEnabled()) {
            return false;
        }

        try {
            self::request('GET', '/api/tags');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return list<string> */
    public static function availableModels(): array
    {
        if (self::$availableModels !== null) {
            return self::$availableModels;
        }

        try {
            $data = self::request('GET', '/api/tags');
            $names = [];
            foreach ($data['models'] ?? [] as $model) {
                $name = trim((string) ($model['name'] ?? ''));
                if ($name !== '') {
                    $names[] = $name;
                }
            }
            self::$availableModels = $names;
        } catch (Throwable) {
            self::$availableModels = [];
        }

        return self::$availableModels;
    }

    private static function resolveModel(): string
    {
        if (self::$resolvedModel !== null) {
            return self::$resolvedModel;
        }

        $wanted = self::config()['model'];
        $available = self::availableModels();

        if ($available === []) {
            return self::$resolvedModel = $wanted;
        }

        if (in_array($wanted, $available, true)) {
            return self::$resolvedModel = $wanted;
        }

        foreach ($available as $name) {
            if (str_starts_with($name, $wanted . ':')) {
                return self::$resolvedModel = self::preferLargerVariant($name, $available, $wanted);
            }
        }

        $familyMatches = array_values(array_filter(
            $available,
            static fn (string $name): bool => str_starts_with($name, explode(':', $wanted)[0])
        ));
        if ($familyMatches !== []) {
            usort($familyMatches, [self::class, 'compareModelPreference']);

            return self::$resolvedModel = $familyMatches[0];
        }

        return self::$resolvedModel = $available[0];
    }

    /** @param list<string> $available */
    private static function preferLargerVariant(string $firstMatch, array $available, string $prefix): string
    {
        $matches = array_values(array_filter(
            $available,
            static fn (string $name): bool => str_starts_with($name, $prefix . ':') || $name === $prefix
        ));
        if ($matches === []) {
            return $firstMatch;
        }

        usort($matches, [self::class, 'compareModelPreference']);

        return $matches[0];
    }

    private static function compareModelPreference(string $a, string $b): int
    {
        return self::modelPreferenceScore($a) <=> self::modelPreferenceScore($b);
    }

    private static function modelPreferenceScore(string $name): int
    {
        if (preg_match('/:(\d+)b$/i', $name, $matches)) {
            return (int) $matches[1];
        }

        return str_contains($name, 'latest') ? 1 : 0;
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array<string, mixed>
     */
    private static function request(string $method, string $path, ?array $payload = null): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required for the AI assistant.');
        }

        $config = self::config();
        $url = $config['base_url'] . $path;
        $ch = curl_init($url);

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $config['timeout'],
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($payload ?? [], JSON_THROW_ON_ERROR);
        }

        curl_setopt_array($ch, $options);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException('Could not reach Ollama: ' . ($curlError ?: 'request failed'));
        }

        $data = json_decode((string) $body, true);

        if ($status >= 400 || !is_array($data)) {
            $message = is_array($data) ? (string) ($data['error'] ?? 'Ollama request failed.') : 'Ollama request failed.';
            throw new RuntimeException($message);
        }

        return $data;
    }
}
