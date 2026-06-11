<?php
require_once __DIR__ . '/../core/bootstrap.php';

if (!Auth::check()) {
    http_response_code(403);
    exit('Forbidden');
}

$id        = (int) ($_GET['id'] ?? 0);
$letterId  = (int) ($_GET['letter_id'] ?? 0);
$path      = $_GET['path'] ?? '';
$downloadName = null;

if ($letterId > 0) {
    $letter = ClientLetterService::getById($letterId);
    if (!$letter) {
        http_response_code(404);
        exit('Not found');
    }

    if (Auth::isClient()) {
        $clientId = Auth::clientId();
        if (!$clientId
            || (int) $letter['client_id'] !== $clientId
            || empty($letter['published_to_portal'])
            || empty($letter['saved_to_record'])) {
            http_response_code(403);
            exit('Forbidden');
        }
    } elseif (!Auth::isAdmin() || !Auth::can(RoleAccess::PERMISSION_CASES)) {
        http_response_code(403);
        exit('Forbidden');
    }

    $rel = ClientLetterService::getDownloadPath($letter);
    if (!$rel) {
        http_response_code(404);
        exit('File not found');
    }

    $fullPath = CaseService::documentPath($rel);
    $downloadName = ($letter['title'] ?? 'client-letter') . (str_ends_with($rel, '.pdf') ? '.pdf' : '.html');
} elseif ($path !== '') {
    $relative = urldecode($path);
    if (str_contains($relative, '..')) {
        http_response_code(400);
        exit('Invalid path');
    }
    $fullPath = CaseService::documentPath($relative);

    if (preg_match('#^cases/(\d+)/generated/invoice_(\d+)\.html$#', $relative, $invoiceMatch)) {
        try {
            CaseService::regenerateInvoiceHtml((int) $invoiceMatch[1], (int) $invoiceMatch[2]);
            $fullPath = CaseService::documentPath($relative);
        } catch (Throwable $e) {
            // Fall back to the existing file if regeneration fails.
        }
    } elseif (preg_match('#^cases/(\d+)/generated/quotation_(\d+)\.html$#', $relative, $quotationMatch)) {
        try {
            CaseService::regenerateQuotationHtml((int) $quotationMatch[1], (int) $quotationMatch[2]);
            $fullPath = CaseService::documentPath($relative);
        } catch (Throwable $e) {
            // Fall back to the existing file if regeneration fails.
        }
    }

    if (!Auth::isAdmin() || !Auth::can(RoleAccess::PERMISSION_CASES)) {
        if (!Auth::isClient()) {
            http_response_code(403);
            exit('Forbidden');
        }
        $clientId = Auth::clientId();
        if (!$clientId || !preg_match('#^cases/(\d+)/#', $relative, $m)) {
            http_response_code(403);
            exit('Forbidden');
        }
        $case = CaseService::getCaseForClient((int) $m[1], $clientId);
        if (!$case) {
            http_response_code(403);
            exit('Forbidden');
        }
        if (str_contains($relative, '/letters/') && ClientLetterService::lettersTableExists()) {
            $published = Database::fetch(
                'SELECT id FROM case_client_letters
                 WHERE case_id = ? AND client_id = ? AND published_to_portal = 1
                   AND (pdf_path = ? OR html_path = ?) LIMIT 1',
                [(int) $m[1], $clientId, $relative, $relative]
            );
            if (!$published) {
                http_response_code(403);
                exit('Forbidden');
            }
        }
    }
} elseif ($id > 0) {
    $doc = Database::fetch('SELECT * FROM documents WHERE id = ?', [$id]);
    if (!$doc) {
        http_response_code(404);
        exit('Not found');
    }

    if (Auth::isClient()) {
        $clientId = Auth::clientId();
        $case = CaseService::getCaseForClient((int) $doc['case_id'], $clientId);
        if (!$case) {
            http_response_code(403);
            exit('Forbidden');
        }
    } elseif (!Auth::isAdmin() || !Auth::can(RoleAccess::PERMISSION_CASES)) {
        http_response_code(403);
        exit('Forbidden');
    }

    $fullPath = CaseService::documentPath($doc['file_path']);
    $downloadName = $doc['original_name'] ?? $doc['file_name'];
} else {
    http_response_code(400);
    exit('Bad request');
}

if (!is_file($fullPath)) {
    http_response_code(404);
    exit('File not found');
}

$mime = mime_content_type($fullPath) ?: 'application/octet-stream';
$name = $downloadName ?? basename($fullPath);

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($fullPath));
header('Content-Disposition: inline; filename="' . basename($name) . '"');
readfile($fullPath);
exit;
