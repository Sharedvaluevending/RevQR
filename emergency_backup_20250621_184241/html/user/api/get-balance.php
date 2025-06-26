<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/qr_coin_manager.php';

// Require user role
require_role('user');

header('Content-Type: application/json');

try {
    $user_id = $_SESSION['user_id'];
    $balance = QRCoinManager::getBalance($user_id);
    
    echo json_encode([
        'success' => true,
        'balance' => (int) $balance
    ]);
    
} catch (Exception $e) {
    error_log("Get balance error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error getting balance: ' . $e->getMessage()
    ]);
}
?> 