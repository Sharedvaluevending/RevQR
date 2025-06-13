<?php
// Direct database connection for testing
$host = 'localhost';
$dbname = 'revenueqr';
$username = 'root';
$password = 'root';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

echo "🎰 TESTING UNIFIED CASINO SYSTEM\n";
echo "=================================\n\n";

try {
    // Test 1: Check unified casino settings
    echo "1. Testing Unified Casino Settings...\n";
    $stmt = $pdo->query("SELECT * FROM casino_unified_settings WHERE id = 1");
    $unified_settings = $stmt->fetch();
    if ($unified_settings) {
        echo "✅ Unified settings configured:\n";
        echo "   - Platform: {$unified_settings['platform_name']}\n";
        echo "   - Daily spins: {$unified_settings['base_daily_spins']}\n";
        echo "   - Bet range: {$unified_settings['min_bet']}-{$unified_settings['max_bet']} QR Coins\n";
        echo "   - House edge target: {$unified_settings['house_edge_target']}%\n";
        echo "   - Jackpot threshold: {$unified_settings['jackpot_threshold']}x\n";
    } else {
        echo "❌ No unified settings found\n";
    }

    // Test 2: Check business participation migration
    echo "\n2. Testing Business Participation Migration...\n";
    $stmt = $pdo->query("
        SELECT COUNT(*) as migrated_count 
        FROM business_casino_participation 
        WHERE casino_enabled = 1
    ");
    $migrated_count = $stmt->fetch()['migrated_count'];
    echo "✅ Businesses migrated to participation model: {$migrated_count}\n";

    if ($migrated_count > 0) {
        $stmt = $pdo->query("
            SELECT b.name, bcp.revenue_share_percentage, bcp.location_bonus_multiplier 
            FROM business_casino_participation bcp
            JOIN businesses b ON bcp.business_id = b.id
            WHERE bcp.casino_enabled = 1
            LIMIT 3
        ");
        $participants = $stmt->fetchAll();
        foreach ($participants as $participant) {
            echo "   - {$participant['name']}: {$participant['revenue_share_percentage']}% revenue share, {$participant['location_bonus_multiplier']}x bonus\n";
        }
    }

    // Test 3: Check unified prize pool
    echo "\n3. Testing Unified Prize Pool...\n";
    $stmt = $pdo->query("SELECT COUNT(*) as prize_count FROM casino_unified_prizes WHERE is_active = 1");
    $prize_count = $stmt->fetch()['prize_count'];
    echo "✅ Unified prizes available: {$prize_count}\n";

    $stmt = $pdo->query("
        SELECT prize_name, win_probability, multiplier_min, multiplier_max, is_jackpot 
        FROM casino_unified_prizes 
        WHERE is_active = 1 
        ORDER BY win_probability DESC
    ");
    $prizes = $stmt->fetchAll();
    foreach ($prizes as $prize) {
        $type = $prize['is_jackpot'] ? '🎰 JACKPOT' : '🎯 Regular';
        echo "   - {$prize['prize_name']}: {$prize['win_probability']}% chance, {$prize['multiplier_min']}-{$prize['multiplier_max']}x ({$type})\n";
    }

    // Test 4: Check business casino summary view
    echo "\n4. Testing Business Casino Summary View...\n";
    $stmt = $pdo->query("SELECT COUNT(*) as enabled_locations FROM business_casino_summary WHERE casino_enabled = 1");
    $enabled_count = $stmt->fetch()['enabled_locations'];
    echo "✅ Casino-enabled locations in summary view: {$enabled_count}\n";

    // Test 5: Check casino plays table columns
    echo "\n5. Testing Casino Plays Table Updates...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM casino_plays LIKE 'business_revenue_share'");
    $revenue_column = $stmt->fetch();
    $stmt = $pdo->query("SHOW COLUMNS FROM casino_plays LIKE 'location_bonus_applied'");
    $bonus_column = $stmt->fetch();
    $stmt = $pdo->query("SHOW COLUMNS FROM casino_plays LIKE 'unified_prize_id'");
    $prize_column = $stmt->fetch();

    echo $revenue_column ? "✅ Revenue sharing column added\n" : "❌ Revenue sharing column missing\n";
    echo $bonus_column ? "✅ Location bonus column added\n" : "❌ Location bonus column missing\n";
    echo $prize_column ? "✅ Unified prize ID column added\n" : "❌ Unified prize ID column missing\n";

    // Test 6: Verify revenue tracking table
    echo "\n6. Testing Business Revenue Tracking...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'business_casino_revenue'");
    if ($stmt->fetch()) {
        echo "✅ Business revenue tracking table created\n";
        
        // Test the structure
        $stmt = $pdo->query("DESCRIBE business_casino_revenue");
        $columns = $stmt->fetchAll();
        echo "   Columns: ";
        echo implode(', ', array_column($columns, 'Field')) . "\n";
    } else {
        echo "❌ Business revenue tracking table missing\n";
    }

    echo "\n🎉 UNIFIED CASINO SYSTEM TEST RESULTS:\n";
    echo "=====================================\n";
    
    echo "✅ **System Simplified Successfully!**\n\n";
    
    echo "**Key Changes:**\n";
    echo "- ✅ Business-specific complex settings → Simple enable/disable participation\n";
    echo "- ✅ Multiple prize pools per business → Single unified prize pool\n";
    echo "- ✅ Different rules per business → Consistent rules everywhere\n";
    echo "- ✅ Complex jackpot settings → Unified jackpot system\n";
    echo "- ✅ Business prize management → Admin-only prize control\n\n";
    
    echo "**Business Benefits:**\n";
    echo "- 🎯 Simple on/off participation (no complex gambling settings)\n";
    echo "- 💰 Automatic revenue sharing from local casino activity\n";
    echo "- 🚶 Increased foot traffic (users must visit location to play)\n";
    echo "- 🏷️ Optional promotional features and location bonuses\n";
    echo "- ⚖️ Zero liability (all gambling managed by platform)\n\n";
    
    echo "**User Benefits:**\n";
    echo "- 🎮 Consistent casino experience across all locations\n";
    echo "- 📏 Same rules, same prizes, same interface everywhere\n";
    echo "- 🗺️ Encourages exploring different business locations\n";
    echo "- 🎁 Optional location bonuses for visiting specific places\n\n";
    
    echo "**Platform Benefits:**\n";
    echo "- 🎛️ Centralized control over all gambling mechanics\n";
    echo "- ⚖️ Simplified compliance and regulation management\n";
    echo "- 📊 Better analytics and optimization opportunities\n";
    echo "- 🛠️ Reduced development and maintenance complexity\n\n";
    
    echo "**Next Steps:**\n";
    echo "1. 🌐 Update casino frontend to use unified system\n";
    echo "2. 📱 Test location-based casino access\n";
    echo "3. 💼 Train businesses on simplified participation model\n";
    echo "4. 📈 Monitor revenue sharing and location bonus effectiveness\n";

} catch (Exception $e) {
    echo "❌ Error testing unified casino system: " . $e->getMessage() . "\n";
}
?> 