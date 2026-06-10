<?php
require_once 'config.php';
$user_type = getUserType();

if (isLoggedIn()) {
    header('Location: explore-feed.php');
    exit;
}

$search_query = $_GET['search'] ?? '';

if (!empty($search_query)) {
    // Search mode - fetch products matching the search
    $searchTerm = "%$search_query%";
    $stmt = $pdo->prepare("SELECT p.*, sp.store_name, sp.full_name,
                           COALESCE(p.original_price, p.price) as original_price,
                           (SELECT AVG(rating) FROM product_reviews WHERE product_id = p.product_id) as seller_rating,
                           (SELECT image_url FROM product_images WHERE product_id = p.product_id AND is_main = 1 LIMIT 1) as product_image
                           FROM products p
                           LEFT JOIN seller_profiles sp ON p.seller_id = sp.profile_id
                           WHERE p.listing_status = 'active' AND p.approval_status = 'approved' AND p.stock_quantity > 0
                           AND (p.product_name LIKE ? OR p.product_description LIKE ? OR sp.store_name LIKE ?)
                           ORDER BY p.created_at DESC LIMIT 20");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $search_results = $stmt->fetchAll();
}

// Fetch discount items
$stmt = $pdo->prepare("SELECT p.*, sp.store_name, sp.full_name,
                       COALESCE(p.original_price, p.price) as original_price,
                       (SELECT AVG(rating) FROM product_reviews WHERE product_id = p.product_id) as seller_rating,
						(SELECT image_url FROM product_images WHERE product_id = p.product_id AND is_main = 1 LIMIT 1) as product_image
                       FROM products p
                       LEFT JOIN seller_profiles sp ON p.seller_id = sp.profile_id
                       WHERE p.listing_status = 'active' AND p.approval_status = 'approved' AND p.stock_quantity > 0 AND p.discount_percentage > 0
                       ORDER BY p.discount_percentage DESC LIMIT 10");
$stmt->execute();
$discount_items = $stmt->fetchAll();

// Fetch trending items (by views)
$stmt = $pdo->prepare("SELECT p.*, sp.store_name, sp.full_name,
                       COALESCE(p.original_price, p.price) as original_price,
                       (SELECT AVG(rating) FROM product_reviews WHERE product_id = p.product_id) as seller_rating,
						(SELECT image_url FROM product_images WHERE product_id = p.product_id AND is_main = 1 LIMIT 1) as product_image
                       FROM products p
                       LEFT JOIN seller_profiles sp ON p.seller_id = sp.profile_id
                       WHERE p.listing_status = 'active' AND p.approval_status = 'approved' AND p.stock_quantity > 0
                       ORDER BY p.view_count DESC LIMIT 10");
$stmt->execute();
$trending_items = $stmt->fetchAll();

// Fetch recent items
$stmt = $pdo->prepare("SELECT p.*, sp.store_name, sp.full_name,
                       COALESCE(p.original_price, p.price) as original_price,
                       (SELECT AVG(rating) FROM product_reviews WHERE product_id = p.product_id) as seller_rating,
						(SELECT image_url FROM product_images WHERE product_id = p.product_id AND is_main = 1 LIMIT 1) as product_image
                       FROM products p
                       LEFT JOIN seller_profiles sp ON p.seller_id = sp.profile_id
                       WHERE p.listing_status = 'active' AND p.approval_status = 'approved' AND p.stock_quantity > 0
                       ORDER BY p.created_at DESC LIMIT 10");
$stmt->execute();
$recent_items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>NEXUS | Premium C2C Marketplace</title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="basestyle.css">
		<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
		<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
		
		<style>
			h1 { 
				font-size: 72px; 
				line-height: 0.9; 
				margin-bottom: 24px; 
			}
			
			.hero { 
				position: relative; 
				padding-top: 100px; 
				padding-bottom: 60px; 
				min-height: 600px; 
				display: flex; 
				align-items: center; 
				overflow: hidden; 
			}
			
			.hero-container { 
				position: relative; 
				z-index: 1; 
				max-width: 1150px;
				margin: 0 40px; 
				padding: 0 20px; 
			}
			.hero-background { 
				position: absolute; 
				top: 0; left: 
				400px; right: 0; 
				bottom: 0; 
				width: 70%; 
				background-image: url('https://img.freepik.com/premium-photo/wildlife-tracks-document-animal-tracks-snow-inviting-viewers-guess-what-wildlife-might-be-nearby_997534-75869.jpg?semt=ais_hybrid&w=740&q=80'); 
				background-size: cover; 
				background-position: 
				center right; 
				background-repeat: no-repeat; 
				z-index: 0; 
			}
			
			.hero-background::before { 
				content: ''; 
				position: absolute; 
				top: 0; 
				left: 0; 
				right: 0; 
				bottom: 0; 
				background: linear-gradient(to right, #0e0e10 0%, #0e0e10 15%, rgba(10,10,10,0.5) 40%, rgba(10,10,10,0.3) 60%, transparent 100%); 
			}
			
			.hero-content { 
			text-align: left; 
			}
			
			.hero-badge {
				display: inline-flex;
				align-items: center;
				gap: 8px;
				padding: 4px 12px;
				background-color: rgba(47, 46, 190, 0.3);
				border: 1px solid rgba(144, 147, 255, 0.2);
				border-radius: 9999px;
				margin-bottom: 24px;
			}

			.badge-dot {
				width: 8px;
				height: 8px;
				background-color: #8ff5ff;
				border-radius: 50%;
				animation: pulse 2s infinite;
			}

			@keyframes pulse {
				0%, 100% {
					opacity: 1;
				}
				50% {
					opacity: 0.5;
				}
			}

			.badge-text {
				font-size: 12px;
				font-weight: bold;
				letter-spacing: 0.1em;
				color: #8ff5ff;
				text-transform: uppercase;
			}

			.hero-description {
				font-size: 18px;
				color: #e5e5e5;
				max-width: 500px;
				margin-bottom: 32px;
				line-height: 1.6;
			}

			.hero-buttons {
				display: flex;
				gap: 16px;
			}

			.explore-section {
				padding: 10px 0 40px;
				background-color: #0e0e10;
			}

			.explore-header {
				text-align: center;
				margin-bottom: 10px;
			}

			.explore-header h2 {
				font-size: 36px;
				color: #8ff5ff;
				margin-bottom: 8px;
			}

			.scroll-wrapper {
				position: relative;
				display: flex;
				align-items: center;
				gap: 12px;
				margin-bottom: 10px;
			}

			.scroll-btn {
				width: 40px;
				height: 40px;
				background-color: #19191c;
				color: #8ff5ff;
				border: 1px solid rgba(118, 117, 119, 0.3);
				border-radius: 50%;
				cursor: pointer;
				flex-shrink: 0;
				transition: all 0.3s;
				display: flex;
				align-items: center;
				justify-content: center;
			}

			.scroll-btn:hover {
				background-color: #8ff5ff;
				border-color: #8ff5ff;
				color: #0e0e10;
			}

			.items-grid {
				display: flex;
				gap: 16px;
				overflow-x: auto;
				scroll-behavior: smooth;
				padding: 10px 0;
				flex: 1;
			}

			.item-card {
				position: relative;
				background-color: #121214;
				border: 1px solid rgba(118, 117, 119, 0.1);
				border-radius: 12px;
				overflow: hidden;
				transition: all 0.3s;
				flex: 0 0 auto;
				width: 220px;
				display: flex;
				flex-direction: column;
			}

			.item-card:hover {
				transform: translateY(-5px);
				border-color: rgba(143, 245, 255, 0.3);
				box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
			}

			.discount-badge {
				position: absolute;
				top: 10px;
				left: 10px;
				background-color: #ff6b6b;
				color: white;
				padding: 4px 8px;
				border-radius: 6px;
				font-size: 11px;
				font-weight: bold;
				z-index: 10;
			}

			.wishlist-btn {
				position: absolute;
				top: 10px;
				right: 10px;
				background-color: rgba(0, 0, 0, 0.5);
				border: none;
				border-radius: 50%;
				width: 32px;
				height: 32px;
				cursor: pointer;
				z-index: 10;
				color: white;
				transition: transform 0.2s;
			}

			.wishlist-btn:hover {
				transform: scale(1.1);
			}

			.item-image {
				aspect-ratio: 1/1;
				height: 200px;
				overflow: hidden;
				flex-shrink: 0;
				cursor: pointer;
			}

			.item-image img {
				width: 100%;
				height: 100%;
				object-fit: cover;
				transition: transform 0.3s;
			}

			.item-card:hover .item-image img {
				transform: scale(1.05);
			}

			.item-details {
				padding: 12px;
				flex: 1;
				display: flex;
				flex-direction: column;
			}

			.item-header {
				display: flex;
				justify-content: space-between;
				align-items: flex-start;
				margin-bottom: 6px;
				gap: 12px;
			}

			.item-header h4 {
				font-size: 14px;
				margin: 0;
				line-height: 1.3;
				flex: 1;
				cursor: pointer;
				color: #fff;
			}

			.item-header h4:hover {
				color: #8ff5ff;
			}

			.price-container {
				text-align: right;
			}

			.item-price {
				font-size: 14px;
				font-weight: bold;
				color: #8ff5ff;
			}

			.original-price {
				font-size: 11px;
				color: #adaaad;
				text-decoration: line-through;
				display: block;
			}

			.item-description {
				font-size: 12px;
				color: #adaaad;
				margin-bottom: 8px;
				line-height: 1.3;
				overflow: hidden;
				text-overflow: ellipsis;
				display: -webkit-box;
				-webkit-line-clamp: 2;
				-webkit-box-orient: vertical;
			}

			.seller-info {
				display: flex;
				justify-content: space-between;
				align-items: center;
				padding-top: 8px;
				border-top: 1px solid rgba(118, 117, 119, 0.1);
				font-size: 11px;
				color: #9ca3af;
				margin-top: auto;
			}

			.rating {
				display: flex;
				align-items: center;
				gap: 3px;
			}

			.rating i {
				color: #ffc107;
				font-size: 10px;
			}

			.rating span {
				font-size: 11px;
				color: #e5e5e5;
			}

			.no-results {
				text-align: center;
				padding: 60px;
				color: #adaaad;
				font-size: 16px;
			}

			.benefits-section {
				padding: 30px 0 80px 0;
			}

			.benefits-header {
				text-align: center;
				margin-bottom: 50px;
			}

			.benefits-header h2 {
				font-size: 36px;
				margin-bottom: 12px;
				color: #8ff5ff;
			}

			.benefits-header p {
				color: #adaaad;
				font-size: 16px;
			}

			.benefits-grid {
				display: grid;
				grid-template-columns: repeat(3, 1fr);
				gap: 32px;
			}

			.benefit-card {
				background-color: #19191c;
				padding: 40px 30px;
				border-radius: 16px;
				text-align: center;
				transition: all 0.3s;
				border: 1px solid rgba(118, 117, 119, 0.1);
			}

			.benefit-card:hover {
				transform: translateY(-5px);
				border-color: rgba(143, 245, 255, 0.3);
			}

			.benefit-icon {
				width: 70px;
				height: 70px;
				display: flex;
				align-items: center;
				justify-content: center;
				margin: 0 auto 20px;
			}

			.benefit-icon i {
				font-size: 40px;
			}

			.benefit-card:nth-child(1) .benefit-icon i {
				color: #8ff5ff;
			}

			.benefit-card:nth-child(2) .benefit-icon i {
				color: #c180ff;
			}

			.benefit-card:nth-child(3) .benefit-icon i {
				color: #9093ff;
			}

			.benefit-card h3 {
				font-size: 22px;
				margin-bottom: 12px;
				color: #f9f5f8;
			}

			.benefit-card p {
				color: #adaaad;
				line-height: 1.6;
				font-size: 14px;
			}
			.hero-gif-bg {
				display: none;
			}
			.mobile-heading {
				display: none;
			}
			
			@media (max-width: 768px) {
				.desktop-heading {
					display: none;
				}
				.hero-background {
					display: none;
				}
				
				.hero-gif-bg {
					display: block;
					position: absolute;
					top: 0;
					left: 0;
					width: 100%;
					height: 100%;
					z-index: 0;
				}
				
				.hero-gif-bg img {
					width: 100%;
					height: 100%;
					object-fit: cover;
				}
				
				.hero-gif-bg::before {
					content: '';
					position: absolute;
					top: 0;
					left: 0;
					right: 0;
					bottom: 0;
					background: rgba(14, 14, 16, 0.6);
					z-index: 1;
				}
				
				.hero-container {
					z-index: 2;
				}
				
				.hero-content .mobile-heading {
					display: block;
					font-size: 44px;
					text-align: center;
					line-height: 1.2;
					margin-bottom: 24px;
				}
				.hero-content h1 {
					display: none;
				}
				
				.mobile-heading br {
					display: none;
				}

				.hero {
					padding-top: 80px;
					padding-bottom: 40px;
					min-height: auto;
				}

				.hero-container {
					margin: 0 16px;
					padding: 0;
				}

				.hero-content {
					text-align: center;
				}

				.hero-description {
					font-size: 15px;
					margin-left: auto;
					margin-right: auto;
					max-width: 100%;
				}

				.hero-buttons {
					flex-direction: column;
				}

				.hero-badge {
					display: none;
				}

				.explore-section {
					padding: 16px 0 20px;
				}

				.explore-header h2 {
					font-size: 22px;
				}

				.explore-header p {
					font-size: 13px;
				}

				.scroll-btn {
					display: none;
				}

				.items-grid {
					gap: 12px;
					padding: 10px 0;
					padding-right: 0;
				}

				.item-card {
					width: 160px;
					flex: 0 0 160px;
				}

				.item-image {
					height: 150px;
				}

				.item-details {
					padding: 10px;
				}

				.item-header h4 {
					font-size: 12px;
				}

				.item-price {
					font-size: 12px;
				}

				.item-description {
					font-size: 11px;
					-webkit-line-clamp: 1;
				}

				.seller-info {
					font-size: 10px;
				}

				.section-heading {
					font-size: 16px;
					margin-bottom: 2px;
				}

				.section-heading i {
					font-size: 14px;
				}

				.scroll-wrapper {
					margin-bottom: 6px;
					gap: 0;
				}

				.no-results {
					padding: 30px;
					font-size: 14px;
				}

				.benefits-section {
					padding: 20px 16px 40px;
				}

				.benefits-header {
					margin-bottom: 20px;
				}

				.benefits-header h2 {
					text-align: center;
					font-size: 22px;
				}

				.benefits-grid {
					display: flex;
					flex-direction: row;
					flex-wrap: wrap;
					justify-content: center;
					gap: 12px;
				}

				.benefit-card {
					background: transparent;
					padding: 0;
					border: none;
					border-radius: 0;
					text-align: left;
				}

				.benefit-card:hover {
					transform: none;
					border-color: transparent;
				}

				.benefit-icon {
					display: none;
				}

				.benefit-card h3 {
					font-size: 15px;
					color: #e5e5e5;
					margin-bottom: 0;
					display: flex;
					align-items: center;
					gap: 10px;
				}

				.benefit-card h3::before {
					font-family: 'Font Awesome 6 Free';
					font-weight: 900;
					font-size: 14px;
					flex-shrink: 0;
					width: 20px;
				}

				.benefit-card:nth-child(1) h3::before {
					content: '\f2b5';
					color: #8ff5ff;
				}

				.benefit-card:nth-child(2) h3::before {
					content: '\f3ed';
					color: #c180ff;
				}

				.benefit-card:nth-child(3) h3::before {
					content: '\f0d1';
					color: #9093ff;
				}

				.benefit-card p {
					display: none;
				}
			}
		</style>
	</head>
	<body>
		<?php include 'header.php'; ?>

		<main>
			<section class="hero">
				<div class="hero-background"></div>
					<div class="hero-gif-bg">
						<img src="https://i.pinimg.com/originals/55/01/60/5501609ee45d514d1f2c4a63502045e2.gif" alt="">
					</div>
				<div class="hero-container">
					<div class="hero-content">
						<div class="hero-badge">
							<span class="badge-dot"></span>
							<span class="badge-text">The Future of e-Commerce</span>
						</div>
						<h1>WHERE <br>CONNECTIONS <br>MULTIPLY</h1>
						<h1 class="mobile-heading">WHERE CONNECTIONS MULTIPLY</h1>
						<p class="hero-description">Empowering local trade, enriching communities, fostering growth - A South African online environment connecting buyers and sellers.</p>
						<div class="hero-buttons">
							<button class="btn-primary" onclick="redirectWithRole('buyer')">Start Exploring</button>
							<button class="btn-primary-transparent" onclick="redirectWithRole('seller')">Start Selling</button>
						</div>
					</div>
				</div>
			</section>
			
			<hr/>
			
			<section class="explore-section">
				<div class="container">
					<div class="explore-header">
						<h2>Curated Just For You</h2>
					</div>
					
					<?php if (empty($discount_items) && empty($trending_items) && empty($recent_items)): ?>
						<div class="no-results">No products available yet. Check back soon!</div>
					<?php else: ?>
						
						<?php if (!empty($search_query)): ?>
						<h2 class="section-heading">Results for "<?php echo htmlspecialchars($search_query); ?>"</h2>
						<?php if (empty($search_results)): ?>
							<div class="no-results">No results found for "<?php echo htmlspecialchars($search_query); ?>"</div>
						<?php else: ?>
							<div class="scroll-wrapper">
								<button class="scroll-btn scroll-left" onclick="this.nextElementSibling.scrollBy({left:-300,behavior:'smooth'})"><i class="fas fa-chevron-left"></i></button>
								<div class="items-grid">
									<?php foreach ($search_results as $product): 
										$imageUrl = $product['product_image'] ?: 'https://via.placeholder.com/300x300/1f1f22/8ff5ff?text=Product';
										$originalPrice = $product['original_price'] ?: $product['price'];
										$discountPercent = floatval($product['discount_percentage'] ?? 0);
										$hasDiscount = $discountPercent > 0;
										$finalPrice = $hasDiscount ? $originalPrice * (1 - $discountPercent / 100) : $product['price'];
									?>
									<article class="item-card">
										<?php if ($hasDiscount): ?>
											<div class="discount-badge"><?php echo $discountPercent; ?>% OFF</div>
										<?php endif; ?>
										<button class="wishlist-btn" onclick="event.stopPropagation(); toggleWishlist(<?php echo $product['product_id']; ?>)">
											<i class="fa-regular fa-heart"></i>
										</button>
										<div class="item-image" onclick="window.location.href='product.php?id=<?php echo $product['product_id']; ?>'">
											<img src="<?php echo htmlspecialchars($imageUrl); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" onerror="this.src='https://via.placeholder.com/300x300/1f1f22/8ff5ff?text=Product'">
										</div>
										<div class="item-details">
											<div class="item-header">
												<h4><?php echo htmlspecialchars($product['product_name']); ?></h4>
												<div class="price-container">
													<span class="item-price">R<?php echo number_format($finalPrice, 2); ?></span>
													<?php if ($hasDiscount): ?>
														<span class="original-price">R<?php echo number_format($originalPrice, 2); ?></span>
													<?php endif; ?>
												</div>
											</div>
											<p class="item-description"><?php echo htmlspecialchars(substr($product['product_description'] ?? '', 0, 60)); ?>...</p>
											<div class="seller-info">
												<span><?php echo htmlspecialchars($product['store_name'] ?: ($product['full_name'] ?? 'Seller')); ?></span>
												<div class="rating"><i class="fas fa-star"></i><span><?php echo $product['seller_rating'] ? number_format($product['seller_rating'], 1) : 'New'; ?></span></div>
											</div>
										</div>
									</article>
									<?php endforeach; ?>
								</div>
								<button class="scroll-btn scroll-right" onclick="this.previousElementSibling.scrollBy({left:300,behavior:'smooth'})"><i class="fas fa-chevron-right"></i></button>
							</div>
						<?php endif; ?>
					<?php else: ?>
						<!-- Discounts Section -->
						<?php if (!empty($discount_items)): ?>
						<section>
							<h2 class="section-heading"><i class="fa-solid fa-fire" style="color: #ff6b35;"></i> Hot Discounts</h2>
							<div class="scroll-wrapper">
								<button class="scroll-btn" onclick="this.nextElementSibling.scrollBy({left:-300,behavior:'smooth'})"><i class="fas fa-chevron-left"></i></button>
								<div class="items-grid">
									<?php foreach ($discount_items as $product): 
										$imageUrl = $product['product_image'] ?: 'https://via.placeholder.com/300x300/1f1f22/8ff5ff?text=Product';
										$originalPrice = $product['original_price'] ?: $product['price'];
										$discountPercent = floatval($product['discount_percentage'] ?? 0);
										$finalPrice = $originalPrice * (1 - $discountPercent / 100);
									?>
									<article class="item-card" data-product-id="<?php echo $product['product_id']; ?>">
										<div class="discount-badge"><?php echo $discountPercent; ?>% OFF</div>
										<button class="wishlist-btn" onclick="event.stopPropagation(); toggleWishlist(<?php echo $product['product_id']; ?>)">
											<i class="fa-regular fa-heart"></i>
										</button>
										<div class="item-image" onclick="window.location.href='product.php?id=<?php echo $product['product_id']; ?>'">
											<img src="<?php echo htmlspecialchars($imageUrl); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" onerror="this.src='https://via.placeholder.com/300x300/1f1f22/8ff5ff?text=Product'">
										</div>
										<div class="item-details">
											<div class="item-header">
												<h4 onclick="window.location.href='product.php?id=<?php echo $product['product_id']; ?>'"><?php echo htmlspecialchars($product['product_name']); ?></h4>
												<div class="price-container">
													<span class="item-price">R<?php echo number_format($finalPrice, 2); ?></span>
													<span class="original-price">R<?php echo number_format($originalPrice, 2); ?></span>
												</div>
											</div>
											<p class="item-description"><?php echo htmlspecialchars(substr($product['product_description'] ?? '', 0, 80)); ?>...</p>
											<div class="seller-info">
												<span><?php echo htmlspecialchars($product['store_name'] ?: ($product['full_name'] ?? 'Seller')); ?></span>
												<div class="rating"><i class="fas fa-star"></i><span><?php echo $product['seller_rating'] ? number_format($product['seller_rating'], 1) : 'New'; ?></span></div>
											</div>
										</div>
									</article>
									<?php endforeach; ?>
								</div>
								<button class="scroll-btn" onclick="this.previousElementSibling.scrollBy({left:300,behavior:'smooth'})"><i class="fas fa-chevron-right"></i></button>
							</div>
						</section>
						<?php endif; ?>
						
						<!-- Trending Section -->
						<?php if (!empty($trending_items)): ?>
						<section>
							<h2 class="section-heading"><i class="fas fa-chart-line" style="color: #ff2d55;"></i>  Trending on Nexus</h2>
							<div class="scroll-wrapper">
								<button class="scroll-btn" onclick="this.nextElementSibling.scrollBy({left:-300,behavior:'smooth'})"><i class="fas fa-chevron-left"></i></button>
								<div class="items-grid">
									<?php foreach ($trending_items as $product): 
										$imageUrl = $product['product_image'] ?: 'https://via.placeholder.com/300x300/1f1f22/8ff5ff?text=Product';
										$originalPrice = $product['original_price'] ?: $product['price'];
										$discountPercent = floatval($product['discount_percentage'] ?? 0);
										$hasDiscount = $discountPercent > 0;
										$finalPrice = $hasDiscount ? $originalPrice * (1 - $discountPercent / 100) : $product['price'];
									?>
									<article class="item-card" data-product-id="<?php echo $product['product_id']; ?>">
										<?php if ($hasDiscount): ?>
											<div class="discount-badge"><?php echo $discountPercent; ?>% OFF</div>
										<?php endif; ?>
										<button class="wishlist-btn" onclick="event.stopPropagation(); toggleWishlist(<?php echo $product['product_id']; ?>)">
											<i class="fa-regular fa-heart"></i>
										</button>
										<div class="item-image" onclick="window.location.href='product.php?id=<?php echo $product['product_id']; ?>'">
											<img src="<?php echo htmlspecialchars($imageUrl); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" onerror="this.src='https://via.placeholder.com/300x300/1f1f22/8ff5ff?text=Product'">
										</div>
										<div class="item-details">
											<div class="item-header">
												<h4 onclick="window.location.href='product.php?id=<?php echo $product['product_id']; ?>'"><?php echo htmlspecialchars($product['product_name']); ?></h4>
												<div class="price-container">
													<span class="item-price">R<?php echo number_format($finalPrice, 2); ?></span>
													<?php if ($hasDiscount): ?>
														<span class="original-price">R<?php echo number_format($originalPrice, 2); ?></span>
													<?php endif; ?>
												</div>
											</div>
											<p class="item-description"><?php echo htmlspecialchars(substr($product['product_description'] ?? '', 0, 80)); ?>...</p>
											<div class="seller-info">
												<span><?php echo htmlspecialchars($product['store_name'] ?: ($product['full_name'] ?? 'Seller')); ?></span>
												<div class="rating"><i class="fas fa-star"></i><span><?php echo $product['seller_rating'] ? number_format($product['seller_rating'], 1) : 'New'; ?></span></div>
											</div>
										</div>
									</article>
									<?php endforeach; ?>
								</div>
								<button class="scroll-btn" onclick="this.previousElementSibling.scrollBy({left:300,behavior:'smooth'})"><i class="fas fa-chevron-right"></i></button>
							</div>
						</section>
						<?php endif; ?>
						
						<!-- Recent Section -->
						<?php if (!empty($recent_items)): ?>
						<section>
							<h2 class="section-heading"><i class="fas fa-clock" style="color: #34c759;"></i> Recently Added</h2>
							<div class="scroll-wrapper">
								<button class="scroll-btn" onclick="this.nextElementSibling.scrollBy({left:-300,behavior:'smooth'})"><i class="fas fa-chevron-left"></i></button>
								<div class="items-grid">
									<?php foreach ($recent_items as $product): 
										$imageUrl = $product['product_image'] ?: 'https://via.placeholder.com/300x300/1f1f22/8ff5ff?text=Product';
										$originalPrice = $product['original_price'] ?: $product['price'];
										$discountPercent = floatval($product['discount_percentage'] ?? 0);
										$hasDiscount = $discountPercent > 0;
										$finalPrice = $hasDiscount ? $originalPrice * (1 - $discountPercent / 100) : $product['price'];
									?>
									<article class="item-card" data-product-id="<?php echo $product['product_id']; ?>">
										<?php if ($hasDiscount): ?>
											<div class="discount-badge"><?php echo $discountPercent; ?>% OFF</div>
										<?php endif; ?>
										<button class="wishlist-btn" onclick="event.stopPropagation(); toggleWishlist(<?php echo $product['product_id']; ?>)">
											<i class="fa-regular fa-heart"></i>
										</button>
										<div class="item-image" onclick="window.location.href='product.php?id=<?php echo $product['product_id']; ?>'">
											<img src="<?php echo htmlspecialchars($imageUrl); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>" onerror="this.src='https://via.placeholder.com/300x300/1f1f22/8ff5ff?text=Product'">
										</div>
										<div class="item-details">
											<div class="item-header">
												<h4 onclick="window.location.href='product.php?id=<?php echo $product['product_id']; ?>'"><?php echo htmlspecialchars($product['product_name']); ?></h4>
												<div class="price-container">
													<span class="item-price">R<?php echo number_format($finalPrice, 2); ?></span>
													<?php if ($hasDiscount): ?>
														<span class="original-price">R<?php echo number_format($originalPrice, 2); ?></span>
													<?php endif; ?>
												</div>
											</div>
											<p class="item-description"><?php echo htmlspecialchars(substr($product['product_description'] ?? '', 0, 80)); ?>...</p>
											<div class="seller-info">
												<span><?php echo htmlspecialchars($product['store_name'] ?: ($product['full_name'] ?? 'Seller')); ?></span>
												<div class="rating"><i class="fas fa-star"></i><span><?php echo $product['seller_rating'] ? number_format($product['seller_rating'], 1) : 'New'; ?></span></div>
											</div>
										</div>
									</article>
									<?php endforeach; ?>
								</div>
								<button class="scroll-btn" onclick="this.previousElementSibling.scrollBy({left:300,behavior:'smooth'})"><i class="fas fa-chevron-right"></i></button>
							</div>
						</section>
						<?php endif; ?>
					<?php endif; ?>
				<?php endif; ?>
				</div>
			</section>
			
			<hr/>
			
			<section class="benefits-section">
				<div class="container">
					<div class="benefits-header">
						<h2 class="desktop-heading">Why Choose Nexus?</h2>
						<h2 class="mobile-heading">Choosing NEXUS:</h2>
					</div>
					<div class="benefits-grid">
						<article class="benefit-card">
							<div class="benefit-icon"><i class="fas fa-handshake"></i></div>
							<h3>Responsive Communication</h3>
							<p>Our platform prioritizes and facilitates communication between buyers and sellers with secure messaging.</p>
						</article>
						<article class="benefit-card">
							<div class="benefit-icon"><i class="fas fa-shield-alt"></i></div>
							<h3>Guaranteed Security</h3>
							<p>Verified profiles with reporting and blocking features ensure safety for all users.</p>
						</article>
						<article class="benefit-card">
							<div class="benefit-icon"><i class="fas fa-truck"></i></div>
							<h3>Countrywide Reach</h3>
							<p>Connect with sellers and buyers all around South Africa with integrated map systems.</p>
						</article>
					</div>
				</div>
			</section>
		</main>

		<?php include 'footer.php'; ?>
		
		<script>
			var currentUserLoggedIn = <?php echo isLoggedIn() ? 'true' : 'false'; ?>;
		</script>
		<script type = "text/javascript" src = "utilities.js" ></script>
		<script>
			
			document.addEventListener('DOMContentLoaded', function() {
				var urlParams = new URLSearchParams(window.location.search);
				var searchQuery = urlParams.get('search');
				if (searchQuery) {
					var itemsFeed = document.getElementById('itemsFeed');
					if (itemsFeed) {
						itemsFeed.innerHTML = '<div class="loading">Searching for "' + searchQuery + '"...</div>';
						fetch('api-products.php', {
							method: 'POST',
							headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
							body: JSON.stringify({ categories: [], sort_by: 'latest', search: searchQuery })
						})
						.then(function(r) { return r.json(); })
						.then(function(data) {
							if (data.success && data.products.length > 0) {
								itemsFeed.innerHTML = createGuestSection('Results for "' + searchQuery + '"', data.products);
								attachScrollButtons();
							} else {
								itemsFeed.innerHTML = '<div class="no-results">No results for "' + searchQuery + '"</div>';
							}
						});
					}
				}
			});
			function redirectWithRole(role) {
				window.location.href = 'register.php?role=' + role;
			}
			
			function toggleWishlist(productId) {
				var wishlist = loadWishlist();
				var index = wishlist.indexOf(productId);
				
				if (index === -1) {
					wishlist.push(productId);
					if (typeof showTempMessage === 'function') showTempMessage("Added to wishlist");
					if (currentUserLoggedIn) {
						fetch('wishlist.php', {
							method: 'POST',
							headers: {
								'Content-Type': 'application/x-www-form-urlencoded',
								'X-Requested-With': 'XMLHttpRequest'
							},
							body: 'action=add&product_id=' + productId
						});
					}
				} else {
					wishlist.splice(index, 1);
					if (typeof showTempMessage === 'function') showTempMessage("Removed from wishlist");
					if (currentUserLoggedIn) {
						fetch('wishlist.php', {
							method: 'POST',
							headers: {
								'Content-Type': 'application/x-www-form-urlencoded',
								'X-Requested-With': 'XMLHttpRequest'
							},
							body: 'action=remove&product_id=' + productId
						});
					}
				}
				
				saveWishlist(wishlist);
				updateWishlistIcons();
			}
			
			function updateWishlistIcons() {
				var wishlist = loadWishlist();
				document.querySelectorAll('.wishlist-btn').forEach(function(btn) {
					var card = btn.closest('.item-card');
					if (card) {
						var productId = parseInt(card.getAttribute('data-product-id'));
						var icon = btn.querySelector('i');
						if (icon) {
							if (wishlist.includes(productId)) {
								icon.classList.remove('fa-regular');
								icon.classList.add('fa-solid');
							} else {
								icon.classList.remove('fa-solid');
								icon.classList.add('fa-regular');
							}
						}
					}
				});
			}
			
			document.addEventListener('DOMContentLoaded', function() {
				updateWishlistIcons();
			});
		</script>
	</body>
</html>