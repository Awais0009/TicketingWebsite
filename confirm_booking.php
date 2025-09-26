<?php
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/security.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: auth/login.php');
    exit();
}

$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$booking_id) {
    $_SESSION['booking_errors'] = ["Invalid booking ID."];
    header('Location: user/my_cart.php');
    exit();
}

try {
    // Simple approach - just update status and calculate total
    $stmt = $pdo->prepare("
        SELECT ub.*, e.title, e.price, e.available_tickets, e.event_date 
        FROM user_bookings ub
        JOIN events e ON ub.event_id = e.id
        WHERE ub.id = ? AND ub.user_id = ? AND ub.status = 'cart'
    ");
    $stmt->execute([$booking_id, $_SESSION['user_id']]);
    $booking = $stmt->fetch();
    
    if (!$booking) {
        $_SESSION['booking_errors'] = ["Booking not found or already confirmed."];
        header('Location: user/my_cart.php');
        exit();
    }
    
    // Check availability
    if ($booking['available_tickets'] < $booking['tickets_requested']) {
        $_SESSION['booking_errors'] = ["Only {$booking['available_tickets']} tickets available."];
        header('Location: user/my_cart.php');
        exit();
    }
    
    // Generate booking reference and calculate total
    $booking_reference = 'BK' . strtoupper(uniqid());
    $total_amount = $booking['tickets_requested'] * $booking['price'];
    
    // Update booking to 'booked' status
    $stmt = $pdo->prepare("
        UPDATE user_bookings 
        SET status = 'booked', 
            booking_reference = ?, 
            total_amount = ?,
            updated_at = CURRENT_TIMESTAMP 
        WHERE id = ? AND status = 'cart'
    ");
    
    $result = $stmt->execute([$booking_reference, $total_amount, $booking_id]);
    
    if ($result && $stmt->rowCount() > 0) {
        $_SESSION['booking_success'] = "Booking confirmed! Reference: {$booking_reference}. Please complete payment.";
    } else {
        $_SESSION['booking_errors'] = ["Failed to confirm booking. Please try again."];
    }
    
} catch (Exception $e) {
    error_log("Confirm booking error: " . $e->getMessage());
    $_SESSION['booking_errors'] = ["Database error occurred. Please try again."];
}

header('Location: user/my_cart.php');
exit();
?>