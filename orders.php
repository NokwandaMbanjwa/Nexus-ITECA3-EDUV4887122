<?php
require_once 'config.php';

if (!isLoggedIn() || getUserType() !== 'seller') {
    header('Location: login.php');
    exit;
}

$user_id = getUserId();
$user_name = getUserName();

$stmt = $pdo->prepare("SELECT profile_id, store_name FROM seller_profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$seller = $stmt->fetch();

if (!$seller) {
    header('Location: register.php');
    exit;
}

$seller_id = $seller['profile_id'];
$store_name = $seller['store_name'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    
    if ($action === 'get_orders') {
        $status_filter = $_POST['status'] ?? 'all';
        
        $sql = "SELECT o.*, 
                       u.email as buyer_email,
                       (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) as item_count
                FROM orders o
                JOIN nexus_users u ON o.buyer_id = u.user_id
                WHERE o.seller_id = ?";
        $params = [$seller_id];
        
        if ($status_filter !== 'all') {
            $sql .= " AND o.status = ?";
            $params[] = $status_filter;
        }
        
        $sql .= " ORDER BY o.created_at DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $orders = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'orders' => $orders]);
        exit;
    }
    
    if ($action === 'update_status') {
        $order_id = $_POST['order_id'] ?? 0;
        $new_status = $_POST['status'] ?? '';
        $tracking_number = $_POST['tracking_number'] ?? '';
        $tracking_carrier = $_POST['tracking_carrier'] ?? '';
        $notes = $_POST['notes'] ?? '';
        
        $valid_statuses = ['processing', 'shipped', 'delivered', 'cancelled'];
        if (!in_array($new_status, $valid_statuses)) {
            echo json_encode(['success' => false, 'error' => 'Invalid status']);
            exit;
        }
        
        try {
            $pdo->beginTransaction();
            
            // Get current status
            $stmt = $pdo->prepare("SELECT status FROM orders WHERE order_id = ? AND seller_id = ?");
            $stmt->execute([$order_id, $seller_id]);
            $current = $stmt->fetch();
            
            if (!$current) {
                echo json_encode(['success' => false, 'error' => 'Order not found']);
                exit;
            }
            
            $old_status = $current['status'];
            
            // Update order status
            $update_fields = "status = ?, updated_at = NOW()";
            $params = [$new_status];
            
            if ($new_status === 'shipped' && $tracking_number) {
                $update_fields .= ", tracking_number = ?, tracking_carrier = ?";
                $params[] = $tracking_number;
                $params[] = $tracking_carrier;
            }
            
            if ($new_status === 'delivered') {
                $update_fields .= ", delivered_date = CURDATE()";
            }
            
            if ($new_status === 'cancelled') {
                $update_fields .= ", cancelled_date = CURDATE(), cancellation_reason = ?";
                $params[] = $notes;
            }
            
            $stmt = $pdo->prepare("UPDATE orders SET $update_fields WHERE order_id = ? AND seller_id = ?");
            $params[] = $order_id;
            $params[] = $seller_id;
            $stmt->execute($params);
            
            $stmt = $pdo->prepare("INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$order_id, $old_status, $new_status, $user_id, $notes]);
            
            $pdo->commit();
            
            // Get buyer info for Pusher notification
            $stmt = $pdo->prepare("SELECT buyer_id FROM orders WHERE order_id = ?");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch();
            $buyer_id = $order['buyer_id'];
            
            // Trigger Pusher event for real-time update to buyer
            $pusher_key = 'a3192cebd4c0ba37141f';
            $pusher_secret = 'f39249c0cbb7baf5eb07';
            $pusher_app_id = '2156423';
            
            $pusher_data = [
                'order_id' => $order_id,
                'status' => $new_status,
                'tracking_number' => $tracking_number,
                'tracking_carrier' => $tracking_carrier,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $pusher_url = "https://api-eu.pusher.com/apps/2156423/events";
            $post_data = json_encode([
                'name' => 'order-updated',
                'channel' => "private-user-{$buyer_id}",
                'data' => $pusher_data
            ]);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $pusher_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Basic ' . base64_encode("{$pusher_key}:{$pusher_secret}")
            ]);
            curl_exec($ch);
            curl_close($ch);
            
            echo json_encode(['success' => true, 'message' => 'Order status updated successfully']);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
   if ($action === 'get_order_details') {
		$order_id = $_POST['order_id'] ?? 0;
		
		$stmt = $pdo->prepare("
			SELECT o.*, u.email as buyer_email,
				   (SELECT full_name FROM buyer_profiles WHERE user_id = o.buyer_id) as buyer_name,
				   (SELECT phone_number FROM buyer_profiles WHERE user_id = o.buyer_id) as buyer_phone
			FROM orders o
			JOIN nexus_users u ON o.buyer_id = u.user_id
			WHERE o.order_id = ? AND o.seller_id = ?
		");
		$stmt->execute([$order_id, $seller_id]);
		$order = $stmt->fetch();
		
		if ($order) {
			// Get items - join with products table to get name and image
			$stmt = $pdo->prepare("
				SELECT oi.*, p.product_name, 
					   (SELECT image_url FROM product_images WHERE product_id = p.product_id AND is_main = 1 LIMIT 1) as product_image
				FROM order_items oi
				JOIN products p ON oi.product_id = p.product_id
				WHERE oi.order_id = ?
			");
			$stmt->execute([$order_id]);
			$items = $stmt->fetchAll();
			
			$stmt = $pdo->prepare("
				SELECT * FROM order_status_history 
				WHERE order_id = ? 
				ORDER BY created_at DESC
			");
			$stmt->execute([$order_id]);
			$history = $stmt->fetchAll();
			
			echo json_encode(['success' => true, 'order' => $order, 'items' => $items, 'history' => $history]);
		} else {
			echo json_encode(['success' => false, 'error' => 'Order not found']);
		}
		exit;
	}
}
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>NEXUS | Orders</title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="basestyle.css">
		<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
		<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
		<script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
		
		<style>
			.seller-orders-page {
				padding-top: 50px;
				padding-bottom: 80px;
			}

			.seller-orders-container {
				max-width: 1200px;
				margin: 0 auto;
				padding: 0 24px;
			}

			.orders-header {
				margin-bottom: 32px;
			}

			.orders-header h1 {
				font-size: 32px;
				color: #8ff5ff;
				margin-bottom: 8px;
			}

			.orders-header p {
				color: #888;
				font-size: 14px;
			}

			.stats-bar {
				display: flex;
				gap: 20px;
				margin-bottom: 32px;
				flex-wrap: wrap;
			}

			.stat-card {
				background: #19191c;
				border: 1px solid #2a2a2a;
				border-radius: 12px;
				padding: 16px 24px;
				min-width: 140px;
				text-align: center;
			}

			.stat-card .stat-label {
				font-size: 12px;
				color: #888;
				margin-bottom: 4px;
			}

			.stat-card .stat-value {
				font-size: 28px;
				font-weight: 600;
				color: #8ff5ff;
			}

			.filter-bar {
				display: flex;
				gap: 12px;
				margin-bottom: 24px;
				flex-wrap: wrap;
			}

			.filter-btn {
				background: #19191c;
				border: 1px solid #2a2a2a;
				border-radius: 20px;
				padding: 8px 20px;
				color: #aaa;
				cursor: pointer;
				transition: all 0.3s;
			}

			.filter-btn:hover,
			.filter-btn.active {
				background: #8ff5ff;
				border-color: #8ff5ff;
				color: #0e0e10;
			}

			.orders-list {
				display: flex;
				flex-direction: column;
				gap: 16px;
			}

			.order-card {
				background: #19191c;
				border: 1px solid #2a2a2a;
				border-radius: 12px;
				overflow: hidden;
				transition: all 0.3s;
			}

			.order-card:hover {
				border-color: #8ff5ff;
			}

			.order-header {
				display: flex;
				justify-content: space-between;
				align-items: center;
				padding: 16px 20px;
				background: #111;
				border-bottom: 1px solid #2a2a2a;
				flex-wrap: wrap;
				gap: 12px;
			}

			.order-number {
				font-weight: 600;
				color: #8ff5ff;
			}

			.order-date {
				font-size: 12px;
				color: #888;
			}

			.status-badge {
				padding: 4px 12px;
				border-radius: 20px;
				font-size: 12px;
				font-weight: 500;
			}

			.status-pending {
				background: rgba(255, 193, 7, 0.15);
				color: #ffc107;
			}

			.status-processing {
				background: rgba(33, 150, 243, 0.15);
				color: #2196f3;
			}

			.status-shipped {
				background: rgba(143, 245, 255, 0.15);
				color: #8ff5ff;
			}

			.status-delivered {
				background: rgba(76, 175, 80, 0.15);
				color: #4caf50;
			}

			.status-cancelled {
				background: rgba(255, 68, 68, 0.15);
				color: #ff4444;
			}

			.order-body {
				padding: 16px 20px;
			}

			.buyer-info {
				display: flex;
				justify-content: space-between;
				align-items: center;
				flex-wrap: wrap;
				gap: 16px;
				margin-bottom: 16px;
				padding-bottom: 12px;
				border-bottom: 1px solid #2a2a2a;
			}

			.buyer-details {
				display: flex;
				align-items: center;
				gap: 12px;
			}

			.buyer-avatar {
				width: 40px;
				height: 40px;
				background: #2a2a2a;
				border-radius: 50%;
				display: flex;
				align-items: center;
				justify-content: center;
				color: #8ff5ff;
			}

			.buyer-name {
				font-weight: 500;
				color: #fff;
			}

			.buyer-email {
				font-size: 11px;
				color: #888;
			}

			.order-total {
				font-size: 18px;
				font-weight: 600;
				color: #8ff5ff;
			}

			.items-list {
				margin-bottom: 16px;
			}

			.item-row {
				display: flex;
				justify-content: space-between;
				align-items: center;
				padding: 8px 0;
				border-bottom: 1px solid #2a2a2a;
			}

			.item-name {
				display: flex;
				align-items: center;
				gap: 12px;
			}

			.item-name img {
				width: 40px;
				height: 40px;
				border-radius: 8px;
				object-fit: cover;
			}

			.item-price {
				color: #888;
				font-size: 13px;
			}

			.order-footer {
				display: flex;
				justify-content: space-between;
				align-items: center;
				flex-wrap: wrap;
				gap: 16px;
				padding-top: 12px;
				border-top: 1px solid #2a2a2a;
			}

			.tracking-info {
				font-size: 12px;
				color: #888;
			}

			.action-buttons {
				display: flex;
				gap: 12px;
			}

			.action-btn {
				padding: 8px 16px;
				border-radius: 8px;
				font-size: 13px;
				cursor: pointer;
				transition: all 0.3s;
				border: none;
			}

			.btn-primary {
				background: #8ff5ff;
				color: #0e0e10;
			}

			.btn-primary:hover {
				background: #6dd5e0;
				transform: translateY(-2px);
			}

			.btn-outline {
				background: transparent;
				border: 1px solid #2a2a2a;
				color: #aaa;
			}

			.btn-outline:hover {
				border-color: #8ff5ff;
				color: #8ff5ff;
			}

			.empty-state {
				text-align: center;
				padding: 60px;
				background: #19191c;
				border-radius: 12px;
			}

			.empty-icon {
				font-size: 64px;
				color: #8ff5ff;
				margin-bottom: 20px;
				opacity: 0.6;
			}

			.empty-state h2 {
				color: #fff;
				margin-bottom: 8px;
			}

			.empty-state p {
				color: #888;
			}

			.modal {
				display: none;
				position: fixed;
				top: 0;
				left: 0;
				right: 0;
				bottom: 0;
				background: rgba(0, 0, 0, 0.9);
				z-index: 1000;
				overflow-y: auto;
				align-items: center;
				justify-content: center;
			}

			.modal.show {
				display: flex;
			}

			.modal-content {
				background: #19191c;
				border-radius: 16px;
				max-width: 500px;
				width: 90%;
				padding: 24px;
				border: 1px solid #2a2a2a;
				max-height: 80vh;
    				overflow-y: auto; 
				margin: auto;
			}

			.modal-header {
				display: flex;
				justify-content: space-between;
				align-items: center;
				margin-bottom: 20px;
			}

			.modal-header h3 {
				color: #8ff5ff;
			}

			.close-modal {
				background: none;
				border: none;
				color: #888;
				font-size: 24px;
				cursor: pointer;
			}

			.form-group {
				margin-bottom: 16px;
			}

			.form-group label {
				display: block;
				margin-bottom: 8px;
				color: #aaa;
				font-size: 13px;
			}

			.form-group input,
			.form-group select,
			.form-group textarea {
				width: 100%;
				padding: 10px;
				background: #0e0e10;
				border: 1px solid #2a2a2a;
				border-radius: 8px;
				color: #e5e5e5;
			}

			.modal-actions {
				display: flex;
				gap: 12px;
				justify-content: flex-end;
				margin-top: 20px;
			}

			@media (max-width: 768px) {
				.seller-orders-page {
					padding-top: 50px;
				}
				.stats-bar {
					gap: 12px;
				}
				.stat-card {
					min-width: calc(50% - 6px);
					flex: 1;
				}
				.order-header {
					flex-direction: column;
					align-items: flex-start;
				}
				.buyer-info {
					flex-direction: column;
					align-items: flex-start;
				}
				.order-footer {
					flex-direction: column;
					align-items: flex-start;
				}
				.action-buttons {
					width: 100%;
				}
				.action-btn {
					flex: 1;
					text-align: center;
				}
			}
		</style>
	</head>

	<body>
		<?php include 'header.php'; ?>
		
		<main class="seller-orders-page">
			<div class="seller-orders-container">
				<div class="orders-header">
					<h1><i class="fas fa-box"></i> Seller Orders</h1>
					<p>Manage and track orders from your customers - <?php echo htmlspecialchars($store_name); ?></p>
				</div>

				<div class="stats-bar" id="statsBar">
					<div class="stat-card">
						<div class="stat-label">Total Orders</div>
						<div class="stat-value" id="totalOrders">0</div>
					</div>
					<div class="stat-card">
						<div class="stat-label">Pending</div>
						<div class="stat-value" id="pendingCount">0</div>
					</div>
					<div class="stat-card">
						<div class="stat-label">Processing</div>
						<div class="stat-value" id="processingCount">0</div>
					</div>
					<div class="stat-card">
						<div class="stat-label">Shipped</div>
						<div class="stat-value" id="shippedCount">0</div>
					</div>
					<div class="stat-card">
						<div class="stat-label">Delivered</div>
						<div class="stat-value" id="deliveredCount">0</div>
					</div>
				</div>

				<div class="filter-bar">
					<button class="filter-btn active" data-status="all">All Orders</button>
					<button class="filter-btn" data-status="pending">Pending</button>
					<button class="filter-btn" data-status="processing">Processing</button>
					<button class="filter-btn" data-status="shipped">Shipped</button>
					<button class="filter-btn" data-status="delivered">Delivered</button>
					<button class="filter-btn" data-status="cancelled">Cancelled</button>
				</div>

				<div id="ordersList" class="orders-list">
					<div class="empty-state">
						<div class="empty-icon"><i class="fas fa-box-open"></i></div>
						<h2>Loading orders...</h2>
					</div>
				</div>
			</div>
		</main>

		<div class="modal" id="updateModal">
			<div class="modal-content">
				<div class="modal-header">
					<h3>Update Order Status</h3>
					<button class="close-modal" onclick="closeModal()">&times;</button>
				</div>
				<form id="updateForm">
					<input type="hidden" id="updateOrderId" name="order_id">
					<input type="hidden" name="action" value="update_status">
					
					<div class="form-group">
						<label>Status</label>
						<select id="orderStatus" name="status" required>
							<option value="processing">Processing</option>
							<option value="shipped">Shipped</option>
							<option value="delivered">Delivered</option>
							<option value="cancelled">Cancelled</option>
						</select>
					</div>
					
					<div class="form-group" id="trackingGroup" style="display: none;">
						<label>Tracking Number</label>
						<input type="text" id="trackingNumber" name="tracking_number" placeholder="Enter tracking number">
					</div>
					
					<div class="form-group" id="carrierGroup" style="display: none;">
						<label>Shipping Carrier</label>
						<select id="trackingCarrier" name="tracking_carrier">
							<option value="">Select carrier</option>
							<option value="SAPO">SAPO (South African Post Office)</option>
							<option value="DPO">DPO Group</option>
							<option value="PAXI">PAXI</option>
							<option value="CourierIt">CourierIt</option>
							<option value="The Courier Guy">The Courier Guy</option>
							<option value="Fastway">Fastway</option>
							<option value="Aramex">Aramex</option>
							<option value="DHL">DHL</option>
							<option value="FedEx">FedEx</option>
							<option value="Other">Other</option>
						</select>
					</div>
					
					<div class="form-group" id="notesGroup" style="display: none;">
						<label>Cancellation Reason</label>
						<textarea id="cancellationNotes" name="notes" rows="3" placeholder="Please provide reason for cancellation..."></textarea>
					</div>
					
					<div class="modal-actions">
						<button type="button" class="action-btn btn-outline" onclick="closeModal()">Cancel</button>
						<button type="submit" class="action-btn btn-primary">Update Status</button>
					</div>
				</form>
			</div>
		</div>

		<div class="modal" id="detailsModal">
			<div class="modal-content" style="max-width: 600px;">
				<div class="modal-header">
					<h3>Order Details</h3>
					<button class="close-modal" onclick="closeDetailsModal()">&times;</button>
				</div>
				<div id="orderDetailsContent"></div>
			</div>
		</div>

		<?php include 'footer.php'; ?>
		
		<script type = "text/javascript" src = "utilities.js" ></script>
		<script>
			let currentFilter = 'all';

			const pusher = new Pusher('a3192cebd4c0ba37141f', {
				cluster: 'eu',
				encrypted: true
			});
			
			const channel = pusher.subscribe(`private-user-<?php echo $user_id; ?>`);
			channel.bind('order-updated', function(data) {
				loadOrders(currentFilter);
				showNotification('Order #' + data.order_id + ' has been updated to ' + data.status);
			});
			
			function loadOrders(status = 'all') {
				fetch(window.location.href, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
						'X-Requested-With': 'XMLHttpRequest'
					},
					body: `action=get_orders&status=${status}`
				})
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						displayOrders(data.orders);
						updateStats(data.orders);
					} else {
						document.getElementById('ordersList').innerHTML = '<div class="empty-state"><div class="empty-icon"><i class="fas fa-exclamation-triangle"></i></div><h2>Error loading orders</h2><p>Please try again later.</p></div>';
					}
				});
			}
			
			function displayOrders(orders) {
				const container = document.getElementById('ordersList');
				
				if (orders.length === 0) {
					container.innerHTML = '<div class="empty-state"><div class="empty-icon"><i class="fas fa-box-open"></i></div><h2>No orders found</h2><p>When customers purchase your items, orders will appear here.</p></div>';
					return;
				}
				
				container.innerHTML = orders.map(order => `
					<div class="order-card" data-order-id="${order.order_id}">
						<div class="order-header">
							<div>
								<span class="order-number">Order #${order.order_number}</span>
								<span class="order-date"> | ${formatDate(order.created_at)}</span>
							</div>
							<span class="status-badge status-${order.status}">${capitalize(order.status)}</span>
						</div>
						<div class="order-body">
							<div class="buyer-info">
								<div class="buyer-details">
									<div class="buyer-avatar"><i class="fas fa-user"></i></div>
									<div>
										<div class="buyer-name">Buyer #${order.buyer_id}</div>
										<div class="buyer-email">${escapeHtml(order.buyer_email)}</div>
									</div>
								</div>
								<div class="order-total">R${parseFloat(order.total_amount).toLocaleString()}</div>
							</div>
							<div class="items-list">
								<div class="item-row">
									<span class="item-name">${order.item_count} item(s)</span>
									<span class="item-price">View details</span>
								</div>
							</div>
							<div class="order-footer">
								<div class="tracking-info">
									${order.tracking_number ? `<i class="fas fa-truck"></i> Tracking: ${escapeHtml(order.tracking_number)}` : 'No tracking info yet'}
								</div>
								<div class="action-buttons">
									<button class="action-btn btn-outline" onclick="viewOrderDetails(${order.order_id})">View Details</button>
									<button class="action-btn btn-primary" onclick="openUpdateModal(${order.order_id}, '${order.status}')">Update Status</button>
								</div>
							</div>
						</div>
					</div>
				`).join('');
			}
			
			function updateStats(orders) {
				const total = orders.length;
				const pending = orders.filter(o => o.status === 'pending').length;
				const processing = orders.filter(o => o.status === 'processing').length;
				const shipped = orders.filter(o => o.status === 'shipped').length;
				const delivered = orders.filter(o => o.status === 'delivered').length;
				
				document.getElementById('totalOrders').textContent = total;
				document.getElementById('pendingCount').textContent = pending;
				document.getElementById('processingCount').textContent = processing;
				document.getElementById('shippedCount').textContent = shipped;
				document.getElementById('deliveredCount').textContent = delivered;
			}
			
			function viewOrderDetails(orderId) {
				fetch(window.location.href, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
						'X-Requested-With': 'XMLHttpRequest'
					},
					body: `action=get_order_details&order_id=${orderId}`
				})
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						const order = data.order;
						const items = data.items;
						const history = data.history;
						
						const itemsHtml = items.map(item => `
							<div class="item-row">
								<div class="item-name">
									<img src="${item.product_image || 'https://via.placeholder.com/40'}" alt="${escapeHtml(item.product_name)}">
									<span>${escapeHtml(item.product_name)} x ${item.quantity}</span>
								</div>
								<div class="item-price">R${parseFloat(item.price).toLocaleString()}</div>
							</div>
						`).join('');
						
						const historyHtml = history.map(h => `
							<div class="item-row">
								<span>${formatDate(h.created_at)}</span>
								<span>${h.old_status ? h.old_status + ' → ' : ''}${h.new_status}</span>
								${h.notes ? `<span>${escapeHtml(h.notes)}</span>` : ''}
							</div>
						`).join('');
						
						document.getElementById('orderDetailsContent').innerHTML = `
							<div style="margin-bottom: 20px;">
								<h4>Order Information</h4>
								<p><strong>Order Number:</strong> ${order.order_number}</p>
								<p><strong>Date:</strong> ${formatDate(order.created_at)}</p>
								<p><strong>Status:</strong> <span class="status-badge status-${order.status}">${capitalize(order.status)}</span></p>
								<p><strong>Total Amount:</strong> R${parseFloat(order.total_amount).toLocaleString()}</p>
							</div>
							<div style="margin-bottom: 20px;">
								<h4>Shipping Address</h4>
								<p>${escapeHtml(order.shipping_address)}</p>
								${order.tracking_number ? `<p><strong>Tracking:</strong> ${escapeHtml(order.tracking_number)} (${escapeHtml(order.tracking_carrier || 'N/A')})</p>` : ''}
							</div>
								<p><strong>Delivery Method:</strong> ${escapeHtml(order.delivery_method || 'Not specified')}</p>
								<p><strong>Delivery Address:</strong><br>${escapeHtml(order.delivery_address || order.shipping_address)}</p>
							<div style="margin-bottom: 20px;">
								<h4>Items</h4>
								${itemsHtml}
							</div>
							<div style="margin-bottom: 20px;">
								<h4>Status History</h4>
								${historyHtml || '<p>No history available</p>'}
							</div>
						`;
						document.getElementById('detailsModal').classList.add('show');
					}
				});
			}
			
			let currentOrderId = null;
			
			function openUpdateModal(orderId, currentStatus) {
				currentOrderId = orderId;
				document.getElementById('updateOrderId').value = orderId;
				document.getElementById('orderStatus').value = 'processing';
				document.getElementById('trackingGroup').style.display = 'none';
				document.getElementById('carrierGroup').style.display = 'none';
				document.getElementById('notesGroup').style.display = 'none';
				document.getElementById('updateModal').classList.add('show');
			}
			
			document.getElementById('orderStatus').addEventListener('change', function() {
				const status = this.value;
				document.getElementById('trackingGroup').style.display = status === 'shipped' ? 'block' : 'none';
				document.getElementById('carrierGroup').style.display = status === 'shipped' ? 'block' : 'none';
				document.getElementById('notesGroup').style.display = status === 'cancelled' ? 'block' : 'none';
			});
			
			document.getElementById('updateForm').addEventListener('submit', function(e) {
				e.preventDefault();
				
				const formData = new FormData(this);
				formData.append('order_id', currentOrderId);
				
				fetch(window.location.href, {
					method: 'POST',
					headers: {
						'X-Requested-With': 'XMLHttpRequest'
					},
					body: formData
				})
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						closeModal();
						loadOrders(currentFilter);
						showNotification(data.message);
					} else {
						alert('Error: ' + data.error);
					}
				});
			});
			
			function closeModal() {
				document.getElementById('updateModal').classList.remove('show');
			}
			
			function closeDetailsModal() {
    				document.getElementById('detailsModal').classList.remove('show');
			}

			document.getElementById('detailsModal').addEventListener('click', function(e) {
    				if (e.target === this) {
        				closeDetailsModal();
    				}
			});

			document.getElementById('updateModal').addEventListener('click', function(e) {
    				if (e.target === this) {
        				closeModal();
    				}
				});
			
			function showNotification(message) {
				let notification = document.getElementById('notification');
				if (!notification) {
					notification = document.createElement('div');
					notification.id = 'notification';
					notification.style.position = 'fixed';
					notification.style.bottom = '20px';
					notification.style.right = '20px';
					notification.style.backgroundColor = '#8ff5ff';
					notification.style.color = '#0e0e10';
					notification.style.padding = '12px 20px';
					notification.style.borderRadius = '8px';
					notification.style.zIndex = '9999';
					document.body.appendChild(notification);
				}
				notification.textContent = message;
				notification.style.display = 'block';
				setTimeout(() => {
					notification.style.display = 'none';
				}, 3000);
			}
			
			function formatDate(dateString) {
				const date = new Date(dateString);
				return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
			}
			
			function capitalize(str) {
				return str.charAt(0).toUpperCase() + str.slice(1);
			}
			
			function escapeHtml(str) {
				if (!str) return '';
				return str.replace(/[&<>]/g, function(m) {
					if (m === '&') return '&amp;';
					if (m === '<') return '&lt;';
					if (m === '>') return '&gt;';
					return m;
				});
			}
			
			channel.bind('new-order', function(data) {
				loadOrders(currentFilter);
				showNotification('New order received! Order #' + data.order_number + ' - R' + data.total_amount.toLocaleString());
			});
			
			document.querySelectorAll('.filter-btn').forEach(btn => {
				btn.addEventListener('click', () => {
					document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
					btn.classList.add('active');
					currentFilter = btn.getAttribute('data-status');
					loadOrders(currentFilter);
				});
			});
			
			loadOrders('all');
		</script>
	</body>
</html>