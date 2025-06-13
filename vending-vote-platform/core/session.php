<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Function to check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Function to require login
function require_login() {
    if (!is_logged_in()) {
        header('Location: /login.php');
        exit;
    }
} 