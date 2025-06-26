<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';

// Require user role
require_role('user');

// Set JSON content type
header('Content-Type: application/json');

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['item_id'])) {
        throw new Exception('Invalid request data');
    }
    
    $item_id = (int)$input['item_id'];
    
    if (!$item_id) {
        throw new Exception('Invalid item ID');
    }
    
    // DEBUG: Log the request
    error_log("DEBUG PURCHASE: User {$_SESSION['user_id']} attempting to purchase item {$item_id}");
    
    // Get business store item details
    $stmt = $pdo->prepare("
        SELECT bsi.*, b.name as business_name
        FROM business_store_items bsi
        JOIN businesses b ON bsi.business_id = b.id
        WHERE bsi.id = ? AND bsi.is_active = 1
    ");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch();
    
    if (!$item) {
        throw new Exception('Item not found or inactive');
    }
    
    error_log("DEBUG PURCHASE: Item found - {$item['item_name']} costs {$item['qr_coin_cost']} coins");
    
    // Check user balance
    $user_balance = QRCoinManager::getBalance($_SESSION['user_id']);
    error_log("DEBUG PURCHASE: User balance is {$user_balance} coins");
    
    if ($user_balance < $item['qr_coin_cost']) {
        throw new Exception('Insufficient QR coins');
    }
    
    // Generate unique purchase code
    $purchase_code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
    error_log("DEBUG PURCHASE: Generated purchase code: {$purchase_code}");
    
    // Check PDO transaction state before starting
    $in_transaction_before = $pdo->inTransaction();
    error_log("DEBUG PURCHASE: PDO in transaction before QRCoinManager call: " . ($in_transaction_before ? 'YES' : 'NO'));
    
    // Test QR coin deduction with detailed error reporting
    error_log("DEBUG PURCHASE: About to call QRCoinManager::spendCoins");
    
    $transaction_result = QRCoinManager::spendCoins(
        $_SESSION['user_id'],
        $item['qr_coin_cost'],
        'business_discount_purchase',
        "DEBUG: Purchased discount: " . $item['item_name'] . " at " . $item['business_name'],
        [
            'item_id' => $item_id, 
            'business_id' => $item['business_id'],
            'discount_percentage' => $item['discount_percentage'],
            'purchase_code' => $purchase_code,
            'debug' => true
        ],
        $item_id,
        'business_store_purchase'
    );
    
    error_log("DEBUG PURCHASE: QRCoinManager::spendCoins result: " . json_encode($transaction_result));
    
    // Check PDO transaction state after QRCoinManager
    $in_transaction_after = $pdo->inTransaction();
    error_log("DEBUG PURCHASE: PDO in transaction after QRCoinManager call: " . ($in_transaction_after ? 'YES' : 'NO'));
    
    // Check if QR coin transaction was successful
    if (!$transaction_result['success']) {
        error_log("DEBUG PURCHASE: QRCoinManager failed - " . ($transaction_result['error'] ?? 'Unknown error'));
        throw new Exception('Failed to deduct QR coins: ' . ($transaction_result['error'] ?? 'Unknown error'));
    }
    
    error_log("DEBUG PURCHASE: QRCoinManager succeeded, new balance: " . $transaction_result['balance']);
    
    // For debug, just return success without doing the full purchase
    echo json_encode([
        'success' => true,
        'message' => 'DEBUG: QR coin deduction successful',
        'purchase_code' => $purchase_code,
        'transaction_result' => $transaction_result,
        'debug_info' => [
            'user_id' => $_SESSION['user_id'],
            'item_id' => $item_id,
            'item_name' => $item['item_name'],
            'qr_cost' => $item['qr_coin_cost'],
            'old_balance' => $user_balance,
            'new_balance' => $transaction_result['balance'],
            'pdo_transaction_before' => $in_transaction_before,
            'pdo_transaction_after' => $in_transaction_after
        ]
    ]);
    
} catch (Exception $e) {
    error_log("DEBUG PURCHASE ERROR: " . $e->getMessage());
    error_log("DEBUG PURCHASE ERROR TRACE: " . $e->getTraceAsString());
    
    // Check final PDO state
    $final_transaction_state = $pdo->inTransaction();
    error_log("DEBUG PURCHASE: PDO in transaction at error: " . ($final_transaction_state ? 'YES' : 'NO'));
    
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
        error_log("DEBUG PURCHASE: Rolled back transaction");
    }
    
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'debug_info' => [
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine(),
            'pdo_transaction_state' => $final_transaction_state
        ]
    ]);
}
?> 