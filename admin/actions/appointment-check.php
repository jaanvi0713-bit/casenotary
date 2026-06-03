<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::guardAction();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$startsAt = normalizeDateTimeInput(trim($_GET['starts_at'] ?? ''));
$endsAt   = normalizeDateTimeInput(trim($_GET['ends_at'] ?? ''));
$exclude  = (int) ($_GET['appointment_id'] ?? 0);

if ($startsAt === '') {
    echo json_encode(['conflicts' => []]);
    exit;
}

if ($endsAt === '') {
    $endsAt = date('Y-m-d H:i:s', strtotime($startsAt . ' +1 hour'));
}

if (strtotime($endsAt) <= strtotime($startsAt)) {
    echo json_encode(['conflicts' => [], 'error' => 'End time must be after start time.']);
    exit;
}

try {
    $conflicts = AppointmentService::findConflicts(
        $startsAt,
        $endsAt,
        $exclude > 0 ? $exclude : null,
        Auth::id()
    );

    $items = array_map(static function (array $row): array {
        return [
            'id'    => (int) ($row['id'] ?? 0),
            'title' => $row['title'] ?? 'Appointment',
            'start' => formatDateTime(appointmentStart($row)),
            'end'   => formatDateTime(appointmentEnd($row) ?: date('Y-m-d H:i:s', strtotime(appointmentStart($row) . ' +1 hour'))),
            'client'=> clientFullName($row),
        ];
    }, $conflicts);

    echo json_encode(['conflicts' => $items]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage(), 'conflicts' => []]);
}
