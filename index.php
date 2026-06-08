<?php
require_once __DIR__ . '/admin/core/bootstrap.php';

header('Location: ' . adminUrl('auth/login.php'));
exit;
