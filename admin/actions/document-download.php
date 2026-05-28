<?php
require_once __DIR__ . '/../core/bootstrap.php';

if (!Auth::check()) {
    http_response_code(403);
    exit('Forbidden');
}

$id   = (int) ($_GET['id'] ?? 0);
$path = $_GET['path'] ?? '';

if ($path !== '') {
    $relative = urldecode($path);
    if (str_contains($relative, '..')) {
        http_response_code(400);
        exit('Invalid path');
    }
    $fullPath = CaseService::documentPath($relative);

    if (!Auth::isAdmin()) {
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
    } elseif (!Auth::isAdmin()) {
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
