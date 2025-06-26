<?php
require_once __DIR__ . '/config.php';

// Start session with custom settings
session_name(SESSION_NAME);
session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// Debug session issues
if (isset($_GET['debug_session'])) {
    error_log("Session Debug - ID: " . session_id());
    error_log("Session Debug - Data: " . json_encode($_SESSION));
    error_log("Session Debug - Cookie params: " . json_encode(session_get_cookie_params()));
}

// Session security functions
function regenerate_session() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

function destroy_session() {
    $_SESSION = array();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();
}

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Check user role
function has_role($required_role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $required_role;
}

// Check if user is a business user
function is_business_user() {
    return has_role('business');
}

// Get business ID
function get_business_id() {
    return $_SESSION['business_id'] ?? null;
}

// Require login
function require_login() {
    if (!is_logged_in()) {
        // Log the login requirement for debugging
        error_log("Login required: No valid session found. Session data: " . json_encode($_SESSION));
        header('Location: ' . APP_URL . '/login.php');
        exit();
    }
}

// Require specific role
function require_role($role) {
    require_login();
    if (!has_role($role)) {
        // Log the access attempt for debugging
        error_log("Role access denied: User has role '" . ($_SESSION['role'] ?? 'none') . "' but needs '$role'");
        header('Location: ' . APP_URL . '/unauthorized.php');
        exit();
    }
}

// Require business user
function require_business_user() {
    require_login();
    if (!is_business_user()) {
        header('Location: ' . APP_URL . '/dashboard.php');
        exit();
    }
}

// Set session data
function set_session_data($user_id, $role, $additional_data = []) {
    $_SESSION['user_id'] = $user_id;
    $_SESSION['role'] = $role;
    foreach ($additional_data as $key => $value) {
        $_SESSION[$key] = $value;
    }
    regenerate_session();
} 