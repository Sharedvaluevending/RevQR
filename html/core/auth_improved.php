<?php
// Enhanced authentication functions

function is_logged_in() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        session_start();
    }
    
    return isset($_SESSION["user_id"]) && !empty($_SESSION["user_id"]);
}

function require_login($redirect_url = "/login.php") {
    if (!is_logged_in()) {
        header("Location: " . $redirect_url);
        exit;
    }
}

function require_role($required_role) {
    require_login();
    
    if (!isset($_SESSION["role"]) || $_SESSION["role"] !== $required_role) {
        // For business pages, redirect to upgrade or proper login
        if ($required_role === "business") {
            header("Location: /login.php?error=business_required");
        } else {
            header("Location: /access-denied.php");
        }
        exit;
    }
}

function has_role($role) {
    return is_logged_in() && isset($_SESSION["role"]) && $_SESSION["role"] === $role;
}

function get_user_id() {
    return $_SESSION["user_id"] ?? null;
}

function get_user_role() {
    return $_SESSION["role"] ?? null;
}

// Debug function
function debug_session() {
    echo "<pre>";
    echo "Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? "Active" : "Inactive") . "\n";
    echo "Session ID: " . session_id() . "\n";
    echo "User ID: " . ($_SESSION["user_id"] ?? "Not set") . "\n";
    echo "User Role: " . ($_SESSION["role"] ?? "Not set") . "\n";
    echo "Session Data: " . print_r($_SESSION, true);
    echo "</pre>";
}
?>