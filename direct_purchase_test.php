<?php
echo "=== Direct Purchase Test ===\n";

try {
    require_once 'html/core/config.php';
    require_once 'html/core/nayax_discount_manager.php';
    require_once 'core/qr_coin_manager.php';
    
    $user_id = 1; // Test user
    $item_id = 8; // Test item
    
    echo "Testing direct purchase...\n";
    echo "User ID: $user_id\n";
    echo "Item ID: $item_id\n\n";
    
    // Check user balance first
    $balance = QRCoinManager::getBalance($user_id);
    echo "User balance: $balance QR coins\n";
    
    // Get item details
    $stmt = $pdo->prepare("
        SELECT qsi.*, bsi.discount_percent, b.name as business_name
        FROM qr_store_items qsi
        LEFT JOIN business_store_items bsi ON qsi.business_store_item_id = bsi.id
        LEFT JOIN businesses b ON bsi.business_id = b.id
        WHERE qsi.id = ?
    ");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        echo "❌ Item not found\n";
        exit;
    }
    
    echo "Item: {$item['item_name']} - {$item['qr_coin_cost']} coins\n";
    
    if ($balance < $item['qr_coin_cost']) {
        echo "❌ Insufficient balance\n";
        exit;
    }
    
    // Test the purchase
    $discount_manager = new NayaxDiscountManager($pdo);
    $result = $discount_manager->purchaseDiscountCode($user_id, $item_id, 'test');
    
    echo "\nPurchase Result:\n";
    echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    
    if ($result['success']) {
        echo "\n✅ Direct purchase successful!\n";
        echo "Discount Code: {$result['discount_code']}\n";
        echo "Expires: {$result['expires_at']}\n";
        
        // Check new balance
        $new_balance = QRCoinManager::getBalance($user_id);
        echo "New balance: $new_balance QR coins\n";
    } else {
        echo "\n❌ Direct purchase failed: " . ($result['message'] ?? 'Unknown error') . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?> 