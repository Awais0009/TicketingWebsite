<?php

require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/security.php';

// Only allow this in development
if (!isLoggedIn() || !hasRole('admin')) {
    header('Location: index.php');
    exit();
}

$pageTitle = 'Database Reset Utility';
include __DIR__ . '/inc/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        switch ($action) {
            case 'clear_cache':
                $pdo->exec("DEALLOCATE ALL");
                $message = "✅ Database query cache cleared successfully!";
                break;
                
            case 'check_tables':
                echo "<h3>Table Structure Check:</h3>";
                
                // Check events table
                $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'events' ORDER BY ordinal_position");
                $events_columns = $stmt->fetchAll();
                
                echo "<h4>Events Table:</h4><ul>";
                foreach ($events_columns as $col) {
                    echo "<li>{$col['column_name']} ({$col['data_type']})</li>";
                }
                echo "</ul>";
                
                // Check user_bookings table
                $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'user_bookings' ORDER BY ordinal_position");
                $bookings_columns = $stmt->fetchAll();
                
                echo "<h4>User Bookings Table:</h4><ul>";
                foreach ($bookings_columns as $col) {
                    echo "<li>{$col['column_name']} ({$col['data_type']})</li>";
                }
                echo "</ul>";
                
                // Check if event_images exists
                $stmt = $pdo->query("SELECT EXISTS (SELECT FROM information_schema.tables WHERE table_name = 'event_images')");
                $images_exists = $stmt->fetch()['exists'];
                
                echo "<h4>Event Images Table:</h4>";
                if ($images_exists) {
                    $stmt = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'event_images' ORDER BY ordinal_position");
                    $images_columns = $stmt->fetchAll();
                    echo "<ul>";
                    foreach ($images_columns as $col) {
                        echo "<li>{$col['column_name']} ({$col['data_type']})</li>";
                    }
                    echo "</ul>";
                } else {
                    echo "<p>❌ Event images table does not exist</p>";
                }
                
                $message = "✅ Table structure check completed!";
                break;
                
            case 'create_event_images':
                $pdo->exec("
                    CREATE TABLE IF NOT EXISTS event_images (
                        id SERIAL PRIMARY KEY,
                        event_id INTEGER NOT NULL REFERENCES events(id) ON DELETE CASCADE,
                        image_url VARCHAR(500) NOT NULL,
                        display_order INTEGER DEFAULT 1,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ");
                $pdo->exec("CREATE INDEX IF NOT EXISTS idx_event_images_event ON event_images(event_id, display_order)");
                $message = "✅ Event images table created successfully!";
                break;
                
            case 'test_queries':
                echo "<h3>Test Query Results:</h3>";
                
                // Test events query
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM events");
                $events_count = $stmt->fetch()['count'];
                echo "<p>Events count: {$events_count}</p>";
                
                // Test user_bookings query
                $stmt = $pdo->query("SELECT COUNT(*) as count FROM user_bookings");
                $bookings_count = $stmt->fetch()['count'];
                echo "<p>User bookings count: {$bookings_count}</p>";
                
                // Test specific event query
                $stmt = $pdo->prepare("SELECT id, title, available_tickets FROM events WHERE id = ?");
                $stmt->execute([1]);
                $event = $stmt->fetch();
                
                if ($event) {
                    echo "<p>Event 1: {$event['title']} ({$event['available_tickets']} tickets available)</p>";
                } else {
                    echo "<p>❌ Event 1 not found</p>";
                }
                
                $message = "✅ Test queries completed!";
                break;
        }
        
        if (isset($message)) {
            echo "<div class='alert alert-success'>{$message}</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>❌ Error: " . sanitizeOutput($e->getMessage()) . "</div>";
    }
}
?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h4><i class="bi bi-tools me-2"></i>Database Reset Utility</h4>
            </div>
            <div class="card-body">
                <p class="text-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Warning:</strong> This utility is for development use only!
                </p>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h6>Clear Query Cache</h6>
                                <p class="text-muted small">Clears PostgreSQL prepared statement cache</p>
                                <form method="POST">
                                    <input type="hidden" name="action" value="clear_cache">
                                    <button type="submit" class="btn btn-warning btn-sm">
                                        <i class="bi bi-arrow-clockwise me-1"></i>Clear Cache
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h6>Check Tables</h6>
                                <p class="text-muted small">Verify database table structure</p>
                                <form method="POST">
                                    <input type="hidden" name="action" value="check_tables">
                                    <button type="submit" class="btn btn-info btn-sm">
                                        <i class="bi bi-search me-1"></i>Check Tables
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h6>Create Event Images Table</h6>
                                <p class="text-muted small">Create missing event_images table</p>
                                <form method="POST">
                                    <input type="hidden" name="action" value="create_event_images">
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="bi bi-plus-circle me-1"></i>Create Table
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="card">
                            <div class="card-body">
                                <h6>Test Queries</h6>
                                <p class="text-muted small">Run test queries to verify functionality</p>
                                <form method="POST">
                                    <input type="hidden" name="action" value="test_queries">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="bi bi-play me-1"></i>Test Queries
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-house me-2"></i>Back to Events
                    </a>
                    <a href="event.php?id=1" class="btn btn-primary ms-2">
                        <i class="bi bi-calendar-event me-2"></i>Test Event Page
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/inc/footer.php'; ?>