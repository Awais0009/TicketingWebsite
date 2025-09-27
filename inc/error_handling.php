<?php
// Production error handling configuration
// Add this to the top of inc/db.php

// Production settings
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Set error reporting level
if ($_ENV['APP_ENV'] === 'production') {
    error_reporting(0);
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
}

// Custom error handler
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    $error_message = "Error: [$errno] $errstr in $errfile on line $errline";
    error_log($error_message);
    
    // Don't display errors in production
    if ($_ENV['APP_ENV'] === 'production') {
        return true;
    }
    
    return false;
}

set_error_handler('customErrorHandler');
?>