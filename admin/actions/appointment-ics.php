<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::guardAction();

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
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
