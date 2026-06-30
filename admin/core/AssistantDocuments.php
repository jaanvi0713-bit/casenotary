<?php

declare(strict_types=1);

class AssistantDocuments
{
    private const MAX_CLIENT_TEXT = 50000;
    private const SESSION_DOC_KEY = 'assistant_last_document_text';
    private const SESSION_DOCS_KEY = 'assistant_document_library';
    private const MAX_STORED_DOCS = 8;
    private const MAX_DOCS_PER_UPLOAD = 5;

    /** @return list<array{id: string, name: string, text: string, source: string}> */
    public static function cachedDocumentItems(): array
    {
        $cached = $_SESSION[self::SESSION_DOCS_KEY] ?? null;
        if (is_array($cached)) {
            if (time() - (int) ($cached['at'] ?? 0) > 3600) {
                return [];
            }

            $items = $cached['items'] ?? [];

            return is_array($items) ? self::normalizeDocumentItems($items) : [];
        }

        $legacy = $_SESSION[self::SESSION_DOC_KEY] ?? null;
        if (!is_array($legacy)) {
            return [];
        }

        if (time() - (int) ($legacy['at'] ?? 0) > 3600) {
            return [];
        }

        $text = assistantSanitizeUtf8(trim((string) ($legacy['text'] ?? '')));
        if ($text === '') {
            return [];
        }

        return [[
            'id'     => 'doc-legacy',
            'name'   => 'Uploaded document',
            'text'   => $text,
            'source' => 'upload',
        ]];
    }

    public static function cachedDocumentText(): string
    {
        $items = self::cachedDocumentItems();
        if ($items === []) {
            return '';
        }

        if (count($items) === 1) {
            return $items[0]['text'];
        }

        $parts = [];
        foreach ($items as $index => $item) {
            $parts[] = '--- Document ' . ($index + 1) . ': ' . $item['name'] . " ---\n" . $item['text'];
        }

        return implode("\n\n", $parts);
    }

    public static function cacheDocumentText(string $text): void
    {
        self::addDocumentItems([[
            'name'   => 'Uploaded document',
            'text'   => $text,
            'source' => 'upload',
        ]]);
    }

    /**
     * @param list<array{id?: string, name?: string, text: string, source?: string}> $items
     */
    public static function addDocumentItems(array $items, bool $replace = false): void
    {
        $normalized = self::normalizeDocumentItems($items);
        if ($normalized === []) {
            return;
        }

        $existing = $replace ? [] : self::cachedDocumentItems();
        $merged = array_merge($existing, $normalized);
        $merged = self::deduplicateDocumentItems($merged);

        if (count($merged) > self::MAX_STORED_DOCS) {
            $merged = array_slice($merged, -self::MAX_STORED_DOCS);
        }

        $_SESSION[self::SESSION_DOCS_KEY] = [
            'at'    => time(),
            'items' => $merged,
        ];
        unset($_SESSION[self::SESSION_DOC_KEY]);
    }

    public static function clearCachedDocumentText(): void
    {
        unset($_SESSION[self::SESSION_DOCS_KEY], $_SESSION[self::SESSION_DOC_KEY]);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<array{id: string, name: string, text: string, source: string}>
     */
    private static function normalizeDocumentItems(array $items): array
    {
        $out = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $text = self::normalizeText((string) ($item['text'] ?? ''));
            if (!self::hasMeaningfulText($text)) {
                continue;
            }

            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') {
                $name = 'Document ' . (count($out) + 1);
            }

            $out[] = [
                'id'     => trim((string) ($item['id'] ?? '')) !== '' ? (string) $item['id'] : uniqid('doc-', true),
                'name'   => $name,
                'text'   => mb_substr($text, 0, self::MAX_CLIENT_TEXT),
                'source' => trim((string) ($item['source'] ?? 'upload')),
            ];
        }

