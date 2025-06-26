<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';
require_once __DIR__ . '/../core/qr_code_manager.php';
require_once __DIR__ . '/../core/business_wallet_manager.php';

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
    
    // Check user balance
    $user_balance = QRCoinManager::getBalance($_SESSION['user_id']);
    if ($user_balance < $item['qr_coin_cost']) {
        throw new Exception('Insufficient QR coins');
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Generate unique purchase code
    $purchase_code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
    
    // Check if code already exists (very unlikely, but better safe)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM business_purchases WHERE purchase_code = ?");
    $stmt->execute([$purchase_code]);
    if ($stmt->fetchColumn() > 0) {
        // Regenerate if collision
        $purchase_code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
    }
    
    // Deduct QR coins - handle transaction properly
    $qr_transaction_success = QRCoinManager::addTransaction(
        $_SESSION['user_id'],
        'spending',
        'business_discount_purchase',
        -$item['qr_coin_cost'],
        "Purchased discount: " . $item['item_name'] . " at " . $item['business_name'],
        [
            'item_id' => $item_id, 
            'business_id' => $item['business_id'],
            'discount_percentage' => $item['discount_percentage'],
            'purchase_code' => $purchase_code
        ]
    );
    
    if (!$qr_transaction_success) {
        $pdo->rollback();
        throw new Exception('Failed to process QR coin transaction. Please try again.');
    }
    
    // Create purchase record
    $expires_at = date('Y-m-d H:i:s', strtotime('+30 days')); // 30-day expiration
    
    $stmt = $pdo->prepare("
        INSERT INTO business_purchases 
        (user_id, business_id, business_store_item_id, qr_coins_spent, discount_percentage, purchase_code, expires_at, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        $item['business_id'],
        $item_id,
        $item['qr_coin_cost'],
        $item['discount_percentage'],
        $purchase_code,
        $expires_at
    ]);
    
    $purchase_id = $pdo->lastInsertId();
    
    // Credit the business wallet (90% of QR coins paid by user)
    $business_earning = (int) ($item['qr_coin_cost'] * 0.9); // 90% to business, 10% platform fee
    $business_wallet = new BusinessWalletManager($pdo);
    $wallet_credited = $business_wallet->addCoins(
        $item['business_id'],
        $business_earning,
        'store_sale',
        "Store sale: {$item['item_name']} (Purchase code: {$purchase_code})",
        $purchase_id,
        'store_purchase',
        [
            'user_id' => $_SESSION['user_id'],
            'store_item_id' => $item_id,
            'purchase_code' => $purchase_code,
            'original_qr_cost' => $item['qr_coin_cost'],
            'platform_fee' => $item['qr_coin_cost'] - $business_earning
        ]
    );
    
    if (!$wallet_credited) {
        error_log("Failed to credit business wallet for purchase ID: {$purchase_id}");
        // Don't fail the transaction, but log the error
    }
    
    // Generate QR code for the purchase
    $purchase_data = [
        'purchase_code' => $purchase_code,
        'business_id' => $item['business_id'],
        'discount_percentage' => $item['discount_percentage'],
        'expires_at' => $expires_at,
        'user_id' => $_SESSION['user_id'],
        'nayax_machine_id' => $input['nayax_machine_id'] ?? null,
        'selected_items' => $input['selected_items'] ?? []
    ];
    
    $qr_result = QRCodeManager::generateDiscountQRCode($purchase_id, $purchase_data);
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Purchase successful! QR code generated for easy redemption.',
        'purchase_code' => $purchase_code,
        'expires_at' => $expires_at,
        'discount_percentage' => $item['discount_percentage'],
        'business_name' => $item['business_name'],
        'purchase_id' => $purchase_id,
        'qr_code_generated' => $qr_result['success'],
        'qr_message' => $qr_result['message'] ?? null,
        'business_credited' => $wallet_credited,
        'business_earning' => $business_earning
    ]);
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Business purchase error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?> 