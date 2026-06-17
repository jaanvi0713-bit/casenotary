<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requireClient();

redirect('pages/dashboard.php');
