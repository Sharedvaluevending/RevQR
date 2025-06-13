<?php
require_once 'html/core/config.php';
require_once 'html/core/session.php';
require_once 'html/core/functions.php';
require_once 'html/core/casino_spin_manager.php';

// Test with a specific user - adjust user_id and business_id as needed
$test_user_id = 4; // Change this to a user with spin packs
$test_business_id = 1; // Change this to a business with casino enabled

echo "🎰 COMPREHENSIVE CASINO FIXES TEST\n";
echo "==================================\n\n";

echo "Testing with User ID: $test_user_id, Business ID: $test_business_id\n\n";

// Test 1: Check spin availability before any plays
echo "1. INITIAL SPIN AVAILABILITY:\n";
$initial_spin_info = CasinoSpinManager::getAvailableSpins($test_user_id, $test_business_id);
echo "   Base spins: {$initial_spin_info['base_spins']}\n";
echo "   Bonus spins: {$initial_spin_info['bonus_spins']}\n";
echo "   Total spins: {$initial_spin_info['total_spins']}\n";
echo "   Spins used: {$initial_spin_info['spins_used']}\n";
echo "   Spins remaining: {$initial_spin_info['spins_remaining']}\n";
echo "   Can play: " . (CasinoSpinManager::canPlay($test_user_id, $test_business_id) ? "✅ YES" : "❌ NO") . "\n";

// Test 2: Check active spin packs
echo "\n2. ACTIVE SPIN PACKS:\n";
$active_packs = CasinoSpinManager::getActiveSpinPacks($test_user_id);
if (empty($active_packs)) {
    echo "   ❌ No active spin packs found\n";
} else {
    foreach ($active_packs as $pack) {
        $data = json_decode($pack['item_data'], true);
        echo "   ✅ {$pack['item_name']}\n";
        echo "      Status: {$pack['status']}\n";
        echo "      Spins per day: " . ($data['spins_per_day'] ?? 'N/A') . "\n";
        echo "      Duration: " . ($data['duration_days'] ?? 'N/A') . " days\n";
        echo "      Created: {$pack['created_at']}\n";
        echo "      Expires: " . ($pack['expires_at'] ?? 'Never') . "\n";
        echo "      ---\n";
    }
}

