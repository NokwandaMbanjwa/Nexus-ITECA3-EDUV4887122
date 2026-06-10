<?php
require_once 'config.php';

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$product_id) {
    header('Location: explore-feed.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT p.*, sp.profile_id as seller_profile_id, sp.store_name, sp.full_name as seller_name, 
           sp.verification_status, sp.user_id as seller_user_id,
           (SELECT COUNT(*) FROM product_images WHERE product_id = p.product_id) as image_count
    FROM products p
    JOIN seller_profiles sp ON p.seller_id = sp.profile_id
    WHERE p.product_id = ? AND p.listing_status = 'active' AND p.approval_status = 'approved'
");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    header('Location: explore-feed.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY display_order, is_main DESC");
$stmt->execute([$product_id]);
$product_images = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT pr.*, u.user_id, 
		   COALESCE(bp.full_name, sp.full_name) as reviewer_name,
		   0 as helpful_count
	FROM product_reviews pr
    JOIN nexus_users u ON pr.user_id = u.user_id
    LEFT JOIN buyer_profiles bp ON u.user_id = bp.user_id
    LEFT JOIN seller_profiles sp ON u.user_id = sp.user_id
    WHERE pr.product_id = ?
    ORDER BY pr.created_at DESC
");
$stmt->execute([$product_id]);
$reviews = $stmt->fetchAll();

$avg_rating = 0;
$rating_counts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
if (!empty($reviews)) {
    $total_rating = 0;
    foreach ($reviews as $review) {
        $total_rating += $review['rating'];
        $rating_counts[$review['rating']]++;
    }
    $avg_rating = round($total_rating / count($reviews), 1);
}

$user_reviewed = false;
$user_rating = 0;
if (isLoggedIn()) {
    $stmt = $pdo->prepare("SELECT review_id, rating FROM product_reviews WHERE product_id = ? AND user_id = ?");
    $stmt->execute([$product_id, getUserId()]);
    $existing_review = $stmt->fetch();
    if ($existing_review) {
        $user_reviewed = true;
        $user_rating = $existing_review['rating'];
    }
}

$has_purchased = false;
if (isLoggedIn()) {
    $stmt = $pdo->prepare("
        SELECT oi.order_item_id FROM order_items oi
        JOIN orders o ON oi.order_id = o.order_id
        WHERE oi.product_id = ? AND o.buyer_id = ? AND o.status = 'delivered'
        LIMIT 1
    ");
    $stmt->execute([$product_id, getUserId()]);
    $has_purchased = (bool)$stmt->fetch();
}

$stmt = $pdo->prepare("UPDATE products SET view_count = view_count + 1 WHERE product_id = ?");
$stmt->execute([$product_id]);

$review_error = '';
$review_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review']) && isLoggedIn()) {
    $rating = (int)$_POST['rating'];
    $review_text = trim($_POST['review_text']);
    
    if ($rating < 1 || $rating > 5) {
        $review_error = "Please select a rating";
    } elseif (empty($review_text)) {
        $review_error = "Please write a review";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT review_id FROM product_reviews WHERE product_id = ? AND user_id = ?");
            $stmt->execute([$product_id, getUserId()]);
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("UPDATE product_reviews SET rating = ?, review_text = ?, updated_at = NOW() WHERE product_id = ? AND user_id = ?");
                $stmt->execute([$rating, $review_text, $product_id, getUserId()]);
                $review_success = "Your review has been updated!";
            } else {
                $stmt = $pdo->prepare("INSERT INTO product_reviews (product_id, user_id, rating, review_text, is_verified_purchase) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$product_id, getUserId(), $rating, $review_text, $has_purchased]);
                $review_success = "Thank you for your review!";
            }
            
            header("Location: product.php?id=$product_id");
            exit;
        } catch (Exception $e) {
            $review_error = "Failed to submit review. Please try again.";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['helpful']) && isLoggedIn()) {
    $review_id = (int)$_POST['review_id'];
    $stmt = $pdo->prepare("INSERT IGNORE INTO review_helpful (review_id, user_id) VALUES (?, ?)");
    $stmt->execute([$review_id, getUserId()]);
    header("Location: product.php?id=$product_id");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart']) && isLoggedIn()) {
    $quantity = (int)$_POST['quantity'];
    $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
    $stmt->execute([getUserId(), $product_id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        $new_quantity = $existing['quantity'] + $quantity;
        $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$new_quantity, getUserId(), $product_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
        $stmt->execute([getUserId(), $product_id, $quantity]);
    }
    
    header("Location: cart.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title><?php echo htmlspecialchars($product['product_name']); ?> | NEXUS</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0");
    <link rel="stylesheet" href="basestyle.css">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
	<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
	<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
	<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    
    <style>
        /* Keep all your existing styles, just add this new style */
        .btn-report-item {
            background: transparent;
            border: 1px solid #ff4444;
            color: #ff4444;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            margin-left: 12px;
        }
        
        .btn-report-item:hover {
            background: rgba(255, 68, 68, 0.1);
            transform: translateY(-2px);
        }
        
        /* Rest of your existing styles remain the same */
        .product-page {
            padding: 120px 0 60px;
        }
        
        .product-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
        }
        
        .product-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 60px;
        }

        .product-gallery {
            position: sticky;
            top: 100px;
        }
        
        .main-image {
            width: 100%;
            aspect-ratio: 1 / 1;
            background: #19191c;
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 16px;
            border: 1px solid #2a2a2a;
        }
        
        .main-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .thumbnail-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
        }
        
        .thumbnail {
            aspect-ratio: 1 / 1;
            background: #19191c;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.2s;
        }
        
        .thumbnail:hover,
        .thumbnail.active {
            border-color: #8ff5ff;
        }
        
        .thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-info h1 {
            font-size: 28px;
            color: #fff;
            margin-bottom: 12px;
        }
        
        .product-price {
            font-size: 32px;
            font-weight: bold;
            color: #8ff5ff;
            margin-bottom: 16px;
        }
        
        .original-price {
            font-size: 18px;
            color: #888;
            text-decoration: line-through;
            margin-left: 12px;
            font-weight: normal;
        }
        
        .discount-badge {
            display: inline-block;
            background: #ff6b6b;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            margin-left: 12px;
        }
        
        .seller-info {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 0;
            border-top: 1px solid #2a2a2a;
            border-bottom: 1px solid #2a2a2a;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .seller-avatar {
            width: 50px;
            height: 50px;
            background: #8ff5ff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: bold;
            color: #0e0e10;
        }
        
        .seller-details h3 {
            font-size: 16px;
            margin-bottom: 4px;
        }
        
        .seller-details p {
            color: #888;
            font-size: 13px;
        }
        .seller-details h3 a:hover,
		.seller-details p a:hover {
			color: #8ff5ff !important;
			text-decoration: underline !important;
		}
        .verified-badge {
            color: #4caf50;
            font-size: 12px;
            margin-left: 6px;
        }
        
        .message-seller {
            background: transparent;
            border: 1px solid #8ff5ff;
            color: #8ff5ff;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .message-seller:hover {
            background: rgba(143, 245, 255, 0.1);
        }
        
        .rating-summary {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }
        
        .avg-rating {
            font-size: 24px;
            font-weight: bold;
            color: #ffc107;
        }
        
        .stars {
            color: #ffc107;
            font-size: 14px;
        }
        
        .review-count {
            color: #888;
            font-size: 13px;
        }
        
        .quantity-selector {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 20px 0;
        }
        
        .quantity-selector label {
            color: #aaa;
        }
        
        .quantity-input {
            width: 60px;
            padding: 8px;
            background: #0e0e10;
            border: 1px solid #2a2a2a;
            border-radius: 8px;
            color: #e5e5e5;
            text-align: center;
        }
        
        .action-buttons {
            display: flex;
            gap: 16px;
            margin-top: 20px;
        }
        
        .btn-add-to-cart {
            flex: 1;
            background: #8ff5ff;
            color: #0e0e10;
            border: none;
            padding: 14px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-add-to-cart:hover {
            background: #6dd5e0;
            transform: translateY(-2px);
        }
        
        .btn-wishlist {
            background: transparent;
            border: 1px solid #2a2a2a;
            color: #e5e5e5;
            padding: 14px 24px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .btn-wishlist.active {
            border-color: #ff6b6b;
            color: #ff6b6b;
        }
        
        .product-description {
            margin: 32px 0;
        }
        
        .product-description h3 {
            margin-bottom: 16px;
            color: #fff;
        }
        
        .product-description p {
            color: #adaaad;
            line-height: 1.6;
        }
        
        .reviews-section {
            border-top: 1px solid #2a2a2a;
            padding-top: 40px;
        }
        
        .reviews-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .write-review-btn {
            background: #8ff5ff;
            color: #0e0e10;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
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
        
        .verified-purchase {
            background: rgba(76, 175, 80, 0.15);
            color: #4caf50;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            margin-left: 8px;
        }
        
        .review-date {
            color: #888;
            font-size: 12px;
        }
        
        .review-stars {
            color: #ffc107;
            font-size: 12px;
            margin-bottom: 8px;
        }
        
        .review-text {
            color: #adaaad;
            line-height: 1.5;
            margin-bottom: 12px;
        }
        
        .helpful-btn {
            background: none;
            border: none;
            color: #888;
            font-size: 12px;
            cursor: pointer;
        }
        
        .helpful-btn:hover {
            color: #8ff5ff;
        }
        
        .review-modal {
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
        
        .review-modal.show {
            display: flex;
        }
        
        .review-modal-content {
            background: #19191c;
            border-radius: 16px;
            padding: 32px;
            max-width: 500px;
            width: 90%;
        }
        
        .rating-input {
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
        
        @media (max-width: 768px) {
            .product-layout {
                grid-template-columns: 1fr;
            }
            .product-gallery {
                position: static;
            }
            .thumbnail-grid {
                grid-template-columns: repeat(5, 1fr);
            }
            .seller-info {
                flex-direction: column;
                align-items: flex-start;
            }
            .btn-report-item {
                margin-left: 0;
                margin-top: 8px;
            }
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>
    
    <main class="product-page">
        <div class="product-container">
            <div class="product-layout">
                <div class="product-gallery">
                    <div class="main-image" id="mainImage">
                        <img src="<?php echo htmlspecialchars($product_images[0]['image_url'] ?? $product['product_image'] ?? 'https://via.placeholder.com/600x600/1f1f22/8ff5ff?text=No+Image'); ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                    </div>
                    <div class="thumbnail-grid" id="thumbnailGrid">
                        <?php foreach ($product_images as $index => $img): ?>
                            <div class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>" data-image="<?php echo htmlspecialchars($img['image_url']); ?>">
                                <img src="<?php echo htmlspecialchars($img['image_url']); ?>" alt="Thumbnail <?php echo $index + 1; ?>">
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="product-info">
                    <h1><?php echo htmlspecialchars($product['product_name']); ?></h1>
                    
                    <div class="product-price">
                        R<?php echo number_format($product['price'], 2); ?>
                        <?php if ($product['original_price'] && $product['original_price'] > $product['price']): ?>
                            <span class="original-price">R<?php echo number_format($product['original_price'], 2); ?></span>
                            <span class="discount-badge"><?php echo round((1 - $product['price'] / $product['original_price']) * 100); ?>%</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="rating-summary">
                        <span class="avg-rating"><?php echo $avg_rating; ?></span>
                        <div class="stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star<?php echo $i <= round($avg_rating) ? '' : '-o'; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <span class="review-count"><?php echo count($reviews); ?> review(s)</span>
                    </div>
                    
                    <div class="seller-info">
                        <div class="seller-avatar">
                            <?php echo strtoupper(substr($product['store_name'] ?? $product['seller_name'], 0, 1)); ?>
                        </div>
                        <div class="seller-details">
                            <h3>
								<a href="user-profile.php?id=<?php echo $product['seller_user_id']; ?>" style="color: #fff; text-decoration: none;">
									<?php echo htmlspecialchars($product['store_name'] ?? $product['seller_name']); ?>
								</a>
								<?php if ($product['verification_status'] === 'approved'): ?>
									<span class="verified-badge"><i class="fas fa-check-circle"></i> Verified</span>
								<?php endif; ?>
							</h3>
                            <p>
								<a href="user-profile.php?id=<?php echo $product['seller_user_id']; ?>" style="color: #888; text-decoration: none;">
									<?php echo htmlspecialchars($product['seller_name']); ?>
								</a>
							</p>
                        </div>
                        <?php if (isLoggedIn() && getUserId() != $product['seller_user_id']): ?>
                            <button class="message-seller" onclick="messageSeller(<?php echo $product['seller_user_id']; ?>, '<?php echo htmlspecialchars($product['store_name'] ?? $product['seller_name']); ?>')">
                                <i class="fas fa-envelope"></i> Message
                            </button>
                            <button class="btn-report-item" onclick="reportItem(<?php echo $product['product_id']; ?>, '<?php echo htmlspecialchars($product['product_name']); ?>')">
                                <i class="fas fa-flag"></i> Report Item
                            </button>
                        <?php endif; ?>
                    </div>
                    
                    <div class="product-description">
                        <h3>Description</h3>
                        <p><?php echo nl2br(htmlspecialchars($product['product_description'])); ?></p>
                    </div>
                    
                    <div class="product-meta">
                        <p><strong>Condition:</strong> <?php echo htmlspecialchars($product['item_condition'] ?? 'Not specified'); ?></p>
                        <p><strong>Category:</strong> <?php echo htmlspecialchars($product['category']); ?></p>
                        <p><strong>Stock:</strong> <?php echo $product['stock_quantity']; ?> available</p>
                    </div>
                    
                    <?php if (isLoggedIn() && getUserId() != $product['seller_user_id']): ?>
                        <form method="post" class="add-to-cart-form">
                            <div class="quantity-selector">
                                <label>Quantity:</label>
                                <input type="number" name="quantity" class="quantity-input" value="1" min="1" max="<?php echo $product['stock_quantity']; ?>">
                            </div>
                            <div class="action-buttons">
                                <button type="submit" name="add_to_cart" class="btn-add-to-cart">
                                    <i class="fas fa-shopping-cart"></i> Add to Cart
                                </button>
                                <button type="button" class="btn-wishlist" id="wishlistBtn" onclick="toggleWishlist(<?php echo $product_id; ?>)">
                                    <i class="fas fa-heart"></i> Wishlist
                                </button>
                            </div>
                        </form>
                    <?php elseif (!isLoggedIn()): ?>
                        <div class="action-buttons">
                            <button class="btn-add-to-cart" onclick="alert('Please login to add items to cart')">
                                <i class="fas fa-shopping-cart"></i> Login to Purchase
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="reviews-section">
                <div class="reviews-header">
                    <h2>Customer Reviews</h2>
                    <?php if (isLoggedIn() && getUserId() != $product['seller_user_id'] && !$user_reviewed): ?>
                        <button class="write-review-btn" onclick="openReviewModal()">
                            <i class="fas fa-star"></i> Write a Review
                        </button>
                    <?php elseif ($user_reviewed): ?>
                        <button class="write-review-btn" onclick="openReviewModal()">
                            <i class="fas fa-edit"></i> Edit Your Review
                        </button>
                    <?php endif; ?>
                </div>
                
                <?php if (empty($reviews)): ?>
                    <p style="color: #888; text-align: center; padding: 40px;">No reviews yet. Be the first to review this product!</p>
                <?php else: ?>
                    <?php foreach ($reviews as $review): ?>
                        <div class="review-card">
                            <div class="review-header">
                                <div>
                                    <span class="reviewer-name"><?php echo htmlspecialchars($review['reviewer_name']); ?></span>
                                    <?php if ($review['is_verified_purchase']): ?>
                                        <span class="verified-purchase"><i class="fas fa-check"></i> Verified Purchase</span>
                                    <?php endif; ?>
                                </div>
                                <span class="review-date"><?php echo date('d M Y', strtotime($review['created_at'])); ?></span>
                            </div>
                            <div class="review-stars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star<?php echo $i <= $review['rating'] ? '' : '-o'; ?>"></i>
                                <?php endfor; ?>
                            </div>
                            <div class="review-text">
                                <?php echo nl2br(htmlspecialchars($review['review_text'])); ?>
                            </div>
                            <form method="post" style="display: inline;">
                                <input type="hidden" name="review_id" value="<?php echo $review['review_id']; ?>">
                                <button type="submit" name="helpful" class="helpful-btn">
                                    <i class="fas fa-thumbs-up"></i> Helpful (<?php echo $review['helpful_count']; ?>)
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <div class="review-modal" id="reviewModal">
        <div class="review-modal-content">
            <h3 style="color: #8ff5ff; margin-bottom: 20px;"><?php echo $user_reviewed ? 'Edit Your Review' : 'Write a Review'; ?></h3>
            <?php if ($review_error): ?>
                <div class="alert alert-error"><?php echo $review_error; ?></div>
            <?php endif; ?>
            <form method="post">
                <label>Your Rating</label>
                <div class="rating-input" id="ratingInput">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="fas fa-star rating-star-input" data-rating="<?php echo $i; ?>"></i>
                    <?php endfor; ?>
                </div>
                <input type="hidden" name="rating" id="ratingValue" value="<?php echo $user_rating; ?>">
                
                <label style="margin-top: 20px; display: block;">Your Review</label>
                <textarea name="review_text" rows="5" style="width: 100%; padding: 12px; background: #0e0e10; border: 1px solid #2a2a2a; border-radius: 8px; color: #e5e5e5; margin-top: 8px;" placeholder="Share your experience with this product..."><?php 
                    $current_review = '';
                    foreach ($reviews as $r) {
                        if ($r['user_id'] == getUserId()) {
                            $current_review = $r['review'];
                            break;
                        }
                    }
                    echo htmlspecialchars($current_review);
                ?></textarea>
                
                <div style="display: flex; gap: 12px; margin-top: 20px;">
                    <button type="button" class="btn-add-to-cart" style="background: #333;" onclick="closeReviewModal()">Cancel</button>
                    <button type="submit" name="submit_review" class="btn-add-to-cart">Submit Review</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <script type = "text/javascript" src = "utilities.js" ></script>
    <script>
        document.querySelectorAll('.thumbnail').forEach(thumb => {
            thumb.addEventListener('click', function() {
                const imageUrl = this.getAttribute('data-image');
                document.getElementById('mainImage').innerHTML = `<img src="${imageUrl}" alt="Product image">`;
                document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
            });
        });
        
        function messageSeller(sellerId, sellerName) {
            window.location.href = `messages.php?user=${sellerId}&name=${encodeURIComponent(sellerName)}`;
        }
        
        // New function to report an item
        function reportItem(productId, productName) {
            if (!<?php echo isLoggedIn() ? 'true' : 'false'; ?>) {
                if (confirm('Please login to report an item. Click OK to go to login page.')) {
                    window.location.href = 'login.php';
                }
                return;
            }
            
            // Redirect to report page with product ID as the reported user (seller)
            // Using seller_user_id from the product
            const sellerId = <?php echo $product['seller_user_id']; ?>;
            window.location.href = `report.php?user=${sellerId}&product=${encodeURIComponent(productId)}&product_name=${encodeURIComponent(productName)}`;
        }
        
        function toggleWishlist(productId) {
            if (!<?php echo isLoggedIn() ? 'true' : 'false'; ?>) {
                if (confirm('Please login to add items to your wishlist. Click OK to go to login page.')) {
                    window.location.href = 'login.php';
                }
                return;
            }
            
            fetch('wishlist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: `action=toggle&product_id=${productId}`
            })
            .then(response => response.json())
            .then(data => {
                const btn = document.getElementById('wishlistBtn');
                if (data.action === 'added') {
                    btn.classList.add('active');
                    showTempMessage('Added to wishlist');
                } else {
                    btn.classList.remove('active');
                    showTempMessage('Removed from wishlist');
                }
                updateWishlistCountBadge();
            });
        }
        
        function checkWishlistStatus() {
            if (!<?php echo isLoggedIn() ? 'true' : 'false'; ?>) return;
            
            fetch('wishlist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'action=get'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.wishlist && data.wishlist.includes(<?php echo $product_id; ?>)) {
                    document.getElementById('wishlistBtn').classList.add('active');
                }
            });
        }
        
        const reviewModal = document.getElementById('reviewModal');
        
        function openReviewModal() {
            reviewModal.classList.add('show');
        }
        
        function closeReviewModal() {
            reviewModal.classList.remove('show');
        }

        window.addEventListener('click', (e) => {
            if (e.target === reviewModal) {
                reviewModal.classList.remove('show');
            }
        });

        let selectedRating = <?php echo $user_rating; ?>;
        const ratingStars = document.querySelectorAll('.rating-star-input');
        const ratingValue = document.getElementById('ratingValue');
        
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
        
        function showTempMessage(message) {
            let msgDiv = document.getElementById('tempMsg');
            if (!msgDiv) {
                msgDiv = document.createElement('div');
                msgDiv.id = 'tempMsg';
                msgDiv.style.position = 'fixed';
                msgDiv.style.bottom = '20px';
                msgDiv.style.right = '20px';
                msgDiv.style.backgroundColor = '#1f1f22';
                msgDiv.style.color = '#8ff5ff';
                msgDiv.style.padding = '12px 20px';
                msgDiv.style.borderRadius = '8px';
                msgDiv.style.zIndex = '9999';
                msgDiv.style.border = '1px solid #8ff5ff';
                document.body.appendChild(msgDiv);
            }
            msgDiv.textContent = message;
            msgDiv.style.display = 'block';
            setTimeout(() => {
                msgDiv.style.display = 'none';
            }, 3000);
        }
        
        // Initialize
        checkWishlistStatus();
    </script>
</body>
</html>