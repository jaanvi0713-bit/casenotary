<?php

declare(strict_types=1);

class CaseChecklistService
{
    public static function ensureSchema(): void
    {
        if (Database::columnExists('cases', 'checklist_json')) {
            return;
        }

        try {
            Database::query('ALTER TABLE cases ADD COLUMN checklist_json JSON DEFAULT NULL AFTER services');
        } catch (Throwable $e) {
            // Optional migration fallback.
        }
    }

    public static function ensureCaseChecklist(int $caseId, string $serviceType, bool $force = false): void
    {
        self::ensureSchema();
        if (!Database::columnExists('cases', 'checklist_json')) {
            return;
        }

        $case = Database::fetch('SELECT checklist_json FROM cases WHERE id = ?', [$caseId]);
        if (!$case) {
            return;
        }

        $current = self::decodeChecklist((string) ($case['checklist_json'] ?? ''));
        if (!$force && $current !== []) {
            return;
        }

        $items = self::defaultChecklistForService($serviceType);
        Database::query(
            'UPDATE cases SET checklist_json = ?, updated_at = NOW() WHERE id = ?',
            [json_encode($items, JSON_UNESCAPED_UNICODE), $caseId]
        );
    }

    public static function getChecklist(int $caseId, string $serviceType = ''): array
    {
        self::ensureSchema();
        if (!Database::columnExists('cases', 'checklist_json')) {
            return [];
        }

        $row = Database::fetch('SELECT checklist_json, service_type FROM cases WHERE id = ?', [$caseId]);
        if (!$row) {
            return [];
        }

        $items = self::decodeChecklist((string) ($row['checklist_json'] ?? ''));
        if ($items === []) {
            $items = self::defaultChecklistForService($serviceType !== '' ? $serviceType : (string) ($row['service_type'] ?? ''));
            Database::query(
                'UPDATE cases SET checklist_json = ?, updated_at = NOW() WHERE id = ?',
                [json_encode($items, JSON_UNESCAPED_UNICODE), $caseId]
            );
        }

        return $items;
    }

    public static function updateItem(int $caseId, string $key, bool $completed): void
    {
        $items = self::getChecklist($caseId);
        if ($items === []) {
            return;
        }

        foreach ($items as &$item) {
            if (($item['key'] ?? '') !== $key) {
                continue;
            }
            $item['completed'] = $completed;
            $item['completed_at'] = $completed ? date('Y-m-d H:i:s') : null;
        }
        unset($item);

        if (Database::columnExists('cases', 'checklist_json')) {
            Database::query(
                'UPDATE cases SET checklist_json = ?, updated_at = NOW() WHERE id = ?',
                [json_encode($items, JSON_UNESCAPED_UNICODE), $caseId]
            );
        }
    }

    public static function progressPercent(array $items): int
    {
        if ($items === []) {
            return 100;
        }
        $total = count($items);
        $done = 0;
        foreach ($items as $item) {
            if (!empty($item['completed'])) {
                $done++;
            }
        }

        return (int) round(($done / $total) * 100);
    }

    public static function missingRequiredLabels(array $items): array
    {
        $missing = [];
        foreach ($items as $item) {
            if (!empty($item['required']) && empty($item['completed'])) {
                $missing[] = (string) ($item['label'] ?? '');
            }
        }
        return array_values(array_filter($missing));
    }

    private static function decodeChecklist(string $json): array
    {
        if (trim($json) === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function previewChecklistForService(string $serviceType): array
    {
        return self::defaultChecklistForService($serviceType);
    }

    private static function defaultChecklistForService(string $serviceType): array
    {
        $base = [
            ['key' => 'client_id', 'label' => 'Client identity verified', 'required' => true, 'completed' => false],
            ['key' => 'source_docs', 'label' => 'Source documents received', 'required' => true, 'completed' => false],
            ['key' => 'invoice_sent', 'label' => 'Invoice sent to client', 'required' => true, 'completed' => false],
            ['key' => 'payment_received', 'label' => 'Payment received', 'required' => false, 'completed' => false],
            ['key' => 'final_delivery', 'label' => 'Final documents delivered', 'required' => true, 'completed' => false],
        ];

        $normalized = strtolower($serviceType);
        if (str_contains($normalized, 'apostille')) {
            $base[] = ['key' => 'apostille_submission', 'label' => 'Apostille submission prepared', 'required' => true, 'completed' => false];
        }
        if (str_contains($normalized, 'power of attorney') || str_contains($normalized, 'poa')) {
            $base[] = ['key' => 'witnessing', 'label' => 'Witness/signing session completed', 'required' => true, 'completed' => false];
        }

        return $base;
    }
}
