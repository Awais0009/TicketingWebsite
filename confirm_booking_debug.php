<?php
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/security.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: auth/login.php');
    exit();
}

$booking_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

echo "<h2>Debug Confirm Booking Process</h2>";
echo "<p><strong>Booking ID:</strong> $booking_id</p>";
echo "<p><strong>User ID:</strong> " . $_SESSION['user_id'] . "</p>";

if (!$booking_id) {
    echo "<p style='color:red'>❌ Invalid booking ID</p>";
    exit();
}

try {
    echo "<h3>Step 1: Check if user_bookings table exists</h3>";
    $stmt = $pdo->query("SELECT COUNT(*) FROM user_bookings");
    echo "<p style='color:green'>✅ user_bookings table exists</p>";
    
    echo "<h3>Step 2: Get booking details</h3>";
    $stmt = $pdo->prepare("
        SELECT ub.*, e.title, e.price, e.available_tickets, e.event_date, e.id as event_id
        FROM user_bookings ub
        JOIN events e ON ub.event_id = e.id
        WHERE ub.id = ? AND ub.user_id = ?
    ");
    $stmt->execute([$booking_id, $_SESSION['user_id']]);
    $booking = $stmt->fetch();
    
    if (!$booking) {
        echo "<p style='color:red'>❌ Booking not found</p>";
        echo "<p>Checking all bookings for this user:</p>";
        $stmt = $pdo->prepare("SELECT * FROM user_bookings WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $all_bookings = $stmt->fetchAll();
        echo "<pre>" . print_r($all_bookings, true) . "</pre>";
        exit();
    }
    
    echo "<p style='color:green'>✅ Booking found</p>";
    echo "<pre>" . print_r($booking, true) . "</pre>";
    
    echo "<h3>Step 3: Check booking status</h3>";
    if ($booking['status'] !== 'cart') {
        echo "<p style='color:red'>❌ Booking status is '{$booking['status']}', not 'cart'</p>";
        exit();
    }
    echo "<p style='color:green'>✅ Booking status is 'cart'</p>";
    
    echo "<h3>Step 4: Check ticket availability</h3>";
    if ($booking['available_tickets'] < $booking['tickets_requested']) {
        echo "<p style='color:red'>❌ Not enough tickets available: {$booking['available_tickets']} < {$booking['tickets_requested']}</p>";
        exit();
    }
    echo "<p style='color:green'>✅ Enough tickets available: {$booking['available_tickets']} >= {$booking['tickets_requested']}</p>";
    
    echo "<h3>Step 5: Check event date</h3>";
    if (strtotime($booking['event_date']) <= time()) {
        echo "<p style='color:red'>❌ Event has already started</p>";
        exit();
    }
    echo "<p style='color:green'>✅ Event is in the future</p>";
    
    echo "<h3>Step 6: Attempt transaction</h3>";
    
    // Start transaction
    echo "<p>🔄 Starting transaction...</p>";
    $pdo->beginTransaction();
    
    // Update booking status
    echo "<p>🔄 Updating booking status...</p>";
    $update_booking_stmt = $pdo->prepare("
        UPDATE user_bookings 
        SET status = 'booked', updated_at = CURRENT_TIMESTAMP 
        WHERE id = ? AND status = 'cart'
    ");
    $result1 = $update_booking_stmt->execute([$booking_id]);
    $affected_rows1 = $update_booking_stmt->rowCount();
    
    echo "<p>Update booking result: " . ($result1 ? "SUCCESS" : "FAILED") . "</p>";
    echo "<p>Affected rows: $affected_rows1</p>";
    
    if (!$result1 || $affected_rows1 === 0) {
        $pdo->rollBack();
        echo "<p style='color:red'>❌ Failed to update booking status</p>";
        exit();
    }
    
    // Update event tickets
    echo "<p>🔄 Updating event tickets...</p>";
    $update_event_stmt = $pdo->prepare("
        UPDATE events 
        SET available_tickets = available_tickets - ? 
        WHERE id = ? AND available_tickets >= ?
    ");
    $result2 = $update_event_stmt->execute([
        $booking['tickets_requested'], 
        $booking['event_id'],
        $booking['tickets_requested']
    ]);
    $affected_rows2 = $update_event_stmt->rowCount();
    
    echo "<p>Update event result: " . ($result2 ? "SUCCESS" : "FAILED") . "</p>";
    echo "<p>Affected rows: $affected_rows2</p>";
    
    if (!$result2 || $affected_rows2 === 0) {
        $pdo->rollBack();
        echo "<p style='color:red'>❌ Failed to update event tickets</p>";
        exit();
    }
    
    // Commit transaction
    echo "<p>🔄 Committing transaction...</p>";
    $pdo->commit();
    echo "<p style='color:green'>✅ Transaction committed successfully!</p>";
    
    echo "<h3>Step 7: Verify changes</h3>";
    // Check updated booking
    $stmt = $pdo->prepare("SELECT * FROM user_bookings WHERE id = ?");
    $stmt->execute([$booking_id]);
    $updated_booking = $stmt->fetch();
    echo "<p><strong>Updated booking status:</strong> " . $updated_booking['status'] . "</p>";
    
    // Check updated event
    $stmt = $pdo->prepare("SELECT available_tickets FROM events WHERE id = ?");
    $stmt->execute([$booking['event_id']]);
    $updated_event = $stmt->fetch();
    echo "<p><strong>Updated available tickets:</strong> " . $updated_event['available_tickets'] . "</p>";
    
    echo "<p style='color:green'>✅ BOOKING CONFIRMED SUCCESSFULLY!</p>";
    echo "<p><a href='user/my_cart.php'>Go to My Cart</a></p>";
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<p style='color:red'>❌ Database error: " . $e->getMessage() . "</p>";
    echo "<p><strong>Error Code:</strong> " . $e->getCode() . "</p>";
    echo "<p><strong>SQL State:</strong> " . $e->errorInfo[0] . "</p>";
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<p style='color:red'>❌ General error: " . $e->getMessage() . "</p>";
}
?>