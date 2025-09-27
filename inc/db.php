<?php

// Set secure session configuration BEFORE session_start()
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1); // Set to 1 for HTTPS only in production
    ini_set('session.use_strict_mode', 1);
    session_start();
}

// Database configuration from environment variables
$host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?? "ep-fancy-moon-afiiy7ck-pooler.c-2.us-west-2.aws.neon.tech";
$dbname = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?? "neondb";
$user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?? "neondb_owner";
$password = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?? "npg_5aCRYOblr1Qw";

try {
    $dsn = "pgsql:host=$host;port=5432;dbname=$dbname;sslmode=require";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
    
    // Test connection
    $pdo->query("SELECT 1");
    
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}

// Include helper functions ONLY ONCE
require_once __DIR__ . '/functions.php';


