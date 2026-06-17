<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/bootstrap.php';

Auth::guardAction();

header('Content-Type: application/json; charset=utf-8');

if (!Auth::can(RoleAccess::PERMISSION_INSIGHTS)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$stats = getDashboardStats();
$payload = InsightsService::getLivePredictionPayload($stats);

echo json_encode($payload, JSON_UNESCAPED_UNICODE);
