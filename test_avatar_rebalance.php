<?php
/**
 * Avatar Economy Rebalance Test
 * Verifies that the avatar system is properly balanced with QR coin economy
 */

require_once __DIR__ . '/html/core/config.php';

echo "🎭 AVATAR ECONOMY REBALANCE TEST\n";
echo "================================\n\n";

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

// Test 1: Database Tables Created
echo "🔄 Testing Database Structure...\n";
try {
    $stmt = $pdo->query("DESCRIBE avatar_config");
    $avatar_config_exists = $stmt->rowCount() > 0;
    test_result("Avatar config table exists", $avatar_config_exists);
    
    $stmt = $pdo->query("DESCRIBE avatar_unlocks");
    $avatar_unlocks_exists = $stmt->rowCount() > 0;
    test_result("Avatar unlocks table exists", $avatar_unlocks_exists);
    
} catch (Exception $e) {
    test_result("Database tables", false, "Error: " . $e->getMessage());
}

// Test 2: Avatar Cost Balance
echo "\n💰 Testing Avatar Cost Balance...\n";
try {
    $stmt = $pdo->query("
        SELECT 
            rarity,
            COUNT(*) as count,
            MIN(cost) as min_cost,
            MAX(cost) as max_cost,
            AVG(cost) as avg_cost
        FROM avatar_config 
        WHERE cost > 0
        GROUP BY rarity
        ORDER BY avg_cost
    ");
    
    $cost_tiers = $stmt->fetchAll();
    $properly_balanced = true;
    $balance_details = [];
    
    foreach ($cost_tiers as $tier) {
        $avg_cost = (int)$tier['avg_cost'];
        $rarity = $tier['rarity'];
        
        // Check if costs are reasonable (based on 95 QR coins per day)
        $days_to_earn = round($avg_cost / 95, 1);
        $balance_details[] = "{$rarity}: {$avg_cost} coins (~{$days_to_earn} days)";
        
        // Balance criteria
        if ($rarity === 'rare' && ($avg_cost < 400 || $avg_cost > 800)) $properly_balanced = false;
        if ($rarity === 'epic' && ($avg_cost < 1000 || $avg_cost > 3000)) $properly_balanced = false;
        if ($rarity === 'legendary' && ($avg_cost < 2500 || $avg_cost > 6000)) $properly_balanced = false;
        if ($rarity === 'mythical' && ($avg_cost < 8000 || $avg_cost > 15000)) $properly_balanced = false;
    }
    
    test_result("Avatar costs properly balanced", $properly_balanced, implode(', ', $balance_details));
    
} catch (Exception $e) {
    test_result("Avatar cost analysis", false, "Error: " . $e->getMessage());
}

// Test 3: Free Avatar Accessibility
echo "\n🆓 Testing Free Avatar Accessibility...\n";
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM avatar_config WHERE cost = 0");
    $free_count = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM avatar_config");
    $total_count = $stmt->fetchColumn();
    
    $free_percentage = round(($free_count / $total_count) * 100, 1);
    $good_accessibility = $free_percentage >= 40; // At least 40% should be free/achievable
    
    test_result("Good avatar accessibility", $good_accessibility, "{$free_count}/{$total_count} avatars free ({$free_percentage}%)");
    
} catch (Exception $e) {
    test_result("Avatar accessibility", false, "Error: " . $e->getMessage());
}

// Test 4: Achievement Avatar Balance
echo "\n🏆 Testing Achievement Avatar Balance...\n";
try {
    $stmt = $pdo->query("
        SELECT avatar_id, name, unlock_requirement, special_perk 
        FROM avatar_config 
        WHERE unlock_method = 'achievement'
    ");
    
    $achievement_avatars = $stmt->fetchAll();
    $achievement_balance = true;
    $achievement_details = [];
    
    foreach ($achievement_avatars as $avatar) {
        $req = json_decode($avatar['unlock_requirement'], true);
        $votes_required = $req['votes_required'] ?? 0;
        
        // Check if achievement requirements are reasonable
        if ($votes_required < 100 || $votes_required > 1000) {
            $achievement_balance = false;
        }
        
        $achievement_details[] = "{$avatar['name']}: {$votes_required} votes";
    }
    
    test_result("Achievement avatar balance", $achievement_balance, implode(', ', $achievement_details));
    
} catch (Exception $e) {
    test_result("Achievement avatar analysis", false, "Error: " . $e->getMessage());
}

// Test 5: Perk System Implementation
echo "\n⚡ Testing Perk System...\n";
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM avatar_config 
        WHERE perk_data IS NOT NULL 
        AND JSON_VALID(perk_data) = 1
    ");
    
    $perk_count = $stmt->fetchColumn();
    
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM avatar_config 
        WHERE cost > 0 OR unlock_method IN ('achievement', 'milestone')
    ");
    
    $paid_count = $stmt->fetchColumn();
    
    $perk_coverage = $perk_count >= $paid_count * 0.8; // 80% of paid avatars should have structured perks
    
    test_result("Perk system coverage", $perk_coverage, "{$perk_count} avatars have structured perks out of {$paid_count} premium avatars");
    
} catch (Exception $e) {
    test_result("Perk system analysis", false, "Error: " . $e->getMessage());
}

