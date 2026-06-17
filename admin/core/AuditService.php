<?php

declare(strict_types=1);

class AuditService
{
    public static function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        array $details = [],
        ?int $userId = null
    ): void {
        try {
            Database::insert(
                'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address, user_agent, details, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    $userId ?? Auth::id(),
                    $action,
                    $entityType,
                    $entityId,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                    json_encode($details, JSON_UNESCAPED_UNICODE),
                ]
            );
        } catch (Throwable $e) {
            // Audit logging is best effort.
        }
    }
}
