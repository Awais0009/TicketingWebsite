<?php
require_once __DIR__ . '/../inc/db_secure.php';
require_once __DIR__ . '/../inc/security.php';

if (!isLoggedIn()) {
    header('Location: ../auth/Login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: checkout.php');
    exit;
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    header('Location: checkout.php?error=' . urlencode('Security error'));
    exit;
}

$user_id = $_SESSION['user_id'];

// Validate required fields
$required_fields = ['full_name', 'email', 'phone', 'payment_method', 'terms'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        header('Location: checkout.php?error=' . urlencode('Please fill all required fields'));
        exit;
    }
}

// SIMPLE payment processing - just update status
try {
    // Generate unique payment ID
    $payment_id = 'PAY_' . date('YmdHis') . '_' . $user_id;
    
    // Get cart items first
    $stmt = $pdo->prepare("SELECT event_id, tickets_requested FROM user_bookings WHERE user_id = ? AND status = 'cart'");
    $stmt->execute([$user_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($cart_items)) {
        header('Location: checkout.php?error=' . urlencode('Your cart is empty'));
        exit;
    }
    
    // Update cart to paid (set booking_reference to NULL first to avoid unique constraint)
    $stmt = $pdo->prepare("UPDATE user_bookings SET booking_reference = NULL WHERE user_id = ? AND status = 'cart'");
    $stmt->execute([$user_id]);
    
    // Now update with payment reference
    $stmt = $pdo->prepare("UPDATE user_bookings SET status = 'paid', booking_reference = ? WHERE user_id = ? AND status = 'cart'");
    $result = $stmt->execute([$payment_id, $user_id]);
    
    if ($result) {
        // Reduce available tickets
        foreach ($cart_items as $item) {
            $stmt = $pdo->prepare("UPDATE events SET available_tickets = available_tickets - ? WHERE id = ?");
            $stmt->execute([$item['tickets_requested'], $item['event_id']]);
        }
        
        header('Location: payment_success.php?payment_id=' . urlencode($payment_id));
        exit;
    } else {
        header('Location: checkout.php?error=' . urlencode('Payment failed'));
        exit;
    }
    
} catch (Exception $e) {
    error_log("Payment error: " . $e->getMessage());
    header('Location: checkout.php?error=' . urlencode('Database error'));
    exit;
}
?>