<?php
require_once 'html/core/config.php';
require_once 'html/core/session.php';
require_once 'html/core/functions.php';
require_once 'html/core/casino_spin_manager.php';

// Test with a specific user - adjust user_id and business_id as needed
$test_user_id = 4; // Change this to a user with spin packs
$test_business_id = 1; // Change this to a business with casino enabled

echo "🎰 CASINO SPIN PACK DEBUG ANALYSIS\n";
echo "==================================\n\n";

echo "Testing with User ID: $test_user_id, Business ID: $test_business_id\n\n";

// 1. Check user's QR store purchases for slot packs
echo "1. USER'S SLOT PACK PURCHASES:\n";
$stmt = $pdo->prepare("
    SELECT uqsp.*, qsi.item_name, qsi.item_data, qsi.item_type
    FROM user_qr_store_purchases uqsp
    JOIN qr_store_items qsi ON uqsp.qr_store_item_id = qsi.id
    WHERE uqsp.user_id = ? AND qsi.item_type = 'slot_pack'
    ORDER BY uqsp.created_at DESC
");
$stmt->execute([$test_user_id]);
$user_purchases = $stmt->fetchAll();

if (empty($user_purchases)) {
    echo "   ❌ No slot pack purchases found for this user\n";
} else {
    foreach ($user_purchases as $purchase) {
        $data = json_decode($purchase['item_data'], true);
        echo "   📦 {$purchase['item_name']} (ID: {$purchase['id']})\n";
        echo "      Status: {$purchase['status']}\n";
        echo "      Purchased: {$purchase['created_at']}\n";
        echo "      Expires: " . ($purchase['expires_at'] ?? 'Never') . "\n";
        echo "      Data: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
        echo "      ---\n";
    }
}

echo "\n2. ACTIVE SPIN PACKS (from CasinoSpinManager):\n";
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
        echo "      ---\n";
    }
}

echo "\n3. AVAILABLE SPINS CALCULATION:\n";
$spin_info = CasinoSpinManager::getAvailableSpins($test_user_id, $test_business_id);
echo "   Base spins: {$spin_info['base_spins']}\n";
echo "   Bonus spins: {$spin_info['bonus_spins']}\n";
echo "   Total spins: {$spin_info['total_spins']}\n";
echo "   Spins used: {$spin_info['spins_used']}\n";
echo "   Spins remaining: {$spin_info['spins_remaining']}\n";

echo "\n4. TODAY'S CASINO PLAYS:\n";
$stmt = $pdo->prepare("
    SELECT cp.*, cdl.plays_count as daily_limit_count
    FROM casino_plays cp
    LEFT JOIN casino_daily_limits cdl ON cp.user_id = cdl.user_id 
        AND cp.business_id = cdl.business_id 
        AND DATE(cp.played_at) = cdl.play_date
    WHERE cp.user_id = ? AND cp.business_id = ? AND DATE(cp.played_at) = CURDATE()
    ORDER BY cp.played_at DESC
");
$stmt->execute([$test_user_id, $test_business_id]);
$todays_plays = $stmt->fetchAll();

echo "   Total plays today: " . count($todays_plays) . "\n";
if (!empty($todays_plays)) {
    foreach ($todays_plays as $play) {
        echo "   - Play #{$play['id']} at {$play['played_at']}: Bet {$play['bet_amount']}, Won {$play['win_amount']}\n";
    }
}

echo "\n5. CASINO DAILY LIMITS TABLE:\n";
$stmt = $pdo->prepare("
    SELECT * FROM casino_daily_limits 
    WHERE user_id = ? AND business_id = ? AND play_date = CURDATE()
");
$stmt->execute([$test_user_id, $test_business_id]);
$daily_limit = $stmt->fetch();

if ($daily_limit) {
    echo "   Plays count: {$daily_limit['plays_count']}\n";
    echo "   Total bet: {$daily_limit['total_bet']}\n";
    echo "   Total won: {$daily_limit['total_won']}\n";
} else {
    echo "   ❌ No daily limit record found for today\n";
}

echo "\n6. BUSINESS CASINO SETTINGS:\n";
$stmt = $pdo->prepare("SELECT * FROM business_casino_settings WHERE business_id = ?");
$stmt->execute([$test_business_id]);
$casino_settings = $stmt->fetch();

if ($casino_settings) {
    echo "   Casino enabled: " . ($casino_settings['casino_enabled'] ? 'Yes' : 'No') . "\n";
    echo "   Max daily plays: {$casino_settings['max_daily_plays']}\n";
    echo "   House edge: {$casino_settings['house_edge']}\n";
    echo "   Jackpot multiplier: {$casino_settings['jackpot_multiplier']}\n";
} else {
    echo "   ❌ No casino settings found for this business\n";
}

echo "\n7. POTENTIAL ISSUES ANALYSIS:\n";

// Check for logic issues in spin calculation
if (!empty($active_packs)) {
    foreach ($active_packs as $pack) {
        $pack_data = json_decode($pack['item_data'], true);
        $spins_per_day = $pack_data['spins_per_day'] ?? 0;
        
        // Check if this pack has been fully used
        $pack_start_date = date('Y-m-d', strtotime($pack['created_at']));
        $days_since_purchase = (strtotime('today') - strtotime($pack_start_date)) / 86400;
        $total_pack_spins_used = $days_since_purchase * $spins_per_day;
        
        // Get actual spins used from this pack for this business
        $stmt = $pdo->prepare("
            SELECT COALESCE(COUNT(*), 0) 
            FROM casino_plays 
            WHERE user_id = ? AND business_id = ? AND played_at >= ?
        ");
        $stmt->execute([$test_user_id, $test_business_id, $pack['created_at']]);
        $actual_spins_used = $stmt->fetchColumn();
        
        echo "   Pack: {$pack['item_name']}\n";
        echo "     - Created: {$pack['created_at']}\n";
        echo "     - Days since purchase: $days_since_purchase\n";
        echo "     - Spins per day: $spins_per_day\n";
        echo "     - Expected total spins used: $total_pack_spins_used\n";
        echo "     - Actual spins used since purchase: $actual_spins_used\n";
        
        // Calculate remaining spins from this pack
        $total_available_from_pack = min(
            $spins_per_day, 
            max(0, ($total_pack_spins_used + $spins_per_day) - $actual_spins_used)
        );
        echo "     - Available spins today from this pack: $total_available_from_pack\n";
        echo "     ---\n";
    }
}

// Check if can play
$can_play = CasinoSpinManager::canPlay($test_user_id, $test_business_id);
echo "\n8. CAN USER PLAY? " . ($can_play ? "✅ YES" : "❌ NO") . "\n";

if (!$can_play) {
    echo "\n💡 TROUBLESHOOTING SUGGESTIONS:\n";
    echo "   1. Check if user has active slot pack purchases\n";
    echo "   2. Verify casino_daily_limits table is updating correctly\n";
    echo "   3. Check if spin pack calculation logic is working\n";
    echo "   4. Ensure business has casino enabled with proper max_daily_plays\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Debug completed. Review the output above for issues.\n";
?> 