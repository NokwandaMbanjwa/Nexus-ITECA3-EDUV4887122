<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = getUserId();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'getFollowed') {
    $stmt = $pdo->prepare("SELECT following_id FROM user_follows WHERE follower_id = ?");
    $stmt->execute([$user_id]);
    $followed = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode(['success' => true, 'followed' => $followed]);
    exit;
}

if ($action === 'toggle') {
    $seller_id = (int)($_POST['seller_id'] ?? 0);
    if (!$seller_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid seller']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT follow_id FROM user_follows WHERE follower_id = ? AND following_id = ?");
    $stmt->execute([$user_id, $seller_id]);

    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("DELETE FROM user_follows WHERE follower_id = ? AND following_id = ?");
        $stmt->execute([$user_id, $seller_id]);
        echo json_encode(['success' => true, 'following' => false]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO user_follows (follower_id, following_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $seller_id]);
        echo json_encode(['success' => true, 'following' => true]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>