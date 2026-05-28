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

function appointmentStart(array $appointment): ?string
{
    return $appointment['starts_at'] ?? $appointment['start_time'] ?? null;
}

function appointmentEnd(array $appointment): ?string
{
    return $appointment['ends_at'] ?? $appointment['end_time'] ?? null;
}

function paymentStatusValue(array $payment): string
{
    return $payment['payment_status'] ?? $payment['status'] ?? 'pending';
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
    static $settings = null;

    if ($settings === null) {
        $settings = Database::fetch('SELECT * FROM company_settings LIMIT 1') ?? [
            'company_name'    => 'Notary Management',
            'primary_color'   => '#3aafa9',
            'secondary_color' => '#00182c',
            'dark_accent'     => '#000000',
            'font_family'     => 'Montserrat',
            'logo'            => null,
        ];
    }

    return $settings;
}

function getDashboardStats(): array
{
    $totalClients = Database::fetch('SELECT COUNT(*) AS count FROM clients')['count'] ?? 0;

    $activeCases = Database::fetch(
        "SELECT COUNT(*) AS count FROM cases WHERE status IN ('pending', 'in_progress', 'waiting_for_client')"
    )['count'] ?? 0;

    $pendingInvoices = Database::fetch(
        "SELECT COUNT(*) AS count FROM invoices WHERE payment_status IN ('pending', 'overdue', 'partially_paid')"
    )['count'] ?? 0;

    $paidInvoices = Database::fetch(
        "SELECT COUNT(*) AS count FROM invoices WHERE payment_status = 'paid'"
    )['count'] ?? 0;

    $upcomingAppointments = Database::fetch(
        "SELECT COUNT(*) AS count FROM appointments WHERE starts_at >= NOW() AND status IN ('scheduled', 'confirmed')"
    )['count'] ?? 0;

    $totalRevenue = Database::fetch(
        "SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE payment_status = 'completed'"
    )['total'] ?? 0;

    $monthlyRevenue = Database::fetch(
        "SELECT COALESCE(SUM(amount), 0) AS total FROM payments WHERE payment_status = 'completed' AND MONTH(paid_at) = MONTH(NOW()) AND YEAR(paid_at) = YEAR(NOW())"
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
        'SELECT al.*, u.name
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
        $appointments = Database::fetchAll(
            "SELECT a.title, a.starts_at, a.created_at, c.first_name, c.last_name
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

function getUnreadNotificationCount(int $userId): int
{
    return (int) (Database::fetch(
        'SELECT COUNT(*) AS count FROM notifications WHERE user_id = ? AND is_read = 0',
        [$userId]
    )['count'] ?? 0);
}

function getUpcomingAppointments(int $limit = 5): array
{
    return Database::fetchAll(
        "SELECT a.*, a.starts_at AS start_time, a.ends_at AS end_time,
                c.company_name, c.first_name, c.last_name
         FROM appointments a
         JOIN clients c ON c.id = a.client_id
         WHERE a.starts_at >= NOW() AND a.status IN ('scheduled', 'confirmed')
         ORDER BY a.starts_at ASC
         LIMIT ?",
        [$limit]
    );
}

function getRecentCases(int $limit = 5): array
{
    return Database::fetchAll(
        "SELECT cs.*, cl.first_name, cl.last_name, cl.company_name
         FROM cases cs
         JOIN clients cl ON cl.id = cs.client_id
         ORDER BY cs.updated_at DESC
         LIMIT ?",
        [$limit]
    );
}

function getRevenueChartData(): array
{
    $rows = Database::fetchAll(
        "SELECT DATE_FORMAT(paid_at, '%b') AS month_label,
                MONTH(paid_at) AS month_num,
                COALESCE(SUM(amount), 0) AS total
         FROM payments
         WHERE payment_status = 'completed' AND paid_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
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
    $dayLabels = ['M', 'T', 'W', 'T', 'F', 'S', 'S'];
    $payments  = array_fill(0, 7, 0.0);
    $invoices  = array_fill(0, 7, 0.0);

    $rows = Database::fetchAll(
        "SELECT DATE(paid_at) AS day_date, COALESCE(SUM(amount), 0) AS total
         FROM payments
         WHERE payment_status = 'completed'
           AND paid_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
         GROUP BY DATE(paid_at)"
    );

    foreach ($rows as $row) {
        $dayIndex = (int) date('N', strtotime($row['day_date'])) - 1;
        if ($dayIndex >= 0 && $dayIndex < 7) {
            $payments[$dayIndex] = (float) $row['total'];
        }
    }

    $invoiceRows = Database::fetchAll(
        "SELECT DATE(created_at) AS day_date, COALESCE(SUM(total), 0) AS total
         FROM invoices
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
         GROUP BY DATE(created_at)"
    );

    foreach ($invoiceRows as $row) {
        $dayIndex = (int) date('N', strtotime($row['day_date'])) - 1;
        if ($dayIndex >= 0 && $dayIndex < 7) {
            $invoices[$dayIndex] = max(0, (float) $row['total'] - $payments[$dayIndex]);
        }
    }

    return [
        'labels'   => $dayLabels,
        'payments' => $payments,
        'invoices' => $invoices,
    ];
}

function getDashboardTrends(array $stats): array
{
    $lastMonthRevenue = (float) (Database::fetch(
        "SELECT COALESCE(SUM(amount), 0) AS total FROM payments
         WHERE payment_status = 'completed'
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

    $class = $map[$status] ?? 'badge-default';
    return sprintf('<span class="status-badge %s">%s</span>', $class, e(ucfirst($status)));
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
    return Database::fetchAll(
        'SELECT p.*, p.payment_status AS status, i.invoice_number, i.total AS invoice_total,
                cl.first_name, cl.last_name, cl.company_name
         FROM payments p
         JOIN invoices i ON i.id = p.invoice_id
         JOIN clients cl ON cl.id = p.client_id
         ORDER BY p.created_at DESC'
    );
}

function getAllAppointments(): array
{
    return Database::fetchAll(
        "SELECT a.*, a.starts_at AS start_time, a.ends_at AS end_time,
                cl.first_name, cl.last_name, cl.company_name, cs.case_number
         FROM appointments a
         JOIN clients cl ON cl.id = a.client_id
         LEFT JOIN cases cs ON cs.id = a.case_id
         ORDER BY a.starts_at DESC"
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

    $nextAppointment = Database::fetch(
        "SELECT title, starts_at AS start_time FROM appointments
         WHERE starts_at >= NOW() AND status IN ('scheduled', 'confirmed')
         ORDER BY starts_at ASC LIMIT 1"
    );

    return [
        'stats'           => $stats,
        'recent_cases'    => $recentCases,
        'pending_payments'=> (int) $pendingPayments,
        'next_appointment'=> $nextAppointment,
    ];
}

function generateChatbotReply(string $message): string
{
    $message = strtolower(trim($message));
    $ctx = getChatbotContext();
    $stats = $ctx['stats'];

    if ($message === '' || preg_match('/^(hi|hello|hey|help)/', $message)) {
        return "Hello! I'm your Notary Admin Assistant. I can help with:\n\n"
            . "• **Clients** — ask \"how many clients\" or \"list clients\"\n"
            . "• **Cases** — ask \"active cases\" or \"pending cases\"\n"
            . "• **Payments** — ask \"total revenue\" or \"pending invoices\"\n"
            . "• **Appointments** — ask \"upcoming appointments\" or \"next appointment\"\n\n"
            . "What would you like to know?";
    }

    if (preg_match('/how many client|total client|client count|number of client/', $message)) {
        return "You currently have **{$stats['total_clients']} registered clients** in the system.";
    }

    if (preg_match('/list client|show client|all client/', $message)) {
        $clients = getAllClients();
        if (empty($clients)) {
            return 'No clients found in the system.';
        }
        $lines = ["Here are your **" . count($clients) . " clients**:", ''];
        foreach (array_slice($clients, 0, 8) as $c) {
            $name = clientFullName($c);
            $company = $c['company_name'] ? " ({$c['company_name']})" : '';
            $lines[] = "• {$name}{$company} — {$c['case_count']} case(s)";
        }
        return implode("\n", $lines);
    }

    if (preg_match('/active case|open case|in progress case/', $message)) {
        return "There are **{$stats['active_cases']} active cases** currently in progress or pending action.";
    }

    if (preg_match('/pending case|case status|list case|show case|all case/', $message)) {
        $cases = $ctx['recent_cases'];
        if (empty($cases)) {
            return 'No cases found.';
        }
        $lines = ['**Recent cases:**', ''];
        foreach ($cases as $case) {
            $status = ucwords(str_replace('_', ' ', $case['status']));
            $lines[] = "• {$case['case_number']} — {$case['title']} (*{$status}*)";
        }
        return implode("\n", $lines);
    }

    if (preg_match('/revenue|total payment|payment total|earnings|income/', $message)) {
        return "**Revenue summary:**\n\n"
            . "• Total revenue: " . formatCurrency($stats['total_revenue']) . "\n"
            . "• This month: " . formatCurrency($stats['monthly_revenue']) . "\n"
            . "• Paid invoices: {$stats['paid_invoices']}";
    }

    if (preg_match('/pending invoice|unpaid|outstanding/', $message)) {
        return "You have **{$stats['pending_invoices']} pending invoices** and **{$ctx['pending_payments']} invoices** awaiting payment follow-up.";
    }

    if (preg_match('/list payment|show payment|recent payment|all payment/', $message)) {
        $payments = getAllPayments();
        if (empty($payments)) {
            return 'No payments recorded yet.';
        }
        $lines = ['**Recent payments:**', ''];
        foreach (array_slice($payments, 0, 6) as $p) {
            $name = clientFullName($p);
            $status = ucfirst(paymentStatusValue($p));
            $lines[] = "• " . formatCurrency((float) $p['amount']) . " from {$name} — {$p['invoice_number']} (*{$status}*)";
        }
        return implode("\n", $lines);
    }

    if (preg_match('/next appointment|upcoming appointment|schedule|appointment/', $message)) {
        if ($ctx['next_appointment']) {
            $appt = $ctx['next_appointment'];
            return "**Next appointment:** {$appt['title']} on " . formatDateTime($appt['start_time']) . ".";
        }
        return "You have **{$stats['upcoming_appointments']} upcoming appointments** scheduled. No future appointments found in the calendar.";
    }

    if (preg_match('/dashboard|summary|overview|status/', $message)) {
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

    $upcoming = (int) (Database::fetch(
        "SELECT COUNT(*) AS c FROM appointments WHERE client_id = ? AND starts_at >= NOW() AND status IN ('scheduled','confirmed')",
        [$clientId]
    )['c'] ?? 0);

    return [
        'active_cases'          => $activeCases,
        'pending_invoices'      => $pendingInvoices,
        'documents'             => $documents,
        'upcoming_appointments' => $upcoming,
    ];
}

function getClientCases(int $clientId): array
{
    return Database::fetchAll(
        'SELECT * FROM cases WHERE client_id = ? ORDER BY updated_at DESC',
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
    return Database::fetchAll(
        "SELECT a.*, a.starts_at AS start_time FROM appointments a
         WHERE a.client_id = ? AND a.starts_at >= NOW() AND a.status IN ('scheduled','confirmed')
         ORDER BY a.starts_at ASC LIMIT ?",
        [$clientId, $limit]
    );
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
    ];
    return $map[$type] ?? 'bi-activity';
}
