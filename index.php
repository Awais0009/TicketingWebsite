<?php
// Railway-compatible setup
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Railway port and host detection
$port = $_ENV['PORT'] ?? getenv('PORT') ?? '8080';
$host = '0.0.0.0';

// Log server info for debugging
error_log("Railway Server Info: Host=$host, Port=$port, SAPI=" . php_sapi_name());

// Debug endpoint for Railway
if (isset($_GET['debug']) && $_GET['debug'] === 'railway') {
    http_response_code(200);
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-cache');
    
    echo "<h1>🚀 Railway Debug Info</h1>";
    echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
    echo "<p><strong>SAPI:</strong> " . php_sapi_name() . "</p>";
    echo "<p><strong>Railway Environment:</strong> " . (getenv('RAILWAY_ENVIRONMENT_NAME') ? 'YES' : 'NO') . "</p>";
    echo "<p><strong>Server Host:</strong> " . $host . "</p>";
    echo "<p><strong>Server Port:</strong> " . $port . "</p>";
    
    // Test environment variables
    $env_vars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'DB_PORT', 'PORT', 'RAILWAY_ENVIRONMENT_NAME'];
    foreach ($env_vars as $var) {
        $value = $_ENV[$var] ?? getenv($var) ?? 'NOT SET';
        $display_value = ($var === 'DB_PASSWORD') ? (($value !== 'NOT SET') ? '***SET***' : 'NOT SET') : $value;
        echo "<p><strong>$var:</strong> $display_value</p>";
    }
    
    // Test file existence
    $files_to_check = ['inc/db_secure.php', 'inc/header.php', 'inc/footer.php'];
    echo "<h3>File Check:</h3>";
    foreach ($files_to_check as $file) {
        $exists = file_exists($file) ? '✅ EXISTS' : '❌ MISSING';
        echo "<p><strong>$file:</strong> $exists</p>";
    }
    
    // Test database connection
    echo "<h3>Database Test:</h3>";
    try {
        if (file_exists('inc/db_secure.php')) {
            require_once __DIR__ . '/inc/db_secure.php';
            echo "<p><strong>✅ Database:</strong> Connected successfully</p>";
            
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
            $result = $stmt->fetch();
            echo "<p><strong>✅ Users:</strong> {$result['count']} users found</p>";
            
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM events");
            $result = $stmt->fetch();  
            echo "<p><strong>✅ Events:</strong> {$result['count']} events found</p>";
        } else {
            echo "<p><strong>❌ Database:</strong> db_secure.php not found</p>";
        }
        
    } catch (Exception $e) {
        echo "<p><strong>❌ Database Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><strong>Error File:</strong> " . $e->getFile() . " Line: " . $e->getLine() . "</p>";
    }
    
    echo "<p><a href='/'>← Back to main site</a></p>";
    exit;
}

// Health check endpoint
if ($_SERVER['REQUEST_URI'] === '/health') {
    http_response_code(200);
    header('Content-Type: application/json');
    header('Cache-Control: no-cache');
    echo json_encode([
        'status' => 'ok', 
        'time' => date('c'),
        'port' => $port,
        'host' => $host,
        'php_version' => phpversion()
    ]);
    exit;
}

// Simple test endpoint 
if ($_SERVER['REQUEST_URI'] === '/test') {
    http_response_code(200);
    header('Content-Type: text/html');
    echo "<h1>🎯 Railway Test Page</h1>";
    echo "<p>If you can see this, Railway is working!</p>";
    echo "<p>PHP Version: " . phpversion() . "</p>";
    echo "<p>Current Time: " . date('Y-m-d H:i:s') . "</p>";
    echo "<p><a href='/?debug=railway'>Debug Info</a></p>";
    exit;
}

