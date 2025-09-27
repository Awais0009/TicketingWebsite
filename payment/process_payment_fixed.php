<?php

require_once __DIR__ . '/../inc/db_secure.php';
require_once __DIR__ . '/../inc/security.php';

// Require login
if (!isLoggedIn()) {
    header('Location: ../auth/Login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: checkout.php');
    exit;
}

// Verify CSRF token
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    header('Location: checkout.php?error=' . urlencode('Security error'));
    exit;
}

$user_id = $_SESSION['user_id'];

// Validate required fields
$required_fields = ['full_name', 'email', 'phone', 'address', 'payment_method', 'terms'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        header('Location: checkout.php?error=' . urlencode("Missing required field: $field"));
        exit;
    }
}

// Additional validation for card payment
if ($_POST['payment_method'] === 'card') {
    $card_fields = ['card_number', 'card_cvc', 'card_month', 'card_year'];
    foreach ($card_fields as $field) {
        if (empty($_POST[$field])) {
            header('Location: checkout.php?error=' . urlencode("Missing card information: $field"));
            exit;
        }
    }
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Get cart items and verify availability
    $stmt = $pdo->prepare("
        SELECT 
            ub.id,
            ub.event_id,
            ub.tickets_requested,
            ub.total_amount,
            ub.booking_reference,
            e.title,
            e.available_tickets,
            e.price
        FROM user_bookings ub 
        JOIN events e ON ub.event_id = e.id 
        WHERE ub.user_id = ? AND ub.status = 'cart'
        FOR UPDATE
    ");
    $stmt->execute([$user_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($cart_items)) {
        throw new Exception('Cart is empty');
    }
    
    // Check availability for all items
    foreach ($cart_items as $item) {
        if ($item['available_tickets'] < $item['tickets_requested']) {
            throw new Exception("Not enough tickets available for {$item['title']}");
        }
    }
    
    $total_amount = array_sum(array_column($cart_items, 'total_amount'));
    
    // Process payment (simplified - in real app, integrate with payment gateway)
    $payment_successful = processPayment([
        'amount' => $total_amount,
        'payment_method' => $_POST['payment_method'],
        'card_number' => $_POST['card_number'] ?? null,
        'customer_info' => [
            'name' => $_POST['full_name'],
            'email' => $_POST['email'],
            'phone' => $_POST['phone'],
            'address' => $_POST['address']
        ]
    ]);
    
    if (!$payment_successful['success']) {
        throw new Exception($payment_successful['error'] ?? 'Payment failed');
    }
    
    // Update bookings to 'paid' status and reduce available tickets
    foreach ($cart_items as $item) {
        // Update booking status
        $new_booking_ref = 'TIX_' . strtoupper(substr(md5($user_id . $item['event_id'] . time()), 0, 8));
        
        $stmt = $pdo->prepare("
            UPDATE user_bookings 
            SET status = 'paid', 
                booking_reference = ?,
                updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->execute([$new_booking_ref, $item['id']]);
        
        // Reduce available tickets
        $stmt = $pdo->prepare("
            UPDATE events 
            SET available_tickets = available_tickets - ? 
            WHERE id = ?
        ");
        $stmt->execute([$item['tickets_requested'], $item['event_id']]);
    }
    
    // Create payment record
    $payment_id = 'PAY_' . strtoupper(substr(md5($user_id . time()), 0, 10));
    $stmt = $pdo->prepare("
        INSERT INTO payments (
            user_id, 
            payment_id, 
            amount, 
            payment_method, 
            status, 
            transaction_data,
            created_at
        ) VALUES (?, ?, ?, ?, 'completed', ?, CURRENT_TIMESTAMP)
    ");
    $stmt->execute([
        $user_id,
        $payment_id,
        $total_amount,
        $_POST['payment_method'],
        json_encode([
            'gateway_transaction_id' => $payment_successful['transaction_id'] ?? null,
            'customer_info' => [
                'name' => $_POST['full_name'],
                'email' => $_POST['email'],
                'phone' => $_POST['phone'],
                'address' => $_POST['address']
            ],
            'items' => $cart_items
        ])
    ]);
    
    $pdo->commit();
    
    // Redirect to success page
    header("Location: payment_success.php?payment_id=$payment_id");
    exit;
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Payment processing error: " . $e->getMessage());
    error_log("User ID: $user_id");
    
    header('Location: checkout.php?error=' . urlencode($e->getMessage()));
    exit;
}

/**
 * Simplified payment processing function
 * In a real application, this would integrate with Stripe, PayPal, etc.
 */
function processPayment($payment_data) {
    // Simulate payment processing
    sleep(1); // Simulate API call delay
    
    // Basic validation
    if ($payment_data['payment_method'] === 'card') {
        $card_number = preg_replace('/\s/', '', $payment_data['card_number']);
        
        // Simulate card validation
        if (strlen($card_number) < 13 || !is_numeric($card_number)) {
            return ['success' => false, 'error' => 'Invalid card number'];
        }
        
        // Simulate declined card (for testing)
        if ($card_number === '4000000000000002') {
            return ['success' => false, 'error' => 'Card was declined'];
        }
    }
    
    // Simulate successful payment
    return [
        'success' => true,
        'transaction_id' => 'TXN_' . strtoupper(substr(md5(time() . rand()), 0, 12)),
        'amount' => $payment_data['amount']
    ];
}
?>