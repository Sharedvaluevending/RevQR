<?php
// ENHANCED Balance Check - Session Persistence Fix
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';

header("Content-Type: application/json; charset=utf-8");

// Prevent any output before headers
ob_start();

// Clean any output buffer
ob_clean();

// Enhanced authentication check using session.php functions
if (!is_logged_in()) {
    echo json_encode([
        "success" => false,
        "error" => "User not authenticated",
        "balance" => 0,
        "code" => 401,
        "debug" => [
            "session_id" => session_id(),
            "has_user_id" => isset($_SESSION["user_id"]),
            "session_active" => session_status() === PHP_SESSION_ACTIVE
        ]
    ]);
    exit;
}

$user_id = $_SESSION["user_id"];

try {
    require_once __DIR__ . "/../core/config.php";
    require_once __DIR__ . "/../core/qr_coin_manager.php";
    
    $balance = QRCoinManager::getBalance($user_id);
    
    echo json_encode([
        "success" => true,
        "balance" => (int) $balance,
        "user_id" => $user_id,
        "timestamp" => time()
    ]);
    
} catch (Exception $e) {
    error_log("Balance check error: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "error" => "Server error: " . $e->getMessage(),
        "balance" => 0
    ]);
}
?>