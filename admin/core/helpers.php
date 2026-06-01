<?php

declare(strict_types=1);

function url(string $path = ''): string
{
    $config = require __DIR__ . '/../config/config.php';
    return rtrim($config['app_url'], '/') . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function adminUrl(string $path = ''): string
{
    $config = require __DIR__ . '/../config/config.php';
    return rtrim($config['app_url'], '/') . '/' . ltrim($path, '/');
}

function clientUrl(string $path = ''): string
{
    $config = require __DIR__ . '/../config/config.php';
    return rtrim($config['client_url'], '/') . '/' . ltrim($path, '/');
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function userFullName(?array $user): string
{
    if (!$user) {
        return '';
    }
    if (!empty($user['name'])) {
        return trim($user['name']);
    }
    return trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
}

function userFirstName(?array $user): string
{
    if (!$user) {
        return '';
    }
    if (!empty($user['name'])) {
        $parts = explode(' ', trim($user['name']), 2);
        return $parts[0];
    }
    return $user['first_name'] ?? '';
}

function userInitials(?array $user): string
{
    $name = userFullName($user);
    if ($name === '') {
        return 'U';
    }
    $parts = preg_split('/\s+/', $name) ?: [];
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}

function clientFullName(array $client): string
{
    return trim(($client['first_name'] ?? '') . ' ' . ($client['last_name'] ?? ''));
}

function appointmentDateTimeValue(?string $value): ?string
{
    if ($value === null) {
        return null;
    }

    $value = trim($value);

    if ($value === '' || str_starts_with($value, '0000-00-00')) {
        return null;
    }

    return $value;
}

function appointmentStart(array $appointment): ?string
{
    return appointmentEffectiveStart($appointment);
}

function appointmentEnd(array $appointment): ?string
{
    return appointmentEffectiveEnd($appointment);
}

function appointmentEffectiveStart(array $appointment): ?string
{
    $resolved = appointmentDateTimeValue($appointment['start_time'] ?? null);
    if ($resolved !== null) {
        return $resolved;
    }

    return appointmentDateTimeValue($appointment['starts_at'] ?? null);
}

function appointmentEffectiveEnd(array $appointment): ?string
{
    $resolved = appointmentDateTimeValue($appointment['end_time'] ?? null);
    if ($resolved !== null) {
        return $resolved;
    }

    return appointmentDateTimeValue($appointment['ends_at'] ?? null);
}

function isUpcomingAppointment(array $appointment): bool
{
    $status = strtolower(trim($appointment['status'] ?? ''));
    if (!in_array($status, ['scheduled', 'confirmed'], true)) {
        return false;
    }

    $start = appointmentEffectiveStart($appointment);
    if (!$start) {
        return false;
    }

    $now = time();
    $end = appointmentEffectiveEnd($appointment);

    if ($end && strtotime($end) >= $now) {
        return true;
    }

    return strtotime($start) >= $now;
}

function isPastAppointment(array $appointment): bool
{
    $start = appointmentEffectiveStart($appointment);
    if (!$start) {
        return false;
    }

    $now = time();
    $end = appointmentEffectiveEnd($appointment);

    if ($end) {
        return strtotime($end) < $now;
    }

    return strtotime($start) < $now;
}

function appointmentStatusColors(): array
{
    return [
        'scheduled' => '#3aafa9',
        'confirmed' => '#10b981',
        'completed' => '#64748b',
        'cancelled' => '#ef4444',
        'past'      => '#f59e0b',
    ];
}

function appointmentCalendarEventColors(array $appointment): array
{
    $colors = appointmentStatusColors();
    $status = strtolower(trim($appointment['status'] ?? 'scheduled'));

    if (in_array($status, ['cancelled', 'completed'], true)) {
        $color = $colors[$status];
    } elseif (isPastAppointment($appointment)) {
        $color = $colors['past'];
    } else {
        $color = $colors[$status] ?? $colors['scheduled'];
    }

    return [
        'backgroundColor' => $color,
        'borderColor'     => $color,
        'classNames'      => isPastAppointment($appointment) && !in_array($status, ['cancelled', 'completed'], true)
            ? ['fc-event-past']
            : [],
    ];
}

function isClientScheduledAppointment(array $appointment): bool
{
    $status = strtolower(trim($appointment['status'] ?? ''));
    if (!in_array($status, ['scheduled', 'confirmed'], true)) {
        return false;
    }

    return appointmentEffectiveStart($appointment) !== null;
}

function formatAppointmentScheduleMeta(array $appointment): string
{
    $start = appointmentEffectiveStart($appointment);
    $end   = appointmentEffectiveEnd($appointment);

    if (!$start) {
        return '—';
    }

    if (!$end) {
        return formatDateTime($start, 'M j, Y g:i A');
    }

    if (date('Y-m-d', strtotime($end)) !== date('Y-m-d', strtotime($start))) {
        return formatDateTime($start, 'M j, Y g:i A') . ' – ' . formatDateTime($end, 'M j, Y g:i A');
    }

    return formatDateTime($start, 'M j, g:i A') . ' – ' . formatDateTime($end, 'g:i A');
}

function calendarEventDateTime(?string $datetime): ?string
{
    if (!$datetime) {
        return null;
    }

    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d\TH:i:s', $timestamp);
}

function normalizeDateTimeInput(string $value): string
{
    $value = trim(str_replace('T', ' ', $value));

    if ($value === '') {
        return '';
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
        $value .= ':00';
    }

    return $value;
}

function paymentStatusValue(array $payment): string
{
    return $payment['payment_status'] ?? $payment['status'] ?? 'pending';
}

function invoiceStatusValue(array $invoice): string
{
    $col = invoiceStatusColumn();

    return $invoice['payment_status'] ?? $invoice[$col] ?? $invoice['status'] ?? 'pending';
}

function effectiveInvoiceStatus(array $invoice): string
{
    $status = invoiceStatusValue($invoice);

    if (in_array($status, ['paid'], true)) {
        return $status;
    }

    if (!empty($invoice['due_date']) && strtotime($invoice['due_date']) < strtotime('today')) {
        if (in_array($status, ['pending', 'partially_paid', 'overdue'], true)) {
            return 'overdue';
        }
    }

    return $status;
}

function syncOverdueInvoices(): int
{
    $statusCol = invoiceStatusColumn();

    $overdueRows = Database::fetchAll(
        "SELECT i.*, cl.user_id AS client_user_id
         FROM invoices i
         JOIN clients cl ON cl.id = i.client_id
         WHERE i.{$statusCol} IN ('pending', 'partially_paid')
           AND i.due_date < CURDATE()"
    );

    foreach ($overdueRows as $invoice) {
        Database::query(
            "UPDATE invoices SET {$statusCol} = 'overdue', updated_at = NOW() WHERE id = ?",
            [(int) $invoice['id']]
        );

        $message = 'Invoice ' . ($invoice['invoice_number'] ?? '') . ' is overdue. Due ' . formatDate($invoice['due_date']) . '.';
        $caseId  = (int) ($invoice['case_id'] ?? 0);

        if ($caseId) {
            CaseService::notifyCaseEvent(
                $caseId,
                'invoice',
                'Invoice overdue',
                ($invoice['invoice_number'] ?? '') . ' — due ' . formatDate($invoice['due_date']),
                'pages/case-view.php?id=' . $caseId . '#invoice-payments'
            );
        } else {
            if (!empty($invoice['client_user_id'])) {
                createNotification(
                    (int) $invoice['client_user_id'],
                    'Overdue Invoice',
                    $message,
                    'invoice',
                    clientUrl('pages/payments.php')
                );
            }

            foreach (Database::fetchAll("SELECT id FROM users WHERE role = 'admin' AND status = 'active'") as $admin) {
                createNotification(
                    (int) $admin['id'],
                    'Overdue Invoice',
                    $message . ' (' . formatCurrency((float) ($invoice['total'] ?? 0)) . ')',
                    'invoice',
                    url('pages/payments.php')
                );
            }
        }
    }

    return count($overdueRows);
}

function getOverdueInvoices(int $limit = 50): array
{
    syncOverdueInvoices();
    $statusCol = invoiceStatusColumn();

    return Database::fetchAll(
        "SELECT i.*, i.{$statusCol} AS payment_status, cl.first_name, cl.last_name, cl.company_name,
                cs.case_number, cs.title AS case_title
         FROM invoices i
         JOIN clients cl ON cl.id = i.client_id
         LEFT JOIN cases cs ON cs.id = i.case_id
         WHERE i.{$statusCol} = 'overdue'
         ORDER BY i.due_date ASC
         LIMIT ?",
        [$limit]
    );
}

function createNotification(int $userId, string $title, string $message, string $type, ?string $link = null): void
{
    try {
        Database::insert(
            'INSERT INTO notifications (user_id, title, message, type, is_read, link, created_at) VALUES (?, ?, ?, ?, 0, ?, NOW())',
            [$userId, $title, $message, $type, $link]
        );
    } catch (Throwable $e) {
        // optional
    }
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $value;
}

function old(string $key, string $default = ''): string
{
    return e($_SESSION['old'][$key] ?? $default);
}

function setOld(array $data): void
{
    $_SESSION['old'] = $data;
}

function clearOld(): void
{
    unset($_SESSION['old']);
}

function getCurrencySettings(): array
{
    static $currency = null;

    if ($currency === null) {
        $config   = require __DIR__ . '/../config/config.php';
        $currency = $config['currency'] ?? [
            'code'   => 'INR',
            'symbol' => 'Rs',
            'locale' => 'en-IN',
        ];
    }

    return $currency;
}

function currencySymbol(): string
{
    return getCurrencySettings()['symbol'];
}

function formatCurrency(float $amount): string
{
    $symbol = currencySymbol();

    return $symbol . ' ' . number_format($amount, 2);
}

function invoiceStatusColumn(): string
{
    static $column = null;

    if ($column === null) {
        $column = Database::columnExists('invoices', 'payment_status') ? 'payment_status' : 'status';
    }

    return $column;
}

function paymentStatusColumn(): string
{
    static $column = null;

    if ($column === null) {
        $column = Database::columnExists('payments', 'payment_status') ? 'payment_status' : 'status';
    }

    return $column;
}

function appointmentStartColumn(): string
{
    static $column = null;

    if ($column === null) {
        $column = Database::columnExists('appointments', 'starts_at') ? 'starts_at' : 'start_time';
    }

    return $column;
}

function appointmentEndColumn(): string
{
    static $column = null;

    if ($column === null) {
        $column = Database::columnExists('appointments', 'ends_at') ? 'ends_at' : 'end_time';
    }

    return $column;
}

function appointmentStartSql(string $alias = ''): string
{
    return appointmentDateTimeSql('starts_at', 'start_time', $alias);
}

function appointmentEndSql(string $alias = ''): string
{
    return appointmentDateTimeSql('ends_at', 'end_time', $alias);
}

function appointmentDateTimeSql(string $primaryCol, string $fallbackCol, string $alias = ''): string
{
    $prefix = $alias !== '' ? "{$alias}." : '';
    $hasPrimary  = Database::columnExists('appointments', $primaryCol);
    $hasFallback = Database::columnExists('appointments', $fallbackCol);

    $isValid = static function (string $column) use ($prefix): string {
        $col = "{$prefix}{$column}";

        return "({$col} IS NOT NULL AND YEAR({$col}) > 0)";
    };

    if ($hasPrimary && $hasFallback) {
        return "CASE WHEN {$isValid($fallbackCol)} THEN {$prefix}{$fallbackCol} WHEN {$isValid($primaryCol)} THEN {$prefix}{$primaryCol} ELSE NULL END";
    }

    if ($hasPrimary) {
        return "CASE WHEN {$isValid($primaryCol)} THEN {$prefix}{$primaryCol} ELSE NULL END";
    }

    if ($hasFallback) {
        return "CASE WHEN {$isValid($fallbackCol)} THEN {$prefix}{$fallbackCol} ELSE NULL END";
    }

    return 'NULL';
}

function userDisplayNameSql(string $alias = 'u', string $as = 'name'): string
{
    if (Database::columnExists('users', 'name')) {
        return "{$alias}.name AS {$as}";
    }

    return "TRIM(CONCAT({$alias}.first_name, ' ', {$alias}.last_name)) AS {$as}";
}

function formatDate(?string $date, string $format = 'M d, Y'): string
{
    if (!$date) {
        return '—';
    }
    return date($format, strtotime($date));
}

function formatDateTime(?string $datetime, string $format = 'M d, Y g:i A'): string
{
    if (!$datetime) {
        return '—';
    }
    return date($format, strtotime($datetime));
}

function statusBadge(string $status): string
{
    $map = [
        'pending'            => 'badge-pending',
        'in_progress'        => 'badge-progress',
        'waiting_for_client' => 'badge-waiting',
        'completed'          => 'badge-completed',
        'closed'             => 'badge-closed',
        'paid'               => 'badge-paid',
        'partially_paid'     => 'badge-partial',
        'overdue'            => 'badge-overdue',
        'scheduled'          => 'badge-scheduled',
        'confirmed'          => 'badge-confirmed',
        'active'             => 'badge-paid',
        'inactive'           => 'badge-closed',
        'suspended'          => 'badge-overdue',
    ];

    $class = $map[$status] ?? 'badge-default';
    $label = ucwords(str_replace('_', ' ', $status));

    return sprintf('<span class="status-badge %s">%s</span>', $class, e($label));
}

function timeAgo(?string $datetime): string
{
    if (!$datetime) {
        return 'Recently';
    }

    $time  = strtotime($datetime);
    if ($time === false) {
        return 'Recently';
    }

    $diff  = time() - $time;

    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';

    return formatDate($datetime);
}

function getCompanySettings(): array
{
    return SettingsService::get();
}

function clearCompanySettingsCache(): void
{
    SettingsService::clearCache();
}

function getDashboardStats(): array
{
    syncOverdueInvoices();
    $invoiceStatus = invoiceStatusColumn();
    $paymentStatus = paymentStatusColumn();
    $appointmentStart = appointmentStartSql();
    $appointmentEnd   = appointmentEndSql();

    $totalClients = Database::fetch('SELECT COUNT(*) AS count FROM clients')['count'] ?? 0;

    $activeCases = Database::fetch(
        "SELECT COUNT(*) AS count FROM cases WHERE status IN ('pending', 'in_progress', 'waiting_for_client')"
    )['count'] ?? 0;

    $pendingInvoices = Database::fetch(
        "SELECT COUNT(*) AS count FROM invoices WHERE {$invoiceStatus} IN ('pending', 'overdue', 'partially_paid')"
    )['count'] ?? 0;

    $paidInvoices = Database::fetch(
        "SELECT COUNT(*) AS count FROM invoices WHERE {$invoiceStatus} = 'paid'"
    )['count'] ?? 0;

    $upcomingAppointments = Database::fetch(
        "SELECT COUNT(*) AS count FROM appointments
         WHERE status IN ('scheduled', 'confirmed')
           AND ({$appointmentStart} >= NOW() OR ({$appointmentEnd} IS NOT NULL AND {$appointmentEnd} >= NOW()))"
    )['count'] ?? 0;

    $totalRevenue = Database::fetch(
        "SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE {$paymentStatus} = 'completed'"
    )['total'] ?? 0;

    $monthlyRevenue = Database::fetch(
        "SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE {$paymentStatus} = 'completed' AND MONTH(paid_at) = MONTH(NOW()) AND YEAR(paid_at) = YEAR(NOW())"
    )['total'] ?? 0;

    return [
        'total_clients'         => (int) $totalClients,
        'active_cases'          => (int) $activeCases,
        'pending_invoices'      => (int) $pendingInvoices,
        'paid_invoices'         => (int) $paidInvoices,
        'upcoming_appointments' => (int) $upcomingAppointments,
        'total_revenue'         => (float) $totalRevenue,
        'monthly_revenue'       => (float) $monthlyRevenue,
    ];
}

function getRecentActivity(int $limit = 8): array
{
    return Database::fetchAll(
        'SELECT al.*, ' . userDisplayNameSql('u') . '
         FROM audit_logs al
         LEFT JOIN users u ON u.id = al.user_id
         ORDER BY al.created_at DESC
         LIMIT ?',
        [$limit]
    );
}

function businessActivityMeta(string $type): array
{
    $map = [
        'client_added'          => ['icon' => 'bi-person-plus', 'class' => 'act-teal'],
        'invoice_paid'          => ['icon' => 'bi-receipt-cutoff', 'class' => 'act-green'],
        'case_created'          => ['icon' => 'bi-briefcase', 'class' => 'act-purple'],
        'appointment_scheduled' => ['icon' => 'bi-calendar-event', 'class' => 'act-orange'],
        'document_uploaded'     => ['icon' => 'bi-file-earmark-arrow-up', 'class' => 'act-blue'],
        'payment_received'      => ['icon' => 'bi-cash-coin', 'class' => 'act-green'],
        'case_status_updated'   => ['icon' => 'bi-arrow-repeat', 'class' => 'act-purple'],
        'notification_sent'     => ['icon' => 'bi-bell', 'class' => 'act-teal'],
    ];

    return $map[$type] ?? ['icon' => 'bi-activity', 'class' => 'act-teal'];
}

function getBusinessActivityFeed(int $limit = 20): array
{
    $feed = [];

    try {
        $clients = Database::fetchAll(
            "SELECT first_name, last_name, company_name, created_at
             FROM clients
             ORDER BY created_at DESC
             LIMIT 8"
        );
        foreach ($clients as $row) {
            $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            if (!empty($row['company_name'])) {
                $name = $name !== '' ? "{$name} ({$row['company_name']})" : $row['company_name'];
            }
            $feed[] = [
                'type'       => 'client_added',
                'title'      => 'New client added',
                'detail'     => $name ?: 'Client profile created',
                'created_at' => $row['created_at'],
            ];
        }
    } catch (Throwable $e) {
        // Optional feed source
    }

    try {
        $cases = Database::fetchAll(
            "SELECT case_number, title, created_at
             FROM cases
             ORDER BY created_at DESC
             LIMIT 8"
        );
        foreach ($cases as $row) {
            $feed[] = [
                'type'       => 'case_created',
                'title'      => 'New case created',
                'detail'     => ($row['case_number'] ?? '') . ' · ' . ($row['title'] ?? 'Case'),
                'created_at' => $row['created_at'],
            ];
        }
    } catch (Throwable $e) {
        // Optional feed source
    }

    try {
        $invoices = Database::fetchAll(
            "SELECT invoice_number, total, updated_at, created_at
             FROM invoices
             WHERE payment_status = 'paid'
             ORDER BY updated_at DESC
             LIMIT 8"
        );
        foreach ($invoices as $row) {
            $feed[] = [
                'type'       => 'invoice_paid',
                'title'      => 'Invoice paid',
                'detail'     => ($row['invoice_number'] ?? 'Invoice') . ' · ' . formatCurrency((float) ($row['total'] ?? 0)),
                'created_at' => $row['updated_at'] ?? $row['created_at'],
            ];
        }
    } catch (Throwable $e) {
        // Optional feed source
    }

    try {
        $payments = Database::fetchAll(
            "SELECT p.amount, p.paid_at, p.created_at, i.invoice_number
             FROM payments p
             JOIN invoices i ON i.id = p.invoice_id
             WHERE p.payment_status = 'completed'
             ORDER BY COALESCE(p.paid_at, p.created_at) DESC
             LIMIT 8"
        );
        foreach ($payments as $row) {
            $feed[] = [
                'type'       => 'payment_received',
                'title'      => 'Payment received',
                'detail'     => formatCurrency((float) ($row['amount'] ?? 0)) . ' · ' . ($row['invoice_number'] ?? 'Invoice'),
                'created_at' => $row['paid_at'] ?? $row['created_at'],
            ];
        }
    } catch (Throwable $e) {
        // Optional feed source
    }

    try {
        $startSql = appointmentStartSql('a');
        $appointments = Database::fetchAll(
            "SELECT a.title, {$startSql} AS starts_at, a.created_at, c.first_name, c.last_name
             FROM appointments a
             JOIN clients c ON c.id = a.client_id
             ORDER BY a.created_at DESC
             LIMIT 8"
        );
        foreach ($appointments as $row) {
            $start = $row['starts_at'] ?? $row['created_at'];
            $feed[] = [
                'type'       => 'appointment_scheduled',
                'title'      => 'Appointment scheduled',
                'detail'     => ($row['title'] ?? 'Appointment') . ' · ' . clientFullName($row) . ' · ' . formatDateTime($start, 'M d, g:i A'),
                'created_at' => $row['created_at'],
            ];
        }
    } catch (Throwable $e) {
        // Optional feed source
    }

    try {
        $documents = Database::fetchAll(
            "SELECT d.original_name, d.file_name, d.created_at, cs.case_number
             FROM documents d
             LEFT JOIN cases cs ON cs.id = d.case_id
             ORDER BY d.created_at DESC
             LIMIT 8"
        );
        foreach ($documents as $row) {
            $fileName = $row['original_name'] ?? $row['file_name'] ?? 'Document';
            $caseRef  = !empty($row['case_number']) ? ' · ' . $row['case_number'] : '';
            $feed[] = [
                'type'       => 'document_uploaded',
                'title'      => 'Document uploaded',
                'detail'     => $fileName . $caseRef,
                'created_at' => $row['created_at'],
            ];
        }
    } catch (Throwable $e) {
        // Optional feed source
    }

    try {
        $caseUpdates = Database::fetchAll(
            "SELECT case_number, title, status, updated_at, created_at
             FROM cases
             WHERE updated_at > DATE_ADD(created_at, INTERVAL 2 MINUTE)
             ORDER BY updated_at DESC
             LIMIT 8"
        );
        foreach ($caseUpdates as $row) {
            $status = ucwords(str_replace('_', ' ', $row['status'] ?? 'updated'));
            $feed[] = [
                'type'       => 'case_status_updated',
                'title'      => 'Case status updated',
                'detail'     => ($row['case_number'] ?? 'Case') . ' · ' . $status,
                'created_at' => $row['updated_at'] ?? $row['created_at'],
            ];
        }
    } catch (Throwable $e) {
        // Optional feed source
    }

    try {
        $notifications = Database::fetchAll(
            "SELECT title, message, created_at
             FROM notifications
             ORDER BY created_at DESC
             LIMIT 8"
        );
        foreach ($notifications as $row) {
            $feed[] = [
                'type'       => 'notification_sent',
                'title'      => 'New notification sent',
                'detail'     => $row['title'] ?? mb_strimwidth($row['message'] ?? 'Notification', 0, 60, '…'),
                'created_at' => $row['created_at'],
            ];
        }
    } catch (Throwable $e) {
        // Optional feed source
    }

    usort($feed, static function (array $a, array $b): int {
        return strtotime($b['created_at']) <=> strtotime($a['created_at']);
    });

    $feed = array_slice($feed, 0, $limit);

    foreach ($feed as &$item) {
        $item['meta'] = businessActivityMeta($item['type']);
    }
    unset($item);

    return $feed;
}

function getRecentNotifications(int $userId, int $limit = 5, bool $unreadOnly = false): array
{
    $sql = 'SELECT * FROM notifications WHERE user_id = ?';
    $params = [$userId];

    if ($unreadOnly) {
        $sql .= ' AND is_read = 0';
    }

    $sql .= ' ORDER BY created_at DESC LIMIT ?';
    $params[] = $limit;

    return Database::fetchAll($sql, $params);
}

function markNotificationAsRead(int $id, int $userId): bool
{
    $stmt = Database::query(
        'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ? AND is_read = 0',
        [$id, $userId]
    );

    return $stmt->rowCount() > 0;
}

function markAllNotificationsAsRead(int $userId): void
{
    Database::query('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0', [$userId]);
}

function deleteNotification(int $id, int $userId): void
{
    Database::query('DELETE FROM notifications WHERE id = ? AND user_id = ?', [$id, $userId]);
}

function getAllNotifications(int $userId, int $limit = 100): array
{
    return Database::fetchAll(
        'SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?',
        [$userId, $limit]
    );
}

function getPendingInvoices(): array
{
    syncOverdueInvoices();
    $statusCol = invoiceStatusColumn();

    return Database::fetchAll(
        "SELECT i.*, i.{$statusCol} AS payment_status, cl.first_name, cl.last_name, cl.company_name, cs.case_number, cs.title AS case_title
         FROM invoices i
         JOIN clients cl ON cl.id = i.client_id
         LEFT JOIN cases cs ON cs.id = i.case_id
         WHERE i.{$statusCol} IN ('pending', 'overdue', 'partially_paid')
         ORDER BY i.due_date ASC, i.created_at DESC"
    );
}

function resolveNotificationRedirect(?string $link): string
{
    if ($link === null || trim($link) === '') {
        return 'pages/dashboard.php';
    }

    $link = trim($link);

    if (str_starts_with($link, 'http://') || str_starts_with($link, 'https://')) {
        $config = require __DIR__ . '/../config/config.php';
        $base   = rtrim($config['app_url'], '/');

        if (str_starts_with($link, $base . '/')) {
            return ltrim(substr($link, strlen($base)), '/');
        }

        return $link;
    }

    $link = ltrim($link, '/');

    if (str_starts_with($link, 'admin/pages/')) {
        return substr($link, 6);
    }

    return $link;
}

function notificationRedirectTarget(array $notif): string
{
    $target = resolveNotificationRedirect($notif['link'] ?? null);
    $type   = $notif['type'] ?? '';

    if ($type === 'invoice') {
        if ($target === 'pages/dashboard.php') {
            return 'pages/payments.php';
        }

        $target = str_replace(['#invoices', '#payments'], '#invoice-payments', $target);

        if (str_contains($target, 'case-view.php') && !str_contains($target, '#')) {
            $target .= '#invoice-payments';
        }
    }

    if ($type === 'payment') {
        $target = str_replace('#payments', '#invoice-payments', $target);
    }

    return $target;
}

function getUnreadNotificationCount(int $userId): int
{
    return (int) (Database::fetch(
        'SELECT COUNT(*) AS count FROM notifications WHERE user_id = ? AND is_read = 0',
        [$userId]
    )['count'] ?? 0);
}

function getUpcomingAppointments(int $limit = 5): array
{
    $startSql = appointmentStartSql('a');
    $endSql   = appointmentEndSql('a');

    return Database::fetchAll(
        "SELECT a.*, {$startSql} AS start_time, {$endSql} AS end_time,
                c.company_name, cu.first_name, cu.last_name
         FROM appointments a
         JOIN clients c ON c.id = a.client_id
         JOIN users cu ON cu.id = c.user_id
         WHERE a.status IN ('scheduled', 'confirmed')
           AND ({$startSql} >= NOW() OR ({$endSql} IS NOT NULL AND {$endSql} >= NOW()))
         ORDER BY {$startSql} ASC
         LIMIT ?",
        [$limit]
    );
}

function getRecentCases(int $limit = 5): array
{
    return Database::fetchAll(
        "SELECT cs.*, cu.first_name, cu.last_name, cl.company_name
         FROM cases cs
         JOIN clients cl ON cl.id = cs.client_id
         JOIN users cu ON cu.id = cl.user_id
         ORDER BY cs.updated_at DESC
         LIMIT ?",
        [$limit]
    );
}

function getRevenueChartData(): array
{
    $paymentStatus = paymentStatusColumn();

    $rows = Database::fetchAll(
        "SELECT DATE_FORMAT(paid_at, '%b') AS month_label,
                MONTH(paid_at) AS month_num,
                COALESCE(SUM(amount), 0) AS total
         FROM payments
         WHERE {$paymentStatus} = 'completed' AND paid_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
         GROUP BY MONTH(paid_at), DATE_FORMAT(paid_at, '%b')
         ORDER BY month_num ASC"
    );

    $months  = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    $data    = array_fill(0, 6, 0);
    $labels  = [];

    for ($i = 5; $i >= 0; $i--) {
        $monthIndex = (int) date('n', strtotime("-{$i} months")) - 1;
        $labels[]   = $months[$monthIndex];
    }

    foreach ($rows as $row) {
        $idx = array_search($row['month_label'], $labels);
        if ($idx !== false) {
            $data[$idx] = (float) $row['total'];
        }
    }

    return ['labels' => $labels, 'data' => $data];
}

function getInvoiceChartData(): array
{
    $rows = Database::fetchAll(
        "SELECT DATE_FORMAT(created_at, '%b') AS month_label,
                MONTH(created_at) AS month_num,
                COALESCE(SUM(total), 0) AS total
         FROM invoices
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
         GROUP BY MONTH(created_at), DATE_FORMAT(created_at, '%b')
         ORDER BY month_num ASC"
    );

    $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    $data   = array_fill(0, 6, 0);
    $labels = [];

    for ($i = 5; $i >= 0; $i--) {
        $monthIndex = (int) date('n', strtotime("-{$i} months")) - 1;
        $labels[]   = $months[$monthIndex];
    }

    foreach ($rows as $row) {
        $idx = array_search($row['month_label'], $labels);
        if ($idx !== false) {
            $data[$idx] = (float) $row['total'];
        }
    }

    return ['labels' => $labels, 'data' => $data];
}

function getWeeklyPaymentsChartData(): array
{
    $paymentStatus = paymentStatusColumn();
    $labels    = [];
    $payments  = array_fill(0, 7, 0.0);
    $invoices  = array_fill(0, 7, 0.0);

    for ($i = 6; $i >= 0; $i--) {
        $labels[] = date('D j', strtotime("-{$i} days"));
    }

    $rows = Database::fetchAll(
        "SELECT DATE(paid_at) AS day_date, COALESCE(SUM(amount), 0) AS total
         FROM payments
         WHERE {$paymentStatus} = 'completed'
           AND paid_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
         GROUP BY DATE(paid_at)"
    );

    foreach ($rows as $row) {
        $idx = sparklineDayIndex($row['day_date']);
        if ($idx !== null) {
            $payments[$idx] = (float) $row['total'];
        }
    }

    $invoiceRows = Database::fetchAll(
        "SELECT DATE(created_at) AS day_date, COALESCE(SUM(total), 0) AS total
         FROM invoices
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
         GROUP BY DATE(created_at)"
    );

    foreach ($invoiceRows as $row) {
        $idx = sparklineDayIndex($row['day_date']);
        if ($idx !== null) {
            $invoices[$idx] = (float) $row['total'];
        }
    }

    return [
        'labels'   => $labels,
        'payments' => $payments,
        'invoices' => $invoices,
    ];
}

function chartSeriesHasData(array $series): bool
{
    foreach ($series as $value) {
        if ((float) $value > 0) {
            return true;
        }
    }

    return false;
}

function getDashboardTrends(array $stats): array
{
    $paymentStatus = paymentStatusColumn();

    $lastMonthRevenue = (float) (Database::fetch(
        "SELECT COALESCE(SUM(amount), 0) AS total FROM payments
         WHERE {$paymentStatus} = 'completed'
           AND MONTH(paid_at) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH))
           AND YEAR(paid_at) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH))"
    )['total'] ?? 0);

    $revenueTrend = $lastMonthRevenue > 0
        ? round((($stats['monthly_revenue'] - $lastMonthRevenue) / $lastMonthRevenue) * 100, 2)
        : ($stats['monthly_revenue'] > 0 ? 100 : 0);

    $lastMonthCases = (int) (Database::fetch(
        "SELECT COUNT(*) AS count FROM cases
         WHERE MONTH(created_at) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH))
           AND YEAR(created_at) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH))"
    )['count'] ?? 0);

    $thisMonthCases = (int) (Database::fetch(
        "SELECT COUNT(*) AS count FROM cases
         WHERE MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW())"
    )['count'] ?? 0);

    $casesTrend = $lastMonthCases > 0
        ? round((($thisMonthCases - $lastMonthCases) / $lastMonthCases) * 100, 2)
        : ($thisMonthCases > 0 ? 100 : 0);

    return [
        'clients'  => ['value' => 2.5, 'up' => true],
        'cases'    => ['value' => abs($casesTrend), 'up' => $casesTrend >= 0],
        'invoices' => ['value' => 1.2, 'up' => false],
        'revenue'  => ['value' => abs($revenueTrend), 'up' => $revenueTrend >= 0],
    ];
}

