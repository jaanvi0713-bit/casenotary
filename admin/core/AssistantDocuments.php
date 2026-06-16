<?php



declare(strict_types=1);



class AssistantDocuments

{

    private const VISION_PAGE_LIMIT = 3;



    /** @return array{content: string, alerts?: list<array<string, string>>} */

    public static function handleUpload(array $file, string $message): array

    {

        self::validateUpload($file);

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {

            throw new RuntimeException('File upload failed.');

        }



        $question = trim($message) !== ''

            ? trim($message)

            : 'Extract key names, dates, identity details, and important clauses from this document.';



        $usedVision = false;

        $text = self::extractText($file);

        $text = assistantSanitizeUtf8($text);

        $summary = '';

        if (self::isPdfFile($file) && trim($text) === '') {

            $pageImages = self::renderPdfPages((string) ($file['tmp_name'] ?? ''), self::VISION_PAGE_LIMIT);

            if ($pageImages === []) {

                throw new RuntimeException(

                    'Could not read this PDF. Try uploading a photo of the document instead.'

                );

            }

            try {

                $summary = self::summarizeWithFallback('', $question, $file, true, $pageImages);

                $usedVision = !str_starts_with($summary, '**Extracted text**');

            } finally {

                self::cleanupTempFiles($pageImages);

            }

        } else {

            $summary = self::summarizeWithFallback($text, $question, $file, false);

        }



        $alerts = AssistantCompliance::screenText(($text !== '' ? $text : $summary) . "\n" . $summary);



        $content = $usedVision

            ? "**Document analysis** _(scanned PDF — read with vision)_\n\n" . $summary

            : "**Document analysis**\n\n" . $summary;



        if ($alerts !== []) {

            $content .= "\n\n**Compliance flags detected** — review the alerts below.";

        }



        return [

            'content' => $content,

            'alerts' => $alerts,

        ];

    }



    public static function extractText(array $file): string

    {

        $path = (string) ($file['tmp_name'] ?? '');

        $name = strtolower((string) ($file['name'] ?? ''));

        $mime = strtolower((string) ($file['type'] ?? ''));



        if ($path === '' || !is_readable($path)) {

            throw new RuntimeException('Uploaded file is not readable.');

        }



        if (self::isPdfFile($file)) {

            return self::extractPdfText($path);

        }



        if (preg_match('/\.(txt|csv|md)$/i', $name) || str_starts_with($mime, 'text/')) {

            $text = file_get_contents($path);



            return is_string($text) ? assistantSanitizeUtf8(trim($text)) : '';

        }



        if (preg_match('/\.(jpe?g|png|gif|webp)$/i', $name) || str_starts_with($mime, 'image/')) {

            return '[Image uploaded — using vision model for extraction]';

        }



        throw new RuntimeException('Unsupported file type. Upload PDF, text, or image files.');

    }



    private static function isPdfFile(array $file): bool

    {

        $name = strtolower((string) ($file['name'] ?? ''));

        $mime = strtolower((string) ($file['type'] ?? ''));



        return str_ends_with($name, '.pdf') || $mime === 'application/pdf';

    }



    private static function extractPdfText(string $path): string

    {

        $text = self::extractPdfTextWithParser($path);

        if ($text !== '') {

            return $text;

        }



        return self::extractPdfTextWithPoppler($path);

    }



    private static function extractPdfTextWithParser(string $path): string

    {

        $autoload = __DIR__ . '/lib/pdfparser/alt_autoload.php';

        if (!is_file($autoload)) {

            return '';

        }



        require_once $autoload;



        try {

            $parser = new Smalot\PdfParser\Parser();

            $pdf = $parser->parseFile($path);



            return trim($pdf->getText());

        } catch (Throwable) {

            return '';

        }

    }



    private static function extractPdfTextWithPoppler(string $path): string

    {

        $pdftotext = self::popplerBinary('pdftotext');

        if ($pdftotext === null) {

            return '';

        }



        foreach ([['-layout'], ['-raw'], []] as $extraFlags) {

            $command = [$pdftotext];

            foreach ($extraFlags as $flag) {
                $command[] = $flag;
            }

            $command[] = $path;

            $command[] = '-';



            $text = trim(self::runCommand($command));

            if ($text !== '') {

                return $text;

            }

        }



        return '';

    }



    /** @return list<string> */

    private static function renderPdfPages(string $path, int $maxPages): array

