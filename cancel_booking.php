
<?php
require_once __DIR__ . '/inc/db_secure.php';
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

$booking_id = (int)($_POST['booking_id'] ?? 0);
$csrf_token = $_POST['csrf_token'] ?? '';

$errors = [];

// Validate CSRF token
if (!verifyCSRFToken($csrf_token)) {
    $errors[] = "Invalid security token.";
}

// Validate booking ID
if (!$booking_id) {
    $errors[] = "Invalid booking.";
}

if (empty($errors)) {
    try {
        // Get booking details
        $stmt = $pdo->prepare("
            SELECT ub.*, e.id as event_id
            FROM user_bookings ub
            JOIN events e ON ub.event_id = e.id
            WHERE ub.id = ? AND ub.user_id = ? AND ub.status IN ('cart', 'booked')
        ");
        $stmt->execute([$booking_id, $_SESSION['user_id']]);
        $booking = $stmt->fetch();
        
        if (!$booking) {
            $errors[] = "Booking not found.";
        } else {
            $pdo->beginTransaction();
            
            // If booking was confirmed (status = 'booked'), restore tickets
            if ($booking['status'] === 'booked') {
                $stmt = $pdo->prepare("
                    UPDATE events 
                    SET available_tickets = available_tickets + ? 
                    WHERE id = ?
                ");
                $stmt->execute([$booking['tickets_requested'], $booking['event_id']]);
            }
            
            // Delete the booking
            $stmt = $pdo->prepare("DELETE FROM user_bookings WHERE id = ?");
            $stmt->execute([$booking_id]);
            
            $pdo->commit();
            
            $_SESSION['booking_success'] = "Booking cancelled successfully.";
            $redirect_url = "event.php?id=" . $booking['event_id'];
        }
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Database error in cancel booking: " . $e->getMessage());
        $errors[] = "Failed to cancel booking. Please try again.";
    }
}

// Handle redirect
if (!empty($errors)) {
    $_SESSION['booking_errors'] = $errors;
    $redirect_url = isset($booking) ? "event.php?id=" . $booking['event_id'] : "user/my_cart.php";
}

header("Location: " . ($redirect_url ?? "user/my_cart.php"));
exit();
?>