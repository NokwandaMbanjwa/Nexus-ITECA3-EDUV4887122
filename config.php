<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'sql300.infinityfree.com';
$dbname = 'if0_42034031_nexus_db';
$username = 'if0_42034031';
$password = 'ZhFzUGI1nWuI';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Auto-login with remember token
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $stmt = $pdo->prepare("SELECT * FROM nexus_users WHERE remember_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if ($user) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['user_type'] = $user['user_type'];
        
        if ($user['user_type'] === 'buyer') {
            $stmt = $pdo->prepare("SELECT full_name, phone_number FROM buyer_profiles WHERE user_id = ?");
            $stmt->execute([$user['user_id']]);
            $profile = $stmt->fetch();
            $_SESSION['full_name'] = $profile['full_name'] ?? 'User';
            $_SESSION['phone'] = $profile['phone_number'] ?? '';
        } elseif ($user['user_type'] === 'seller') {
            $stmt = $pdo->prepare("SELECT full_name, phone_number FROM seller_profiles WHERE user_id = ?");
            $stmt->execute([$user['user_id']]);
            $profile = $stmt->fetch();
            $_SESSION['full_name'] = $profile['full_name'] ?? 'User';
            $_SESSION['phone'] = $profile['phone_number'] ?? '';
        } else {
            $_SESSION['full_name'] = 'Admin';
            $_SESSION['phone'] = '';
        }
    }
}
function getUserType() {
    if (isset($_SESSION['user_id']) && isset($_SESSION['user_type'])) {
        return $_SESSION['user_type'];
    }
    return 'guest';
}

function getUserAccessLevel() {
    $type = getUserType();
    if ($type === 'admin') {
        return 'admin';
    }

    if (isset($_SESSION['user_id'])) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT profile_id FROM seller_profiles WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        if ($stmt->fetch()) {
            return 'seller';
        }
        
        $stmt = $pdo->prepare("SELECT profile_id FROM buyer_profiles WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        if ($stmt->fetch()) {
            return 'buyer';
        }
    }
    
    return $type;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getUserName() {
    return $_SESSION['full_name'] ?? 'Guest';
}

function getUserEmail() {
    return $_SESSION['email'] ?? '';
}
function isAdmin() {
    return $_SESSION['is_admin'] ?? false;
}
function getAgeFromSAID($id_number) {
    // First 6 digits = date of birth (YYMMDD)
    if (!preg_match('/^[0-9]{13}$/', $id_number)) {
        return null;
    }
    
    $year = substr($id_number, 0, 2);
    $month = substr($id_number, 2, 2);
    $day = substr($id_number, 4, 2);
    
    // Determine full year
    $current_year = date('Y');
    $current_short_year = date('y');
    
    if ($year <= $current_short_year) {
        $full_year = '20' . $year;
    } else {
        $full_year = '19' . $year;
    }
    
    $birth_date = $full_year . '-' . $month . '-' . $day;
    $birth_timestamp = strtotime($birth_date);
    
    if (!$birth_timestamp) {
        return null;
    }
    
    $age = date('Y') - date('Y', $birth_timestamp);
    
    if (date('md') < date('md', $birth_timestamp)) {
        $age--;
    }
    
    return $age;
}
?>