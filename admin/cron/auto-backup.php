<?php
/**
 * Automatic backup cron script.
 * Schedule via cPanel or server cron to run daily:
 *   0 2 * * * php /path/to/admin/cron/auto-backup.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

if (Database::tableExists('companies')) {
    $companies = Database::fetchAll(
        "SELECT id FROM companies WHERE status = 'active' OR status IS NULL"
    );
} else {
    $companies = [['id' => 1]];
}

foreach ($companies as $company) {
    $companyId = (int) ($company['id'] ?? 0);
    if ($companyId <= 0) {
        continue;
    }

    $settings = SettingsService::forCompany($companyId);
    $freq     = (string) ($settings['backup_frequency'] ?? 'never');
    $lastAt   = $settings['last_backup_at'] ?? null;

    if ($freq === 'never') {
        continue;
    }

    $isDue = false;
    if ($lastAt === null || trim((string) $lastAt) === '') {
        $isDue = true;
    } elseif ($freq === 'weekly' && strtotime((string) $lastAt) < strtotime('-7 days')) {
        $isDue = true;
    } elseif ($freq === 'monthly' && strtotime((string) $lastAt) < strtotime('-30 days')) {
        $isDue = true;
    }

    if (!$isDue) {
        continue;
    }

    try {
        $trigger = $freq === 'monthly' ? 'monthly' : 'weekly';
        $result  = BackupService::create($companyId, $trigger);
        SettingsService::saveSetting('last_backup_at', date('Y-m-d H:i:s'), $companyId);

        echo "[OK] Backup written for company {$companyId}: {$result['file']}";
        echo ' | emailed: ' . $result['emailed'] . "\n";
    } catch (Throwable $e) {
        echo "[ERR] Company {$companyId}: " . $e->getMessage() . "\n";
    }
}

echo "[DONE]\n";
