<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::guardAction();
if (!Auth::can(RoleAccess::PERMISSION_NOTIFICATIONS)) {
    flash('error', 'You do not have permission to run reminders.');
    redirect('pages/dashboard.php');
}

$appointmentCount = ReminderService::sendDueReminders();
$workflowCount    = ReminderService::sendCaseWorkflowReminders();

flash('success', "Reminders processed. Appointments: {$appointmentCount}. Cases: {$workflowCount}.");
redirect('pages/dashboard.php');
