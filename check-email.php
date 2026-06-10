<?php
require_once 'config.php';

header('Content-Type: application/json');

$email = $_POST['email'] ?? '';

if (empty($email)) {
    echo json_encode(['exists' => false]);
    exit;
}

$stmt = $pdo->prepare("SELECT user_id FROM nexus_users WHERE email = ?");
$stmt->execute([$email]);
$exists = $stmt->fetch();

echo json_encode(['exists' => $exists !== false]);
?>