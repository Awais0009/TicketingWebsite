<?php
echo "<h2>PHP Requirements Check</h2>\n";

// Check PHP version
echo "<h3>PHP Version: " . PHP_VERSION . "</h3>\n";

// Required extensions
$required = [
    'pdo' => 'PDO Extension',
    'pdo_pgsql' => 'PostgreSQL PDO Driver', 
    'session' => 'Session Management',
    'json' => 'JSON Support',
    'openssl' => 'OpenSSL (for security)',
    'hash' => 'Hash Functions',
    'filter' => 'Filter Functions',
    'mbstring' => 'Multibyte String'
];

echo "<h3>Extension Status:</h3>\n<ul>\n";
foreach ($required as $ext => $name) {
    $status = extension_loaded($ext) ? '✅ LOADED' : '❌ MISSING';
    echo "<li><strong>$name</strong>: $status</li>\n";
}
echo "</ul>\n";

// Test database connection
echo "<h3>Database Connection Test:</h3>\n";
try {
    require_once 'inc/db.php';
    echo "✅ Database connection successful\n";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
}

// Test security functions
echo "<h3>Security Functions Test:</h3>\n";
if (function_exists('password_hash')) {
    echo "✅ password_hash() available\n";
} else {
    echo "❌ password_hash() missing\n";
}

if (function_exists('random_bytes')) {
    echo "✅ random_bytes() available\n";
} else {
    echo "❌ random_bytes() missing\n";
}

if (function_exists('hash_equals')) {
    echo "✅ hash_equals() available\n";
} else {
    echo "❌ hash_equals() missing\n";
}
?>