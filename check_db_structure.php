<?php

require_once __DIR__ . '/inc/db.php';

echo "<h2>Database Structure Check</h2>";

try {
    // Check user_bookings table structure
    echo "<h3>user_bookings table structure:</h3>";
    $stmt = $pdo->query("SELECT column_name, data_type, is_nullable, column_default 
                        FROM information_schema.columns 
                        WHERE table_name = 'user_bookings' 
                        ORDER BY ordinal_position");
    $columns = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Nullable</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['column_name']}</td>";
        echo "<td>{$col['data_type']}</td>";
        echo "<td>{$col['is_nullable']}</td>";
        echo "<td>{$col['column_default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check events table structure
    echo "<h3>events table structure:</h3>";
    $stmt = $pdo->query("SELECT column_name, data_type, is_nullable, column_default 
                        FROM information_schema.columns 
                        WHERE table_name = 'events' 
                        ORDER BY ordinal_position");
    $columns = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Column</th><th>Type</th><th>Nullable</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['column_name']}</td>";
        echo "<td>{$col['data_type']}</td>";
        echo "<td>{$col['is_nullable']}</td>";
        echo "<td>{$col['column_default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check constraints and indexes
    echo "<h3>user_bookings constraints:</h3>";
    $stmt = $pdo->query("SELECT constraint_name, constraint_type 
                        FROM information_schema.table_constraints 
                        WHERE table_name = 'user_bookings'");
    $constraints = $stmt->fetchAll();
    
    foreach ($constraints as $constraint) {
        echo "<p>{$constraint['constraint_name']}: {$constraint['constraint_type']}</p>";
    }
    
    // Sample data
    echo "<h3>Sample user_bookings data:</h3>";
    $stmt = $pdo->query("SELECT ub.*, u.name as user_name, e.title as event_title 
                        FROM user_bookings ub 
                        LEFT JOIN users u ON ub.user_id = u.id 
                        LEFT JOIN events e ON ub.event_id = e.id 
                        ORDER BY ub.created_at DESC LIMIT 5");
    $bookings = $stmt->fetchAll();
    
    if (empty($bookings)) {
        echo "<p>No bookings found</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>ID</th><th>User</th><th>Event</th><th>Tickets</th><th>Status</th></tr>";
        foreach ($bookings as $booking) {
            echo "<tr>";
            echo "<td>{$booking['id']}</td>";
            echo "<td>{$booking['user_name']}</td>";
            echo "<td>{$booking['event_title']}</td>";
            echo "<td>{$booking['tickets_requested']}</td>";
            echo "<td>{$booking['status']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color:red'>Database error: " . $e->getMessage() . "</p>";
}
?>