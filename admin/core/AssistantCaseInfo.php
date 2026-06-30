<?php

declare(strict_types=1);

/**
 * Read-only case intelligence for the assistant (summary, checklist, documents, billing).
 */
class AssistantCaseInfo
{
    /** @return array{content: string, type: string}|null */
    public static function tryAnswer(string $message): ?array
    {
        if (!self::looksLikeQuery($message)) {
            return null;
        }

        if (AssistantRouter::shouldUploadToCase($message, true)
            || AssistantRouter::matchActionTopic($message) !== null) {
            return null;
        }

        $case = assistantFindCaseByReferenceFromMessage($message);
        if ($case === null) {
            $clientName = assistantExtractClientNameFromActionMessage($message);
            if ($clientName !== '') {
                $clientId = assistantResolveClientId($clientName);
                if ($clientId !== null) {
                    $case = Database::fetch(
                        'SELECT cs.*, cl.first_name, cl.last_name, cl.company_name
                         FROM cases cs
                         JOIN clients cl ON cl.id = cs.client_id
                         WHERE cs.client_id = ?
                         ORDER BY cs.updated_at DESC
                         LIMIT 1',
                        [$clientId]
                    ) ?: null;
                }
            }
        }

        if ($case === null) {
            return [
                'content' => 'Which **case** should I look at? Use a case number (e.g. **CASE-2026-0006**) or a client name.',
                'type' => 'text',
            ];
        }

        $lower = strtolower(trim($message));

        if (preg_match('/\b(what.?s missing|whats missing|missing on|checklist|incomplete|outstanding|to[- ]?do)\b/', $lower)) {
            return ['content' => self::formatMissing($case), 'type' => 'text'];
        }

        if (preg_match('/\b(documents?|files?|uploads?)\b.*\b(on|for|in)\b/i', $message)
            || preg_match('/\blist\b.*\b(documents?|files?)\b/i', $message)) {
            return ['content' => self::formatDocuments($case), 'type' => 'text'];
        }

        if (preg_match('/\b(billing|invoices?|payments?|balance|owed)\b/i', $message)) {
            return ['content' => self::formatBilling($case), 'type' => 'text'];
        }

        return ['content' => self::formatSummary($case), 'type' => 'text'];
    }

    public static function looksLikeQuery(string $message): bool
    {
        $message = assistantNormalizeUserMessage($message);
        if ($message === '') {
            return false;
        }

        if (preg_match(
            '/\b(summarize|summarise|summary|overview|status of|what.?s missing|whats missing|missing on|checklist for|'
            . 'documents on|files on|list documents|billing for|invoices for)\b/i',
            $message
        )) {
            return true;
        }

        if (assistantExtractCaseReferenceFromMessage($message) !== ''
            && preg_match('/\b(case|matter)\b/i', $message)
            && !preg_match('/\b(delete|remove|drop|cancel)\b/i', $message)) {
            return true;
        }

        return false;
    }

