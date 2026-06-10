<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user_id = getUserId();
$user_name = getUserName();

$stmt = $pdo->prepare("SELECT profile_id FROM buyer_profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$buyer = $stmt->fetch();
$buyer_profile_id = $buyer['profile_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    
    if ($action === 'get_purchases') {
        $stmt = $pdo->prepare("
            SELECT o.*, sp.store_name,
                   (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) as item_count
            FROM orders o
            JOIN seller_profiles sp ON o.seller_id = sp.profile_id
            WHERE o.buyer_id = ?
            ORDER BY o.created_at DESC
        ");
        $stmt->execute([$user_id]);
        $orders = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'orders' => $orders]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>NEXUS | My Purchases</title>
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
			.purchases-page {
				padding-top: 50px;
				padding-bottom: 80px;
			}

			.purchases-container {
				max-width: 1000px;
				margin: 0 auto;
				padding: 0 24px;
			}

			.purchases-header {
				margin-bottom: 32px;
			}

			.purchases-header h1 {
				font-size: 32px;
				color: #8ff5ff;
				margin-bottom: 8px;
			}

			.purchases-header p {
				color: #888;
				font-size: 14px;
			}

			.section-header {
				margin-bottom: 16px;
				margin-top: 32px;
			}

			.section-header h2 {
				font-size: 18px;
				color: #fff;
				font-weight: 500;
			}

			.section-header:first-of-type {
				margin-top: 0;
			}

			.purchase-list {
				display: flex;
				flex-direction: column;
				gap: 1px;
				background: #1a1a1a;
				border: 1px solid #2a2a2a;
			}

			.purchase-item {
				background: #0a0a0a;
				padding: 16px 20px;
				transition: background 0.2s;
			}

			.purchase-item:hover {
				background: #0f0f0f;
			}

			.purchase-main {
				display: flex;
				justify-content: space-between;
				align-items: flex-start;
				flex-wrap: wrap;
				gap: 12px;
				margin-bottom: 10px;
			}

			.product-info h3 {
				font-size: 15px;
				font-weight: 500;
				color: #fff;
				margin-bottom: 4px;
			}

			.product-meta {
				display: flex;
				flex-wrap: wrap;
				gap: 16px;
				font-size: 12px;
				color: #666;
			}

			.product-meta span {
				display: inline-flex;
				align-items: center;
				gap: 4px;
			}

			.price {
				font-size: 15px;
				font-weight: 500;
				color: #8ff5ff;
			}

			.purchase-footer {
				display: flex;
				justify-content: space-between;
				align-items: center;
				flex-wrap: wrap;
				gap: 12px;
				padding-top: 10px;
				border-top: 1px solid #1a1a1a;
				font-size: 12px;
			}

			.order-id {
				color: #555;
			}

			.status-badge {
				padding: 4px 12px;
				border-radius: 20px;
				font-size: 11px;
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

			.action-link {
				background: none;
				border: none;
				color: #666;
				font-size: 12px;
				cursor: pointer;
				transition: color 0.2s;
			}

			.action-link:hover {
				color: #8ff5ff;
			}

			.tracking-link {
				color: #8ff5ff;
				text-decoration: none;
				font-size: 12px;
			}

			.tracking-link:hover {
				text-decoration: underline;
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
				font-size: 24px;
				color: #fff;
				margin-bottom: 8px;
			}

			.empty-state p {
				color: #888;
				margin-bottom: 20px;
			}

			.explore-link {
				display: inline-block;
				background: #8ff5ff;
				color: #0a0a0a;
				text-decoration: none;
				padding: 10px 24px;
				border-radius: 8px;
				font-size: 14px;
				font-weight: 500;
				transition: all 0.3s;
			}

			.explore-link:hover {
				background: #6dd5e0;
				transform: translateY(-2px);
			}

			.empty-subsection {
				padding: 40px 0;
				text-align: center;
				background: #0a0a0a;
				border: 1px solid #2a2a2a;
			}

			.empty-subsection p {
				color: #666;
				font-size: 13px;
				margin-bottom: 12px;
			}

			.small-link {
				font-size: 12px;
				color: #8ff5ff;
				text-decoration: none;
			}

			.small-link:hover {
				text-decoration: underline;
			}

			.loading {
				text-align: center;
				padding: 40px;
				color: #888;
			}

			.notification {
				position: fixed;
				bottom: 20px;
				right: 20px;
				background: #8ff5ff;
				color: #0e0e10;
				padding: 12px 20px;
				border-radius: 8px;
				z-index: 9999;
				display: none;
				animation: slideIn 0.3s ease;
			}

			@keyframes slideIn {
				from {
					transform: translateX(100%);
					opacity: 0;
				}
				to {
					transform: translateX(0);
					opacity: 1;
				}
			}

			@media (max-width: 768px) {
				.purchases-page {
					padding-top: 50px;
				}
				.purchase-main {
					flex-direction: column;
				}
				.product-meta {
					flex-direction: column;
					gap: 6px;
				}
				.purchase-footer {
					flex-direction: column;
					align-items: flex-start;
				}
				.empty-state h2 {
					font-size: 20px;
				}
			}
		</style>
	</head>

	<body>
		<?php include 'header.php'; ?>
		
		<main class="purchases-page">
			<div class="purchases-container">
				<div class="purchases-header">
					<h1><i class="fas fa-receipt"></i> Purchase History</h1>
					<p>Track and manage your orders</p>
				</div>

				<div id="loadingState" class="loading">
					<i class="fas fa-spinner fa-spin"></i> Loading your orders...
				</div>

				<div id="purchasesContent" style="display: none;">
					<div class="section-header">
						<h2>Active Orders</h2>
					</div>
					<div id="activeList" class="purchase-list"></div>
					<div id="emptyActive" class="empty-subsection" style="display: none;">
						<p>No active orders</p>
					</div>

					<div class="section-header">
						<h2>Order History</h2>
					</div>
					<div id="historyList" class="purchase-list"></div>
					<div id="emptyHistory" class="empty-subsection" style="display: none;">
						<p>No past orders</p>
						<a href="explore-feed.php" class="small-link">Start shopping →</a>
					</div>
				</div>

				<div id="emptyState" style="display: none;">
					<div class="empty-state">
						<div class="empty-icon">
							<i class="fas fa-receipt"></i>
						</div>
						<h2>No purchases yet</h2>
						<p>You haven't made any purchases. Start exploring and find something you love!</p>
						<a href="explore-feed.php" class="explore-link">Explore Items</a>
					</div>
				</div>
			</div>
		</main>
		
		<?php include 'footer.php'; ?>
		
		<div id="notification" class="notification"></div>
		
		<script type = "text/javascript" src = "utilities.js" ></script>
		<script>
			const currentUserId = <?php echo $user_id; ?>;
			let allOrders = [];

			const pusher = new Pusher('a3192cebd4c0ba37141f', {
				cluster: 'eu',
				encrypted: true
			});

			const channel = pusher.subscribe(`private-user-${currentUserId}`);
			channel.bind('order-updated', function(data) {
				console.log('Order update received:', data);
				loadPurchases();
				showNotification(`Order #${data.order_id} status updated to ${data.status}${data.tracking_number ? `. Tracking number: ${data.tracking_number}` : ''}`);
			});
			
			function loadPurchases() {
				fetch(window.location.href, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
						'X-Requested-With': 'XMLHttpRequest'
					},
					body: 'action=get_purchases'
				})
				.then(response => response.json())
				.then(data => {
					document.getElementById('loadingState').style.display = 'none';
					
					if (data.success && data.orders && data.orders.length > 0) {
						allOrders = data.orders;
						document.getElementById('purchasesContent').style.display = 'block';
						document.getElementById('emptyState').style.display = 'none';
						renderOrders();
					} else {
						document.getElementById('purchasesContent').style.display = 'none';
						document.getElementById('emptyState').style.display = 'block';
					}
				})
				.catch(error => {
					console.error('Error:', error);
					document.getElementById('loadingState').innerHTML = '<div class="empty-state"><div class="empty-icon"><i class="fas fa-exclamation-triangle"></i></div><h2>Error loading orders</h2><p>Please refresh the page to try again.</p></div>';
				});
			}
			
			function renderOrders() {
				const activeOrders = allOrders.filter(order => 
					order.status === 'pending' || order.status === 'processing' || order.status === 'shipped'
				);
				const historyOrders = allOrders.filter(order => 
					order.status === 'delivered' || order.status === 'cancelled'
				);
				
				renderActiveOrders(activeOrders);
				renderHistoryOrders(historyOrders);
			}
			
			function renderActiveOrders(orders) {
				const container = document.getElementById('activeList');
				const emptyDiv = document.getElementById('emptyActive');
				
				if (orders.length === 0) {
					container.style.display = 'none';
					emptyDiv.style.display = 'block';
					return;
				}
				
				container.style.display = 'block';
				emptyDiv.style.display = 'none';
				
				container.innerHTML = orders.map(order => `
					<div class="purchase-item" data-order-id="${order.order_id}">
						<div class="purchase-main">
							<div class="product-info">
								<h3>Order #${escapeHtml(order.order_number)}</h3>
								<div class="product-meta">
									<span><i class="fas fa-store"></i> ${escapeHtml(order.store_name)}</span>
									<span><i class="fas fa-calendar"></i> ${formatDate(order.created_at)}</span>
									<span><i class="fas fa-box"></i> ${order.item_count} item(s)</span>
									${order.estimated_delivery ? `<span><i class="fas fa-truck"></i> Est: ${formatDate(order.estimated_delivery)}</span>` : ''}
								</div>
							</div>
							<div class="price">R${parseFloat(order.total_amount).toLocaleString()}</div>
						</div>
						<div class="purchase-footer">
							<span class="order-id">#${escapeHtml(order.order_number)}</span>
							<div style="display: flex; gap: 16px; align-items: center; flex-wrap: wrap;">
								<span class="status-badge status-${order.status}">${capitalize(order.status)}</span>
								${order.status === 'shipped' && order.tracking_number ? 
									`<button class="action-link" onclick="trackOrder('${escapeHtml(order.tracking_number)}', '${escapeHtml(order.tracking_carrier || '')}')">Track</button>` : 
									order.status === 'processing' ? 
									`<span class="action-link" style="color: #888;"><i class="fas fa-spinner fa-spin"></i> Preparing</span>` : ''
								}
								<button class="action-link" onclick="contactSeller('${order.seller_id}')">Message Seller</button>
								${order.status === 'delivered' && !order.buyer_confirmed ? 
									`<button class="action-link" style="color: #4caf50;" onclick="confirmDelivery(${order.order_id})">
										<i class="fas fa-check-circle"></i> Confirm Delivery
									</button>` : ''}
								${order.status === 'delivered' ? 
								`<button class="action-link" onclick="leaveReview(${order.order_id})">Leave Review</button>` : ''}
								
								${order.status === 'delivered' && !order.buyer_confirmed ? 
								`<button class="action-link" style="color: #4caf50;" onclick="confirmDelivery(${order.order_id})">
									<i class="fas fa-check-circle"></i> Confirm Delivery
								</button>` : ''}
							</div>
						</div>
					</div>
				`).join('');
			}
			
			function confirmDelivery(orderId) {
				if (confirm('Confirm that you have received this order?')) {
					fetch('update-order-status.php', {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
						body: 'action=confirm_delivery&order_id=' + orderId
					})
					.then(function(r) { return r.json(); })
					.then(function(data) {
						if (data.success) {
							showNotification('Delivery confirmed! Thank you for your purchase.');
							loadPurchases();
						}
					});
				}
			}
			
			function renderHistoryOrders(orders) {
				const container = document.getElementById('historyList');
				const emptyDiv = document.getElementById('emptyHistory');
				
				if (orders.length === 0) {
					container.style.display = 'none';
					emptyDiv.style.display = 'block';
					return;
				}
				
				container.style.display = 'block';
				emptyDiv.style.display = 'none';
				
				container.innerHTML = orders.map(order => `
					<div class="purchase-item" data-order-id="${order.order_id}">
						<div class="purchase-main">
							<div class="product-info">
								<h3>Order #${escapeHtml(order.order_number)}</h3>
								<div class="product-meta">
									<span><i class="fas fa-store"></i> ${escapeHtml(order.store_name)}</span>
									<span><i class="fas fa-calendar"></i> ${formatDate(order.created_at)}</span>
									<span><i class="fas fa-box"></i> ${order.item_count} item(s)</span>
									${order.delivered_date ? `<span><i class="fas fa-check-circle"></i> Delivered: ${formatDate(order.delivered_date)}</span>` : ''}
									${order.cancelled_date ? `<span><i class="fas fa-times-circle"></i> Cancelled: ${formatDate(order.cancelled_date)}</span>` : ''}
								</div>
							</div>
							<div class="price">R${parseFloat(order.total_amount).toLocaleString()}</div>
						</div>
						<div class="purchase-footer">
							<span class="order-id">#${escapeHtml(order.order_number)}</span>
							<div style="display: flex; gap: 16px; align-items: center;">
								<span class="status-badge status-${order.status}">${capitalize(order.status)}</span>
								<button class="action-link" onclick="viewOrderDetails(${order.order_id})">View Details</button>
								${order.status === 'delivered' ? `<button class="action-link" onclick="buyAgain(${order.order_id})">Buy Again</button>` : ''}
								<button class="action-link" onclick="contactSeller('${order.seller_id}')">Message Seller</button>
							</div>
						</div>
					</div>
				`).join('');
			}
			
			function viewOrderDetails(orderId) {
				const order = allOrders.find(o => o.order_id == orderId);
				if (order) {
					let detailsHtml = `
						<div style="padding: 20px; background: #19191c; border-radius: 12px;">
							<h3 style="color: #8ff5ff; margin-bottom: 16px;">Order #${escapeHtml(order.order_number)}</h3>
							<p><strong>Order Date:</strong> ${formatDate(order.created_at)}</p>
							<p><strong>Status:</strong> <span class="status-badge status-${order.status}">${capitalize(order.status)}</span></p>
							<p><strong>Total Amount:</strong> R${parseFloat(order.total_amount).toLocaleString()}</p>
							<p><strong>Seller:</strong> ${escapeHtml(order.store_name)}</p>
							<p><strong>Shipping Address:</strong><br>${escapeHtml(order.shipping_address)}</p>
							${order.tracking_number ? `<p><strong>Tracking Number:</strong> ${escapeHtml(order.tracking_number)}</p>` : ''}
							${order.tracking_carrier ? `<p><strong>Carrier:</strong> ${escapeHtml(order.tracking_carrier)}</p>` : ''}
							${order.estimated_delivery ? `<p><strong>Estimated Delivery:</strong> ${formatDate(order.estimated_delivery)}</p>` : ''}
							${order.delivered_date ? `<p><strong>Delivered Date:</strong> ${formatDate(order.delivered_date)}</p>` : ''}
							${order.cancelled_date ? `<p><strong>Cancelled Date:</strong> ${formatDate(order.cancelled_date)}</p>` : ''}
							${order.cancellation_reason ? `<p><strong>Cancellation Reason:</strong> ${escapeHtml(order.cancellation_reason)}</p>` : ''}
						</div>
					`;
					
					const modal = document.createElement('div');
					modal.style.position = 'fixed';
					modal.style.top = '0';
					modal.style.left = '0';
					modal.style.right = '0';
					modal.style.bottom = '0';
					modal.style.background = 'rgba(0,0,0,0.9)';
					modal.style.zIndex = '1000';
					modal.style.display = 'flex';
					modal.style.alignItems = 'center';
					modal.style.justifyContent = 'center';
					modal.innerHTML = `
						<div style="background: #19191c; border-radius: 16px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;">
							<div style="padding: 20px; border-bottom: 1px solid #2a2a2a; display: flex; justify-content: space-between; align-items: center;">
								<h3 style="color: #8ff5ff;">Order Details</h3>
								<button onclick="this.closest('.modal-container').remove()" style="background: none; border: none; color: #888; font-size: 24px; cursor: pointer;">&times;</button>
							</div>
							<div style="padding: 20px;">
								${detailsHtml}
							</div>
						</div>
					`;
					modal.className = 'modal-container';
					document.body.appendChild(modal);
				}
			}
			
			function trackOrder(trackingNumber, carrier) {
				alert(`Track your package: ${trackingNumber}\nCarrier: ${carrier || 'Standard Shipping'}\n\nCheck the carrier's website for real-time updates.`);
			}
			
			function contactSeller(sellerId) {
				window.location.href = `messages.php?user=${sellerId}`;
			}
			
			function buyAgain(orderId) {
				alert('This feature will allow you to reorder the same items. Coming soon!');
			}
			
			function leaveReview(orderId) {
				const rating = prompt('Rate your experience (1-5 stars):', '5');
				if (rating && rating >= 1 && rating <= 5) {
					const review = prompt('Leave a review (optional):', '');
					alert(`Thank you for your ${rating}-star review!`);
					// In production, save to database
				}
			}
			
			function formatDate(dateString) {
				if (!dateString) return 'N/A';
				const date = new Date(dateString);
				return date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
			}
			
			function capitalize(str) {
				return str.charAt(0).toUpperCase() + str.slice(1);
			}
			
			function escapeHtml(str) {
				if (!str) return '';
				return String(str).replace(/[&<>]/g, function(m) {
					if (m === '&') return '&amp;';
					if (m === '<') return '&lt;';
					if (m === '>') return '&gt;';
					return m;
				});
			}
			
			function showNotification(message) {
				const notification = document.getElementById('notification');
				notification.textContent = message;
				notification.style.display = 'block';
				setTimeout(() => {
					notification.style.display = 'none';
				}, 5000);
			}
			
			document.addEventListener('click', function(e) {
				if (e.target.classList && e.target.classList.contains('modal-container')) {
					e.target.remove();
				}
			});
			
			loadPurchases();
		</script>
	</body>
</html>