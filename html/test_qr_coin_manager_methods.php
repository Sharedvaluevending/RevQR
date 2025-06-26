<?php
/**
 * TEST: QRCoinManager Methods
 * Verifies the return structure and functionality of QRCoinManager methods
 */
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/qr_coin_manager.php';

echo "<h2>QRCoinManager Methods Test</h2>\n";

$test_user_id = 1; // Change to a real user ID

// Test 1: Get Balance
echo "<h3>1. Testing getBalance()</h3>\n";
$balance = QRCoinManager::getBalance($test_user_id);
echo "<p>Balance for user $test_user_id: <strong>$balance</strong> (type: " . gettype($balance) . ")</p>\n";

// Test 2: Add Transaction
echo "<h3>2. Testing addTransaction()</h3>\n";
$add_result = QRCoinManager::addTransaction(
    $test_user_id,
    'earning',
    'test',
    10,
    'Test transaction'
);
echo "<p>Add transaction result:</p>\n";
echo "<pre>" . print_r($add_result, true) . "</pre>\n";

if (is_array($add_result) && isset($add_result['success'])) {
    echo "<p style='color: green'>✅ CORRECT: addTransaction() returns array with 'success' key</p>\n";
} else {
    echo "<p style='color: red'>❌ ERROR: addTransaction() should return array with 'success' key</p>\n";
}

// Test 3: Spend Coins
echo "<h3>3. Testing spendCoins()</h3>\n";
$spend_result = QRCoinManager::spendCoins(
    $test_user_id,
    5,
    'test',
    'Test spending'
);
echo "<p>Spend coins result:</p>\n";
echo "<pre>" . print_r($spend_result, true) . "</pre>\n";

if (is_array($spend_result) && isset($spend_result['success'])) {
    echo "<p style='color: green'>✅ CORRECT: spendCoins() returns array with 'success' key</p>\n";
} else {
    echo "<p style='color: red'>❌ ERROR: spendCoins() should return array with 'success' key</p>\n";
}

// Test 4: Final Balance
echo "<h3>4. Final Balance Check</h3>\n";
$final_balance = QRCoinManager::getBalance($test_user_id);
echo "<p>Final balance for user $test_user_id: <strong>$final_balance</strong></p>\n";

echo "<hr><p><strong>Next Steps:</strong></p>\n";
echo "<ol>\n";
echo "<li>Play blackjack and check the error logs at <code>/var/log/apache2/error.log</code></li>\n";
echo "<li>Look for lines starting with 'BLACKJACK API:' to see what's happening</li>\n";
echo "<li>Check the browser network tab for the API response</li>\n";
echo "</ol>\n";
?> 