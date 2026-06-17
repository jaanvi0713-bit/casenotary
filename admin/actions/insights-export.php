<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

Auth::guardAction();

$type = strtolower(trim((string) ($_GET['type'] ?? 'snapshot')));
$allowed = ['snapshot', 'cases', 'clients', 'appointments', 'payments'];
if (!in_array($type, $allowed, true)) {
    $type = 'snapshot';
}

if (!Auth::can(RoleAccess::PERMISSION_INSIGHTS)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$filename = 'insights-' . $type . '-' . date('Y-m-d-His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');

switch ($type) {
    case 'cases':
        if (!Auth::can(RoleAccess::PERMISSION_CASES)) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
        fputcsv($out, ['Case #', 'Title', 'Client', 'Service', 'Status', 'Priority', 'Deadline', 'Created']);
        foreach (getAllCases() as $row) {
            fputcsv($out, [
                $row['case_number'] ?? '',
                $row['title'] ?? '',
                clientFullName($row),
                $row['service_type'] ?? '',
                $row['status'] ?? '',
                $row['priority'] ?? '',
                $row['deadline'] ?? '',
                $row['created_at'] ?? '',
            ]);
        }
        break;

    case 'clients':
        if (!Auth::can(RoleAccess::PERMISSION_CLIENTS)) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
        fputcsv($out, ['Name', 'Company', 'Email', 'Phone', 'Created']);
        foreach (getAllClients() as $row) {
            fputcsv($out, [
                clientFullName($row),
                $row['company_name'] ?? '',
                $row['email'] ?? '',
                $row['phone'] ?? '',
                $row['created_at'] ?? '',
            ]);
        }
        break;

    case 'appointments':
        if (!Auth::can(RoleAccess::PERMISSION_APPOINTMENTS)) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
        fputcsv($out, ['Start', 'End', 'Client', 'Case', 'Status', 'Location']);
        foreach (getAllAppointments() as $row) {
            fputcsv($out, [
                $row['start_time'] ?? $row['start_at'] ?? '',
                $row['end_time'] ?? $row['end_at'] ?? '',
                clientFullName($row),
                $row['case_number'] ?? '',
                $row['status'] ?? '',
                $row['location'] ?? '',
            ]);
        }
        break;

    case 'payments':
        if (!Auth::can(RoleAccess::PERMISSION_PAYMENTS)) {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
        fputcsv($out, ['Invoice', 'Client', 'Amount', 'Method', 'Status', 'Paid At', 'Receipt']);
        foreach (getAllPayments() as $payment) {
            fputcsv($out, [
                $payment['invoice_number'] ?? '',
                clientFullName($payment),
                number_format((float) ($payment['amount'] ?? 0), 2, '.', ''),
                $payment['payment_method'] ?? '',
                paymentStatusValue($payment),
                $payment['paid_at'] ?? $payment['created_at'] ?? '',
                $payment['receipt_number'] ?? '',
            ]);
        }
        break;

    default:
        $stats = getDashboardStats();
        $hub = InsightsService::getHubData($stats);
        $prediction = $hub['prediction_suite'] ?? [];

        fputcsv($out, ['Section', 'Metric', 'Value']);
        fputcsv($out, ['Overview', 'Health score', (string) ($hub['health_score'] ?? 0)]);
        fputcsv($out, ['Financial', 'Total revenue', number_format((float) ($stats['total_revenue'] ?? 0), 2, '.', '')]);
        fputcsv($out, ['Financial', 'Monthly revenue', number_format((float) ($stats['monthly_revenue'] ?? 0), 2, '.', '')]);
        fputcsv($out, ['Financial', 'Outstanding balance', number_format((float) ($stats['outstanding_balance'] ?? 0), 2, '.', '')]);
        fputcsv($out, ['Financial', 'Collection rate %', number_format((float) ($stats['collection_rate'] ?? 0), 1, '.', '')]);
        fputcsv($out, ['Cases', 'Active cases', (string) ((int) ($stats['active_cases'] ?? 0))]);
        fputcsv($out, ['Cases', 'Urgent cases', (string) ((int) ($stats['urgent_cases'] ?? 0))]);
        fputcsv($out, ['Clients', 'Total clients', (string) ((int) ($stats['total_clients'] ?? 0))]);
        fputcsv($out, ['AI Prediction', 'Base estimate', number_format((float) ($prediction['base'] ?? 0), 2, '.', '')]);
        fputcsv($out, ['AI Prediction', 'Best case', number_format((float) ($prediction['best'] ?? 0), 2, '.', '')]);
        fputcsv($out, ['AI Prediction', 'Worst case', number_format((float) ($prediction['worst'] ?? 0), 2, '.', '')]);
        fputcsv($out, ['AI Prediction', 'Confidence', (string) ($prediction['confidence'] ?? 'low')]);
        break;
}

fclose($out);
exit;
