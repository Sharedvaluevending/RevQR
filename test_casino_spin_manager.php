<?php
/**
 * Test Casino Spin Manager
 * Tests the new slot machine spin pack system
 */

require_once __DIR__ . '/html/core/config.php';
require_once __DIR__ . '/html/core/casino_spin_manager.php';
require_once __DIR__ . '/html/core/qr_coin_manager.php';

echo "=== Casino Spin Manager Test ===\n\n";

// Test user ID (assuming user 4 exists)
$test_user_id = 4;
$test_business_id = 1; // Assuming business 1 has casino enabled

echo "Testing with User ID: $test_user_id, Business ID: $test_business_id\n\n";

// Initialize the manager
CasinoSpinManager::init($pdo);

// Test 1: Get active spin packs (should be empty initially)
echo "1. Testing getActiveSpinPacks():\n";
$active_packs = CasinoSpinManager::getActiveSpinPacks($test_user_id);
echo "   Active packs found: " . count($active_packs) . "\n";
foreach ($active_packs as $pack) {
    echo "   - " . $pack['item_name'] . " (expires: " . ($pack['expires_at'] ?? 'never') . ")\n";
}
echo "\n";

// Test 2: Get available spins
echo "2. Testing getAvailableSpins():\n";
$spin_info = CasinoSpinManager::getAvailableSpins($test_user_id, $test_business_id);
echo "   Base spins: " . $spin_info['base_spins'] . "\n";
echo "   Bonus spins: " . $spin_info['bonus_spins'] . "\n";
echo "   Total spins: " . $spin_info['total_spins'] . "\n";
echo "   Spins used: " . $spin_info['spins_used'] . "\n";
echo "   Spins remaining: " . $spin_info['spins_remaining'] . "\n";
echo "   Active packs: " . count($spin_info['active_packs']) . "\n";
echo "\n";

// Test 3: Check if user can play
echo "3. Testing canPlay():\n";
$can_play = CasinoSpinManager::canPlay($test_user_id, $test_business_id);
echo "   Can play: " . ($can_play ? 'YES' : 'NO') . "\n\n";

// Test 4: Get spin pack status
echo "4. Testing getSpinPackStatus():\n";
$status = CasinoSpinManager::getSpinPackStatus($test_user_id);
echo "   Has packs: " . ($status['has_packs'] ? 'YES' : 'NO') . "\n";
echo "   Message: " . $status['message'] . "\n";
if ($status['has_packs']) {
    echo "   Total bonus spins: " . $status['total_bonus_spins'] . "\n";
    echo "   Pack count: " . $status['pack_count'] . "\n";
    echo "   Earliest expiry: " . ($status['earliest_expiry'] ?? 'none') . "\n";
}
echo "\n";

// Test 5: Check QR store items
echo "5. Testing QR Store slot_pack items:\n";
$stmt = $pdo->prepare("SELECT item_name, qr_coin_cost, rarity FROM qr_store_items WHERE item_type = 'slot_pack' AND is_active = 1");
$stmt->execute();
$slot_packs = $stmt->fetchAll();
echo "   Available slot packs: " . count($slot_packs) . "\n";
foreach ($slot_packs as $pack) {
    echo "   - " . $pack['item_name'] . " (" . $pack['qr_coin_cost'] . " coins, " . $pack['rarity'] . ")\n";
}
echo "\n";

// Test 6: Check user's QR coin balance
echo "6. Testing user QR coin balance:\n";
$balance = QRCoinManager::getBalance($test_user_id);
echo "   User balance: " . number_format($balance) . " QR coins\n\n";

// Test 7: Check business casino settings
echo "7. Testing business casino settings:\n";
$stmt = $pdo->prepare("SELECT casino_enabled, max_daily_plays FROM business_casino_settings WHERE business_id = ?");
$stmt->execute([$test_business_id]);
$casino_settings = $stmt->fetch();
if ($casino_settings) {
    echo "   Casino enabled: " . ($casino_settings['casino_enabled'] ? 'YES' : 'NO') . "\n";
    echo "   Max daily plays: " . $casino_settings['max_daily_plays'] . "\n";
} else {
    echo "   No casino settings found for business $test_business_id\n";
}
echo "\n";

// Test 8: Check today's casino plays
echo "8. Testing today's casino plays:\n";
$stmt = $pdo->prepare("
    SELECT COALESCE(plays_count, 0) as plays_today 
    FROM casino_daily_limits 
    WHERE user_id = ? AND business_id = ? AND play_date = CURDATE()
");
$stmt->execute([$test_user_id, $test_business_id]);
$plays_today = $stmt->fetchColumn() ?: 0;
echo "   Plays today: $plays_today\n\n";

echo "=== Test Complete ===\n";
echo "Summary:\n";
echo "- User can play: " . ($can_play ? 'YES' : 'NO') . "\n";
echo "- Available spins: " . $spin_info['spins_remaining'] . "/" . $spin_info['total_spins'] . "\n";
echo "- Active spin packs: " . count($active_packs) . "\n";
echo "- QR coin balance: " . number_format($balance) . "\n";

if (!$can_play && $spin_info['spins_remaining'] <= 0) {
    echo "\n💡 Tip: User should purchase slot_pack items from QR store to get more spins!\n";
}
?> 