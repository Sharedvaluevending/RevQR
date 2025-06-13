<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/csrf.php';

// Force no-cache headers
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
header("Content-Type: application/json");

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Force regenerate CSRF token for fresh request
unset($_SESSION['csrf_token']);
$fresh_token = generate_csrf_token();

// Return fresh token
echo json_encode([
    'success' => true,
    'token' => $fresh_token,
    'timestamp' => time()
]);
?> 