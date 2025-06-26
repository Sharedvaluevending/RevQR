<?php
require_once __DIR__ . '/html/core/config.php';

echo "Testing Business Store Item Deletion...\n\n";

// Test 1: Check if there are any business store items
$stmt = $pdo->prepare("
    SELECT id, item_name, business_id 
    FROM business_store_items 
    LIMIT 5
");
$stmt->execute();
$items = $stmt->fetchAll();

if (empty($items)) {
    echo "❌ No business store items found to test deletion.\n";
    exit;
}

echo "Found " . count($items) . " business store items:\n";
foreach ($items as $item) {
    echo "- ID: {$item['id']}, Name: {$item['item_name']}, Business: {$item['business_id']}\n";
}
echo "\n";

// Test 2: Check for any purchases or references
foreach ($items as $item) {
    echo "Checking item ID {$item['id']} ({$item['item_name']}):\n";
    
    // Check business_purchases
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM business_purchases WHERE business_store_item_id = ?");
    $stmt->execute([$item['id']]);
    $business_purchases = $stmt->fetchColumn();
    
    // Check user_store_purchases
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_store_purchases WHERE store_item_id = ?");
    $stmt->execute([$item['id']]);
    $user_purchases = $stmt->fetchColumn();
    
    // Check qr_codes
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM qr_codes WHERE store_item_id = ?");
    $stmt->execute([$item['id']]);
    $qr_codes = $stmt->fetchColumn();
    
    $total = $business_purchases + $user_purchases + $qr_codes;
    
    echo "  - Business purchases: {$business_purchases}\n";
    echo "  - User purchases: {$user_purchases}\n";
    echo "  - QR codes: {$qr_codes}\n";
    echo "  - Total references: {$total}\n";
    
    if ($total == 0) {
        echo "  ✅ This item can be safely deleted (no references)\n";
    } else {
        echo "  ❌ This item cannot be deleted (has {$total} references)\n";
    }
    echo "\n";
}

echo "Test completed.\n";
?> 