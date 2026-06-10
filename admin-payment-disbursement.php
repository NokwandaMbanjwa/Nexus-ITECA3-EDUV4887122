<?php
require_once 'admin-auth.php';

if (!$is_super_admin && $admin_role !== 'payments') {
    header('Location: admin-dashboard.php?error=unauthorized');
    exit;
}

// Get completed orders that haven't been paid out yet
$stmt = $pdo->prepare("
    SELECT o.*, 
           sp.store_name, sp.full_name as seller_full_name, sp.user_id as seller_user_id,
           sp.bank_name, sp.account_holder_name, sp.account_number, sp.branch_code, sp.account_type,
           u.email as buyer_email,
           (SELECT COUNT(*) FROM order_items WHERE order_id = o.order_id) as item_count
    FROM orders o
    JOIN seller_profiles sp ON o.seller_id = sp.profile_id
    JOIN nexus_users u ON o.buyer_id = u.user_id
    WHERE o.payment_status = 'paid'
    AND o.payment_disbursed = FALSE 
    AND o.buyer_confirmed = 1
    ORDER BY o.created_at ASC
");
$stmt->execute();
$pending_payouts = $stmt->fetchAll();

$seller_totals = [];
foreach ($pending_payouts as $payout) {
    $seller_id = $payout['seller_id'];
    if (!isset($seller_totals[$seller_id])) {
        $seller_totals[$seller_id] = [
            'seller_name' => $payout['store_name'] ?: $payout['seller_full_name'] ?: 'Unknown Seller',
            'bank_name' => $payout['bank_name'],
            'account_holder_name' => $payout['account_holder_name'],
            'account_number' => substr($payout['account_number'], -4),
            'total_sales' => 0,
            'total_nexus_fee' => 0,
            'total_earnings' => 0,
            'orders' => []
        ];
    }
    
    $nexus_fee = $payout['total_amount'] * 0.10;
    $seller_earnings = $payout['total_amount'] - $nexus_fee;
    
    $seller_totals[$seller_id]['total_sales'] += $payout['total_amount'];
    $seller_totals[$seller_id]['total_nexus_fee'] += $nexus_fee;
    $seller_totals[$seller_id]['total_earnings'] += $seller_earnings;
    $seller_totals[$seller_id]['orders'][] = $payout;
}

// Calculate total platform revenue (sum of all 10% fees from completed orders)
$stmt = $pdo->prepare("
    SELECT SUM(total_amount * 0.10) as total_revenue
    FROM orders
    WHERE status = 'delivered' AND payment_status = 'paid'
");
$stmt->execute();
$total_revenue = $stmt->fetch()['total_revenue'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payout'])) {
    $seller_id = $_POST['seller_id'];
    $amount = $_POST['amount'];
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("
			UPDATE orders 
			SET payment_disbursed = TRUE 
			WHERE seller_id = ? 
			AND status = 'delivered' 
			AND payment_status = 'paid' 
			AND buyer_confirmed = 1
			AND payment_disbursed = FALSE
		");
        $stmt->execute([$seller_id]);
        
        foreach ($seller_totals[$seller_id]['orders'] as $order) {
            $nexus_fee = $order['total_amount'] * 0.10;
            $seller_earnings = $order['total_amount'] - $nexus_fee;
            
            $stmt = $pdo->prepare("
                INSERT INTO seller_payments (seller_id, order_id, total_amount, nexus_fee, seller_earnings, payment_status, payment_date, transaction_reference)
                VALUES (?, ?, ?, ?, ?, 'completed', CURDATE(), ?)
            ");
            $transaction_ref = 'PAY-' . strtoupper(uniqid());
            $stmt->execute([$seller_id, $order['order_id'], $order['total_amount'], $nexus_fee, $seller_earnings, $transaction_ref]);
        }
        
        $pdo->commit();
        
        $success = "Payment of R" . number_format($amount, 2) . " processed successfully for " . htmlspecialchars($seller_totals[$seller_id]['seller_name']);
        
        header("Location: admin-payment-disbursement.php?success=1");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Failed to process payout: " . $e->getMessage();
    }
}

$stmt = $pdo->prepare("
    SELECT sp.*, sp.store_name,
           (SELECT COUNT(*) FROM seller_payments WHERE seller_id = sp.profile_id) as payment_count,
           (SELECT SUM(seller_earnings) FROM seller_payments WHERE seller_id = sp.profile_id) as total_paid
    FROM seller_profiles sp
    WHERE sp.verification_status = 'approved'
    ORDER BY sp.store_name ASC
");
$stmt->execute();
$all_sellers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>Payment Disbursement | Admin</title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="basestyle.css">
		<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
		<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
		
		<style>
			.admin-container {
				padding: 30px 24px 60px;
				max-width: 1400px;
				margin: 0 auto;
			}
			
			.page-header {
				margin-bottom: 30px;
			}
			
			.page-header h1 {
				color: #b6b5d8;
				font-size: 32px;
			}
			
			.stats-grid {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
				gap: 20px;
				margin-bottom: 40px;
			}
			
			.stat-card {
				background: #131315;
				border: 1px solid #2a2a2a;
				border-radius: 12px;
				padding: 20px;
				text-align: center;
			}
			
			.stat-card h3 {
				font-size: 36px;
				color: #b6b5d8;
				margin-bottom: 5px;
			}
			
			.section-title {
				font-size: 24px;
				color: #fff;
				margin: 30px 0 20px;
				padding-bottom: 10px;
				border-bottom: 1px solid #2a2a2a;
			}
			
			.seller-card {
				background: #19191c;
				border: 1px solid #2a2a2a;
				border-radius: 12px;
				margin-bottom: 20px;
				overflow: hidden;
			}
			
			.seller-header {
				display: flex;
				justify-content: space-between;
				align-items: center;
				padding: 20px;
				background: #111;
				border-bottom: 1px solid #2a2a2a;
				flex-wrap: wrap;
				gap: 15px;
			}
			
			.seller-name {
				font-size: 18px;
				font-weight: 600;
				color: #fff;
			}
			
			.seller-details {
				padding: 20px;
			}
			
			.bank-info {
				background: #0e0e10;
				padding: 15px;
				border-radius: 8px;
				margin-bottom: 20px;
			}
			
			.bank-info p {
				margin: 5px 0;
				font-size: 13px;
			}
			
			.orders-table {
				width: 100%;
				border-collapse: collapse;
				margin-top: 15px;
			}
			
			.orders-table th,
			.orders-table td {
				padding: 10px;
				text-align: left;
				border-bottom: 1px solid #2a2a2a;
			}
			
			.orders-table th {
				background: #0a0a0a;
				color: #b6b5d8;
			}
			
			.total-row {
				background: #1a1a1a;
				font-weight: bold;
			}
			
			.btn-payout {
				background: #4caf50;
				color: white;
				border: none;
				padding: 10px 24px;
				border-radius: 8px;
				cursor: pointer;
				font-size: 14px;
				margin-top: 15px;
			}
			
			.btn-payout:hover {
				background: #45a049;
			}
			
			.empty-state {
				text-align: center;
				padding: 60px;
				background: #19191c;
				border-radius: 12px;
				color: #888;
			}
			
			.alert {
				padding: 12px;
				border-radius: 8px;
				margin-bottom: 20px;
			}
			
			.alert-success {
				background: rgba(76, 175, 80, 0.1);
				border: 1px solid #4caf50;
				color: #4caf50;
			}
			
			.alert-error {
				background: rgba(255, 68, 68, 0.1);
				border: 1px solid #ff4444;
				color: #ff4444;
			}
			
			.commission-note {
				background: rgba(143, 245, 255, 0.1);
				border: 1px solid #b6b5d8;
				border-radius: 8px;
				padding: 12px;
				margin-bottom: 20px;
				font-size: 13px;
			}
			
			.proof-link {
				color: #b6b5d8;
				text-decoration: none;
			}
			
			.proof-link:hover {
				text-decoration: underline;
			}
			
			@media (max-width: 768px) {
				.admin-container {
					padding: 25px 16px 40px;
				}
				.stats-grid {
					grid-template-columns: 1fr;
				}
				.orders-table {
					font-size: 12px;
					overflow-x: auto;
					display: block;
				}
			}
		</style>
	</head>

	<body>
		<?php include 'admin-header.php'; ?>
		<?php include 'admin-sidebar.php'; ?>
		
		<main class="admin-main">
			<div class="admin-container">
				<div class="page-header">
					<h1><i class="fas fa-money-bill-wave"></i> Payment Disbursement</h1>
					<p>Process seller payouts for completed orders (10% platform fee deducted)</p>
				</div>
				
				<?php if (isset($success)): ?>
					<div class="alert alert-success"><?php echo $success; ?></div>
				<?php endif; ?>
				
				<?php if (isset($error)): ?>
					<div class="alert alert-error"><?php echo $error; ?></div>
				<?php endif; ?>
				
				<div class="commission-note">
					<i class="fas fa-info-circle"></i> 
					<strong>Commission Structure:</strong> Nexus takes a 10% platform fee on every sale. 
					The amount shown for each seller is after the 10% deduction.
				</div>
				
				<!-- Stats -->
				<div class="stats-grid">
					<div class="stat-card">
						<h3><?php echo count($pending_payouts); ?></h3>
						<p>Pending Payouts</p>
					</div>
					<div class="stat-card">
						<h3><?php echo count($seller_totals); ?></h3>
						<p>Sellers Awaiting Payment</p>
					</div>
					<div class="stat-card">
						<h3>10%</h3>
						<p>Platform Fee</p>
					</div>
					<div class="stat-card">
						<h3>R<?php echo number_format($total_revenue, 2); ?></h3>
						<p>Total Platform Revenue</p>
					</div>
				</div>
				
				<!-- Completed Orders Section -->
				<h2 class="section-title">Completed Orders (Pending Payout)</h2>
				
				<?php if (empty($pending_payouts)): ?>
					<div class="empty-state">
						<i class="fas fa-check-circle" style="font-size: 48px; margin-bottom: 16px; color: #4caf50;"></i>
						<p>No pending payouts. All completed orders have been paid out.</p>
					</div>
				<?php else: ?>
					<div class="seller-card">
						<div class="seller-header">
							<span class="seller-name">All Completed Orders</span>
						</div>
						<div class="seller-details">
							<table class="orders-table">
								<thead>
									<tr>
										<th>Order #</th>
										<th>Seller</th>
										<th>Buyer</th>
										<th>Total Amount</th>
										<th>Nexus Fee (10%)</th>
										<th>Seller Earnings</th>
										<th>Payment Method</th>
										<th>Proof of Payment</th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ($pending_payouts as $order): ?>
										<?php $nexus_fee = $order['total_amount'] * 0.10; ?>
										<?php $seller_earnings = $order['total_amount'] - $nexus_fee; ?>
										<tr>
											<td>#<?php echo $order['order_number']; ?></td>
											<td><?php echo htmlspecialchars($order['store_name'] ?: $order['seller_full_name'] ?: 'Unknown'); ?></td>
											<td><?php echo htmlspecialchars($order['buyer_email']); ?></td>
											<td>R<?php echo number_format($order['total_amount'], 2); ?></td>
											<td>R<?php echo number_format($nexus_fee, 2); ?></td>
											<td><strong>R<?php echo number_format($seller_earnings, 2); ?></strong></td>
											<td>
												<?php
												$method = $order['payment_method'] ?? 'N/A';
												$icon = '';
												if ($method == 'card') $icon = '<i class="fas fa-credit-card"></i>';
												elseif ($method == 'ewallet') $icon = '<i class="fas fa-mobile-alt"></i>';
												elseif ($method == 'eft') $icon = '<i class="fas fa-university"></i>';
												echo $icon . ' ' . ucfirst($method);
												?>
											</td>
											<td>
												<?php if (!empty($order['proof_of_payment'])): ?>
													<a href="<?php echo htmlspecialchars($order['proof_of_payment']); ?>" target="_blank" class="proof-link">
														<i class="fas fa-file-image"></i> View Proof
													</a>
												<?php else: ?>
													<span style="color: #888;">Not uploaded</span>
												<?php endif; ?>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				<?php endif; ?>
				
				<!-- Pending Payouts Section (Grouped by Seller) -->
				<h2 class="section-title">Pending Seller Payouts</h2>
				
				<?php if (empty($seller_totals)): ?>
					<div class="empty-state">
						<i class="fas fa-check-circle" style="font-size: 48px; margin-bottom: 16px; color: #4caf50;"></i>
						<p>No pending payouts. All completed orders have been paid out.</p>
					</div>
				<?php else: ?>
					<?php foreach ($seller_totals as $seller_id => $seller): ?>
						<div class="seller-card">
							<div class="seller-header">
								<div>
									<span class="seller-name"><?php echo htmlspecialchars($seller['seller_name']); ?></span>
								</div>
								<div>
									<strong>Total to Pay:</strong> R<?php echo number_format($seller['total_earnings'], 2); ?>
								</div>
							</div>
							<div class="seller-details">
								<div class="bank-info">
									<h4><i class="fas fa-university"></i> Banking Details</h4>
									<p><strong>Bank:</strong> <?php echo htmlspecialchars($seller['bank_name'] ?: 'Not provided'); ?></p>
									<p><strong>Account Holder:</strong> <?php echo htmlspecialchars($seller['account_holder_name'] ?: 'Not provided'); ?></p>
									<p><strong>Account Number:</strong> ****<?php echo htmlspecialchars($seller['account_number']); ?></p>
								</div>
								
								<h4>Orders Summary</h4>
								<table class="orders-table">
									<thead>
										<tr>
											<th>Order #</th>
											<th>Order Date</th>
											<th>Total Amount</th>
											<th>Nexus Fee (10%)</th>
											<th>Seller Earnings</th>
											<th>Payment Method</th>
											<th>Proof</th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ($seller['orders'] as $order): ?>
											<?php $nexus_fee = $order['total_amount'] * 0.10; ?>
											<?php $seller_earnings = $order['total_amount'] - $nexus_fee; ?>
											<tr>
												<td>#<?php echo $order['order_number']; ?></td>
												<td><?php echo date('d M Y', strtotime($order['created_at'])); ?></td>
												<td>R<?php echo number_format($order['total_amount'], 2); ?></td>
												<td>R<?php echo number_format($nexus_fee, 2); ?></td>
												<td><strong>R<?php echo number_format($seller_earnings, 2); ?></strong></td>
												<td>
													<?php
													$method = $order['payment_method'] ?? 'N/A';
													$icon = '';
													if ($method == 'card') $icon = '<i class="fas fa-credit-card"></i>';
													elseif ($method == 'ewallet') $icon = '<i class="fas fa-mobile-alt"></i>';
													elseif ($method == 'eft') $icon = '<i class="fas fa-university"></i>';
													echo $icon . ' ' . ucfirst($method);
													?>
												</td>
												<td>
													<?php if (!empty($order['proof_of_payment'])): ?>
														<a href="<?php echo htmlspecialchars($order['proof_of_payment']); ?>" target="_blank" class="proof-link">
															<i class="fas fa-file-image"></i> View
														</a>
													<?php else: ?>
														<span style="color: #888;">N/A</span>
													<?php endif; ?>
												</td>
											</tr>
										<?php endforeach; ?>
										<tr class="total-row">
											<td colspan="4" style="text-align: right;"><strong>Total:</strong></td>
											<td colspan="3"><strong>R<?php echo number_format($seller['total_earnings'], 2); ?></strong></td>
										</tr>
									</tbody>
								</table>
								
								<form method="post" onsubmit="return confirm('Process payment of R<?php echo number_format($seller['total_earnings'], 2); ?> to <?php echo htmlspecialchars($seller['seller_name']); ?>?')">
									<input type="hidden" name="seller_id" value="<?php echo $seller_id; ?>">
									<input type="hidden" name="amount" value="<?php echo $seller['total_earnings']; ?>">
									<button type="submit" name="process_payout" class="btn-payout">
										<i class="fas fa-paper-plane"></i> Process Payout (R<?php echo number_format($seller['total_earnings'], 2); ?>)
									</button>
								</form>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
				
				<!-- Payout History Section -->
				<h2 class="section-title">Recent Payout History</h2>
				<div class="seller-card">
					<div class="seller-header">
						<span class="seller-name">All Sellers</span>
					</div>
					<div class="seller-details">
						<table class="orders-table">
							<thead>
								<tr>
									<th>Seller</th>
									<th>Transaction Reference</th>
									<th>Amount</th>
									<th>Payment Date</th>
								</tr>
							</thead>
							<tbody>
								<?php
								$stmt = $pdo->prepare("
									SELECT sp.*, s.store_name, s.full_name as seller_full_name, sp.seller_earnings, sp.payment_date, sp.transaction_reference
									FROM seller_payments sp
									JOIN seller_profiles s ON sp.seller_id = s.profile_id
									ORDER BY sp.payment_date DESC
									LIMIT 20
								");
								$stmt->execute();
								$payment_history = $stmt->fetchAll();
								?>
								<?php if (empty($payment_history)): ?>
									<tr>
										<td colspan="4" style="text-align: center;">No payment history yet</td>
									</tr>
								<?php else: ?>
									<?php foreach ($payment_history as $payment): ?>
										<tr>
											<td><?php echo htmlspecialchars($payment['store_name'] ?: $payment['seller_full_name'] ?: 'Unknown'); ?></td>
											<td><?php echo htmlspecialchars($payment['transaction_reference']); ?></td>
											<td>R<?php echo number_format($payment['seller_earnings'], 2); ?></td>
											<td><?php echo date('d M Y', strtotime($payment['payment_date'])); ?></td>
										</tr>
									<?php endforeach; ?>
								<?php endif; ?>
							</tbody>
						</table>
					</div>
				</div>
			</div>
		</main>
		
		<script>
			const hamburgerBtn = document.getElementById('hamburgerBtn');
			const adminSidebar = document.getElementById('adminSidebar');
			if (hamburgerBtn) {
				hamburgerBtn.addEventListener('click', () => adminSidebar.classList.toggle('open'));
			}
		</script>
	</body>
</html>