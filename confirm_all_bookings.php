<?php

require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/security.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: auth/login.php');
    exit();
}

// Handle POST request only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: user/my_cart.php');
    exit();
}

$csrf_token = $_POST['csrf_token'] ?? '';

// Validate CSRF token
if (!verifyCSRFToken($csrf_token)) {
    $_SESSION['booking_errors'] = ["Invalid security token."];
    header('Location: user/my_cart.php');
    exit();
}

try {
    // Get all cart items for user
    $stmt = $pdo->prepare("
        SELECT ub.*, e.available_tickets, e.event_date
        FROM user_bookings ub
        JOIN events e ON ub.event_id = e.id
        WHERE ub.user_id = ? AND ub.status = 'cart'
        FOR UPDATE
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $cart_items = $stmt->fetchAll();
    
    if (empty($cart_items)) {
        $_SESSION['booking_errors'] = ["No cart items to confirm."];
        header('Location: user/my_cart.php');
        exit();
    }
    
    // Validate all items
    foreach ($cart_items as $item) {
        if ($item['available_tickets'] < $item['tickets_requested']) {
            $_SESSION['booking_errors'] = ["Not enough tickets available for some events."];
            header('Location: user/my_cart.php');
            exit();
        }
        
        if (strtotime($item['event_date']) <= time()) {
            $_SESSION['booking_errors'] = ["One or more events have already started."];
            header('Location: user/my_cart.php');
            exit();
        }
    }
    
    $pdo->beginTransaction();
    
    // Confirm all bookings
    $confirmed_count = 0;
    foreach ($cart_items as $item) {
        // Update booking status
        $stmt = $pdo->prepare("
            UPDATE user_bookings 
            SET status = 'booked', updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->execute([$item['id']]);
        
        // Reserve tickets
        $stmt = $pdo->prepare("
            UPDATE events 
            SET available_tickets = available_tickets - ? 
            WHERE id = ?
        ");
        $stmt->execute([$item['tickets_requested'], $item['event_id']]);
        
        $confirmed_count++;
    }
    
    $pdo->commit();
    
    $_SESSION['booking_success'] = "Successfully confirmed {$confirmed_count} bookings! Please complete payment to secure your tickets.";
    header("Location: user/my_cart.php");
    exit();
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Confirm all bookings error: " . $e->getMessage());
    $_SESSION['booking_errors'] = ["Failed to confirm bookings: " . $e->getMessage()];
    header('Location: user/my_cart.php');
    exit();
}
?>