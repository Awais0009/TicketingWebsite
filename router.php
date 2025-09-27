<?php
// Router for Railway PHP built-in server
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Log the request
error_log("Router handling: " . $uri);

// Route all requests to index.php
require_once __DIR__ . '/index.php';
?>