<?php
/**
 * Converts legacy receipt numbers (RCP-2026-0001) to alphanumeric format (RCP-2026-4X7R2).
 * Run: php admin/sql/migrate_receipt_number_format.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

echo "Receipt number format migration...\n\n";

$result = CaseService::migrateLegacyReceiptNumbers();

foreach ($result['details'] as $line) {
    echo "[OK] {$line}\n";
}

echo "\nUpdated: {$result['updated']}\n";
echo "Skipped (already new format): {$result['skipped']}\n";
echo "\nMigration complete.\n";
