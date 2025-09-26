<?php

// Set secure session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);


session_start();

// Database configuration - Replace with your Neon DB credentials
$host = "ep-fancy-moon-afiiy7ck-pooler.c-2.us-west-2.aws.neon.tech";
$dbname = "neondb";
$user = "neondb_owner";
$password = "npg_5aCRYOblr1Qw";

try {
    $dsn = "pgsql:host=$host;port=5432;dbname=$dbname;sslmode=require";
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection failed. Please try again later.");
}


