<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireClient();

flash('error', 'Appointment requests are currently unavailable in the client portal.');
header('Location: ' . clientUrl('pages/appointments.php'));
exit;
