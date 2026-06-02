<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    echo 'Invalid request.';
    exit;
}

$config   = require __DIR__ . '/../config/config.php';
$csrfName = $config['security']['csrf_token_name'];
if (!CSRF::validate($_POST[$csrfName] ?? null)) {
    http_response_code(403);
    echo 'Invalid request.';
    exit;
}

$caseId = (int) ($_POST['case_id'] ?? 0);
if ($caseId <= 0) {
    http_response_code(400);
    echo 'Invalid case.';
    exit;
}

try {
    $sections = ClientLetterService::sectionsFromPost($_POST);
    $embed    = !empty($_POST['embed']);
    echo ClientLetterService::renderHtml($caseId, $sections, $embed);
} catch (Throwable $e) {
    http_response_code(500);
    echo '<p>' . e($e->getMessage()) . '</p>';
}
exit;
