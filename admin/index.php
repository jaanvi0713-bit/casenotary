<?php
require_once __DIR__ . '/core/bootstrap.php';

if (Auth::check() && Auth::isAdmin()) {
    redirect('pages/dashboard.php');
}

redirect('auth/login.php');
