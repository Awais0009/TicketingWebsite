<?php
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/security.php';

// Enable error logging for debugging
error_reporting(E_ALL);
ini_set('log_errors', 1);

if (!isLoggedIn()) {
    header('Location: auth/Login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    error_log("CSRF token verification failed");
    header('Location: index.php?error=csrf');
    exit;
}

$event_id = (int)($_POST['event_id'] ?? 0);
$tickets = (int)($_POST['tickets'] ?? 0);
$user_id = $_SESSION['user_id'];
$is_edit = isset($_POST['is_edit']) && $_POST['is_edit'] === '1'; // Check if this is an edit from cart

// Validate input
if (!$event_id || !$tickets || $tickets <= 0 || $tickets > 10) {
    error_log("Invalid input: event_id=$event_id, tickets=$tickets, user_id=$user_id");
    header('Location: index.php?error=invalid_data');
    exit;
}

try {
    // Get event details first
    $stmt = $pdo->prepare("SELECT id, title, price, available_tickets, total_tickets FROM events WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        error_log("Event not found: $event_id");
        header('Location: index.php?error=event_not_found');
        exit;
    }
    
    // Check if user already has this event in cart
    $stmt = $pdo->prepare("SELECT id, tickets_requested, total_amount, status FROM user_bookings WHERE user_id = ? AND event_id = ?");
    $stmt->execute([$user_id, $event_id]);
    $existing_booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_booking) {
        // User already has this event - check status
        if ($existing_booking['status'] !== 'cart') {
            error_log("User $user_id already has non-cart booking for event $event_id: status={$existing_booking['status']}");
            header("Location: event.php?id=$event_id&error=" . urlencode("You already have a {$existing_booking['status']} booking for this event"));
            exit;
        }
        
        // For EDIT: replace quantity, for ADD: add to existing
        if ($is_edit) {
            $new_quantity = $tickets; // REPLACE with new quantity
            $action_message = "updated to $tickets tickets";
        } else {
            $new_quantity = $existing_booking['tickets_requested'] + $tickets; // ADD to existing
            $action_message = "added $tickets more tickets (Total: $new_quantity)";
        }
        
        // Check constraints: 1-10 tickets and availability
        if ($new_quantity > 10) {
            $current_msg = $is_edit ? "trying to set $tickets" : "current: {$existing_booking['tickets_requested']}, adding: $tickets";
            error_log("Total tickets would exceed maximum: $current_msg, max=10");
            header("Location: event.php?id=$event_id&error=" . urlencode("Maximum 10 tickets per person. ($current_msg)"));
            exit;
        }
        
        if ($new_quantity > $event['available_tickets']) {
            error_log("Total quantity exceeds available: total=$new_quantity, available={$event['available_tickets']}");
            header("Location: event.php?id=$event_id&error=" . urlencode("Only {$event['available_tickets']} tickets available."));
            exit;
        }
        
        $new_total = $event['price'] * $new_quantity;
        
        // Update existing cart item
        $stmt = $pdo->prepare("UPDATE user_bookings SET tickets_requested = ?, total_amount = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $result = $stmt->execute([$new_quantity, $new_total, $existing_booking['id']]);
        
        if (!$result) {
            throw new Exception("Failed to update cart item");
        }
        
        $message = "Cart $action_message";
        error_log("Cart updated for user $user_id, event $event_id: $message");
        
    } else {
        // Check ticket availability for new booking
        if ($event['available_tickets'] < $tickets) {
            error_log("Not enough tickets: requested=$tickets, available={$event['available_tickets']}");
            header("Location: event.php?id=$event_id&error=" . urlencode("Only {$event['available_tickets']} tickets available"));
            exit;
        }
        
        // Create new cart item
        $booking_reference = 'CART_' . $user_id . '_' . $event_id . '_' . time();
        $total_amount = $event['price'] * $tickets;
        
        $stmt = $pdo->prepare("
            INSERT INTO user_bookings (user_id, event_id, tickets_requested, status, booking_reference, total_amount, created_at, updated_at) 
            VALUES (?, ?, ?, 'cart', ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
        ");
        $result = $stmt->execute([$user_id, $event_id, $tickets, $booking_reference, $total_amount]);
        
        if (!$result) {
            throw new Exception("Failed to create cart item");
        }
        
        $message = "Added $tickets ticket(s) to your cart!";
        error_log("New cart item created for user $user_id, event $event_id: $message");
    }
    
    // Success - redirect to cart
    header('Location: user/my_cart.php?success=' . urlencode($message));
    exit;
    
} catch (PDOException $e) {
    error_log("Database error in book_ticket.php: " . $e->getMessage());
    error_log("Error Code: " . $e->getCode());
    
    // Handle specific PostgreSQL error codes
    if ($e->getCode() == '23505') {
        if (strpos($e->getMessage(), 'user_bookings_booking_reference_key') !== false) {
            header("Location: event.php?id=$event_id&error=" . urlencode("Please try again (reference collision)"));
        } else {
            header("Location: event.php?id=$event_id&error=" . urlencode("You already have this event in your cart"));
        }
    } elseif ($e->getCode() == '23503') {
        header("Location: event.php?id=$event_id&error=" . urlencode("Invalid user or event"));
    } elseif ($e->getCode() == '23514') {
        if (strpos($e->getMessage(), 'user_bookings_tickets_requested_check') !== false) {
            header("Location: event.php?id=$event_id&error=" . urlencode("Invalid ticket quantity (must be 1-10)"));
        } else {
            header("Location: event.php?id=$event_id&error=" . urlencode("Data validation error"));
        }
    } else {
        error_log("Full PDO Error Info: " . print_r($e->errorInfo, true));
        header("Location: event.php?id=$event_id&error=" . urlencode("Database error occurred"));
    }
    exit;
    
} catch (Exception $e) {
    error_log("General error in book_ticket.php: " . $e->getMessage());
    header("Location: event.php?id=$event_id&error=" . urlencode($e->getMessage()));
    exit;
}
?>