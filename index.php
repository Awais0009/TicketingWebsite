<?php
// Minimal test version for Railway debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Railway compatibility
$port = $_ENV['PORT'] ?? getenv('PORT') ?? '8080';
$host = '0.0.0.0';

// Simple test endpoint
if ($_SERVER['REQUEST_URI'] === '/test') {
    http_response_code(200);
    header('Content-Type: text/html');
    echo "<h1>🎯 Railway Test - SUCCESS!</h1>";
    echo "<p>PHP is working perfectly!</p>";
    echo "<p>PHP Version: " . phpversion() . "</p>";
    echo "<p>Current Time: " . date('Y-m-d H:i:s') . "</p>";
    echo "<p>Server: $host:$port</p>";
    exit;
}

// Health check
if ($_SERVER['REQUEST_URI'] === '/health') {
    http_response_code(200);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'time' => date('c')]);
    exit;
}

// Debug endpoint
if (isset($_GET['debug']) && $_GET['debug'] === 'railway') {
    http_response_code(200);
    header('Content-Type: text/html');
    
    echo "<h1>🚀 Railway Debug Info</h1>";
    echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
    echo "<p><strong>Server:</strong> $host:$port</p>";
    
    // Check file existence
    $files = ['inc/db_secure.php', 'inc/header.php', 'inc/footer.php'];
    echo "<h3>File Check:</h3>";
    foreach ($files as $file) {
        $exists = file_exists($file) ? '✅ EXISTS' : '❌ MISSING';
        echo "<p><strong>$file:</strong> $exists</p>";
    }
    
    // Check environment variables
    $env_vars = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'];
    echo "<h3>Environment Variables:</h3>";
    foreach ($env_vars as $var) {
        $value = $_ENV[$var] ?? getenv($var) ?? 'NOT SET';
        $display = ($var === 'DB_PASSWORD' && $value !== 'NOT SET') ? '***SET***' : $value;
        echo "<p><strong>$var:</strong> $display</p>";
    }
    
    exit;
}

// Simple main page (no includes)
try {
    http_response_code(200);
    header('Content-Type: text/html');
    
    echo "<!DOCTYPE html><html><head><title>Railway Test</title></head><body>";
    echo "<h1>🎫 Ticketing Website</h1>";
    echo "<p>Successfully deployed on Railway!</p>";
    echo "<p><a href='/test'>Simple Test</a></p>";
    echo "<p><a href='/?debug=railway'>Debug Info</a></p>";
    echo "<p><a href='/health'>Health Check</a></p>";
    echo "</body></html>";
    
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    echo "Error: " . $e->getMessage();
}
?>