function sparklineDayIndex(string $date): ?int
{
    $target = strtotime(date('Y-m-d', strtotime($date)));
    $today  = strtotime('today');
    $diff   = (int) round(($today - $target) / 86400);

    if ($diff < 0 || $diff > 6) {
        return null;
    }

    return 6 - $diff;
}

function getLast7DaysSparklineData(): array
{
    $labels = [];
    for ($i = 6; $i >= 0; $i--) {
        $labels[] = date('M j', strtotime("-{$i} days"));
    }

    $clients      = array_fill(0, 7, 0);
    $cases        = array_fill(0, 7, 0);
    $invoices     = array_fill(0, 7, 0);
    $paidInvoices = array_fill(0, 7, 0);
    $payments     = array_fill(0, 7, 0.0);

    foreach (Database::fetchAll(
        "SELECT DATE(created_at) AS day_date, COUNT(*) AS total
         FROM clients
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
         GROUP BY DATE(created_at)"
    ) as $row) {
        $idx = sparklineDayIndex($row['day_date']);
        if ($idx !== null) {
            $clients[$idx] = (int) $row['total'];
        }
    }

    foreach (Database::fetchAll(
        "SELECT DATE(created_at) AS day_date, COUNT(*) AS total
         FROM cases
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
         GROUP BY DATE(created_at)"
    ) as $row) {
        $idx = sparklineDayIndex($row['day_date']);
        if ($idx !== null) {
            $cases[$idx] = (int) $row['total'];
        }
    }

    foreach (Database::fetchAll(
        "SELECT DATE(created_at) AS day_date, COUNT(*) AS total
         FROM invoices
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
         GROUP BY DATE(created_at)"
    ) as $row) {
        $idx = sparklineDayIndex($row['day_date']);
        if ($idx !== null) {
            $invoices[$idx] = (int) $row['total'];
        }
    }

    foreach (Database::fetchAll(
        "SELECT DATE(updated_at) AS day_date, COUNT(*) AS total
         FROM invoices
         WHERE payment_status = 'paid'
           AND updated_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
         GROUP BY DATE(updated_at)"
    ) as $row) {
        $idx = sparklineDayIndex($row['day_date']);
        if ($idx !== null) {
            $paidInvoices[$idx] = (int) $row['total'];
        }
    }

    foreach (Database::fetchAll(
        "SELECT DATE(paid_at) AS day_date, COALESCE(SUM(amount), 0) AS total
         FROM payments
         WHERE payment_status = 'completed'
           AND paid_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
         GROUP BY DATE(paid_at)"
    ) as $row) {
        $idx = sparklineDayIndex($row['day_date']);
        if ($idx !== null) {
            $payments[$idx] = (float) $row['total'];
        }
    }

    return [
        'labels'        => $labels,
        'clients'       => $clients,
        'cases'         => $cases,
        'invoices'      => $invoices,
        'paid_invoices' => $paidInvoices,
        'payments'      => $payments,
    ];
}

