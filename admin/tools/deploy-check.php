<?php

/**
 * Production readiness check.
 * Run: php admin/tools/deploy-check.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

$ok = [];
$warnings = [];
$errors = [];

if (!empty($config['debug'])) {
    $warnings[] = 'Debug mode is ON. Set APP_DEBUG=0 on production (or use a non-localhost domain).';
} else {
    $ok[] = 'Debug mode is off.';
}

if (ini_get('display_errors') === '1' || ini_get('display_errors') === 'On') {
    $warnings[] = 'display_errors is enabled.';
} else {
    $ok[] = 'display_errors is disabled.';
}

try {
    Database::getInstance()->query('SELECT 1');
    $ok[] = 'Database connection OK.';
} catch (Throwable $e) {
    $errors[] = 'Database connection failed: ' . $e->getMessage();
}

$writablePaths = [
    'admin/uploads'           => __DIR__ . '/../uploads',
    'admin/storage/logs'      => __DIR__ . '/../storage/logs',
    'admin/storage/backups'   => __DIR__ . '/../storage/backups',
];

foreach ($writablePaths as $label => $path) {
    if (!is_dir($path)) {
        $errors[] = $label . ' directory is missing.';
        continue;
    }

    if (!is_writable($path)) {
        $errors[] = $label . ' is not writable by the web server.';
        continue;
    }

    $ok[] = $label . ' is writable.';
}

if (TenantService::isEnabled()) {
    if (!Database::tableExists('companies')) {
        $errors[] = 'companies table missing. Run: php admin/sql/migrate_multi_company.php';
    } else {
        $ok[] = 'Multi-company tables OK.';
    }
}

if (!Database::tableExists('chatbot_conversations')) {
    $warnings[] = 'chatbot_conversations table missing. Run: php admin/sql/migrate_assistant_chats.php';
} else {
    $ok[] = 'Assistant chat library table OK.';
}

$coreFiles = [
    'admin/api/assistant.php',
    'admin/api/chatbot.php',
    'admin/actions/company-action.php',
    'client/api/chatbot.php',
];

foreach ($coreFiles as $relative) {
    $full = dirname(__DIR__, 2) . '/' . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    if (!is_file($full) || filesize($full) < 10) {
        $errors[] = $relative . ' is missing or empty.';
    }
}

if (class_exists('CompanyService') && TenantService::isEnabled()) {
    $ok[] = 'Loaded ' . CompanyService::countAll() . ' company workspace(s).';
}

echo "Case Notary — deploy check\n";
echo str_repeat('=', 28) . "\n\n";

if ($ok !== []) {
    echo "OK\n";
    foreach ($ok as $line) {
        echo "  [OK] {$line}\n";
    }
    echo "\n";
}

if ($warnings !== []) {
    echo "Warnings\n";
    foreach ($warnings as $line) {
        echo "  [WARN] {$line}\n";
    }
    echo "\n";
}

if ($errors !== []) {
    echo "Errors\n";
    foreach ($errors as $line) {
        echo "  [FAIL] {$line}\n";
    }
    echo "\nDeploy check failed.\n";
    exit(1);
}

echo "Deploy check passed";
echo $warnings !== [] ? ' with warnings.' : '.';
echo "\n";
exit(0);
