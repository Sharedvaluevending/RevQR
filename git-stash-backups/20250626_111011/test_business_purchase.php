<?php
require_once __DIR__ . '/html/core/config.php';

// Test business store purchase flow
echo "Testing Business Store Purchase System...\n\n";

// 1. Check if there are any business store items
$stmt = $pdo->prepare("
    SELECT id, item_name, qr_coin_cost, discount_percentage, business_id 
    FROM business_store_items 
    WHERE is_active = 1 
    LIMIT 1
");
$stmt->execute();
$item = $stmt->fetch();

if (!$item) {
    echo "No active business store items found.\n";
    exit;
}

echo "Found business store item: {$item['item_name']} (ID: {$item['id']})\n";
echo "Cost: {$item['qr_coin_cost']} QR coins\n";
echo "Discount: {$item['discount_percentage']}%\n\n";

// 2. Check if there are any users with QR coins
$stmt = $pdo->prepare("
    SELECT u.id, u.username, COALESCE(SUM(qct.amount), 0) as balance
    FROM users u
    LEFT JOIN qr_coin_transactions qct ON u.id = qct.user_id
    GROUP BY u.id
    HAVING balance > 0
    LIMIT 1
");
$stmt->execute();
$user = $stmt->fetch();

if (!$user) {
    echo "No users with QR coins found.\n";
    exit;
}

echo "Found user with QR coins: {$user['username']} (ID: {$user['id']}, Balance: {$user['balance']})\n\n";

// 3. Test the StoreManager purchase method
require_once __DIR__ . '/html/core/store_manager.php';

echo "Testing StoreManager::purchaseBusinessItem()...\n";
$result = StoreManager::purchaseBusinessItem($user['id'], $item['id']);

if ($result['success']) {
    echo "✅ Purchase successful!\n";
    echo "Purchase Code: {$result['purchase_code']}\n";
    echo "QR Code Generated: " . ($result['qr_code_generated'] ? 'Yes' : 'No') . "\n";
    echo "Message: {$result['message']}\n";
    
    // Check if QR code was actually generated in database
    $stmt = $pdo->prepare("
        SELECT id, qr_code_data, status 
        FROM business_purchases 
        WHERE purchase_code = ?
    ");
    $stmt->execute([$result['purchase_code']]);
    $purchase = $stmt->fetch();
    
    if ($purchase) {
        echo "✅ Purchase record created in business_purchases table\n";
        echo "QR Code Data: " . ($purchase['qr_code_data'] ? 'Present' : 'Missing') . "\n";
        echo "Status: {$purchase['status']}\n";
    } else {
        echo "❌ Purchase record not found in business_purchases table\n";
    }
    
} else {
    echo "❌ Purchase failed: {$result['message']}\n";
}

echo "\nTest completed.\n";
?> 