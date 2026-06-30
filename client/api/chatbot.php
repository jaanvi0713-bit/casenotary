<?php

declare(strict_types=1);

header('Content-Type: application/json');
http_response_code(410);

echo json_encode([
    'success' => false,
    'message' => 'This endpoint is no longer available.',
]);
