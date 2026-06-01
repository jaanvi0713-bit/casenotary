<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAdmin();

$backup = SettingsService::exportBackup();
$json   = json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
$filename = 'notary-settings-backup-' . date('Y-m-d-His') . '.json';

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen((string) $json));
echo $json;
exit;
