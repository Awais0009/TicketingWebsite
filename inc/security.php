<?php
/**
 * Basic security functions
 */

// Generate CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Validate CSRF token
function validateCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }
    return true;
}

// Clean input (basic sanitation for user input)
function cleanInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    return $data;
}

// Sanitize output (prevent XSS when displaying data)
function sanitizeOutput($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Check user role
function hasRole($requiredRole) {
    if (!isLoggedIn()) {
        return false;
    }

    $userRole = $_SESSION['role'] ?? 'user';

    // Admin has access to everything
    if ($userRole === 'admin') {
        return true;
    }

    // Check specific role
    return $userRole === $requiredRole;
}
