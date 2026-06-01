<?php

declare(strict_types=1);

class CaseService
{
    public const STATUSES = [
        'pending',
        'in_progress',
        'waiting_for_client',
        'completed',
        'closed',
    ];

    public const STATUS_TRANSITIONS = [
        'pending'            => ['in_progress', 'waiting_for_client', 'closed'],
        'in_progress'        => ['waiting_for_client', 'completed', 'closed'],
        'waiting_for_client' => ['in_progress', 'completed', 'closed'],
        'completed'          => ['closed', 'in_progress'],
        'closed'             => ['pending'],
    ];

    public static function isValidStatus(string $status): bool
    {
        return in_array($status, self::STATUSES, true);
    }

    public static function canTransitionStatus(string $from, string $to): bool
    {
        if ($from === $to) {
            return true;
        }

        if (!self::isValidStatus($from) || !self::isValidStatus($to)) {
            return false;
        }

        return in_array($to, self::STATUS_TRANSITIONS[$from] ?? [], true);
    }

    public static function getAllowedStatuses(string $current): array
    {
        if (!self::isValidStatus($current)) {
            return self::STATUSES;
        }

        $options = array_merge([$current], self::STATUS_TRANSITIONS[$current] ?? []);

        return array_values(array_unique($options));
    }

    public static function statusLabel(string $status): string
    {
        return ucwords(str_replace('_', ' ', $status));
    }

    public static function assertStatusTransition(string $from, string $to): void
    {
        if (!self::canTransitionStatus($from, $to)) {
            throw new RuntimeException(
                'Cannot change status from ' . self::statusLabel($from) . ' to ' . self::statusLabel($to) . '.'
            );
        }
    }
    public static function parseServicesFromRequest(array $data): array
    {
        $types = $data['services']['type'] ?? null;
        $fees  = $data['services']['fee'] ?? null;

        if (is_array($types) && is_array($fees)) {
            $services = [];

            foreach ($types as $index => $type) {
                $type = trim((string) $type);
                if ($type === '') {
                    continue;
                }

                $services[] = [
                    'type' => $type,
                    'fee'  => (float) ($fees[$index] ?? 0),
                ];
            }

            if ($services !== []) {
                return $services;
            }
        }

        $type = trim($data['service_type'] ?? '');
        if ($type === '') {
            throw new RuntimeException('At least one service is required.');
        }

        return [[
            'type' => $type,
            'fee'  => (float) ($data['service_fee'] ?? 0),
        ]];
    }

    public static function getCaseServices(array $case): array
    {
        if (!empty($case['services'])) {
            $decoded = is_string($case['services']) ? json_decode($case['services'], true) : $case['services'];

            if (is_array($decoded) && $decoded !== []) {
                $services = [];

                foreach ($decoded as $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    $type = trim((string) ($item['type'] ?? $item['description'] ?? ''));
                    if ($type === '') {
                        continue;
                    }

                    $services[] = [
                        'type' => $type,
                        'fee'  => (float) ($item['fee'] ?? $item['amount'] ?? 0),
                    ];
                }

                if ($services !== []) {
                    return $services;
                }
            }
        }

        $type = trim($case['service_type'] ?? '');
        if ($type === '') {
            return [['type' => '', 'fee' => 0.0]];
        }

        return [[
            'type' => $type,
            'fee'  => (float) ($case['service_fee'] ?? 0),
        ]];
    }

    public static function caseServicesTotal(array $services): float
    {
        $total = 0.0;

        foreach ($services as $service) {
            $total += (float) ($service['fee'] ?? 0);
        }

        return $total;
    }

    public static function formatCaseServicesLabel(array $services, int $maxLength = 150): string
    {
        if ($services === []) {
            return '';
        }

        $labels = array_map(static fn(array $service): string => $service['type'], $services);
        $joined = implode(', ', $labels);

        if (strlen($joined) <= $maxLength) {
            return $joined;
        }

        $extra = count($services) - 1;

        return $extra > 0
            ? $services[0]['type'] . ' +' . $extra . ' more'
            : substr($services[0]['type'], 0, $maxLength - 3) . '...';
    }

    public static function formatCaseServicesHtml(array $case): string
    {
        $services = self::getCaseServices($case);
        $rows     = '';

        foreach ($services as $service) {
            $rows .= '<tr><td>' . e($service['type']) . '</td><td class="num">' . formatCurrency((float) $service['fee']) . '</td></tr>';
        }

        return $rows;
    }

    private static function resolveCaseServices(array $data): array
    {
        $services = self::parseServicesFromRequest($data);

        return [
            'services'      => $services,
            'service_type'  => self::formatCaseServicesLabel($services),
            'service_fee'   => self::caseServicesTotal($services),
            'services_json' => json_encode($services, JSON_UNESCAPED_UNICODE),
        ];
    }