    {

        $pdftoppm = self::popplerBinary('pdftoppm');

        if ($pdftoppm === null || !is_readable($path)) {

            return [];

        }



        $maxPages = max(1, min($maxPages, 5));

        $outputDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cn_pdf_' . bin2hex(random_bytes(6));

        if (!mkdir($outputDir) && !is_dir($outputDir)) {

            return [];

        }



        $prefix = $outputDir . DIRECTORY_SEPARATOR . 'page';

        $command = [

            $pdftoppm,

            '-png',

            '-r',

            '150',

            '-f',

            '1',

            '-l',

            (string) $maxPages,

            $path,

            $prefix,

        ];



        self::runCommand($command);



        $images = glob($prefix . '-*.png') ?: [];

        sort($images, SORT_NATURAL);



        if ($images === []) {

            self::removeDirectory($outputDir);



            return [];

        }



        return array_values($images);

    }



    private static function popplerBinary(string $name): ?string

    {

        $relative = '/../../bin/poppler/poppler-24.08.0/Library/bin/' . $name;

        $base = __DIR__ . $relative;



        if (PHP_OS_FAMILY === 'Windows') {

            $exe = $base . '.exe';



            return is_file($exe) ? $exe : null;

        }



        return is_file($base) && is_executable($base) ? $base : null;

    }



    /** @param list<string> $command */

    private static function runCommand(array $command): string

