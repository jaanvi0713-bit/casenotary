<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireClient();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verifyRequest()) {
    flash('error', 'Invalid request.');
    header('Location: ' . clientUrl('pages/appointments.php'));
    exit;
}

$clientId = Auth::clientId();
if (!$clientId) {
    flash('error', 'Client profile not found.');
    header('Location: ' . clientUrl('pages/appointments.php'));
    exit;
}

try {
    $id = AppointmentService::createClientRequest($_POST, $clientId);
    flash('success', 'Your appointment request has been submitted. We will notify you once it is reviewed.');
    header('Location: ' . clientUrl('pages/appointments.php?requested=' . $id));
    exit;
} catch (Throwable $e) {
    setOld($_POST);
    flash('error', $e->getMessage());
    header('Location: ' . clientUrl('pages/appointments.php'));
    exit;
}