    /** @param array<string, mixed> $case */
    private static function formatSummary(array $case): string
    {
        $caseId = (int) ($case['id'] ?? 0);
        $status = ucwords(str_replace('_', ' ', (string) ($case['status'] ?? '')));
        $docCount = count(CaseService::getDocuments($caseId));
        $invoices = CaseService::getInvoices($caseId);
        $pending = 0;
        foreach ($invoices as $inv) {
            if (assistantInvoiceIsOutstanding($inv)) {
                $pending++;
            }
        }

        $lines = [
            '**Case summary — ' . ($case['case_number'] ?? 'Case') . '**',
            '',
            '• **Title:** ' . ($case['title'] ?? '—'),
            '• **Client:** ' . clientFullName($case),
            '• **Status:** ' . $status,
            '• **Service:** ' . ($case['service_type'] ?? '—'),
            '• **Documents:** ' . $docCount,
            '• **Outstanding invoices:** ' . $pending,
        ];

        if (trim((string) ($case['description'] ?? '')) !== '') {
            $lines[] = '• **Description:** ' . mb_strimwidth((string) $case['description'], 0, 200, '…');
        }

        $lines[] = '';
        $lines[] = 'Ask **what\'s missing on ' . ($case['case_number'] ?? 'this case') . '** for checklist items.';
        $lines[] = assistantAdminLink('pages/case-view.php?id=' . $caseId, 'Open case');

        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $case */
    private static function formatMissing(array $case): string
    {
        $caseId = (int) ($case['id'] ?? 0);
        $missing = [];
        $ok = [];

        $checklist = CaseChecklistService::getChecklist($caseId, (string) ($case['service_type'] ?? ''));
        $requiredMissing = CaseChecklistService::missingRequiredLabels($checklist);
        foreach ($requiredMissing as $label) {
            $missing[] = 'Checklist: **' . $label . '**';
        }
        if ($requiredMissing === [] && $checklist !== []) {
            $ok[] = 'Required checklist complete';
        }

        if (empty($case['assigned_admin_id'])) {
            $missing[] = 'No **assigned staff**';
        } else {
            $ok[] = 'Staff assigned';
        }

        if (count(CaseService::getDocuments($caseId)) === 0) {
            $missing[] = 'No **documents** uploaded';
        } else {
            $ok[] = 'Documents on file';
        }

        $deadlines = CaseDeadlineService::listForCase($caseId);
        $openDeadlines = array_filter($deadlines, static fn (array $d): bool => ($d['status'] ?? '') !== 'completed');
        if ($openDeadlines !== []) {
            $missing[] = count($openDeadlines) . ' open **deadline(s)**';
        }

        $lines = [
            '**Missing / to-do — ' . ($case['case_number'] ?? 'Case') . '** (' . clientFullName($case) . ')',
            '',
        ];

        if ($missing === []) {
            $lines[] = '_Nothing critical flagged — case looks in good shape._';
        } else {
            foreach ($missing as $item) {
                $lines[] = '• ' . $item;
            }
        }

        if ($ok !== []) {
            $lines[] = '';
            $lines[] = '**OK:** ' . implode(' · ', $ok);
        }

        $lines[] = '';
        $lines[] = assistantAdminLink('pages/case-view.php?id=' . $caseId, 'Open case workspace');

        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $case */
    private static function formatDocuments(array $case): string
    {
        $caseId = (int) ($case['id'] ?? 0);
        $docs = CaseService::getDocuments($caseId);

        $lines = [
            '**Documents — ' . ($case['case_number'] ?? 'Case') . '**',
            '',
        ];

        if ($docs === []) {
            $lines[] = '_No documents uploaded yet._';
        } else {
            foreach (array_slice($docs, 0, 15) as $doc) {
                $lines[] = '• **' . ($doc['original_name'] ?? 'File') . '**'
                    . ' — ' . formatDateTime((string) ($doc['created_at'] ?? ''));
            }
            if (count($docs) > 15) {
                $lines[] = '_…and ' . (count($docs) - 15) . ' more._';
            }
        }

        $lines[] = '';
        $lines[] = assistantAdminLink('pages/case-view.php?id=' . $caseId . '#documents', 'Open documents');

        return implode("\n", $lines);
    }

    /** @param array<string, mixed> $case */
    private static function formatBilling(array $case): string
    {
        $caseId = (int) ($case['id'] ?? 0);
        $billing = CaseService::getCaseBilling($case);
        $grandTotal = (float) ($billing['totals']['grand_total'] ?? 0);
        $invoices = CaseService::getInvoices($caseId);

        $lines = [
            '**Billing — ' . ($case['case_number'] ?? 'Case') . '**',
            '',
            '• **Case fee total:** ' . formatCurrency($grandTotal),
        ];

        if ($invoices !== []) {
            $lines[] = '';
            $lines[] = '**Invoices:**';
            foreach (array_slice($invoices, 0, 8) as $inv) {
                $remaining = CaseService::getInvoiceRemainingBalance($inv);
                $lines[] = '• **' . ($inv['invoice_number'] ?? 'Invoice') . '** — '
                    . formatCurrency((float) ($inv['total'] ?? 0))
                    . ' (*' . ucwords(str_replace('_', ' ', invoiceStatusValue($inv))) . '*, '
                    . formatCurrency($remaining) . ' remaining)';
            }
        }

        $lines[] = '';
        $lines[] = assistantAdminLink('pages/case-view.php?id=' . $caseId . '#invoice-payments', 'Open billing');

        return implode("\n", $lines);
    }
}
