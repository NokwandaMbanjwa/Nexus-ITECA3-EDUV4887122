<?php
require_once 'config.php';

$profile_user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$profile_user_id) {
    header('Location: explore-feed.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT u.user_id, u.email, u.user_type, u.created_at as joined_date,
           bp.full_name as buyer_name, bp.phone_number as buyer_phone, 
           bp.profile_picture as buyer_picture, bp.bio as buyer_bio,
           sp.full_name as seller_name, sp.phone_number as seller_phone,
           sp.store_name, sp.selling_description, sp.verification_status,
           sp.profile_picture as seller_picture, sp.bio as seller_bio,
           sp.created_at as seller_joined
    FROM nexus_users u
    LEFT JOIN buyer_profiles bp ON u.user_id = bp.user_id
    LEFT JOIN seller_profiles sp ON u.user_id = sp.user_id
    WHERE u.user_id = ?
");
$stmt->execute([$profile_user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: explore-feed.php');
    exit;
}

$is_seller = ($user['user_type'] === 'seller');
$is_verified = ($user['verification_status'] === 'approved');
$current_user_id = getUserId();
$is_own_profile = ($current_user_id == $profile_user_id);
$is_logged_in = isLoggedIn();

$user_products = [];
if ($is_seller) {
    $stmt = $pdo->prepare("
        SELECT p.*, 
               (SELECT image_url FROM product_images WHERE product_id = p.product_id AND is_main = TRUE LIMIT 1) as main_image
        FROM products p
        WHERE p.seller_id = (SELECT profile_id FROM seller_profiles WHERE user_id = ?) 
		AND p.listing_status = 'active' AND p.approval_status = 'approved'
        ORDER BY p.created_at DESC
        LIMIT 12
    ");
    $stmt->execute([$profile_user_id]);
    $user_products = $stmt->fetchAll();
}

$stmt = $pdo->prepare("
    SELECT ur.*, u.user_id as rater_user_id,
           COALESCE(bp.full_name, sp.full_name) as rater_name
    FROM user_ratings ur
    JOIN nexus_users u ON ur.rater_id = u.user_id
    LEFT JOIN buyer_profiles bp ON u.user_id = bp.user_id
    LEFT JOIN seller_profiles sp ON u.user_id = sp.user_id
    WHERE ur.rated_id = ?
    ORDER BY ur.created_at DESC
");
$stmt->execute([$profile_user_id]);
$ratings = $stmt->fetchAll();

$avg_rating = 0;
$rating_counts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
if (!empty($ratings)) {
    $total = 0;
    foreach ($ratings as $rating) {
        $total += $rating['rating'];
        $rating_counts[$rating['rating']]++;
    }
    $avg_rating = round($total / count($ratings), 1);
}

$user_rated = false;
$user_rating_value = 0;
if ($is_logged_in && !$is_own_profile) {
    $stmt = $pdo->prepare("SELECT rating_id, rating FROM user_ratings WHERE rater_id = ? AND rated_id = ?");
    $stmt->execute([$current_user_id, $profile_user_id]);
    $existing_rating = $stmt->fetch();
    if ($existing_rating) {
        $user_rated = true;
        $user_rating_value = $existing_rating['rating'];
    }
}

$joined_date = new DateTime($user['joined_date']);
$now = new DateTime();
$member_since = $joined_date->format('F Y');
$member_days = $joined_date->diff($now)->days;

$rating_error = '';
$rating_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating']) && $is_logged_in && !$is_own_profile) {
    $rating = (int)$_POST['rating'];
    $review = trim($_POST['review']);
    
    if ($rating < 1 || $rating > 5) {
        $rating_error = "Please select a rating";
    } elseif (empty($review)) {
        $rating_error = "Please write a review";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO user_ratings (rater_id, rated_id, rating, review) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE rating = ?, review = ?
            ");
            $stmt->execute([$current_user_id, $profile_user_id, $rating, $review, $rating, $review]);
            $rating_success = "Your rating has been submitted!";
            
            header("Location: user-profile.php?id=$profile_user_id");
            exit;
        } catch (Exception $e) {
            $rating_error = "Failed to submit rating. Please try again.";
        }
    }
}

$seller_profile_id = null;
$is_following = false;

if ($is_seller) {
    $stmt = $pdo->prepare("SELECT profile_id FROM seller_profiles WHERE user_id = ?");
    $stmt->execute([$profile_user_id]);
    $seller_profile = $stmt->fetch();
    $seller_profile_id = $seller_profile ? $seller_profile['profile_id'] : null;
    
    if ($is_logged_in && !$is_own_profile && $seller_profile_id) {
        $stmt = $pdo->prepare("SELECT follow_id FROM user_follows WHERE follower_id = ? AND following_id = ?");
        $stmt->execute([$current_user_id, $seller_profile_id]);
        $is_following = (bool)$stmt->fetch();
    }
}

$display_name = $is_seller ? ($user['store_name'] ?: $user['seller_name']) : ($user['buyer_name'] ?? 'User');

$profile_title = '';
if ($is_seller) {
    if (!empty($user['store_name'])) {
        $profile_title = htmlspecialchars($user['store_name']);
        $profile_title .= '<span style="font-size: 14px; color: #888; margin-left: 8px;">(' . htmlspecialchars($user['seller_name']) . ')</span>';
    } else {
        $profile_title = htmlspecialchars($user['seller_name']);
    }
} else {
    $profile_title = htmlspecialchars($user['buyer_name'] ?? 'User');
}

$profile_picture = '';
if ($is_seller && $user['seller_picture']) {
    $profile_picture = $user['seller_picture'];
} elseif (!$is_seller && $user['buyer_picture']) {
    $profile_picture = $user['buyer_picture'];
}
$bio = $is_seller ? ($user['seller_bio'] ?? $user['selling_description'] ?? '') : ($user['buyer_bio'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title><?php echo htmlspecialchars($display_name); ?> | NEXUS Profile</title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="basestyle.css">
		<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
		<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
		
		<style>
			.profile-page {
				padding: 40px 0 60px;
			}
			
			.profile-container {
				max-width: 1000px;
				margin: 0 auto;
				padding: 0 24px;
			}
			
			.profile-header {
				background: #19191c;
				border: 1px solid #2a2a2a;
				border-radius: 20px;
				padding: 32px;
				margin-bottom: 32px;
				display: flex;
				gap: 32px;
				flex-wrap: wrap;
			}
			
			.profile-avatar {
				width: 120px;
				height: 120px;
				background: #0e0e10;
				border-radius: 50%;
				display: flex;
				align-items: center;
				justify-content: center;
				font-size: 48px;
				color: #8ff5ff;
				border: 3px solid #8ff5ff;
				overflow: hidden;
			}
			
			.profile-avatar img {
				width: 100%;
				height: 100%;
				object-fit: cover;
			}
			
			.profile-info {
				flex: 1;
			}
			
			.profile-info h1 {
				font-size: 28px;
				color: #fff;
				margin-bottom: 8px;
				display: flex;
				align-items: center;
				gap: 12px;
				flex-wrap: wrap;
			}
			
			.verified-badge {
				background: rgba(76, 175, 80, 0.15);
				color: #4caf50;
				padding: 4px 12px;
				border-radius: 20px;
				font-size: 12px;
				font-weight: normal;
			}
			
			.member-since {
				color: #888;
				font-size: 14px;
				margin-bottom: 12px;
			}
			
			.bio {
				color: #adaaad;
				line-height: 1.6;
				margin-bottom: 16px;
				max-width: 500px;
			}
			
			.profile-stats {
				display: flex;
				gap: 24px;
				margin-top: 16px;
			}
			
			.stat {
				text-align: center;
			}
			
			.stat-number {
				font-size: 24px;
				font-weight: bold;
				color: #8ff5ff;
			}
			
			.stat-label {
				font-size: 12px;
				color: #888;
			}
			
			.profile-actions {
				display: flex;
				gap: 12px;
				align-items: flex-start;
				flex-wrap: wrap;
			}
			
			.btn-rate, .btn-report, .btn-message {
				padding: 10px 20px;
				border-radius: 8px;
				cursor: pointer;
				transition: all 0.2s;
				font-size: 14px;
			}
			
			.btn-rate {
				background: #8ff5ff;
				color: #0e0e10;
				border: none;
			}
			
			.btn-message {
				background: transparent;
				border: 1px solid #8ff5ff;
				color: #8ff5ff;
			}
			
			.btn-report {
				background: transparent;
				border: 1px solid #ff4444;
				color: #ff4444;
text-decoration: none;
			}
			
			.btn-rate:hover, .btn-message:hover, .btn-report:hover {
				transform: translateY(-2px);
			}
			
			.btn-follow {
				padding: 10px 20px;
				border-radius: 8px;
				cursor: pointer;
				transition: all 0.2s;
				font-size: 14px;
				background: transparent;
				border: 1px solid #8ff5ff;
				color: #8ff5ff;
			}

			.btn-follow:hover {
				background: rgba(143, 245, 255, 0.1);
				transform: translateY(-2px);
			}

			.btn-follow.following {
				background: #8ff5ff;
				color: #0e0e10;
				border-color: #8ff5ff;
			}
			
			.rating-summary {
				background: #131315;
				border: 1px solid #2a2a2a;
				border-radius: 16px;
				padding: 24px;
				margin-bottom: 32px;
				display: flex;
				gap: 40px;
				flex-wrap: wrap;
				align-items: center;
			}
			
			.avg-rating-box {
				text-align: center;
			}
			
			.avg-rating-number {
				font-size: 48px;
				font-weight: bold;
				color: #ffc107;
			}
			
			.stars-large {
				color: #ffc107;
				font-size: 16px;
				margin: 8px 0;
			}
			
			.rating-bars {
				flex: 1;
				max-width: 300px;
			}
			
			.rating-bar-item {
				display: flex;
				align-items: center;
				gap: 8px;
				margin-bottom: 8px;
			}
			
			.rating-star-label {
				width: 30px;
				font-size: 12px;
				color: #888;
			}
			
			.rating-bar {
				flex: 1;
				height: 8px;
				background: #2a2a2a;
				border-radius: 4px;
				overflow: hidden;
			}
			
			.rating-bar-fill {
				height: 100%;
				background: #ffc107;
				border-radius: 4px;
			}
			
			.rating-percent {
				width: 40px;
				font-size: 12px;
				color: #888;
			}
			
			.reviews-section {
				margin-bottom: 32px;
			}
			
			.section-title {
				font-size: 20px;
				color: #fff;
				margin-bottom: 20px;
			}
			
			.review-card {
				background: #19191c;
				border: 1px solid #2a2a2a;
				border-radius: 12px;
				padding: 20px;
				margin-bottom: 16px;
			}
			
			.review-header {
				display: flex;
				justify-content: space-between;
				align-items: center;
				margin-bottom: 12px;
				flex-wrap: wrap;
			}
			
			.reviewer-name {
				font-weight: 600;
				color: #fff;
			}
			
			.review-stars {
				color: #ffc107;
				font-size: 12px;
			}
			
			.review-date {
				color: #888;
				font-size: 12px;
			}
			
			.review-text {
				color: #adaaad;
				line-height: 1.5;
			}
			
			.products-grid {
				display: grid;
				grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
				gap: 20px;
				margin-top: 20px;
			}
			
			.product-card {
				background: #19191c;
				border: 1px solid #2a2a2a;
				border-radius: 12px;
				overflow: hidden;
				transition: all 0.3s;
				cursor: pointer;
			}
			
			.product-card:hover {
				transform: translateY(-5px);
				border-color: #8ff5ff;
			}
			
			.product-image {
				width: 100%;
				height: 150px;
				background: #0e0e10;
				overflow: hidden;
			}
			
			.product-image img {
				width: 100%;
				height: 100%;
				object-fit: cover;
			}
			
			.product-info {
				padding: 12px;
			}
			
			.product-name {
				font-size: 14px;
				font-weight: 600;
				margin-bottom: 6px;
				color: #fff;
			}
			
			.product-price {
				font-size: 14px;
				color: #8ff5ff;
				font-weight: bold;
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
			}
			
			.modal.show {
				display: flex;
			}
			
			.modal-content {
				background: #19191c;
				border-radius: 16px;
				padding: 32px;
				max-width: 500px;
				width: 90%;
			}
			
			.rating-stars-input {
				display: flex;
				gap: 8px;
				justify-content: center;
				margin: 20px 0;
			}
			
			.rating-star-input {
				font-size: 32px;
				cursor: pointer;
				color: #555;
				transition: color 0.2s;
			}
			
			.rating-star-input:hover,
			.rating-star-input.active {
				color: #ffc107;
			}
			
			.empty-state {
				text-align: center;
				padding: 40px;
				color: #888;
			}
			
			@media (max-width: 768px) {
				.profile-header {
					flex-direction: column;
					align-items: center;
					text-align: center;
				}
				.profile-info {
					text-align: center;
				}
				.profile-stats {
					justify-content: center;
				}
				.bio {
					margin-left: auto;
					margin-right: auto;
				}
				.rating-summary {
					flex-direction: column;
					align-items: center;
				}
				.products-grid {
					grid-template-columns: repeat(2, 1fr);
				}
				
				.profile-actions {
					display: grid;
					grid-template-columns: repeat(2, 1fr);
					gap: 12px;
					width: 100%;
				}
			}
		</style>
	</head>

	<body>
		<?php include 'header.php'; ?>
		
		<main class="profile-page">
			<div class="profile-container">
				<div class="profile-header">
					<div class="profile-avatar">
						<?php if ($profile_picture): ?>
							<img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile picture">
						<?php else: ?>
							<i class="fas fa-user"></i>
						<?php endif; ?>
					</div>
					<div class="profile-info">
						<h1>
    <?php
        if ($is_seller && !empty($user['store_name'])) {
            echo htmlspecialchars($user['store_name']);
            echo '<span style="font-size: 14px; color: #888; margin-left: 8px;">(' . htmlspecialchars($user['seller_name']) . ')</span>';
        } else {
            echo htmlspecialchars($display_name);
        }
    ?>
    <?php if ($is_seller && $is_verified): ?>
        <span class="verified-badge"><i class="fas fa-check-circle"></i> Verified Seller</span>
    <?php endif; ?>
</h1>

						<div class="member-since">
							<i class="fas fa-calendar-alt"></i> Member since <?php echo $member_since; ?> 
							(<?php echo $member_days; ?> days on Nexus)
						</div>
						<?php if ($bio): ?>
							<div class="bio"><?php echo nl2br(htmlspecialchars($bio)); ?></div>
						<?php endif; ?>
						<div class="profile-stats">
							<div class="stat">
								<div class="stat-number"><?php echo count($ratings); ?></div>
								<div class="stat-label">Reviews</div>
							</div>
							<?php if ($is_seller): ?>
								<div class="stat">
									<div class="stat-number"><?php echo count($user_products); ?></div>
									<div class="stat-label">Products</div>
								</div>
							<?php endif; ?>
						</div>
					</div>
					<div class="profile-actions">
						<?php if ($is_logged_in && !$is_own_profile): ?>
							<button class="btn-rate" onclick="openRatingModal()">
								<i class="fas fa-star"></i> Rate User
							</button>
							<?php if ($is_seller): ?>
								<button class="btn-message" onclick="messageUser(<?php echo $profile_user_id; ?>, '<?php echo htmlspecialchars($display_name); ?>')">
									<i class="fas fa-envelope"></i> Message
								</button>
							<?php endif; ?>
							<a href="report.php?user=<?php echo $profile_user_id; ?>" class="btn-report">
    <i class="fas fa-flag"></i> Report
</a>

						<?php endif; ?>
						<?php if ($is_logged_in && !$is_own_profile && $is_seller): ?>
							<button class="btn-follow" id="followBtn" data-seller-id="<?php echo $seller_profile_id; ?>" onclick="toggleFollow(<?php echo $seller_profile_id; ?>, this)">
								<i class="fas <?php echo $is_following ? 'fa-user-check' : 'fa-user-plus'; ?>"></i> 
								<?php echo $is_following ? 'Following' : 'Follow'; ?>
							</button>
						<?php endif; ?>
					</div>
				</div>
				
				<div class="rating-summary">
					<div class="avg-rating-box">
						<div class="avg-rating-number"><?php echo $avg_rating; ?></div>
						<div class="stars-large">
							<?php for ($i = 1; $i <= 5; $i++): ?>
								<i class="fas fa-star<?php echo $i <= round($avg_rating) ? '' : '-o'; ?>"></i>
							<?php endfor; ?>
						</div>
						<div class="member-since"><?php echo count($ratings); ?> ratings</div>
					</div>
					<div class="rating-bars">
						<?php for ($i = 5; $i >= 1; $i--): ?>
							<?php $percent = count($ratings) > 0 ? round($rating_counts[$i] / count($ratings) * 100) : 0; ?>
							<div class="rating-bar-item">
								<span class="rating-star-label"><?php echo $i; ?> <i class="fas fa-star"></i></span>
								<div class="rating-bar">
									<div class="rating-bar-fill" style="width: <?php echo $percent; ?>%"></div>
								</div>
								<span class="rating-percent"><?php echo $percent; ?>%</span>
							</div>
						<?php endfor; ?>
					</div>
				</div>
				
				<!-- Reviews Section -->
				<div class="reviews-section">
					<h2 class="section-title">User Reviews</h2>
					<?php if (empty($ratings)): ?>
						<div class="empty-state">
							<i class="fas fa-comment" style="font-size: 48px; margin-bottom: 16px; display: block;"></i>
							<p>No reviews yet. Be the first to leave a review!</p>
						</div>
					<?php else: ?>
						<?php foreach ($ratings as $rating): ?>
							<div class="review-card">
								<div class="review-header">
									<div>
										<span class="reviewer-name"><?php echo htmlspecialchars($rating['rater_name']); ?></span>
									</div>
									<span class="review-date"><?php echo date('d M Y', strtotime($rating['created_at'])); ?></span>
								</div>
								<div class="review-stars">
									<?php for ($i = 1; $i <= 5; $i++): ?>
										<i class="fas fa-star<?php echo $i <= $rating['rating'] ? '' : '-o'; ?>"></i>
									<?php endfor; ?>
								</div>
								<div class="review-text">
									<?php echo nl2br(htmlspecialchars($rating['review'])); ?>
								</div>
							</div>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
				
				<?php if ($is_seller && !empty($user_products)): ?>
					<div class="reviews-section">
						<h2 class="section-title">Products by <?php echo htmlspecialchars($display_name); ?></h2>
						<div class="products-grid">
							<?php foreach ($user_products as $product): ?>
								<div class="product-card" onclick="window.location.href='product.php?id=<?php echo $product['product_id']; ?>'">
									<div class="product-image">
										<img src="<?php echo htmlspecialchars($product['main_image'] ?? 'https://via.placeholder.com/200x150/1f1f22/8ff5ff?text=Product'); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
									</div>
									<div class="product-info">
										<div class="product-name"><?php echo htmlspecialchars($product['product_name']); ?></div>
										<div class="product-price">R<?php echo number_format($product['price'], 2); ?></div>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>
			</div>
		</main>
		
		<div class="modal" id="ratingModal">
			<div class="modal-content">
				<h3 style="color: #8ff5ff; margin-bottom: 20px;">Rate <?php echo htmlspecialchars($display_name); ?></h3>
				<?php if ($rating_error): ?>
					<div class="alert alert-error"><?php echo $rating_error; ?></div>
				<?php endif; ?>
				<form method="post">
					<label>Your Rating</label>
					<div class="rating-stars-input" id="ratingStarsInput">
						<?php for ($i = 1; $i <= 5; $i++): ?>
							<i class="fas fa-star rating-star-input" data-rating="<?php echo $i; ?>"></i>
						<?php endfor; ?>
					</div>
					<input type="hidden" name="rating" id="ratingValue" value="<?php echo $user_rating_value; ?>">
					
					<label style="margin-top: 20px; display: block;">Your Review</label>
					<textarea name="review" rows="5" style="width: 100%; padding: 12px; background: #0e0e10; border: 1px solid #2a2a2a; border-radius: 8px; color: #e5e5e5; margin-top: 8px;" placeholder="Share your experience with this user..."></textarea>
					
					<div style="display: flex; gap: 12px; margin-top: 20px;">
						<button type="button" class="btn-rate" style="background: #333;" onclick="closeRatingModal()">Cancel</button>
						<button type="submit" name="submit_rating" class="btn-rate">Submit Rating</button>
					</div>
				</form>
			</div>
		</div>
		
		<?php include 'footer.php'; ?>
		
		<script>
			function messageUser(userId, userName) {
				window.location.href = `messages.php?user=${userId}&name=${encodeURIComponent(userName)}`;
			}
			
			const ratingModal = document.getElementById('ratingModal');
			const reportModal = document.getElementById('reportModal');
			
			function openRatingModal() {
				ratingModal.classList.add('show');
			}
			
			function closeRatingModal() {
				ratingModal.classList.remove('show');
			}
			
			function openReportModal() {
				reportModal.classList.add('show');
			}
			
			function closeReportModal() {
				reportModal.classList.remove('show');
			}

			window.addEventListener('click', (e) => {
				if (e.target === ratingModal) {
					ratingModal.classList.remove('show');
				}
				if (e.target === reportModal) {
					reportModal.classList.remove('show');
				}
			});
			
			let selectedRating = <?php echo $user_rating_value; ?>;
			const ratingStars = document.querySelectorAll('#ratingStarsInput .rating-star-input');
			const ratingValue = document.getElementById('ratingValue');
			
			if (ratingStars.length) {
				ratingStars.forEach(star => {
					star.addEventListener('click', function() {
						selectedRating = parseInt(this.getAttribute('data-rating'));
						ratingValue.value = selectedRating;
						ratingStars.forEach(s => {
							const rating = parseInt(s.getAttribute('data-rating'));
							if (rating <= selectedRating) {
								s.classList.add('active');
							} else {
								s.classList.remove('active');
							}
						});
					});
					
					const starRating = parseInt(star.getAttribute('data-rating'));
					if (selectedRating >= starRating) {
						star.classList.add('active');
					}
				});
			}
			
			function toggleFollow(sellerId, button) {
				if (!<?php echo $is_logged_in ? 'true' : 'false'; ?>) {
					window.location.href = 'login.php';
					return;
				}
				
				fetch('follow-api.php', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
						'X-Requested-With': 'XMLHttpRequest'
					},
					body: 'action=toggle&seller_id=' + sellerId
				})
				.then(function(response) { return response.json(); })
				.then(function(data) {
					if (data.success) {
						if (data.following) {
							button.innerHTML = '<i class="fas fa-user-check"></i> Following';
							button.classList.add('following');
						} else {
							button.innerHTML = '<i class="fas fa-user-plus"></i> Follow';
							button.classList.remove('following');
						}
					}
				});
			}
		</script>
	</body>
</html>