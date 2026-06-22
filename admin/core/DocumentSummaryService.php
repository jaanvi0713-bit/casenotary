<?php

declare(strict_types=1);

class DocumentSummaryService
{
    public static function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        if (Database::columnExists('documents', 'ai_summary')) {
            return;
        }

        $migration = __DIR__ . '/../sql/migrate_case_features.php';
        if (is_file($migration)) {
            try {
                require $migration;
            } catch (Throwable $e) {
                error_log('[DocumentSummaryService] Schema migration failed: ' . $e->getMessage());
            }
        }
    }

    public static function summarizeAfterUpload(int $documentId, int $caseId, string $relativePath, string $originalName): void
    {
        self::ensureSchema();
        if (!Database::columnExists('documents', 'ai_summary')) {
            return;
        }

        $summary = self::buildSummary($relativePath, $originalName);
        if ($summary === '') {
            return;
        }

        Database::query('UPDATE documents SET ai_summary = ? WHERE id = ?', [$summary, $documentId]);
        CaseService::logCaseEvent($caseId, 'document_summarized', [
            'document' => $originalName,
            'summary'  => mb_substr($summary, 0, 200),
        ]);
    }

    public static function buildSummary(string $relativePath, string $originalName): string
    {
        $config = require __DIR__ . '/../config/config.php';
        $fullPath = rtrim($config['upload']['path'], '/\\') . '/' . ltrim($relativePath, '/\\');
        if (!is_file($fullPath)) {
            return '';
        }

        $text = AssistantDocuments::extractTextFromFilePath($fullPath, $originalName);
        if ($text === '') {
            return 'Uploaded file: ' . $originalName . ' (no extractable text for summary).';
        }

        $excerpt = mb_substr($text, 0, 6000);

        return self::heuristicSummary($originalName, $excerpt);
    }

    private static function heuristicSummary(string $name, string $text): string
    {
        $lines = preg_split('/\R+/', $text) ?: [];
        $snippets = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (mb_strlen($line) < 12) {
                continue;
            }
            $snippets[] = '• ' . mb_substr($line, 0, 140);
            if (count($snippets) >= 4) {
                break;
            }
        }

        if ($snippets === []) {
            return 'Document **' . $name . '** uploaded (' . number_format(mb_strlen($text)) . ' characters extracted).';
        }

        return "**{$name}** — key points:\n" . implode("\n", $snippets);
    }

    /** @return list<array<string, mixed>> */
    public static function summariesForCase(int $caseId, int $limit = 5): array
    {
        self::ensureSchema();
        if (!Database::columnExists('documents', 'ai_summary')) {
            return [];
        }

        return Database::fetchAll(
            'SELECT id, original_name, ai_summary, created_at FROM documents
             WHERE case_id = ? AND ai_summary IS NOT NULL AND ai_summary <> ""
             ORDER BY created_at DESC LIMIT ?',
            [$caseId, $limit]
        );
    }
}
