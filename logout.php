<?php
require_once 'config.php';

// If switching from admin to user account
if (isset($_GET['switch']) && $_GET['switch'] === 'user') {
    unset($_SESSION['is_admin']);
    unset($_SESSION['admin_role']);
    header('Location: explore-feed.php');
    exit;
}

session_start();

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

header('Location: index.php');
exit;
?>