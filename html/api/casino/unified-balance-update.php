<?php
/**
 * Unified Balance Update API
 * Handles balance changes for casino games using QRCoinManager
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['status' => 'OK']);
    exit;
}

try {
    require_once __DIR__ . '/../../core/config.php';
    require_once __DIR__ . '/../../core/session.php';
    require_once __DIR__ . '/../../core/qr_coin_manager.php';
    
    // Check if user is logged in
    if (!is_logged_in()) {
        throw new Exception('User not authenticated');
    }
    
    // Only accept POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate input
    $user_id = (int)($input['user_id'] ?? $_SESSION['user_id']);
    $business_id = (int)($input['business_id'] ?? 1);
    $balance_change = (int)($input['balance_change'] ?? 0);
    $description = $input['description'] ?? 'Casino balance adjustment';
    $game_type = $input['game_type'] ?? 'casino';
    
    // Verify user ID matches session
    if ($user_id !== $_SESSION['user_id']) {
        throw new Exception('User ID mismatch');
    }
    
    if ($balance_change == 0) {
        // No change needed
        echo json_encode([
            'success' => true,
            'message' => 'No balance change needed',
            'balance' => QRCoinManager::getBalance($user_id),
            'change_amount' => 0
        ]);
        exit;
    }
    
    // Apply the balance change using QRCoinManager
    if ($balance_change > 0) {
        // Positive change - add earnings
        $result = QRCoinManager::addTransaction(
            $user_id,
            'earning',
            'casino_adjustment',
            $balance_change,
            $description,
            ['game_type' => $game_type, 'business_id' => $business_id, 'adjustment_type' => 'positive'],
            $business_id,
            'casino'
        );
    } else {
        // Negative change - spend coins
        $result = QRCoinManager::addTransaction(
            $user_id,
            'spending',
            'casino_adjustment',
            $balance_change, // Already negative
            $description,
            ['game_type' => $game_type, 'business_id' => $business_id, 'adjustment_type' => 'negative'],
            $business_id,
            'casino'
        );
    }
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Balance updated successfully',
            'balance' => $result['balance'],
            'change_amount' => $balance_change,
            'transaction_id' => $result['transaction_id'] ?? null
        ]);
    } else {
        throw new Exception($result['error']);
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?> 