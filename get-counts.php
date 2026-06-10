<?php
require_once 'config.php';
 
header('Content-Type: application/json');
 
if (!isLoggedIn()) {
    echo json_encode(['cart' => 0, 'wishlist' => 0, 'messages' => 0]);
    exit;
}
 
$user_id = getUserId();
 
try {
    // Cart: number of distinct product rows for this user
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM cart WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $cart_count = (int)$stmt->fetchColumn();
 
    // Wishlist: number of items saved
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $wishlist_count = (int)$stmt->fetchColumn();
 
    // Messages: unread messages where current user is the receiver
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM messages m
        JOIN conversations c ON m.conversation_id = c.conversation_id
        WHERE m.is_read = 0
          AND (c.participant1_id = ? OR c.participant2_id = ?)
          AND m.sender_id != ?
    ");
    $stmt->execute([$user_id, $user_id, $user_id]);
    $messages_count = (int)$stmt->fetchColumn();
 
    echo json_encode([
        'cart'     => $cart_count,
        'wishlist' => $wishlist_count,
        'messages' => $messages_count
    ]);
} catch (Exception $e) {
    echo json_encode(['cart' => 0, 'wishlist' => 0, 'messages' => 0]);
}
?>