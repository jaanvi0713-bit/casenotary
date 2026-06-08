<?php

declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

$errors = [];
$ok = [];

function check(string $label, callable $fn): void
{
    global $errors, $ok;
    try {
        $fn();
        $ok[] = $label;
        echo "[OK] {$label}\n";
    } catch (Throwable $e) {
        $errors[] = "{$label}: " . $e->getMessage();
        echo "[FAIL] {$label}: " . $e->getMessage() . "\n";
    }
}

echo "=== Schema columns ===\n";
foreach (['quotations', 'proposals', 'invoices', 'payments', 'receipts', 'cases'] as $table) {
    echo "{$table}: " . implode(', ', array_column(Database::fetchAll("SHOW COLUMNS FROM {$table}"), 'Field')) . "\n";
}

$case = Database::fetch('SELECT id FROM cases ORDER BY id DESC LIMIT 1');
if (!$case) {
    echo "No cases found — skipping document generation tests.\n";
    exit(empty($errors) ? 0 : 1);
}

$caseId = (int) $case['id'];
echo "\n=== Tests on case #{$caseId} ===\n";

check('generateQuotation', static fn() => CaseService::generateQuotation($caseId, ['title' => 'Test quote']));
check('generateProposal', static fn() => CaseService::generateProposal($caseId, ['title' => 'Test proposal']));
check('generateInvoice', static fn() => CaseService::generateInvoice($caseId, ['notes' => 'Test invoice']));

$invoice = Database::fetch('SELECT id FROM invoices WHERE case_id = ? ORDER BY id DESC LIMIT 1', [$caseId]);
if ($invoice) {
    check('recordPayment', static function () use ($invoice) {
        $result = CaseService::recordPayment((int) $invoice['id'], [
            'amount' => 1.00,
            'payment_method' => 'bank_transfer',
            'notes' => 'Automated test payment',
        ], 1);
        if (empty($result['success'])) {
            throw new RuntimeException($result['message'] ?? 'Payment failed');
        }
    });
}

check('getCaseById', static fn() => CaseService::getCaseById($caseId) ?: throw new RuntimeException('Case not found'));
check('getReceipts', static fn() => CaseService::getReceipts($caseId));

echo "\n=== Summary ===\n";
echo count($ok) . ' passed, ' . count($errors) . " failed\n";
exit($errors === [] ? 0 : 1);
