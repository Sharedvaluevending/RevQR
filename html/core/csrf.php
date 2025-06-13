<?php
/**
 * CSRF Protection
 * 
 * This file provides functions for generating and validating CSRF tokens
 * to protect against Cross-Site Request Forgery attacks.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generate a new CSRF token
 * 
 * @return string The generated token
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a CSRF token
 * 
 * @param string $token The token to validate
 * @return bool Whether the token is valid
 */
function validate_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check if the current request has a valid CSRF token
 * 
 * @return bool Whether the request has a valid token
 */
function check_csrf_token() {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    return $token && validate_csrf_token($token);
}

/**
 * Require a valid CSRF token for the current request
 * 
 * @throws Exception If the token is invalid
 */
function require_csrf_token() {
    if (!check_csrf_token()) {
        throw new Exception('Invalid CSRF token');
    }
}

/**
 * Get CSRF token HTML input
 * 
 * @return string The HTML input for the CSRF token
 */
function csrf_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verify CSRF token from POST request
 * 
 * @return bool Whether the CSRF token is valid
 */
function verify_csrf_token() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return true; // Only verify POST requests
    }
    
    // Ensure session is started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $token = $_POST['csrf_token'] ?? '';
    
    // Check if session token exists
    if (empty($_SESSION['csrf_token'])) {
        error_log('CSRF token validation failed: Session token not set. Session ID: ' . session_id() . ', User ID: ' . ($_SESSION['user_id'] ?? 'not set'));
        error_log('POST data: ' . print_r($_POST, true));
        http_response_code(403);
        die('CSRF token validation failed - session expired');
    }
    
    // Check if submitted token exists
    if (empty($token)) {
        error_log('CSRF token validation failed: No token submitted. Session token exists: ' . ($_SESSION['csrf_token'] ?? 'not set'));
        http_response_code(403);
        die('CSRF token validation failed - no token provided');
    }
    
    if (!validate_csrf_token($token)) {
        error_log('CSRF token validation failed. Session token: ' . ($_SESSION['csrf_token'] ?? 'not set') . ', Submitted token: ' . $token);
        error_log('Session ID: ' . session_id() . ', User ID: ' . ($_SESSION['user_id'] ?? 'not set'));
        http_response_code(403);
        die('CSRF token validation failed - invalid token');
    }
    return true;
} 