<?php
require_once __DIR__ . '/../inc/db.php';
require_once __DIR__ . '/../inc/security.php';

// Require login
if (!isLoggedIn()) {
    header('Location: ../auth/Login.php');
    exit;
}

// Verify CSRF token from URL parameter
if (!verifyCSRFToken($_GET['csrf_token'] ?? '')) {
    header('Location: my_cart.php?error=' . urlencode('Security error. Please try again.'));
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Count items before deletion for confirmation message
    $stmt = $pdo->prepare("SELECT COUNT(*) as item_count, SUM(tickets_requested) as total_tickets FROM user_bookings WHERE user_id = ? AND status = 'cart'");
    $stmt->execute([$user_id]);
    $cart_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($cart_stats['item_count'] == 0) {
        header('Location: my_cart.php?error=' . urlencode('Your cart is already empty'));
        exit;
    }
    
    // Delete all cart items for this user
    $stmt = $pdo->prepare("DELETE FROM user_bookings WHERE user_id = ? AND status = 'cart'");
    $result = $stmt->execute([$user_id]);
    
    if ($result) {
        $deleted_count = $stmt->rowCount();
        
        if ($deleted_count > 0) {
            $message = "Cleared all items from cart ({$deleted_count} events, {$cart_stats['total_tickets']} tickets)";
            error_log("Cart cleared: user_id=$user_id, deleted_items=$deleted_count");
            header('Location: my_cart.php?success=' . urlencode($message));
        } else {
            header('Location: my_cart.php?error=' . urlencode('No items were removed'));
        }
    } else {
        error_log("Failed to clear cart: user_id=$user_id");
        header('Location: my_cart.php?error=' . urlencode('Failed to clear cart'));
    }
    
} catch (PDOException $e) {
    error_log("Database error clearing cart: " . $e->getMessage());
    header('Location: my_cart.php?error=' . urlencode('Database error occurred'));
    
} catch (Exception $e) {
    error_log("General error clearing cart: " . $e->getMessage());
    header('Location: my_cart.php?error=' . urlencode('An error occurred while clearing the cart'));
}

exit;
?>