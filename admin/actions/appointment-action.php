<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !CSRF::verifyRequest()) {
    flash('error', 'Invalid request.');
    redirect('pages/appointments.php');
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'create_appointment') {
        $id = AppointmentService::create($_POST, Auth::id());
        redirect('pages/appointments.php?added=' . $id);
    }

    if ($action === 'update_appointment') {
        $id = (int) ($_POST['appointment_id'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('Invalid appointment.');
        }
        AppointmentService::update($id, $_POST);
        flash('success', 'Appointment updated.');
        redirect('pages/appointments.php');
    }

    if ($action === 'delete_appointment' || $action === 'cancel_appointment') {
        $id = (int) ($_POST['appointment_id'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('Invalid appointment.');
        }
        AppointmentService::delete($id);
        flash('success', 'Appointment deleted.');
        redirect('pages/appointments.php');
    }

    flash('error', 'Unknown action.');
    redirect('pages/appointments.php');
} catch (Throwable $e) {
    flash('error', $e->getMessage());
    redirect('pages/appointments.php');
}
