<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = getUserId();
$payment_method = $_POST['payment_method'] ?? '';
$reference = $_POST['reference'] ?? '';
$amount = $_POST['amount'] ?? 0;

// Handle file upload
$upload_dir = 'uploads/payments/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if (!isset($_FILES['proof_of_payment']) || $_FILES['proof_of_payment']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['proof_of_payment'];
$allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
$max_size = 5 * 1024 * 1024;

if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, PDF allowed.']);
    exit;
}

if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'File too large. Max 5MB.']);
    exit;
}

$file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$file_name = time() . '_' . $user_id . '_' . uniqid() . '.' . $file_ext;
$file_path = $upload_dir . $file_name;

if (!move_uploaded_file($file['tmp_name'], $file_path)) {
    echo json_encode(['success' => false, 'message' => 'Failed to save file']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Proof of payment uploaded successfully']);
?>