function kpiTrendBadge(array $trend, bool $inline = false): string
{
    $value = (float) ($trend['value'] ?? 0);
    $up    = (bool) ($trend['up'] ?? true);

    if ($value == 0.0) {
        $class = 'neutral';
        $arrow = '→';
    } elseif ($up) {
        $class = 'up';
        $arrow = '↑';
    } else {
        $class = 'down';
        $arrow = '↓';
    }

    $inlineClass = $inline ? ' kpi-trend-inline' : '';
    $formatted   = number_format($value, fmod($value, 1.0) === 0.0 ? 0 : 1);

    return sprintf(
        '<span class="kpi-trend %s%s">%s%% %s</span>',
        $class,
        $inlineClass,
        $formatted,
        $arrow
    );
}

function notificationIcon(string $type): string
{
    $icons = [
        'invoice'     => 'bi-receipt',
        'payment'     => 'bi-credit-card',
        'appointment' => 'bi-calendar-event',
        'document'    => 'bi-file-earmark',
        'case'        => 'bi-briefcase',
        'account'     => 'bi-person-plus',
        'system'      => 'bi-bell',
    ];

    return $icons[$type] ?? 'bi-bell';
}

function priorityBadge(string $priority): string
{
    $map = [
        'low'    => 'badge-default',
        'medium' => 'badge-scheduled',
        'high'   => 'badge-partial',
        'urgent' => 'badge-overdue',
    ];

    $class = $map[$priority] ?? 'badge-default';
    return sprintf('<span class="status-badge %s">%s</span>', $class, e(ucfirst($priority)));
}

