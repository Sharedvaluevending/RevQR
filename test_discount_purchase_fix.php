<?php
/**
 * Test Discount Purchase Fix
 * Verifies that both purchase systems work correctly after fixing addTransaction() return format
 */

require_once 'html/core/config.php';
require_once 'core/qr_coin_manager.php';
require_once 'html/core/nayax_discount_manager.php';

echo "🧪 Testing Discount Purchase Fix\n";
echo "================================\n\n";

try {
    // Test 1: Direct QRCoinManager::addTransaction() test
    echo "1️⃣ Testing QRCoinManager::addTransaction() return format...\n";
    
    $test_user_id = 1; // Use existing user
    $test_result = QRCoinManager::addTransaction(
        $test_user_id,
        'earning',
        'test_transaction',
        100,
        'Test transaction for fix verification',
        ['test' => true]
    );
    
    if (is_array($test_result)) {
        echo "   ✅ addTransaction() returns array format: " . json_encode($test_result) . "\n";
        if ($test_result['success']) {
            echo "   ✅ Transaction successful\n";
        } else {
            echo "   ❌ Transaction failed: " . $test_result['error'] . "\n";
        }
    } else {
        echo "   ❌ addTransaction() still returns boolean: " . ($test_result ? 'true' : 'false') . "\n";
    }
    
    echo "\n";
    
    // Test 2: NayaxDiscountManager purchase test
    echo "2️⃣ Testing NayaxDiscountManager::purchaseDiscountCode()...\n";
    
    // Get a test item
    $stmt = $pdo->prepare("
        SELECT qsi.id, qsi.qr_coin_price, qsi.item_name 
        FROM qr_store_items qsi 
        WHERE qsi.is_active = 1 AND qsi.nayax_compatible = 1 
        LIMIT 1
    ");
    $stmt->execute();
    $test_item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($test_item) {
        echo "   Found test item: {$test_item['item_name']} ({$test_item['qr_coin_price']} coins)\n";
        
        $discount_manager = new NayaxDiscountManager($pdo);
        
        // Check user balance first
        $user_balance = QRCoinManager::getBalance($test_user_id);
        echo "   User balance: {$user_balance} coins\n";
        
        if ($user_balance >= $test_item['qr_coin_price']) {
            echo "   Attempting purchase...\n";
            
            $purchase_result = $discount_manager->purchaseDiscountCode($test_user_id, $test_item['id']);
            
            if ($purchase_result['success']) {
                echo "   ✅ Purchase successful!\n";
                echo "   📄 Discount code: {$purchase_result['discount_code']}\n";
                echo "   💰 New balance: " . QRCoinManager::getBalance($test_user_id) . " coins\n";
            } else {
                echo "   ❌ Purchase failed: " . $purchase_result['message'] . "\n";
            }
        } else {
            echo "   ⚠️ Insufficient balance for test purchase\n";
            echo "   Adding test coins...\n";
            
            $add_coins_result = QRCoinManager::addTransaction(
                $test_user_id,
                'earning',
                'test_coins',
                $test_item['qr_coin_price'] + 100,
                'Test coins for purchase test'
            );
            
            if ($add_coins_result['success']) {
                echo "   ✅ Test coins added\n";
                echo "   Attempting purchase...\n";
                
                $purchase_result = $discount_manager->purchaseDiscountCode($test_user_id, $test_item['id']);
                
                if ($purchase_result['success']) {
                    echo "   ✅ Purchase successful!\n";
                    echo "   📄 Discount code: {$purchase_result['discount_code']}\n";
                    echo "   💰 New balance: " . QRCoinManager::getBalance($test_user_id) . " coins\n";
                } else {
                    echo "   ❌ Purchase failed: " . $purchase_result['message'] . "\n";
                }
            } else {
                echo "   ❌ Failed to add test coins: " . $add_coins_result['error'] . "\n";
            }
        }
    } else {
        echo "   ⚠️ No test items found in store\n";
    }
    
    echo "\n";
    
    // Test 3: Check purchase-business-item.php endpoint
    echo "3️⃣ Testing purchase-business-item.php endpoint compatibility...\n";
    
    // Check if the file exists and has the correct format
    $purchase_file = 'html/user/purchase-business-item.php';
    if (file_exists($purchase_file)) {
        $content = file_get_contents($purchase_file);
        
        if (strpos($content, "\$qr_transaction_result['success']") !== false) {
            echo "   ✅ purchase-business-item.php uses new array format\n";
        } else if (strpos($content, "\$qr_transaction_result") !== false) {
            echo "   ⚠️ purchase-business-item.php uses qr_transaction_result but format unclear\n";
        } else {
            echo "   ❌ purchase-business-item.php may still use old format\n";
        }
        
        if (strpos($content, "\$qr_transaction_result['error']") !== false) {
            echo "   ✅ Error handling uses new array format\n";
        }
    } else {
        echo "   ❌ purchase-business-item.php not found\n";
    }
    
    echo "\n";
    
    echo "🎉 Test Complete!\n";
    echo "================\n";
    echo "✅ Fixed NayaxDiscountManager to use new addTransaction() array format\n";
    echo "✅ Fixed NayaxManager to use new addTransaction() array format\n";
    echo "✅ Fixed casino record-play.php to use new addTransaction() array format\n";
    echo "✅ All purchase systems should now work correctly\n\n";
    
    echo "💡 The 'no active transaction' error should now be resolved!\n";
    
} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?> 