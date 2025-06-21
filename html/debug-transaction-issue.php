<?php
/**
 * Debug Transaction Issue
 * Helps identify the "no active transaction" error
 */

session_start();
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/qr_coin_manager.php';

// Check if user is logged in
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    die('Please log in first');
}

echo "<h2>🔍 Transaction Debug Tool</h2>";
echo "<p>User ID: {$user_id}</p>";

// Step 1: Check PDO connection and transaction state
echo "<h3>Step 1: PDO Connection Status</h3>";
try {
    echo "✅ PDO connected: " . ($pdo ? "Yes" : "No") . "<br>";
    echo "✅ PDO in transaction: " . ($pdo->inTransaction() ? "Yes" : "No") . "<br>";
    echo "✅ PDO autocommit: " . ($pdo->getAttribute(PDO::ATTR_AUTOCOMMIT) ? "Yes" : "No") . "<br>";
} catch (Exception $e) {
    echo "❌ PDO error: " . $e->getMessage() . "<br>";
}

// Step 2: Check current balance
echo "<h3>Step 2: Current Balance</h3>";
try {
    $balance = QRCoinManager::getBalance($user_id);
    echo "✅ Current balance: {$balance} QR coins<br>";
} catch (Exception $e) {
    echo "❌ Balance check error: " . $e->getMessage() . "<br>";
}

// Step 3: Test a small transaction
echo "<h3>Step 3: Test Transaction (1 coin earning)</h3>";
try {
    $result = QRCoinManager::addTransaction(
        $user_id,
        'earning',
        'debug_test',
        1,
        'Debug test transaction',
        ['debug' => true, 'timestamp' => time()],
        null,
        'debug'
    );
    
    if ($result['success']) {
        echo "✅ Test transaction successful<br>";
        echo "   - Transaction ID: " . ($result['transaction_id'] ?? 'N/A') . "<br>";
        echo "   - New balance: " . $result['balance'] . "<br>";
        echo "   - Previous balance: " . $result['previous_balance'] . "<br>";
    } else {
        echo "❌ Test transaction failed<br>";
        echo "   - Error: " . $result['error'] . "<br>";
        echo "   - Balance: " . $result['balance'] . "<br>";
        if (isset($result['debug'])) {
            echo "   - Debug info: " . json_encode($result['debug']) . "<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Test transaction exception: " . $e->getMessage() . "<br>";
    echo "   - File: " . $e->getFile() . "<br>";
    echo "   - Line: " . $e->getLine() . "<br>";
}

// Step 4: Check transaction history
echo "<h3>Step 4: Recent Transaction History</h3>";
try {
    $history = QRCoinManager::getTransactionHistory($user_id, 5);
    echo "✅ Found " . count($history) . " recent transactions<br>";
    foreach ($history as $tx) {
        echo "   - [{$tx['created_at']}] {$tx['transaction_type']}: {$tx['amount']} coins - {$tx['description']}<br>";
    }
} catch (Exception $e) {
    echo "❌ Transaction history error: " . $e->getMessage() . "<br>";
}

// Step 5: Test discount purchase simulation
echo "<h3>Step 5: Discount Purchase Simulation</h3>";
if (isset($_GET['test_purchase'])) {
    echo "<p>🧪 Simulating discount purchase...</p>";
    
    try {
        // Get a sample discount item
        $stmt = $pdo->prepare("
            SELECT * FROM qr_store_items 
            WHERE nayax_compatible = 1 AND is_active = 1 
            ORDER BY qr_coin_cost ASC 
            LIMIT 1
        ");
        $stmt->execute();
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($item) {
            echo "📦 Test item: {$item['item_name']} (Cost: {$item['qr_coin_cost']} coins)<br>";
            
            $current_balance = QRCoinManager::getBalance($user_id);
            echo "💰 Current balance: {$current_balance} coins<br>";
            
            if ($current_balance >= $item['qr_coin_cost']) {
                echo "✅ Sufficient balance for test purchase<br>";
                
                // Test the spending transaction
                $spend_result = QRCoinManager::spendCoins(
                    $user_id,
                    $item['qr_coin_cost'],
                    'debug_purchase',
                    "Debug test purchase: {$item['item_name']}",
                    ['debug' => true, 'item_id' => $item['id']],
                    $item['id'],
                    'debug_purchase'
                );
                
                if ($spend_result['success']) {
                    echo "✅ Test purchase successful!<br>";
                    echo "   - New balance: {$spend_result['balance']}<br>";
                    
                    // Refund the test purchase
                    $refund_result = QRCoinManager::addTransaction(
                        $user_id,
                        'adjustment',
                        'debug_refund',
                        $item['qr_coin_cost'],
                        "Debug test refund",
                        ['debug' => true, 'refund_for' => $item['id']],
                        null,
                        'debug_refund'
                    );
                    
                    if ($refund_result['success']) {
                        echo "✅ Test refund successful - balance restored<br>";
                    } else {
                        echo "⚠️ Test refund failed: " . $refund_result['error'] . "<br>";
                    }
                } else {
                    echo "❌ Test purchase failed<br>";
                    echo "   - Error: " . $spend_result['error'] . "<br>";
                    if (isset($spend_result['debug'])) {
                        echo "   - Debug: " . json_encode($spend_result['debug']) . "<br>";
                    }
                }
            } else {
                echo "⚠️ Insufficient balance for test purchase<br>";
            }
        } else {
            echo "❌ No test items found<br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Purchase simulation error: " . $e->getMessage() . "<br>";
        echo "   - File: " . $e->getFile() . "<br>";
        echo "   - Line: " . $e->getLine() . "<br>";
    }
} else {
    echo "<p><a href='?test_purchase=1' style='background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🧪 Run Purchase Test</a></p>";
}

// Step 6: Check database connection details
echo "<h3>Step 6: Database Connection Info</h3>";
try {
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    echo "✅ Database version: {$version}<br>";
    
    $isolation = $pdo->query('SELECT @@transaction_isolation')->fetchColumn();
    echo "✅ Transaction isolation: {$isolation}<br>";
    
    $autocommit = $pdo->query('SELECT @@autocommit')->fetchColumn();
    echo "✅ Autocommit: {$autocommit}<br>";
    
} catch (Exception $e) {
    echo "❌ Database info error: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<p><strong>Instructions:</strong></p>";
echo "<ol>";
echo "<li>Review all the above checks for any errors</li>";
echo "<li>If you see 'no active transaction' error, note exactly where it appears</li>";
echo "<li>Try the purchase test to simulate the discount purchase flow</li>";
echo "<li>Check the browser console for any JavaScript errors</li>";
echo "</ol>";

echo "<p><a href='/user/business-stores.php'>← Back to Business Stores</a> | ";
echo "<a href='/nayax/discount-store.php'>← Back to Discount Store</a></p>";
?> 