function paymentStatusBadge(string $status): string
{
    $map = [
        'pending'   => 'badge-pending',
        'completed' => 'badge-paid',
        'failed'    => 'badge-overdue',
        'refunded'  => 'badge-closed',
    ];

    $labels = [
        'pending'   => 'Pending',
        'completed' => 'Completed',
        'failed'    => 'Failed',
        'refunded'  => 'Refunded',
    ];

    $class = $map[$status] ?? 'badge-default';
    $label = $labels[$status] ?? ucfirst(str_replace('_', ' ', $status));

    return sprintf('<span class="status-badge %s">%s</span>', $class, e($label));
}

function paymentMethodBadge(string $method): string
{
    $map = [
        'stripe'         => ['badge-scheduled', 'Stripe'],
        'bank_transfer'  => ['badge-default', 'Bank Transfer'],
        'cash'           => ['badge-default', 'Cash'],
        'check'          => ['badge-default', 'Check'],
        'other'          => ['badge-default', 'Other'],
    ];

    [$class, $label] = $map[$method] ?? ['badge-default', ucwords(str_replace('_', ' ', $method))];

    return sprintf('<span class="status-badge %s">%s</span>', $class, e($label));
}

function getAllClients(): array
{
    return Database::fetchAll(
        'SELECT c.*, c.status AS user_status,
                (SELECT COUNT(*) FROM cases cs WHERE cs.client_id = c.id) AS case_count
         FROM clients c
         ORDER BY c.last_name ASC, c.first_name ASC'
    );
}

