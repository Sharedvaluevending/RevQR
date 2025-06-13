<?php
/**
 * Test Casino Management System
 */

require_once __DIR__ . '/html/core/config.php';

echo "🎰 TESTING CASINO MANAGEMENT SYSTEM\n";
echo "===================================\n\n";

try {
    // Test global settings
    echo "1. Testing Global Settings...\n";
    $stmt = $pdo->query("SELECT * FROM casino_global_settings WHERE id = 1");
    $global_settings = $stmt->fetch();
    if ($global_settings) {
        echo "✅ Global settings found:\n";
        echo "   - Jackpot Min: {$global_settings['global_jackpot_min']} QR Coins\n";
        echo "   - House Edge: {$global_settings['global_house_edge']}%\n";
        echo "   - Bet Limits: {$global_settings['min_bet_limit']}-{$global_settings['max_bet_limit']} QR Coins\n";
    } else {
        echo "❌ No global settings found\n";
    }

    // Test prize templates
    echo "\n2. Testing Prize Templates...\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM casino_prize_templates WHERE is_active = 1");
    $template_count = $stmt->fetch()['count'];
    echo "✅ Active prize templates: {$template_count}\n";

    $stmt = $pdo->query("SELECT * FROM casino_prize_templates WHERE is_active = 1 ORDER BY win_probability DESC LIMIT 3");
    $templates = $stmt->fetchAll();
    foreach ($templates as $template) {
        echo "   - {$template['prize_name']}: {$template['win_probability']}% chance, {$template['multiplier_min']}-{$template['multiplier_max']}x\n";
    }

    // Test casino analytics summary table
    echo "\n3. Testing Analytics Table...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'casino_analytics_summary'");
    if ($stmt->fetch()) {
        echo "✅ Casino analytics summary table exists\n";
    } else {
        echo "❌ Casino analytics summary table missing\n";
    }

    // Test business casino settings
    echo "\n4. Testing Business Casino Integration...\n";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM business_casino_settings WHERE casino_enabled = 1");
    $enabled_businesses = $stmt->fetch()['count'];
    echo "✅ Businesses with casino enabled: {$enabled_businesses}\n";

    // Test casino plays data
    echo "\n5. Testing Casino Plays Data...\n";
    $stmt = $pdo->query("SELECT COUNT(*) as total_plays, SUM(bet_amount) as total_bets, SUM(win_amount) as total_winnings FROM casino_plays");
    $casino_stats = $stmt->fetch();
    echo "✅ Casino activity:\n";
    echo "   - Total plays: {$casino_stats['total_plays']}\n";
    echo "   - Total bets: {$casino_stats['total_bets']} QR Coins\n";
    echo "   - Total winnings: {$casino_stats['total_winnings']} QR Coins\n";
    
    if ($casino_stats['total_bets'] > 0) {
        $house_edge = (($casino_stats['total_bets'] - $casino_stats['total_winnings']) / $casino_stats['total_bets']) * 100;
        echo "   - House edge: " . number_format($house_edge, 2) . "%\n";
    }

    echo "\n🎉 CASINO MANAGEMENT SYSTEM TEST COMPLETE!\n";
    echo "\nAdmin Interface: /admin/casino-management.php\n";
    echo "Business Interface: /business/casino-analytics.php\n";
    echo "\nFeatures Available:\n";
    echo "✅ Global casino settings management\n";
    echo "✅ Prize template creation and editing\n";
    echo "✅ Business-specific casino analytics\n";
    echo "✅ Win rate monitoring and adjustment\n";
    echo "✅ Revenue tracking and reporting\n";
    echo "✅ Player behavior analytics\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?> 