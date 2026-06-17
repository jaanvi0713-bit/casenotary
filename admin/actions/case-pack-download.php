<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::guardAction();

$caseId = (int) ($_GET['case_id'] ?? 0);
if ($caseId <= 0) {
    http_response_code(400);
    exit('Invalid case.');
}

$case = CaseService::getCaseById($caseId);
if (!$case) {
    http_response_code(404);
    exit('Case not found.');
}

$tmpZip = tempnam(sys_get_temp_dir(), 'casepack_');
if ($tmpZip === false) {
    http_response_code(500);
    exit('Could not create temporary archive.');
}

$zipPath = $tmpZip . '.zip';
@rename($tmpZip, $zipPath);

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    exit('Could not create ZIP archive.');
}

$addIfFile = static function (string $relativePath, string $nameInZip) use ($zip): void {
    $full = CaseService::documentPath($relativePath);
    if (is_file($full)) {
        $zip->addFile($full, $nameInZip);
    }
};

foreach (CaseService::getDocuments($caseId) as $doc) {
    $relative = (string) ($doc['file_path'] ?? '');
    if ($relative === '') {
        continue;
    }
    $name = 'documents/' . basename((string) ($doc['original_name'] ?? $doc['file_name'] ?? $relative));
    $addIfFile($relative, $name);
}

foreach (CaseService::getInvoices($caseId) as $inv) {
    if (!empty($inv['pdf_path'])) {
        $addIfFile((string) $inv['pdf_path'], 'invoices/' . basename((string) $inv['pdf_path']));
    }
}

foreach (CaseService::getQuotations($caseId) as $quo) {
    if (!empty($quo['pdf_path'])) {
        $addIfFile((string) $quo['pdf_path'], 'quotations/' . basename((string) $quo['pdf_path']));
    }
}

foreach (CaseService::getProposals($caseId) as $pro) {
    if (!empty($pro['pdf_path'])) {
        $addIfFile((string) $pro['pdf_path'], 'proposals/' . basename((string) $pro['pdf_path']));
    }
}

foreach (CaseService::getReceipts($caseId) as $receipt) {
    $path = 'cases/' . $caseId . '/generated/receipt_' . (int) ($receipt['id'] ?? 0) . '.html';
    $addIfFile($path, 'receipts/' . basename($path));
}

$letterPath = CaseService::getClientLetterRelativePath($caseId);
if ($letterPath) {
    $addIfFile($letterPath, 'letters/' . basename($letterPath));
}

$zip->close();

AuditService::log(
    'case_pack_downloaded',
    'case',
    $caseId,
    ['case_number' => $case['case_number'] ?? null],
    Auth::id()
);

$downloadName = ($case['case_number'] ?? 'case-' . $caseId) . '_pack.zip';
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . basename($downloadName) . '"');
header('Content-Length: ' . (string) filesize($zipPath));
readfile($zipPath);
@unlink($zipPath);
exit;
