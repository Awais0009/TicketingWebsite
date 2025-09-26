<?php
require_once 'inc/db.php';
require_once 'inc/functions.php';

// Debug payment processing
$user_id = 4; // The user from your log

echo "<h2>Payment Processing Debug for User $user_id</h2>";

try {
    // Check cart items
    $stmt = $pdo->prepare("
        SELECT 
            ub.id,
            ub.event_id,
            ub.tickets_requested,
            ub.total_amount,
            ub.status,
            ub.booking_reference,
            e.title,
            e.available_tickets,
            e.price
        FROM user_bookings ub 
        JOIN events e ON ub.event_id = e.id 
        WHERE ub.user_id = ?
        ORDER BY ub.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>All Bookings for User $user_id:</h3>";
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Event</th><th>Tickets</th><th>Status</th><th>Reference</th><th>Available</th></tr>";
    foreach($bookings as $booking) {
        echo "<tr>";
        echo "<td>{$booking['id']}</td>";
        echo "<td>{$booking['title']}</td>";
        echo "<td>{$booking['tickets_requested']}</td>";
        echo "<td>{$booking['status']}</td>";
        echo "<td>{$booking['booking_reference']}</td>";
        echo "<td>{$booking['available_tickets']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check for cart items specifically
    $cart_items = array_filter($bookings, function($b) { return $b['status'] === 'cart'; });
    echo "<h3>Cart Items Count: " . count($cart_items) . "</h3>";
    
    if (!empty($cart_items)) {
        echo "<h3>Test Transaction Operations:</h3>";
        
        foreach($cart_items as $item) {
            echo "<h4>Testing item: {$item['title']}</h4>";
            
            // Test the UPDATE query
            try {
                $test_payment_id = 'TEST_' . time();
                echo "1. Testing booking update...<br>";
                
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("
                    UPDATE user_bookings 
                    SET booking_reference = ?, 
                        updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ? AND status = 'cart'
                ");
                $result = $stmt->execute([$test_payment_id, $item['id']]);
                echo "   Result: " . ($result ? 'SUCCESS' : 'FAILED') . "<br>";
                echo "   Rows affected: " . $stmt->rowCount() . "<br>";
                
                // Roll back the test
                $pdo->rollback();
                echo "   Test rolled back<br>";
                
                echo "2. Testing event update...<br>";
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("
                    UPDATE events 
                    SET updated_at = CURRENT_TIMESTAMP 
                    WHERE id = ?
                ");
                $result = $stmt->execute([$item['event_id']]);
                echo "   Result: " . ($result ? 'SUCCESS' : 'FAILED') . "<br>";
                echo "   Rows affected: " . $stmt->rowCount() . "<br>";
                
                $pdo->rollback();
                echo "   Test rolled back<br>";
                
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollback();
                }
                echo "   ERROR: " . $e->getMessage() . "<br>";
            }
        }
    }
    
    // Check table constraints
    echo "<h3>Table Constraints Check:</h3>";
    
    $stmt = $pdo->query("
        SELECT conname, contype, pg_get_constraintdef(oid) as definition
        FROM pg_constraint 
        WHERE conrelid = 'user_bookings'::regclass
    ");
    $constraints = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>Constraint Name</th><th>Type</th><th>Definition</th></tr>";
    foreach($constraints as $constraint) {
        echo "<tr>";
        echo "<td>{$constraint['conname']}</td>";
        echo "<td>{$constraint['contype']}</td>";
        echo "<td>{$constraint['definition']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
}

echo "<br><a href='index.php'>Back to Home</a>";
?>