function getAllCases(): array
{
    return Database::fetchAll(
        "SELECT cs.*, cl.first_name, cl.last_name, cl.email, cl.company_name,
                adm.name AS admin_name
         FROM cases cs
         JOIN clients cl ON cl.id = cs.client_id
         LEFT JOIN users adm ON adm.id = cs.assigned_admin_id
         ORDER BY cs.updated_at DESC"
    );
}

function getAllPayments(): array
{
    syncOverdueInvoices();
    $paymentCol = paymentStatusColumn();

    return Database::fetchAll(
        "SELECT p.*, p.{$paymentCol} AS payment_status, i.invoice_number, i.total AS invoice_total, i.case_id,
                cl.first_name, cl.last_name, cl.company_name,
                r.id AS receipt_id, r.receipt_number
         FROM payments p
         JOIN invoices i ON i.id = p.invoice_id
         JOIN clients cl ON cl.id = i.client_id
         LEFT JOIN receipts r ON r.payment_id = p.id
         ORDER BY COALESCE(p.paid_at, p.created_at) DESC"
    );
}

function getAllAppointments(): array
{
    $startSql = appointmentStartSql('a');
    $endSql   = appointmentEndSql('a');

    return Database::fetchAll(
        "SELECT a.*, {$startSql} AS start_time, {$endSql} AS end_time,
                cl.first_name, cl.last_name, cl.company_name, cs.case_number
         FROM appointments a
         JOIN clients cl ON cl.id = a.client_id
         LEFT JOIN cases cs ON cs.id = a.case_id
         ORDER BY {$startSql} DESC"
    );
}

