<?php

require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/security.php';

// Debug: Log all POST data
error_log("Payment process POST data: " . print_r($_POST, true));

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: ../auth/login.php');
    exit();
}

// Handle POST request only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['payment_error'] = "Invalid request method.";
    header('Location: ../user/my_cart.php');
    exit();
}

$payment_method = $_POST['payment_method'] ?? '';
$booking_ids = explode(',', $_POST['booking_ids'] ?? '');
$total_amount = floatval($_POST['total_amount'] ?? 0);
$csrf_token = $_POST['csrf_token'] ?? '';
$stripe_token = $_POST['stripe_token'] ?? '';

$errors = [];

// Validate CSRF token
if (!validateCSRFToken($csrf_token)) {
    $errors[] = "Invalid security token.";
}

// Validate payment method
if (!in_array($payment_method, ['stripe', 'demo', 'test', 'js_test'])) {
    $errors[] = "Invalid payment method: " . $payment_method;
}

// Validate booking IDs
$booking_ids = array_filter(array_map('intval', $booking_ids));
if (empty($booking_ids)) {
    $errors[] = "No bookings to process.";
}

// For test methods, just show success
if (in_array($payment_method, ['test', 'js_test'])) {
    $_SESSION['payment_success'] = [
        'method' => $payment_method,
        'booking_references' => ['TEST_' . time()],
        'total_amount' => $total_amount,
        'test_mode' => true
    ];
    header("Location: payment_success.php");
    exit();
}

// Process actual payment (simplified version)
if (empty($errors)) {
    try {
        // Get booking details first
        $placeholders = str_repeat('?,', count($booking_ids) - 1) . '?';
        $stmt = $pdo->prepare("
            SELECT ub.*, e.title, e.price 
            FROM user_bookings ub
            JOIN events e ON ub.event_id = e.id
            WHERE ub.id IN ($placeholders) AND ub.user_id = ? AND ub.status = 'booked'
        ");
        $stmt->execute(array_merge($booking_ids, [$_SESSION['user_id']]));
        $bookings = $stmt->fetchAll();
        
        if (count($bookings) !== count($booking_ids)) {
            throw new Exception("Some bookings not found or invalid status.");
        }
        
        // Generate booking references
        $booking_references = [];
        foreach ($bookings as $booking) {
            $booking_references[] = 'BK' . strtoupper(uniqid());
        }
        
        // Simple approach: Just update user_bookings status to 'paid'
        // No separate bookings table needed for now
        $stmt = $pdo->prepare("
            UPDATE user_bookings 
            SET status = 'paid', updated_at = CURRENT_TIMESTAMP 
            WHERE id IN ($placeholders) AND status = 'booked'
        ");
        $result = $stmt->execute($booking_ids);
        
        if (!$result || $stmt->rowCount() === 0) {
            throw new Exception("Failed to update booking status - bookings may have been modified");
        }
        
        // Store success data in session
        $_SESSION['payment_success'] = [
            'method' => $payment_method,
            'booking_references' => $booking_references,
            'total_amount' => $total_amount
        ];
        
        // Redirect to success page
        header("Location: payment_success.php");
        exit();
        
    } catch (Exception $e) {
        error_log("Payment processing error: " . $e->getMessage());
        
        $_SESSION['payment_error'] = "Payment processing failed: " . $e->getMessage();
        header('Location: checkout.php');
        exit();
    }
}

// If errors, redirect back to checkout
$_SESSION['payment_errors'] = $errors;
error_log("Payment errors: " . print_r($errors, true));
header('Location: checkout.php');
exit();
?>