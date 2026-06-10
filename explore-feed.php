<?php
require_once 'config.php';

$is_search = !empty($_GET['search']) || !empty($_GET['category']);
$category_filter = $_GET['category'] ?? '';
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if (!isLoggedIn() && !$is_search && !$is_ajax) {
    header('Location: login.php');
    exit;
}

if (!isLoggedIn()) {
    $user_id = 0;
    $user_type = 'guest';
} else {
    $user_id = getUserId();
    $user_type = getUserType();
}

$seller_profile_id = null;
if ($user_type === 'seller' && $user_id) {
    $stmt = $pdo->prepare("SELECT profile_id FROM seller_profiles WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $seller = $stmt->fetch();
    $seller_profile_id = $seller['profile_id'] ?? null;
}

$user_lat = null;
$user_lng = null;
$stmt = $pdo->prepare("SELECT latitude, longitude FROM user_addresses WHERE user_id = ? AND is_default = TRUE");
$stmt->execute([$user_id]);
$address = $stmt->fetch();
if ($address) {
    $user_lat = $address['latitude'];
    $user_lng = $address['longitude'];
}

if ($is_ajax && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $filters = json_decode(file_get_contents('php://input'), true);
    $categories = $filters['categories'] ?? [];
    $sort_by = $filters['sort_by'] ?? 'latest';
    $min_price = $filters['min_price'] ?? null;
    $max_price = $filters['max_price'] ?? null;
    $search = $filters['search'] ?? '';
    
    try {
        $sql = "SELECT p.*, sp.store_name, sp.full_name, sp.profile_id as seller_profile_id,
			COALESCE(p.original_price, p.price) as original_price,
			(SELECT AVG(rating) FROM product_reviews WHERE product_id = p.product_id) as seller_rating,
			(SELECT image_url FROM product_images WHERE product_id = p.product_id AND is_main = 1 LIMIT 1) as product_image
			FROM products p
			LEFT JOIN seller_profiles sp ON p.seller_id = sp.profile_id
			WHERE p.listing_status = 'active' AND p.approval_status = 'approved' AND p.stock_quantity > 0";

		$params = [];

		// Exclude seller's own items
		if ($seller_profile_id) {
			$sql .= " AND p.seller_id != ?";
			$params[] = $seller_profile_id;
		}
        
        if (!empty($categories)) {
            $placeholders = implode(',', array_fill(0, count($categories), '?'));
            $sql .= " AND p.category IN ($placeholders)";
            $params = array_merge($params, $categories);
        }
        
        if ($min_price !== null && is_numeric($min_price)) {
            $sql .= " AND p.price >= ?";
            $params[] = $min_price;
        }
        if ($max_price !== null && is_numeric($max_price)) {
            $sql .= " AND p.price <= ?";
            $params[] = $max_price;
        }
        
        if (!empty($search)) {
            $sql .= " AND (p.product_name LIKE ? OR p.product_description LIKE ? OR sp.store_name LIKE ?)";
            $search_term = "%$search%";
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        switch ($sort_by) {
            case 'price-low-high': $sql .= " ORDER BY p.price ASC"; break;
            case 'price-high-low': $sql .= " ORDER BY p.price DESC"; break;
            case 'highest-rated': $sql .= " ORDER BY seller_rating DESC"; break;
            default: $sql .= " ORDER BY p.created_at DESC";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();
        
        echo json_encode(['success' => true, 'products' => $products]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

function getDiscountItems($pdo, $seller_profile_id = null) {
    $sql = "SELECT p.*, sp.store_name, sp.full_name, sp.profile_id as seller_profile_id,
                   COALESCE(p.original_price, p.price) as original_price,
                   (SELECT AVG(rating) FROM product_reviews WHERE product_id = p.product_id) as seller_rating,
                   (SELECT image_url FROM product_images WHERE product_id = p.product_id AND is_main = 1 LIMIT 1) as product_image
                   FROM products p
                   LEFT JOIN seller_profiles sp ON p.seller_id = sp.profile_id
                   WHERE p.listing_status = 'active' AND p.approval_status = 'approved' AND p.stock_quantity > 0 AND p.discount_percentage > 0";
    
    $params = [];
    if ($seller_profile_id) {
        $sql .= " AND p.seller_id != ?";
        $params[] = $seller_profile_id;
    }
    
    $sql .= " ORDER BY p.discount_percentage DESC LIMIT 20";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getRecentItems($pdo, $seller_profile_id = null) {
    $sql = "SELECT p.*, sp.store_name, sp.full_name, sp.profile_id as seller_profile_id,
                   COALESCE(p.original_price, p.price) as original_price,
                   (SELECT AVG(rating) FROM product_reviews WHERE product_id = p.product_id) as seller_rating,
                   (SELECT image_url FROM product_images WHERE product_id = p.product_id AND is_main = 1 LIMIT 1) as product_image
                   FROM products p
                   LEFT JOIN seller_profiles sp ON p.seller_id = sp.profile_id
                   WHERE p.listing_status = 'active' AND p.approval_status = 'approved' AND p.stock_quantity > 0";
    
    $params = [];
    if ($seller_profile_id) {
        $sql .= " AND p.seller_id != ?";
        $params[] = $seller_profile_id;
    }
    
    $sql .= " ORDER BY p.created_at DESC LIMIT 20";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getPopularItems($pdo, $seller_profile_id = null) {
    $sql = "SELECT p.*, sp.store_name, sp.full_name, sp.profile_id as seller_profile_id,
                   COALESCE(p.original_price, p.price) as original_price,
                   (SELECT AVG(rating) FROM product_reviews WHERE product_id = p.product_id) as seller_rating,
                   (SELECT image_url FROM product_images WHERE product_id = p.product_id AND is_main = 1 LIMIT 1) as product_image
                   FROM products p
                   LEFT JOIN seller_profiles sp ON p.seller_id = sp.profile_id
                   WHERE p.listing_status = 'active' AND p.approval_status = 'approved' AND p.stock_quantity > 0";
    
    $params = [];
    if ($seller_profile_id) {
        $sql .= " AND p.seller_id != ?";
        $params[] = $seller_profile_id;
    }
    
    $sql .= " ORDER BY p.view_count DESC LIMIT 20";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getFollowedSellersItems($pdo, $user_id, $seller_profile_id = null) {
    $sql = "SELECT p.*, sp.store_name, sp.full_name, sp.profile_id as seller_profile_id,
                   COALESCE(p.original_price, p.price) as original_price,
                   (SELECT AVG(rating) FROM product_reviews WHERE product_id = p.product_id) as seller_rating,
                   (SELECT image_url FROM product_images WHERE product_id = p.product_id AND is_main = 1 LIMIT 1) as product_image
                   FROM products p
                   LEFT JOIN seller_profiles sp ON p.seller_id = sp.profile_id
                   WHERE p.listing_status = 'active' AND p.approval_status = 'approved' AND p.stock_quantity > 0
                   AND sp.profile_id IN (SELECT following_id FROM user_follows WHERE follower_id = ?)";
    
    $params = [$user_id];
    if ($seller_profile_id) {
        $sql .= " AND p.seller_id != ?";
        $params[] = $seller_profile_id;
    }
    
    $sql .= " ORDER BY p.created_at DESC LIMIT 20";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getPersonalizedItems($pdo, $user_id, $seller_profile_id = null) {
    $stmt = $pdo->prepare("SELECT p.category, COUNT(*) as view_count
                           FROM user_browsing_history ubh
                           JOIN products p ON ubh.product_id = p.product_id
                           WHERE ubh.user_id = ? GROUP BY p.category ORDER BY view_count DESC LIMIT 20");
    $stmt->execute([$user_id]);
    $categories = $stmt->fetchAll();
    
    if (empty($categories)) return [];
    
    $category_names = array_column($categories, 'category');
    $placeholders = implode(',', array_fill(0, count($category_names), '?'));
    
    $sql = "SELECT p.*, sp.store_name, sp.full_name, sp.profile_id as seller_profile_id,
                   COALESCE(p.original_price, p.price) as original_price,
                   (SELECT AVG(rating) FROM product_reviews WHERE product_id = p.product_id) as seller_rating,
                   (SELECT image_url FROM product_images WHERE product_id = p.product_id AND is_main = 1 LIMIT 1) as product_image
                   FROM products p
                   LEFT JOIN seller_profiles sp ON p.seller_id = sp.profile_id
                   WHERE p.listing_status = 'active' AND p.approval_status = 'approved' AND p.stock_quantity > 0 AND p.category IN ($placeholders)";
    
    $params = $category_names;
    if ($seller_profile_id) {
        $sql .= " AND p.seller_id != ?";
        $params[] = $seller_profile_id;
    }
    
    $sql .= " ORDER BY p.created_at DESC LIMIT 20";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getSimilarSellersItems($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT DISTINCT category FROM products 
                           WHERE seller_id = (SELECT profile_id FROM seller_profiles WHERE user_id = ?)");
    $stmt->execute([$user_id]);
    $my_categories = $stmt->fetchAll();
    
    if (empty($my_categories)) return [];
    
    $category_names = array_column($my_categories, 'category');
    $placeholders = implode(',', array_fill(0, count($category_names), '?'));
    
    $stmt = $pdo->prepare("SELECT p.*, sp.store_name, sp.full_name, sp.profile_id as seller_profile_id,
                           COALESCE(p.original_price, p.price) as original_price,
						   (SELECT AVG(rating) FROM product_reviews WHERE product_id = p.product_id) as seller_rating,
                           (SELECT image_url FROM product_images WHERE product_id = p.product_id AND is_main = 1 LIMIT 1) as product_image
                           FROM products p
                           LEFT JOIN seller_profiles sp ON p.seller_id = sp.profile_id
                           WHERE p.listing_status = 'active' AND p.approval_status = 'approved' AND p.stock_quantity > 0
                           AND p.category IN ($placeholders) AND sp.user_id != ?
                           ORDER BY p.created_at DESC LIMIT 20");
    $params = array_merge($category_names, [$user_id]);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

$discount_items = getDiscountItems($pdo, $seller_profile_id);
$recent_items = getRecentItems($pdo, $seller_profile_id);
$popular_items = getPopularItems($pdo, $seller_profile_id);
$followed_items = getFollowedSellersItems($pdo, $user_id, $seller_profile_id);
$personalized_items = getPersonalizedItems($pdo, $user_id, $seller_profile_id);
$similar_sellers_items = ($user_type === 'seller') ? getSimilarSellersItems($pdo, $user_id, $seller_profile_id) : [];

if (!empty($_GET['view_product'])) {
    $product_id = (int)$_GET['view_product'];
    $stmt = $pdo->prepare("INSERT INTO user_browsing_history (user_id, product_id) VALUES (?, ?)");
    $stmt->execute([$user_id, $product_id]);
    $stmt = $pdo->prepare("UPDATE products SET view_count = view_count + 1 WHERE product_id = ?");
    $stmt->execute([$product_id]);
}
?>
<!DOCTYPE html>
<html lang="en">
	<head>
		<title>NEXUS | Explore</title>
		<meta charset="UTF-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<link rel="stylesheet" href="basestyle.css">
		<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
		<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
		<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
		<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
		<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
		<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
		
		<style>
			.explore-section {
				padding: 20px 0 20px;
				background-color: #0e0e10;
			}

			.section-heading {
				text-align: left;
				display: inline-block;
				margin-bottom: 20px;
			}

			.section-heading:after {
				left: 0;
				transform: none;
			}

			.explore-layout {
				display: grid;
				grid-template-columns: 280px 1fr;
				gap: 40px;
			}

			.filters-sidebar {
				background-color: #131315;
				border: 1px solid #052a33;
				border-radius: 16px;
				padding: 20px;
				height: fit-content;
				position: sticky;
				top: 100px;
				overflow-y: auto;
				scrollbar-width: thin;
				scrollbar-color: #85ffff #131315;
			}

			.filters-sidebar::-webkit-scrollbar {
				width: 6px;
			}

			.filters-sidebar::-webkit-scrollbar-track {
				background: #131315;
				border-radius: 10px;
			}

			.filters-sidebar::-webkit-scrollbar-thumb {
				background: #85ffff;
				border-radius: 10px;
			}

			.filters-sidebar h3 {
				margin-bottom: 20px;
				color: #8ff5ff;
			}

			.filter-group {
				margin-bottom: 20px;
			}

			.filter-group h4 {
				margin-bottom: 12px;
				font-size: 14px;
				color: #e5e5e5;
			}

			.checkbox-group {
				display: flex;
				flex-direction: column;
				gap: 10px;
				max-height: 200px;
				overflow-y: auto;
			}

			.checkbox-item {
				display: flex;
				align-items: center;
				gap: 8px;
				cursor: pointer;
				color: #adaaad;
				font-size: 13px;
			}

			.checkbox-item:hover {
				color: #8ff5ff;
			}

			.checkbox-item input[type="checkbox"] {
				width: 14px;
				height: 14px;
				cursor: pointer;
			}

			.radio-group {
				display: flex;
				flex-direction: column;
				gap: 10px;
			}

			.radio-item {
				display: flex;
				align-items: center;
				gap: 8px;
				cursor: pointer;
				color: #adaaad;
				font-size: 13px;
			}

			.radio-item:hover {
				color: #8ff5ff;
			}

			.radio-item input[type="radio"] {
				width: 14px;
				height: 14px;
				cursor: pointer;
			}

			.price-range {
				display: flex;
				align-items: center;
				gap: 8px;
				flex-wrap: wrap;
			}

			.price-range input {
				flex: 1;
				min-width: 80px;
				background-color: #0e0e10;
				border: 1px solid rgba(118, 117, 119, 0.3);
				border-radius: 10px;
				padding: 10px 12px;
				color: #e5e5e5;
				font-size: 14px;
			}

			.price-range input:focus {
				outline: none;
				border-color: #8ff5ff;
			}

			.price-range span {
				color: #adaaad;
			}

			.apply-filters {
				width: 100%;
				background-color: #8ff5ff;
				color: #0e0e10;
				border: none;
				border-radius: 10px;
				padding: 10px;
				font-weight: 600;
				cursor: pointer;
				margin-top: 10px;
			}

			.apply-filters:hover {
				background-color: #6dd5e0;
				transform: translateY(-1px);
			}

			.scroll-wrapper {
				position: relative;
				display: flex;
				align-items: center;
				gap: 12px;
				margin-bottom: 10px;
				width: 100%;
				min-width: 0;
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
			}

			.scroll-btn:hover {
				background-color: #8ff5ff;
				border-color: #8ff5ff;
				color: #0e0e10;
			}

			.items-feed {
				width: 100%;
				min-width: 0;
				overflow: hidden;
			}

			.items-grid {
				display: flex;
				flex-direction: row;
				gap: 16px;
				overflow-x: auto;
				scroll-behavior: smooth;
				padding: 20px 0;
				flex: 1;
				flex-wrap: nowrap;
				scrollbar-width: none;
			}

			.items-grid::-webkit-scrollbar {
				display: none;
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

			.add-to-cart-btn {
				width: 100%;
				margin-top: 8px;
				background-color: #8ff5ff;
				color: #0e0e10;
				border: none;
				padding: 6px;
				border-radius: 6px;
				font-size: 11px;
				font-weight: 600;
				cursor: pointer;
			}

			.add-to-cart-btn:hover {
				background-color: #6dd5e0;
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

			.no-results {
				text-align: center;
				padding: 60px;
				color: #adaaad;
			}
			.mobile-sort-bar {
				display: none;
			}
			@media only screen and (max-width: 1024px) {
				.filters-sidebar {
					display: none;
				}
				.explore-layout {
					grid-template-columns: 1fr;
					gap: 0;
				}
				.scroll-wrapper {
					margin-bottom: 5px;
				}
				.section-heading {
					margin-bottom: 8px;
				}
				.scroll-btn {
					display: none;
				}
				.items-feed {
					overflow: visible;
				}
				.items-grid {
					gap: 12px;
					padding: 10px 0;
					padding-right: 30px;
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
				.add-to-cart-btn {
					font-size: 10px;
					padding: 5px;
				}
				.section-heading {
					font-size: 17px;
					margin-bottom: 10px;
				}
				.product-section {
					padding: 0 8px;
				}
				.mobile-sort-bar {
					display: block;
					padding: 0 0 16px;
					position: relative;
				}
				.sort-toggle {
					display: flex;
					align-items: center;
					gap: 8px;
					padding: 10px 16px;
					background: #1a1a1a;
					border: 1px solid #2a2a2a;
					border-radius: 10px;
					color: #8ff5ff;
					font-size: 13px;
					cursor: pointer;
					width: 100%;
					justify-content: space-between;
				}
				.sort-toggle i:first-child {
					font-size: 14px;
				}
				.sort-toggle i:last-child {
					font-size: 10px;
					transition: transform 0.2s;
				}
				.sort-toggle.open i:last-child {
					transform: rotate(180deg);
				}
				.sort-panel {
					display: none;
					position: absolute;
					top: 100%;
					left: 0;
					right: 0;
					background: #1a1a1a;
					border: 1px solid #2a2a2a;
					border-radius: 12px;
					padding: 14px;
					z-index: 50;
					margin-top: 4px;
				}
				.sort-panel.show {
					display: block;
				}
				.sort-panel .radio-item {
					display: flex;
					align-items: center;
					gap: 10px;
					padding: 10px 0;
					color: #adaaad;
					font-size: 13px;
					cursor: pointer;
				}
				.sort-panel .radio-item input {
					accent-color: #8ff5ff;
				}
				.sort-panel .price-row {
					display: flex;
					align-items: center;
					gap: 8px;
					margin-top: 12px;
					padding-top: 12px;
					border-top: 1px solid #2a2a2a;
				}
				.sort-panel .price-row input {
					flex: 1;
					padding: 10px;
					background: #0e0e10;
					border: 1px solid #2a2a2a;
					border-radius: 8px;
					color: #e5e5e5;
					font-size: 13px;
				}
				.sort-panel .price-row span {
					color: #666;
				}
				.sort-panel .apply-btn {
					width: 100%;
					margin-top: 12px;
					padding: 10px;
					background: #8ff5ff;
					color: #0e0e10;
					border: none;
					border-radius: 8px;
					font-weight: 600;
					font-size: 13px;
					cursor: pointer;
				}
			}

			@media only screen and (max-width: 480px) {
				.item-card {
					width: 145px;
					flex: 0 0 145px;
				}
				.item-image {
					height: 135px;
				}
				.section-heading {
					font-size: 15px;
				}
			}
			
			.sort-panel .price-row input {
				flex: 1;
				padding: 8px;
				background: #0e0e10;
				border: 1px solid #2a2a2a;
				border-radius: 8px;
				color: #e5e5e5;
				font-size: 13px;
				max-width: 120px;
			}

			.product-section {
				padding: 16px 8px;
				border-bottom: 1px solid rgba(255, 255, 255, 0.04);
				margin-bottom: 4px;
			}
			.product-section:last-child {
				border-bottom: none;
			}

			@media only screen and (max-width: 360px) {
				.item-card {
					width: 130px;
					flex: 0 0 130px;
				}
				.item-image {
					height: 120px;
				}
				.section-heading {
					font-size: 14px;
				}
				.sort-panel .price-row input {
					padding: 6px;
					max-width: 100px;
				}
			}
		</style>
	</head>
	<body>
		<?php include 'header.php'; ?>
		<main class="explore-section">
			<div class="container">
				<div class="explore-layout">
					<aside class="filters-sidebar">
						<h3><i class="fas fa-filter"></i> Filters</h3>
						<div class="filter-group">
							<h4>Category</h4>
							<div class="checkbox-group" id="categoryFilters">
								<label class="checkbox-item"><input type="checkbox" value="Baby & Toddler"> Baby & Toddler</label>
								<label class="checkbox-item"><input type="checkbox" value="Beauty"> Beauty</label>
								<label class="checkbox-item"><input type="checkbox" value="Books"> Books</label>
								<label class="checkbox-item"><input type="checkbox" value="Electronics"> Electronics</label>
								<label class="checkbox-item"><input type="checkbox" value="Entertainment"> Entertainment</label>
								<label class="checkbox-item"><input type="checkbox" value="Fashion"> Fashion</label>
								<label class="checkbox-item"><input type="checkbox" value="Gaming"> Gaming</label>
								<label class="checkbox-item"><input type="checkbox" value="Home & Living"> Home & Living</label>
								<label class="checkbox-item"><input type="checkbox" value="Office"> Office</label>
								<label class="checkbox-item"><input type="checkbox" value="Pets"> Pets</label>
								<label class="checkbox-item"><input type="checkbox" value="Sport"> Sport</label>
								<label class="checkbox-item"><input type="checkbox" value="Other"> Other</label>
							</div>
						</div>
						<div class="filter-group">
							<h4>Sort By</h4>
							<div class="radio-group" id="sortFilters">
								<label class="radio-item"><input type="radio" name="sort-by" value="latest" checked> Latest</label>
								<label class="radio-item"><input type="radio" name="sort-by" value="price-low-high"> Price: Low to High</label>
								<label class="radio-item"><input type="radio" name="sort-by" value="price-high-low"> Price: High to Low</label>
								<label class="radio-item"><input type="radio" name="sort-by" value="highest-rated"> Highest Rated</label>
							</div>
						</div>
						<div class="filter-group">
							<h4>Price Range (R)</h4>
							<div class="price-range">
								<input type="number" id="price-min" placeholder="Min">
								<span>-</span>
								<input type="number" id="price-max" placeholder="Max">
							</div>
						</div>
						<button class="apply-filters" id="applyFiltersBtn">Apply Filters</button>
					</aside>
					
					<div class="mobile-sort-bar">
						<button class="sort-toggle" id="mobileSortBtn">
							<i class="fas fa-sliders-h"></i> Sort & Filter <i class="fas fa-chevron-down"></i>
						</button>
						<div class="sort-panel" id="mobileSortPanel">
							<div class="radio-group">
								<label class="radio-item"><input type="radio" name="mobile-sort" value="latest" checked> Latest</label>
								<label class="radio-item"><input type="radio" name="mobile-sort" value="price-low-high"> Price: Low to High</label>
								<label class="radio-item"><input type="radio" name="mobile-sort" value="price-high-low"> Price: High to Low</label>
								<label class="radio-item"><input type="radio" name="mobile-sort" value="highest-rated"> Highest Rated</label>
							</div>
							<div class="price-row">
								<input type="number" id="mobilePriceMin" placeholder="Min R">
								<span>-</span>
								<input type="number" id="mobilePriceMax" placeholder="Max R">
							</div>
							<button class="apply-btn" id="mobileApplyBtn">Apply</button>
						</div>
					</div>

					<div class="items-feed" id="itemsFeed">
						<?php
						// Fetch approved products for guest homepage
						$stmt = $pdo->prepare("SELECT p.*, sp.store_name, sp.full_name,
											   COALESCE(p.original_price, p.price) as original_price,
											   (SELECT image_url FROM product_images WHERE product_id = p.product_id AND is_main = 1 LIMIT 1) as product_image
											   FROM products p
											   LEFT JOIN seller_profiles sp ON p.seller_id = sp.profile_id
											   WHERE p.listing_status = 'active' AND p.approval_status = 'approved' AND p.stock_quantity > 0
												ORDER BY p.created_at DESC LIMIT 9");
						$stmt->execute();
						$home_products = $stmt->fetchAll();
						
						if (empty($home_products)): ?>
							<div class="no-results">No products available yet. Check back soon!</div>
						<?php else: ?>
							<section class="product-section">
								<h2 class="section-heading">Recently Added Items</h2>
								<div class="scroll-wrapper">
									<button class="scroll-btn scroll-left" onclick="this.nextElementSibling.scrollBy({left:-280,behavior:'smooth'})"><i class="fas fa-chevron-left"></i></button>
									<div class="items-grid">
										<?php foreach ($home_products as $product): 
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
											<button class="wishlist-btn" onclick="event.stopPropagation(); toggleWishlistFromHomepage(<?php echo $product['product_id']; ?>)">
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
													<div class="rating"><i class="fas fa-star"></i><span>4.5</span></div>
												</div>
											</div>
										</article>
										<?php endforeach; ?>
									</div>
									<button class="scroll-btn scroll-right" onclick="this.previousElementSibling.scrollBy({left:280,behavior:'smooth'})"><i class="fas fa-chevron-right"></i></button>
								</div>
							</section>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</main>
		<?php include 'footer.php'; ?>
		
		<script>
			var currentUserLoggedIn = <?php echo isLoggedIn() ? 'true' : 'false'; ?>;
		</script>
		<script type="text/javascript" src="utilities.js"></script>
		<script>
			currentUserType = '<?php echo $user_type; ?>';
			currentUserId = <?php echo $user_id; ?>;

			var sectionData = {
				discounts: <?php echo json_encode($discount_items); ?>,
				recent: <?php echo json_encode($recent_items); ?>,
				popular: <?php echo json_encode($popular_items); ?>,
				followed: <?php echo json_encode($followed_items); ?>,
				personalized: <?php echo json_encode($personalized_items); ?>,
				similarSellers: <?php echo json_encode($similar_sellers_items); ?>
			};

			function trackProductView(productId) {
				fetch(window.location.pathname + '?view_product=' + productId, { method: 'GET' });
			}

			function displaySections() {
				var urlParams = new URLSearchParams(window.location.search);
				var searchQuery = urlParams.get('search') || '';
				var categoryFilter = urlParams.get('category') || '';

				if (searchQuery || categoryFilter) {
					var categories = categoryFilter ? [categoryFilter] : [];
					document.getElementById('itemsFeed').innerHTML = '<div class="loading">Loading...</div>';
					fetch(window.location.href, {
						method: 'POST',
						headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
						body: JSON.stringify({ categories: categories, sort_by: 'latest', search: searchQuery })
					})
					.then(function(r) { return r.json(); })
					.then(function(data) {
						if (data.success && data.products.length > 0) {
							document.getElementById('itemsFeed').innerHTML = createProductSection('Results', data.products);
							attachScrollListeners(); attachWishlistListeners();
						} else {
							document.getElementById('itemsFeed').innerHTML = '<div class="no-results">No results found.</div>';
						}
					});
					return;
				}

				var feedContainer = document.getElementById('itemsFeed');
				var html = '';

				if (sectionData.discounts && sectionData.discounts.length > 0) html += createProductSection('<i class="fas fa-fire" style="color: #ff6b35;"></i> Discounts', sectionData.discounts);
				if (sectionData.recent && sectionData.recent.length > 0) html += createProductSection('<i class="fas fa-clock" style="color: #34c759;"></i> Recently Added Items', sectionData.recent);
				if (sectionData.popular && sectionData.popular.length > 0) html += createProductSection('<i class="fas fa-chart-line" style="color: #ff2d55;"></i> Popular on Nexus', sectionData.popular);
				if (sectionData.followed && sectionData.followed.length > 0) html += createProductSection('<i class="fas fa-user-check" style="color: #8ff5ff;"></i> From Sellers You Follow', sectionData.followed);
				if (sectionData.personalized && sectionData.personalized.length > 0) html += createProductSection('<i class="fas fa-thumbs-up" style="color: #ffc107;"></i> Recommended For You', sectionData.personalized);
				if (currentUserType === 'seller' && sectionData.similarSellers && sectionData.similarSellers.length > 0) html += createProductSection('<i class="fas fa-users" style="color: #c180ff;"></i> Connect with Similar Sellers', sectionData.similarSellers);

				if (html === '') html = '<div class="no-results"><i class="fas fa-box-open"></i><p>No products found. Check back later!</p></div>';

				feedContainer.innerHTML = html;
				attachScrollListeners();
				attachWishlistListeners();
				attachFollowListeners();
			}

			function createProductSection(title, products) {
				var html = '<section class="product-section"><h2 class="section-heading">' + title + '</h2><div class="scroll-wrapper"><button class="scroll-btn scroll-left"><i class="fas fa-chevron-left"></i></button><div class="items-grid">';

				products.forEach(function(product) {
					var productId = product.product_id;
					var imageUrl = product.product_image || 'https://via.placeholder.com/300x300/1f1f22/8ff5ff?text=Product';
					var originalPrice = parseFloat(product.original_price || product.price);
					var discountPercent = parseFloat(product.discount_percentage || 0);
					var hasDiscount = discountPercent > 0;
					var finalPrice = hasDiscount ? originalPrice * (1 - discountPercent / 100) : parseFloat(product.price);
					var description = product.product_description ? product.product_description.substring(0, 80) : '';
					var rating = product.seller_rating ? parseFloat(product.seller_rating).toFixed(1) : 'New';

					html += '<article class="item-card" data-product-id="' + productId + '">';
					if (hasDiscount) html += '<div class="discount-badge">' + discountPercent + '% OFF</div>';
					html += '<button class="wishlist-btn" onclick="event.stopPropagation(); toggleWishlist(' + productId + ')"><i class="fa-regular fa-heart"></i></button>';
					html += '<div class="item-image" onclick="trackProductView(' + productId + '); window.location.href=\'product.php?id=' + productId + '\'"><img src="' + imageUrl + '" alt="' + escapeHtml(product.product_name) + '" onerror="this.src=\'https://via.placeholder.com/300x300/1f1f22/8ff5ff?text=Product\'"></div>';
					html += '<div class="item-details"><div class="item-header"><h4>' + escapeHtml(product.product_name) + '</h4><div class="price-container"><span class="item-price">R' + parseFloat(finalPrice).toLocaleString() + '</span>';
					if (hasDiscount) html += '<span class="original-price">R' + originalPrice.toLocaleString() + '</span>';
					html += '</div></div><p class="item-description">' + escapeHtml(description) + '...</p><div class="seller-info"><span>' + escapeHtml(product.store_name || product.full_name || 'Seller') + '</span><div class="rating"><i class="fas fa-star"></i><span>' + rating + '</span></div></div><button class="add-to-cart-btn" onclick="event.stopPropagation(); addToCart(' + productId + ', \'' + escapeHtml(product.product_name) + '\', ' + finalPrice + ')"><i class="fas fa-shopping-cart"></i> Add to Cart</button></div></article>';
				});

				html += '</div><button class="scroll-btn scroll-right"><i class="fas fa-chevron-right"></i></button></div></section>';
				return html;
			}

			function escapeHtml(str) {
				if (!str) return '';
				var div = document.createElement('div');
				div.textContent = str;
				return div.innerHTML;
			}

			function attachScrollListeners() {
				document.querySelectorAll('.scroll-wrapper').forEach(function(wrapper) {
					var grid = wrapper.querySelector('.items-grid');
					if (wrapper.querySelector('.scroll-left')) wrapper.querySelector('.scroll-left').onclick = function() { grid.scrollBy({ left: -280, behavior: 'smooth' }); };
					if (wrapper.querySelector('.scroll-right')) wrapper.querySelector('.scroll-right').onclick = function() { grid.scrollBy({ left: 280, behavior: 'smooth' }); };
				});
			}

			function attachWishlistListeners() {
				var wishlist = loadWishlist();
				document.querySelectorAll('.wishlist-btn i').forEach(function(icon) {
					var card = icon.closest('.item-card');
					if (card && wishlist.includes(parseInt(card.getAttribute('data-product-id')))) {
						icon.classList.remove('fa-regular'); icon.classList.add('fa-solid');
					}
				});
			}

			function toggleWishlist(productId) {
				var wishlist = loadWishlist();
				var index = wishlist.indexOf(productId);
				if (index === -1) { wishlist.push(productId); showTempMessage('Item added to wishlist'); }
				else { wishlist.splice(index, 1); showTempMessage('Item removed from wishlist'); }
				saveWishlist(wishlist);
				document.querySelectorAll('.wishlist-btn').forEach(function(btn) {
					var card = btn.closest('.item-card');
					if (card && parseInt(card.getAttribute('data-product-id')) === productId) {
						var icon = btn.querySelector('i');
						wishlist.includes(productId) ? (icon.classList.remove('fa-regular'), icon.classList.add('fa-solid')) : (icon.classList.remove('fa-solid'), icon.classList.add('fa-regular'));
					}
				});
				updateWishlistCountBadge();
			}

			function attachFollowListeners() {}

			function addToCart(productId, productName, price) {
				if (!isUserLoggedIn()) { if (confirm('Please sign in.')) window.location.href = 'register.php'; return false; }
				fetch('cart.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' }, body: 'action=add&product_id=' + productId + '&quantity=1' })
				.then(function(r) { return r.json(); }).then(function(d) { if(d.success) showTempMessage(productName + ' added to cart'); });
				return true;
			}

			document.getElementById('applyFiltersBtn').addEventListener('click', function() {
				var categories = [];
				document.querySelectorAll('#categoryFilters input:checked').forEach(function(cb) { categories.push(cb.value); });
				var sortBy = document.querySelector('input[name="sort-by"]:checked').value;
				var minPrice = document.getElementById('price-min').value;
				var maxPrice = document.getElementById('price-max').value;

				document.getElementById('itemsFeed').innerHTML = '<div class="loading"><i class="fas fa-spinner"></i><p>Loading...</p></div>';
				fetch(window.location.href, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify({ categories: categories, sort_by: sortBy, min_price: minPrice, max_price: maxPrice, search: '' }) })
				.then(function(r) { return r.json(); })
				.then(function(data) {
					if (data.success && data.products.length > 0) { document.getElementById('itemsFeed').innerHTML = createProductSection('Search Results', data.products); attachScrollListeners(); attachWishlistListeners(); }
					else { document.getElementById('itemsFeed').innerHTML = '<div class="no-results">No products match.</div>'; }
				});
			});

			function showTempMessage(message) {
				var msg = document.getElementById('tempMessage');
				if (!msg) { msg = document.createElement('div'); msg.id = 'tempMessage'; msg.style.cssText = 'position:fixed;bottom:20px;right:20px;background:#1f1f22;color:#8ff5ff;padding:12px 20px;border-radius:8px;z-index:9999;border:1px solid #8ff5ff;'; document.body.appendChild(msg); }
				msg.textContent = message; msg.style.display = 'block';
				setTimeout(function() { msg.style.display = 'none'; }, 3000);
			}
			
			var mobileSortBtn = document.getElementById('mobileSortBtn');
			var mobileSortPanel = document.getElementById('mobileSortPanel');

			if (mobileSortBtn && mobileSortPanel) {
				mobileSortBtn.addEventListener('click', function(e) {
					e.stopPropagation();
					mobileSortPanel.classList.toggle('show');
					this.classList.toggle('open');
				});
				document.addEventListener('click', function(e) {
					if (!mobileSortBtn.contains(e.target) && !mobileSortPanel.contains(e.target)) {
						mobileSortPanel.classList.remove('show');
						mobileSortBtn.classList.remove('open');
					}
				});
			}

			document.getElementById('mobileApplyBtn')?.addEventListener('click', function() {
				var sortBy = document.querySelector('input[name="mobile-sort"]:checked').value;
				var minPrice = document.getElementById('mobilePriceMin').value;
				var maxPrice = document.getElementById('mobilePriceMax').value;

				document.getElementById('itemsFeed').innerHTML = '<div class="loading"><i class="fas fa-spinner"></i><p>Loading...</p></div>';
				mobileSortPanel.classList.remove('show');
				mobileSortBtn.classList.remove('open');

				fetch(window.location.href, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
					body: JSON.stringify({ categories: [], sort_by: sortBy, min_price: minPrice || null, max_price: maxPrice || null, search: '' })
				})
				.then(function(r) { return r.json(); })
				.then(function(data) {
					if (data.success && data.products.length > 0) {
						document.getElementById('itemsFeed').innerHTML = createProductSection('Results', data.products);
						attachScrollListeners();
						attachWishlistListeners();
					} else {
						document.getElementById('itemsFeed').innerHTML = '<div class="no-results">No products match.</div>';
					}
				});
			});


			displaySections();
		</script>
	</body>
</html>