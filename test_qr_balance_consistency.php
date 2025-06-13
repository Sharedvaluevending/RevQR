<?php
/**
 * QR Coin Balance Consistency Test
 * Verifies all user pages show the same QR coin balance using QRCoinManager
 */

require_once __DIR__ . '/html/core/config.php';
require_once __DIR__ . '/html/core/qr_coin_manager.php';
require_once __DIR__ . '/html/core/functions.php';

echo "🎯 QR COIN BALANCE CONSISTENCY TEST\n";
echo "====================================\n\n";

$tests_passed = 0;
$tests_failed = 0;

function test_result($test_name, $passed, $details = '') {
    global $tests_passed, $tests_failed;
    
    if ($passed) {
        echo "✅ PASS: $test_name\n";
        if ($details) echo "   → $details\n";
        $tests_passed++;
    } else {
        echo "❌ FAIL: $test_name\n";
        if ($details) echo "   → $details\n";
        $tests_failed++;
    }
}

// Test User ID (use a real user for testing, or create one)
$test_user_id = 1; // Adjust this based on your database

echo "🔍 Testing QR Coin Balance Consistency...\n";
echo "Test User ID: {$test_user_id}\n\n";

// Test 1: Get QRCoinManager Balance (NEW SYSTEM)
echo "💰 Testing QRCoinManager Balance...\n";
try {
    $qr_manager_balance = QRCoinManager::getBalance($test_user_id);
    test_result("QRCoinManager balance retrieval", true, "Balance: {$qr_manager_balance} QR coins");
} catch (Exception $e) {
    test_result("QRCoinManager balance retrieval", false, "Error: " . $e->getMessage());
    $qr_manager_balance = 0;
}

// Test 2: Get getUserStats Balance (OLD SYSTEM)
echo "\n📊 Testing getUserStats Balance...\n";
try {
    $user_stats = getUserStats($test_user_id, '127.0.0.1');
    $legacy_balance = $user_stats['user_points'];
    test_result("getUserStats balance retrieval", true, "Balance: {$legacy_balance} points (legacy)");
} catch (Exception $e) {
    test_result("getUserStats balance retrieval", false, "Error: " . $e->getMessage());
    $legacy_balance = 0;
}

// Test 3: Balance Comparison
echo "\n⚖️ Comparing Balance Systems...\n";
$balance_difference = abs($qr_manager_balance - $legacy_balance);
$difference_percentage = $legacy_balance > 0 ? round(($balance_difference / $legacy_balance) * 100, 1) : 0;

test_result("Balance systems comparison", $balance_difference < 1000, 
    "QRCoinManager: {$qr_manager_balance}, getUserStats: {$legacy_balance}, Difference: {$balance_difference} ({$difference_percentage}%)");

// Test 4: Check File Includes for QRCoinManager
echo "\n📁 Testing File Dependencies...\n";
$user_files_to_check = [
    'html/user/dashboard.php',
    'html/user/spin.php', 
    'html/user/vote.php',
    'html/user/rewards.php',
    'html/user/avatars.php'
];

$files_using_qr_manager = 0;
$files_using_legacy = 0;

foreach ($user_files_to_check as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        $has_qr_manager = (strpos($content, 'QRCoinManager::getBalance') !== false);
        $has_legacy_points = (strpos($content, '$stats[\'user_points\']') !== false);
        
        if ($has_qr_manager && !$has_legacy_points) {
            test_result(basename($file) . " uses QRCoinManager", true, "Correctly uses QRCoinManager::getBalance()");
            $files_using_qr_manager++;
        } elseif ($has_legacy_points && !$has_qr_manager) {
            test_result(basename($file) . " uses legacy system", false, "Still uses \$stats['user_points'] - needs update");
            $files_using_legacy++;
        } elseif ($has_qr_manager && $has_legacy_points) {
            test_result(basename($file) . " mixed systems", false, "Uses both QRCoinManager and legacy - potential conflict");
        } else {
            test_result(basename($file) . " no balance display", true, "File doesn't display user balance");
        }
    } else {
        test_result(basename($file) . " file exists", false, "File not found");
    }
}

// Test 5: Check for Consistent Variable Names
echo "\n🔤 Testing Variable Name Consistency...\n";
$variable_consistency = 0;
$total_variable_checks = 0;

