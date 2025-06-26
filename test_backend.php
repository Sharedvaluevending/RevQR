<?php
echo "=== Backend System Test ===\n";

// Test 1: Database connection
echo "1. Database Connection: ";
try {
    require_once 'html/core/config.php';
    $stmt = $pdo->query('SELECT 1');
    echo "✅ OK\n";
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
    exit;
}

// Test 2: QRCoinManager
echo "2. QRCoinManager: ";
try {
    require_once 'core/qr_coin_manager.php';
    echo "✅ OK\n";
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
    exit;
}

// Test 3: NayaxDiscountManager
echo "3. NayaxDiscountManager: ";
try {
    require_once 'html/core/nayax_discount_manager.php';
    echo "✅ OK\n";
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
    exit;
}

// Test 4: Check for discount items
echo "4. Discount Items Available: ";
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM qr_store_items qsi
        WHERE qsi.nayax_compatible = 1 AND qsi.is_active = 1
    ");
    $stmt->execute();
    $count = $stmt->fetchColumn();
    echo "✅ Found $count items\n";
    
    if ($count > 0) {
        $stmt = $pdo->prepare("
            SELECT qsi.id, qsi.item_name, qsi.qr_coin_cost
            FROM qr_store_items qsi
            WHERE qsi.nayax_compatible = 1 AND qsi.is_active = 1
            LIMIT 1
        ");
        $stmt->execute();
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "   Sample item: {$item['item_name']} - {$item['qr_coin_cost']} coins\n";
    }
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
}

// Test 5: Check for test user
echo "5. Test User: ";
try {
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE username = 'testuser' OR id = 1 LIMIT 1");
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "✅ Found user: {$user['username']} (ID: {$user['id']})\n";
        
        // Check balance
        $balance = QRCoinManager::getBalance($user['id']);
        echo "   Balance: $balance QR coins\n";
        
        // Test purchase simulation
        if ($balance >= 10) {
            echo "6. Test Purchase Simulation: ";
            try {
                $discount_manager = new NayaxDiscountManager($pdo);
                
                // Get first available item
                $stmt = $pdo->prepare("
                    SELECT id FROM qr_store_items 
                    WHERE nayax_compatible = 1 AND is_active = 1 AND qr_coin_cost <= ?
                    LIMIT 1
                ");
                $stmt->execute([$balance]);
                $item_id = $stmt->fetchColumn();
                
                if ($item_id) {
                    echo "✅ Ready to test with item ID: $item_id\n";
                    echo "   To test purchase, run: curl -X POST -H 'Content-Type: application/json' -d '{\"item_id\":$item_id,\"machine_id\":\"test\",\"source\":\"cli_test\"}' http://localhost/html/api/purchase-discount.php\n";
                } else {
                    echo "❌ No affordable items found\n";
                }
            } catch (Exception $e) {
                echo "❌ FAILED: " . $e->getMessage() . "\n";
            }
        } else {
            echo "6. Test Purchase: ❌ Insufficient balance ($balance coins)\n";
        }
    } else {
        echo "❌ No test user found\n";
    }
} catch (Exception $e) {
    echo "❌ FAILED: " . $e->getMessage() . "\n";
}

echo "\n=== Test Complete ===\n";
?> 