<?php

declare(strict_types=1);

class CaseService
{
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
        $caseNumber = self::generateNumber('CASE');

        $id = Database::insert(
            "INSERT INTO cases (case_number, title, description, service_type, service_fee,
                                client_id, assigned_admin_id, priority, deadline, status, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
            [
                $caseNumber,
                trim($data['title']),
                trim($data['description'] ?? '') ?: null,
                trim($data['service_type']),
                (float) ($data['service_fee'] ?? 0),
                (int) $data['client_id'],
                !empty($data['assigned_admin_id']) ? (int) $data['assigned_admin_id'] : $adminId,
                $data['priority'] ?? 'medium',
                !empty($data['deadline']) ? $data['deadline'] : null,
                $data['status'] ?? 'pending',
            ]
        );

        self::notifyCaseEvent($id, 'case', 'New case created', "Case {$caseNumber} was created.", 'pages/case-view.php?id=' . $id);

        return $id;
    }

    public static function updateCase(int $id, array $data): void
    {
        Database::query(
            "UPDATE cases SET title = ?, description = ?, service_type = ?, service_fee = ?,
                              client_id = ?, assigned_admin_id = ?, priority = ?, deadline = ?, status = ?,
                              updated_at = NOW()
             WHERE id = ?",
            [
                trim($data['title']),
                trim($data['description'] ?? '') ?: null,
                trim($data['service_type']),
                (float) ($data['service_fee'] ?? 0),
                (int) $data['client_id'],
                !empty($data['assigned_admin_id']) ? (int) $data['assigned_admin_id'] : null,
                $data['priority'] ?? 'medium',
                !empty($data['deadline']) ? $data['deadline'] : null,
                $data['status'] ?? 'pending',
                $id,
            ]
        );

        self::notifyCaseEvent($id, 'case', 'Case updated', 'Case details were updated.', 'pages/case-view.php?id=' . $id);
    }

    public static function updateStatus(int $id, string $status): void
    {
        Database::query('UPDATE cases SET status = ?, updated_at = NOW() WHERE id = ?', [$status, $id]);
        $label = ucwords(str_replace('_', ' ', $status));
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
        $case   = self::getCaseById($caseId);
        $number = self::generateNumber('QUO');
        $fee    = (float) ($data['amount'] ?? $case['service_fee'] ?? 0);
        $tax    = (float) ($data['tax_rate'] ?? 0);
        $total  = $fee + ($fee * $tax / 100);

        $lineItems = json_encode([['description' => $case['service_type'] ?? 'Service', 'amount' => $fee]]);

        $id = Database::insert(
            "INSERT INTO quotations (case_id, quotation_number, title, line_items, subtotal, tax_rate, total, status, valid_until, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'sent', ?, NOW(), NOW())",
            [
                $caseId,
                $number,
                $data['title'] ?? 'Quotation for ' . $case['title'],
                $lineItems,
                $fee,
                $tax,
                $total,
                $data['valid_until'] ?? date('Y-m-d', strtotime('+30 days')),
            ]
        );

        self::saveHtmlDocument($caseId, 'quotation', $id, $number, $case, [
            'title'   => 'Quotation',
            'number'  => $number,
            'amount'  => $total,
            'details' => $case['service_type'],
        ]);

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

        self::saveHtmlDocument($caseId, 'proposal', $id, $number, $case, [
            'title'   => 'Proposal',
            'number'  => $number,
            'amount'  => $amount,
            'details' => nl2br(e($content)),
        ]);

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

        self::saveHtmlDocument($caseId, 'invoice', $id, $number, $case, [
            'title'   => 'Invoice',
            'number'  => $number,
            'amount'  => $total,
            'details' => 'Due: ' . formatDate($due),
        ]);

        self::notifyCaseEvent($caseId, 'invoice', 'Invoice generated', $number . ' — ' . formatCurrency($total), 'pages/case-view.php?id=' . $caseId . '#invoices');

        return $id;
    }

    public static function recordPayment(int $invoiceId, array $data, int $adminId): array
    {
        $invoice = Database::fetch('SELECT * FROM invoices WHERE id = ?', [$invoiceId]);
        if (!$invoice) {
            return ['success' => false, 'message' => 'Invoice not found.'];
        }

        $amount = (float) ($data['amount'] ?? $invoice['total']);
        $method = $data['payment_method'] ?? 'bank_transfer';

        try {
            $paymentId = Database::insert(
                "INSERT INTO payments (invoice_id, amount, payment_method, payment_status, paid_at, notes, created_at)
                 VALUES (?, ?, ?, 'completed', NOW(), ?, NOW())",
                [$invoiceId, $amount, $method, $data['notes'] ?? null]
            );
        } catch (Throwable $e) {
            $paymentId = Database::insert(
                "INSERT INTO payments (invoice_id, amount, payment_method, status, paid_at, notes, created_at)
                 VALUES (?, ?, ?, 'completed', NOW(), ?, NOW())",
                [$invoiceId, $amount, $method, $data['notes'] ?? null]
            );
        }

        try {
            Database::query(
                "UPDATE invoices SET payment_status = 'paid', updated_at = NOW() WHERE id = ?",
                [$invoiceId]
            );
        } catch (Throwable $e) {
            Database::query("UPDATE invoices SET status = 'paid', updated_at = NOW() WHERE id = ?", [$invoiceId]);
        }

        $receiptId = self::generateReceipt($paymentId, $invoice);

        $caseId = (int) ($invoice['case_id'] ?? 0);
        if ($caseId) {
            self::notifyCaseEvent(
                $caseId,
                'payment',
                'Payment received',
                formatCurrency($amount) . ' for ' . ($invoice['invoice_number'] ?? 'invoice'),
                'pages/case-view.php?id=' . $caseId . '#payments'
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

    public static function getActivity(int $caseId): array
    {
        $events = [];

        $case = self::getCaseById($caseId);
        if ($case) {
            $events[] = [
                'type'    => 'case_created',
                'title'   => 'Case created',
                'detail'  => $case['case_number'],
                'time'    => $case['created_at'],
            ];
        }

        foreach (self::getDocuments($caseId) as $doc) {
            $events[] = [
                'type'   => 'document',
                'title'  => ($doc['upload_source'] ?? 'admin') === 'client' ? 'Client upload' : 'Document uploaded',
                'detail' => $doc['original_name'] ?? $doc['file_name'],
                'time'   => $doc['created_at'],
            ];
        }

        foreach (self::getInvoices($caseId) as $inv) {
            $events[] = [
                'type'   => 'invoice',
                'title'  => 'Invoice generated',
                'detail' => ($inv['invoice_number'] ?? '') . ' · ' . formatCurrency((float) ($inv['total'] ?? 0)),
                'time'   => $inv['created_at'],
            ];
        }

        foreach (self::getPayments($caseId) as $pay) {
            $events[] = [
                'type'   => 'payment',
                'title'  => 'Payment received',
                'detail' => formatCurrency((float) ($pay['amount'] ?? 0)),
                'time'   => $pay['paid_at'] ?? $pay['created_at'],
            ];
        }

        foreach (self::getProposals($caseId) as $pro) {
            $events[] = [
                'type'   => 'proposal',
                'title'  => 'Proposal created',
                'detail' => $pro['proposal_number'] ?? '',
                'time'   => $pro['created_at'],
            ];
        }

        foreach (self::getQuotations($caseId) as $quo) {
            $events[] = [
                'type'   => 'quotation',
                'title'  => 'Quotation created',
                'detail' => $quo['quotation_number'] ?? '',
                'time'   => $quo['created_at'],
            ];
        }

        usort($events, static fn($a, $b) => strtotime($b['time']) <=> strtotime($a['time']));

        return array_slice($events, 0, 30);
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

    private static function saveHtmlDocument(int $caseId, string $kind, int $docId, string $number, array $case, array $meta): void
    {
        $config = require __DIR__ . '/../config/config.php';
        $dir    = rtrim($config['upload']['path'], '/\\') . '/cases/' . $caseId . '/generated';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $company = getCompanySettings();
        $client  = clientFullName($case);
        $html    = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>' . e($meta['title']) . '</title>
            <style>body{font-family:Montserrat,sans-serif;padding:40px;color:#00182c}h1{color:#3aafa9}
            .meta{margin:20px 0;padding:16px;background:#f8fafb;border-radius:8px}</style></head><body>
            <h1>' . e($company['company_name']) . '</h1>
            <h2>' . e($meta['title']) . ' — ' . e($number) . '</h2>
            <div class="meta"><p><strong>Case:</strong> ' . e($case['case_number']) . ' — ' . e($case['title']) . '</p>
            <p><strong>Client:</strong> ' . e($client) . '</p>
            <p><strong>Amount:</strong> ' . formatCurrency((float) $meta['amount']) . '</p>
            <p>' . ($meta['details'] ?? '') . '</p></div></body></html>';

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
}