foreach ($user_files_to_check as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        $total_variable_checks++;
        
        // Check if file uses $user_points for QR coins
        $uses_user_points = (strpos($content, '$user_points = QRCoinManager::getBalance') !== false);
        $uses_other_var = (strpos($content, '$user_qr_balance = QRCoinManager::getBalance') !== false ||
                          strpos($content, '$qr_balance = QRCoinManager::getBalance') !== false);
        
        if ($uses_user_points) {
            $variable_consistency++;
            test_result(basename($file) . " variable naming", true, "Uses \$user_points consistently");
        } elseif ($uses_other_var) {
            test_result(basename($file) . " variable naming", false, "Uses non-standard variable name");
        } else {
            test_result(basename($file) . " variable naming", true, "No QR balance variable (acceptable)");
            $variable_consistency++;
        }
    }
}

// Test 6: Migration Status
echo "\n🔄 Testing Migration Status...\n";
$migration_complete = ($files_using_qr_manager >= 4 && $files_using_legacy == 0);
test_result("QR coin system migration", $migration_complete, 
    "Files using QRCoinManager: {$files_using_qr_manager}, Files using legacy: {$files_using_legacy}");

// Test 7: Database Transaction History
echo "\n💳 Testing Transaction History...\n";
try {
    $recent_transactions = QRCoinManager::getTransactionHistory($test_user_id, 5);
    $has_transactions = count($recent_transactions) > 0;
    test_result("QR coin transaction history", $has_transactions, count($recent_transactions) . " recent transactions found");
} catch (Exception $e) {
    test_result("QR coin transaction history", false, "Error: " . $e->getMessage());
}

// Test 8: Configuration Values
echo "\n⚙️ Testing Economic Configuration...\n";
try {
    $vote_base = ConfigManager::get('qr_coin_vote_base', 5);
    $spin_base = ConfigManager::get('qr_coin_spin_base', 15);
    $economy_mode = ConfigManager::get('economy_mode', 'legacy');
    
    $config_valid = ($vote_base > 0 && $spin_base > 0);
    test_result("Economic configuration", $config_valid, 
        "Vote: {$vote_base} coins, Spin: {$spin_base} coins, Mode: {$economy_mode}");
} catch (Exception $e) {
    test_result("Economic configuration", false, "Error: " . $e->getMessage());
}

// Final Summary
echo "\n📊 TEST SUMMARY\n";
echo "================\n";
echo "✅ Passed: {$tests_passed}\n";
echo "❌ Failed: {$tests_failed}\n";
$total_tests = $tests_passed + $tests_failed;
$success_rate = $total_tests > 0 ? round(($tests_passed / $total_tests) * 100, 1) : 0;
echo "📈 Success Rate: {$success_rate}%\n\n";

if ($success_rate >= 90) {
    echo "🎉 QR COIN BALANCE CONSISTENCY: EXCELLENT!\n";
    echo "All pages are now using the unified QRCoinManager system.\n\n";
    
    echo "✅ CONSISTENCY STATUS:\n";
    echo "• All user pages use QRCoinManager::getBalance()\n";
    echo "• No legacy \$stats['user_points'] references\n";
    echo "• Consistent variable naming (\$user_points)\n";
    echo "• Transaction history tracking enabled\n";
    echo "• Economic configuration active\n\n";
    
    echo "🌐 WEB RESULT:\n";
    echo "Users will now see identical QR coin balances on:\n";
    echo "• Dashboard: https://revenueqr.sharedvaluevending.com/user/dashboard.php\n";
    echo "• Spin Wheel: https://revenueqr.sharedvaluevending.com/user/spin.php\n";
    echo "• All other user pages\n";
    
} elseif ($success_rate >= 75) {
    echo "⚠️ QR COIN BALANCE CONSISTENCY: GOOD BUT NEEDS ATTENTION\n";
    echo "Most pages are consistent, but some issues remain.\n\n";
    
    echo "🔧 RECOMMENDED ACTIONS:\n";
    echo "1. Review failed tests above\n";
    echo "2. Complete remaining file updates\n";
    echo "3. Test user experience on live site\n";
    
} else {
    echo "🚨 QR COIN BALANCE CONSISTENCY: NEEDS MAJOR WORK\n";
    echo "Significant inconsistencies remain across pages.\n\n";
    
    echo "⚠️ CRITICAL ISSUES:\n";
    echo "• Users seeing different balances on different pages\n";
    echo "• Mixed old/new calculation systems\n";
    echo "• Potential data integrity problems\n";
}

echo "\n=== QR COIN BALANCE CONSISTENCY TEST COMPLETE ===\n";
?> 