    private static function insertCaseRow(array $row): int
    {
        $columns = array_keys($row);
        $sql     = sprintf(
            'INSERT INTO cases (%s, created_at, updated_at) VALUES (%s, NOW(), NOW())',
            implode(', ', $columns),
            implode(', ', array_fill(0, count($columns), '?'))
        );

        return Database::insert($sql, array_values($row));
    }

    private static function updateCaseRow(int $id, array $row): void
    {
        $sets   = [];
        $params = [];

        foreach ($row as $column => $value) {
            $sets[]   = "{$column} = ?";
            $params[] = $value;
        }

        $sets[]   = 'updated_at = NOW()';
        $params[] = $id;

        Database::query('UPDATE cases SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
    }

    public static function generateNumber(string $prefix): string
    {
        $year = date('Y');
        $pattern = $prefix . '-' . $year . '-%';
        $tableMap = [
            'CASE' => ['cases', 'case_number'],
            'INV'  => ['invoices', 'invoice_number'],
            'QUO'  => ['quotations', 'quotation_number'],
            'PRO'  => ['proposals', 'proposal_number'],
            'RCP'  => ['receipts', 'receipt_number'],
        ];

        [$table, $column] = $tableMap[$prefix] ?? ['cases', 'case_number'];

        try {
            $count = (int) (Database::fetch(
                "SELECT COUNT(*) AS c FROM {$table} WHERE {$column} LIKE ?",
                [$pattern]
            )['c'] ?? 0);
        } catch (Throwable $e) {
            $count = random_int(100, 999);
        }

        return sprintf('%s-%s-%04d', $prefix, $year, $count + 1);
    }

    public static function getCaseById(int $id): ?array
    {
        return Database::fetch(
            "SELECT cs.*, cl.first_name, cl.last_name, cl.email, cl.phone, cl.company_name,
                    cl.user_id AS client_user_id, adm.name AS admin_name
             FROM cases cs
             JOIN clients cl ON cl.id = cs.client_id
             LEFT JOIN users adm ON adm.id = cs.assigned_admin_id
             WHERE cs.id = ?",
            [$id]
        );
    }

    public static function getCaseForClient(int $caseId, int $clientId): ?array
    {
        return Database::fetch(
            "SELECT cs.*, cl.first_name, cl.last_name, cl.email, cl.company_name
             FROM cases cs
             JOIN clients cl ON cl.id = cs.client_id
             WHERE cs.id = ? AND cs.client_id = ?",
            [$caseId, $clientId]
        );
    }

    public static function getWorkspace(int $caseId): ?array
    {
        $case = self::getCaseById($caseId);
        if (!$case) {
            return null;
        }

        return [
            'case'        => $case,
            'documents'   => self::getDocuments($caseId),
            'quotations'  => self::getQuotations($caseId),
            'proposals'   => self::getProposals($caseId),
            'invoices'    => self::getInvoices($caseId),
            'payments'    => self::getPayments($caseId),
            'receipts'    => self::getReceipts($caseId),
            'notes'       => self::getNotes($caseId, true),
            'activity'    => self::getActivity($caseId),
        ];
    }

    public static function createCase(array $data, int $adminId): int
    {
        $caseNumber   = self::generateNumber('CASE');
        $instructions = trim($data['client_instructions'] ?? '') ?: null;
        $status       = $data['status'] ?? 'pending';

        if (!self::isValidStatus($status)) {
            throw new RuntimeException('Invalid case status.');
        }

        $resolved = self::resolveCaseServices($data);
        $row      = [
            'case_number'       => $caseNumber,
            'title'             => trim($data['title']),
            'description'       => trim($data['description'] ?? '') ?: null,
            'service_type'      => $resolved['service_type'],
            'service_fee'       => $resolved['service_fee'],
            'client_id'         => (int) $data['client_id'],
            'assigned_admin_id' => !empty($data['assigned_admin_id']) ? (int) $data['assigned_admin_id'] : $adminId,
            'priority'          => $data['priority'] ?? 'medium',
            'deadline'          => !empty($data['deadline']) ? $data['deadline'] : null,
            'status'            => $status,
        ];

        if (Database::columnExists('cases', 'client_instructions')) {
            $row['client_instructions'] = $instructions;
        }

        if (Database::columnExists('cases', 'services')) {
            $row['services'] = $resolved['services_json'];
        }

        $id = self::insertCaseRow($row);

        if ($instructions && !Database::columnExists('cases', 'client_instructions') && Database::tableExists('case_notes')) {
            try {
                self::addNote($id, $adminId, 'Client instructions: ' . $instructions, false);
            } catch (Throwable $e) {
                // Optional notes table
            }
        }

        try {
            self::notifyCaseEvent($id, 'case', 'New case created', "Case {$caseNumber} was created.", 'pages/case-view.php?id=' . $id);
        } catch (Throwable $e) {
            // Notifications are optional
        }

        return $id;
    }

    public static function runCreateWorkflow(int $caseId, array $data, int $adminId): array
    {
        $case   = self::getCaseById($caseId);
        $client = ClientService::getById((int) ($case['client_id'] ?? 0));

        if (!$case || !$client) {
            return ['quote_sent' => false, 'login_sent' => false, 'error' => null];
        }

        $instructions = trim($data['client_instructions'] ?? $case['client_instructions'] ?? '');
        $sendEmails   = !isset($data['send_emails']) || !empty($data['send_emails']);

        $quoteSent = false;
        $loginSent = false;
        $error     = null;

        if (!$sendEmails || empty($client['email'])) {
            return ['quote_sent' => false, 'login_sent' => false, 'error' => null];
        }

        try {
            if (!Database::tableExists('quotations')) {
                throw new RuntimeException(
                    'Quotation tables are not set up yet. Run: php admin/sql/migrate_cases.php'
                );
            }

            $quotationId = self::generateQuotation($caseId, [
                'title'  => 'Quotation — ' . $case['title'],
                'amount' => (float) $case['service_fee'],
            ]);

            $quotation = Database::fetch('SELECT * FROM quotations WHERE id = ?', [$quotationId]);
            $docPath   = null;

            if (!empty($quotation['pdf_path'])) {
                $config  = require __DIR__ . '/../config/config.php';
                $docPath = rtrim($config['upload']['path'], '/\\') . '/' . ltrim($quotation['pdf_path'], '/');
            }

            $quoteSent = MailService::sendQuoteEmail(
                $client,
                $case,
                $quotation['quotation_number'] ?? 'QUO',
                $docPath && is_file($docPath) ? $docPath : null
            );
        } catch (Throwable $e) {
            $error = 'Quotation email could not be sent. You can generate one from the case page.';
        }

        try {
            if (!empty($client['user_id'])) {
                $loginSent = MailService::sendLoginEmail($client, $instructions);
            }
        } catch (Throwable $e) {
            $error = ($error ? $error . ' ' : '') . 'Portal login email could not be sent.';
        }

        return ['quote_sent' => $quoteSent, 'login_sent' => $loginSent, 'error' => $error];
    }

    public static function updateCase(int $id, array $data): void
    {
        $existing = self::getCaseById($id);
        if (!$existing) {
            throw new RuntimeException('Case not found.');
        }

        $newStatus = $data['status'] ?? $existing['status'];
        if (!self::isValidStatus($newStatus)) {
            throw new RuntimeException('Invalid case status.');
        }
        self::assertStatusTransition($existing['status'], $newStatus);

        $instructions = trim($data['client_instructions'] ?? '') ?: null;
        $resolved     = self::resolveCaseServices($data);
        $row          = [
            'title'             => trim($data['title']),
            'description'       => trim($data['description'] ?? '') ?: null,
            'service_type'      => $resolved['service_type'],
            'service_fee'       => $resolved['service_fee'],
            'client_id'         => (int) $data['client_id'],
            'assigned_admin_id' => !empty($data['assigned_admin_id']) ? (int) $data['assigned_admin_id'] : null,
            'priority'          => $data['priority'] ?? 'medium',
            'deadline'          => !empty($data['deadline']) ? $data['deadline'] : null,
            'status'            => $newStatus,
        ];

        if (Database::columnExists('cases', 'client_instructions') && array_key_exists('client_instructions', $data)) {
            $row['client_instructions'] = $instructions;
        }

        if (Database::columnExists('cases', 'services')) {
            $row['services'] = $resolved['services_json'];
        }

        self::updateCaseRow($id, $row);

        if ($existing['status'] !== $newStatus) {
            self::logCaseEvent($id, 'status_changed', [
                'from' => $existing['status'],
                'to'   => $newStatus,
            ], Auth::id());
        }

        self::notifyCaseEvent($id, 'case', 'Case updated', 'Case details were updated.', 'pages/case-view.php?id=' . $id);
    }

    public static function updateStatus(int $id, string $status, ?int $userId = null): void
    {
        $case = self::getCaseById($id);
        if (!$case) {
            throw new RuntimeException('Case not found.');
        }

        if (!self::isValidStatus($status)) {
            throw new RuntimeException('Invalid case status.');
        }

        self::assertStatusTransition($case['status'], $status);

        if ($case['status'] === $status) {
            return;
        }

        Database::query('UPDATE cases SET status = ?, updated_at = NOW() WHERE id = ?', [$status, $id]);

        self::logCaseEvent($id, 'status_changed', [
            'from' => $case['status'],
            'to'   => $status,
        ], $userId ?? Auth::id());

        $label = self::statusLabel($status);
        self::notifyCaseEvent($id, 'case', 'Case status updated', "Status changed to {$label}.", 'pages/case-view.php?id=' . $id);
    }

    public static function getDocuments(int $caseId): array
    {
        try {
            return Database::fetchAll(
                "SELECT d.*, u.name AS uploader_name
                 FROM documents d
                 LEFT JOIN users u ON u.id = d.uploaded_by
                 WHERE d.case_id = ?
                 ORDER BY d.created_at DESC",
                [$caseId]
            );
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function uploadDocument(int $caseId, array $file, int $userId, string $source = 'admin'): array
    {
        $config = require __DIR__ . '/../config/config.php';
        $upload = $config['upload'];

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Upload failed. Please try again.'];
        }

        if ($file['size'] > $upload['max_size']) {
            return ['success' => false, 'message' => 'File exceeds maximum size limit.'];
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $upload['allowed_types'], true)) {
            return ['success' => false, 'message' => 'File type not allowed.'];
        }

        $caseDir = rtrim($upload['path'], '/\\') . '/cases/' . $caseId;
        if (!is_dir($caseDir)) {
            mkdir($caseDir, 0755, true);
        }

        $storedName = uniqid('doc_', true) . '.' . $ext;
        $destPath   = $caseDir . '/' . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            return ['success' => false, 'message' => 'Could not save uploaded file.'];
        }

        $relativePath = 'cases/' . $caseId . '/' . $storedName;
        $mimeType     = mime_content_type($destPath) ?: $file['type'] ?? 'application/octet-stream';

        try {
            Database::insert(
                "INSERT INTO documents (case_id, uploaded_by, upload_source, file_name, original_name,
                                        file_path, file_type, file_size, mime_type, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $caseId, $userId, $source, $storedName, $file['name'],
                    $relativePath, $ext, (int) $file['size'], $mimeType,
                ]
            );
        } catch (Throwable $e) {
            Database::insert(
                "INSERT INTO documents (case_id, uploaded_by, file_name, original_name,
                                        file_path, file_type, file_size, mime_type, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                [
                    $caseId, $userId, $storedName, $file['name'],
                    $relativePath, $ext, (int) $file['size'], $mimeType,
                ]
            );
        }

