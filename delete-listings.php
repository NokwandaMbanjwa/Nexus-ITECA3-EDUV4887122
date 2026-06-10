<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn() || getUserType() !== 'seller') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$product_id = $_POST['product_id'] ?? 0;
$user_id = getUserId();

$stmt = $pdo->prepare("SELECT profile_id FROM seller_profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$seller = $stmt->fetch();

if ($seller) {
    $stmt = $pdo->prepare("DELETE FROM products WHERE product_id = ? AND seller_id = ?");
    $stmt->execute([$product_id, $seller['profile_id']]);
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Seller not found']);
}