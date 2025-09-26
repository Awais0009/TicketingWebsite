<?php
require_once __DIR__ . '/../inc/db.php';

// Test unique payment ID generation
$payment_id = 'PAY_' . date('YmdHis') . '_' . microtime(true) . '_3_' . rand(100, 999);
$payment_id = str_replace('.', '', $payment_id);

echo "Generated payment ID: $payment_id\n";
echo "Length: " . strlen($payment_id) . "\n";

// Check if it's unique
$stmt = $pdo->prepare('SELECT booking_reference FROM user_bookings WHERE booking_reference = ?');
$stmt->execute([$payment_id]);
echo "Unique check: " . ($stmt->rowCount() == 0 ? 'UNIQUE' : 'DUPLICATE') . "\n";

// Now test the actual payment process for user 4
$user_id = 4;
echo "\nTesting payment for user $user_id:\n";

try {
    // Get cart items
    $stmt = $pdo->prepare("SELECT event_id, tickets_requested FROM user_bookings WHERE user_id = ? AND status = 'cart'");
    $stmt->execute([$user_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($cart_items) . " cart items\n";
    
    if (!empty($cart_items)) {
        // Clear existing references
        $stmt = $pdo->prepare("UPDATE user_bookings SET booking_reference = NULL WHERE user_id = ? AND status = 'cart'");
        $stmt->execute([$user_id]);
        
        // Update to paid
        $stmt = $pdo->prepare("UPDATE user_bookings SET status = 'paid', booking_reference = ? WHERE user_id = ? AND status = 'cart'");
        $result = $stmt->execute([$payment_id, $user_id]);
        
        echo "Payment result: " . ($result ? 'SUCCESS' : 'FAILED') . "\n";
        echo "Rows updated: " . $stmt->rowCount() . "\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>