        return $out;
    }

    /**
     * @param list<array{id: string, name: string, text: string, source: string}> $items
     * @return list<array{id: string, name: string, text: string, source: string}>
     */
    private static function deduplicateDocumentItems(array $items): array
    {
        $out = [];
        $indexByFingerprint = [];

        foreach ($items as $item) {
            $fingerprint = self::documentTextFingerprint((string) ($item['text'] ?? ''));
            if (isset($indexByFingerprint[$fingerprint])) {
                $existingIndex = $indexByFingerprint[$fingerprint];
                $existingName = strtolower((string) ($out[$existingIndex]['name'] ?? ''));
                $newName = strtolower((string) ($item['name'] ?? ''));

                if ($newName !== 'uploaded document' && $newName !== 'document'
                    && ($existingName === 'uploaded document' || $existingName === 'document')) {
                    $out[$existingIndex]['name'] = (string) $item['name'];
                }

                continue;
            }

            $indexByFingerprint[$fingerprint] = count($out);
            $out[] = $item;
        }

        return $out;
    }

    private static function documentTextFingerprint(string $text): string
    {
        $text = self::normalizeText($text);

        if (preg_match('/\b(INV-\d{4}-[A-Z0-9]+)\b/i', $text, $match)) {
            return 'inv:' . strtoupper($match[1]);
        }

        if (preg_match('/\b(RCP-\d{4}-[A-Z0-9]+)\b/i', $text, $match)) {
            return 'rcp:' . strtoupper($match[1]);
        }

        if (preg_match('/\b(QUO-\d{4}-[A-Z0-9]+)\b/i', $text, $match)) {
            return 'quo:' . strtoupper($match[1]);
        }

        return 'txt:' . md5(mb_substr($text, 0, 5000));
    }

    private static function documentDisplayLabel(array $item): string
    {
        $text = (string) ($item['text'] ?? '');

        if (preg_match('/\b(INV-\d{4}-[A-Z0-9]+)\b/i', $text, $match)) {
            return strtoupper($match[1]);
        }

        if (preg_match('/\b(RCP-\d{4}-[A-Z0-9]+)\b/i', $text, $match)) {
            return strtoupper($match[1]);
        }

        $name = trim((string) ($item['name'] ?? 'Document'));
        $lower = strtolower($name);

        if ($lower === 'uploaded document' || $lower === 'document' || $lower === 'screenshot') {
            return 'Document';
        }

        return $name;
    }

    private static function normalizeAnswerKey(string $body): string
    {
        $plain = preg_replace('/\*\*([^*]+)\*\*/', '$1', $body) ?? $body;

        return preg_replace('/\s+/', ' ', strtolower(trim($plain))) ?? '';
    }

    private static function stripDocumentAnswerPrefix(string $body): string
    {
        return trim(preg_replace('/^\*\*From your document:\*\*\s*/i', '', $body) ?? $body);
    }

    /**
     * @return array{content: string, alerts?: list<array<string, string>>}
     */
    public static function handleDocument(string $message, ?array $upload, string $clientDocumentText = '', string $documentSource = ''): array
    {
        @set_time_limit(90);

        $question = trim($message) !== ''
            ? trim($message)
            : 'Summarize this document: document type, parties, dates, reference numbers, amounts, and important clauses or action items.';

        $clientDocumentText = self::normalizeText($clientDocumentText);
        if (mb_strlen($clientDocumentText) > self::MAX_CLIENT_TEXT) {
            $clientDocumentText = mb_substr($clientDocumentText, 0, self::MAX_CLIENT_TEXT);
        }

        $hasUpload = $upload !== null && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
        if ($hasUpload) {
            self::validateUpload($upload);
        }

        if (!$hasUpload && $clientDocumentText === '') {
            throw new InvalidArgumentException('No document content was provided.');
        }

        $text = $clientDocumentText;
        $usedBrowserExtraction = $clientDocumentText !== '' && self::hasMeaningfulText($clientDocumentText);

        if ($hasUpload) {
            $fileText = self::readPlainUploadText($upload);
            $text = self::pickBestText($text, $fileText);

            $skipServerPdf = self::hasMeaningfulText($clientDocumentText)
                && mb_strlen($clientDocumentText) >= 120;

            if (self::isPdfUpload($upload) && !$skipServerPdf) {
                $serverPdfText = self::extractPdfText((string) ($upload['tmp_name'] ?? ''));
                $text = self::pickBestText($text, $serverPdfText);
            }
        }

        $isImage = $hasUpload && self::isImageUpload($upload);
        if ($isImage && !self::hasMeaningfulText($text)) {
            $summary = self::summarizeImageUpload($upload, $question);
            $alerts = AssistantCompliance::screenText($summary);

            $summaryBlock = str_starts_with($summary, '**Summary**')
                ? $summary
                : "**Summary**\n\n" . $summary;

            return [
                'content' => "**Document analysis** _(image)_\n\n" . $summaryBlock,
                'alerts' => $alerts,
            ];
        }

        if (!self::hasMeaningfulText($text)) {
            throw new RuntimeException(
                'Could not read text from this file. Try a text-based PDF or HTML letter, re-save the PDF as flattened, or upload a clear photo of the page.'
            );
        }

        self::addDocumentItems([[
            'name'   => self::guessDocumentName($upload, $documentSource),
            'text'   => $text,
            'source' => $documentSource !== '' ? $documentSource : 'upload',
        ]]);

        if (trim($message) !== '' && self::shouldAnswerFromDocument($message) && !self::looksLikeSummarizeRequest($message)) {
            return self::answerDocumentQuestion($message, $text);
        }

        $summary = self::summarizeText($text, $question);
        $alerts = AssistantCompliance::screenText($text . "\n" . $summary);

        $fromScreenshot = strtolower($documentSource) === 'screenshot';
        $header = $fromScreenshot
            ? "**Document analysis** _(from screenshot)_\n\n"
            : ($usedBrowserExtraction
                ? "**Document analysis** _(extracted in browser)_\n\n"
                : "**Document analysis**\n\n");

        $content = $header . $summary;
        if ($alerts !== []) {
            $content .= "\n\n**Compliance flags detected** — review the alerts below.";
        }

        return [
            'content' => $content,
            'alerts' => $alerts,
        ];
    }

    /**
     * @param list<array<string, mixed>> $uploads
     * @param list<array{id?: string, name?: string, text?: string, source?: string}> $clientItems
     * @return array{content: string, alerts?: list<array<string, string>>}
     */
    public static function handleDocuments(string $message, array $uploads = [], array $clientItems = []): array
    {
        @set_time_limit(120);

        if (count($uploads) > self::MAX_DOCS_PER_UPLOAD) {
            throw new InvalidArgumentException('You can upload up to ' . self::MAX_DOCS_PER_UPLOAD . ' files at once.');
        }

        $processed = [];
        $count = max(count($uploads), count($clientItems));

        for ($i = 0; $i < $count; $i++) {
            $upload = $uploads[$i] ?? null;
            $clientItem = is_array($clientItems[$i] ?? null) ? $clientItems[$i] : [];
            $item = self::buildDocumentItemFromSources($upload, $clientItem);
            if ($item !== null) {
                $processed[] = $item;
            }
        }

        if ($processed === []) {
            throw new InvalidArgumentException('No document content could be read from the uploaded files.');
        }

        self::addDocumentItems($processed);
        $library = self::cachedDocumentItems();
        $question = trim($message);

        if ($question !== '' && self::shouldAnswerFromDocument($question) && !self::looksLikeSummarizeRequest($question)) {
            return self::answerMultiDocumentQuestion($question, $library);
        }

        if (count($processed) === 1) {
            $only = $processed[0];

            return self::handleDocument(
                $message,
                null,
                $only['text'],
                (string) ($only['source'] ?? '')
            );
        }

        return self::summarizeMultipleDocuments($message, $processed);
    }

    /**
     * @param list<array{id: string, name: string, text: string, source: string}> $items
     * @return array{content: string, alerts?: list<array<string, string>>}
     */
    public static function answerMultiDocumentQuestion(string $message, array $items): array
    {
        $items = self::deduplicateDocumentItems($items);
        if ($items === []) {
            throw new RuntimeException('No document text is available to answer from.');
        }

        $targets = self::resolveTargetDocuments($message, $items);
        if ($targets === []) {
            $targets = $items;
        }

        $targets = self::deduplicateDocumentItems($targets);

        if (count($targets) === 1) {
            $item = $targets[0];
            $result = self::answerDocumentQuestion($message, $item['text']);
            $body = self::stripDocumentAnswerPrefix((string) ($result['content'] ?? ''));

            return [
                'content' => '**From your document:** ' . $body,
                'alerts'  => $result['alerts'] ?? [],
            ];
        }

        $entries = [];
        $alerts = [];
        foreach ($targets as $item) {
            $result = self::answerDocumentQuestion($message, $item['text']);
            $body = self::stripDocumentAnswerPrefix((string) ($result['content'] ?? ''));
            if ($body === '') {
                continue;
            }

            $entries[] = [
                'label' => self::documentDisplayLabel($item),
                'body'  => $body,
            ];

            foreach ($result['alerts'] ?? [] as $alert) {
                $alerts[] = $alert;
            }
        }

        if ($entries === []) {
            throw new RuntimeException('Could not find an answer in the uploaded documents.');
        }

        $unique = [];
        foreach ($entries as $entry) {
            $key = self::normalizeAnswerKey($entry['body']);
            if ($key === '') {
                continue;
            }
            if (!isset($unique[$key])) {
                $unique[$key] = $entry;
            }
        }
        $uniqueEntries = array_values($unique);

        if (count($uniqueEntries) === 1) {
            return [
                'content' => '**From your document:** ' . $uniqueEntries[0]['body'],
                'alerts'  => $alerts,
            ];
        }

        $parts = [];
        foreach ($uniqueEntries as $entry) {
            $parts[] = '• **' . $entry['label'] . ':** ' . $entry['body'];
        }

        return [
            'content' => "**From your documents:**\n\n" . implode("\n\n", $parts),
            'alerts'  => $alerts,
        ];
    }

    /**
     * @param list<array{id: string, name: string, text: string, source: string}> $items
     * @return list<array{id: string, name: string, text: string, source: string}>
     */
    private static function resolveTargetDocuments(string $question, array $items): array
    {
        if ($items === []) {
            return [];
        }

        if (count($items) === 1) {
            return $items;
        }

        $lower = strtolower(trim($question));

        if (preg_match('/\b(all documents|all files|each document|every document|both documents|compare|across all)\b/', $lower)) {
            return $items;
        }

        foreach ($items as $item) {
            $stem = strtolower(pathinfo($item['name'], PATHINFO_FILENAME));
            if ($stem !== '' && str_contains($lower, $stem)) {
                return [$item];
            }
        }

        $typeRules = [
            'quotation' => '/\b(quotation|quo-)/i',
            'receipt'   => '/\b(receipt|rcp-)/i',
            'invoice'   => '/\b(invoice|inv-)/i',
        ];

        foreach ($typeRules as $pattern) {
            if (!preg_match($pattern, $lower)) {
                continue;
            }

            $matched = [];
            foreach ($items as $item) {
                $haystack = $item['name'] . "\n" . mb_substr($item['text'], 0, 1200);
                if (preg_match($pattern, $haystack)) {
                    $matched[] = $item;
                }
            }

            if (count($matched) === 1) {
                return $matched;
            }
        }

        if (preg_match('/\b(first|1st|document 1|doc 1)\b/', $lower)) {
            return [$items[0]];
        }

        if (preg_match('/\b(second|2nd|document 2|doc 2)\b/', $lower) && isset($items[1])) {
            return [$items[1]];
        }

        if (preg_match('/\b(third|3rd|document 3|doc 3)\b/', $lower) && isset($items[2])) {
            return [$items[2]];
        }

        return self::deduplicateDocumentItems($items);
    }

    /**
     * @param list<array{name: string, text: string, source?: string}> $items
     * @return array{content: string, alerts?: list<array<string, string>>}
     */
    public static function summarizeMultipleDocuments(string $message, array $items): array
    {
        $question = trim($message) !== ''
            ? trim($message)
            : 'Summarize each document: type, parties, dates, reference numbers, amounts, and key details.';

        $sections = ['**Document analysis** (' . count($items) . ' files)', ''];
        $alerts = [];

        foreach ($items as $index => $item) {
            $summary = self::summarizeText($item['text'], $question);
            $sections[] = '### ' . ($index + 1) . '. ' . $item['name'];
            $sections[] = '';
            $sections[] = $summary;
            $sections[] = '';
            $alerts = array_merge($alerts, AssistantCompliance::screenText($item['text'] . "\n" . $summary));
        }

        $content = implode("\n", $sections);
        if ($alerts !== []) {
            $content .= "\n\n**Compliance flags detected** — review the alerts below.";
        }

        return [
            'content' => trim($content),
            'alerts'  => $alerts,
        ];
    }

    /**
     * @param array<string, mixed>|null $upload
     * @param array<string, mixed> $clientItem
     * @return array{name: string, text: string, source: string}|null
     */
    private static function buildDocumentItemFromSources(?array $upload, array $clientItem): ?array
    {
        $hasUpload = $upload !== null && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
        if ($hasUpload) {
            self::validateUpload($upload);
        }

        $clientText = self::normalizeText((string) ($clientItem['text'] ?? ''));
        if (mb_strlen($clientText) > self::MAX_CLIENT_TEXT) {
            $clientText = mb_substr($clientText, 0, self::MAX_CLIENT_TEXT);
        }

        $text = $clientText;
        if ($hasUpload) {
            $fileText = self::readPlainUploadText($upload);
            $text = self::pickBestText($text, $fileText);

            $skipServerPdf = self::hasMeaningfulText($clientText) && mb_strlen($clientText) >= 120;
            if (self::isPdfUpload($upload) && !$skipServerPdf) {
                $serverPdfText = self::extractPdfText((string) ($upload['tmp_name'] ?? ''));
                $text = self::pickBestText($text, $serverPdfText);
            }
        }

        $source = trim((string) ($clientItem['source'] ?? ''));
        if ($hasUpload && self::isImageUpload($upload) && !self::hasMeaningfulText($text)) {
            throw new RuntimeException(
                'Could not read text from image "' . ($upload['name'] ?? 'image') . '". Wait for browser OCR or use a text-based PDF.'
            );
        }

        if (!self::hasMeaningfulText($text)) {
            return null;
        }

        $name = trim((string) ($clientItem['name'] ?? ''));
        if ($name === '' && $hasUpload) {
            $name = (string) ($upload['name'] ?? 'Document');
        }
        if ($name === '') {
            $name = 'Document';
        }

        return [
            'name'   => $name,
            'text'   => $text,
            'source' => $source !== '' ? $source : ($hasUpload && self::isImageUpload($upload) ? 'screenshot' : 'upload'),
        ];
    }

    private static function guessDocumentName(?array $upload, string $documentSource): string
    {
        if ($upload !== null && trim((string) ($upload['name'] ?? '')) !== '') {
            return (string) $upload['name'];
        }

        return strtolower($documentSource) === 'screenshot' ? 'Screenshot' : 'Uploaded document';
    }

    public static function referencesUploadedDocument(string $message): bool
    {
        $lower = strtolower(trim($message));
        if ($lower === '') {
            return false;
        }

        if (AssistantRouter::looksLikeDocumentScan($message)) {
            return true;
        }

        if (preg_match(
            '/\b(this|that|the|uploaded|attached|provided|same)\b.*\b(document|doc|file|pdf|letter|invoice|receipt|screenshot|image|attachment|upload)\b/',
            $lower
        )) {
            return true;
        }

        if (preg_match(
            '/\b(document|doc|file|pdf|letter|invoice|receipt|screenshot|image|attachment|upload)\b.*\b(provided|attached|uploaded|above|here|say|show|list|contain|mention)\b/',
            $lower
        )) {
            return true;
        }

        if (preg_match('/\b(on|in|from)\s+(the\s+)?(document|doc|file|pdf|letter|invoice|receipt|screenshot|upload|attachment)\b/', $lower)) {
            return true;
        }

        if (preg_match(
            '/\b(amount|total|date|due date|invoice number|reference|bill to|paid|balance|fee|price|cost|name|address|email|phone)\b.*\b(on|in|from|for)\b.*\b(receipt|invoice|document|letter|file|upload)\b/',
            $lower
        )) {
            return true;
        }

        return (bool) preg_match(
            '/\b(what|how much|who|when|where|which)\b.*\b(receipt|invoice|document|letter|file|upload|attachment)\b/',
            $lower
        );
    }

    public static function looksLikeCaseDocumentLoad(string $message): bool
    {
        $lower = strtolower(trim($message));
        if ($lower === '') {
            return false;
        }

        if (!preg_match('/\bcase[- ]?#?\s*[a-z0-9-]+/i', $message)) {
            return false;
        }

        if (AssistantRouter::looksLikeCaseDocumentUpload($message)) {
            return false;
        }

        return (bool) preg_match(
            '/\b(document|file|invoice|receipt|quotation|pdf|letter|upload|attachment|scan|read|summarize|summary|amount|total)\b/i',
            $lower
        );
    }

    /**
     * Load a document already stored on a case and answer or summarize it.
     *
     * @return array{content: string, alerts?: list<array<string, string>>}|null
     */
    public static function tryIngestCaseDocument(string $message): ?array
    {
        if (!self::looksLikeCaseDocumentLoad($message)) {
            return null;
        }

        if (!preg_match('/case[- ]?#?\s*([A-Z0-9-]+)/i', $message, $caseMatch)) {
            return null;
        }

        $case = assistantFindCaseByReference($caseMatch[1]);
        if ($case === null) {
            return [
                'content' => 'I could not find case **' . $caseMatch[1] . '**. Check the case number and try again.',
            ];
        }

        $caseId = (int) ($case['id'] ?? 0);
        $documents = CaseService::getDocuments($caseId);
        if ($documents === []) {
            return [
                'content' => 'Case **' . ($case['case_number'] ?? $caseMatch[1]) . '** has no uploaded documents yet.',
            ];
        }

        $document = self::pickCaseDocument($message, $documents);
        if ($document === null) {
            $names = array_map(static fn (array $row): string => (string) ($row['original_name'] ?? 'file'), array_slice($documents, 0, 8));
            return [
                'content' => 'Which file on **' . ($case['case_number'] ?? '') . '** should I read? Available: '
                    . implode(', ', $names) . '.',
            ];
        }

        $config = require dirname(__DIR__) . '/config/config.php';
        $uploadRoot = rtrim((string) ($config['upload']['path'] ?? ''), '/\\');
        $relativePath = (string) ($document['file_path'] ?? '');
        $fullPath = $uploadRoot . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');

        if (!is_readable($fullPath)) {
            return [
                'content' => 'I found **' . ($document['original_name'] ?? 'the file') . '** on that case but could not read it from disk.',
            ];
        }

        $text = self::extractTextFromFilePath($fullPath, (string) ($document['original_name'] ?? 'document'));
        if (!self::hasMeaningfulText($text)) {
            return [
                'content' => '**' . ($document['original_name'] ?? 'Document') . '** on case **'
                    . ($case['case_number'] ?? '') . '** has no readable text (it may be a scanned image). '
                    . 'Download it from '
                    . assistantAdminLink('pages/case-view.php?id=' . $caseId . '#documents', 'the case')
                    . ' and attach it here for browser OCR.',
            ];
        }

        self::addDocumentItems([[
            'name' => (string) ($document['original_name'] ?? 'Case document'),
            'text' => $text,
            'source' => 'case',
        ]]);

        if (trim($message) !== '' && self::shouldAnswerFromDocument($message) && !self::looksLikeSummarizeRequest($message)) {
            return self::answerDocumentQuestion($message, $text);
        }

        $summary = self::summarizeText($text, $message !== '' ? $message : 'Summarize this case document.');
        $alerts = AssistantCompliance::screenText($text . "\n" . $summary);

        return [
            'content' => "**Case document** — " . ($document['original_name'] ?? 'file')
                . ' (' . ($case['case_number'] ?? '') . ")\n\n" . $summary,
            'alerts' => $alerts,
        ];
    }

    /** @param list<array<string, mixed>> $documents */
    private static function pickCaseDocument(string $message, array $documents): ?array
    {
        $lower = strtolower($message);
        $hints = ['invoice', 'receipt', 'quotation', 'contract', 'deed', 'letter', 'pdf', 'passport', 'id'];

        foreach ($hints as $hint) {
            if (!str_contains($lower, $hint)) {
                continue;
            }

            foreach ($documents as $document) {
                $name = strtolower((string) ($document['original_name'] ?? ''));
                if (str_contains($name, $hint)) {
                    return $document;
                }
            }
        }

        return $documents[0] ?? null;
    }

    public static function extractTextFromFilePath(string $path, string $originalName): string
    {
        $file = [
            'tmp_name' => $path,
            'name' => $originalName,
            'type' => mime_content_type($path) ?: '',
            'error' => UPLOAD_ERR_OK,
        ];

        $text = self::readPlainUploadText($file);
        if (self::isPdfUpload($file)) {
            $text = self::pickBestText($text, self::extractPdfText($path));
        }

        return self::normalizeText($text);
    }

    public static function shouldAnswerFromDocument(string $message): bool
    {
        $message = trim($message);
        if ($message === '') {
            return false;
        }

        if (AssistantCalculations::looksLikeCalculationQuery($message)) {
            return false;
        }

        if (AssistantPracticeFaq::matches($message)) {
            return false;
        }

        if (self::isClearNonDocumentIntent($message)) {
            return false;
        }

        if (self::referencesUploadedDocument($message)) {
            return true;
        }

        return self::looksLikeDocumentFieldQuestion($message);
    }

    public static function looksLikeDocumentFieldQuestion(string $message): bool
    {
        $lower = strtolower(trim($message));
        if ($lower === '') {
            return false;
        }

        if (preg_match('/\b(revenue|clients?|cases?|appointments?|notifications?|dashboard)\b/', $lower)) {
            return false;
        }

        if (self::isClearNonDocumentIntent($message)) {
            return false;
        }

        if (AssistantPracticeFaq::matches($message)) {
            return false;
        }

        if (AssistantCalculations::looksLikeCalculationQuery($message)) {
            return false;
        }

        if (preg_match(
            '/\b(what is|what\'s|how much is|how much was|tell me|show me)\b.*\b(the )?(amount|total|fee|fees|balance|price|cost|payment|paid|vat|subtotal|grand total)\b/',
            $lower
        )) {
            return true;
        }

        if (preg_match(
            '/\b(the )?(amount|total fee|grand total|payment received|balance due|receipt number|invoice number|quotation number|case reference|matter reference|due date|bill to|billed to|vat amount|subtotal)\b/',
            $lower
        )) {
            return true;
        }

        if (preg_match('/\b(vat|subtotal|grand total|amount due|amount paid|financial|breakdown)\b/', $lower)) {
            return true;
        }

        return (bool) preg_match(
            '/\b(amount|fee|total|balance|paid|payment|vat)\b.*\b(on|in|from|for)\b/',
            $lower
        );
    }

    public static function looksLikeSummarizeRequest(string $message): bool
    {
        $lower = strtolower(trim($message));
        if ($lower === '') {
            return true;
        }

        return (bool) preg_match(
            '/\b(summarize|summary|sum up|overview|extract(?:\s+details?)?|scan|read|ocr|analy[sz]e|key details|pull details)\b/',
            $lower
        );
    }

    /**
     * @return array{content: string, alerts?: list<array<string, string>>}
     */
    public static function answerDocumentQuestion(string $message, string $text): array
    {
        $question = trim($message);
        $text = self::normalizeText($text);
        if (!self::hasMeaningfulText($text)) {
            throw new RuntimeException('No document text is available to answer from.');
        }

        $focus = self::detectQuestionFocus($question);
        $financial = self::extractFinancialFields($text);

        $quick = self::tryQuickAnswer($question, $text);
        if ($quick !== null) {
            return [
                'content' => '**From your document:** ' . $quick,
                'alerts' => self::isFinancialFocus($focus) ? [] : self::complianceAlertsForDocument($text),
            ];
        }

        if ($focus === 'amount') {
            if (isset($financial['Grand Total'])) {
                return [
                    'content' => '**From your document:** The grand total is **' . $financial['Grand Total'] . '**.',
                    'alerts' => [],
                ];
            }

            $amount = self::resolvePrimaryAmount($text);
            if ($amount !== null) {
                return [
                    'content' => '**From your document:** The amount is **' . $amount . '**.',
                    'alerts' => [],
                ];
            }
        }

        if ($focus === 'vat') {
            $vat = $financial['VAT Amount'] ?? self::formatMoneyDisplay(0);
            return [
                'content' => '**From your document:** The VAT amount is **' . $vat . '**.',
                'alerts' => [],
            ];
        }

        if ($focus === 'financial_summary') {
            $summary = self::formatFinancialSummary($financial);
            if ($summary !== '') {
                return [
                    'content' => '**From your document:** ' . $summary,
                    'alerts' => [],
                ];
            }
        }

        $structured = self::formatStructuredDetails(self::extractStructuredDetails($text));

        $passage = self::extractPassageForQuestion($question, $text);
        if ($passage !== null) {
            $fallback = $passage;
        } else {
            $fallback = $structured !== ''
                ? $structured
                : 'I could not find a direct answer. Here is extracted text from the file — try asking about a specific field such as total, date, or bill-to name.';
        }

        return [
            'content' => '**From your document:** ' . $fallback,
            'alerts' => self::isFinancialFocus($focus) ? [] : self::complianceAlertsForDocument($text),
        ];
    }

    private static function isFinancialFocus(string $focus): bool
    {
        return in_array($focus, ['amount', 'vat', 'subtotal', 'amount_due', 'amount_paid', 'financial_summary'], true);
    }

    /** @return list<array<string, string>> */
    private static function complianceAlertsForDocument(string $text): array
    {
        if (!self::looksLikeIdentityDocument($text)) {
            return [];
        }

        return AssistantCompliance::screenText($text);
    }

    private static function looksLikeIdentityDocument(string $text): bool
    {
        $lower = strtolower($text);

        if (preg_match('/\b(receipt|invoice|payment received|amount paid|bill to|grand total|vat amount)\b/', $lower)) {
            return false;
        }

        return (bool) preg_match(
            '/\b(affidavit|jurat|passport|power of attorney|statutory declaration|acknowledgment|birth\s*date|date of birth)\b/',
            $lower
        );
    }

    private static function detectQuestionFocus(string $question): string
    {
        $lower = strtolower(trim($question));

        if (preg_match('/\b(breakdown|all (?:the )?(?:amounts|figures|totals)|financial summary|summary of (?:amounts|charges|fees))\b/', $lower)) {
            return 'financial_summary';
        }

        if (preg_match('/\b(vat|value added tax)\b/', $lower) || preg_match('/\btax\s+(?:amount|total|rate)\b/', $lower)) {
            return 'vat';
        }

        if (preg_match('/\b(subtotal|sub-total|sub total)\b/', $lower)) {
            return 'subtotal';
        }

        if (preg_match('/\b(amount due|balance due|outstanding|owing|due to pay)\b/', $lower)) {
            return 'amount_due';
        }

        if (preg_match('/\b(amount paid|payment received|paid amount|how much (?:was )?paid)\b/', $lower)) {
            return 'amount_paid';
        }

        if (preg_match('/\b(invoice number|invoice #|receipt number|receipt #|receipt ref|quotation number|quotation #|quo-|reference number|case reference|matter ref|inv-|rcp-)\b/', $lower)) {
            return 'reference';
        }

        if (preg_match('/\b(date|due date|when|issued|valid until)\b/', $lower)) {
            return 'date';
        }

        if (preg_match('/\b(bill to|billed to|customer|client name|who is|name on)\b/', $lower)) {
            return 'party';
        }

        if (preg_match('/\b(email|phone|address|contact)\b/', $lower)) {
            return 'contact';
        }

        if (preg_match('/\b(grand total|total amount|overall total|main amount|how much)\b/', $lower)) {
            return 'amount';
        }

        if (preg_match('/\b(amount|sum|fee|price|cost|balance|money|payment)\b/', $lower)) {
            return 'amount';
        }

        return 'general';
    }

    private static function resolveLabeledAmount(string $text, string $labelPattern, bool $allowZero = false): ?string
    {
        $moneySuffix = '(?:[£$€]|Rs\.?|\?|\p{Sc})?\s*([\d,]+(?:\.\d{2})?)';
        $pattern = '/' . $labelPattern . '\s*:?\s*' . $moneySuffix . '/iu';
        if (!preg_match($pattern, $text, $match)) {
            return null;
        }

        $value = self::parseAmountValue((string) ($match[1] ?? ''));
        if (!$allowZero && $value <= 0) {
            return null;
        }

        return self::formatMoneyDisplay($value);
    }

    /**
     * @return array<string, string>
     */
    private static function extractFinancialFields(string $text): array
    {
        $definitions = [
            'Grand Total' => ['pattern' => '\bgrand\s+total\b', 'allow_zero' => false],
            'Amount Due' => ['pattern' => '\bamount\s+due\b', 'allow_zero' => true],
            'Amount Paid' => ['pattern' => '\bamount\s+paid\b', 'allow_zero' => true],
            'Payment received' => ['pattern' => '\bpayment\s+received\b', 'allow_zero' => true],
            'VAT Amount' => ['pattern' => '\bvat\s+(?:amount|total)\b', 'allow_zero' => true],
            'Subtotal' => ['pattern' => '\bsubtotal\b', 'allow_zero' => true],
            'Total fee' => ['pattern' => '\btotal\s+fee\b', 'allow_zero' => false],
            'Proposed amount' => ['pattern' => '\bproposed\s+amount\b', 'allow_zero' => false],
            'Net Amount (Excluding VAT)' => ['pattern' => '\bnet\s+amount\s*\(\s*excluding\s+vat\s*\)', 'allow_zero' => true],
            'Net Amount (Including VAT)' => ['pattern' => '\bnet\s+amount\s*\(\s*including\s+vat\s*\)', 'allow_zero' => true],
        ];

        $fields = [];
        foreach ($definitions as $label => $def) {
            $value = self::resolveLabeledAmount($text, $def['pattern'], $def['allow_zero']);
            if ($value !== null) {
                $fields[$label] = $value;
            }
        }

        if (!isset($fields['VAT Amount'])) {
            $tax = self::resolveLabeledAmount($text, '\btax\s*\([^)]+\)', true);
            if ($tax !== null) {
                $fields['VAT Amount'] = $tax;
            }
        }

        $primary = self::resolvePrimaryAmount($text);
        if ($primary !== null && !isset($fields['Grand Total'])) {
            $fields['Grand Total'] = $primary;
        }

        return $fields;
    }

    /**
     * @param array<string, string> $financial
     */
    private static function formatFinancialSummary(array $financial): string
    {
        if ($financial === []) {
            return '';
        }

        $order = [
            'Subtotal',
            'VAT Amount',
            'Net Amount (Excluding VAT)',
            'Net Amount (Including VAT)',
            'Total fee',
            'Proposed amount',
            'Grand Total',
            'Amount Due',
            'Amount Paid',
            'Payment received',
        ];

        $lines = ['**Financial summary**', ''];
        foreach ($order as $label) {
            if (isset($financial[$label])) {
                $lines[] = '• **' . $label . ':** ' . $financial[$label];
            }
        }

        foreach ($financial as $label => $value) {
            if (in_array($label, $order, true)) {
                continue;
            }
            $lines[] = '• **' . $label . ':** ' . $value;
        }

        return implode("\n", $lines);
    }

    private static function parseAmountValue(string $amount): float
    {
        $clean = preg_replace('/[^\d.]/', '', $amount) ?? '';

        return $clean === '' ? 0.0 : (float) $clean;
    }

    private static function formatMoneyDisplay(float $value): string
    {
        return formatCurrency(round($value, 2));
    }

    /**
     * Pick the document's main monetary figure (grand total, amount due, etc.)
     * and ignore incidental zero lines such as VAT £0.00.
     */
    private static function resolvePrimaryAmount(string $text): ?string
    {
        /** @var list<array{priority: int, value: float}> */
        $candidates = [];

        $labeledPatterns = [
            ['pattern' => '\bgrand\s+total\b', 'priority' => 100],
            ['pattern' => '\bamount\s+due\b', 'priority' => 95],
            ['pattern' => '\bproposed\s+amount\b', 'priority' => 95],
            ['pattern' => '\bpayment\s+received\b', 'priority' => 90],
            ['pattern' => '\btotal\s+fee\b', 'priority' => 85],
            ['pattern' => '\bamount\s+paid\b', 'priority' => 80],
            ['pattern' => '\bnet\s+amount\s*\(\s*including\s+vat\s*\)', 'priority' => 75],
            ['pattern' => '\bsubtotal\b', 'priority' => 40],
        ];

        $moneySuffix = '(?:[£$€]|Rs\.?|\?|\p{Sc})?\s*([\d,]+(?:\.\d{2})?)';

        foreach ($labeledPatterns as $rule) {
            $pattern = '/' . $rule['pattern'] . '\s*:?\s*' . $moneySuffix . '/iu';
            if (!preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
                continue;
            }

            foreach ($matches as $match) {
                $value = self::parseAmountValue((string) ($match[1] ?? ''));
                $candidates[] = ['priority' => $rule['priority'], 'value' => $value];
            }
        }

        // Summary-row "Total" (exclude VAT / unit price columns).
        if (preg_match_all(
            '/(?<!vat\s)(?<!unit\s)\btotal\b\s*:?\s*(?:[£$€]|Rs\.?|\?|\p{Sc})?\s*([\d,]+(?:\.\d{2})?)/iu',
            $text,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $value = self::parseAmountValue((string) ($match[1] ?? ''));
                $candidates[] = ['priority' => 60, 'value' => $value];
            }
        }

        $best = self::pickBestAmountCandidate($candidates);
        if ($best !== null) {
            return self::formatMoneyDisplay($best);
        }

        if (preg_match_all('/(?:[£$€]|Rs\.?|\?|\p{Sc})\s*([\d,]+(?:\.\d{2})?)/iu', $text, $matches)) {
            $values = [];
            foreach ($matches[1] as $raw) {
                $values[] = self::parseAmountValue((string) $raw);
            }

            $best = self::pickBestAmountCandidate(array_map(
                static fn (float $value): array => ['priority' => 20, 'value' => $value],
                $values
            ));
            if ($best !== null) {
                return self::formatMoneyDisplay($best);
            }
        }

        if (preg_match_all('/\b(?:amount|fee|total)\b[^0-9]{0,24}([\d,]+(?:\.\d{2})?)/iu', $text, $matches)) {
            $values = [];
            foreach ($matches[1] as $raw) {
                $values[] = self::parseAmountValue((string) $raw);
            }

            $best = self::pickBestAmountCandidate(array_map(
                static fn (float $value): array => ['priority' => 15, 'value' => $value],
                $values
            ));
            if ($best !== null) {
                return self::formatMoneyDisplay($best);
            }
        }

        return null;
    }

    /**
     * @param list<array{priority: int, value: float}> $candidates
     */
    private static function pickBestAmountCandidate(array $candidates): ?float
    {
        if ($candidates === []) {
            return null;
        }

        usort($candidates, static function (array $a, array $b): int {
            $aZero = $a['value'] <= 0;
            $bZero = $b['value'] <= 0;
            if ($aZero !== $bZero) {
                return $aZero <=> $bZero;
            }

            if ($a['priority'] !== $b['priority']) {
                return $b['priority'] <=> $a['priority'];
            }

            return $b['value'] <=> $a['value'];
        });

        $best = $candidates[0]['value'];

        return $best > 0 ? $best : null;
    }

    private static function extractAmountFromText(string $text): ?string
    {
        return self::resolvePrimaryAmount($text);
    }

    private static function tryQuickAnswer(string $question, string $text): ?string
    {
        $fields = self::extractStructuredDetails($text);
        $financial = self::extractFinancialFields($text);
        $focus = self::detectQuestionFocus($question);

        if ($focus === 'financial_summary') {
            $summary = self::formatFinancialSummary($financial);
            if ($summary !== '') {
                return $summary;
            }
        }

        if ($focus === 'vat') {
            if (isset($financial['VAT Amount'])) {
                return 'The VAT amount is **' . $financial['VAT Amount'] . '**.';
            }

            return 'The VAT amount on this document is **' . self::formatMoneyDisplay(0) . '**.';
        }

        if ($focus === 'subtotal') {
            if (isset($financial['Subtotal'])) {
                return 'The subtotal is **' . $financial['Subtotal'] . '**.';
            }

            return null;
        }

        if ($focus === 'amount_due') {
            if (isset($financial['Amount Due'])) {
                return 'The amount due is **' . $financial['Amount Due'] . '**.';
            }

            if (isset($financial['Grand Total'])) {
                return 'The amount due is **' . $financial['Grand Total'] . '**.';
            }

            return null;
        }

        if ($focus === 'amount_paid') {
            foreach (['Payment received', 'Amount Paid'] as $key) {
                if (isset($financial[$key])) {
                    return 'The amount paid is **' . $financial[$key] . '**.';
                }
            }

            if (isset($fields['Payment amount'])) {
                return 'The amount paid is **' . $fields['Payment amount'] . '**.';
            }

            return null;
        }

        if ($focus === 'amount') {
            if (isset($financial['Grand Total'])) {
                return 'The grand total is **' . $financial['Grand Total'] . '**.';
            }

            $amount = self::resolvePrimaryAmount($text);
            if ($amount !== null) {
                return 'The amount is **' . $amount . '**.';
            }

            return null;
        }

        if ($focus === 'reference') {
            $lower = strtolower($question);

            if (preg_match('/\bquotation\b/', $lower) && isset($fields['Quotation'])) {
                return 'The quotation number is **' . $fields['Quotation'] . '**.';
            }

            if (preg_match('/\b(receipt|rcp)\b/i', $question) && isset($fields['Receipt number'])) {
                return 'The receipt number is **' . $fields['Receipt number'] . '**.';
            }

            if (preg_match('/\binvoice\b/i', $question) && isset($fields['Invoice number'])) {
                return 'The invoice number is **' . $fields['Invoice number'] . '**.';
            }

            foreach (['Quotation', 'Invoice number', 'Receipt number', 'Case reference', 'Matter reference'] as $key) {
                if (isset($fields[$key])) {
                    return 'The ' . strtolower($key) . ' is **' . $fields[$key] . '**.';
                }
            }

            if (preg_match('/\b(RCP-\d{4}-[A-Z0-9]+)\b/i', $text, $match)) {
                return 'The receipt number is **' . strtoupper($match[1]) . '**.';
            }

            return null;
        }

        if ($focus === 'date') {
            $lower = strtolower($question);
            if (str_contains($lower, 'due') && isset($fields['Due date'])) {
                return 'The due date is **' . $fields['Due date'] . '**.';
            }
            if (isset($fields['Date'])) {
                return 'The date on the document is **' . $fields['Date'] . '**.';
            }

            return null;
        }

        if ($focus === 'party') {
            if (self::isInvoiceLikeDocument($text)) {
                $invoiceFields = self::extractInvoiceStructuredDetails($text);
                if (!empty($invoiceFields['Bill to'])) {
                    return '**Bill to:** ' . $invoiceFields['Bill to'];
                }
            }

            if (isset($fields['Bill to'])) {
                $billTo = self::formatContactBlock((string) $fields['Bill to']);

                return '**Bill to:** ' . $billTo;
            }
            if (isset($fields['To'])) {
                return '**To:** ' . $fields['To'];
            }
            if (isset($fields['Name'])) {
                return 'The name on the document is **' . $fields['Name'] . '**.';
            }

            return null;
        }

        if ($focus === 'contact') {
            if (preg_match('/\b(email)\b/i', $question) && isset($fields['Email'])) {
                return 'The email is **' . $fields['Email'] . '**.';
            }

            return null;
        }

        $targeted = self::tryAnswerFromAvailableFields($question, $fields, $financial);
        if ($targeted !== null) {
            return $targeted;
        }

        if ($focus === 'general' && preg_match(
            '/\b(amount|fee|vat|total|subtotal|price|charge|payment|financial|breakdown|quotation|invoice|receipt)\b/i',
            $question
        )) {
            $summary = self::formatFinancialSummary($financial);
            if ($summary !== '') {
                return $summary;
            }
        }

        return null;
    }

    private static function extractPassageForQuestion(string $question, string $text): ?string
    {
        $stopWords = [
            'a', 'an', 'the', 'is', 'are', 'was', 'were', 'what', 'which', 'who', 'whom', 'whose',
            'when', 'where', 'why', 'how', 'does', 'do', 'did', 'can', 'could', 'should', 'would',
            'about', 'from', 'with', 'this', 'that', 'these', 'those', 'document', 'file', 'say', 'says',
            'tell', 'me', 'please', 'show', 'give', 'find', 'any', 'there', 'have', 'has', 'on', 'in', 'for',
        ];

        $keywords = array_values(array_filter(
            preg_split('/\W+/u', strtolower($question)) ?: [],
            static function (string $word) use ($stopWords): bool {
                return mb_strlen($word) >= 3 && !in_array($word, $stopWords, true);
            }
        ));

        if ($keywords === []) {
            return null;
        }

        $chunks = preg_split('/\R+/u', $text) ?: [];
        $bestScore = 0;
        $bestLine = null;

        foreach ($chunks as $chunk) {
            $line = trim($chunk);
            if (mb_strlen($line) < 12) {
                continue;
            }

            $lower = strtolower($line);
            $score = 0;
            foreach ($keywords as $keyword) {
                if (str_contains($lower, $keyword)) {
                    $score += mb_strlen($keyword);
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestLine = $line;
            }
        }

        if ($bestLine === null || $bestScore < 4) {
            return null;
        }

        return mb_strimwidth($bestLine, 0, 420, '…');
    }

    /**
     * @param array<string, string> $fields
     * @param array<string, string> $financial
     */
    private static function tryAnswerFromAvailableFields(string $question, array $fields, array $financial): ?string
    {
        $lower = strtolower(trim($question));

        $keywordMap = [
            'vat' => 'VAT Amount',
            'subtotal' => 'Subtotal',
            'grand total' => 'Grand Total',
            'amount due' => 'Amount Due',
            'amount paid' => 'Amount Paid',
            'payment received' => 'Payment received',
            'quotation' => 'Quotation',
            'invoice number' => 'Invoice number',
            'receipt number' => 'Receipt number',
            'case reference' => 'Case reference',
            'bill to' => 'Bill to',
            'due date' => 'Due date',
            'email' => 'Email',
        ];

        foreach ($keywordMap as $keyword => $fieldKey) {
            if (!str_contains($lower, $keyword)) {
                continue;
            }

            if (isset($financial[$fieldKey])) {
                return 'The ' . strtolower($fieldKey) . ' is **' . $financial[$fieldKey] . '**.';
            }

            if (isset($fields[$fieldKey])) {
                return 'The ' . strtolower($fieldKey) . ' is **' . $fields[$fieldKey] . '**.';
            }
        }

        return null;
    }

    private static function isClearNonDocumentIntent(string $message): bool
    {
        $lower = strtolower(trim($message));

        if (AssistantRouter::looksLikeCaseDocumentUpload($message)) {
            return true;
        }

        if (AssistantCalculations::looksLikeCalculationQuery($message)) {
            return true;
        }

        if (AssistantKnowledge::looksLikeSystemQuery($message)
            || AssistantKnowledge::looksLikeCapabilitiesQuery($message)) {
            return true;
        }

        if (AssistantPracticeFaq::matches($message)) {
            return true;
        }

        if (preg_match(
            '/\b(how many|client count|active cases|total revenue|revenue by|upcoming appointments|recent payments|overdue invoices?|unread notifications|start intake|schedule appointment|book appointment|cancel appointment)\b/',
            $lower
        )) {
            return true;
        }

        if (preg_match('/\b(find|search|look up)\b.*\b(client|case)\b/', $lower)
            && !preg_match('/\b(document|receipt|invoice|letter|file|upload|attachment)\b/', $lower)) {
            return true;
        }

        if (preg_match('/\b(jurat|apostille|affidavit|poa|notary public|acknowledgment|statutory declaration)\b/', $lower)
            && !preg_match('/\b(receipt|invoice|document|letter|file|upload|attached|provided)\b/', $lower)) {
            return true;
        }

        return false;
    }

    private static function normalizeText(string $text): string
    {
        $text = assistantSanitizeUtf8($text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? '';
        $text = preg_replace('/\R{3,}/u', "\n\n", $text) ?? '';

        return trim($text);
    }

    private static function hasMeaningfulText(string $text): bool
    {
        $text = self::normalizeText($text);

        return $text !== '' && (bool) preg_match('/[\p{L}\p{N}]{2,}/u', $text);
    }

    private static function pickBestText(string ...$candidates): string
    {
        $best = '';

        foreach ($candidates as $candidate) {
            $candidate = self::normalizeText($candidate);
            if ($candidate === '') {
                continue;
            }

            if (!self::hasMeaningfulText($candidate)) {
                continue;
            }

            if (mb_strlen($candidate) > mb_strlen($best)) {
                $best = $candidate;
            }
        }

        return $best;
    }

    private static function isImageUpload(array $file): bool
    {
        $name = strtolower((string) ($file['name'] ?? ''));
        $mime = strtolower((string) ($file['type'] ?? ''));

        return preg_match('/\.(jpe?g|png|gif|webp)$/i', $name) || str_starts_with($mime, 'image/');
    }

    private static function readPlainUploadText(array $file): string
    {
        $path = (string) ($file['tmp_name'] ?? '');
        $name = strtolower((string) ($file['name'] ?? ''));
        $mime = strtolower((string) ($file['type'] ?? ''));

        if ($path === '' || !is_readable($path)) {
            return '';
        }

        if (self::isPdfUpload($file)) {
            return '';
        }

        if (preg_match('/\.(html?|htm)$/i', $name) || str_contains($mime, 'html')) {
            return self::extractHtmlText($path);
        }

        if (!preg_match('/\.(txt|csv|md)$/i', $name) && !str_starts_with($mime, 'text/')) {
            return '';
        }

        $raw = file_get_contents($path);

        return is_string($raw) ? self::normalizeText($raw) : '';
    }

    private static function extractHtmlText(string $path): string
    {
        $raw = file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return '';
        }

        if (preg_match('/<body[^>]*>(.*)<\/body>/is', $raw, $match)) {
            $raw = $match[1];
        }

        $raw = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $raw) ?? $raw;
        $raw = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $raw) ?? $raw;
        $raw = preg_replace('/<\/(p|div|h\d|li|tr|br)\b[^>]*>/i', "\n", $raw) ?? $raw;
        $text = html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return self::normalizeText($text);
    }

    private static function isPdfUpload(array $file): bool
    {
        $name = strtolower((string) ($file['name'] ?? ''));
        $mime = strtolower((string) ($file['type'] ?? ''));

        return str_ends_with($name, '.pdf') || $mime === 'application/pdf';
    }

    private static function extractPdfText(string $path): string
    {
        if ($path === '' || !is_readable($path)) {
            return '';
        }

        $fromPoppler = self::extractPdfTextWithPoppler($path);
        if (self::hasMeaningfulText($fromPoppler)) {
            return $fromPoppler;
        }

        return self::extractPdfTextWithPdfParser($path);
    }

    private static function summarizeImageUpload(array $file, string $question): string
    {
        unset($file, $question);

        return '**Could not read this screenshot.** Attach a clear image and wait for **Reading screenshot…** to finish, '
            . 'or use a text-based PDF. Then ask your question (e.g. _what is the amount?_).';
    }

    private static function buildBriefOverview(array $fields, string $text): string
    {
        $bullets = [];

        if (preg_match('/\bRECEIPT\b/i', $text)) {
            $bullets[] = 'This is a receipt or payment confirmation.';
        } elseif (preg_match('/\bINVOICE\b/i', $text)) {
            $bullets[] = 'This is an invoice or billing document.';
        } elseif (preg_match('/\b(agreement|contract|deed|affidavit|letter)\b/i', $text, $m)) {
            $bullets[] = 'This appears to be a ' . strtolower($m[1]) . ' or formal letter.';
        } else {
            $bullets[] = 'Document text was extracted successfully.';
        }

        if (isset($fields['Bill to'])) {
            $billToLines = preg_split('/\R+/', (string) $fields['Bill to']) ?: [];
            $billToHeadline = trim((string) ($billToLines[0] ?? $fields['Bill to']));
            $bullets[] = 'Bill to: ' . $billToHeadline;
        } elseif (isset($fields['To'])) {
            $bullets[] = 'Addressed to: ' . $fields['To'];
        }

        if (isset($fields['Payment amount'])) {
            $bullets[] = 'Payment received: ' . $fields['Payment amount'];
        } elseif (isset($fields['Grand Total'])) {
            $bullets[] = 'Grand total: ' . $fields['Grand Total'];
        } elseif (isset($fields['Amount Due'])) {
            $bullets[] = 'Amount due: ' . $fields['Amount Due'];
        } elseif (isset($fields['Total'])) {
            $bullets[] = 'Total: ' . $fields['Total'];
        }

        if (isset($fields['Issue date'])) {
            $bullets[] = 'Issue date: ' . $fields['Issue date'];
        } elseif (isset($fields['Date'])) {
            $bullets[] = 'Date: ' . $fields['Date'];
        }

        if (isset($fields['Due date'])) {
            $bullets[] = 'Due date: ' . $fields['Due date'];
        }

        if (isset($fields['Receipt number'])) {
            $bullets[] = 'Receipt #: ' . $fields['Receipt number'];
        } elseif (isset($fields['Invoice number'])) {
            $bullets[] = 'Invoice #: ' . $fields['Invoice number'];
        } elseif (isset($fields['Case reference'])) {
            $bullets[] = 'Case ref: ' . $fields['Case reference'];
        }

        $lines = ['**Summary**', ''];
        foreach ($bullets as $bullet) {
            $lines[] = '• ' . $bullet;
        }

        $lines[] = '';
        $lines[] = '_Ask a follow-up in chat (e.g. “what is the amount?” or “who is it billed to?”)._';

        return implode("\n", $lines);
    }

    private static function summarizeText(string $text, string $question): string
    {
        $text = self::normalizeText($text);
        $question = trim($question) !== ''
            ? $question
            : 'Summarize this document: document type, parties, dates, reference numbers, amounts, and important clauses or action items.';

        $structured = self::extractStructuredDetails($text);
        $structuredBlock = self::formatStructuredDetails($structured);
        $briefOverview = self::buildBriefOverview($structured, $text);
        $isInvoice = self::isInvoiceLikeDocument($text);

        $parts = [$briefOverview];
        if ($structuredBlock !== '') {
            $parts[] = '';
            $parts[] = $structuredBlock;
        }

        if (!$isInvoice || count($structured) < 4) {
            $parts[] = '';
            $parts[] = self::buildTextFallbackSummary($text, $isInvoice);
        }

        $clauses = self::extractImportantClauses($text);
        if ($clauses !== '') {
            $parts[] = '';
            $parts[] = "**Notable clauses / action items**\n\n" . $clauses;
        }

        return implode("\n", $parts);
    }

    private static function extractImportantClauses(string $text): string
    {
        if ($text === '') {
            return '';
        }

        if (self::isInvoiceLikeDocument($text) || preg_match('/\bRECEIPT\b/i', $text)) {
            return '';
        }

        $keywords = '/\b(shall|must|agree to|hereby|witness|notwithstanding|whereas|liable|indemnif|termination|governing law|jurisdiction|power of attorney|bound by)\b/i';
        $sentences = preg_split('/(?<=[.?!])\s+/u', $text) ?: [];
        $clauses = [];

        foreach ($sentences as $sentence) {
            $sentence = trim(preg_replace('/\s+/', ' ', $sentence) ?? '');
            if ($sentence === '' || mb_strlen($sentence) < 25) {
                continue;
            }
            if (!preg_match($keywords, $sentence)) {
                continue;
            }
            $clauses[] = '• ' . mb_strimwidth($sentence, 0, 220, '…');
            if (count($clauses) >= 5) {
                break;
            }
        }

        return implode("\n", $clauses);
    }

    private static function normalizeSummaryBullets(string $summary): string
    {
        $summary = trim($summary);
        if ($summary === '') {
            return '';
        }

        $lines = preg_split('/\R+/', $summary) ?: [];
        $out = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (preg_match('/^[-*•]\s+/', $line)) {
                $out[] = $line;
                continue;
            }

            if (preg_match('/^\d+[.)]\s+/', $line)) {
                $out[] = preg_replace('/^\d+[.)]\s+/', '• ', $line) ?? ('• ' . $line);
                continue;
            }

            $out[] = '• ' . $line;
        }

        return implode("\n", $out);
    }

    private static function extractPdfTextWithPoppler(string $path): string
    {
        $pdftotext = self::popplerExecutable('pdftotext');
        if ($pdftotext === null) {
            return '';
        }

        $output = self::runCommand([$pdftotext, '-layout', '-enc', 'UTF-8', $path, '-']);

        return self::normalizeText($output);
    }

    private static function extractPdfTextWithPdfParser(string $path): string
    {
        try {
            if (!class_exists(\Smalot\PdfParser\Parser::class)) {
                require_once __DIR__ . '/lib/pdfparser/alt_autoload.php';
            }

            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($path);
            $text = $pdf->getText();

            return self::normalizeText(is_string($text) ? $text : '');
        } catch (Throwable $e) {
            error_log('Assistant PDF parser: ' . $e->getMessage());

            return '';
        }
    }

    private static function popplerBinDir(): ?string
    {
        $dir = realpath(__DIR__ . '/../bin/poppler/poppler-24.08.0/Library/bin');

        return is_dir($dir) ? $dir : null;
    }

    private static function popplerExecutable(string $name): ?string
    {
        $dir = self::popplerBinDir();
        if ($dir === null) {
            return null;
        }

        $exe = $dir . DIRECTORY_SEPARATOR . $name . (PHP_OS_FAMILY === 'Windows' ? '.exe' : '');

        return is_file($exe) ? $exe : null;
    }

    /** @param list<string> $command */
    private static function runCommand(array $command): string
    {
        if ($command === []) {
            return '';
        }

        if (function_exists('proc_open')) {
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = proc_open($command, $descriptors, $pipes, self::popplerBinDir() ?: null);
            if (is_resource($process)) {
                fclose($pipes[0]);
                $stdout = stream_get_contents($pipes[1]) ?: '';
                $stderr = stream_get_contents($pipes[2]) ?: '';
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);

                $output = trim($stdout !== '' ? $stdout : $stderr);
                if ($output !== '') {
                    return $output;
                }
            }
        }

        return self::runCommandWithShell($command);
    }

    /** @param list<string> $command */
    private static function runCommandWithShell(array $command): string
    {
        if (!function_exists('shell_exec') || $command === []) {
            return '';
        }

        $escaped = array_map(static fn (string $part): string => self::escapeShellArg($part), $command);
        $commandLine = implode(' ', $escaped);
        $cwd = self::popplerBinDir();

        if ($cwd !== null && $cwd !== '') {
            if (PHP_OS_FAMILY === 'Windows') {
                $commandLine = 'cd /d ' . self::escapeShellArg($cwd) . ' && ' . $commandLine;
            } else {
                $commandLine = 'cd ' . self::escapeShellArg($cwd) . ' && ' . $commandLine;
            }
        }

        $output = shell_exec($commandLine);
        if (is_string($output) && trim($output) !== '') {
            return trim($output);
        }

        if (function_exists('exec')) {
            $lines = [];
            @exec($commandLine, $lines);
            if ($lines !== []) {
                return trim(implode("\n", $lines));
            }
        }

        return '';
    }

    private static function escapeShellArg(string $value): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return '"' . str_replace('"', '""', $value) . '"';
        }

        return escapeshellarg($value);
    }

    private static function isInvoiceLikeDocument(string $text): bool
    {
        return (bool) preg_match('/\bINVOICE\b/i', $text)
            || (bool) preg_match('/\bINV-\d{4}-[A-Z0-9]+\b/i', $text);
    }

    private static function formatContactBlock(string $raw): string
    {
        $raw = trim(self::normalizeText($raw));
        if ($raw === '') {
            return '';
        }

        $raw = preg_replace('/\s+([A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,})\s*$/iu', "\n$1", $raw) ?? $raw;
        $raw = preg_replace('/,\s*(\d{4,6}\s+)/u', ",\n$1", $raw) ?? $raw;

        $lines = [];
        foreach (preg_split('/\R+/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        if (count($lines) === 1 && preg_match('/^([A-Z][a-z]+(?:\s+[A-Z][a-z]+){0,2})\s+((?:\d+|[a-z]).*)$/u', $lines[0], $match)) {
            $lines = [trim($match[1]), trim($match[2])];
        } elseif (isset($lines[0]) && preg_match('/^([A-Z][a-z]+(?:\s+[A-Z][a-z]+){0,2}),?\s+((?:[a-z]|\d).*)$/u', $lines[0], $match)) {
            array_splice($lines, 0, 1, [trim($match[1]), rtrim(trim($match[2]), ',')]);
        }

        if ($lines === []) {
            return mb_strimwidth($raw, 0, 200, '…');
        }

        return implode("\n", array_slice($lines, 0, 6));
    }

    private static function extractInvoiceLineItems(string $text): string
    {
        $items = [];

        if (preg_match_all(
            '/\b(Disbursement|Notarisation|Notarization|Legalisation|Legalization|Travel|Mobile\s+visit|Consultation|Apostille)\b\s+(\d+)\s+(?:[£$€]\s*)?([\d,]+\.\d{2})\s+(?:[£$€]\s*)?([\d,]+\.\d{2})\s+(?:[£$€]\s*)?([\d,]+\.\d{2})/iu',
            $text,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $match) {
                $items[] = sprintf(
                    '%s — qty %s · unit %s · VAT %s · line total %s',
                    trim($match[1]),
                    $match[2],
                    self::formatMoneyDisplay(self::parseAmountValue((string) $match[3])),
                    self::formatMoneyDisplay(self::parseAmountValue((string) $match[4])),
                    self::formatMoneyDisplay(self::parseAmountValue((string) $match[5]))
                );
            }
        }

        return $items !== [] ? implode("\n", $items) : '';
    }

    /**
     * @return array<string, string>
     */
    private static function extractInvoiceStructuredDetails(string $text): array
    {
        $fields = ['Document type' => 'Invoice'];

        if (preg_match('/#\s*(INV-\d{4}-[A-Z0-9]+)/i', $text, $match)) {
            $fields['Invoice number'] = strtoupper($match[1]);
        } elseif (preg_match('/\b(INV-\d{4}-[A-Z0-9]+)\b/i', $text, $match)) {
            $fields['Invoice number'] = strtoupper($match[1]);
        }

        if (preg_match('/issue\s*date\s*:?\s*(\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{2,4})/i', $text, $match)) {
            $fields['Issue date'] = trim($match[1]);
        }

        if (preg_match('/due\s*date\s*:?\s*(\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{2,4})/i', $text, $match)) {
            $fields['Due date'] = trim($match[1]);
        }

        if (preg_match('/INVOICE\s*#?\s*INV[-\w]+\s+([A-Za-z][A-Za-z0-9\s&\'.-]{2,45}?)(?:\s+(?:Street|St\.|Road|Rd)\b|\n)/i', $text, $match)) {
            $fields['From'] = trim($match[1]);
        }

        if (preg_match('/Bill\s*To:\s*(.+?)(?=Description|Quantity|Unit\s*Price|Subtotal|Payable\s*To|Thank\s+you)/is', $text, $match)) {
            $billTo = self::formatContactBlock((string) $match[1]);
            if ($billTo !== '') {
                $fields['Bill to'] = $billTo;
            }
        }

        $lineItems = self::extractInvoiceLineItems($text);
        if ($lineItems !== '') {
            $fields['Line items'] = $lineItems;
        }

        $financialOrder = [
            'Subtotal',
            'VAT Amount',
            'Net Amount (Excluding VAT)',
            'Net Amount (Including VAT)',
            'Grand Total',
            'Amount Paid',
            'Amount Due',
        ];
        $financial = self::extractFinancialFields($text);
        foreach ($financialOrder as $label) {
            if (!isset($financial[$label]) || $financial[$label] === self::formatMoneyDisplay(0.0)) {
                continue;
            }
            $fields[$label] = $financial[$label];
        }

        if (preg_match('/Payable\s*To:\s*([^\n]+?)(?:\s+Account\s+name:|\n|$)/i', $text, $match)) {
            $fields['Payable to'] = trim($match[1]);
        }

        if (preg_match('/Account\s*name:\s*([^\n]+?)(?:\s+Account\s+number:|\n|$)/i', $text, $match)) {
            $fields['Bank account name'] = trim($match[1]);
        }

        if (preg_match('/Account\s*number:\s*(\d+)/i', $text, $match)) {
            $fields['Account number'] = trim($match[1]);
        }

        if (preg_match('/\b(CASE-\d{4}-\d+)\b/i', $text, $match)) {
            $fields['Case reference'] = strtoupper($match[1]);
        }

        return $fields;
    }

    /** @return array<string, string> */
    private static function extractStructuredDetails(string $text): array
    {
        if ($text === '') {
            return [];
        }

        if (self::isInvoiceLikeDocument($text)) {
            return self::extractInvoiceStructuredDetails($text);
        }

        return self::extractGenericStructuredDetails($text);
    }

    /** @return array<string, string> */
    private static function extractGenericStructuredDetails(string $text): array
    {
        $fields = [];

        if (preg_match('/\b(CASE-\d{4}-\d{4,})\b/i', $text, $match)) {
            $fields['Case reference'] = strtoupper($match[1]);
        }

        if (preg_match('/Matter\s*ref:?\s*([A-Z0-9-]+)/i', $text, $match)) {
            $fields['Matter reference'] = strtoupper(trim($match[1]));
        }

        if (preg_match('/\bTo:\s*([^\n]+)/i', $text, $match)) {
            $fields['To'] = mb_strimwidth(trim($match[1]), 0, 120, '…');
        }

        if (preg_match('/\b(INV-\d{4}-[A-Z0-9]+)\b/i', $text, $match)) {
            $fields['Invoice number'] = strtoupper($match[1]);
        } elseif (preg_match('/\b(RCP-\d{4}-[A-Z0-9]+)\b/i', $text, $match)) {
            $fields['Receipt number'] = strtoupper($match[1]);
        } elseif (preg_match('/#\s*(INV[-\w]+)/i', $text, $match)) {
            $fields['Invoice number'] = $match[1];
        } elseif (preg_match('/invoice\s*#?\s*([A-Z0-9-]+)/i', $text, $match)) {
            $fields['Invoice number'] = $match[1];
        }

        if (preg_match('/payment\s+received\s*:\s*(?:[£$€]|\?|\p{Sc})?\s*([\d,]+(?:\.\d{2})?)/iu', $text, $match)) {
            $value = self::parseAmountValue((string) ($match[1] ?? ''));
            if ($value > 0) {
                $fields['Payment amount'] = self::formatMoneyDisplay($value);
            }
        }

        if (preg_match('/\b(QUO-\d{4}-\d{4,})\b/i', $text, $match)) {
            $fields['Quotation'] = strtoupper($match[1]);
        }

        if (preg_match('/\bdate\s*:?\s*(\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{2,4}|\d{4}-\d{2}-\d{2}|[A-Za-z]+ \d{1,2}, \d{4})/i', $text, $match)) {
            $fields['Date'] = trim($match[1]);
        }

        if (preg_match('/due\s*date\s*:?\s*(\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{2,4})/i', $text, $match)) {
            $fields['Due date'] = trim($match[1]);
        }

        if (preg_match('/bill\s*to\s*:?\s*(.+?)(?=Description|Quantity|Unit\s*Price|Subtotal|Payable|Thank\s+you|\n\n)/is', $text, $match)) {
            $billTo = self::formatContactBlock((string) $match[1]);
            if ($billTo !== '') {
                $fields['Bill to'] = $billTo;
            }
        }

        if (preg_match('/case\s*reference\s*:?\s*([A-Z0-9-]+)/i', $text, $match) && !isset($fields['Case reference'])) {
            $fields['Case reference'] = strtoupper($match[1]);
        }

        foreach (self::extractFinancialFields($text) as $label => $value) {
            $fields[$label] = $value;
        }

        if (!isset($fields['Total']) && isset($fields['Grand Total'])) {
            $fields['Total'] = $fields['Grand Total'];
        } elseif (!isset($fields['Grand Total'])) {
            $primaryAmount = self::resolvePrimaryAmount($text);
            if ($primaryAmount !== null) {
                $fields['Grand Total'] = $primaryAmount;
                $fields['Total'] = $primaryAmount;
            }
        }

        if (isset($fields['Grand Total'], $fields['Total']) && $fields['Grand Total'] === $fields['Total']) {
            unset($fields['Total']);
        }

        if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $text, $match) && !isset($fields['Bill to'])) {
            $fields['Email'] = strtolower($match[0]);
        }

        return $fields;
    }

    /** @param array<string, string> $fields */
    private static function formatStructuredDetails(array $fields): string
    {
        if ($fields === []) {
            return '';
        }

        $isInvoice = ($fields['Document type'] ?? '') === 'Invoice';
        $order = [
            'Document type',
            'From',
            'Bill to',
            'To',
            'Quotation',
            'Invoice number',
            'Receipt number',
            'Case reference',
            'Matter reference',
            'Issue date',
            'Date',
            'Due date',
            'Line items',
            'Subtotal',
            'VAT Amount',
            'Net Amount (Excluding VAT)',
            'Net Amount (Including VAT)',
            'Total fee',
            'Proposed amount',
            'Grand Total',
            'Total',
            'Amount Due',
            'Amount Paid',
            'Payment received',
            'Payment amount',
            'Payable to',
            'Bank account name',
            'Account number',
            'Email',
        ];

        $title = $isInvoice ? '**Invoice details**' : '**Key details extracted**';
        $lines = [$title];
        $used = [];

        foreach ($order as $label) {
            if (!isset($fields[$label]) || isset($used[$label])) {
                continue;
            }
            $lines[] = '• **' . $label . ':** ' . $fields[$label];
            $used[$label] = true;
        }

        foreach ($fields as $label => $value) {
            if (isset($used[$label])) {
                continue;
            }
            $lines[] = '• **' . $label . ':** ' . $value;
        }

        return implode("\n", $lines);
    }

    private static function buildTextFallbackSummary(string $text, bool $compact = false): string
    {
        if ($compact) {
            return '_Ask a follow-up in chat (e.g. “what is the amount due?” or “who is it billed to?”)._';
        }

        $snippet = self::formatExtractedTextForDisplay(mb_substr($text, 0, 6000));
        $lines = preg_split('/\R+/', $snippet) ?: [];
        $bullets = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '' && mb_strlen($line) > 1) {
                $bullets[] = '• ' . $line;
            }
            if (count($bullets) >= 40) {
                break;
            }
        }

        if ($bullets === []) {
            $bullets[] = '• ' . mb_substr(self::formatExtractedTextForDisplay($snippet), 0, 500);
        }

        return "**Extracted text**\n\n" . implode("\n", $bullets);
    }

    private static function formatExtractedTextForDisplay(string $text): string
    {
        $text = self::normalizeText($text);
        if ($text === '') {
            return '';
        }

        $labels = [
            'Bill To:',
            'Bill to:',
            'Ship To:',
            'Issue Date:',
            'Due Date:',
            'Due date:',
            'Invoice reference:',
            'Case reference',
            'Description',
            'Quantity',
            'Unit Price',
            'Subtotal',
            'VAT Amount',
            'VAT Total',
            'Total fee:',
            'Thank you',
        ];

        foreach ($labels as $label) {
            $pattern = '/\s+(' . preg_quote($label, '/') . ')/iu';
            $text = preg_replace($pattern, "\n\n$1", $text) ?? $text;
        }

        $text = preg_replace('/(Bill To:)\s*/iu', "$1\n", $text) ?? $text;
        $text = preg_replace('/(Bill to:)\s*/iu', "$1\n", $text) ?? $text;
        $text = preg_replace('/\s+(RECEIPT\s+#)/iu', "\n\n$1", $text) ?? $text;
        $text = preg_replace('/\s+(INVOICE\s+#)/iu', "\n\n$1", $text) ?? $text;
        $text = preg_replace('/\s+(INV-\d{4}-)/iu', "\n\n$1", $text) ?? $text;
        $text = preg_replace('/\s+([A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,})/iu', "\n$1", $text) ?? $text;
        $text = preg_replace('/\s+(\+\d{7,15})\b/u', "\n$1", $text) ?? $text;
        $text = preg_replace('/,\s*(\d{4,6})\s+/u', ",\n$1 ", $text) ?? $text;
        $text = preg_replace('/\s+([a-z]{4,})\s+(\+\d{7,})/u', "\n$1\n$2", $text) ?? $text;
        $text = preg_replace('/([a-z0-9.-]+\.[a-z]{2,})\s+([a-z]{4,})\s+/iu', "$1\n$2\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }

    private static function friendlyAnalysisError(Throwable $e): string
    {
        $message = $e->getMessage();

        if (str_contains($message, 'vision') || str_contains($message, 'image') || str_contains($message, 'multimodal')) {
            return 'Could not read text from this image. Try a clearer photo, or attach a PDF with selectable text.';
        }

        return 'Could not analyze this image. Try a clearer photo, or use a text-based PDF.';
    }

    private static function validateUpload(array $file): void
    {
        $config = require __DIR__ . '/../config/config.php';
        $maxSize = (int) ($config['upload']['max_size'] ?? 10 * 1024 * 1024);
        $size = (int) ($file['size'] ?? 0);

        if ($size <= 0) {
            throw new RuntimeException('Uploaded file is empty.');
        }

        if ($size > $maxSize) {
            throw new RuntimeException('File is too large. Maximum size is ' . (int) round($maxSize / 1024 / 1024) . ' MB.');
        }

        $name = strtolower((string) ($file['name'] ?? ''));
        $allowed = ['.pdf', '.html', '.htm', '.txt', '.csv', '.md', '.jpg', '.jpeg', '.png', '.gif', '.webp'];
        $allowedType = false;

        foreach ($allowed as $extension) {
            if (str_ends_with($name, $extension)) {
                $allowedType = true;
                break;
            }
        }

        if (!$allowedType) {
            throw new RuntimeException('Unsupported file type. Upload PDF, HTML, text, or image files.');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '') {
            throw new RuntimeException('Invalid upload.');
        }

        if (!is_uploaded_file($tmp) && !is_readable($tmp)) {
            throw new RuntimeException('Invalid upload.');
        }
    }
}