    {

        $descriptors = [

            0 => ['pipe', 'r'],

            1 => ['pipe', 'w'],

            2 => ['pipe', 'w'],

        ];



        $process = proc_open($command, $descriptors, $pipes, self::popplerBinDir());

        if (!is_resource($process)) {

            return '';

        }



        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]) ?: '';

        $stderr = stream_get_contents($pipes[2]) ?: '';

        fclose($pipes[1]);

        fclose($pipes[2]);

        proc_close($process);



        return trim($stdout !== '' ? $stdout : $stderr);

    }



    /** @param list<string> $files */

    private static function cleanupTempFiles(array $files): void

    {

        $dirs = [];

        foreach ($files as $file) {

            if (is_file($file)) {

                $dirs[dirname($file)] = true;

                @unlink($file);

            }

        }



        foreach (array_keys($dirs) as $dir) {

            self::removeDirectory($dir);

        }

    }



    private static function removeDirectory(string $dir): void

    {

        if (!is_dir($dir)) {

            return;

        }



        foreach (glob($dir . DIRECTORY_SEPARATOR . '*') ?: [] as $item) {

            if (is_file($item)) {

                @unlink($item);

            }

        }



        @rmdir($dir);

    }

    private static function popplerBinDir(): ?string
    {
        $binary = self::popplerBinary('pdftoppm');

        return $binary !== null ? dirname($binary) : null;
    }

    /**
     * @param list<string> $pageImages
     */
    private static function summarizeWithFallback(
        string $text,
        string $question,
        array $file,
        bool $useVision,
        array $pageImages = []
    ): string {
        $text = assistantSanitizeUtf8(trim($text));
        $structured = self::extractStructuredDetails($text);
        $structuredBlock = self::formatStructuredDetails($structured);

        try {
            $aiSummary = $useVision
                ? OllamaService::summarizeDocumentImages($pageImages, $question)
                : OllamaService::summarizeDocument($text, $question, $file);

            $aiSummary = assistantSanitizeUtf8(trim($aiSummary));

            if (self::isUnhelpfulAiSummary($aiSummary, $text !== '')) {
                throw new RuntimeException('AI summary was not useful.');
            }

            if ($structuredBlock !== '') {
                return $structuredBlock . "\n\n**AI notes**\n" . $aiSummary;
            }

            return $aiSummary;
        } catch (Throwable $e) {
            if ($text !== '') {
                $fallback = self::buildTextFallbackSummary($text, $question);

                return $structuredBlock !== ''
                    ? $structuredBlock . "\n\n" . $fallback
                    : $fallback;
            }

            throw new RuntimeException(self::friendlyAnalysisError($e));
        }
    }

    /** @return array<string, string> */
    private static function extractStructuredDetails(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $fields = [];

        if (preg_match('/#\s*(INV[-\w]+)/i', $text, $match)) {
            $fields['Invoice number'] = $match[1];
        } elseif (preg_match('/invoice\s*#?\s*([A-Z0-9-]+)/i', $text, $match)) {
            $fields['Invoice number'] = $match[1];
        }

        if (preg_match('/\bdate\s*:?\s*(\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{2,4}|\d{4}-\d{2}-\d{2})/i', $text, $match)) {
            $fields['Date'] = $match[1];
        }

        if (preg_match('/due\s*date\s*:?\s*([^\n]+)/i', $text, $match)) {
            $fields['Due date'] = trim($match[1]);
        }

        if (preg_match('/bill\s*to\s*:?\s*(.+?)(?:\n\n|\n(?:payable|total|amount|net)\b)/is', $text, $match)) {
            $billTo = trim(preg_replace('/\s+/', ' ', $match[1]) ?? '');
            if ($billTo !== '') {
                $fields['Bill to'] = mb_strimwidth($billTo, 0, 200, '…');
            }
        }

        if (preg_match('/total\s*[£$€]?\s*([\d,]+(?:\.\d{2})?)/i', $text, $match)) {
            $fields['Total'] = $match[1];
        }

        if (preg_match_all('/(?:£|€|\$|rs\.?)\s*[\d,]+(?:\.\d{2})?/i', $text, $matches)) {
            $amounts = array_values(array_unique(array_map('trim', $matches[0])));
            if ($amounts !== []) {
                $fields['Amounts'] = implode('; ', array_slice($amounts, 0, 8));
            }
        }

        if (preg_match('/\b([A-Z][a-z]+(?:\s+[A-Z][a-z]+){1,3})\b/', $text, $match)
            && !isset($fields['Bill to'])) {
            $fields['Name'] = $match[1];
        }

        return $fields;
    }

    /** @param array<string, string> $fields */
    private static function formatStructuredDetails(array $fields): string
    {
        if ($fields === []) {
            return '';
        }

        $lines = ['**Key details extracted**'];
        foreach ($fields as $label => $value) {
            $lines[] = '• **' . $label . ':** ' . $value;
        }

        return implode("\n", $lines);
    }

    private static function isUnhelpfulAiSummary(string $summary, bool $hadDocumentText): bool
    {
        if ($summary === '') {
            return true;
        }

        if (!$hadDocumentText) {
            return false;
        }

        $lower = strtolower($summary);
        foreach ([
            'please provide',
            'please upload',
            'share the document',
            'provide the document',
            'provide the legal',
            'once you provide',
            'i\'d be happy to',
            'i would be happy',
            'happy to help',
        ] as $phrase) {
            if (str_contains($lower, $phrase)) {
                return true;
            }
        }

        return mb_strlen($summary) < 40 && !str_contains($summary, '•');
    }

    private static function buildTextFallbackSummary(string $text, string $question): string
    {
        $snippet = mb_substr(trim($text), 0, 6000);
        $lines = preg_split('/\R+/', $snippet) ?: [];
        $bullets = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '' && mb_strlen($line) > 2) {
                $bullets[] = '• ' . $line;
            }
            if (count($bullets) >= 40) {
                break;
            }
        }

        if ($bullets === []) {
            $bullets[] = '• ' . mb_substr($snippet, 0, 500);
        }

        return "**Extracted text** _(AI summary unavailable — showing document text)_\n\n"
            . implode("\n", $bullets);
    }

    private static function friendlyAnalysisError(Throwable $e): string
    {
        $message = $e->getMessage();

        if (str_contains($message, 'out-of-memory') || str_contains($message, 'allocate')) {
            return 'The AI model ran out of memory. Try a smaller Ollama model (e.g. `ollama pull qwen3.5:2b`) or close other apps.';
        }

        if (str_contains($message, 'not found')) {
            return 'The AI model is not available in Ollama. Run `ollama pull qwen2.5:0.5b` or set OLLAMA_MODEL in config.';
        }

        if (str_contains($message, 'Could not reach Ollama')) {
            return 'Could not reach Ollama. Start Ollama, then try again.';
        }

        if (str_contains($message, 'image') || str_contains($message, 'vision') || str_contains($message, 'multimodal')) {
            return 'Image reading needs a vision model in Ollama. Run `ollama pull llava`, then attach your PDF or screenshot again.';
        }

        return 'Could not analyze this document. Try a clearer PDF, or upload a photo of the page.';
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
        $allowed = ['.pdf', '.txt', '.csv', '.md', '.jpg', '.jpeg', '.png', '.gif', '.webp'];
        $allowedType = false;

        foreach ($allowed as $extension) {
            if (str_ends_with($name, $extension)) {
                $allowedType = true;
                break;
            }
        }

        if (!$allowedType) {
            throw new RuntimeException('Unsupported file type. Upload PDF, text, or image files.');
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '') {
            throw new RuntimeException('Invalid upload.');
        }

        // Some local Windows/WAMP setups may provide a readable temp file path
        // that does not pass is_uploaded_file() in this execution context.
        if (!is_uploaded_file($tmp) && !is_readable($tmp)) {
            throw new RuntimeException('Invalid upload.');
        }
    }

}


