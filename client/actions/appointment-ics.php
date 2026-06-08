<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireClient();

$id = (int) ($_GET['id'] ?? 0);
$clientId = Auth::clientId();

if ($id <= 0 || !$clientId) {
    http_response_code(404);
    exit('Appointment not found.');
}

$appointment = Database::fetch(
    'SELECT id FROM appointments WHERE id = ? AND client_id = ?',
    [$id, $clientId]
);

if (!$appointment) {
    http_response_code(404);
    exit('Appointment not found.');
}

$path = GoogleCalendarService::getIcsFilePath($id);

if (!$path || !is_file($path)) {
    http_response_code(404);
    exit('Calendar file not found.');
}

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="appointment-' . $id . '.ics"');
header('Content-Length: ' . filesize($path));
readfile($path);
exit;
