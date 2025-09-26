<?php


// Prevent multiple inclusions
if (!function_exists('sanitizeOutput')) {

/**
 * Sanitize output for display
 */
function sanitizeOutput($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Check if user has specific role
 */
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Check if user is admin
 */
function isAdmin() {
    return hasRole('admin');
}

/**
 * Check if user is organizer
 */
function isOrganizer() {
    return hasRole('organizer') || hasRole('admin');
}

/**
 * Require specific role
 */
function requireRole($role) {
    if (!hasRole($role)) {
        header('Location: /index.php?error=access_denied');
        exit;
    }
}

/**
 * Require admin access
 */
function requireAdmin() {
    requireRole('admin');
}

} // End function_exists check