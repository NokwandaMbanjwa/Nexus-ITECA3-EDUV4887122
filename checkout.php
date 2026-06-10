<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user_id = getUserId();
$user_type = getUserType();

$stmt = $pdo->prepare("
    SELECT c.*, p.product_name, p.product_description, p.price,
       (SELECT image_url FROM product_images WHERE product_id = p.product_id AND is_main = 1 LIMIT 1) as product_image,
       sp.store_name
    FROM cart c
    JOIN products p ON c.product_id = p.product_id
    LEFT JOIN seller_profiles sp ON p.seller_id = sp.profile_id
    WHERE c.user_id = ?
    ORDER BY c.added_at DESC
");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll();

$subtotal = 0;

foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

$total = $subtotal;
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>NEXUS | Checkout</title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="basestyle.css">
		<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
		<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
		
		<style>
			.checkout-page {
				padding: 50px 0 40px;
				width: 100%;
				max-width: 100%;
				overflow-x: hidden;
			}
			
			.checkout-container {
				max-width: 1000px;
				margin: 0 auto;
				padding: 0 24px;
				width: 100%;
				box-sizing: border-box;
			}
			
			.checkout-header {
				margin-bottom: 32px;
			}
			
			.checkout-header h1 {
				font-size: 32px;
				color: #8ff5ff;
				margin-bottom: 8px;
			}
			
			.checkout-header p {
				color: #888;
				font-size: 14px;
			}
			
			.checkout-layout {
				display: flex;
				flex-direction: column;
				gap: 32px;
				width: 100%;
			}
			
			.cart-items-section {
				background: transparent;
				border: none;
				padding: 0;
				width: 100%;
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
				width: 100%;
				box-sizing: border-box;
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
				min-width: 0;
			}
			
			.item-details h3 {
				font-size: 16px;
				font-weight: 600;
				color: #fff;
				margin-bottom: 6px;
				word-wrap: break-word;
			}
			
			.item-description {
				font-size: 13px;
				color: #888;
				line-height: 1.4;
				margin-bottom: 8px;
				max-width: 400px;
				word-wrap: break-word;
			}
			
			.item-price {
				font-size: 16px;
				font-weight: 600;
				color: #8ff5ff;
			}
			
			.item-quantity {
				font-size: 14px;
				color: #aaa;
				margin-top: 4px;
			}
			
			.item-total {
				font-size: 16px;
				font-weight: 600;
				color: #8ff5ff;
				min-width: 100px;
				text-align: right;
				flex-shrink: 0;
			}
			
			.mobile-item-total {
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
				box-sizing: border-box;
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
			
			.payment-methods {
				margin-top: 24px;
				width: 100%;
			}
			
			.payment-methods h3 {
				font-size: 16px;
				color: #fff;
				margin-bottom: 12px;
			}
			
			.payment-option {
				display: flex;
				align-items: center;
				gap: 12px;
				padding: 12px;
				background: #0e0e10;
				border: 1px solid #2a2a2a;
				border-radius: 12px;
				margin-bottom: 10px;
				cursor: pointer;
				transition: all 0.2s;
				width: 100%;
				box-sizing: border-box;
			}
			
			.payment-option:hover {
				border-color: #8ff5ff;
			}
			
			.payment-option.selected {
				border-color: #8ff5ff;
				background: rgba(143, 245, 255, 0.05);
			}
			
			.payment-option input {
				accent-color: #8ff5ff;
				width: 18px;
				height: 18px;
				cursor: pointer;
				flex-shrink: 0;
			}
			
			.payment-option label {
				flex: 1;
				cursor: pointer;
				color: #e5e5e5;
				font-size: 14px;
				display: flex;
				align-items: center;
				gap: 10px;
				min-width: 0;
			}
			
			.payment-option i {
				font-size: 20px;
				color: #8ff5ff;
				flex-shrink: 0;
			}
			
			.pay-btn {
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
				box-sizing: border-box;
			}
			
			.pay-btn:hover {
				background: #6dd5e0;
				transform: translateY(-2px);
			}
			
			.empty-cart {
				text-align: center;
				padding: 60px;
				background: #19191c;
				border-radius: 16px;
				width: 100%;
				box-sizing: border-box;
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
			
			.modal {
				display: none;
				position: fixed;
				top: 0;
				left: 0;
				right: 0;
				bottom: 0;
				background: rgba(0, 0, 0, 0.95);
				z-index: 1000;
				align-items: center;
				justify-content: center;
				overflow-y: auto;
				padding: 20px;
			}
			
			.modal.show {
				display: flex;
			}
			
			.modal-content {
				background: #19191c;
				border: 1px solid #2a2a2a;
				border-radius: 20px;
				padding: 32px;
				max-width: 500px;
				width: 100%;
				max-height: 90vh;
				overflow-y: auto;
				margin: auto;
				box-sizing: border-box;
			}
			
			.modal-header {
				display: flex;
				justify-content: space-between;
				align-items: center;
				margin-bottom: 20px;
				position: relative;
				top: 0;
				background: #19191c;
				padding-bottom: 10px;
			}
			
			.modal-header h2 {
				color: #8ff5ff;
				font-size: 20px;
				word-wrap: break-word;
			}
			
			.close-modal {
				background: none;
				border: none;
				color: #888;
				font-size: 28px;
				cursor: pointer;
				flex-shrink: 0;
			}
			
			.modal-footer {
				display: flex;
				gap: 12px;
				justify-content: flex-end;
				margin-top: 24px;
				position: relative;
				bottom: 0;
				background: #19191c;
				padding-top: 10px;
			}
			
			.modal-body {
				margin-bottom: 20px;
				flex: 1;
			}
			
			.payment-info {
				text-align: center;
				word-wrap: break-word;
			}
			
			.payment-info h3 {
				color: #fff;
				margin-bottom: 8px;
			}
			
			.payment-info p {
				color: #888;
				font-size: 14px;
			}
			
			#paymentDetails .form-group {
				text-align: left;
				margin-bottom: 16px;
			}
			
			#paymentDetails .form-group label {
				display: block;
				margin-bottom: 6px;
				color: #aaa;
				font-size: 13px;
			}
			
			#paymentDetails .form-group input {
				width: 100%;
				padding: 12px;
				background: #0e0e10;
				border: 1px solid #2a2a2a;
				border-radius: 8px;
				color: #e5e5e5;
				box-sizing: border-box;
			}
			
			#payment-form .form-group {
				text-align: left;
				margin-bottom: 16px;
			}

			#payment-form .form-group label {
				display: block;
				margin-bottom: 6px;
				color: #aaa;
				font-size: 13px;
			}

			#payment-form .form-group input {
				width: 100%;
				padding: 12px;
				background: #0e0e10;
				border: 1px solid #2a2a2a;
				border-radius: 8px;
				color: #e5e5e5;
				font-size: 14px;
				box-sizing: border-box;
			}

			#payment-form .form-group input:focus {
				outline: none;
				border-color: #8ff5ff;
			}
			#bankingAppStep1 .form-group input:focus,
			#bankingAppStep2 .form-group input:focus {
				outline: none;
				border-color: #8ff5ff;
			}

			#bankingAppStep1 .form-group input {
				width: 100%;
				padding: 12px;
				background: #0e0e10;
				border: 1px solid #2a2a2a;
				border-radius: 8px;
				color: #e5e5e5;
				font-size: 14px;
				box-sizing: border-box;
			}
			.card-input {
				width: 100%;
				padding: 12px;
				background: #0e0e10;
				border: 1px solid #2a2a2a;
				border-radius: 8px;
				color: #e5e5e5;
				font-size: 14px;
				box-sizing: border-box;
			}

			.card-input:focus {
				outline: none;
				border-color: #8ff5ff;
			}
						
			.form-row {
				display: grid;
				grid-template-columns: 1fr 1fr;
				gap: 16px;
			}
			
			.save-btn, .cancel-btn {
				padding: 10px 24px;
				border-radius: 8px;
				font-size: 14px;
				font-weight: 600;
				cursor: pointer;
			}
			
			.save-btn {
				background: #8ff5ff;
				color: #0e0e10;
				border: none;
			}
			
			.cancel-btn {
				background: transparent;
				border: 1px solid #2a2a2a;
				color: #aaa;
			}
			@media (max-width: 768px) {
				.checkout-page {
					padding: 100px 0 60px;
				}
				
				.checkout-container {
					padding: 0 16px;
				}
				
				.checkout-header h1 {
					font-size: 26px;
				}
				
				.cart-item {
					flex-direction: row;
					align-items: flex-start;
					gap: 12px;
					padding: 16px 0;
					border-bottom: 1px solid rgba(118, 117, 119, 0.08);
				}
				
				.item-image {
					width: 65px;
					height: 65px;
					border-radius: 10px;
				}
				
				.item-details {
					flex: 1;
					min-width: 0;
				}
				
				.item-details h3 {
					font-size: 14px;
					white-space: nowrap;
					overflow: hidden;
					text-overflow: ellipsis;
				}
				
				.item-description {
					font-size: 12px;
					display: -webkit-box;
					-webkit-line-clamp: 2;
					-webkit-box-orient: vertical;
					overflow: hidden;
					max-width: 100%;
					margin-bottom: 6px;
				}
				
				.item-price {
					font-size: 14px;
				}
				
				.item-quantity {
					font-size: 12px;
				}
				
				.item-total {
					display: none;
				}
				
				.mobile-item-total {
					display: block;
					font-size: 14px;
					font-weight: 600;
					color: #8ff5ff;
					margin-top: 6px;
				}
				
				.order-summary {
					max-width: 100%;
					margin: 0;
					border-radius: 12px;
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
				
				.payment-methods h3 {
					font-size: 14px;
				}
				
				.payment-option {
					padding: 10px 12px;
				}
				
				.payment-option label {
					font-size: 13px;
				}
				
				.payment-option i {
					font-size: 18px;
				}
				
				.pay-btn {
					padding: 16px;
					font-size: 15px;
				}

				.modal-content {
					padding: 24px 16px;
					border-radius: 16px;
					margin: 10px;
					max-height: 85vh;
				}
				
				.modal-header h2 {
					font-size: 18px;
				}
				
				.form-row {
					grid-template-columns: 1fr;
					gap: 0;
				}
				
				.test-card-info p strong {
					width: 120px;
					font-size: 12px;
				}
				
				.test-card-info p {
					font-size: 12px;
				}
				
				.test-card-info p span {
					font-size: 12px;
				}
				
				.save-btn, .cancel-btn {
					padding: 12px 20px;
					font-size: 14px;
					width: 100%;
				}
				
				.modal-footer {
					flex-direction: column;
					gap: 8px;
				}
				
				.cancel-btn {
					order: 2;
				}
				
				.save-btn {
					order: 1;
				}
			}
			
			@media (max-width: 480px) {
				.checkout-page {
					padding: 30px 0 40px;
				}
				
				.checkout-container {
					padding: 0 12px;
				}
				
				.checkout-header h1 {
					font-size: 22px;
				}
				
				.checkout-header p {
					font-size: 13px;
				}
				
				.cart-item {
					gap: 10px;
					padding: 14px 0;
				}
				
				.item-image {
					width: 55px;
					height: 55px;
					border-radius: 8px;
				}
				
				.item-details h3 {
					font-size: 13px;
				}
				
				.item-description {
					font-size: 11px;
					-webkit-line-clamp: 1;
				}
				
				.item-price {
					font-size: 13px;
				}
				
				.mobile-item-total {
					font-size: 13px;
				}
				
				.order-summary {
					padding: 16px 12px;
				}
				
				.pay-btn {
					padding: 14px;
					font-size: 14px;
				}
				
				.payment-option {
					padding: 8px 10px;
				}
				
				.payment-option label {
					font-size: 12px;
				}
				
				.modal-content {
					padding: 20px 12px;
				}
				
				.test-card-info p strong {
					width: 100px;
				}
			}
			
			@media (max-width: 360px) {
				.checkout-container {
					padding: 0 10px;
				}
				
				.item-image {
					width: 50px;
					height: 50px;
				}
				
				.item-details h3 {
					font-size: 12px;
				}
				
				.item-description {
					font-size: 10px;
				}
				
				.item-price,
				.mobile-item-total {
					font-size: 12px;
				}
				
				.test-card-info p strong {
					width: 90px;
					font-size: 11px;
				}
			}
		</style>
	</head>

	<body>
		<?php include 'header.php'; ?>
		
		<main class="checkout-page">
			<div class="checkout-container">
				<div class="checkout-header">
					<h1><i class="fas fa-shopping-bag"></i> Checkout</h1>
					<p>Review your items and complete your purchase</p>
				</div>
				
				<?php if (empty($cart_items)): ?>
					<div class="empty-cart">
						<i class="fas fa-shopping-cart"></i>
						<h2>Your cart is empty</h2>
						<p>Looks like you haven't added any items to your cart yet.</p>
						<a href="explore-feed.php" class="btn-primary" style="text-decoration: none;">Start Shopping</a>
					</div>
				<?php else: ?>
					<div class="checkout-layout">
						<div class="cart-items-section">
							<div class="cart-items-header">
								<h2>Order Items</h2>
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
											<div class="item-quantity">Qty: <?php echo $item['quantity']; ?></div>
											<div class="mobile-item-total">R<?php echo number_format($item['price'] * $item['quantity'], 2); ?></div>
										</div>
										<div class="item-total">
											R<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
										</div>
									</div>
								<?php endforeach; ?>
							</div>
						</div>

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
							<div style="background: #19191c; border: 1px solid #2a2a2a; border-radius: 16px; padding: 24px; max-width: 500px; margin: 0 auto 20px; width: 100%; box-sizing: border-box;">
								<h3 style="color: #fff; margin-bottom: 16px; font-size: 16px;">
									<i class="fas fa-truck"></i> Delivery Details
								</h3>
								
								<div class="form-group" style="text-align: left; margin-bottom: 16px;">
									<label style="display: block; margin-bottom: 6px; color: #aaa; font-size: 13px;">Delivery Address</label>
									<textarea id="deliveryAddress" name="delivery_address" rows="3" placeholder="Enter your delivery address or leave blank to use your default address" style="width: 100%; padding: 12px; background: #0e0e10; border: 1px solid #2a2a2a; border-radius: 8px; color: #e5e5e5; font-size: 14px; box-sizing: border-box; resize: vertical;"><?php 
										// Pre-fill with default address
										$stmt = $pdo->prepare("SELECT residential_address, city_town, province, postal_code FROM buyer_profiles WHERE user_id = ?");
										$stmt->execute([$user_id]);
										$addr = $stmt->fetch();
										if ($addr) {
											echo htmlspecialchars(implode(', ', array_filter([$addr['residential_address'], $addr['city_town'], $addr['province'], $addr['postal_code']])));
										}
									?></textarea>
									<p style="font-size: 11px; color: #666; margin-top: 6px;">Your default address is pre-filled. You can edit it for this order.</p>
								</div>
								
								<div class="form-group" style="text-align: left;">
									<label style="display: block; margin-bottom: 6px; color: #aaa; font-size: 13px;">Preferred Delivery Method</label>
									<select id="deliveryMethod" name="delivery_method" style="width: 100%; padding: 12px; background: #0e0e10; border: 1px solid #2a2a2a; border-radius: 8px; color: #e5e5e5; font-size: 14px; box-sizing: border-box;">
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
							</div>
							<!-- Payment Methods -->
							<div class="payment-methods">
								<h3>Select Payment Method</h3>
								
								<div class="payment-option selected" data-method="ewallet">
									<input type="radio" name="payment_method" value="ewallet" id="ewallet" checked>
									<label for="ewallet">
										<i class="fas fa-mobile-alt"></i>
										eWallet / Mobile Money
									</label>
								</div>
								
								<div class="payment-option" data-method="eft">
									<input type="radio" name="payment_method" value="eft" id="eft">
									<label for="eft">
										<i class="fas fa-university"></i>
										Instant EFT
									</label>
								</div>
								
								<div class="payment-option" data-method="card">
									<input type="radio" name="payment_method" value="card" id="card">
									<label for="card">
										<i class="fas fa-credit-card"></i>
										Credit / Debit Card
									</label>
								</div>
							</div>
							
							<button class="pay-btn" id="payBtn">
								<i class="fas fa-lock"></i> Proceed to Payment
							</button>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</main>
		
		<div class="modal" id="bankingModal">
			<div class="modal-content">
				<div class="modal-header">
					<h2 id="bankingModalTitle"><i class="fas fa-university"></i> NEXUS Banking Details</h2>
					<button class="close-modal" id="closeBankingModal">&times;</button>
				</div>
				<div class="modal-body">
					<div class="payment-info">
						<i class="fas fa-check-circle" style="color: #4caf50; font-size: 48px; margin-bottom: 16px;"></i>
						<h3>Complete Your Payment</h3>
						<p>Use the details below to make your payment. Your order will be processed once payment is confirmed.</p>
						
						<div class="test-card-info">
							<p><strong>Bank:</strong> <span>NEXUS Digital Bank</span></p>
							<p><strong>Account Holder:</strong> <span>NEXUS Marketplace (Pty) Ltd</span></p>
							<p><strong>Account Number:</strong> <span>6284 1927 3541</span></p>
							<p><strong>Branch Code:</strong> <span>205491</span></p>
							<p><strong>Reference:</strong> <span id="paymentReference">NEX-<?php echo strtoupper(substr(uniqid(), -8)); ?></span></p>
							<p><strong>Amount:</strong> <span>R<?php echo number_format($total, 2); ?></span></p>
						</div>
						
						<p style="margin-top: 16px; font-size: 13px;">Please use the reference number above when making payment.</p>
					</div>
				</div>
				<div class="modal-footer">
					<button class="cancel-btn" id="cancelBankingBtn">Cancel</button>
					<button class="save-btn" id="madePaymentBtn">I've Made Payment</button>
				</div>
			</div>
		</div>

		<div class="modal" id="uploadProofModal">
			<div class="modal-content">
				<div class="modal-header">
					<h2><i class="fas fa-upload"></i> Upload Proof of Payment</h2>
					<button class="close-modal" id="closeUploadProofModal">&times;</button>
				</div>
				<div class="modal-body">
					<div class="payment-info">
						<p style="margin-bottom: 16px;">Please upload a screenshot or PDF of your payment confirmation.</p>
						
						<div class="test-card-info" style="margin-bottom: 20px;">
							<p><strong>Amount Paid:</strong> <span>R<?php echo number_format($total, 2); ?></span></p>
							<p><strong>Reference:</strong> <span id="uploadRef"></span></p>
						</div>
						
						<form id="proofForm" enctype="multipart/form-data">
							<div class="form-group" style="text-align: left;">
								<label style="color: #aaa; font-size: 13px; display: block; margin-bottom: 6px;">
									Select Proof of Payment
								</label>
								<input type="file" id="proofOfPayment" name="proof_of_payment" accept="image/*,.pdf" style="width: 100%; padding: 10px; background: #0e0e10; border: 1px solid #2a2a2a; border-radius: 8px; color: #e5e5e5; box-sizing: border-box;" required>
								<p style="font-size: 11px; color: #666; margin-top: 6px;">Accepted: JPG, PNG, PDF. Max: 5MB</p>
							</div>
						</form>
					</div>
				</div>
				<div class="modal-footer">
					<button class="cancel-btn" id="cancelUploadBtn">Cancel</button>
					<button class="save-btn" id="confirmUploadBtn">Confirm Payment</button>
				</div>
			</div>
		</div>
		
		<div class="modal" id="cardModal">
			<div class="modal-content">
				<div class="modal-header">
					<h2><i class="fas fa-credit-card"></i> Secure Card Payment</h2>
					<button class="close-modal" id="closeCardModal">&times;</button>
				</div>
				<div class="modal-body">
					<form id="payment-form">
						<div class="form-group">
							<label>Cardholder Name</label>
							<input type="text" id="cardholderName" class="card-input">
						</div>
						<div class="form-group">
							<label>Card Number</label>
							<input type="text" id="cardNumber" class="card-input">
						</div>
						<div class="form-row">
							<div class="form-group">
								<label>Expiry Date</label>
								<input type="text" id="cardExpiry" placeholder="MM/YY" class="card-input">
							</div>
							<div class="form-group">
								<label>CVV</label>
								<input type="text" id="cardCvv" class="card-input">
							</div>
						</div>
					</form>
				</div>
				<div class="modal-footer">
					<button class="cancel-btn" id="cancelCardBtn">Cancel</button>
					<button class="save-btn" id="confirmCardBtn">Pay R<?php echo number_format($total, 2); ?></button>
				</div>
			</div>
		</div>
		
		<div class="modal" id="bankingAppModal">
			<div class="modal-content" style="max-width: 420px;">
				<div class="modal-header">
					<h2>Banking App</h2>
					<button class="close-modal" id="closeBankingAppModal">&times;</button>
				</div>
				<div class="modal-body">
					<div id="bankingAppStep1">
						<div class="payment-info">
							<i class="fas fa-lock" style="color: #8ff5ff; font-size: 48px; margin-bottom: 16px;"></i>
							<h3>Secure Login</h3>
							<p>Enter your banking app password to authorize this payment</p>
							
							<div class="form-group" style="text-align: left; margin-top: 20px;">
								<label>Password</label>
								<input type="password" id="bankingPassword" placeholder="Enter your banking password" style="width: 100%; padding: 12px; background: #0e0e10; border: 1px solid #2a2a2a; border-radius: 8px; color: #e5e5e5; font-size: 14px; box-sizing: border-box;">
							</div>
							
							<div style="display: flex; gap: 12px; margin-top: 20px;">
								<button class="cancel-btn" id="cancelBankingAppBtn" style="flex: 1;">Cancel</button>
								<button class="save-btn" id="loginBankingAppBtn" style="flex: 1;">Login</button>
							</div>
						</div>
					</div>
					
					<div id="bankingAppStep2" style="display: none;">
						<div class="payment-info">
							<i class="fas fa-shopping-cart" style="color: #ffc107; font-size: 48px; margin-bottom: 16px;"></i>
							<h3>Confirm Payment</h3>
							<p style="margin-bottom: 20px;">A purchase of <strong style="color: #8ff5ff; font-size: 18px;">R<?php echo number_format($total, 2); ?></strong> on the Nexus shopping platform has been initiated.</p>
							<p style="color: #aaa; font-size: 13px;">If this was you, press the confirm button.</p>
							
							<div style="display: flex; gap: 12px; margin-top: 24px;">
								<button class="cancel-btn" id="declineBankingAppBtn" style="flex: 1;">Cancel</button>
								<button class="save-btn" id="confirmBankingAppBtn" style="flex: 1;">Confirm Payment</button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- Payment Declined Modal -->
		<div class="modal" id="paymentDeclinedModal">
			<div class="modal-content" style="text-align: center; max-width: 420px;">
				<i class="fas fa-times-circle" style="color: #ff4444; font-size: 64px; margin-bottom: 20px;"></i>
				<h2 style="color: #ff4444;">Payment Cancelled</h2>
				<p>The transaction was cancelled and your card was not charged.</p>
				<div class="modal-buttons" style="margin-top: 24px;">
					<button class="save-btn" id="returnToCheckoutBtn">Return to Checkout</button>
				</div>
			</div>
		</div>
		<div class="modal" id="successModal">
			<div class="modal-content" style="text-align: center;">
				<i class="fas fa-check-circle" style="color: #4caf50; font-size: 64px; margin-bottom: 20px;"></i>
				<h2>Payment Successful!</h2>
				<p>Your order has been placed successfully.</p>
				<div class="modal-buttons" style="margin-top: 24px;">
					<button class="save-btn" id="continueShoppingBtn">Continue Shopping</button>
				</div>
			</div>
		</div>
        
        <?php include 'footer.php'; ?>
        
        <script type="text/javascript" src="utilities.js"></script>
        <script>
			var selectedPaymentMethod = 'ewallet';
			var paymentReference = document.getElementById('paymentReference').textContent;

			// Payment method selection
			document.querySelectorAll('.payment-option').forEach(function(option) {
				option.addEventListener('click', function() {
					document.querySelectorAll('.payment-option').forEach(function(opt) { opt.classList.remove('selected'); });
					this.classList.add('selected');
					var radio = this.querySelector('input');
					if (radio) radio.checked = true;
					selectedPaymentMethod = this.getAttribute('data-method');
				});
			});

			document.getElementById('payBtn').addEventListener('click', function() {
				if (selectedPaymentMethod === 'card') {
					document.getElementById('cardModal').classList.add('show');
				} else {
					var title = document.getElementById('bankingModalTitle');
					title.innerHTML = selectedPaymentMethod === 'ewallet' 
						? '<i class="fas fa-mobile-alt"></i> eWallet / Mobile Money Payment'
						: '<i class="fas fa-university"></i> Instant EFT Payment';
					document.getElementById('bankingModal').classList.add('show');
				}
			});

			document.getElementById('closeBankingModal').addEventListener('click', function() {
				document.getElementById('bankingModal').classList.remove('show');
			});
			document.getElementById('cancelBankingBtn').addEventListener('click', function() {
				document.getElementById('bankingModal').classList.remove('show');
			});

			document.getElementById('madePaymentBtn').addEventListener('click', function() {
				document.getElementById('bankingModal').classList.remove('show');
				document.getElementById('uploadRef').textContent = paymentReference;
				document.getElementById('proofOfPayment').value = '';
				document.getElementById('uploadProofModal').classList.add('show');
			});

			document.getElementById('closeUploadProofModal').addEventListener('click', function() {
				document.getElementById('uploadProofModal').classList.remove('show');
			});
			document.getElementById('cancelUploadBtn').addEventListener('click', function() {
				document.getElementById('uploadProofModal').classList.remove('show');
			});

			document.getElementById('confirmUploadBtn').addEventListener('click', function() {
				var proofFile = document.getElementById('proofOfPayment');
				if (!proofFile.files.length) {
					alert('Please select a file to upload');
					return;
				}
				
				var file = proofFile.files[0];
				var maxSize = 5 * 1024 * 1024;
				if (file.size > maxSize) {
					alert('File size must be less than 5MB');
					return;
				}
				
				var allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
				if (allowedTypes.indexOf(file.type) === -1) {
					alert('Only JPG, PNG, and PDF files are allowed');
					return;
				}

				var confirmBtn = document.getElementById('confirmUploadBtn');
				confirmBtn.disabled = true;
				confirmBtn.textContent = 'Processing...';
				
				var formData = new FormData();
				formData.append('proof_of_payment', file);
				formData.append('payment_method', selectedPaymentMethod);
				formData.append('reference', paymentReference);
				formData.append('amount', '<?php echo $total; ?>');
				
				fetch('process-payment.php', {
					method: 'POST',
					body: formData
				})
				.then(function(response) { return response.json(); })
				.then(function(uploadData) {
					if (uploadData.success) {
						return fetch('create-order.php', {
							method: 'POST',
							headers: {
								'Content-Type': 'application/x-www-form-urlencoded',
								'X-Requested-With': 'XMLHttpRequest'
							},
							body: 'payment_method=' + selectedPaymentMethod + 
								  '&reference=' + paymentReference + 
								  '&proof_path=' + uploadData.file_path +
								  '&delivery_method=' + document.getElementById('deliveryMethod').value +
								  '&delivery_address=' + encodeURIComponent(document.getElementById('deliveryAddress').value)
						});
					} else {
						throw new Error(uploadData.message || 'Upload failed');
					}
				})
				.then(function(response) { return response.json(); })
				.then(function(data) {
					confirmBtn.disabled = false;
					confirmBtn.textContent = 'Confirm Payment';
					
					if (data.success) {
						document.getElementById('uploadProofModal').classList.remove('show');
						document.getElementById('successModal').classList.add('show');
						// Clear cart display
						document.getElementById('cartItemsContainer').innerHTML = '';
						document.getElementById('subtotal').textContent = 'R0.00';
						document.getElementById('totalAmount').textContent = 'R0.00';
					} else {
						alert(data.message || 'Payment processing failed. Please try again.');
					}
				})
				.catch(function(error) {
					confirmBtn.disabled = false;
					confirmBtn.textContent = 'Confirm Payment';
					alert('Error: ' + error.message);
				});
			});
			
			document.getElementById('cardNumber').addEventListener('input', function(e) {
				var value = this.value.replace(/\s/g, '').replace(/[^0-9]/g, '');
				value = value.substring(0, 16);
				var formatted = '';
				for (var i = 0; i < value.length; i++) {
					if (i > 0 && i % 4 === 0) {
						formatted += ' ';
					}
					formatted += value[i];
				}
				this.value = formatted;
			});

			document.getElementById('cardExpiry').addEventListener('input', function(e) {
				var value = this.value.replace(/[^0-9]/g, '');
				
				value = value.substring(0, 4);

				if (value.length > 2) {
					value = value.substring(0, 2) + '/' + value.substring(2);
				}
				
				this.value = value;
			});

			document.getElementById('cardCvv').addEventListener('input', function(e) {
				this.value = this.value.replace(/[^0-9]/g, '').substring(0, 3);
			});

			document.getElementById('cardholderName').addEventListener('input', function(e) {
				this.value = this.value.replace(/[^a-zA-Z\s]/g, '');
			});
			document.getElementById('closeCardModal').addEventListener('click', function() {
				document.getElementById('cardModal').classList.remove('show');
			});
			document.getElementById('cancelCardBtn').addEventListener('click', function() {
				document.getElementById('cardModal').classList.remove('show');
			});

			// Card payment
			document.getElementById('confirmCardBtn').addEventListener('click', function() {
				var cardholderName = document.getElementById('cardholderName').value.trim();
				var cardNumber = document.getElementById('cardNumber').value.trim();
				var cardExpiry = document.getElementById('cardExpiry').value.trim();
				var cardCvv = document.getElementById('cardCvv').value.trim();
				
				if (!cardholderName || !cardNumber || !cardExpiry || !cardCvv) {
					alert('Please fill in all card details');
					return;
				}
				
				document.getElementById('cardModal').classList.remove('show');
				document.getElementById('bankingAppStep1').style.display = 'block';
				document.getElementById('bankingAppStep2').style.display = 'none';
				document.getElementById('bankingPassword').value = '';
				document.getElementById('bankingAppModal').classList.add('show');
			});

			document.getElementById('closeBankingAppModal').addEventListener('click', function() {
				document.getElementById('bankingAppModal').classList.remove('show');
			});
			document.getElementById('cancelBankingAppBtn').addEventListener('click', function() {
				document.getElementById('bankingAppModal').classList.remove('show');
			});

			document.getElementById('loginBankingAppBtn').addEventListener('click', function() {
				var password = document.getElementById('bankingPassword').value.trim();
				
				if (!password) {
					alert('Please enter your banking password');
					return;
				}

				var loginBtn = document.getElementById('loginBankingAppBtn');
				loginBtn.disabled = true;
				loginBtn.textContent = 'Verifying...';
				
				setTimeout(function() {
					document.getElementById('bankingAppStep1').style.display = 'none';
					document.getElementById('bankingAppStep2').style.display = 'block';
					loginBtn.disabled = false;
					loginBtn.textContent = 'Login';
				}, 1000);
			});

			// Banking App - Confirm Payment
			document.getElementById('confirmBankingAppBtn').addEventListener('click', function() {
				var confirmBtn = document.getElementById('confirmBankingAppBtn');
				confirmBtn.disabled = true;
				confirmBtn.textContent = 'Processing...';
				
				document.getElementById('bankingAppModal').classList.remove('show');
				
				// Create order
				fetch('create-order.php', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
						'X-Requested-With': 'XMLHttpRequest'
					},
					body: 'payment_method=card&reference=CARD-' + Date.now() + 
						  '&delivery_method=' + document.getElementById('deliveryMethod').value +
						  '&delivery_address=' + encodeURIComponent(document.getElementById('deliveryAddress').value)
				})
				.then(function(response) { return response.json(); })
				.then(function(data) {
					confirmBtn.disabled = false;
					confirmBtn.textContent = 'Confirm Payment';
					
					if (data.success) {
						document.getElementById('successModal').classList.add('show');
						document.getElementById('cartItemsContainer').innerHTML = '';
						document.getElementById('subtotal').textContent = 'R0.00';
						document.getElementById('totalAmount').textContent = 'R0.00';
					} else {
						alert(data.message || 'Payment processing failed. Please try again.');
					}
				})
				.catch(function(error) {
					confirmBtn.disabled = false;
					confirmBtn.textContent = 'Confirm Payment';
					alert('Error processing payment. Please try again.');
				});
			});

			// Banking App - Decline Payment
			document.getElementById('declineBankingAppBtn').addEventListener('click', function() {
				document.getElementById('bankingAppModal').classList.remove('show');
				document.getElementById('paymentDeclinedModal').classList.add('show');
			});

			document.getElementById('returnToCheckoutBtn').addEventListener('click', function() {
				document.getElementById('paymentDeclinedModal').classList.remove('show');
			});

			document.getElementById('paymentDeclinedModal').addEventListener('click', function(e) {
				if (e.target === this) {
					this.classList.remove('show');
				}
			});

			document.getElementById('continueShoppingBtn').addEventListener('click', function() {
				document.getElementById('successModal').classList.remove('show');
				window.location.href = 'explore-feed.php';
			});

			window.addEventListener('click', function(e) {
				[document.getElementById('bankingModal'), document.getElementById('uploadProofModal'), 
				 document.getElementById('cardModal'), document.getElementById('successModal'),
				 document.getElementById('bankingAppModal'), document.getElementById('paymentDeclinedModal')].forEach(function(modal) {
					if (e.target === modal) {
						modal.classList.remove('show');
					}
				});
			});
		</script>
    </body>
</html>