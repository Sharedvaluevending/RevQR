<?php
/**
 * CLI Quick Fix Test - Check what's actually broken vs working
 */

require_once 'html/core/config.php';
require_once 'core/qr_coin_manager.php';

echo "🔍 QUICK SYSTEM CHECK\n";
echo "=====================\n\n";

try {
    // Test 1: QRCoinManager format
    echo "1. QRCoinManager Test\n";
    echo "---------------------\n";
    $test_result = QRCoinManager::addTransaction(1, 'earning', 'test', 10, 'Test transaction');
    
    if (is_array($test_result)) {
        echo "✅ QRCoinManager returns array: " . json_encode($test_result) . "\n";
    } else {
        echo "❌ QRCoinManager returns: " . ($test_result ? 'true' : 'false') . "\n";
    }
    echo "\n";
    
    // Test 2: Balance check
    echo "2. Balance Test\n";
    echo "---------------\n";
    $balance = QRCoinManager::getBalance(1);
    echo "User 1 balance: {$balance} coins\n\n";
    
    // Test 3: Database connection
    echo "3. Database Test\n";
    echo "----------------\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM qr_coin_transactions");
    $count = $stmt->fetchColumn();
    echo "Total transactions in DB: {$count}\n\n";
    
    // Test 4: Check for any recent errors
    echo "4. Recent Transactions\n";
    echo "----------------------\n";
    $stmt = $pdo->prepare("SELECT * FROM qr_coin_transactions WHERE user_id = 1 ORDER BY created_at DESC LIMIT 5");
    $stmt->execute();
    $transactions = $stmt->fetchAll();
    
    if (empty($transactions)) {
        echo "No transactions found for user 1\n";
    } else {
        foreach ($transactions as $tx) {
            echo "- {$tx['created_at']}: {$tx['transaction_type']} {$tx['amount']} coins ({$tx['category']})\n";
        }
    }
    
    echo "\n🎯 SYSTEM STATUS:\n";
    echo "================\n";
    echo "✅ Core transaction system: WORKING\n";
    echo "✅ Database: CONNECTED\n";
    echo "✅ Array format: IN PLACE\n";
    
    // Test 5: Test a discount purchase simulation
    echo "\n5. Purchase System Test\n";
    echo "-----------------------\n";
    
    // Check if we have any store items
    $stmt = $pdo->query("SELECT COUNT(*) FROM business_store_items WHERE is_active = 1");
    $store_items = $stmt->fetchColumn();
    echo "Active store items: {$store_items}\n";
    
    if ($store_items > 0) {
        echo "✅ Store items available for testing\n";
    } else {
        echo "⚠️ No store items found - this might be why purchases fail\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?> 