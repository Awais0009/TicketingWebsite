<?php
// Railway debugging with extensive logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// Log everything for debugging
$port = $_ENV['PORT'] ?? getenv('PORT') ?? '8080';
$host = '0.0.0.0';

// Log all environment and request info
error_log("=== RAILWAY REQUEST DEBUG ===");
error_log("REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'UNKNOWN'));
error_log("REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'));
error_log("SERVER_PORT: " . ($_SERVER['SERVER_PORT'] ?? 'UNKNOWN'));
error_log("HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'UNKNOWN'));
error_log("ENV_PORT: " . $port);
error_log("PHP_SAPI: " . php_sapi_name());
error_log("Current working directory: " . getcwd());
error_log("Script filename: " . (__FILE__ ?? 'UNKNOWN'));

// Always return 200 OK for any request
http_response_code(200);
header('Content-Type: text/html; charset=UTF-8');

echo "<!DOCTYPE html><html><head><title>Railway Debug</title></head><body>";
echo "<h1>🚀 Railway Debug Response</h1>";
echo "<p><strong>Status:</strong> PHP is responding!</p>";
echo "<p><strong>Time:</strong> " . date('Y-m-d H:i:s T') . "</p>";
echo "<p><strong>PHP Version:</strong> " . phpversion() . "</p>";
echo "<p><strong>SAPI:</strong> " . php_sapi_name() . "</p>";
echo "<p><strong>Request URI:</strong> " . htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'UNKNOWN') . "</p>";
echo "<p><strong>Request Method:</strong> " . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN') . "</p>";
echo "<p><strong>Server Port:</strong> " . ($_SERVER['SERVER_PORT'] ?? 'UNKNOWN') . "</p>";
echo "<p><strong>Environment PORT:</strong> " . $port . "</p>";
echo "<p><strong>Working Directory:</strong> " . getcwd() . "</p>";

// Show all environment variables
echo "<h3>Environment Variables:</h3><pre>";
$env_vars = ['PORT', 'RAILWAY_ENVIRONMENT_NAME', 'RAILWAY_PROJECT_ID', 'DB_HOST', 'DB_NAME', 'DB_USER'];
foreach ($env_vars as $var) {
    $value = $_ENV[$var] ?? getenv($var) ?? 'NOT_SET';
    if ($var === 'DB_PASSWORD') {
        $value = ($value !== 'NOT_SET') ? '***SET***' : 'NOT_SET';
    }
    echo "$var = $value\n";
}
echo "</pre>";

// Show server variables
echo "<h3>Server Variables:</h3><pre>";
$server_vars = ['SERVER_SOFTWARE', 'SERVER_NAME', 'SERVER_PORT', 'REQUEST_METHOD', 'REQUEST_URI', 'HTTP_HOST', 'HTTP_USER_AGENT'];
foreach ($server_vars as $var) {
    $value = $_SERVER[$var] ?? 'NOT_SET';
    echo "$var = " . htmlspecialchars($value) . "\n";
}
echo "</pre>";

echo "<p>If you can see this page, PHP is working! Check Railway deploy logs for any errors.</p>";
echo "</body></html>";

// Force output
if (ob_get_level()) {
    ob_end_flush();
}
flush();
?>