function getChatbotContext(): array
{
    $stats = getDashboardStats();

    $recentCases = Database::fetchAll(
        "SELECT case_number, title, status FROM cases ORDER BY updated_at DESC LIMIT 5"
    );

    $pendingPayments = Database::fetch(
        "SELECT COUNT(*) AS count FROM invoices WHERE payment_status IN ('pending', 'overdue', 'partially_paid')"
    )['count'] ?? 0;

    $startSql = appointmentStartSql();
    $endSql   = appointmentEndSql();
    $nextAppointment = Database::fetch(
        "SELECT title, {$startSql} AS start_time FROM appointments
         WHERE status IN ('scheduled', 'confirmed')
           AND ({$startSql} >= NOW() OR ({$endSql} IS NOT NULL AND {$endSql} >= NOW()))
         ORDER BY {$startSql} ASC LIMIT 1"
    );

    return [
        'stats'           => $stats,
        'recent_cases'    => $recentCases,
        'pending_payments'=> (int) $pendingPayments,
        'next_appointment'=> $nextAppointment,
    ];
}

function getActiveCasesForChat(int $limit = 10): array
{
    return Database::fetchAll(
        "SELECT cs.case_number, cs.title, cs.status, cl.first_name, cl.last_name, cl.company_name
         FROM cases cs
         JOIN clients cl ON cl.id = cs.client_id
         WHERE cs.status IN ('pending', 'in_progress', 'waiting_for_client')
         ORDER BY cs.updated_at DESC
         LIMIT ?",
        [$limit]
    );
}

function formatChatbotCaseList(array $cases, string $heading): string
{
    if ($cases === []) {
        return 'No cases found matching that request.';
    }

    $lines = [$heading . ' (' . count($cases) . ')', ''];

    foreach ($cases as $case) {
        $status = ucwords(str_replace('_', ' ', $case['status'] ?? 'unknown'));
        $client = clientFullName($case);
        $lines[] = "• **{$case['case_number']}** — {$case['title']} (*{$status}*) — {$client}";
    }

    return implode("\n", $lines);
}

function chatbotWantsList(string $message): bool
{
    return (bool) preg_match('/\b(list|show|display|give me|name|which|tell me about)\b/', $message);
}

function chatbotWantsCount(string $message): bool
{
    return (bool) preg_match('/\b(how many|number of|count of|total number)\b/', $message);
}

function chatbotIsFollowUpList(string $message): bool
{
    return (bool) preg_match(
        '/^(list them|show them|list those|show those|name them|what are they|list it|show it|go on|continue|more details|details please|yes list|yes show)$/',
        $message
    );
}

