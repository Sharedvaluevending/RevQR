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
    
    // Get business store item details with enhanced validation
    $stmt = $pdo->prepare("
        SELECT bsi.*, b.name as business_name, b.id as business_id
        FROM business_store_items bsi
        JOIN businesses b ON bsi.business_id = b.id
        WHERE bsi.id = ? AND bsi.is_active = 1
    ");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        throw new Exception('Item not found or inactive');
    }
    
    // Enhanced item validation
    if (!isset($item['qr_coin_cost']) || $item['qr_coin_cost'] <= 0) {
        throw new Exception('Invalid item pricing');
    }
    
    if (!isset($item['discount_percentage']) || $item['discount_percentage'] <= 0) {
        throw new Exception('Invalid discount configuration');
    }
    
    // Check user balance
    $user_balance = QRCoinManager::getBalance($_SESSION['user_id']);
    if ($user_balance < $item['qr_coin_cost']) {
        throw new Exception('Insufficient QR coins. You need ' . $item['qr_coin_cost'] . ' but only have ' . $user_balance);
    }
    
    // Generate unique purchase code first (outside any transaction)
    $purchase_code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
    
    // Check if code already exists (very unlikely, but better safe)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM business_purchases WHERE purchase_code = ?");
    $stmt->execute([$purchase_code]);
    if ($stmt->fetchColumn() > 0) {
        // Regenerate if collision
        $purchase_code = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
    }
    
    // Phase 1: Process the QR coin transaction (this has its own transaction handling)
    $spent = QRCoinManager::spendCoins(
        $_SESSION['user_id'],
        $item['qr_coin_cost'],
        'business_discount_purchase',
        "Purchased discount: " . $item['item_name'] . " at " . $item['business_name'],
        [
            'item_id' => $item_id, 
            'business_id' => $item['business_id'],
            'discount_percentage' => $item['discount_percentage'],
            'purchase_code' => $purchase_code
        ],
        $item_id,
        'business_store_purchase'
    );
    
    if (!$spent) {
        throw new Exception('Failed to process QR coin transaction. Please check your balance and try again.');
    }
    
    // Phase 2: Create purchase record and related operations in a separate transaction
    try {
        $pdo->beginTransaction();
        
        // Create purchase record with enhanced expiration logic
        $expires_at = date('Y-m-d H:i:s', strtotime('+30 days')); // 30-day expiration
        
        // Check if item has custom expiration
        if (isset($item['expiry_days']) && $item['expiry_days'] > 0) {
            $expires_at = date('Y-m-d H:i:s', strtotime('+' . $item['expiry_days'] . ' days'));
        } elseif (isset($item['expiry_hours']) && $item['expiry_hours'] > 0) {
            $expires_at = date('Y-m-d H:i:s', strtotime('+' . $item['expiry_hours'] . ' hours'));
        }
        
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
        
        // Generate QR code for the purchase - Enhanced with error handling
        $purchase_data = [
            'purchase_code' => $purchase_code,
            'business_id' => $item['business_id'],
            'discount_percentage' => $item['discount_percentage'],
            'expires_at' => $expires_at,
            'user_id' => $_SESSION['user_id'],
            'item_name' => $item['item_name'],
            'business_name' => $item['business_name'],
            'nayax_machine_id' => $input['nayax_machine_id'] ?? null,
            'selected_items' => $input['selected_items'] ?? []
        ];
        
        $qr_result = ['success' => false, 'message' => 'QR code generation skipped'];
        try {
            $qr_result = QRCodeManager::generateDiscountQRCode($purchase_id, $purchase_data);
            
            if (!$qr_result['success']) {
                error_log("QR Code generation failed for purchase ID {$purchase_id}: " . ($qr_result['message'] ?? 'Unknown error'));
                // Don't fail the transaction, but log the error
            }
        } catch (Exception $qr_e) {
            error_log("QR Code generation exception for purchase ID {$purchase_id}: " . $qr_e->getMessage());
            $qr_result = ['success' => false, 'message' => 'QR code generation failed: ' . $qr_e->getMessage()];
        }
        
        $pdo->commit();
        
        // Phase 3: Credit business wallet (separate from main transaction to prevent rollback issues)
        $business_earning = (int) ($item['qr_coin_cost'] * 0.9); // 90% to business, 10% platform fee
        $wallet_credited = false;
        
        try {
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
        } catch (Exception $wallet_e) {
            error_log("Failed to credit business wallet for purchase ID: {$purchase_id}. Error: " . $wallet_e->getMessage());
            // Don't fail the overall purchase for wallet issues
        }
        
        // Enhanced response with more details
        $response = [
            'success' => true, 
            'message' => 'Purchase successful! QR code generated for easy redemption.',
            'purchase_code' => $purchase_code,
            'expires_at' => $expires_at,
            'discount_percentage' => $item['discount_percentage'],
            'business_name' => $item['business_name'],
            'item_name' => $item['item_name'],
            'purchase_id' => $purchase_id,
            'qr_code_generated' => $qr_result['success'] ?? false,
            'qr_message' => $qr_result['message'] ?? null,
            'business_credited' => $wallet_credited,
            'business_earning' => $business_earning,
            'qr_coins_spent' => $item['qr_coin_cost'],
            'expires_in_days' => ceil((strtotime($expires_at) - time()) / (24 * 3600))
        ];
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // If the purchase record creation failed but QR coins were already spent,
        // we need to refund the user
        try {
            QRCoinManager::addTransaction(
                $_SESSION['user_id'],
                'refund',
                'business_discount_purchase_failed',
                $item['qr_coin_cost'],
                "Refund for failed business purchase: " . $item['item_name'],
                [
                    'original_purchase_code' => $purchase_code,
                    'item_id' => $item_id,
                    'business_id' => $item['business_id'],
                    'refund_reason' => 'Purchase record creation failed'
                ]
            );
            
            throw new Exception('Purchase failed during record creation. QR coins have been refunded. Error: ' . $e->getMessage());
        } catch (Exception $refund_e) {
            error_log("Failed to refund user after purchase failure: " . $refund_e->getMessage());
            throw new Exception('Purchase failed and refund failed. Please contact support. Original error: ' . $e->getMessage());
        }
    }
    
} catch (Exception $e) {
    error_log("Business purchase error: " . $e->getMessage() . " - User ID: " . ($_SESSION['user_id'] ?? 'unknown') . " - Item ID: " . ($item_id ?? 'unknown'));
    
    $response = [
        'success' => false, 
        'message' => $e->getMessage(),
        'debug_info' => [
            'user_id' => $_SESSION['user_id'] ?? null,
            'item_id' => $item_id ?? null,
            'timestamp' => date('Y-m-d H:i:s'),
            'user_balance' => $user_balance ?? null
        ]
    ];
    
    echo json_encode($response);
}
?> 