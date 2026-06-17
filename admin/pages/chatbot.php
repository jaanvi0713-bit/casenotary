<?php

require_once __DIR__ . '/../core/bootstrap.php';

Auth::requirePage('chatbot');

redirect('pages/assistant.php');
