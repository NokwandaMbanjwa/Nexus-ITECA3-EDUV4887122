<?php
require_once 'config.php';

$user_type = getUserType();
$user_id = getUserId();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Please login to manage wishlist']);
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    $product_id = $_POST['product_id'] ?? 0;
    
    try {
        if ($action === 'add') {
            // Check if already in wishlist
            $stmt = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$user_id, $product_id]);
            
            if (!$stmt->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
                $stmt->execute([$user_id, $product_id]);
                echo json_encode(['success' => true, 'action' => 'added']);
            } else {
                echo json_encode(['success' => true, 'action' => 'already_exists']);
            }
        } 
        elseif ($action === 'remove') {
            $stmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$user_id, $product_id]);
            echo json_encode(['success' => true, 'action' => 'removed']);
        }
		elseif ($action === 'toggle') {
			// Check if already in wishlist
			$stmt = $pdo->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
			$stmt->execute([$user_id, $product_id]);
			
			if ($stmt->fetch()) {
				$stmt = $pdo->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
				$stmt->execute([$user_id, $product_id]);
				echo json_encode(['success' => true, 'action' => 'removed']);
			} else {
				$stmt = $pdo->prepare("INSERT INTO wishlist (user_id, product_id) VALUES (?, ?)");
				$stmt->execute([$user_id, $product_id]);
				echo json_encode(['success' => true, 'action' => 'added']);
			}
		}
        elseif ($action === 'get') {
            $stmt = $pdo->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $items = $stmt->fetchAll(PDO::FETCH_COLUMN);
            echo json_encode(['success' => true, 'wishlist' => $items]);
        }
        elseif ($action === 'sync') {
            // Sync local wishlist with database
            $localWishlist = json_decode($_POST['local_wishlist'] ?? '[]', true);
            if (is_array($localWishlist) && !empty($localWishlist)) {
                foreach ($localWishlist as $product_id) {
                    $stmt = $pdo->prepare("INSERT IGNORE INTO wishlist (user_id, product_id) VALUES (?, ?)");
                    $stmt->execute([$user_id, $product_id]);
                }
            }
            echo json_encode(['success' => true, 'message' => 'Wishlist synced']);
        }
        else {
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

$wishlist_items = [];
$product_ids = [];

if (isLoggedIn()) {
    // Get from database
    try {
        $stmt = $pdo->prepare("
            SELECT w.*, p.product_name, p.product_description, p.price, p.category,
				(SELECT image_url FROM product_images WHERE product_id = p.product_id AND is_main = 1 LIMIT 1) as product_image,
				sp.store_name, sp.full_name
			FROM wishlist w
			JOIN products p ON w.product_id = p.product_id
			LEFT JOIN seller_profiles sp ON p.seller_id = sp.profile_id
			WHERE w.user_id = ? AND p.listing_status = 'active'
			ORDER BY w.added_at DESC
        ");
        $stmt->execute([$user_id]);
        $wishlist_items = $stmt->fetchAll();
        
        foreach ($wishlist_items as $item) {
            $product_ids[] = $item['product_id'];
        }
    } catch (Exception $e) {
        error_log("Wishlist error: " . $e->getMessage());
    }
}

$explore_link = '#';
if ($user_type === 'guest') {
    $explore_link = 'index.php';
} else {
    $explore_link = 'explore-feed.php';
}
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>NEXUS | My Wishlist</title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="basestyle.css">
		<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
		<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">

		<style>
			.wishlist-container {
				padding: 70px 32px 60px;
				max-width: 1200px;
				margin: 0 auto;
				min-height: 60vh;
			}
			
			.wishlist-content {
				text-align: center;
				max-width: 500px;
				margin: 60px auto;
			}
			
			.empty-icon {
				font-size: 80px;
				color: #ff6b6b;
				margin-bottom: 24px;
			}
			
			.wishlist-content h2 {
				font-size: 28px;
				margin-bottom: 12px;
				color: #f9f5f8;
			}
			
			.empty-message {
				font-size: 16px;
				margin-bottom: 24px;
				color: #adaaad;
			}
			
			.explore-btn {
				display: inline-block;
				background: #8ff5ff;
				color: #0e0e10;
				padding: 12px 32px;
				border-radius: 12px;
				text-decoration: none;
				font-weight: 600;
				transition: all 0.3s;
			}
			
			.explore-btn:hover {
				transform: translateY(-2px);
				box-shadow: 0 0 10px rgba(143, 245, 255, 0.3);
			}
			
			.wishlist-grid {
				display: flex;
				flex-direction: column;
				gap: 32px;
			}
			
			.wishlist-item {
				display: flex;
				gap: 16px;
				padding: 20px 0;
				border-bottom: 1px solid rgba(118, 117, 119, 0.1);
			}
			
			.wishlist-item:hover {
				border-color: rgba(143, 245, 255, 0.3);
				box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
			}
			
			.wishlist-item-image {
				width: 100px;
				height: 100px;
				flex-shrink: 0;
				background: #0e0e10;
				border-radius: 12px;
				overflow: hidden;
			}
			
			.wishlist-item-image img {
				width: 100%;
				height: 100%;
				object-fit: cover;
			}
			
			.wishlist-item:hover .wishlist-item-image img {
				transform: scale(1.05);
			}
			
			.wishlist-item-details {
				padding: 16px;
				flex: 1;
			}
			
			.wishlist-item-details h3 {
				font-size: 16px;
				margin-bottom: 8px;
				color: #f9f5f8;
				line-height: 1.4;
			}
			
			.wishlist-item-details .seller {
				font-size: 12px;
				color: #8ff5ff;
				margin-bottom: 8px;
				display: flex;
				align-items: center;
				gap: 6px;
			}
			
			.wishlist-item-details .seller i {
				font-size: 11px;
			}
			
			.wishlist-item-details .price {
				font-size: 20px;
				font-weight: bold;
				color: #8ff5ff;
				margin-bottom: 8px;
			}
			
			.wishlist-item-details .description {
				font-size: 13px;
				color: #adaaad;
				line-height: 1.5;
				margin-bottom: 12px;
			}
			
			.wishlist-item-actions {
				display: flex;
				flex-direction: row;
				align-items: flex-start;
				gap: 12px;
			}
			
			.remove-btn {
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
			
			.remove-btn:hover {
				background-color: #ff6b6b;
				color: #0e0e10;
			}
			
			.add-to-cart-btn {
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
			
			.add-to-cart-btn:hover {
				background-color: #8ff5ff;
				color: #0e0e10;
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
			
			.sync-message {
				position: fixed;
				bottom: 20px;
				right: 20px;
				background: #8ff5ff;
				color: #0e0e10;
				padding: 12px 20px;
				border-radius: 8px;
				font-weight: 600;
				z-index: 1000;
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

			@media (max-width: 1024px) {
				.wishlist-container {
					padding: 70px 24px 50px;
				}
				
				.wishlist-item {
					gap: 16px;
					padding: 18px 0;
				}
				
				.wishlist-item-image {
					width: 90px;
					height: 90px;
				}
				
				.wishlist-item-details {
					padding: 12px;
				}
				
				.wishlist-item-details h3 {
					font-size: 15px;
				}
				
				.wishlist-item-details .price {
					font-size: 18px;
				}
				
				.wishlist-item-details .description {
					font-size: 12px;
				}
				
				.wishlist-item-actions {
					gap: 10px;
				}
				
				.remove-btn,
				.add-to-cart-btn {
					padding: 8px 20px;
					font-size: 13px;
				}
			}

			@media (max-width: 768px) {
				.wishlist-container {
					padding: 100px 16px 40px;
				}
				
				.wishlist-header h1 {
					font-size: 26px;
				}
				
				.wishlist-header p {
					font-size: 14px;
				}
				
				.wishlist-grid {
					gap: 0;
				}
				
				.wishlist-item {
					flex-direction: row;
					align-items: flex-start;
					gap: 14px;
					padding: 16px 0;
					flex-wrap: wrap;
				}
				
				.wishlist-item-image {
					width: 70px;
					height: 70px;
					border-radius: 10px;
				}
				
				.wishlist-item-details {
					flex: 1;
					min-width: 0;
					padding: 0;
				}
				
				.wishlist-item-details h3 {
					font-size: 16px;
					margin-bottom: 4px;
				}
				
				.wishlist-item-details .seller {
					font-size: 13px;
					margin-bottom: 4px;
				}
				
				.wishlist-item-details .price {
					font-size: 16px;
					margin-bottom: 6px;
				}
				
				.wishlist-item-details .description {
					font-size: 13px;
					margin-bottom: 10px;
					line-height: 1.4;
					display: -webkit-box;
					-webkit-line-clamp: 2;
					-webkit-box-orient: vertical;
					overflow: hidden;
				}
				
				.wishlist-item-actions {
					flex-direction: row;
					justify-content: flex-start;
					gap: 10px;
					width: 100%;
					padding-left: 84px;
				}
				
				.remove-btn,
				.add-to-cart-btn {
					padding: 8px 18px;
					font-size: 13px;
					white-space: nowrap;
				}
			}

			@media (max-width: 480px) {
				.wishlist-container {
					padding: 100px 12px 30px;
				}
				
				.wishlist-header h1 {
					font-size: 22px;
				}
				
				.wishlist-header p {
					font-size: 13px;
				}
				
				.wishlist-item {
					gap: 12px;
					padding: 14px 0;
				}
				
				.wishlist-item-image {
					width: 60px;
					height: 60px;
					border-radius: 8px;
				}
				
				.wishlist-item-details h3 {
					font-size: 15px;
				}
				
				.wishlist-item-details .seller {
					font-size: 12px;
				}
				
				.wishlist-item-details .price {
					font-size: 15px;
				}
				
				.wishlist-item-details .description {
					font-size: 12px;
					margin-bottom: 8px;
				}
				
				.wishlist-item-actions {
					padding-left: 72px; 
					gap: 8px;
				}
				
				.remove-btn,
				.add-to-cart-btn {
					padding: 7px 14px;
					font-size: 12px;
				}
			}

			@media (max-width: 360px) {
				.wishlist-header h1 {
					font-size: 20px;
				}
				
				.wishlist-item-image {
					width: 55px;
					height: 55px;
				}
				
				.wishlist-item-details h3 {
					font-size: 14px;
				}
				
				.wishlist-item-details .description {
					font-size: 11px;
					-webkit-line-clamp: 1;
				}
				
				.wishlist-item-actions {
					padding-left: 67px;
				}
				
				.remove-btn,
				.add-to-cart-btn {
					padding: 6px 12px;
					font-size: 11px;
				}
			}
		</style>
	</head>

	<body>
		<?php include 'header.php'; ?>
		
		<main>
			<div class="wishlist-container" id="wishlistContainer">
				<div id="wishlistContent">
					<div class="loading">
						<i class="fas fa-spinner"></i>
						<p>Loading your wishlist...</p>
					</div>
				</div>
			</div>
		</main>
		
		<?php include 'footer.php'; ?>
		
		<script type = "text/javascript" src = "utilities.js" ></script>
		<script>
			let currentUserLoggedIn = <?php echo isLoggedIn() ? 'true' : 'false'; ?>;
			let currentUserId = <?php echo getUserId() ?: 'null'; ?>;
			let wishlistItems = [];

			const exploreLink = '<?php echo $explore_link; ?>';
			document.addEventListener('DOMContentLoaded', function() {
				if (currentUserLoggedIn) {
					// Logged in user - sync and load from database
					syncLocalWishlistWithDatabase();
					loadWishlistFromDatabase();
					checkPendingCartItem();
				} else {
					// Guest user - load from local storage
					loadWishlistFromLocal();
				}
			});
			
			// Check for pending cart item after login
			function checkPendingCartItem() {
				const pendingItem = sessionStorage.getItem('pending_cart_item');
				if (pendingItem && currentUserLoggedIn) {
					const item = JSON.parse(pendingItem);
					addToWishlistCart(item.id, item.name, item.price);
					sessionStorage.removeItem('pending_cart_item');
				}
			}
			
			// Sync local wishlist with database when user logs in
			function syncLocalWishlistWithDatabase() {
				const localWishlist = loadWishlist();
				
				if (localWishlist.length > 0) {
					fetch('wishlist.php', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded',
							'X-Requested-With': 'XMLHttpRequest'
						},
						body: `action=sync&local_wishlist=${JSON.stringify(localWishlist)}`
					})
					.then(response => response.json())
					.then(data => {
						if (data.success) {
							// Clear local wishlist after sync
							saveWishlist([]);
							showSyncMessage('Wishlist synced with your account!');
							loadWishlistFromDatabase();
						}
					})
					.catch(error => console.error('Sync error:', error));
				}
			}
			
			// Load wishlist from database for logged-in users
			function loadWishlistFromDatabase() {
				const container = document.getElementById('wishlistContent');
				container.innerHTML = '<div class="loading"><i class="fas fa-spinner"></i><p>Loading your wishlist...</p></div>';
				
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
					if (data.success && data.wishlist) {
						const productIds = data.wishlist;
						if (productIds.length === 0) {
							showEmptyWishlist();
						} else {
							fetchProductDetails(productIds);
						}
					} else {
						showEmptyWishlist();
					}
				})
				.catch(error => {
					console.error('Error:', error);
					showEmptyWishlist();
				});
			}
			
			// Fetch product details for wishlist items
			function fetchProductDetails(productIds) {
				if (productIds.length === 0) {
					showEmptyWishlist();
					return;
				}
				
				fetch(`get_products.php?product_ids=${productIds.join(',')}`)
					.then(response => response.json())
					.then(products => {
						if (products.error) {
							showEmptyWishlist();
						} else {
							displayWishlistItems(products);
						}
					})
					.catch(error => {
						console.error('Error fetching products:', error);
						showEmptyWishlist();
					});
			}
			
			// Load wishlist from local storage for guests
			function loadWishlistFromLocal() {
				const localWishlist = loadWishlist();
				
				if (localWishlist.length === 0) {
					showEmptyWishlist();
					return;
				}
				
				// For guests, product details are fetched from an API
				fetch(`get_products.php?product_ids=${localWishlist.join(',')}`)
					.then(response => response.json())
					.then(products => {
						if (products.error || products.length === 0) {
							showEmptyWishlist();
						} else {
							displayWishlistItems(products, true);
						}
					})
					.catch(error => {
						console.error('Error:', error);
						showEmptyWishlist();
					});
			}
			
			function displayWishlistItems(products, isGuest = false) {
				const container = document.getElementById('wishlistContent');
				
				if (!products || products.length === 0) {
					showEmptyWishlist();
					return;
				}
				
				let html = '<div class="wishlist-grid">';
				
				products.forEach(product => {
					const productId = product.product_id;
					const imageUrl = product.product_image || 'https://via.placeholder.com/300x300/1f1f22/8ff5ff?text=Product';
					const storeName = product.store_name || 'Independent Seller';
					const description = product.product_description ? product.product_description.substring(0, 100) : 'No description available';
					
					html += `
						<div class="wishlist-item" data-product-id="${productId}">
							<div class="wishlist-item-image">
								<img src="${imageUrl}" alt="${escapeHtml(product.product_name)}" onerror="this.src='https://via.placeholder.com/300x300/1f1f22/8ff5ff?text=Product'">
							</div>
							<div class="wishlist-item-details">
								<h3>${escapeHtml(product.product_name)}</h3>
								<div class="seller">
									<i class="fas fa-store"></i>
									<span>${escapeHtml(storeName)}</span>
								</div>
								<div class="price">R${parseFloat(product.price).toLocaleString()}</div>
								<div class="description">${escapeHtml(description)}${description.length >= 100 ? '...' : ''}</div>
							</div>
							<div class="wishlist-item-actions">
								<button class="remove-btn" onclick="removeFromWishlist(${productId})">Remove</button>
								<button class="add-to-cart-btn" onclick="addToWishlistCart(${productId}, '${escapeHtml(product.product_name)}', ${product.price})">Add to Cart</button>
							</div>
						</div>
					`;
				});
				
				html += '</div>';
				container.innerHTML = html;
			}
			
			function removeFromWishlist(productId) {
				if (currentUserLoggedIn) {
					fetch('wishlist.php', {
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
							showTempMessage('Item removed from wishlist');
							const itemCard = document.querySelector(`.wishlist-item[data-product-id="${productId}"]`);
							if (itemCard) {
								itemCard.remove();
							}
							const remainingItems = document.querySelectorAll('.wishlist-item');
							if (remainingItems.length === 0) {
								showEmptyWishlist();
							}
							updateWishlistCountBadge();
						}
					})
					.catch(error => {
						console.error('Error:', error);
						showTempMessage('Failed to remove item');
					});
				} else {
					let wishlist = loadWishlist();
					const index = wishlist.indexOf(productId);
					if (index !== -1) {
						wishlist.splice(index, 1);
						saveWishlist(wishlist);
						showTempMessage('Item removed from wishlist');
						
						const itemCard = document.querySelector(`.wishlist-item[data-product-id="${productId}"]`);
						if (itemCard) {
							itemCard.remove();
						}
						
						const remainingItems = document.querySelectorAll('.wishlist-item');
						if (remainingItems.length === 0) {
							showEmptyWishlist();
						}
						updateWishlistCountBadge();
					}
				}
			}
			
			// Add to cart from wishlist
			function addToWishlistCart(productId, productName, productPrice) {
				if (!currentUserLoggedIn) {
					if (confirm("You need to sign in to add items to your cart. Click OK to go to the login page.")) {
						sessionStorage.setItem('pending_cart_item', JSON.stringify({
							id: productId,
							name: productName,
							price: productPrice
						}));
						window.location.href = "login.php";
					}
					return;
				}

				fetch('cart.php', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
						'X-Requested-With': 'XMLHttpRequest'
					},
					body: `action=add&product_id=${productId}&quantity=1`
				})
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						removeFromWishlist(productId);
						showTempMessage(`${productName} added to cart and removed from wishlist!`);
					} else {
						showTempMessage(data.message || 'Failed to add to cart');
					}
				})
				.catch(error => {
					console.error('Error:', error);
					showTempMessage('Failed to add to cart');
				});
			}
			
			function showEmptyWishlist() {
				const container = document.getElementById('wishlistContent');
				container.innerHTML = `
					<div class="wishlist-content">
						<div class="empty-icon">
							<i class="far fa-heart"></i>
						</div>
						<h2>Your wishlist is empty</h2>
						<p class="empty-message">Save items you love to your wishlist and they'll appear here.</p>
						<a href="${exploreLink}" class="explore-btn"> Start Exploring </a>
					</div>
				`;
			}
			
			// Helper function to escape HTML
			function escapeHtml(str) {
				if (!str) return '';
				return str.replace(/[&<>]/g, function(m) {
					if (m === '&') return '&amp;';
					if (m === '<') return '&lt;';
					if (m === '>') return '&gt;';
					return m;
				});
			}
			
			function showSyncMessage(message) {
				const msgDiv = document.createElement('div');
				msgDiv.className = 'sync-message';
				msgDiv.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
				document.body.appendChild(msgDiv);
				
				setTimeout(() => {
					msgDiv.remove();
				}, 3000);
			}
			
			function showTempMessage(message) {
				let messageDiv = document.getElementById('tempMessage');
				if (!messageDiv) {
					messageDiv = document.createElement('div');
					messageDiv.id = 'tempMessage';
					messageDiv.style.position = 'fixed';
					messageDiv.style.bottom = '20px';
					messageDiv.style.right = '20px';
					messageDiv.style.backgroundColor = '#1f1f22';
					messageDiv.style.color = '#8ff5ff';
					messageDiv.style.padding = '12px 20px';
					messageDiv.style.borderRadius = '8px';
					messageDiv.style.zIndex = '9999';
					messageDiv.style.border = '1px solid #8ff5ff';
					document.body.appendChild(messageDiv);
				}
				messageDiv.textContent = message;
				messageDiv.style.display = 'block';
				setTimeout(() => {
					messageDiv.style.display = 'none';
				}, 3000);
			}
			
			window.addToCart = function(productId, productName, productPrice) {
				if (!currentUserLoggedIn) {
					if (confirm("You need to sign in to add items to your cart. Click OK to go to the registration page.")) {
						window.location.href = "register.php";
					}
					return false;
				}
				return true;
			};
			
			function updateWishlistCountBadge() {
				if (currentUserLoggedIn) {
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
						if (data.success && data.wishlist) {
							const count = data.wishlist.length;
							updateBadgeUI(count);
						}
					})
					.catch(error => console.error('Error:', error));
				} else {
					const count = loadWishlist().length;
					updateBadgeUI(count);
				}
			}
			
			function updateBadgeUI(count) {
				const wishlistLink = document.querySelector('.icon-btn[aria-label="Wishlist"]');
				if (wishlistLink) {
					const existingBadge = wishlistLink.querySelector('.wishlist-badge');
					if (existingBadge) existingBadge.remove();
					
					if (count > 0) {
						const badge = document.createElement('span');
						badge.className = 'wishlist-badge';
						badge.textContent = count;
						badge.style.position = 'absolute';
						badge.style.top = '-5px';
						badge.style.right = '-10px';
						badge.style.backgroundColor = '#ff6b6b';
						badge.style.color = 'white';
						badge.style.borderRadius = '50%';
						badge.style.padding = '2px 6px';
						badge.style.fontSize = '10px';
						badge.style.fontWeight = 'bold';
						wishlistLink.style.position = 'relative';
						wishlistLink.appendChild(badge);
					}
				}
			}
		</script>
	</body>
</html>