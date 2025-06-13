<?php
// Authentication helper functions

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function is_business_user() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'vendor';
}

function is_admin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: /login.php');
        exit;
    }
}

function require_business_user() {
    require_login();
    if (!is_business_user()) {
        header('Location: /dashboard.php');
        exit;
    }
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        header('Location: /dashboard.php');
        exit;
    }
}

// Get current user's business ID
function get_user_business_id() {
    return $_SESSION['business_id'] ?? null;
}

// Get current user's ID
function get_user_id() {
    return $_SESSION['user_id'] ?? null;
}

// Get current user's role
function get_user_role() {
    return $_SESSION['user_role'] ?? null;
} 