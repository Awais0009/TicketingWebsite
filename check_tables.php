<?php
require_once 'inc/db.php';

// Check if event_images table exists
try {
    $query = $pdo->query('SELECT * FROM event_images LIMIT 1');
    echo "event_images table exists\n";
} catch(Exception $e) {
    echo "event_images table missing: " . $e->getMessage() . "\n";
}

// Clear any cached query plans that might be causing issues
try {
    $pdo->query('DEALLOCATE ALL');
    echo "Cleared all cached query plans\n";
} catch(Exception $e) {
    echo "Error clearing plans: " . $e->getMessage() . "\n";
}

// Check events table
try {
    $query = $pdo->query('SELECT id, title, images FROM events LIMIT 3');
    $events = $query->fetchAll(PDO::FETCH_ASSOC);
    echo "Events found: " . count($events) . "\n";
    foreach($events as $event) {
        echo "Event {$event['id']}: {$event['title']}\n";
    }
} catch(Exception $e) {
    echo "Events error: " . $e->getMessage() . "\n";
}
?>