<?php
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user_id = getUserId();
$user_type = getUserType();

$my_seller_id = null;
if ($user_type === 'seller') {
    $stmt = $pdo->prepare("SELECT profile_id FROM seller_profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $my_profile = $stmt->fetch();
    $my_seller_id = $my_profile ? $my_profile['profile_id'] : null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unfollow') {
    $following_id = $_POST['following_id'] ?? 0;
    
    $stmt = $pdo->prepare("DELETE FROM user_follows WHERE follower_id = ? AND following_id = ?");
    $stmt->execute([$user_id, $following_id]);
    
    header('Location: followlist.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT sp.*, 
           u.email,
           COUNT(DISTINCT p.product_id) as total_products,
           (SELECT COUNT(*) FROM user_follows WHERE following_id = sp.profile_id) as follower_count
    FROM user_follows uf
    JOIN seller_profiles sp ON uf.following_id = sp.profile_id
    JOIN nexus_users u ON sp.user_id = u.user_id
    LEFT JOIN products p ON sp.profile_id = p.seller_id AND p.listing_status = 'active'
    WHERE uf.follower_id = ?
    GROUP BY sp.profile_id
    ORDER BY uf.followed_at DESC
");
$stmt->execute([$user_id]);
$followed_sellers = $stmt->fetchAll();

$has_follows = count($followed_sellers) > 0;
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>NEXUS | Follow List</title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="basestyle.css">
		<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
		<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">

		<style>	
			.follow-page {
				padding: 50px 24px 60px;
			}

			.follow-container {
				max-width: 1000px;
				margin: 0 auto;
				padding: 0 24px;
			}

			.follow-header {
				margin-bottom: 32px;
			}

			.follow-header h1 {
				font-size: 32px;
				color: #8ff5ff;
				margin-bottom: 8px;
			}

			.follow-header p {
				font-size: 16px;
				color: #adaaad;
			}

			.empty-state {
				text-align: center;
				padding: 60px 20px;
				background: #19191c;
				border-radius: 16px;
				border: 1px solid #2a2a2a;
			}

			.empty-icon {
				font-size: 64px;
				color: #8ff5ff;
				margin-bottom: 20px;
				opacity: 0.6;
			}

			.empty-state h2 {
				color: #f9f5f8;
				font-size: 24px;
				margin-bottom: 12px;
			}

			.empty-state p {
				font-size: 16px;
				color: #adaaad;
				margin-bottom: 24px;
			}

			.discover-link {
				display: inline-block;
				background: #8ff5ff;
				color: #0e0e10;
				padding: 12px 32px;
				border-radius: 12px;
				text-decoration: none;
				font-weight: 600;
				transition: all 0.3s;
			}

			.discover-link:hover {
				background: #6dd5e0;
				transform: translateY(-2px);
			}

			.follow-list {
				display: flex;
				flex-direction: column;
				gap: 32px;
			}

			.follow-item {
				background: transparent;
				display: flex;
				gap: 16px;
				padding: 20px 0;
				border-bottom: 1px solid rgba(118, 117, 119, 0.1);
			}

			.follow-item:hover {
				border-color: rgba(143, 245, 255, 0.3);
				transform: translateY(-2px);
			}

			.user-info {
				display: flex;
				align-items: flex-start;
				gap: 20px;
				flex: 1;
				flex-wrap: wrap;
			}

			.user-avatar {
				width: 80px;
				height: 80px;
				background: #0e0e10;
				border-radius: 50%;
				display: flex;
				align-items: center;
				justify-content: center;
				font-size: 32px;
				color: #8ff5ff;
				border: 2px solid #2a2a2a;
				flex-shrink: 0;
			}

			.user-details {
				flex: 1;
			}

			.user-details h3 {
				font-size: 20px;
				font-weight: 600;
				color: #f9f5f8;
				margin-bottom: 6px;
			}

			.user-details .store-name {
				font-size: 14px;
				color: #8ff5ff;
				margin-bottom: 8px;
			}

			.user-details .bio {
				font-size: 14px;
				color: #adaaad;
				margin-bottom: 12px;
				line-height: 1.5;
			}

			.user-stats {
				display: flex;
				gap: 20px;
				margin-top: 8px;
				font-size: 13px;
				color: #e5e5e5;
			}

			.user-stats span {
				display: inline-flex;
				align-items: center;
				gap: 6px;
			}

			.user-stats i {
				color: #8ff5ff;
				font-size: 12px;
			}

			.follow-actions {
				display: flex;
				flex-direction: row;
				align-items: flex-start;
				gap: 12px;
			}

			.message-btn {
				padding: 10px 24px;
				border-radius: 10px;
				font-size: 14px;
				font-weight: 500;
				background: transparent;
				border: 1px solid #8ff5ff;
				color: #8ff5ff;
				cursor: pointer;
				transition: all 0.3s;
			}

			.message-btn:hover {
				background: rgba(143, 245, 255, 0.1);
				transform: translateY(-2px);
			}

			.unfollow-btn {
				padding: 10px 24px;
				border-radius: 10px;
				font-size: 14px;
				font-weight: 500;
				background: transparent;
				border: 1px solid #ff6b6b;
				color: #ff6b6b;
				cursor: pointer;
				transition: all 0.3s;
			}

			.unfollow-btn:hover {
				background: rgba(255, 107, 107, 0.1);
				transform: translateY(-2px);
			}

			@media (max-width: 768px) {
				.follow-page {
					padding: 40px 16px;
				}
				
				.follow-container {
					padding: 0;
				}
				
				.follow-header h1 {
					font-size: 26px;
				}
				
				.follow-header p {
					font-size: 14px;
				}
				
				.follow-list {
					gap: 0;
				}
				
				.follow-item {
					flex-direction: row;
					align-items: flex-start;
					gap: 14px;
					padding: 16px 0;
					flex-wrap: wrap;
				}
				
				.user-info {
					flex-direction: row;
					align-items: flex-start;
					text-align: left;
					gap: 14px;
					flex: 1;
					min-width: 0;
					flex-wrap: wrap;
				}
				
				.user-avatar {
					width: 56px;
					height: 56px;
					font-size: 24px;
				}
				
				.user-details {
					text-align: left;
					flex: 1;
					min-width: 200px;
				}
				
				.user-details h3 {
					font-size: 16px;
					margin-bottom: 4px;
				}
				
				.user-details .store-name {
					font-size: 13px;
					margin-bottom: 4px;
				}
				
				.user-details .bio {
					font-size: 13px;
					margin-bottom: 10px;
					line-height: 1.4;
					display: -webkit-box;
					-webkit-line-clamp: 2;
					-webkit-box-orient: vertical;
					overflow: hidden;
				}
				
				.user-stats {
					display: none;
				}
				
				.follow-actions {
					flex-direction: row;
					justify-content: flex-start;
					gap: 10px;
					width: 100%;
					padding-left: 70px;
				}
				
				.message-btn,
				.unfollow-btn {
					padding: 8px 18px;
					font-size: 13px;
					white-space: nowrap;
				}
			}

			@media (max-width: 480px) {
				.follow-page {
					padding: 30px 12px 30px;
				}
				
				.follow-header h1 {
					font-size: 22px;
				}
				
				.follow-header p {
					font-size: 13px;
				}
				
				.follow-item {
					gap: 12px;
					padding: 14px 0;
				}
				
				.user-info {
					gap: 12px;
				}
				
				.user-avatar {
					width: 48px;
					height: 48px;
					font-size: 20px;
				}
				
				.user-details h3 {
					font-size: 15px;
				}
				
				.user-details .store-name {
					font-size: 12px;
				}
				
				.user-details .bio {
					font-size: 12px;
					margin-bottom: 8px;
				}
				
				.follow-actions {
					padding-left: 60px;
					gap: 8px;
				}
				
				.message-btn,
				.unfollow-btn {
					padding: 7px 14px;
					font-size: 12px;
				}
			}

			@media (max-width: 360px) {
				.follow-header h1 {
					font-size: 20px;
				}
				
				.user-avatar {
					width: 42px;
					height: 42px;
					font-size: 18px;
				}
				
				.user-details h3 {
					font-size: 14px;
				}
				
				.user-details .bio {
					font-size: 11px;
					-webkit-line-clamp: 1;
				}
				
				.follow-actions {
					padding-left: 54px;
				}
				
				.message-btn,
				.unfollow-btn {
					padding: 6px 12px;
					font-size: 11px;
				}
			}
		</style>
	</head>

	<body>
		<?php include 'header.php'; ?>
		
		<main class="follow-page">
			<div class="follow-container">
				<div class="follow-header">
					<h1><i class="fas fa-users"></i> Follow List</h1>
					<p>Sellers you follow on Nexus</p>
				</div>

				<?php if (!$has_follows): ?>
					<div class="empty-state">
						<div class="empty-icon">
							<i class="fas fa-user-plus"></i>
						</div>
						<h2>No followed sellers yet</h2>
						<p>Follow sellers to see their latest listings and updates in your feed.</p>
						<a href="explore-feed.php" class="discover-link">Discover Sellers</a>
					</div>
				<?php else: ?>
					<div class="follow-list">
						<?php foreach ($followed_sellers as $seller): ?>
							<div class="follow-item" data-seller-id="<?php echo $seller['profile_id']; ?>">
								<div class="user-info">
									<div class="user-avatar">
										<i class="fas fa-store"></i>
									</div>
									<div class="user-details">
										<h3><?php echo htmlspecialchars($seller['store_name'] ?: $seller['full_name']); ?></h3>
										<div class="store-name">
											<i class="fas fa-user"></i> <?php echo htmlspecialchars($seller['full_name']); ?>
										</div>
										<?php if (!empty($seller['selling_description'])): ?>
											<div class="bio"><?php echo htmlspecialchars(substr($seller['selling_description'], 0, 120)) . (strlen($seller['selling_description']) > 120 ? '...' : ''); ?></div>
										<?php endif; ?>
										<div class="user-stats">
											<span><i class="fas fa-users"></i> <?php echo number_format($seller['follower_count']); ?> followers</span>
											<span><i class="fas fa-tag"></i> <?php echo $seller['total_products']; ?> listings</span>
											<span><i class="fas fa-calendar"></i> Joined <?php echo date('M Y', strtotime($seller['created_at'])); ?></span>
										</div>
									</div>
								</div>
								<div class="follow-actions">
									<button class="message-btn" onclick="messageSeller(<?php echo $seller['profile_id']; ?>, '<?php echo htmlspecialchars($seller['store_name'] ?: $seller['full_name']); ?>')">
										<i class="fas fa-envelope"></i> Message
									</button>
									<form method="post" action="" style="display: inline; margin: 0;" onsubmit="return confirmUnfollow('<?php echo htmlspecialchars($seller['store_name'] ?: $seller['full_name']); ?>')">
										<input type="hidden" name="action" value="unfollow">
										<input type="hidden" name="following_id" value="<?php echo $seller['profile_id']; ?>">
										<button type="submit" class="unfollow-btn">
											<i class="fas fa-user"></i> Unfollow
										</button>
									</form>
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
			function confirmUnfollow(sellerName) {
				return confirm(`Are you sure you want to unfollow ${sellerName}? You will no longer see their updates in your feed.`);
			}
			
			function messageSeller(sellerId, sellerName) {
				window.location.href = `messages.php?user=${encodeURIComponent(sellerId)}&name=${encodeURIComponent(sellerName)}`;
			}
		</script>
	</body>
</html>