<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once 'config.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->prepare("SELECT p.*, sp.store_name, sp.profile_id as seller_profile_id,
                           COALESCE(p.original_price, p.price) as original_price,
                           (SELECT image_url FROM product_images WHERE product_id = p.product_id AND is_main = 1 LIMIT 1) as product_image
                           FROM products p
                           LEFT JOIN seller_profiles sp ON p.seller_id = sp.profile_id
                           WHERE p.listing_status = 'active' AND p.approval_status = 'approved' AND p.discount_percentage > 0
                           ORDER BY p.discount_percentage DESC LIMIT 10");
    $stmt->execute();
    $discounts = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT p.*, sp.store_name, sp.profile_id as seller_profile_id,
                           COALESCE(p.original_price, p.price) as original_price,
                           (SELECT image_url FROM product_images WHERE product_id = p.product_id AND is_main = 1 LIMIT 1) as product_image
                           FROM products p
                           LEFT JOIN seller_profiles sp ON p.seller_id = sp.profile_id
                           WHERE p.listing_status = 'active' AND p.approval_status = 'approved'
                           ORDER BY p.view_count DESC LIMIT 10");
    $stmt->execute();
    $trending = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT p.*, sp.store_name, sp.profile_id as seller_profile_id,
                           COALESCE(p.original_price, p.price) as original_price,
                           (SELECT image_url FROM product_images WHERE product_id = p.product_id AND is_main = 1 LIMIT 1) as product_image
                           FROM products p
                           LEFT JOIN seller_profiles sp ON p.seller_id = sp.profile_id
                           WHERE p.listing_status = 'active' AND p.approval_status = 'approved'
                           ORDER BY p.created_at DESC LIMIT 10");
    $stmt->execute();
    $recent = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'discounts' => $discounts,
        'trending' => $trending,
        'recent' => $recent
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>