        $label = $source === 'client' ? 'Client uploaded a document' : 'New document uploaded';
        self::notifyCaseEvent($caseId, 'document', $label, $file['name'], 'pages/case-view.php?id=' . $caseId . '#documents');

        return ['success' => true, 'message' => 'Document uploaded successfully.'];
    }

    public static function getNotes(int $caseId, bool $internalOnly = false): array
    {
        try {
            $sql = "SELECT n.*, u.name AS author_name
                    FROM case_notes n
                    JOIN users u ON u.id = n.user_id
                    WHERE n.case_id = ?";
            $params = [$caseId];
            if ($internalOnly) {
                $sql .= ' AND n.is_internal = 1';
            }
            $sql .= ' ORDER BY n.created_at DESC';
            return Database::fetchAll($sql, $params);
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function addNote(int $caseId, int $userId, string $note, bool $internal = true): void
    {
        Database::insert(
            'INSERT INTO case_notes (case_id, user_id, note, is_internal, created_at) VALUES (?, ?, ?, ?, NOW())',
            [$caseId, $userId, trim($note), $internal ? 1 : 0]
        );
    }

    public static function getQuotations(int $caseId): array
    {
        try {
            return Database::fetchAll(
                'SELECT * FROM quotations WHERE case_id = ? ORDER BY created_at DESC',
                [$caseId]
            );
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function getProposals(int $caseId): array
    {
        try {
            return Database::fetchAll(
                'SELECT * FROM proposals WHERE case_id = ? ORDER BY created_at DESC',
                [$caseId]
            );
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function getInvoices(int $caseId): array
    {
        $statusCol = invoiceStatusColumn();

        return Database::fetchAll(
            "SELECT i.*, i.{$statusCol} AS payment_status
             FROM invoices i
             WHERE i.case_id = ?
             ORDER BY i.created_at DESC",
            [$caseId]
        );
    }

    public static function getPayments(int $caseId): array
    {
        $statusCol = paymentStatusColumn();

        return Database::fetchAll(
            "SELECT p.*, p.{$statusCol} AS payment_status,
                    i.invoice_number, i.total AS invoice_total
             FROM payments p
             JOIN invoices i ON i.id = p.invoice_id
             WHERE i.case_id = ?
             ORDER BY COALESCE(p.paid_at, p.created_at) DESC",
            [$caseId]
        );
    }

    public static function getReceipts(int $caseId): array
    {
        try {
            return Database::fetchAll(
                "SELECT r.*, i.invoice_number
                 FROM receipts r
                 JOIN invoices i ON i.id = r.invoice_id
                 WHERE i.case_id = ?
                 ORDER BY r.created_at DESC",
                [$caseId]
            );
        } catch (Throwable $e) {
            return [];
        }
    }

    public static function generateQuotation(int $caseId, array $data): int
    {
        $case     = self::getCaseById($caseId);
        $number   = self::generateNumber('QUO');
        $services = self::getCaseServices($case ?: []);
        $fee      = (float) ($data['amount'] ?? self::caseServicesTotal($services));
        $tax      = (float) ($data['tax_rate'] ?? 0);
        $total    = $fee + ($fee * $tax / 100);

        $lineItems = array_map(static fn(array $service): array => [
            'description' => $service['type'],
            'amount'      => (float) $service['fee'],
        ], $services);

        if ($lineItems === []) {
            $lineItems = [['description' => $case['service_type'] ?? 'Service', 'amount' => $fee]];
        }

        $lineItemsJson = json_encode($lineItems, JSON_UNESCAPED_UNICODE);

        $id = Database::insert(
            "INSERT INTO quotations (case_id, quotation_number, title, line_items, subtotal, tax_rate, total, status, valid_until, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'sent', ?, NOW(), NOW())",
            [
                $caseId,
                $number,
                $data['title'] ?? 'Quotation for ' . $case['title'],
                $lineItemsJson,
                $fee,
                $tax,
                $total,
                $data['valid_until'] ?? date('Y-m-d', strtotime('+30 days')),
            ]
        );

        self::saveHtmlDocument($caseId, 'quotation', $id);

        self::notifyCaseEvent($caseId, 'document', 'Quotation created', $number, 'pages/case-view.php?id=' . $caseId . '#quotations');

        return $id;
    }

    public static function generateProposal(int $caseId, array $data): int
    {
        $case    = self::getCaseById($caseId);
        $number  = self::generateNumber('PRO');
        $content = trim($data['content'] ?? '') ?: 'Proposal for notary services related to ' . $case['title'];
        $amount  = (float) ($data['amount'] ?? $case['service_fee'] ?? 0);

        $id = Database::insert(
            "INSERT INTO proposals (case_id, proposal_number, title, content, amount, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, 'sent', NOW(), NOW())",
            [
                $caseId,
                $number,
                $data['title'] ?? 'Proposal — ' . $case['title'],
                $content,
                $amount,
            ]
        );

        self::saveHtmlDocument($caseId, 'proposal', $id);

        self::notifyCaseEvent($caseId, 'document', 'Proposal created', $number, 'pages/case-view.php?id=' . $caseId . '#quotations');

        return $id;
    }

    public static function generateInvoice(int $caseId, array $data): int
    {
        $case   = self::getCaseById($caseId);
        $number = self::generateNumber('INV');
        $amount = (float) ($data['amount'] ?? $case['service_fee'] ?? 0);
        $tax    = (float) ($data['tax_rate'] ?? 0);
        $taxAmt = $amount * $tax / 100;
        $total  = $amount + $taxAmt;
        $due    = $data['due_date'] ?? date('Y-m-d', strtotime('+14 days'));

        try {
            $id = Database::insert(
                "INSERT INTO invoices (invoice_number, case_id, client_id, amount, tax_rate, tax_amount, total,
                                       payment_status, due_date, notes, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW(), NOW())",
                [$number, $caseId, $case['client_id'], $amount, $tax, $taxAmt, $total, $due, $data['notes'] ?? null]
            );
        } catch (Throwable $e) {
            $id = Database::insert(
                "INSERT INTO invoices (invoice_number, case_id, client_id, amount, tax_rate, tax_amount, total,
                                       status, due_date, notes, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW(), NOW())",
                [$number, $caseId, $case['client_id'], $amount, $tax, $taxAmt, $total, $due, $data['notes'] ?? null]
            );
        }

        self::saveHtmlDocument($caseId, 'invoice', $id);

        self::notifyCaseEvent($caseId, 'invoice', 'Invoice generated', $number . ' — ' . formatCurrency($total), 'pages/case-view.php?id=' . $caseId . '#invoice-payments');

        return $id;
    }

    public static function getInvoicePaidTotal(int $invoiceId): float
    {
        $statusCol = paymentStatusColumn();

        return (float) (Database::fetch(
            "SELECT COALESCE(SUM(amount), 0) AS total FROM payments
             WHERE invoice_id = ? AND {$statusCol} = 'completed'",
            [$invoiceId]
        )['total'] ?? 0);
    }

    public static function getInvoiceRemainingBalance(array $invoice): float
    {
        return max(0, round((float) ($invoice['total'] ?? 0) - self::getInvoicePaidTotal((int) $invoice['id']), 2));
    }

    public static function updateInvoicePaymentStatus(int $invoiceId): void
    {
        $invoice = Database::fetch('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
        if (!$invoice) {
            return;
        }

        $statusCol = invoiceStatusColumn();
        $paid      = self::getInvoicePaidTotal($invoiceId);
        $total     = (float) ($invoice['total'] ?? 0);

        if ($paid >= $total - 0.009) {
            $newStatus = 'paid';
        } elseif ($paid > 0) {
            $newStatus = 'partially_paid';
        } elseif (!empty($invoice['due_date']) && strtotime($invoice['due_date']) < strtotime('today')) {
            $newStatus = 'overdue';
        } else {
            $newStatus = 'pending';
        }

        Database::query(
            "UPDATE invoices SET {$statusCol} = ?, updated_at = NOW() WHERE id = ?",
            [$newStatus, $invoiceId]
        );
    }

    public static function recordStripePayment(int $invoiceId, float $amount, string $stripePaymentId): array
    {
        if ($stripePaymentId !== '') {
            $existing = Database::fetch(
                'SELECT id FROM payments WHERE stripe_payment_id = ? LIMIT 1',
                [$stripePaymentId]
            );
            if ($existing) {
                return ['success' => true, 'message' => 'Payment already recorded.', 'payment_id' => (int) $existing['id']];
            }
        }

        return self::recordPayment($invoiceId, [
            'amount'            => $amount,
            'payment_method'    => 'stripe',
            'stripe_payment_id' => $stripePaymentId,
            'notes'             => 'Paid via Stripe Checkout',
        ], 0);
    }

    public static function recordPayment(int $invoiceId, array $data, int $adminId): array
    {
        $invoice = Database::fetch('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
        if (!$invoice) {
            return ['success' => false, 'message' => 'Invoice not found.'];
        }

        $remaining = self::getInvoiceRemainingBalance($invoice);
        if ($remaining <= 0) {
            return ['success' => false, 'message' => 'This invoice is already fully paid.'];
        }

        $amount = isset($data['amount']) && $data['amount'] !== ''
            ? (float) $data['amount']
            : $remaining;
        $method = $data['payment_method'] ?? 'bank_transfer';
        $stripeId = trim($data['stripe_payment_id'] ?? '');

        if ($amount <= 0 || $amount > $remaining + 0.009) {
            return [
                'success' => false,
                'message' => 'Invalid payment amount. Remaining balance: ' . formatCurrency($remaining) . '.',
            ];
        }

        if ($stripeId !== '') {
            $existing = Database::fetch(
                'SELECT id FROM payments WHERE stripe_payment_id = ? LIMIT 1',
                [$stripeId]
            );
            if ($existing) {
                return ['success' => true, 'message' => 'Payment already recorded.', 'payment_id' => (int) $existing['id']];
            }
        }

        $statusCol = paymentStatusColumn();

        try {
            $paymentId = Database::insert(
                "INSERT INTO payments (invoice_id, amount, payment_method, stripe_payment_id, {$statusCol}, paid_at, notes, created_at)
                 VALUES (?, ?, ?, ?, 'completed', NOW(), ?, NOW())",
                [$invoiceId, $amount, $method, $stripeId ?: null, $data['notes'] ?? null]
            );
        } catch (Throwable $e) {
            $paymentId = Database::insert(
                "INSERT INTO payments (invoice_id, amount, payment_method, stripe_payment_id, status, paid_at, notes, created_at)
                 VALUES (?, ?, ?, ?, 'completed', NOW(), ?, NOW())",
                [$invoiceId, $amount, $method, $stripeId ?: null, $data['notes'] ?? null]
            );
        }

        self::updateInvoicePaymentStatus($invoiceId);

        $invoice['payment_amount'] = $amount;
        $receiptId = self::generateReceipt($paymentId, $invoice);

        $caseId = (int) ($invoice['case_id'] ?? 0);
        if ($caseId) {
            self::notifyCaseEvent(
                $caseId,
                'payment',
                'Payment received',
                formatCurrency($amount) . ' for ' . ($invoice['invoice_number'] ?? 'invoice'),
                'pages/case-view.php?id=' . $caseId . '#invoice-payments'
            );
        }

        return ['success' => true, 'message' => 'Payment recorded.', 'payment_id' => $paymentId, 'receipt_id' => $receiptId];
    }

    public static function generateReceipt(int $paymentId, ?array $invoice = null): int
    {
        if (!$invoice) {
            $invoice = Database::fetch(
                'SELECT i.*, p.amount AS payment_amount FROM invoices i JOIN payments p ON p.invoice_id = i.id WHERE p.id = ?',
                [$paymentId]
            );
        }

        $number = self::generateNumber('RCP');
        $amount = (float) ($invoice['payment_amount'] ?? $invoice['total'] ?? 0);

        try {
            return Database::insert(
                "INSERT INTO receipts (receipt_number, payment_id, invoice_id, client_id, amount, created_at)
                 VALUES (?, ?, ?, ?, ?, NOW())",
                [$number, $paymentId, $invoice['id'], $invoice['client_id'], $amount]
            );
        } catch (Throwable $e) {
            return 0;
        }
    }

    public static function getActivity(int $caseId, int $limit = 50): array
    {
        $events = [];

        $case = self::getCaseById($caseId);
        if ($case) {
            $events[] = [
                'type'   => 'case_created',
                'title'  => 'Case created',
                'detail' => $case['case_number'],
                'time'   => $case['created_at'],
                'actor'  => null,
            ];
        }

        foreach (self::getDocuments($caseId) as $doc) {
            $events[] = [
                'type'   => 'document',
                'title'  => ($doc['upload_source'] ?? 'admin') === 'client' ? 'Client uploaded document' : 'Document uploaded',
                'detail' => $doc['original_name'] ?? $doc['file_name'],
                'time'   => $doc['created_at'],
                'actor'  => $doc['uploader_name'] ?? null,
            ];
        }

        foreach (self::getInvoices($caseId) as $inv) {
            $events[] = [
                'type'   => 'invoice',
                'title'  => 'Invoice generated',
                'detail' => ($inv['invoice_number'] ?? '') . ' · ' . formatCurrency((float) ($inv['total'] ?? 0)),
                'time'   => $inv['created_at'],
                'actor'  => null,
            ];
        }

        foreach (self::getPayments($caseId) as $pay) {
            $events[] = [
                'type'   => 'payment',
                'title'  => 'Payment received',
                'detail' => formatCurrency((float) ($pay['amount'] ?? 0)) . ' · ' . ($pay['invoice_number'] ?? ''),
                'time'   => $pay['paid_at'] ?? $pay['created_at'],
                'actor'  => null,
            ];
        }

        foreach (self::getProposals($caseId) as $pro) {
            $events[] = [
                'type'   => 'proposal',
                'title'  => 'Proposal created',
                'detail' => ($pro['proposal_number'] ?? '') . ' · ' . formatCurrency((float) ($pro['amount'] ?? 0)),
                'time'   => $pro['created_at'],
                'actor'  => null,
            ];
        }

        foreach (self::getQuotations($caseId) as $quo) {
            $events[] = [
                'type'   => 'quotation',
                'title'  => 'Quotation created',
                'detail' => ($quo['quotation_number'] ?? '') . ' · ' . formatCurrency((float) ($quo['total'] ?? 0)),
                'time'   => $quo['created_at'],
                'actor'  => null,
            ];
        }

        foreach (self::getNotes($caseId, true) as $note) {
            $events[] = [
                'type'   => 'note',
                'title'  => 'Internal note added',
                'detail' => mb_substr($note['note'], 0, 120),
                'time'   => $note['created_at'],
                'actor'  => $note['author_name'] ?? null,
            ];
        }

        try {
            foreach (Database::fetchAll(
                "SELECT a.*, u.name AS actor_name
                 FROM appointments a
                 WHERE a.case_id = ?
                 ORDER BY a.created_at DESC",
                [$caseId]
            ) as $appt) {
                $events[] = [
                    'type'   => 'appointment',
                    'title'  => 'Appointment scheduled',
                    'detail' => ($appt['title'] ?? 'Appointment') . ' · ' . formatDateTime($appt['start_time'] ?? $appt['created_at']),
                    'time'   => $appt['created_at'],
                    'actor'  => null,
                ];
            }
        } catch (Throwable $e) {
            // appointments optional
        }

        try {
            foreach (Database::fetchAll(
                "SELECT al.*, u.name AS actor_name
                 FROM audit_logs al
                 LEFT JOIN users u ON u.id = al.user_id
                 WHERE al.entity_type = 'case' AND al.entity_id = ?
                 ORDER BY al.created_at DESC",
                [$caseId]
            ) as $log) {
                $details = json_decode($log['details'] ?? '{}', true) ?: [];
                $event   = self::auditLogToActivityEvent($log['action'], $details, $log['actor_name'] ?? null);
                if ($event) {
                    $events[] = array_merge($event, ['time' => $log['created_at']]);
                }
            }
        } catch (Throwable $e) {
            // audit logs optional
        }

        usort($events, static fn($a, $b) => strtotime($b['time']) <=> strtotime($a['time']));

        return array_slice($events, 0, $limit);
    }

    private static function auditLogToActivityEvent(string $action, array $details, ?string $actor): ?array
    {
        if ($action === 'status_changed') {
            $from = self::statusLabel($details['from'] ?? '');
            $to   = self::statusLabel($details['to'] ?? '');

            return [
                'type'   => 'status',
                'title'  => 'Status changed',
                'detail' => $from . ' → ' . $to,
                'actor'  => $actor,
            ];
        }

        return null;
    }

    public static function logCaseEvent(int $caseId, string $action, array $details = [], ?int $userId = null): void
    {
        try {
            Database::insert(
                'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address, user_agent, details, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    $userId,
                    $action,
                    'case',
                    $caseId,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                    json_encode($details, JSON_UNESCAPED_UNICODE),
                ]
            );
        } catch (Throwable $e) {
            // optional
        }
    }

    public static function getAdmins(): array
    {
        return Database::fetchAll(
            "SELECT id, name, email FROM users WHERE role = 'admin' AND status = 'active' ORDER BY name ASC"
        );
    }

    public static function notifyCaseEvent(int $caseId, string $type, string $title, string $message, string $link = ''): void
    {
        $case = self::getCaseById($caseId);
        if (!$case) {
            return;
        }

        $userIds = [];

        if (!empty($case['client_user_id'])) {
            $userIds[] = (int) $case['client_user_id'];
        }

        foreach (Database::fetchAll("SELECT id FROM users WHERE role = 'admin' AND status = 'active'") as $admin) {
            $userIds[] = (int) $admin['id'];
        }

        $userIds = array_unique($userIds);

        foreach ($userIds as $userId) {
            try {
                Database::insert(
                    'INSERT INTO notifications (user_id, title, message, type, is_read, link, created_at) VALUES (?, ?, ?, ?, 0, ?, NOW())',
                    [$userId, $title, $message, $type, $link ? url($link) : null]
                );
            } catch (Throwable $e) {
                // notifications optional
            }
        }
    }

    private static function saveHtmlDocument(int $caseId, string $kind, int $docId): void
    {
        $case = self::getCaseById($caseId);
        if (!$case) {
            return;
        }

        $html = match ($kind) {
            'quotation' => DocumentTemplate::quotation($case, Database::fetch('SELECT * FROM quotations WHERE id = ?', [$docId]) ?: []),
            'proposal'  => DocumentTemplate::proposal($case, Database::fetch('SELECT * FROM proposals WHERE id = ?', [$docId]) ?: []),
            'invoice'   => DocumentTemplate::invoice($case, Database::fetch('SELECT * FROM invoices WHERE id = ?', [$docId]) ?: []),
            default     => '',
        };

        if ($html === '') {
            return;
        }

        $config = require __DIR__ . '/../config/config.php';
        $dir    = rtrim($config['upload']['path'], '/\\') . '/cases/' . $caseId . '/generated';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = $kind . '_' . $docId . '.html';
        file_put_contents($dir . '/' . $filename, $html);

        $relative = 'cases/' . $caseId . '/generated/' . $filename;

        try {
            if ($kind === 'quotation') {
                Database::query('UPDATE quotations SET pdf_path = ? WHERE id = ?', [$relative, $docId]);
            } elseif ($kind === 'proposal') {
                Database::query('UPDATE proposals SET pdf_path = ? WHERE id = ?', [$relative, $docId]);
            } elseif ($kind === 'invoice') {
                Database::query('UPDATE invoices SET pdf_path = ? WHERE id = ?', [$relative, $docId]);
            }
        } catch (Throwable $e) {
            // pdf_path optional
        }
    }

    public static function documentPath(string $relativePath): string
    {
        $config = require __DIR__ . '/../config/config.php';
        return rtrim($config['upload']['path'], '/\\') . '/' . ltrim($relativePath, '/\\');
    }

    public static function deleteCase(int $caseId): void
    {
        $case = self::getCaseById($caseId);
        if (!$case) {
            throw new RuntimeException('Case not found.');
        }

        foreach (self::getDocuments($caseId) as $doc) {
            $path = $doc['file_path'] ?? $doc['stored_path'] ?? null;
            if ($path && is_file(self::documentPath($path))) {
                @unlink(self::documentPath($path));
            }
        }

        Database::query('DELETE FROM cases WHERE id = ?', [$caseId]);
    }

    public static function deleteDocument(int $documentId, int $caseId): void
    {
        $doc = Database::fetch('SELECT * FROM documents WHERE id = ? AND case_id = ?', [$documentId, $caseId]);
        if (!$doc) {
            throw new RuntimeException('Document not found.');
        }

        $path = $doc['file_path'] ?? null;
        if ($path && is_file(self::documentPath($path))) {
            @unlink(self::documentPath($path));
        }

        Database::query('DELETE FROM documents WHERE id = ?', [$documentId]);
    }
}
