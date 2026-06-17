<?php
require_once __DIR__ . '/../core/bootstrap.php';

Auth::requirePage('notifications');

$params = $_GET;
$params['tab'] = 'messages';
$query = http_build_query($params);
header('Location: ' . url('pages/notifications.php' . ($query !== '' ? '?' . $query : '?tab=messages')));
exit;