function generateChatbotReply(string $message): string
{
    $message = strtolower(trim($message));
    $ctx = getChatbotContext();
    $stats = $ctx['stats'];
    $lastTopic = $_SESSION['chatbot_last_topic'] ?? null;

    if ($message === '' || preg_match('/^(hi|hello|hey)$/', $message)) {
        return "Hello! I'm your Notary Admin Assistant. I can help with:\n\n"
            . "• **Clients** — ask \"how many clients\" or \"list clients\"\n"
            . "• **Cases** — ask \"how many active cases\" or \"list active cases\"\n"
            . "• **Payments** — ask \"total revenue\" or \"list recent payments\"\n"
            . "• **Appointments** — ask \"upcoming appointments\" or \"next appointment\"\n\n"
            . "What would you like to know?";
    }

    if ($message === 'help') {
        return "Hello! I'm your Notary Admin Assistant. I can help with:\n\n"
            . "• **Clients** — ask \"how many clients\" or \"list clients\"\n"
            . "• **Cases** — ask \"how many active cases\" or \"list active cases\"\n"
            . "• **Payments** — ask \"total revenue\" or \"list recent payments\"\n"
            . "• **Appointments** — ask \"upcoming appointments\" or \"next appointment\"\n\n"
            . "After a count, you can say **list them** to see the items.";
    }

    if (chatbotIsFollowUpList($message) && $lastTopic) {
        switch ($lastTopic) {
            case 'active_cases':
                $_SESSION['chatbot_last_topic'] = 'active_cases';
                return formatChatbotCaseList(getActiveCasesForChat(), '**Active cases:**');
            case 'clients':
                $_SESSION['chatbot_last_topic'] = 'clients';
                $clients = getAllClients();
                if ($clients === []) {
                    return 'No clients found in the system.';
                }
                $lines = ['Here are your **' . count($clients) . ' clients**:', ''];
                foreach (array_slice($clients, 0, 10) as $client) {
                    $name = clientFullName($client);
                    $company = $client['company_name'] ? " ({$client['company_name']})" : '';
                    $lines[] = "• {$name}{$company} — {$client['case_count']} case(s)";
                }
                return implode("\n", $lines);
            case 'cases':
                $_SESSION['chatbot_last_topic'] = 'cases';
                return formatChatbotCaseList(
                    Database::fetchAll(
                        "SELECT cs.case_number, cs.title, cs.status, cl.first_name, cl.last_name, cl.company_name
                         FROM cases cs
                         JOIN clients cl ON cl.id = cs.client_id
                         ORDER BY cs.updated_at DESC
                         LIMIT 10"
                    ),
                    '**Recent cases:**'
                );
            case 'payments':
                $_SESSION['chatbot_last_topic'] = 'payments';
                $payments = getAllPayments();
                if ($payments === []) {
                    return 'No payments recorded yet.';
                }
                $lines = ['**Recent payments:**', ''];
                foreach (array_slice($payments, 0, 8) as $payment) {
                    $name = clientFullName($payment);
                    $status = ucfirst(paymentStatusValue($payment));
                    $lines[] = '• ' . formatCurrency((float) $payment['amount']) . " from {$name} — {$payment['invoice_number']} (*{$status}*)";
                }
                return implode("\n", $lines);
            case 'appointments':
                $_SESSION['chatbot_last_topic'] = 'appointments';
                $appointments = getUpcomingAppointments(8);
                if ($appointments === []) {
                    return 'No upcoming appointments scheduled.';
                }
                $lines = ['**Upcoming appointments:**', ''];
                foreach ($appointments as $appointment) {
                    $start = appointmentStart($appointment) ?? $appointment['start_time'] ?? null;
                    $client = clientFullName($appointment);
                    $lines[] = '• **' . ($appointment['title'] ?? 'Appointment') . '** — '
                        . ($start ? formatDateTime($start) : 'TBD') . " — {$client}";
                }
                return implode("\n", $lines);
        }
    }

    if (preg_match('/client/', $message)) {
        if (chatbotWantsList($message) || preg_match('/\blist client|\bshow client|\ball client/', $message)) {
            $_SESSION['chatbot_last_topic'] = 'clients';
            $clients = getAllClients();
            if ($clients === []) {
                return 'No clients found in the system.';
            }
            $lines = ['Here are your **' . count($clients) . ' clients**:', ''];
            foreach (array_slice($clients, 0, 10) as $client) {
                $name = clientFullName($client);
                $company = $client['company_name'] ? " ({$client['company_name']})" : '';
                $lines[] = "• {$name}{$company} — {$client['case_count']} case(s)";
            }
            return implode("\n", $lines);
        }

        if (chatbotWantsCount($message) || preg_match('/how many client|total client|client count|number of client/', $message)) {
            $_SESSION['chatbot_last_topic'] = 'clients';
            return "You currently have **{$stats['total_clients']} registered clients** in the system.";
        }
    }

    if (preg_match('/active case|open case|in progress case|pending case/', $message)) {
        if (chatbotWantsList($message) || preg_match('/\blist active|\bshow active|\blist case|\bshow case/', $message)) {
            $_SESSION['chatbot_last_topic'] = 'active_cases';
            return formatChatbotCaseList(getActiveCasesForChat(), '**Active cases:**');
        }

        $_SESSION['chatbot_last_topic'] = 'active_cases';
        return "There are **{$stats['active_cases']} active cases** currently in progress or pending action.";
    }

    if (preg_match('/case/', $message)) {
        if (chatbotWantsList($message) || preg_match('/\blist case|\bshow case|\ball case|recent case/', $message)) {
            $_SESSION['chatbot_last_topic'] = 'cases';
            return formatChatbotCaseList(
                Database::fetchAll(
                    "SELECT cs.case_number, cs.title, cs.status, cl.first_name, cl.last_name, cl.company_name
                     FROM cases cs
                     JOIN clients cl ON cl.id = cs.client_id
                     ORDER BY cs.updated_at DESC
                     LIMIT 10"
                ),
                '**Recent cases:**'
            );
        }

        if (chatbotWantsCount($message)) {
            $_SESSION['chatbot_last_topic'] = 'cases';
            $totalCases = (int) (Database::fetch('SELECT COUNT(*) AS c FROM cases')['c'] ?? 0);
            return "You have **{$totalCases} cases** in total, with **{$stats['active_cases']} active**.";
        }
    }

    if (preg_match('/revenue|total payment|payment total|earnings|income/', $message) && !chatbotWantsList($message)) {
        $_SESSION['chatbot_last_topic'] = 'revenue';
        return "**Revenue summary:**\n\n"
            . "• Total revenue: " . formatCurrency($stats['total_revenue']) . "\n"
            . "• This month: " . formatCurrency($stats['monthly_revenue']) . "\n"
            . "• Paid invoices: {$stats['paid_invoices']}";
    }

    if (preg_match('/pending invoice|unpaid|outstanding/', $message)) {
        $_SESSION['chatbot_last_topic'] = 'payments';
        return "You have **{$stats['pending_invoices']} pending invoices** and **{$ctx['pending_payments']} invoices** awaiting payment follow-up.";
    }

    if (preg_match('/payment/', $message)) {
        if (chatbotWantsList($message) || preg_match('/\blist payment|\bshow payment|\brecent payment|\ball payment/', $message)) {
            $_SESSION['chatbot_last_topic'] = 'payments';
            $payments = getAllPayments();
            if ($payments === []) {
                return 'No payments recorded yet.';
            }
            $lines = ['**Recent payments:**', ''];
            foreach (array_slice($payments, 0, 8) as $payment) {
                $name = clientFullName($payment);
                $status = ucfirst(paymentStatusValue($payment));
                $lines[] = '• ' . formatCurrency((float) $payment['amount']) . " from {$name} — {$payment['invoice_number']} (*{$status}*)";
            }
            return implode("\n", $lines);
        }
    }

    if (preg_match('/appointment|schedule/', $message)) {
        if (chatbotWantsList($message) || preg_match('/\blist appointment|\bshow appointment|\bupcoming appointment/', $message)) {
            $_SESSION['chatbot_last_topic'] = 'appointments';
            $appointments = getUpcomingAppointments(8);
            if ($appointments === []) {
                return 'No upcoming appointments scheduled.';
            }
            $lines = ['**Upcoming appointments:**', ''];
            foreach ($appointments as $appointment) {
                $start = appointmentStart($appointment) ?? $appointment['start_time'] ?? null;
                $client = clientFullName($appointment);
                $lines[] = '• **' . ($appointment['title'] ?? 'Appointment') . '** — '
                    . ($start ? formatDateTime($start) : 'TBD') . " — {$client}";
            }
            return implode("\n", $lines);
        }

        $_SESSION['chatbot_last_topic'] = 'appointments';
        if ($ctx['next_appointment']) {
            $appt = $ctx['next_appointment'];
            return "**Next appointment:** {$appt['title']} on " . formatDateTime($appt['start_time']) . '.';
        }

        return "You have **{$stats['upcoming_appointments']} upcoming appointments** scheduled. No future appointments found in the calendar.";
    }

    if (preg_match('/dashboard|summary|overview|status/', $message)) {
        $_SESSION['chatbot_last_topic'] = 'dashboard';
        return "**Dashboard overview:**\n\n"
            . "• Clients: {$stats['total_clients']}\n"
            . "• Active cases: {$stats['active_cases']}\n"
            . "• Pending invoices: {$stats['pending_invoices']}\n"
            . "• Upcoming appointments: {$stats['upcoming_appointments']}\n"
            . "• Total revenue: " . formatCurrency($stats['total_revenue']);
    }

    return "I'm not sure about that. Try asking about **clients**, **cases**, **payments**, **appointments**, or type **help** for available commands.";
}

