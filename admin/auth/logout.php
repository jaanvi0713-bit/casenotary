<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::logout();
redirect('auth/login.php');
