<?php

declare(strict_types=1);

class OllamaService
{
    private static ?string $resolvedModel = null;

    /** @var list<string>|null */
    private static ?array $availableModels = null;

    private static ?bool $reachableCache = null;

    private static ?int $reachableCheckedAt = null;

    /** @return array{base_url: string, model: string, vision_model: string, timeout: int, chat_timeout: int, ping_timeout: int, keep_alive: string, num_predict: int, enabled: bool} */
    private static function config(): array
    {
        $config = require __DIR__ . '/../config/config.php';
        $assistant = $config['assistant'] ?? [];

        return [
            'enabled'      => (bool) ($assistant['enabled'] ?? true),
            'base_url'     => rtrim((string) ($assistant['base_url'] ?? 'http://127.0.0.1:11434'), '/'),
            'model'        => (string) ($assistant['model'] ?? 'qwen2.5:0.5b'),
            'vision_model' => (string) ($assistant['vision_model'] ?? 'llava'),
            'timeout'      => max(15, (int) ($assistant['timeout'] ?? 60)),
            'chat_timeout' => max(15, (int) ($assistant['chat_timeout'] ?? 60)),
            'ping_timeout' => max(2, (int) ($assistant['ping_timeout'] ?? 3)),
            'keep_alive'   => (string) ($assistant['keep_alive'] ?? '15m'),
            'num_predict'  => max(64, (int) ($assistant['num_predict'] ?? 384)),
            'document_use_ai' => (bool) ($assistant['document_use_ai'] ?? false),
            'document_chat_timeout' => max(8, (int) ($assistant['document_chat_timeout'] ?? 20)),
        ];
    }

    public static function useAiForDocuments(): bool
    {
        return self::config()['document_use_ai'];
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

        return self::chatWithModel(self::resolveModel(), $messages);
    }

    /**
     * @param list<array{role: string, content: string, images?: list<string>}> $messages
     * @param array<string, mixed> $optionOverrides
     */
    private static function chatWithModel(string $model, array $messages, ?int $timeout = null, array $optionOverrides = []): string
    {
        $config = self::config();
        $options = array_merge([
            'num_predict' => $config['num_predict'],
            'temperature' => 0.5,
        ], $optionOverrides);

        $payload = [
            'model'      => $model,
            'messages'   => $messages,
            'stream'     => false,
            'keep_alive' => $config['keep_alive'],
            'options'    => $options,
        ];

        $response = self::request('POST', '/api/chat', $payload, $timeout ?? $config['chat_timeout']);
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
                    'content' => 'You summarize notary office document images for staff. '
                        . 'Return 4–10 concise bullet points: document type, parties, dates, reference numbers, amounts, and key clauses. '
                        . 'Only state facts visible in the image.',
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

        return self::summarizeTextContent($snippet, $question);
    }

    public static function summarizeTextContent(string $text, string $question): string
    {
        if (!self::isEnabled()) {
            throw new RuntimeException('The AI assistant is disabled.');
        }

        $snippet = mb_substr(assistantSanitizeUtf8($text), 0, 12000);
        if (trim($snippet) === '') {
            throw new RuntimeException('No text could be extracted from this document.');
        }

        $instruction = trim($question) !== ''
            ? $question
            : 'Summarize this document in clear bullet points.';

        $config = self::config();
        $predict = min(256, max(128, $config['num_predict']));

        return self::chatWithModel(self::resolveModel(), [
            [
                'role' => 'system',
                'content' => 'You summarize notary and legal office documents for staff. '
                    . 'Reply with 4–10 concise bullet points covering: document type, parties and names, dates, '
                    . 'reference or invoice numbers, monetary amounts, and important clauses or next steps. '
                    . 'Use markdown bullets (- or •). Only state facts present in the text; do not invent details.',
            ],
            [
                'role' => 'user',
                'content' => $instruction . "\n\n---\n" . $snippet,
            ],
        ], $config['document_chat_timeout'], [
            'num_predict' => $predict,
            'temperature' => 0.3,
        ]);
    }

    public static function answerAboutDocument(string $text, string $question): string
    {
        if (!self::isEnabled()) {
            throw new RuntimeException('The AI assistant is disabled.');
        }

        $snippet = mb_substr(assistantSanitizeUtf8($text), 0, 12000);
        if (trim($snippet) === '') {
            throw new RuntimeException('No document text is available.');
        }

        $instruction = trim($question) !== '' ? trim($question) : 'Answer the question using the document.';
        $config = self::config();

        return self::chatWithModel(self::resolveModel(), [
            [
                'role' => 'system',
                'content' => 'You answer questions about an uploaded notary-office document. '
                    . 'Use ONLY facts from the document text below. Be brief and direct. '
                    . 'For amount or total questions, quote the payment received or grand total figure exactly. '
                    . 'Do not confuse case references, invoice numbers, or receipt numbers with monetary amounts. '
                    . 'If the answer is not in the document, say you cannot find it in the uploaded file.',
            ],
            [
                'role' => 'user',
                'content' => "Question: {$instruction}\n\n---\nDocument text:\n{$snippet}",
            ],
        ], min($config['document_chat_timeout'], 25), [
            'num_predict' => 192,
            'temperature' => 0.2,
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

        if (self::$reachableCache !== null && self::$reachableCheckedAt !== null
            && (time() - self::$reachableCheckedAt) < 45) {
            return self::$reachableCache;
        }

        try {
            self::request('GET', '/api/tags', null, self::config()['ping_timeout']);
            self::$reachableCache = true;
        } catch (Throwable) {
            self::$reachableCache = false;
        }

        self::$reachableCheckedAt = time();

        return self::$reachableCache;
    }

    /** @return list<string> */
    public static function availableModels(): array
    {
        if (self::$availableModels !== null) {
            return self::$availableModels;
        }

        try {
            $data = self::request('GET', '/api/tags', null, self::config()['ping_timeout']);
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

        $wanted = trim(self::config()['model']);
        if ($wanted === '') {
            throw new RuntimeException('No Ollama model is configured for the AI assistant.');
        }
        $available = self::availableModels();

        if ($available === []) {
            return self::$resolvedModel = $wanted;
        }

        if (in_array($wanted, $available, true)) {
            return self::$resolvedModel = $wanted;
        }

        throw new RuntimeException(
            'Configured Ollama model "' . $wanted . '" is not installed. '
            . 'Installed models: ' . implode(', ', $available)
        );
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array<string, mixed>
     */
    private static function request(string $method, string $path, ?array $payload = null, ?int $timeout = null): array
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('PHP cURL extension is required for the AI assistant.');
        }

        $config = self::config();
        $timeout = $timeout ?? $config['timeout'];
        // Hard cap so PHP max_execution_time (often 120s) never gets hit.
        // This prevents fatal errors that break the JSON response.
        $timeout = max(5, min((int) $timeout, 45));
        @set_time_limit((int) $timeout + 2);
        $url = $config['base_url'] . $path;
        $ch = curl_init($url);

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_NOSIGNAL       => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        ];

        if (defined('CURLOPT_TIMEOUT_MS')) {
            $options[CURLOPT_TIMEOUT_MS] = $timeout * 1000;
        }

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
