<?php
require_once 'html/core/config.php';
require_once 'html/core/ai_assistant.php';

echo "=== ENHANCED AI INSIGHTS TEST ===\n\n";

$business_id = 1; // Test with business ID 1
$aiAssistant = new AIAssistant();

echo "Testing Business ID: $business_id\n\n";

try {
    // Get comprehensive analytics
    echo "1. GETTING COMPREHENSIVE ANALYTICS...\n";
    $analytics = $aiAssistant->getBusinessAnalytics($business_id, $pdo);
    
    echo "📊 Basic Analytics:\n";
    echo "   - Revenue Trend: $" . number_format($analytics['revenue_trend'], 2) . "\n";
    echo "   - Total Sales: " . $analytics['total_sales'] . "\n";
    echo "   - Low Stock Count: " . $analytics['low_stock_count'] . "\n";
    echo "   - Optimization Score: " . $analytics['optimization_score'] . "%\n";
    echo "   - Date Range: " . $analytics['date_range'] . "\n\n";
    
    // NEW: Casino Analytics
    echo "🎰 Casino Analytics:\n";
    if (!empty($analytics['casino_participation'])) {
        $casino = $analytics['casino_participation'];
        echo "   - Casino Enabled: " . ($casino['casino_enabled'] ? 'YES' : 'NO') . "\n";
        if ($casino['casino_enabled']) {
            echo "   - Location Bonus: " . ($casino['location_bonus_multiplier'] ?? 1.0) . "x\n";
            echo "   - Featured Promotion: " . ($casino['featured_promotion'] ?? 'None') . "\n";
            if (!empty($analytics['casino_revenue'])) {
                $revenue = $analytics['casino_revenue'];
                echo "   - Casino Revenue: $" . number_format($revenue['total_casino_revenue'], 2) . "\n";
                echo "   - Total Plays: " . $revenue['total_plays'] . "\n";
            }
        }
    } else {
        echo "   - No casino participation data\n";
    }
    echo "\n";
    
    // NEW: Promotional Ads Analytics
    echo "📢 Promotional Ads Analytics:\n";
    if (!empty($analytics['promotional_ads'])) {
        foreach ($analytics['promotional_ads'] as $ad_type) {
            echo "   - {$ad_type['feature_type']}: {$ad_type['total_ads']} ads, {$ad_type['total_views']} views, {$ad_type['total_clicks']} clicks (CTR: {$ad_type['ctr']}%)\n";
        }
    } else {
        echo "   - No promotional ads data\n";
    }
    echo "\n";
    
    // NEW: Spin Wheels Analytics
    echo "🎡 Spin Wheels Analytics:\n";
    if (!empty($analytics['spin_wheels'])) {
        foreach ($analytics['spin_wheels'] as $wheel) {
            echo "   - {$wheel['wheel_name']}: {$wheel['total_spins']} spins, {$wheel['total_wins']} wins\n";
        }
    } else {
        echo "   - No spin wheels data\n";
    }
    echo "\n";
    
    // NEW: Pizza Trackers Analytics
    echo "🍕 Pizza Trackers Analytics:\n";
    if (!empty($analytics['pizza_trackers'])) {
        foreach ($analytics['pizza_trackers'] as $tracker) {
            echo "   - {$tracker['name']}: {$tracker['progress_percent']}% complete, $" . number_format($tracker['current_revenue'], 2) . "/$" . number_format($tracker['revenue_goal'], 2) . "\n";
        }
    } else {
        echo "   - No pizza trackers data\n";
    }
    echo "\n";
    
    // NEW: QR Performance Analytics
    echo "📱 QR Performance Analytics:\n";
    if (!empty($analytics['qr_performance'])) {
        foreach ($analytics['qr_performance'] as $qr_type) {
            echo "   - {$qr_type['qr_type']}: {$qr_type['total_qr_codes']} codes, {$qr_type['total_scans']} scans\n";
        }
    } else {
        echo "   - No QR performance data\n";
    }
    echo "\n";
    
    // Generate AI insights
    echo "2. GENERATING AI INSIGHTS...\n";
    $insights = $aiAssistant->generateInsights($analytics);
    
    echo "🧠 AI-Generated Insights (" . count($insights['recommendations']) . " total):\n";
    foreach ($insights['recommendations'] as $i => $insight) {
        echo "   " . ($i + 1) . ". [{$insight['priority']}] {$insight['title']}\n";
        echo "      Description: {$insight['description']}\n";
        echo "      Action: {$insight['action']}\n";
        echo "      Impact: {$insight['impact']}\n";
        echo "\n";
    }
    
    // Test specific AI chat responses for new features
    echo "3. TESTING AI CHAT RESPONSES...\n";
    
    $test_messages = [
        "How can I enable casino features to earn revenue?",
        "What promotional ads should I create?",
        "How do I optimize my spin wheel performance?",
        "Should I create pizza trackers for customer engagement?",
        "Why aren't my QR codes being scanned?"
    ];
    
    foreach ($test_messages as $i => $message) {
        echo "   Test " . ($i + 1) . ": '$message'\n";
        try {
            $response = $aiAssistant->sendChatMessage($message, $analytics);
            $response_text = is_array($response) ? json_encode($response) : (string)$response;
            echo "   Response: " . substr($response_text, 0, 100) . "...\n\n";
        } catch (Exception $e) {
            echo "   Error: " . $e->getMessage() . "\n\n";
        }
    }
    
    // Test new feature detection
    echo "4. FEATURE UTILIZATION ANALYSIS...\n";
    
    $features_status = [
        'Casino' => !empty($analytics['casino_participation']['casino_enabled']),
        'Promotional Ads' => !empty($analytics['promotional_ads']),
        'Spin Wheels' => !empty($analytics['spin_wheels']),
        'Pizza Trackers' => !empty($analytics['pizza_trackers']),
        'QR Codes' => !empty($analytics['qr_performance']),
        'Active Campaigns' => !empty($analytics['campaign_performance'])
    ];
    
    $enabled_features = array_filter($features_status);
    $disabled_features = array_diff_key($features_status, $enabled_features);
    
    echo "✅ ENABLED FEATURES (" . count($enabled_features) . "):\n";
    foreach ($enabled_features as $feature => $status) {
        echo "   - $feature\n";
    }
    
    echo "\n❌ DISABLED/UNUSED FEATURES (" . count($disabled_features) . "):\n";
    foreach ($disabled_features as $feature => $status) {
        echo "   - $feature\n";
    }
    
    echo "\n📈 OPTIMIZATION SCORE BREAKDOWN:\n";
    echo "   Current Score: {$analytics['optimization_score']}%\n";
    echo "   Potential with all features: ~85-95%\n";
    
    // Revenue opportunity analysis
    echo "\n💰 REVENUE OPPORTUNITY ANALYSIS:\n";
    
    $revenue_opportunities = [];
    
    if (empty($analytics['casino_participation']['casino_enabled'])) {
        $revenue_opportunities[] = "Casino Revenue: $200-800/month (passive income from customer play)";
    }
    
    if (empty($analytics['promotional_ads']) || array_sum(array_column($analytics['promotional_ads'], 'total_ads')) == 0) {
        $revenue_opportunities[] = "Promotional Ads: 25-50% increase in feature engagement";
    }
    
    if (empty($analytics['spin_wheels'])) {
        $revenue_opportunities[] = "Spin Wheels: 30-60% increase in customer engagement";
    }
    
    if (empty($analytics['pizza_trackers'])) {
        $revenue_opportunities[] = "Pizza Trackers: Community-driven repeat business";
    }
    
    if (!empty($revenue_opportunities)) {
        foreach ($revenue_opportunities as $opportunity) {
            echo "   • $opportunity\n";
        }
    } else {
        echo "   🎉 All major revenue features are being utilized!\n";
    }
    
    echo "\n5. SUCCESS METRICS SUMMARY:\n";
    echo "   📊 Analytics Depth: " . (count($analytics) > 10 ? 'COMPREHENSIVE' : 'BASIC') . "\n";
    echo "   🧠 AI Insights Quality: " . (count($insights['recommendations']) > 3 ? 'RICH' : 'LIMITED') . "\n";
    echo "   🎯 Feature Coverage: " . count($enabled_features) . "/6 features active\n";
    echo "   🚀 Revenue Optimization: " . (count($revenue_opportunities) == 0 ? 'MAXIMIZED' : count($revenue_opportunities) . ' opportunities remaining') . "\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== TEST COMPLETED ===\n";
?> 