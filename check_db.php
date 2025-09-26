<?php

require_once 'inc/db.php';

echo "<h2>Database Tables and Columns</h2>";

// Check if users table exists and its structure
try {
    $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'users' ORDER BY ordinal_position");
    echo "<h3>Users Table Columns:</h3>";
    while ($row = $stmt->fetch()) {
        echo "- " . $row['column_name'] . " (" . $row['data_type'] . ")<br>";
    }
} catch (Exception $e) {
    echo "Users table error: " . $e->getMessage() . "<br>";
}

// Check events table
try {
    $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'events' ORDER BY ordinal_position");
    echo "<h3>Events Table Columns:</h3>";
    while ($row = $stmt->fetch()) {
        echo "- " . $row['column_name'] . " (" . $row['data_type'] . ")<br>";
    }
} catch (Exception $e) {
    echo "Events table error: " . $e->getMessage() . "<br>";
}

// List all tables
try {
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
    echo "<h3>All Tables:</h3>";
    while ($row = $stmt->fetch()) {
        echo "- " . $row['table_name'] . "<br>";
    }
} catch (Exception $e) {
    echo "Tables list error: " . $e->getMessage() . "<br>";
}
?>