function getClientDashboardStats(int $clientId): array
{
    $activeCases = (int) (Database::fetch(
        "SELECT COUNT(*) AS c FROM cases WHERE client_id = ? AND status IN ('pending','in_progress','waiting_for_client')",
        [$clientId]
    )['c'] ?? 0);

    $pendingInvoices = (int) (Database::fetch(
        'SELECT COUNT(*) AS c FROM invoices WHERE client_id = ? AND ' . invoiceStatusColumn() . " IN ('pending','overdue','partially_paid')",
        [$clientId]
    )['c'] ?? 0);

    $documents = (int) (Database::fetch(
        'SELECT COUNT(*) AS c FROM documents d JOIN cases cs ON cs.id = d.case_id WHERE cs.client_id = ?',
        [$clientId]
    )['c'] ?? 0);

    return [
        'active_cases'          => $activeCases,
        'pending_invoices'      => $pendingInvoices,
        'documents'             => $documents,
        'upcoming_appointments' => count(getClientUpcomingAppointments($clientId, 1000)),
    ];
}

function getClientCases(int $clientId): array
{
    return Database::fetchAll(
        'SELECT c.*,
                (SELECT COUNT(*) FROM documents d WHERE d.case_id = c.id) AS document_count
         FROM cases c
         WHERE c.client_id = ?
         ORDER BY c.updated_at DESC',
        [$clientId]
    );
}

function getClientRecentCases(int $clientId, int $limit = 5): array
{
    return Database::fetchAll(
        'SELECT * FROM cases WHERE client_id = ? ORDER BY updated_at DESC LIMIT ?',
        [$clientId, $limit]
    );
}

function getClientUpcomingAppointments(int $clientId, int $limit = 5): array
{
    $appointments = getClientAppointments($clientId);
    $visible = [];

    foreach ($appointments as $appointment) {
        if (!isClientScheduledAppointment($appointment)) {
            continue;
        }

        $appointment['start_time'] = appointmentEffectiveStart($appointment);
        $appointment['end_time']   = appointmentEffectiveEnd($appointment);
        $visible[] = $appointment;
    }

    usort($visible, static function (array $a, array $b): int {
        $aStart = strtotime(appointmentEffectiveStart($a) ?? '');
        $bStart = strtotime(appointmentEffectiveStart($b) ?? '');
        $now    = time();

        $aActive = isUpcomingAppointment($a);
        $bActive = isUpcomingAppointment($b);

        if ($aActive !== $bActive) {
            return $bActive <=> $aActive;
        }

        return $aStart <=> $bStart;
    });

    return array_slice($visible, 0, $limit);
}

function getClientAppointments(int $clientId): array
{
    $startSql = appointmentStartSql('a');
    $endSql   = appointmentEndSql('a');

    return Database::fetchAll(
        "SELECT a.*, {$startSql} AS start_time, {$endSql} AS end_time
         FROM appointments a
         WHERE a.client_id = ?
         ORDER BY {$startSql} DESC",
        [$clientId]
    );
}

function getClientInvoices(int $clientId): array
{
    syncOverdueInvoices();
    $statusCol = invoiceStatusColumn();

    return Database::fetchAll(
        "SELECT i.*, i.{$statusCol} AS payment_status, cs.case_number, cs.title AS case_title
         FROM invoices i
         LEFT JOIN cases cs ON cs.id = i.case_id
         WHERE i.client_id = ?
         ORDER BY i.created_at DESC",
        [$clientId]
    );
}

function getClientPayments(int $clientId): array
{
    $paymentStatus = paymentStatusColumn();

    return Database::fetchAll(
        "SELECT p.*, p.{$paymentStatus} AS payment_status, i.invoice_number, i.total AS invoice_total,
                r.id AS receipt_id, r.receipt_number
         FROM payments p
         JOIN invoices i ON i.id = p.invoice_id
         LEFT JOIN receipts r ON r.payment_id = p.id
         WHERE i.client_id = ?
         ORDER BY COALESCE(p.paid_at, p.created_at) DESC",
        [$clientId]
    );
}

function clientNotificationRedirectTarget(array $notif): string
{
    $target = resolveNotificationRedirect($notif['link'] ?? null);

    if (str_starts_with($target, 'pages/case-view.php')) {
        return $target;
    }

    if (str_starts_with($target, 'pages/appointments.php')) {
        return 'pages/appointments.php';
    }

    if (str_starts_with($target, 'pages/payments.php') || ($notif['type'] ?? '') === 'invoice' || ($notif['type'] ?? '') === 'payment') {
        return 'pages/payments.php';
    }

    if (str_starts_with($target, 'http://') || str_starts_with($target, 'https://')) {
        return $target;
    }

    return 'pages/dashboard.php';
}

function caseActivityIcon(string $type): string
{
    $map = [
        'case_created' => 'bi-briefcase',
        'document'     => 'bi-file-earmark-arrow-up',
        'invoice'      => 'bi-receipt',
        'payment'      => 'bi-cash-coin',
        'proposal'     => 'bi-file-text',
        'quotation'    => 'bi-file-earmark-text',
        'note'         => 'bi-journal-text',
        'status'       => 'bi-arrow-repeat',
        'appointment'  => 'bi-calendar-event',
        'update'       => 'bi-pencil-square',
    ];

    return $map[$type] ?? 'bi-activity';
}

function caseActivityTone(string $type): string
{
    $map = [
        'case_created' => 'tone-teal',
        'document'     => 'tone-blue',
        'invoice'      => 'tone-orange',
        'payment'      => 'tone-green',
        'proposal'     => 'tone-purple',
        'quotation'    => 'tone-teal',
        'note'         => 'tone-gray',
        'status'       => 'tone-indigo',
        'appointment'  => 'tone-blue',
        'update'       => 'tone-gray',
    ];

    return $map[$type] ?? 'tone-gray';
}

function caseActivityDateLabel(string $datetime): string
{
    $time = strtotime($datetime);
    $today = strtotime('today');
    $yesterday = strtotime('yesterday');

    if ($time >= $today) {
        return 'Today';
    }

    if ($time >= $yesterday) {
        return 'Yesterday';
    }

    return date('F j, Y', $time);
}

function passwordStrengthError(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        return 'Password must contain at least one uppercase letter.';
    }

    if (!preg_match('/[a-z]/', $password)) {
        return 'Password must contain at least one lowercase letter.';
    }

    if (!preg_match('/[0-9]/', $password)) {
        return 'Password must contain at least one number.';
    }

    return null;
}
