<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || getUserType() !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$message_id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM contact_messages WHERE message_id = ?");
$stmt->execute([$message_id]);
$message = $stmt->fetch();

if ($message) {
    echo json_encode(['success' => true, 'message' => $message]);
} else {
    echo json_encode(['success' => false, 'message' => 'Message not found']);
}
?>