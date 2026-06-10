<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user_id = getUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $product_id = $_POST['product_id'] ?? 0;
    $quantity = (int)($_POST['quantity'] ?? 1);
    
    try {
        if ($action === 'add') {
            // Check if item is already in cart
            $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$user_id, $product_id]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                $new_quantity = $existing['quantity'] + $quantity;
                $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$new_quantity, $user_id, $product_id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $product_id, $quantity]);
            }
            echo json_encode(['success' => true, 'message' => 'Item added to cart']);
        }
        elseif ($action === 'update') {
            if ($quantity <= 0) {
                $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$user_id, $product_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$quantity, $user_id, $product_id]);
            }
            echo json_encode(['success' => true]);
        }
        elseif ($action === 'remove') {
            $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$user_id, $product_id]);
            echo json_encode(['success' => true]);
        }
        elseif ($action === 'get') {
            $stmt = $pdo->prepare("
               SELECT c.*, p.product_name, p.product_description, p.price,
					   (SELECT image_url FROM product_images WHERE product_id = p.product_id AND is_main = 1 LIMIT 1) as product_image,
					   sp.store_name, sp.full_name
				FROM cart c
				JOIN products p ON c.product_id = p.product_id
				LEFT JOIN seller_profiles sp ON p.seller_id = sp.profile_id
				WHERE c.user_id = ?
				ORDER BY c.added_at DESC
            ");
            $stmt->execute([$user_id]);
            $cart_items = $stmt->fetchAll();
            echo json_encode(['success' => true, 'cart' => $cart_items]);
        }
        elseif ($action === 'clear') {
            $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
            $stmt->execute([$user_id]);
            echo json_encode(['success' => true]);
        }
        else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Get cart items for display
$stmt = $pdo->prepare("
    SELECT c.*, p.product_name, p.product_description, p.price,
       (SELECT image_url FROM product_images WHERE product_id = p.product_id AND is_main = 1 LIMIT 1) as product_image,
       sp.store_name, sp.full_name,
       (p.price * c.quantity) as subtotal
	FROM cart c
	JOIN products p ON c.product_id = p.product_id
	LEFT JOIN seller_profiles sp ON p.seller_id = sp.profile_id
	WHERE c.user_id = ?
	ORDER BY c.added_at DESC
");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll();

// Calculate totals
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

$total = $subtotal;
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>NEXUS | My Cart</title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="basestyle.css">
		<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
		<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
		
		<style>
			.cart-page {
				padding: 50px 0 40px;
			}
			
			.cart-container {
				max-width: 1000px;
				margin: 0 auto;
				padding: 0 24px;
			}
			
			.cart-header {
				margin-bottom: 32px;
			}
			
			.cart-header h1 {
				font-size: 32px;
				color: #8ff5ff;
				margin-bottom: 8px;
			}
			
			.cart-header p {
				color: #888;
				font-size: 14px;
			}
			
			.cart-layout {
				display: flex;
				flex-direction: column;
				gap: 32px;
			}
			
			.cart-items-section {
				background: transparent;
				border: none;
				padding: 0;
			}
			
			.cart-items-header {
				display: flex;
				justify-content: space-between;
				align-items: center;
				padding-bottom: 16px;
				border-bottom: 1px solid rgba(118, 117, 119, 0.1);
				margin-bottom: 16px;
			}
			
			.cart-items-header h2 {
				font-size: 18px;
				color: #fff;
			}
			
			.cart-items-header span {
				color: #888;
				font-size: 14px;
			}
			
			.cart-item {
				display: flex;
				gap: 16px;
				padding: 20px 0;
				border-bottom: 1px solid rgba(118, 117, 119, 0.1);
			}
			
			.cart-item:last-child {
				border-bottom: none;
			}
			
			.item-image {
				width: 100px;
				height: 100px;
				flex-shrink: 0;
				background: #0e0e10;
				border-radius: 12px;
				overflow: hidden;
			}
			
			.item-image img {
				width: 100%;
				height: 100%;
				object-fit: cover;
			}
			
			.item-details {
				flex: 1;
			}
			
			.item-details h3 {
				font-size: 16px;
				font-weight: 600;
				color: #fff;
				margin-bottom: 6px;
			}
			
			.item-description {
				font-size: 13px;
				color: #888;
				line-height: 1.4;
				margin-bottom: 8px;
				max-width: 400px;
			}
			
			.item-price {
				font-size: 16px;
				font-weight: 600;
				color: #8ff5ff;
			}
			
			.item-actions {
				display: flex;
				flex-direction: column;
				align-items: flex-end;
				gap: 12px;
			}
			
			.quantity-selector {
				display: flex;
				align-items: center;
				gap: 8px;
				background: #0e0e10;
				border: 1px solid #2a2a2a;
				border-radius: 8px;
				padding: 4px;
			}
			
			.quantity-btn {
				width: 28px;
				height: 28px;
				background: #1a1a1a;
				border: none;
				border-radius: 6px;
				color: #8ff5ff;
				cursor: pointer;
				font-size: 14px;
				transition: all 0.2s;
			}
			
			.quantity-btn:hover {
				background: #8ff5ff;
				color: #0e0e10;
			}
			
			.quantity-btn:active {
				transform: scale(0.95);
			}
			
			.quantity-value {
				min-width: 40px;
				text-align: center;
				color: #fff;
				font-size: 14px;
			}
			
			.remove-item {
				background: none;
				border: none;
				color: #ff6b6b;
				font-size: 12px;
				cursor: pointer;
				transition: all 0.2s;
			}
			
			.remove-item:hover {
				color: #ff4444;
			}
			
			.item-total {
				font-size: 16px;
				font-weight: 600;
				color: #8ff5ff;
			}
			
			.mobile-price,
			.mobile-actions-row,
			.remove-item-mobile {
				display: none;
			}
			
			.order-summary {
				background: #19191c;
				border: 1px solid #2a2a2a;
				border-radius: 16px;
				padding: 24px;
				max-width: 500px;
				margin: 0 auto;
				width: 100%;
			}
			
			.order-summary h2 {
				font-size: 18px;
				color: #fff;
				margin-bottom: 20px;
				padding-bottom: 12px;
				border-bottom: 1px solid #2a2a2a;
			}
			
			.summary-row {
				display: flex;
				justify-content: space-between;
				margin-bottom: 12px;
				font-size: 14px;
			}
			
			.summary-row .label {
				color: #888;
			}
			
			.summary-row .value {
				color: #e5e5e5;
			}
			
			.summary-row.total {
				margin-top: 16px;
				padding-top: 16px;
				border-top: 1px solid #2a2a2a;
				font-size: 18px;
				font-weight: 600;
			}
			
			.summary-row.total .label,
			.summary-row.total .value {
				color: #8ff5ff;
			}
			
			.checkout-btn {
				width: 100%;
				margin-top: 24px;
				padding: 14px;
				background: #8ff5ff;
				color: #0e0e10;
				border: none;
				border-radius: 12px;
				font-size: 16px;
				font-weight: 600;
				cursor: pointer;
				transition: all 0.3s;
			}
			
			.checkout-btn:hover {
				background: #6dd5e0;
				transform: translateY(-2px);
			}
			
			.empty-cart {
				text-align: center;
				padding: 60px;
				background: #19191c;
				border-radius: 16px;
			}
			
			.empty-cart i {
				font-size: 64px;
				color: #8ff5ff;
				margin-bottom: 20px;
				opacity: 0.6;
			}
			
			.empty-cart h2 {
				color: #fff;
				margin-bottom: 12px;
			}
			
			.empty-cart p {
				color: #888;
				margin-bottom: 24px;
			}
			
			.loading {
				text-align: center;
				padding: 60px;
				color: #adaaad;
			}
			
			.loading i {
				font-size: 40px;
				color: #8ff5ff;
				margin-bottom: 16px;
				display: inline-block;
				animation: spin 1s linear infinite;
			}
			
			@keyframes spin {
				from { transform: rotate(0deg); }
				to { transform: rotate(360deg); }
			}
			
			@media (max-width: 768px) {
				.cart-page {
					padding: 40px 0 60px;
				}
				
				.cart-container {
					padding: 0 16px;
				}
				
				.cart-header h1 {
					font-size: 28px;
				}
				
				.cart-item {
					display: flex;
					flex-direction: row;
					align-items: flex-start;
					gap: 12px;
					padding: 16px 0;
					border-bottom: 1px solid rgba(118, 117, 119, 0.08);
				}
				
				.cart-item:first-child {
					padding-top: 0;
				}
				
				.item-image {
					width: 70px;
					height: 70px;
					border-radius: 10px;
					flex-shrink: 0;
				}
				
				.item-details {
					flex: 1;
					min-width: 0;
				}
				
				.item-details h3 {
					font-size: 14px;
					margin-bottom: 4px;
					white-space: nowrap;
					overflow: hidden;
					text-overflow: ellipsis;
				}
				
				.item-description {
					font-size: 12px;
					margin-bottom: 6px;
					display: -webkit-box;
					-webkit-line-clamp: 2;
					-webkit-box-orient: vertical;
					overflow: hidden;
					max-width: 100%;
				}
				
				.item-price {
					display: none;
				}
				
				.item-actions {
					display: none;
				}
				
				.mobile-price {
					display: block;
					font-size: 14px;
					font-weight: 600;
					color: #8ff5ff;
					margin-bottom: 8px;
				}
				
				.mobile-actions-row {
					display: flex;
					align-items: center;
					justify-content: space-between;
					margin-top: 4px;
				}
				
				.remove-item-mobile {
					display: inline-block;
					background: none;
					border: none;
					color: #ff6b6b;
					font-size: 11px;
					cursor: pointer;
					padding: 0;
					margin-top: 6px;
					transition: all 0.2s;
				}
				
				.remove-item-mobile:hover {
					color: #ff4444;
				}
				
				.quantity-selector {
					padding: 2px;
					gap: 4px;
				}
				
				.quantity-btn {
					width: 26px;
					height: 26px;
					font-size: 12px;
				}
				
				.quantity-value {
					min-width: 32px;
					font-size: 13px;
				}
				
				/* Order summary full width */
				.order-summary {
					max-width: 100%;
					margin: 0;
					border-radius: 12px;
				}
				
				.checkout-btn {
					padding: 16px;
					font-size: 15px;
				}
			}
			@media (max-width: 480px) {
				.cart-page {
					padding: 40px 0 40px;
				}
				
				.cart-container {
					padding: 0 12px;
				}
				
				.cart-header h1 {
					font-size: 24px;
				}
				
				.cart-header p {
					font-size: 13px;
				}
				
				.cart-item {
					gap: 10px;
					padding: 14px 0;
				}
				
				.item-image {
					width: 60px;
					height: 60px;
					border-radius: 8px;
				}
				
				.item-details h3 {
					font-size: 13px;
				}
				
				.item-description {
					font-size: 11px;
					-webkit-line-clamp: 2;
				}
				
				.mobile-price {
					font-size: 13px;
				}
				
				.quantity-btn {
					width: 24px;
					height: 24px;
					font-size: 11px;
				}
				
				.quantity-value {
					min-width: 28px;
					font-size: 12px;
				}
				
				.order-summary {
					padding: 20px 16px;
				}
				
				.order-summary h2 {
					font-size: 16px;
				}
				
				.summary-row {
					font-size: 13px;
				}
				
				.summary-row.total {
					font-size: 16px;
				}
				
				.checkout-btn {
					padding: 14px;
					font-size: 14px;
				}
			}
			@media (max-width: 360px) {
				.cart-container {
					padding: 0 10px;
				}
				
				.item-image {
					width: 55px;
					height: 55px;
				}
				
				.item-details h3 {
					font-size: 12px;
				}
				
				.item-description {
					font-size: 10px;
					-webkit-line-clamp: 1;
				}
				
				.mobile-price {
					font-size: 12px;
				}
			}
		</style>
	</head>

	<body>
		<?php include 'header.php'; ?>
		
		<main class="cart-page">
			<div class="cart-container">
				<div class="cart-header">
					<h1><i class="fas fa-shopping-cart"></i> My Cart</h1>
					<p>Review your items before checkout</p>
				</div>
				
				<div id="cartContent">
					<?php if (empty($cart_items)): ?>
						<div class="empty-cart">
							<i class="fas fa-shopping-cart"></i>
							<h2>Your cart is empty</h2>
							<p>Looks like you haven't added any items to your cart yet.</p>
							<a href="explore-feed.php" class="btn-primary" style="text-decoration: none;">Start Shopping</a>
						</div>
					<?php else: ?>
						<div class="cart-layout">
							<div class="cart-items-section">
								<div class="cart-items-header">
									<h2>Cart Items</h2>
									<span><?php echo count($cart_items); ?> item(s)</span>
								</div>
								
								<div id="cartItemsContainer">
									<?php foreach ($cart_items as $item): ?>
										<div class="cart-item" data-product-id="<?php echo $item['product_id']; ?>">
											<div class="item-image">
												<img src="<?php echo htmlspecialchars($item['product_image'] ?? 'https://via.placeholder.com/300x300/1f1f22/8ff5ff?text=Product'); ?>" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
											</div>
											<div class="item-details">
												<h3><?php echo htmlspecialchars($item['product_name']); ?></h3>
												<p class="item-description"><?php echo htmlspecialchars(substr($item['product_description'] ?? 'No description', 0, 100)); ?></p>
												
												<div class="item-price">R<?php echo number_format($item['price'], 2); ?></div>
												
												<div class="mobile-price">R<?php echo number_format($item['price'], 2); ?></div>
												
												<div class="mobile-actions-row">
													<div class="quantity-selector">
														<button class="quantity-btn decrease" data-product-id="<?php echo $item['product_id']; ?>">-</button>
														<span class="quantity-value" id="qty-<?php echo $item['product_id']; ?>"><?php echo $item['quantity']; ?></span>
														<button class="quantity-btn increase" data-product-id="<?php echo $item['product_id']; ?>">+</button>
													</div>
													<div class="mobile-item-total" id="mobile-total-<?php echo $item['product_id']; ?>">
														R<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
													</div>
												</div>

												<button class="remove-item-mobile" data-product-id="<?php echo $item['product_id']; ?>">
													<i class="fas fa-trash-alt"></i> Remove
												</button>
											</div>

											<div class="item-actions">
												<div class="quantity-selector">
													<button class="quantity-btn decrease" data-product-id="<?php echo $item['product_id']; ?>">-</button>
													<span class="quantity-value" id="qty-desk-<?php echo $item['product_id']; ?>"><?php echo $item['quantity']; ?></span>
													<button class="quantity-btn increase" data-product-id="<?php echo $item['product_id']; ?>">+</button>
												</div>
												<button class="remove-item" data-product-id="<?php echo $item['product_id']; ?>">
													<i class="fas fa-trash-alt"></i> Remove
												</button>
												<div class="item-total" id="total-<?php echo $item['product_id']; ?>">
													R<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
												</div>
											</div>
										</div>
									<?php endforeach; ?>
								</div>
							</div>
							
							<!-- Order Summary Section -->
							<div class="order-summary">
								<h2>Order Summary</h2>
								
								<div class="summary-row">
									<span class="label">Subtotal</span>
									<span class="value" id="subtotal">R<?php echo number_format($subtotal, 2); ?></span>
								</div>
								<div class="summary-row total">
									<span class="label">Total</span>
									<span class="value" id="totalAmount">R<?php echo number_format($total, 2); ?></span>
								</div>
								
								<button class="checkout-btn" id="checkoutBtn">
									<i class="fas fa-lock"></i> Proceed to Checkout
								</button>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</main>
		
		<?php include 'footer.php'; ?>
		
		<script type="text/javascript" src="utilities.js"></script>
		<script>
			function updateCart(productId, newQuantity) {
				fetch(window.location.href, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
						'X-Requested-With': 'XMLHttpRequest'
					},
					body: `action=update&product_id=${productId}&quantity=${newQuantity}`
				})
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						if (newQuantity <= 0) {
							const itemElement = document.querySelector(`.cart-item[data-product-id="${productId}"]`);
							if (itemElement) {
								itemElement.remove();
							}
						}
						location.reload();
					}
				});
			}
			
			// Remove item from cart
			function removeFromCart(productId) {
				if (confirm('Remove this item from your cart?')) {
					fetch(window.location.href, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded',
							'X-Requested-With': 'XMLHttpRequest'
						},
						body: `action=remove&product_id=${productId}`
					})
					.then(response => response.json())
					.then(data => {
						if (data.success) {
							location.reload();
						}
					});
				}
			}
			
			document.querySelectorAll('.item-actions .quantity-btn').forEach(btn => {
				btn.addEventListener('click', function() {
					const productId = this.getAttribute('data-product-id');
					const isIncrease = this.classList.contains('increase');
					const quantitySpan = document.getElementById(`qty-desk-${productId}`);
					let currentQty = parseInt(quantitySpan.textContent);
					
					if (isIncrease) {
						currentQty++;
					} else if (currentQty > 1) {
						currentQty--;
					}
					
					// Update both desktop and mobile quantity displays
					quantitySpan.textContent = currentQty;
					const mobileQtySpan = document.getElementById(`qty-${productId}`);
					if (mobileQtySpan) {
						mobileQtySpan.textContent = currentQty;
					}
					
					// Update item totals
					const itemCard = this.closest('.cart-item');
					const priceText = itemCard.querySelector('.item-price').textContent;
					const price = parseFloat(priceText.replace('R', '').replace(/,/g, ''));
					const itemTotal = price * currentQty;
					
					const desktopTotal = document.getElementById(`total-${productId}`);
					if (desktopTotal) {
						desktopTotal.textContent = `R${itemTotal.toLocaleString('en-ZA', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
					}
					
					const mobileTotal = document.getElementById(`mobile-total-${productId}`);
					if (mobileTotal) {
						mobileTotal.textContent = `R${itemTotal.toLocaleString('en-ZA', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
					}
					
					// Update cart in database
					updateCart(productId, currentQty);
				});
			});
		
			document.querySelectorAll('.mobile-actions-row .quantity-btn').forEach(btn => {
				btn.addEventListener('click', function() {
					const productId = this.getAttribute('data-product-id');
					const isIncrease = this.classList.contains('increase');
					const quantitySpan = document.getElementById(`qty-${productId}`);
					let currentQty = parseInt(quantitySpan.textContent);
					
					if (isIncrease) {
						currentQty++;
					} else if (currentQty > 1) {
						currentQty--;
					}
					
					// Update both mobile and desktop quantity displays
					quantitySpan.textContent = currentQty;
					const deskQtySpan = document.getElementById(`qty-desk-${productId}`);
					if (deskQtySpan) {
						deskQtySpan.textContent = currentQty;
					}
					
					// Update item totals
					const itemCard = this.closest('.cart-item');
					const priceText = itemCard.querySelector('.mobile-price').textContent;
					const price = parseFloat(priceText.replace('R', '').replace(/,/g, ''));
					const itemTotal = price * currentQty;
					
					const desktopTotal = document.getElementById(`total-${productId}`);
					if (desktopTotal) {
						desktopTotal.textContent = `R${itemTotal.toLocaleString('en-ZA', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
					}
					
					const mobileTotal = document.getElementById(`mobile-total-${productId}`);
					if (mobileTotal) {
						mobileTotal.textContent = `R${itemTotal.toLocaleString('en-ZA', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
					}
					
					// Update cart in database
					updateCart(productId, currentQty);
				});
			});
			
			document.querySelectorAll('.remove-item').forEach(btn => {
				btn.addEventListener('click', function() {
					const productId = this.getAttribute('data-product-id');
					removeFromCart(productId);
				});
			});
			
			document.querySelectorAll('.remove-item-mobile').forEach(btn => {
				btn.addEventListener('click', function() {
					const productId = this.getAttribute('data-product-id');
					removeFromCart(productId);
				});
			});
			
			const checkoutBtn = document.getElementById('checkoutBtn');
			if (checkoutBtn) {
				checkoutBtn.addEventListener('click', function() {
					window.location.href = 'checkout.php';
				});
			}
		</script>
	</body>
</html>