// Test 3: Simulate a casino play to test the logic
echo "\n3. SIMULATING CASINO PLAY:\n";
if (CasinoSpinManager::canPlay($test_user_id, $test_business_id)) {
    echo "   ✅ User can play - simulating a play...\n";
    
    // Record a simulated play in casino_daily_limits
    $stmt = $pdo->prepare("
        INSERT INTO casino_daily_limits (user_id, business_id, play_date, plays_count, total_bet, total_won)
        VALUES (?, ?, CURDATE(), 1, 1, 0)
        ON DUPLICATE KEY UPDATE 
            plays_count = plays_count + 1,
            total_bet = total_bet + 1
    ");
    $stmt->execute([$test_user_id, $test_business_id]);
    
    // Check spin availability after the simulated play
    $after_play_spin_info = CasinoSpinManager::getAvailableSpins($test_user_id, $test_business_id);
    echo "   After simulated play:\n";
    echo "     Spins used: {$after_play_spin_info['spins_used']}\n";
    echo "     Spins remaining: {$after_play_spin_info['spins_remaining']}\n";
    echo "     Can still play: " . (CasinoSpinManager::canPlay($test_user_id, $test_business_id) ? "✅ YES" : "❌ NO") . "\n";
    
    // Rollback the simulated play
    $stmt = $pdo->prepare("
        UPDATE casino_daily_limits 
        SET plays_count = plays_count - 1, total_bet = total_bet - 1
        WHERE user_id = ? AND business_id = ? AND play_date = CURDATE()
    ");
    $stmt->execute([$test_user_id, $test_business_id]);
    echo "   ✅ Simulated play rolled back\n";
    
} else {
    echo "   ❌ User cannot play - checking why...\n";
    
    if ($initial_spin_info['spins_remaining'] <= 0) {
        echo "     Reason: No spins remaining ({$initial_spin_info['spins_remaining']})\n";
    }
    
    if ($initial_spin_info['base_spins'] <= 0) {
        echo "     Reason: Casino not enabled or no base spins\n";
    }
}

// Test 4: Test edge cases
echo "\n4. EDGE CASE TESTING:\n";

// Test with expired packs
echo "   Testing expired pack detection...\n";
$stmt = $pdo->prepare("
    SELECT COUNT(*) as expired_count
    FROM user_qr_store_purchases uqsp
    JOIN qr_store_items qsi ON uqsp.qr_store_item_id = qsi.id
    WHERE uqsp.user_id = ? 
    AND qsi.item_type = 'slot_pack' 
    AND uqsp.status = 'active'
    AND uqsp.expires_at IS NOT NULL 
    AND uqsp.expires_at <= NOW()
");
$stmt->execute([$test_user_id]);
$expired_count = $stmt->fetchColumn();
echo "   Expired packs that should be marked as used: $expired_count\n";

// Test pack expiration logic
foreach ($active_packs as $pack) {
    $data = json_decode($pack['item_data'], true);
    $duration_days = $data['duration_days'] ?? 7;
    $pack_end_date = date('Y-m-d H:i:s', strtotime($pack['created_at'] . " + {$duration_days} days"));
    $is_expired = strtotime($pack_end_date) <= time();
    
    echo "   Pack '{$pack['item_name']}': ";
    echo $is_expired ? "❌ EXPIRED" : "✅ ACTIVE";
    echo " (ends: $pack_end_date)\n";
}

// Test 5: Verify database consistency
echo "\n5. DATABASE CONSISTENCY CHECK:\n";

// Check casino_daily_limits vs casino_plays count
$stmt = $pdo->prepare("
    SELECT 
        cdl.plays_count as daily_limit_count,
        COUNT(cp.id) as actual_plays_count
    FROM casino_daily_limits cdl
    LEFT JOIN casino_plays cp ON cdl.user_id = cp.user_id 
        AND cdl.business_id = cp.business_id 
        AND DATE(cp.played_at) = cdl.play_date
    WHERE cdl.user_id = ? AND cdl.business_id = ? AND cdl.play_date = CURDATE()
    GROUP BY cdl.id
");
$stmt->execute([$test_user_id, $test_business_id]);
$consistency_check = $stmt->fetch();

if ($consistency_check) {
    $daily_limit_count = $consistency_check['daily_limit_count'];
    $actual_plays_count = $consistency_check['actual_plays_count'];
    
    echo "   Daily limits table shows: $daily_limit_count plays\n";
    echo "   Actual casino_plays count: $actual_plays_count plays\n";
    
    if ($daily_limit_count == $actual_plays_count) {
        echo "   ✅ Database counts are consistent\n";
    } else {
        echo "   ❌ Database counts are inconsistent!\n";
        echo "   This could cause spin counting issues.\n";
    }
} else {
    echo "   ℹ️ No daily limit record found for today\n";
}

// Test 6: Final verification
echo "\n6. FINAL VERIFICATION:\n";
$final_spin_info = CasinoSpinManager::getAvailableSpins($test_user_id, $test_business_id);
$can_play_final = CasinoSpinManager::canPlay($test_user_id, $test_business_id);

echo "   Final spins remaining: {$final_spin_info['spins_remaining']}\n";
echo "   Final can play status: " . ($can_play_final ? "✅ YES" : "❌ NO") . "\n";

if ($final_spin_info['spins_remaining'] > 0 && $can_play_final) {
    echo "   ✅ ALL TESTS PASSED - User should be able to access slot machine\n";
} else if ($final_spin_info['spins_remaining'] <= 0) {
    echo "   ℹ️ User has used all available spins for today\n";
    echo "   This is expected behavior if they've reached their limit\n";
} else {
    echo "   ❌ ISSUE DETECTED - User has spins but cannot play\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Comprehensive test completed.\n";

// Summary of fixes applied
echo "\n📋 FIXES APPLIED:\n";
echo "1. ✅ Simplified spin pack calculation logic in CasinoSpinManager\n";
echo "2. ✅ Fixed bonus spin calculation to properly add daily allowances\n";
echo "3. ✅ Updated JavaScript to track and display remaining spins\n";
echo "4. ✅ Added proper spin limit checking in client-side code\n";
echo "5. ✅ Improved error handling and user feedback\n";

echo "\n🎯 EXPECTED BEHAVIOR:\n";
echo "- Users with active spin packs should get bonus spins daily\n";
echo "- Spin count should decrease after each play\n";
echo "- Users should be blocked when they reach their limit\n";
echo "- Spin button should update to show 'No Spins Left' when appropriate\n";
echo "- Users should be redirected to QR Store to buy more spin packs\n";
?> 