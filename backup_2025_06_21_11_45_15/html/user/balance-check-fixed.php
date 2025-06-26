<?php
// FIXED Balance Check - Session Conflict Resolution
header("Content-Type: application/json; charset=utf-8");

// Prevent any output before headers
ob_start();

// Start session safely with proper error handling
if (session_status() === PHP_SESSION_NONE) {
    try {
        session_start();
    } catch (Exception $e) {
        error_log("Session start failed: " . $e->getMessage());
        echo json_encode([
            "success" => false,
            "error" => "Session initialization failed",
            "balance" => 0
        ]);
        exit;
    }
}

// Clean any output buffer
ob_clean();

// Enhanced authentication check
$user_id = $_SESSION["user_id"] ?? null;

// Debug logging
error_log("Balance check - User ID: " . ($user_id ?: "null"));
error_log("Balance check - Session data: " . json_encode($_SESSION));

if (!$user_id || empty($user_id)) {
    echo json_encode([
        "success" => false,
        "error" => "User not authenticated",
        "balance" => 0,
        "debug" => [
            "session_id" => session_id(),
            "has_user_id" => isset($_SESSION["user_id"]),
            "user_id_value" => $user_id
        ]
    ]);
    exit;
}

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