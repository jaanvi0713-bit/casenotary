<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::guardAction();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$startsAt  = normalizeDateTimeInput(trim($_GET['starts_at'] ?? ''));
$endsAt    = normalizeDateTimeInput(trim($_GET['ends_at'] ?? ''));
$excludeId = (int) ($_GET['exclude_id'] ?? 0);

if ($startsAt === '') {
    echo json_encode(['conflicts' => [], 'allow_overlap' => true]);
    exit;
}

if ($endsAt === '') {
    $endsAt = date('Y-m-d H:i:s', strtotime($startsAt . ' +1 hour'));
} else {
    $endsAt = normalizeAppointmentEndTime($startsAt, $endsAt);
}

$conflicts = AppointmentService::findConflicts(
    $startsAt,
    $endsAt,
    $excludeId > 0 ? $excludeId : null
);

$payload = [];
foreach ($conflicts as $conflict) {
    $startLabel = formatDateTime($conflict['starts_at'] ?? null);
    $endLabel   = formatDateTime($conflict['ends_at'] ?? null);
    $range      = ($endLabel !== '' && $endLabel !== $startLabel)
        ? $startLabel . ' – ' . $endLabel
        : $startLabel;

    $payload[] = [
        'id'        => (int) ($conflict['id'] ?? 0),
        'title'     => (string) ($conflict['title'] ?? 'Appointment'),
        'starts_at' => (string) ($conflict['starts_at'] ?? ''),
        'ends_at'   => (string) ($conflict['ends_at'] ?? ''),
        'label'     => ($conflict['title'] ?? 'Appointment') . ' (' . $range . ')',
    ];
}

echo json_encode([
    'conflicts'     => $payload,
    'allow_overlap' => $payload === [],
]);
