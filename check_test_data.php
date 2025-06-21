<?php
require_once __DIR__ . '/html/core/config.php';

echo "=== TEST DATA ANALYSIS FOR TRUNCATE PLANNING ===\n\n";

// Check analytics data - what time period and what type?
echo "📊 ANALYTICS DATA ANALYSIS:\n";
$analytics = $pdo->query("
    SELECT 
        MIN(created_at) as earliest,
        MAX(created_at) as latest,
        COUNT(*) as total_records,
        machine_id,
        campaign_id,
        item_id
    FROM analytics 
    WHERE machine_id NOT IN (SELECT id FROM machines)
       OR campaign_id NOT IN (SELECT id FROM qr_campaigns)
       OR item_id NOT IN (SELECT id FROM items)
    GROUP BY machine_id, campaign_id, item_id
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($analytics as $a) {
    echo "   - {$a['total_records']} records from {$a['earliest']} to {$a['latest']}\n";
    echo "     Machine ID: {$a['machine_id']}, Campaign: {$a['campaign_id']}, Item: {$a['item_id']}\n";
}

// Check votes data
echo "\n🗳️ VOTES DATA ANALYSIS:\n";
$votes = $pdo->query("
    SELECT 
        MIN(created_at) as earliest,
        MAX(created_at) as latest,
        COUNT(*) as total_records,
        user_id,
        machine_id,
        campaign_id
    FROM votes 
    WHERE user_id IS NULL OR user_id NOT IN (SELECT id FROM users)
       OR qr_code_id IS NULL OR qr_code_id NOT IN (SELECT id FROM qr_codes)
    GROUP BY user_id, machine_id, campaign_id
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($votes as $v) {
    echo "   - {$v['total_records']} votes from {$v['earliest']} to {$v['latest']}\n";
    echo "     User: " . ($v['user_id'] ?? 'NULL') . ", Machine: {$v['machine_id']}, Campaign: {$v['campaign_id']}\n";
}

// Check business store items
echo "\n🏪 BUSINESS STORE ITEMS ANALYSIS:\n";
$store_items = $pdo->query("
    SELECT 
        MIN(created_at) as earliest,
        MAX(created_at) as latest,
        COUNT(*) as total_records,
        business_id,
        machine_id
    FROM business_store_items 
    WHERE machine_id IS NULL OR machine_id NOT IN (SELECT id FROM voting_lists)
    GROUP BY business_id, machine_id
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($store_items as $si) {
    echo "   - {$si['total_records']} items from {$si['earliest']} to {$si['latest']}\n";
    echo "     Business: {$si['business_id']}, Machine: " . ($si['machine_id'] ?? 'NULL') . "\n";
}

// Check if this looks like test data
echo "\n🔍 TEST DATA INDICATORS:\n";
$test_indicators = $pdo->query("
    SELECT 
        'analytics' as table_name,
        COUNT(*) as count,
        MIN(created_at) as earliest,
        MAX(created_at) as latest
    FROM analytics 
    WHERE machine_id NOT IN (SELECT id FROM machines)
    UNION ALL
    SELECT 
        'votes' as table_name,
        COUNT(*) as count,
        MIN(created_at) as earliest,
        MAX(created_at) as latest
    FROM votes 
    WHERE user_id IS NULL OR user_id NOT IN (SELECT id FROM users)
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($test_indicators as $ti) {
    $is_test = false;
    $reason = "";
    
    // Check if it's old data (more than 30 days)
    $days_old = (time() - strtotime($ti['latest'])) / (24 * 60 * 60);
    if ($days_old > 30) {
        $is_test = true;
        $reason = "Old data ({$days_old} days old)";
    }
    
    // Check if it's from a specific test period
    if (strpos($ti['earliest'], '2025-05-27') !== false) {
        $is_test = true;
        $reason = "From test period (May 27, 2025)";
    }
    
    echo "   {$ti['table_name']}: {$ti['count']} records ({$ti['earliest']} to {$ti['latest']})\n";
    echo "   " . ($is_test ? "✅ LIKELY TEST DATA: {$reason}" : "❓ NEEDS REVIEW") . "\n";
}

echo "\n💡 TRUNCATE IMPACT:\n";
echo "If you're planning to truncate all test data, this orphaned data will be removed anyway.\n";
echo "The orphaned records are likely from:\n";
echo "1. Test campaigns that were deleted\n";
echo "2. Test machines that were removed\n";
echo "3. Test users that were cleaned up\n";
echo "4. Incomplete test transactions\n\n";

echo "🎯 RECOMMENDATION:\n";
echo "Since you're planning a full truncate/reset, you can safely ignore these orphans.\n";
echo "They'll be cleaned up when you do your fresh start.\n";
echo "No need to fix anything now - focus on your main development instead!\n"; 