<?php
require_once __DIR__ . '/../../core/config.php';
require_once __DIR__ . '/../../core/session.php';
require_once __DIR__ . '/../../core/qr_coin_manager.php';
require_once __DIR__ . '/../../core/functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    
    // Get JSON input
    $json_input = file_get_contents('php://input');
    $data = json_decode($json_input, true);
    
    if (!$data) {
        throw new Exception('Invalid JSON input');
    }
    
    $final_balance = $data['final_balance'] ?? null;
    $source = $data['source'] ?? 'unknown';
    
    if ($final_balance === null || !is_numeric($final_balance)) {
        throw new Exception('Invalid final_balance provided');
    }
    
    // Get current balance from database
    $current_balance = QRCoinManager::getBalance($user_id);
    
    // Calculate the difference needed to reach the final balance
    $balance_adjustment = $final_balance - $current_balance;
    
    // Only make adjustment if there's a meaningful difference (more than 0.01)
    if (abs($balance_adjustment) > 0.01) {
        // Use QRCoinManager to properly record the balance adjustment
        $description = "Balance sync from $source (adjustment: " . ($balance_adjustment > 0 ? '+' : '') . "$balance_adjustment)";
        
        $result = QRCoinManager::addTransaction(
            $user_id,
            $balance_adjustment > 0 ? 'earning' : 'spending',
            'balance_sync',
            $balance_adjustment, // Can be positive or negative
            $description,
            ['source' => $source, 'sync_type' => 'force_sync'],
            null,
            'balance_sync'
        );
        
        if (!$result['success']) {
            throw new Exception('Failed to record balance adjustment: ' . $result['error']);
        }
        
        // Verify the new balance
        $new_balance = $result['balance'];
        
        echo json_encode([
            'success' => true,
            'message' => 'Balance synchronized successfully',
            'previous_balance' => $current_balance,
            'adjustment_amount' => $balance_adjustment,
            'new_balance' => $new_balance,
            'source' => $source
        ]);
    } else {
        // No adjustment needed
        echo json_encode([
            'success' => true,
            'message' => 'Balance already synchronized',
            'current_balance' => $current_balance,
            'requested_balance' => $final_balance,
            'source' => $source
        ]);
    }
    
} catch (Exception $e) {
    error_log("Force balance sync error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to sync balance: ' . $e->getMessage()
    ]);
}
?> 