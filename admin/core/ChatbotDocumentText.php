<?php

declare(strict_types=1);

class ChatbotDocumentText
{
    public static function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        if (!Database::columnExists('documents', 'extracted_text')) {
            try {
                Database::query('ALTER TABLE documents ADD COLUMN extracted_text MEDIUMTEXT NULL');
            } catch (Throwable $e) {
                // Column may already exist on concurrent requests.
            }
        }
    }

    public static function absolutePath(string $relativePath): string
    {
        $config = require __DIR__ . '/../config/config.php';
        $base   = rtrim((string) ($config['upload']['path'] ?? ''), '/\\');

        return $base . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($relativePath, '/\\'));
    }

    public static function extractFromFile(string $absolutePath, string $ext): string
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return '';
        }

        $ext = strtolower($ext);

        if (in_array($ext, ['txt', 'csv', 'md', 'log'], true)) {
            $raw = @file_get_contents($absolutePath);

            return self::normalizeText(is_string($raw) ? $raw : '');
        }

        if ($ext === 'pdf') {
            return self::extractPdfText($absolutePath);
        }

        if ($ext === 'docx') {
            return self::extractDocxText($absolutePath);
        }

        return '';
    }

    public static function indexDocumentRow(array $row): void
    {
        self::ensureSchema();

        $docId = (int) ($row['id'] ?? 0);
        if ($docId <= 0) {
            return;
        }

        $existing = trim((string) ($row['extracted_text'] ?? ''));
        if ($existing !== '' && mb_strlen($existing) > 30) {
            return;
        }

        $relative = (string) ($row['file_path'] ?? '');
        if ($relative === '') {
            return;
        }

        $extCol = documentExtensionColumn();
        $ext    = strtolower((string) ($row['file_type'] ?? $row[$extCol] ?? pathinfo($relative, PATHINFO_EXTENSION)));
        $text = self::extractFromFile(self::absolutePath($relative), $ext);
        if ($text === '') {
            return;
        }

        $stored = mb_strimwidth($text, 0, 65000, '');

        try {
            Database::query('UPDATE documents SET extracted_text = ? WHERE id = ?', [$stored, $docId]);
        } catch (Throwable $e) {
            // Non-fatal for search.
        }
    }

    /**
     * Index recent PDF/DOCX documents in scope so content search can match them.
     */
    public static function indexRecentSearchableDocuments(int $limit = 20): void
    {
        self::ensureSchema();

        $extSql = documentExtensionSql('d');
        $where  = ["{$extSql} IN ('pdf', 'docx', 'txt')", '(d.extracted_text IS NULL OR d.extracted_text = "")'];
        $params = [];
        chatbotAppendCaseScope($where, $params, 'cs', 'cl');

        $rows = Database::fetchAll(
            "SELECT d.id, d.file_path, {$extSql} AS file_type, d.extracted_text
             FROM documents d
             JOIN cases cs ON cs.id = d.case_id
             JOIN clients cl ON cl.id = cs.client_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY d.created_at DESC
             LIMIT " . max(1, min($limit, 40)),
            $params
        );

        foreach ($rows as $row) {
            self::indexDocumentRow($row);
        }
    }

    private static function extractPdfText(string $path): string
    {
        if (self::commandExists('pdftotext')) {
            $tmp = tempnam(sys_get_temp_dir(), 'cn_pdf_');
            if ($tmp !== false) {
                $cmd = 'pdftotext -layout ' . escapeshellarg($path) . ' ' . escapeshellarg($tmp) . ' 2>&1';
                @exec($cmd, $output, $code);
                if ($code === 0 && is_file($tmp)) {
                    $raw = @file_get_contents($tmp);
                    @unlink($tmp);
                    $text = self::normalizeText(is_string($raw) ? $raw : '');
                    if ($text !== '') {
                        return $text;
                    }
                } elseif (is_file($tmp)) {
                    @unlink($tmp);
                }
            }
        }

        return self::extractPdfStringsFallback($path);
    }

    private static function extractPdfStringsFallback(string $path): string
    {
        $raw = @file_get_contents($path);
        if (!is_string($raw) || $raw === '') {
            return '';
        }

        $parts = [];
        if (preg_match_all('/\(([^\\\\)]{2,})\)/', $raw, $matches)) {
            foreach ($matches[1] as $chunk) {
                $chunk = str_replace(['\\(', '\\)', '\\n', '\\r'], ['(', ')', ' ', ' '], $chunk);
                if (preg_match('/[a-zA-Z]{3,}/', $chunk)) {
                    $parts[] = $chunk;
                }
            }
        }

        return self::normalizeText(implode(' ', $parts));
    }

    private static function extractDocxText(string $path): string
    {
        if (!class_exists('ZipArchive')) {
            return '';
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            return '';
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if (!is_string($xml) || $xml === '') {
            return '';
        }

        $text = strip_tags(str_replace(['</w:p>', '<w:tab/>'], ["\n", "\t"], $xml));

        return self::normalizeText(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private static function normalizeText(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        if (!mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-8');
        }

        $raw = preg_replace('/[^\S\n]+/u', ' ', $raw) ?? $raw;
        $raw = preg_replace('/\n{3,}/', "\n\n", $raw) ?? $raw;

        return trim($raw);
    }

    private static function commandExists(string $command): bool
    {
        static $cache = [];

        if (array_key_exists($command, $cache)) {
            return $cache[$command];
        }

        $which = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'where' : 'which';
        @exec($which . ' ' . escapeshellarg($command) . ' 2>&1', $output, $code);
        $cache[$command] = $code === 0;

        return $cache[$command];
    }
}