// Wrap your existing code in try-catch
try {
    // Your existing events listing code
    require_once 'inc/db_secure.php';
    
    // Get search parameters
    $search = $_GET['search'] ?? '';
    $category = $_GET['category'] ?? '';
    
    // Build query
    $sql = "SELECT * FROM events WHERE status = 'published'";
    $params = [];
    
    if (!empty($search)) {
        $sql .= " AND (title ILIKE ? OR description ILIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($category)) {
        $sql .= " AND category = ?";
        $params[] = $category;
    }
    
    $sql .= " ORDER BY event_date ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $events = $stmt->fetchAll();
    
    // Get categories for filter
    $stmt = $pdo->query("SELECT DISTINCT category FROM events WHERE status = 'published' ORDER BY category");
    $categories = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Tickets - Home</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php 
    // Safe include for header
    try {
        if (file_exists('inc/header.php')) {
            include 'inc/header.php';
        } else {
            // Simple navigation if header missing
            echo '<nav class="navbar navbar-expand-lg navbar-dark bg-primary">';
            echo '<div class="container"><a class="navbar-brand" href="/">Event Tickets</a></div>';
            echo '</nav>';
        }
    } catch (Exception $e) {
        error_log("Header include error: " . $e->getMessage());
        echo '<div class="alert alert-warning">Navigation temporarily unavailable</div>';
    }
    ?>
    
    <!-- Hero Section -->
    <div class="bg-primary text-white py-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 mb-4">Find Amazing Events</h1>
                    <p class="lead mb-4">Discover and book tickets for the best events in your area</p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Search and Filter Section -->
    <div class="container mt-4">
        <form method="GET" class="row g-3 mb-4">
            <div class="col-md-4">
                <input type="text" class="form-control" name="search" 
                       placeholder="Search events..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-3">
                <select class="form-select" name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                <?php echo $category === $cat['category'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['category']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-search"></i> Search
                </button>
            </div>
        </form>
    </div>
    
    <!-- Events Grid -->
    <div class="container mb-5">
        <?php if (empty($events)): ?>
            <div class="alert alert-info text-center">
                <h4>No events found</h4>
                <p>Try adjusting your search criteria or check back later for new events.</p>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($events as $event): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card h-100 shadow-sm">
                            <?php if ($event['image_url']): ?>
                                <img src="<?php echo htmlspecialchars($event['image_url']); ?>" 
                                     class="card-img-top" alt="Event Image" style="height: 200px; object-fit: cover;">
                            <?php endif; ?>
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title"><?php echo htmlspecialchars($event['title']); ?></h5>
                                <p class="card-text text-muted small mb-2">
                                    <i class="bi bi-calendar"></i> 
                                    <?php echo date('M j, Y g:i A', strtotime($event['event_date'])); ?>
                                </p>
                                <p class="card-text text-muted small mb-2">
                                    <i class="bi bi-geo-alt"></i> 
                                    <?php echo htmlspecialchars($event['location']); ?>
                                </p>
                                <p class="card-text flex-grow-1">
                                    <?php echo htmlspecialchars(substr($event['description'], 0, 100)) . '...'; ?>
                                </p>
                                <div class="mt-auto">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="h5 text-primary mb-0">$<?php echo number_format($event['price'], 2); ?></span>
                                        <small class="text-muted">
                                            <?php echo $event['available_tickets']; ?> tickets left
                                        </small>
                                    </div>
                                    <a href="event.php?id=<?php echo $event['id']; ?>" 
                                       class="btn btn-primary w-100">View Details</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <?php 
    // Safe include for footer
    try {
        if (file_exists('inc/footer.php')) {
            include 'inc/footer.php';
        } else {
            echo '<footer class="bg-dark text-white text-center py-3">';
            echo '<div class="container"><p>&copy; 2025 Event Tickets. All rights reserved.</p></div>';
            echo '</footer>';
        }
    } catch (Exception $e) {
        error_log("Footer include error: " . $e->getMessage());
    }
    ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
} catch (Exception $e) {
    // Log the error
    error_log("Index.php Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Show user-friendly error
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    echo "<!DOCTYPE html><html><head><title>Error</title></head><body>";
    echo "<h1>🚨 Website Error</h1>";
    echo "<p>We're experiencing technical difficulties. Please try again later.</p>";
    echo "<p>Error details: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='/?debug=railway'>Debug Info</a> | <a href='/health'>Health Check</a> | <a href='/test'>Simple Test</a></p>";
    echo "</body></html>";
} catch (Throwable $e) {
    // Catch any other errors
    error_log("Critical Error: " . $e->getMessage());
    http_response_code(500);
    echo "Critical error occurred. Please contact support.";
}
?>