// Test 6: Economic Progression
echo "\n📈 Testing Economic Progression...\n";
try {
    $stmt = $pdo->query("
        SELECT 
            cost,
            CASE 
                WHEN cost = 0 THEN 'Free'
                WHEN cost <= 700 THEN 'Week 1'
                WHEN cost <= 1500 THEN 'Week 2-3'
                WHEN cost <= 3000 THEN 'Month 1'
                WHEN cost <= 6000 THEN 'Month 2+'
                ELSE 'Long-term'
            END as earning_tier,
            COUNT(*) as count
        FROM avatar_config
        GROUP BY earning_tier
        ORDER BY 
            CASE earning_tier
                WHEN 'Free' THEN 1
                WHEN 'Week 1' THEN 2
                WHEN 'Week 2-3' THEN 3
                WHEN 'Month 1' THEN 4
                WHEN 'Month 2+' THEN 5
                WHEN 'Long-term' THEN 6
            END
    ");
    
    $progression = $stmt->fetchAll();
    $good_progression = true;
    $progression_details = [];
    
    foreach ($progression as $tier) {
        $progression_details[] = "{$tier['earning_tier']}: {$tier['count']} avatars";
        
        // Check for reasonable distribution
        if ($tier['earning_tier'] === 'Free' && $tier['count'] < 3) $good_progression = false;
        if ($tier['earning_tier'] === 'Week 1' && $tier['count'] < 1) $good_progression = false;
    }
    
    test_result("Economic progression", $good_progression, implode(', ', $progression_details));
    
} catch (Exception $e) {
    test_result("Economic progression analysis", false, "Error: " . $e->getMessage());
}

// Test 7: Verify Updated Costs Match Code
echo "\n🔄 Testing Code-Database Sync...\n";
try {
    // Check a few key avatars
    $key_avatars = [
        2 => 500,   // QR James
        3 => 600,   // QR Mike
        4 => 1200,  // QR Kevin
        11 => 10000 // QR Clayton
    ];
    
    $sync_good = true;
    $sync_details = [];
    
    foreach ($key_avatars as $avatar_id => $expected_cost) {
        $stmt = $pdo->prepare("SELECT name, cost FROM avatar_config WHERE avatar_id = ?");
        $stmt->execute([$avatar_id]);
        $avatar = $stmt->fetch();
        
        if ($avatar && $avatar['cost'] == $expected_cost) {
            $sync_details[] = "{$avatar['name']}: {$avatar['cost']} ✓";
        } else {
            $sync_good = false;
            $actual_cost = $avatar ? $avatar['cost'] : 'NOT FOUND';
            $sync_details[] = "{$avatar['name']}: Expected {$expected_cost}, Got {$actual_cost} ✗";
        }
    }
    
    test_result("Code-database synchronization", $sync_good, implode(', ', $sync_details));
    
} catch (Exception $e) {
    test_result("Code-database sync", false, "Error: " . $e->getMessage());
}

// Final Summary
echo "\n📊 TEST SUMMARY\n";
echo "================\n";
echo "✅ Passed: {$tests_passed}\n";
echo "❌ Failed: {$tests_failed}\n";
$total_tests = $tests_passed + $tests_failed;
$success_rate = $total_tests > 0 ? round(($tests_passed / $total_tests) * 100, 1) : 0;
echo "📈 Success Rate: {$success_rate}%\n\n";

if ($success_rate >= 85) {
    echo "🎉 AVATAR ECONOMY REBALANCE: SUCCESSFUL!\n";
    echo "The avatar system is now properly balanced with the QR coin economy.\n\n";
    
    echo "💡 IMPLEMENTATION STATUS:\n";
    echo "✅ Database structure created\n";
    echo "✅ Avatar costs rebalanced\n";
    echo "✅ Economic progression established\n";
    echo "✅ Achievement system balanced\n";
    echo "✅ Perk system implemented\n\n";
    
    echo "🚀 NEXT STEPS:\n";
    echo "1. Update avatar purchase logic to use new tables\n";
    echo "2. Implement avatar perk bonuses in earning calculations\n";
    echo "3. Add visual indicators for earning time estimates\n";
    echo "4. Test user purchasing flow\n";
    echo "5. Monitor user engagement metrics\n";
    
} else {
    echo "⚠️ AVATAR ECONOMY REBALANCE: NEEDS ATTENTION\n";
    echo "Some issues were found that need to be addressed.\n";
}

echo "\n=== AVATAR ECONOMY REBALANCE TEST COMPLETE ===\n";
?> 