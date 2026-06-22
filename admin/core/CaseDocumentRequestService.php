<?php

declare(strict_types=1);

class CaseDocumentRequestService
{
    public static function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        if (Database::tableExists('case_document_requests')) {
            return;
        }

        $migration = __DIR__ . '/../sql/migrate_case_features.php';
        if (is_file($migration)) {
            try {
                require $migration;
            } catch (Throwable $e) {
                error_log('[CaseDocumentRequestService] Schema migration failed: ' . $e->getMessage());
            }
        }
    }

    /** @return list<array<string, mixed>> */
    public static function listForCase(int $caseId): array
    {
        self::ensureSchema();
        if (!Database::tableExists('case_document_requests')) {
            return [];
        }

        return Database::fetchAll(
            'SELECT r.*, d.original_name AS document_name
             FROM case_document_requests r
             LEFT JOIN documents d ON d.id = r.document_id
             WHERE r.case_id = ?
             ORDER BY r.required DESC, r.created_at ASC',
            [$caseId]
        );
    }

    public static function add(int $caseId, array $data, ?int $userId = null): int
    {
        self::ensureSchema();
        $label = trim((string) ($data['label'] ?? ''));
        if ($label === '') {
            throw new RuntimeException('Document request label is required.');
        }

        $id = insertTableRow('case_document_requests', [
            'case_id'     => $caseId,
            'label'       => $label,
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'required'    => !empty($data['required']) ? 1 : 0,
            'status'      => 'pending',
            'created_by'  => $userId,
        ]);

        CaseService::logCaseEvent($caseId, 'document_requested', ['label' => $label], $userId);

        return $id;
    }

    public static function waive(int $requestId, int $caseId, ?int $userId = null): void
    {
        self::ensureSchema();
        $row = Database::fetch('SELECT * FROM case_document_requests WHERE id = ? AND case_id = ?', [$requestId, $caseId]);
        if (!$row) {
            throw new RuntimeException('Document request not found.');
        }

        Database::query(
            "UPDATE case_document_requests SET status = 'waived', updated_at = NOW() WHERE id = ?",
            [$requestId]
        );

        CaseService::logCaseEvent($caseId, 'document_request_waived', ['label' => $row['label']], $userId);
    }

    public static function delete(int $requestId, int $caseId): void
    {
        self::ensureSchema();
        Database::query('DELETE FROM case_document_requests WHERE id = ? AND case_id = ?', [$requestId, $caseId]);
    }

    public static function tryFulfillFromUpload(int $caseId, int $documentId, string $originalName): void
    {
        self::ensureSchema();
        if (!Database::tableExists('case_document_requests')) {
            return;
        }

        $pending = Database::fetchAll(
            "SELECT * FROM case_document_requests WHERE case_id = ? AND status = 'pending' ORDER BY id ASC",
            [$caseId]
        );

        $nameLower = strtolower($originalName);
        foreach ($pending as $req) {
            $labelLower = strtolower((string) $req['label']);
            if ($labelLower !== '' && (str_contains($nameLower, $labelLower) || str_contains($labelLower, pathinfo($nameLower, PATHINFO_FILENAME)))) {
                self::markFulfilled((int) $req['id'], $documentId);
                return;
            }
        }

        if (count($pending) === 1) {
            self::markFulfilled((int) $pending[0]['id'], $documentId);
        }
    }

    public static function markFulfilled(int $requestId, int $documentId): void
    {
        Database::query(
            "UPDATE case_document_requests SET status = 'uploaded', document_id = ?, fulfilled_at = NOW(), updated_at = NOW() WHERE id = ?",
            [$documentId, $requestId]
        );
    }

    public static function pendingCountForCase(int $caseId): int
    {
        self::ensureSchema();
        if (!Database::tableExists('case_document_requests')) {
            return 0;
        }

        return (int) (Database::fetch(
            "SELECT COUNT(*) AS c FROM case_document_requests WHERE case_id = ? AND status = 'pending' AND required = 1",
            [$caseId]
        )['c'] ?? 0);
    }
}
