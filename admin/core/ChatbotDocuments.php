<?php

declare(strict_types=1);

function chatbotReplyForDocumentSearch(string $message): ?string
{
    $normalized = strtolower(trim($message));

    if (!preg_match(
        '/\b(find|search|look for|which case|cases with|documents? with|files? with|uploaded|passport|pdf|inside|containing|text in)\b/',
        $normalized
    ) || !preg_match('/\b(document|documents|doc|docs|file|files|upload|passport|pdf|content)\b/', $normalized)) {
        return null;
    }

    $term = chatbotExtractDocumentSearchTerm($message);
    if ($term === '') {
        return 'What should I search for? Example: **find documents with passport** or **search PDFs for affidavit**.';
    }

    ChatbotDocumentText::ensureSchema();
    ChatbotDocumentText::indexRecentSearchableDocuments(25);

    $like = '%' . $term . '%';
    $extSql = documentExtensionSql('d');
    $searchClauses = [
        'LOWER(d.original_name) LIKE LOWER(?)',
        'LOWER(d.file_name) LIKE LOWER(?)',
        'LOWER(COALESCE(d.description, "")) LIKE LOWER(?)',
        'LOWER(cs.case_number) LIKE LOWER(?)',
        'LOWER(cs.title) LIKE LOWER(?)',
    ];
    $params = [$like, $like, $like, $like, $like];

    if (Database::columnExists('documents', 'extracted_text')) {
        $searchClauses[] = 'LOWER(COALESCE(d.extracted_text, "")) LIKE LOWER(?)';
        $params[] = $like;
    }

    $where = ['(' . implode("\n            OR ", $searchClauses) . ')'];

    chatbotAppendCaseScope($where, $params, 'cs', 'cl');

    $rows = Database::fetchAll(
        "SELECT d.original_name, d.file_name, {$extSql} AS file_type, d.created_at, d.description,
                cs.id AS case_id, cs.case_number, cs.title,
                cl.first_name, cl.last_name, cl.company_name
         FROM documents d
         JOIN cases cs ON cs.id = d.case_id
         JOIN clients cl ON cl.id = cs.client_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY d.created_at DESC
         LIMIT 15",
        $params
    );

    if ($rows === []) {
        return 'No documents matching **“' . $term . '”**. Try another keyword (e.g. passport, invoice, id).';
    }

    $lines = ['**Documents matching “' . $term . '”** (' . count($rows) . '):', ''];

    foreach ($rows as $row) {
        $name = (string) ($row['original_name'] ?? $row['file_name'] ?? 'Document');
        $caseNo = (string) ($row['case_number'] ?? '');
        $client = clientFullName($row);
        $when = !empty($row['created_at']) ? formatDate($row['created_at']) : '';
        $type = strtolower((string) ($row['file_type'] ?? ''));
        $inPdf = $type === 'pdf' ? ' (PDF)' : '';
        $lines[] = '• **' . $name . '**' . $inPdf . ' — case **' . $caseNo . '** (' . $client . ')' . ($when !== '' ? " — {$when}" : '');
        if (!empty($row['case_id'])) {
            $lines[] = '  ' . chatbotAdminLink('pages/case-view.php?id=' . (int) $row['case_id'], 'Open case');
        }
    }

    $_SESSION['chatbot_last_topic'] = 'documents';

    return implode("\n", $lines);
}

function chatbotExtractDocumentSearchTerm(string $message): string
{
    $normalized = strtolower(trim($message));

    if (preg_match('/\b(?:with|named|called|containing|matching)\s+["\']?([a-z0-9][a-z0-9\s_-]{1,40})/i', $message, $matches)) {
        return trim($matches[1]);
    }

    if (preg_match('/\b(passport|affidavit|poa|power of attorney|id card|driving licence|license|birth certificate|deed|contract|invoice|receipt)\b/i', $message, $matches)) {
        return strtolower($matches[1]);
    }

    $term = chatbotNormalizeLookupTerm($message);
    $term = preg_replace('/\b(find|search|documents?|docs?|files?|uploads?|cases?|with|for|show|list)\b/', ' ', $term);
    $term = trim(preg_replace('/\s+/', ' ', (string) $term));

    return mb_strimwidth($term, 0, 50, '');
}
