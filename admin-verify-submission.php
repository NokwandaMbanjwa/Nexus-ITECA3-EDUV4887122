<?php
require_once 'admin-auth.php';

if (!$is_super_admin && $admin_role !== 'verification') {
    header('Location: admin-dashboard.php?error=unauthorized');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $product_id = $_POST['product_id'] ?? 0;
    $reason = $_POST['reason'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    if ($action === 'approve') {
        $stmt = $pdo->prepare("UPDATE products SET approval_status = 'approved', listing_status = 'active', rejection_reason = NULL, review_notes = ? WHERE product_id = ?");
        $stmt->execute([$notes, $product_id]);
        
        $stmt = $pdo->prepare("SELECT p.*, sp.user_id, sp.store_name FROM products p JOIN seller_profiles sp ON p.seller_id = sp.profile_id WHERE p.product_id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, related_id) VALUES (?, 'product_verification', 'Product Approved', ?, ?)");
        $stmt->execute([$product['user_id'], 'Your product "' . $product['product_name'] . '" has been approved and is now live on the marketplace.', $product_id]);
        
        $success = "Product approved and published successfully!";
        
    } elseif ($action === 'reject') {
        $stmt = $pdo->prepare("UPDATE products SET approval_status = 'rejected', rejection_reason = ?, review_notes = ? WHERE product_id = ?");
        $stmt->execute([$reason, $notes, $product_id]);
        
        $stmt = $pdo->prepare("SELECT p.*, sp.user_id, sp.store_name FROM products p JOIN seller_profiles sp ON p.seller_id = sp.profile_id WHERE p.product_id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        $_SESSION['rejection_data'] = [
            'seller_id' => $product['user_id'],
            'seller_name' => $product['store_name'],
            'product_name' => $product['product_name'],
            'reason' => $reason
        ];
        
        header('Location: admin-messages.php?action=reject&user_id=' . $product['user_id'] . '&product=' . urlencode($product['product_name']) . '&reason=' . urlencode($reason));
        exit;
    }
}

$stmt = $pdo->prepare("
    SELECT p.*, sp.store_name, sp.user_id as seller_user_id,
           (SELECT COUNT(*) FROM product_images WHERE product_id = p.product_id) as image_count,
           (SELECT image_url FROM product_images WHERE product_id = p.product_id AND is_main = 1 LIMIT 1) as product_image
    FROM products p
    JOIN seller_profiles sp ON p.seller_id = sp.profile_id
    WHERE p.approval_status = 'pending' AND p.is_draft = FALSE
    ORDER BY p.created_at ASC
");
$stmt->execute();
$pending_products = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT p.*, sp.store_name
    FROM products p
    JOIN seller_profiles sp ON p.seller_id = sp.profile_id
    WHERE p.approval_status = 'approved'
    ORDER BY p.updated_at DESC
    LIMIT 20
");
$stmt->execute();
$approved_products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>Admin |Verify Seller Submissions</title>
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
				padding: 40px 24px 60px;
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

			.product-card {
				background: #19191c;
				border: 1px solid #2a2a2a;
				border-radius: 12px;
				margin-bottom: 20px;
				overflow: hidden;
			}

			.product-header {
				display: flex;
				justify-content: space-between;
				align-items: center;
				padding: 20px;
				background: #111;
				border-bottom: 1px solid #2a2a2a;
				flex-wrap: wrap;
				gap: 15px;
			}

			.product-info h3 {
				color: #fff;
				margin-bottom: 5px;
			}

			.product-info p {
				color: #888;
				font-size: 13px;
			}

			.product-body {
				padding: 20px;
				display: flex;
				gap: 20px;
				flex-wrap: wrap;
			}

			.product-image {
				width: 150px;
				height: 150px;
				background: #0e0e10;
				border-radius: 8px;
				overflow: hidden;
				flex-shrink: 0;
			}

			.product-image img {
				width: 100%;
				height: 100%;
				object-fit: cover;
			}

			.product-details {
				flex: 1;
			}

			.product-details p {
				color: #e5e5e5;
				margin-bottom: 10px;
				line-height: 1.5;
			}

			.product-price {
				font-size: 20px;
				color: #b6b5d8;
				font-weight: bold;
			}

			.action-buttons {
				display: flex;
				gap: 12px;
				margin-top: 20px;
				flex-wrap: wrap;
			}

			.btn-approve {
				background: #4caf50;
				color: white;
				border: none;
				padding: 10px 24px;
				border-radius: 8px;
				cursor: pointer;
			}

			.btn-reject {
				background: #ff4444;
				color: white;
				border: none;
				padding: 10px 24px;
				border-radius: 8px;
				cursor: pointer;
			}

			.reject-form {
				display: none;
				margin-top: 15px;
				padding: 15px;
				background: #0e0e10;
				border-radius: 8px;
			}

			.reject-form textarea {
				width: 100%;
				padding: 10px;
				background: #1a1a1a;
				border: 1px solid #2a2a2a;
				border-radius: 8px;
				color: #e5e5e5;
				margin-bottom: 10px;
			}

			.empty-state {
				text-align: center;
				padding: 60px;
				background: #19191c;
				border-radius: 12px;
				color: #888;
			}

			.status-badge {
				display: inline-block;
				padding: 4px 12px;
				border-radius: 20px;
				font-size: 12px;
				font-weight: 500;
			}

			.status-pending {
				background: rgba(255, 193, 7, 0.15);
				color: #ffc107;
			}

			@media (max-width: 768px) {
				.admin-container {
					padding: 80px 16px 40px;
				}

				.product-body {
					flex-direction: column;
				}

				.product-image {
					width: 100%;
					height: 200px;
				}

				.page-header h1 {
					font-size: 24px;
				}

				.section-title {
					font-size: 20px;
				}

				.action-buttons {
					flex-direction: column;
				}

				.btn-approve,
				.btn-reject {
					width: 100%;
					text-align: center;
				}

				.stats-grid {
					gap: 12px;
				}

				.stat-card {
					padding: 14px;
				}

				.stat-card h3 {
					font-size: 28px;
				}

				@media (max-width: 480px) {
					.admin-container {
						padding: 70px 12px 30px;
					}

					.product-body {
						padding: 14px;
					}

					.product-details p {
						font-size: 13px;
					}

					.product-price {
						font-size: 18px;
					}

					.product-info h3 {
						font-size: 16px;
					}

					.empty-state {
						padding: 40px 20px;
					}

					.reject-form {
						padding: 12px;
					}

					.reject-form textarea {
						font-size: 13px;
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
					<h1><i class="fas fa-file-alt"></i> Verify Seller Submissions</h1>
					<p>Review and approve products before they go live on the marketplace</p>
				</div>
				
				<div class="stats-grid">
					<div class="stat-card"><h3><?php echo count($pending_products); ?></h3><p>Pending Review</p></div>
					<div class="stat-card"><h3><?php echo count($approved_products); ?></h3><p>Approved (Last 20)</p></div>
				</div>
				
				<h2 class="section-title">Pending Product Reviews</h2>
				<?php if (empty($pending_products)): ?>
					<div class="empty-state">
						<i class="fas fa-check-circle" style="font-size: 48px; margin-bottom: 16px;"></i>
						<p>No pending product submissions</p>
					</div>
				<?php else: ?>
					<?php foreach ($pending_products as $product): ?>
						<div class="product-card">
							<div class="product-header">
								<div class="product-info">
									<h3><?php echo htmlspecialchars($product['product_name']); ?></h3>
									<p>Seller: <?php echo htmlspecialchars($product['store_name']); ?> | Submitted: <?php echo date('d M Y H:i', strtotime($product['created_at'])); ?></p>
								</div>
								<span class="status-badge status-pending">Pending Review</span>
							</div>
							<div class="product-body">
								<div class="product-image">
									<img src="<?php echo htmlspecialchars($product['product_image'] ?? 'https://via.placeholder.com/150x150/1f1f22/8ff5ff?text=Product'); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
								</div>
								<div class="product-details">
									<p><?php echo nl2br(htmlspecialchars(substr($product['product_description'] ?? 'No description', 0, 300))); ?></p>
									<div class="product-price">R<?php echo number_format($product['price'], 2); ?></div>
									<p><strong>Condition:</strong> <?php echo ucfirst($product['item_condition'] ?? 'Not specified'); ?></p>
									<p><strong>Category:</strong> <?php echo ucfirst($product['category'] ?? 'Uncategorized'); ?></p>
									<p><strong>Quantity:</strong> <?php echo $product['stock_quantity']; ?> available</p>
									<?php if ($product['discount_percentage'] > 0): ?>
										<p><strong>Discount:</strong> <?php echo $product['discount_percentage']; ?>% off</p>
									<?php endif; ?>
									<p><strong>Images:</strong> <?php echo $product['image_count']; ?> image(s) uploaded</p>
									
									<div class="action-buttons">
										<button class="btn-approve" onclick="approveProduct(<?php echo $product['product_id']; ?>)">✓ Approve & Publish</button>
										<button class="btn-reject" onclick="showRejectForm(<?php echo $product['product_id']; ?>)">✗ Reject</button>
									</div>
									<div class="reject-form" id="reject-form-<?php echo $product['product_id']; ?>">
										<textarea id="reason-<?php echo $product['product_id']; ?>" rows="3" placeholder="Reason for rejection..."></textarea>
										<textarea id="notes-<?php echo $product['product_id']; ?>" rows="2" placeholder="Internal notes (optional)"></textarea>
										<div>
											<button class="btn-reject" onclick="rejectProduct(<?php echo $product['product_id']; ?>)">Submit Rejection</button>
											<button class="btn-approve" style="background: #666;" onclick="hideRejectForm(<?php echo $product['product_id']; ?>)">Cancel</button>
										</div>
									</div>
								</div>
							</div>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</main>
		
		<form id="actionForm" method="post" style="display: none;">
			<input type="hidden" name="action" id="actionType">
			<input type="hidden" name="product_id" id="productId">
			<input type="hidden" name="reason" id="reasonText">
			<input type="hidden" name="notes" id="notesText">
		</form>
		
		<script type = "text/javascript" src = "utilities.js" ></script>
		<script>
			function approveProduct(productId) {
				if (confirm('Approve this product? It will appear on the Explore Feed immediately.')) {
					document.getElementById('actionType').value = 'approve';
					document.getElementById('productId').value = productId;
					document.getElementById('actionForm').submit();
				}
			}
			
			function showRejectForm(productId) {
				document.getElementById('reject-form-' + productId).style.display = 'block';
			}
			
			function hideRejectForm(productId) {
				document.getElementById('reject-form-' + productId).style.display = 'none';
			}
			
			function rejectProduct(productId) {
				const reason = document.getElementById('reason-' + productId).value;
				const notes = document.getElementById('notes-' + productId).value;
				if (!reason) { alert('Please provide a reason for rejection.'); return; }
				if (confirm('Reject this product? You will be redirected to message the seller.')) {
					document.getElementById('actionType').value = 'reject';
					document.getElementById('productId').value = productId;
					document.getElementById('reasonText').value = reason;
					document.getElementById('notesText').value = notes;
					document.getElementById('actionForm').submit();
				}
			}
		</script>
	</body>
</html>