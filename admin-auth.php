<?php
require_once 'config.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: admin-login.php');
    exit;
}

$admin_role = $_SESSION['admin_role'] ?? null;
$is_super_admin = ($admin_role === 'super_admin');

// Define which roles can access which pages
$page_permissions = [
    // Super admin only pages
    'admin-applications.php' => ['super_admin'],
    'transaction-history.php' => ['super_admin'],
    
    // Shared pages
    'admin-dashboard.php' => ['super_admin', 'payments', 'verification', 'safety_support', 'legal', 'social_media'],
    'admin-messages.php' => ['super_admin', 'payments', 'verification', 'safety_support', 'legal', 'social_media'],
    'Explore-Feed.php' => ['super_admin', 'payments', 'verification', 'safety_support', 'legal', 'social_media'],
    
    // Payments department pages
    'admin-bank-details.php' => ['super_admin', 'payments'],
    'admin-payment-gateway.php' => ['super_admin', 'payments'],
    'admin-payment-disputes.php' => ['super_admin', 'payments'],
    
    // Safety & Support department pages
    'admin-buyer-reports.php' => ['super_admin', 'safety_support'],
    'admin-seller-reports.php' => ['super_admin', 'safety_support'],
    'admin-general-reports.php' => ['super_admin', 'safety_support'],
    
    // Verification department pages
    'admin-verify-seller.php' => ['super_admin', 'verification'],
    'admin-verify-submissions.php' => ['super_admin', 'verification'],
    
    // Legal department pages
    'admin-privacy.php' => ['super_admin', 'legal'],
    'admin-terms.php' => ['super_admin', 'legal'],
    
    // Social Media department pages
    'admin-facebook.php' => ['super_admin', 'facebook.nexus.com'],
    'admin-twitter.php' => ['super_admin', 'x.nexus.com'],
    'admin-instagram.php' => ['super_admin', 'instagram.nexus.com'],
    'admin-linkedin.php' => ['super_admin', 'linkedin.nexus.com'],
];

$current_page = basename($_SERVER['PHP_SELF']);

// Check permission for the current page
if (!$is_super_admin && isset($page_permissions[$current_page])) {
    if (!in_array($admin_role, $page_permissions[$current_page])) {
        $_SESSION['admin_error'] = "You don't have permission to access that page.";
        header('Location: admin-dashboard.php');
        exit;
    }
}

function hasAccess($page, $admin_role, $is_super_admin) {
    global $page_permissions;
    if ($is_super_admin) return true;
    if (!isset($page_permissions[$page])) return false;
    return in_array($admin_role, $page_permissions[$page]);
}

function logAdminActivity($action, $details = null) {
    global $pdo;
    $admin_id = getUserId();
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    
    $stmt = $pdo->prepare("INSERT INTO admin_activity_log (admin_id, action, details, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$admin_id, $action, $details, $ip]);
}
?>