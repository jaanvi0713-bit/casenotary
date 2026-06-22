<?php

declare(strict_types=1);

class CaseDeadlineService
{
    public const TYPES = ['filing', 'statutory', 'limitation', 'appointment', 'other'];
    public const STATUSES = ['pending', 'completed', 'overdue'];

    public static function ensureSchema(): void
    {
        static $done = false;
        if ($done) {
            return;
        }
        $done = true;

        if (Database::tableExists('case_deadlines')) {
            return;
        }

        $migration = __DIR__ . '/../sql/migrate_case_features.php';
        if (is_file($migration)) {
            try {
                require $migration;
            } catch (Throwable $e) {
                error_log('[CaseDeadlineService] Schema migration failed: ' . $e->getMessage());
            }
        }
    }

  /** @return list<array<string, mixed>> */
    public static function listForCase(int $caseId): array
    {
        self::ensureSchema();
        if (!Database::tableExists('case_deadlines')) {
            return [];
        }

        self::syncOverdueStatuses($caseId);

        return Database::fetchAll(
            'SELECT * FROM case_deadlines WHERE case_id = ? ORDER BY due_date ASC, id ASC',
            [$caseId]
        );
    }

    public static function add(int $caseId, array $data, ?int $userId = null): int
    {
        self::ensureSchema();
        $label = trim((string) ($data['label'] ?? ''));
        $dueDate = trim((string) ($data['due_date'] ?? ''));
        if ($label === '' || $dueDate === '') {
            throw new RuntimeException('Deadline label and due date are required.');
        }

        $type = strtolower(trim((string) ($data['deadline_type'] ?? 'other')));
        if (!in_array($type, self::TYPES, true)) {
            $type = 'other';
        }

        $notes = trim((string) ($data['notes'] ?? '')) ?: null;
        $status = strtotime($dueDate) < strtotime('today') ? 'overdue' : 'pending';

        $id = insertTableRow('case_deadlines', [
            'case_id'        => $caseId,
            'label'          => $label,
            'deadline_type'  => $type,
            'due_date'       => $dueDate,
            'status'         => $status,
            'notes'          => $notes,
            'created_by'     => $userId,
        ]);

        CaseService::logCaseEvent($caseId, 'deadline_added', [
            'label' => $label,
            'due_date' => $dueDate,
            'deadline_type' => $type,
        ], $userId);

        return $id;
    }

    public static function complete(int $deadlineId, int $caseId, ?int $userId = null): void
    {
        self::ensureSchema();
        $row = Database::fetch('SELECT * FROM case_deadlines WHERE id = ? AND case_id = ?', [$deadlineId, $caseId]);
        if (!$row) {
            throw new RuntimeException('Deadline not found.');
        }

        Database::query(
            'UPDATE case_deadlines SET status = ?, completed_at = NOW(), updated_at = NOW() WHERE id = ?',
            ['completed', $deadlineId]
        );

        CaseService::logCaseEvent($caseId, 'deadline_completed', [
            'label' => $row['label'],
        ], $userId);
    }

    public static function delete(int $deadlineId, int $caseId): void
    {
        self::ensureSchema();
        Database::query('DELETE FROM case_deadlines WHERE id = ? AND case_id = ?', [$deadlineId, $caseId]);
    }

    public static function syncOverdueStatuses(?int $caseId = null): void
    {
        if (!Database::tableExists('case_deadlines')) {
            return;
        }

        $sql = "UPDATE case_deadlines SET status = 'overdue', updated_at = NOW()
                WHERE status = 'pending' AND due_date < CURDATE()";
        $params = [];
        if ($caseId !== null) {
            $sql .= ' AND case_id = ?';
            $params[] = $caseId;
        }
        Database::query($sql, $params);
    }

    /** @return list<array<string, mixed>> */
    public static function upcomingAlerts(int $limit = 10): array
    {
        self::ensureSchema();
        self::syncOverdueStatuses();

        if (!Database::tableExists('case_deadlines')) {
            return [];
        }

        $where = ["cd.status IN ('pending', 'overdue')", 'cs.status NOT IN (\'completed\', \'closed\')'];
        $params = [];
        if (TenantService::isEnabled()) {
            $where[] = 'cs.company_id = ?';
            $params[] = TenantService::id();
        }
        $params[] = $limit;

        return Database::fetchAll(
            "SELECT cd.*, cs.case_number, cs.title AS case_title
             FROM case_deadlines cd
             INNER JOIN cases cs ON cs.id = cd.case_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY cd.due_date ASC
             LIMIT ?",
            $params
        );
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            'filing' => 'Filing',
            'statutory' => 'Statutory',
            'limitation' => 'Limitation',
            'appointment' => 'Appointment',
            default => 'Other',
        };
    }
}
