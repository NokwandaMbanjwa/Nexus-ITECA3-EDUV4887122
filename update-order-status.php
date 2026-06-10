<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = getUserId();
$action = $_POST['action'] ?? '';
$order_id = (int)($_POST['order_id'] ?? 0);

if ($action === 'confirm_delivery') {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_id = ? AND buyer_id = ? AND status = 'delivered'");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch();

    if ($order) {
        $stmt = $pdo->prepare("UPDATE orders SET buyer_confirmed = 1, status = 'confirmed', updated_at = NOW() WHERE order_id = ?");
        $stmt->execute([$order_id]);

        $stmt = $pdo->prepare("INSERT INTO site_stats (recorded_date, total_sales, total_revenue) 
                              VALUES (CURDATE(), 1, ?) 
                              ON DUPLICATE KEY UPDATE total_sales = total_sales + 1, total_revenue = total_revenue + ?");
        $stmt->execute([$order['total_amount'], $order['total_amount']]);

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Order not found or not delivered']);
    }
    exit;
}

$stmt = $pdo->prepare("UPDATE orders SET buyer_confirmed = 1, buyer_confirmed_date = NOW() WHERE order_id = ? AND buyer_id = ?");
    $stmt->execute([$order_id, $user_id]);
    
    echo json_encode(['success' => true, 'message' => 'Delivery confirmed successfully']);
    exit;
}
?>