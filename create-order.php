<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = getUserId();
$payment_method = $_POST['payment_method'] ?? 'card';
$reference = $_POST['reference'] ?? null;
$delivery_method = $_POST['delivery_method'] ?? 'SAPO';
$delivery_address = $_POST['delivery_address'] ?? '';
$proof_path = $_POST['proof_path'] ?? null;

try {
    $pdo->beginTransaction();

    // Get cart items with seller info
    $stmt = $pdo->prepare("
        SELECT c.*, p.product_name, p.price, p.seller_id, p.stock_quantity, 
               sp.user_id as seller_user_id
        FROM cart c
        JOIN products p ON c.product_id = p.product_id
        JOIN seller_profiles sp ON p.seller_id = sp.profile_id
        WHERE c.user_id = ?
    ");
    $stmt->execute([$user_id]);
    $cart_items = $stmt->fetchAll();

    if (empty($cart_items)) {
        echo json_encode(['success' => false, 'message' => 'Cart is empty']);
        exit;
    }

    // Get buyer's default address if no delivery address provided
    if (empty($delivery_address)) {
        $stmt = $pdo->prepare("SELECT residential_address, city_town, province, postal_code 
                               FROM buyer_profiles WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $address = $stmt->fetch();
        $delivery_address = $address ? 
            $address['residential_address'] . ', ' . $address['city_town'] . ', ' . $address['province'] . ' ' . $address['postal_code'] : '';
    }

    // Group items by seller
    $seller_items = [];
    foreach ($cart_items as $item) {
        $seller_items[$item['seller_id']][] = $item;
    }

    $order_ids = [];

    foreach ($seller_items as $seller_id => $items) {
        $order_total = 0;
        foreach ($items as $item) {
            if ($item['stock_quantity'] < $item['quantity']) {
                throw new Exception("Insufficient stock for product: " . $item['product_name']);
            }
            $order_total += $item['price'] * $item['quantity'];
        }

        $order_number = 'NEX-' . strtoupper(substr(uniqid(), -8));

        // Create order with delivery info
        $stmt = $pdo->prepare("
			INSERT INTO orders (
				order_number, buyer_id, seller_id, total_amount, 
				status, payment_status, payment_method, 
				payment_reference, proof_of_payment,
				delivery_method, delivery_address,
				shipping_address, created_at, updated_at
			)
			VALUES (?, ?, ?, ?, 'pending', 'paid', ?, ?, ?, ?, ?, ?, NOW(), NOW())
		");
		$stmt->execute([
			$order_number, $user_id, $seller_id, $order_total,
			$payment_method, $reference, $proof_path,
			$delivery_method, $delivery_address,
			$delivery_address
		]);
        $order_id = $pdo->lastInsertId();
        $order_ids[] = $order_id;

        // Insert order items and update stock
        foreach ($items as $item) {
            $item_subtotal = $item['price'] * $item['quantity'];
            
            $stmt = $pdo->prepare("
                INSERT INTO order_items (
                    order_id, product_id, quantity, price, subtotal
                ) VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$order_id, $item['product_id'], $item['quantity'], $item['price'], $item_subtotal]);

            $stmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ? AND stock_quantity >= ?");
            $stmt->execute([$item['quantity'], $item['product_id'], $item['quantity']]);
            
            $stmt = $pdo->prepare("SELECT stock_quantity FROM products WHERE product_id = ?");
            $stmt->execute([$item['product_id']]);
            $new_stock = $stmt->fetch()['stock_quantity'];
            
            if ($new_stock == 0) {
                $stmt = $pdo->prepare("UPDATE products SET listing_status = 'sold' WHERE product_id = ?");
                $stmt->execute([$item['product_id']]);
            }
        }

        // Add notes
        $item_count = count($items);
        $notes = "{$item_count} item(s) | Payment: {$payment_method} | Delivery: {$delivery_method}";
        $stmt = $pdo->prepare("UPDATE orders SET notes = ? WHERE order_id = ?");
        $stmt->execute([$notes, $order_id]);

        // Status history
        $stmt = $pdo->prepare("
            INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, notes, created_at) 
            VALUES (?, NULL, 'pending', ?, 'Order placed successfully', NOW())
        ");
        $stmt->execute([$order_id, $user_id]);

        // Send message to seller about delivery method
        $seller_user_id = $items[0]['seller_user_id'];
		$message_content = "Order {$order_number} has been allocated. The preferred delivery method is {$delivery_method}. Don't forget to update the order status as required.";

		// Check if conversation already exists between buyer and seller
		$stmt = $pdo->prepare("
			SELECT conversation_id FROM conversations 
			WHERE (participant1_id = ? AND participant2_id = ?) 
			   OR (participant1_id = ? AND participant2_id = ?)
		");
		$stmt->execute([$user_id, $seller_user_id, $seller_user_id, $user_id]);
		$conversation = $stmt->fetch();

		if ($conversation) {
			$conversation_id = $conversation['conversation_id'];
			// Update last message time
			$stmt = $pdo->prepare("UPDATE conversations SET last_message = ?, last_message_time = NOW() WHERE conversation_id = ?");
			$stmt->execute([$message_content, $conversation_id]);
		} else {
			// Create new conversation
			$stmt = $pdo->prepare("
				INSERT INTO conversations (participant1_id, participant2_id, last_message, last_message_time, created_at)
				VALUES (?, ?, ?, NOW(), NOW())
			");
			$stmt->execute([$user_id, $seller_user_id, $message_content]);
			$conversation_id = $pdo->lastInsertId();
		}

		// Insert the message
		$stmt = $pdo->prepare("
			INSERT INTO messages (conversation_id, sender_id, receiver_id, message, created_at, is_read)
			VALUES (?, ?, ?, ?, NOW(), 0)
		");
		$stmt->execute([$conversation_id, $user_id, $seller_user_id, $message_content]);
        // Notify seller via Pusher
        $pusher_data = [
            'order_id' => $order_id,
            'order_number' => $order_number,
            'total_amount' => $order_total,
            'status' => 'pending',
            'item_count' => $item_count,
            'delivery_method' => $delivery_method,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $pusher_key = 'a3192cebd4c0ba37141f';
        $pusher_secret = 'f39249c0cbb7baf5eb07';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://api-eu.pusher.com/apps/2156423/events");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'name' => 'new-order',
            'channel' => "private-user-{$seller_user_id}",
            'data' => $pusher_data
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode("{$pusher_key}:{$pusher_secret}")
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    // Clear cart
    $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->execute([$user_id]);

    $pdo->commit();

    echo json_encode([
        'success' => true, 
        'order_ids' => $order_ids,
        'message' => 'Orders created successfully'
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>