<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$pusher_key = '';
$pusher_secret = '';

$body = file_get_contents('php://input');
$params = [];
parse_str($body, $params);

$channel_name = $params['channel_name'] ?? '';
$socket_id = $params['socket_id'] ?? '';

if (!$channel_name || !$socket_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

$user_id = getUserId();

$expected_channel = 'private-user-' . $user_id;

if ($channel_name !== $expected_channel) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized channel']);
    exit;
}

$string_to_sign = $socket_id . ':' . $channel_name;
$auth_signature = hash_hmac('sha256', $string_to_sign, $pusher_secret);

echo json_encode([
    'auth' => $pusher_key . ':' . $auth_signature,
    'channel_data' => json_encode([
        'user_id' => $user_id,
        'user_info' => [
            'name' => getUserName()
        ]
    ])
]);
?>