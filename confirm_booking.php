<?php
session_start();
require_once 'inc/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit();
}

$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$booking_id) {
    $_SESSION['error'] = "Invalid booking ID.";
    header('Location: user/my_cart.php');
    exit();
}

try {
    // Get booking details
    $stmt = $pdo->prepare("
        SELECT ub.*, e.title, e.price, e.available_tickets 
        FROM user_bookings ub
        JOIN events e ON ub.event_id = e.id
        WHERE ub.id = ? AND ub.user_id = ? AND ub.status = 'cart'
    ");
    $stmt->execute([$booking_id, $_SESSION['user_id']]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        $_SESSION['error'] = "Booking not found or already confirmed.";
        header('Location: user/my_cart.php');
        exit();
    }
    
    // Check availability
    if ($booking['available_tickets'] < $booking['tickets_requested']) {
        $_SESSION['error'] = "Sorry, only {$booking['available_tickets']} tickets are available for this event.";
        header('Location: user/my_cart.php');
        exit();
    }
    
    // Generate booking reference and calculate total
    $booking_reference = 'BK' . strtoupper(uniqid());
    $total_amount = $booking['tickets_requested'] * $booking['price'];
    
    // Update booking status to 'booked' - simple approach without transactions
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
        // Also reduce available tickets in events table
        try {
            $stmt = $pdo->prepare("
                UPDATE events 
                SET available_tickets = available_tickets - ? 
                WHERE id = ? AND available_tickets >= ?
            ");
            $stmt->execute([$booking['tickets_requested'], $booking['event_id'], $booking['tickets_requested']]);
        } catch (Exception $e) {
            // Log error but don't fail the booking confirmation
            error_log("Failed to update event tickets: " . $e->getMessage());
        }
        
        $_SESSION['success'] = "Booking confirmed successfully! Reference: {$booking_reference}. Please complete payment.";
        
        // Redirect to payment
        header("Location: payment/checkout.php?booking_id={$booking_id}");
        exit();
        
    } else {
        $_SESSION['error'] = "Failed to confirm booking. Please try again.";
    }
    
} catch (Exception $e) {
    error_log("Confirm booking error: " . $e->getMessage());
    $_SESSION['error'] = "System error occurred. Please try again later.";
}

header('Location: user/my_cart.php');
exit();
?>