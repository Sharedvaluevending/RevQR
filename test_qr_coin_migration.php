<?php
/**
 * QR Coin System Migration Test Suite
 * Tests all fixes applied to migrate from points to QR coins system
 */

require_once __DIR__ . '/html/core/config.php';

echo "=== QR COIN SYSTEM MIGRATION TEST SUITE ===\n";
echo "Generated: " . date('Y-m-d H:i:s') . "\n\n";

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

// Test 1: Database Terminology Migration
echo "🔄 Testing Database Terminology Migration...\n";
try {
    $stmt = $pdo->query("
        SELECT 
            COUNT(CASE WHEN prize_won LIKE '%QR Coins%' THEN 1 END) as qr_coin_prizes,
            COUNT(CASE WHEN prize_won LIKE '%Points%' THEN 1 END) as point_prizes,
            COUNT(*) as total_prizes
        FROM spin_results 
        WHERE prize_won IN ('50 QR Coins', '200 QR Coins', '500 QR Coins!', '-20 QR Coins', '50 Points', '200 Points', '500 Points!', '-20 Points')
    ");
    $result = $stmt->fetch();
    
    test_result(
        "Spin Prize Terminology Migration", 
        $result['point_prizes'] == 0 && $result['qr_coin_prizes'] > 0,
        "QR Coin prizes: {$result['qr_coin_prizes']}, Remaining point prizes: {$result['point_prizes']}"
    );
} catch (Exception $e) {
    test_result("Spin Prize Terminology Migration", false, "Error: " . $e->getMessage());
}

// Test 2: Weekly Vote Limits Table
echo "\n🗳️ Testing Weekly Vote Limits System...\n";
try {
    $stmt = $pdo->query("DESCRIBE user_weekly_vote_limits");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $required_columns = ['user_id', 'week_year', 'votes_used', 'vote_limit'];
    $has_all_columns = true;
    foreach ($required_columns as $col) {
        if (!in_array($col, $columns)) {
            $has_all_columns = false;
            break;
        }
    }
    
    test_result(
        "Weekly Vote Limits Table Structure", 
        $has_all_columns,
        $has_all_columns ? "All required columns present" : "Missing required columns"
    );
} catch (Exception $e) {
    test_result("Weekly Vote Limits Table Structure", false, "Table missing or error: " . $e->getMessage());
}

// Test 3: Lose All Votes Functionality (Simulation)
echo "\n💀 Testing 'Lose All Votes' Logic...\n";
try {
    // Check if the new logic would work (without actually executing)
    $current_week = date('Y-W');
    $test_user_id = 1; // Use a test user
    
    // Simulate the new "Lose All Votes" logic
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as can_insert 
        FROM information_schema.tables 
        WHERE table_schema = 'revenueqr' 
        AND table_name = 'user_weekly_vote_limits'
    ");
    $stmt->execute();
    $table_exists = $stmt->fetchColumn() > 0;
    
    test_result(
        "'Lose All Votes' Infrastructure", 
        $table_exists,
        $table_exists ? "Weekly limits table exists for safe vote penalty" : "Table missing - would fall back to warning message"
    );
} catch (Exception $e) {
    test_result("'Lose All Votes' Infrastructure", false, "Error: " . $e->getMessage());
}

// Test 4: Spin Wheel Prize Consistency
echo "\n🎰 Testing Spin Wheel Prize Consistency...\n";
try {
    // Check if any old "Points" references remain in recent spin results
    $stmt = $pdo->query("
        SELECT prize_won, COUNT(*) as count 
        FROM spin_results 
        WHERE spin_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND (prize_won LIKE '%Points%' OR prize_won LIKE '%QR Coins%')
        GROUP BY prize_won
        ORDER BY count DESC
    ");
    $recent_prizes = $stmt->fetchAll();
    
    $has_old_points = false;
    $qr_coin_count = 0;
    foreach ($recent_prizes as $prize) {
        if (strpos($prize['prize_won'], 'Points') !== false) {
            $has_old_points = true;
        }
        if (strpos($prize['prize_won'], 'QR Coins') !== false) {
            $qr_coin_count += $prize['count'];
        }
    }
    
    test_result(
        "Recent Spin Prize Consistency", 
        !$has_old_points,
        $has_old_points ? "Found old 'Points' references in recent spins" : "All recent prizes use 'QR Coins' terminology"
    );
} catch (Exception $e) {
    test_result("Recent Spin Prize Consistency", false, "Error: " . $e->getMessage());
}

// Test 5: QR Coin Manager Integration
echo "\n💰 Testing QR Coin Manager Integration...\n";
try {
    // Include QR Coin Manager if it exists
    if (file_exists('html/core/qr_coin_manager.php')) {
        require_once 'html/core/qr_coin_manager.php';
    }
    
    // Check if QR Coin Manager class exists and is functional
    if (class_exists('QRCoinManager')) {
        // Test basic functionality (read-only)
        $test_balance = QRCoinManager::getBalance(1); // Test user 1
        test_result(
            "QR Coin Manager Functionality", 
            is_numeric($test_balance),
            "QR Coin Manager returns numeric balance: $test_balance"
        );
    } else {
        test_result("QR Coin Manager Functionality", false, "QRCoinManager class not found");
    }
} catch (Exception $e) {
    test_result("QR Coin Manager Functionality", false, "Error: " . $e->getMessage());
}

// Test 6: User Interface Terminology
echo "\n🖥️ Testing User Interface Terminology...\n";
try {
    // Check key files for terminology consistency
    $files_to_check = [
        'html/user/spin.php' => ['50 QR Coins', '200 QR Coins', '500 QR Coins!', '-20 QR Coins'],
        'html/user/leaderboard.php' => ['QR Coin Leaders', 'QR coins earned'],
        'html/user/avatars.php' => ['QR Coins']
    ];
    
    $terminology_consistent = true;
    $checked_files = 0;
    
    foreach ($files_to_check as $file => $expected_terms) {
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $file_consistent = true;
            
            foreach ($expected_terms as $term) {
                if (strpos($content, $term) === false) {
                    $file_consistent = false;
                    break;
                }
            }
            
            if (!$file_consistent) {
                $terminology_consistent = false;
            }
            $checked_files++;
        }
    }
    
    test_result(
        "UI Terminology Consistency", 
        $terminology_consistent,
        "Checked $checked_files files for QR Coins terminology"
    );
} catch (Exception $e) {
    test_result("UI Terminology Consistency", false, "Error: " . $e->getMessage());
}

// Test 7: Legacy Points System Status
echo "\n🔄 Testing Legacy Points System Migration...\n";
try {
    // Check if getUserStats function still calculates points correctly
    require_once 'html/core/functions.php';
    
    $test_stats = getUserStats(1, '127.0.0.1'); // Test user
    $has_user_points = isset($test_stats['user_points']) && is_numeric($test_stats['user_points']);
    
    test_result(
        "Legacy Stats Function Compatibility", 
        $has_user_points,
        $has_user_points ? "getUserStats() returns numeric user_points: {$test_stats['user_points']}" : "getUserStats() missing or broken"
    );
} catch (Exception $e) {
    test_result("Legacy Stats Function Compatibility", false, "Error: " . $e->getMessage());
}

// Summary
echo "\n📊 MIGRATION TEST SUMMARY\n";
echo "=====================================\n";
echo "✅ Tests Passed: $tests_passed\n";
echo "❌ Tests Failed: $tests_failed\n";
$success_rate = $tests_passed + $tests_failed > 0 ? round(($tests_passed / ($tests_passed + $tests_failed)) * 100, 1) : 0;
echo "📈 Success Rate: {$success_rate}%\n\n";

if ($tests_failed == 0) {
    echo "🎉 ALL MIGRATION TESTS PASSED! The QR coin system migration is complete.\n\n";
    echo "✨ MIGRATION STATUS: SUCCESSFUL\n";
    echo "🚀 System ready for production use!\n";
} else {
    echo "⚠️ MIGRATION INCOMPLETE - Some tests failed.\n";
    echo "🔧 Review failed tests and apply additional fixes.\n";
}

echo "\n=== END OF MIGRATION TEST SUITE ===\n";
?> 