<?php
require_once 'config.php';
require_once 'stripe-config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$amount = $input['amount'] ?? 0;

try {
    $paymentIntent = \Stripe\PaymentIntent::create([
        'amount' => round($amount * 100), 
        'currency' => 'zar',
        'description' => 'NEXUS Marketplace Purchase',
        'metadata' => [
            'user_id' => getUserId()
        ]
    ]);

    echo json_encode([
        'success' => true,
        'clientSecret' => $paymentIntent->client_secret
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>