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
    header('Location: index.php');
    exit();
}

$event_id = (int)($_POST['event_id'] ?? 0);
$tickets = (int)($_POST['tickets'] ?? 0);
$csrf_token = $_POST['csrf_token'] ?? '';
$user_id = $_SESSION['user_id'];

$errors = [];
$success = '';

// Validate CSRF token
if (!validateCSRFToken($csrf_token)) {
    $errors[] = "Invalid security token.";
}

// Validate input
if (!$event_id) {
    $errors[] = "Invalid event.";
}

if ($tickets < 1 || $tickets > 10) {
    $errors[] = "You can book 1-10 tickets maximum.";
}

// Get event details and check availability
if (empty($errors)) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
        $stmt->execute([$event_id]);
        $event = $stmt->fetch();
        
        if (!$event) {
            $errors[] = "Event not found.";
        } elseif ($event['available_tickets'] < $tickets) {
            $errors[] = "Only {$event['available_tickets']} tickets available.";
        } elseif (strtotime($event['event_date']) <= time()) {
            $errors[] = "This event has already started.";
        }
    } catch (PDOException $e) {
        $errors[] = "Database error occurred.";
        error_log("Database error in booking: " . $e->getMessage());
    }
}

// Check if user already has this event in cart or booked
$existing_booking = null;
if (empty($errors)) {
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM user_bookings 
            WHERE user_id = ? AND event_id = ? AND status IN ('cart', 'booked')
        ");
        $stmt->execute([$user_id, $event_id]);
        $existing_booking = $stmt->fetch();
        
    } catch (PDOException $e) {
        $errors[] = "Database error occurred.";
        error_log("Database error checking existing booking: " . $e->getMessage());
    }
}

// Process the booking
if (empty($errors)) {
    try {
        if ($existing_booking) {
            if ($existing_booking['status'] === 'booked') {
                $errors[] = "You have already booked tickets for this event. Complete payment to book again.";
            } else {
                // Update existing cart entry
                $stmt = $pdo->prepare("
                    UPDATE user_bookings 
                    SET tickets_requested = ?, updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $stmt->execute([$tickets, $existing_booking['id']]);
                $success = "Updated your booking: {$tickets} tickets in cart.";
            }
        } else {
            // Add new cart entry
            $stmt = $pdo->prepare("
                INSERT INTO user_bookings (user_id, event_id, tickets_requested, status) 
                VALUES (?, ?, ?, 'cart')
            ");
            $stmt->execute([$user_id, $event_id, $tickets]);
            $success = "Successfully added {$tickets} tickets to your cart!";
        }
        
    } catch (PDOException $e) {
        $errors[] = "Failed to add tickets to cart.";
        error_log("Database error adding to cart: " . $e->getMessage());
    }
}

// Store messages in session and redirect back
if (!empty($errors)) {
    $_SESSION['booking_errors'] = $errors;
} elseif ($success) {
    $_SESSION['booking_success'] = $success;
}

header("Location: event.php?id=" . $event_id);
exit();
?>