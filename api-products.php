<?php
require_once 'config.php';
header('Content-Type: application/json');

$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if (!$is_ajax || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

$filters = json_decode(file_get_contents('php://input'), true);
$categories = $filters['categories'] ?? [];
$sort_by = $filters['sort_by'] ?? 'latest';
$min_price = $filters['min_price'] ?? null;
$max_price = $filters['max_price'] ?? null;
$search = $filters['search'] ?? '';

try {
    $sql = "SELECT p.*, sp.store_name, sp.profile_id as seller_profile_id,
            COALESCE(p.original_price, p.price) as original_price,
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

    switch ($sort_by) {
        case 'price-low-high':
            $sql .= " ORDER BY p.price ASC";
            break;
        case 'price-high-low':
            $sql .= " ORDER BY p.price DESC";
            break;
        default:
            $sql .= " ORDER BY p.created_at DESC";
    }

    $sql .= " LIMIT 50";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();

    echo json_encode(['success' => true, 'products' => $products]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>