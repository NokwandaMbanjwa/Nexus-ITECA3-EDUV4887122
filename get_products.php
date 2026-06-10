<?php
require_once 'config.php';

header('Content-Type: application/json');

if (isset($_GET['product_ids']) && !empty($_GET['product_ids'])) {
    $ids = array_map('intval', explode(',', $_GET['product_ids']));
    
    if (empty($ids)) {
        echo json_encode([]);
        exit;
    }
    
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    try {
        $stmt = $pdo->prepare("SELECT p.*, sp.store_name, sp.full_name,
                               COALESCE(p.original_price, p.price) as original_price,
                               (SELECT image_url FROM product_images WHERE product_id = p.product_id AND is_main = 1 LIMIT 1) as product_image
                               FROM products p
                               LEFT JOIN seller_profiles sp ON p.seller_id = sp.profile_id
                               WHERE p.product_id IN ($placeholders)
                               AND p.listing_status = 'active' AND p.approval_status = 'approved'");
        $stmt->execute($ids);
        $products = $stmt->fetchAll();
        
        // Sort products to match the order of requested IDs
        $productMap = [];
        foreach ($products as $product) {
            $productMap[$product['product_id']] = $product;
        }
        
        $sortedProducts = [];
        foreach ($ids as $id) {
            if (isset($productMap[$id])) {
                $sortedProducts[] = $productMap[$id];
            }
        }
        
        echo json_encode($sortedProducts);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

$categories = isset($_GET['categories']) ? explode(',', $_GET['categories']) : [];
$sort = $_GET['sort'] ?? 'latest';
$min_price = $_GET['min_price'] ?? null;
$max_price = $_GET['max_price'] ?? null;
$search = $_GET['search'] ?? '';

try {
    $sql = "SELECT p.*, sp.store_name, sp.full_name, sp.profile_id as seller_profile_id,
            COALESCE(p.original_price, p.price) as original_price,
            (SELECT AVG(rating) FROM seller_reviews WHERE seller_id = sp.profile_id) as seller_rating,
            (SELECT image_url FROM product_images WHERE product_id = p.product_id AND is_main = 1 LIMIT 1) as product_image
            FROM products p
            LEFT JOIN seller_profiles sp ON p.seller_id = sp.profile_id
            WHERE p.listing_status = 'active' AND p.approval_status = 'approved'";

    $params = [];

    if (!empty($categories)) {
        $placeholders = implode(',', array_fill(0, count($categories), '?'));
        $sql .= " AND p.category IN ($placeholders)";
        $params = array_merge($params, $categories);
    }

    if ($min_price !== null && $min_price !== '' && is_numeric($min_price)) {
        $sql .= " AND p.price >= ?";
        $params[] = floatval($min_price);
    }
    if ($max_price !== null && $max_price !== '' && is_numeric($max_price)) {
        $sql .= " AND p.price <= ?";
        $params[] = floatval($max_price);
    }

    if (!empty($search)) {
        $sql .= " AND (p.product_name LIKE ? OR p.product_description LIKE ? OR sp.store_name LIKE ?)";
        $search_term = "%$search%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }

    switch ($sort) {
        case 'price-low-high':
            $sql .= " ORDER BY p.price ASC";
            break;
        case 'price-high-low':
            $sql .= " ORDER BY p.price DESC";
            break;
        case 'highest-rated':
            $sql .= " ORDER BY seller_rating DESC";
            break;
        case 'latest':
        default:
            $sql .= " ORDER BY p.created_at DESC";
    }

    $sql .= " LIMIT 50";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    echo json_encode($products);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>