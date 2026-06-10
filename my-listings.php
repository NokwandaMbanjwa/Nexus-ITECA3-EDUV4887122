<?php
require_once 'config.php';

if (!isLoggedIn() || getUserType() !== 'seller') {
    header('Location: login.php');
    exit;
}

$user_id = getUserId();
$stmt = $pdo->prepare("SELECT profile_id FROM seller_profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$seller = $stmt->fetch();
$seller_id = $seller['profile_id'];

// Get all products by this seller
$stmt = $pdo->prepare("SELECT p.*, 
                   (SELECT COUNT(*) FROM product_images WHERE product_id = p.product_id) as image_count,
                   (SELECT image_url FROM product_images WHERE product_id = p.product_id AND is_main = 1 LIMIT 1) as product_image
                   FROM products p 
                   WHERE p.seller_id = ? 
                   ORDER BY p.created_at DESC");
$stmt->execute([$seller_id]);
$products = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>NEXUS| My Listings</title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="basestyle.css">
		<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
		<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
		
		<style>
			.listings-container {
				padding-top: 50px;
				padding-bottom: 80px;
			}

			.listings-wrapper {
				max-width: 1200px;
				margin: 0 auto;
				padding: 0 24px;
			}

			.listings-header {
				margin-bottom: 32px;
			}

			.stats-bar {
				display: flex;
				gap: 16px;
				margin-bottom: 32px;
				flex-wrap: wrap;
			}

			.stat-card {
				background: #111;
				border: 1px solid #2a2a2a;
				border-radius: 12px;
				padding: 16px 24px;
				min-width: 120px;
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

			.alert {
				padding: 12px 16px;
				border-radius: 8px;
				margin-bottom: 24px;
			}

			.alert-success {
				background: rgba(76, 175, 80, 0.1);
				border: 1px solid #4caf50;
				color: #4caf50;
				font-size: 13px;
			}

			.listings-grid {
				display: grid;
				grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
				gap: 20px;
			}

			.listing-card {
				background: #111;
				border: 1px solid #2a2a2a;
				border-radius: 12px;
				overflow: hidden;
				transition: all 0.2s;
			}

			.listing-card:hover {
				border-color: rgba(143, 245, 255, 0.3);
				transform: translateY(-2px);
			}

			.listing-image {
				aspect-ratio: 1 / 1;
				background: #0e0e10;
				display: flex;
				align-items: center;
				justify-content: center;
				color: #555;
				font-size: 32px;
				overflow: hidden;
			}

			.listing-info {
				padding: 16px;
			}

			.listing-title {
				font-size: 15px;
				font-weight: 500;
				color: #fff;
				margin-bottom: 8px;
				white-space: nowrap;
				overflow: hidden;
				text-overflow: ellipsis;
			}

			.listing-price {
				color: #8ff5ff;
				font-size: 16px;
				font-weight: 600;
				margin-bottom: 8px;
			}

			.listing-meta {
				display: flex;
				justify-content: space-between;
				align-items: center;
				margin-bottom: 12px;
				font-size: 12px;
				color: #666;
			}

			.listing-status {
				display: inline-block;
				padding: 4px 10px;
				border-radius: 20px;
				font-size: 11px;
				font-weight: 500;
			}

			.status-published {
				background: rgba(76, 175, 80, 0.15);
				color: #4caf50;
			}

			.status-pending {
				background: rgba(255, 193, 7, 0.15);
				color: #ffc107;
			}

			.status-draft {
				background: rgba(158, 158, 158, 0.15);
				color: #9e9e9e;
			}

			.status-rejected {
				background: rgba(244, 67, 54, 0.15);
				color: #f44336;
			}

			.listing-actions {
				display: flex;
				gap: 12px;
				margin-top: 12px;
			}

			.listing-actions a,
			.listing-actions button {
				flex: 1;
				text-align: center;
				padding: 8px 12px;
				border-radius: 6px;
				text-decoration: none;
				font-size: 12px;
				cursor: pointer;
				border: none;
				transition: all 0.2s;
			}

			.btn-edit {
				background: #1a1a1a;
				color: #8ff5ff;
				border: 1px solid #2a2a2a;
			}

			.btn-edit:hover {
				border-color: #8ff5ff;
			}

			.btn-delete {
				background: transparent;
				border: 1px solid #2a2a2a;
				color: #888;
			}

			.btn-delete:hover {
				border-color: #f44336;
				color: #f44336;
			}

			.empty-state {
				text-align: center;
				padding: 60px 0;
				background: #111;
				border: 1px solid #2a2a2a;
				border-radius: 16px;
			}

			.empty-state i {
				font-size: 48px;
				color: #8ff5ff;
				margin-bottom: 16px;
				opacity: 0.6;
			}

			.empty-state p {
				color: #888;
				font-size: 14px;
				margin-bottom: 20px;
			}

			.btn-primary {
				display: inline-block;
				background: #8ff5ff;
				color: #0a0a0a;
				padding: 12px 28px;
				border-radius: 40px;
				text-decoration: none;
				font-weight: 600;
				font-size: 14px;
				transition: all 0.2s;
			}

			.btn-primary:hover {
				background: #7de0ea;
				transform: translateY(-2px);
			}

			@media (max-width: 768px) {
				.listings-wrapper {
					padding: 0 16px;
				}

				.listings-header h1 {
					font-size: 24px;
				}

				.stats-bar {
					gap: 12px;
				}

				.stat-card {
					padding: 12px 16px;
					min-width: 100px;
				}

				.stat-card .stat-value {
					font-size: 22px;
				}
			}
		</style>
	</head>
	<body>
		<?php include 'header.php'; ?>
		
		<main class="listings-container">
			<div class="listings-wrapper">
				<div class="listings-header">
					<h1>My Listings</h1>
				</div>
				
				<?php if (isset($_SESSION['upload_success'])): ?>
					<div class="alert alert-success"><?php echo $_SESSION['upload_success']; unset($_SESSION['upload_success']); ?></div>
				<?php endif; ?>
				
				<?php if (isset($_GET['success'])): ?>
					<div class="alert alert-success">
						<?php 
						if ($_GET['success'] == 'submitted') echo "Your item has been submitted for admin review.";
						elseif ($_GET['success'] == 'published') echo "Item is now live on the marketplace!";
						elseif ($_GET['success'] == 'updated') echo "Item updated successfully!";
						elseif ($_GET['success'] == 'deleted') echo "Item deleted successfully!";
						elseif ($_GET['success'] == 'draft_saved') echo "Draft saved successfully!";
						?>
					</div>
				<?php endif; ?>
				
				<div class="stats-bar">
					<div class="stat-card">
						<div class="stat-label">Total Listings</div>
						<div class="stat-value"><?php echo count($products); ?></div>
					</div>
					<div class="stat-card">
						<div class="stat-label">Active</div>
						<div class="stat-value">
							<?php echo count(array_filter($products, function($p) { 
								return !$p['is_draft'] && $p['approval_status'] === 'approved'; 
							})); ?>
						</div>
					</div>
					<div class="stat-card">
						<div class="stat-label">Pending Review</div>
						<div class="stat-value">
							<?php echo count(array_filter($products, function($p) { 
								return !$p['is_draft'] && $p['approval_status'] === 'pending'; 
							})); ?>
						</div>
					</div>
				</div>
				
				<?php if (empty($products)): ?>
					<div class="empty-state">
						<i class="fas fa-box-open"></i>
						<p>You haven't listed any items yet.</p>
						<a href="post-items.php" class="btn-primary">Start Selling</a>
					</div>
				<?php else: ?>
					<div class="listings-grid">
						<?php foreach ($products as $product): ?>
							<div class="listing-card">
								<div class="listing-image">
									<?php if (!empty($product['product_image'])): ?>
										<img src="<?php echo htmlspecialchars($product['product_image']); ?>" 
											 alt="<?php echo htmlspecialchars($product['product_name']); ?>" 
											 style="width:100%; height:100%; object-fit:cover;">
									<?php else: ?>
										<i class="fas fa-image"></i>
									<?php endif; ?>
								</div>
								<div class="listing-info">
									<h3 class="listing-title"><?php echo htmlspecialchars($product['product_name']); ?></h3>
									<div class="listing-price">R<?php echo number_format($product['price'], 2); ?></div>
									<div class="listing-meta">
										<span>Qty: <?php echo $product['stock_quantity']; ?></span>
										<span class="listing-status status-<?php 
											echo $product['is_draft'] ? 'draft' : ($product['approval_status'] === 'approved' ? 'published' : $product['approval_status']); 
										?>">
											<?php 
											if ($product['is_draft']) echo "Draft";
											elseif ($product['approval_status'] === 'approved') echo "Published";
											elseif ($product['approval_status'] === 'pending') echo "Pending";
											else echo "Rejected";
											?>
										</span>
									</div>
									<div class="listing-actions">
										<a href="post-items.php?edit=<?php echo $product['product_id']; ?>" class="btn-edit">Edit</a>
										<button class="btn-delete" onclick="deleteListing(<?php echo $product['product_id']; ?>)">Delete</button>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</main>
		
		<?php include 'footer.php'; ?>
		
		<script type = "text/javascript" src = "utilities.js" ></script>
		<script>
			function deleteListing(productId) {
				if (confirm('Delete this listing? This action cannot be undone.')) {
					fetch('delete-listings.php', {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: 'product_id=' + productId
					})
					.then(response => response.json())
					.then(data => {
						if (data.success) window.location.href = 'my-listings.php?success=deleted';
						else alert('Error deleting listing');
					})
					.catch(() => alert('Error deleting listing'));
				}
			}
		</script>
	</body>
</html>