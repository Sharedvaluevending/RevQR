<?php
require_once 'html/core/config.php';
require_once 'html/core/ai_assistant.php';

$aiAssistant = new AIAssistant();
$business_id = 1;

echo "=== TESTING INSIGHTS GENERATION ===\n\n";

// Get analytics data
echo "1. Getting analytics data...\n";
$analytics = $aiAssistant->getBusinessAnalytics($business_id, $pdo);

echo "✅ Analytics retrieved:\n";
echo "- Casino participation: " . (empty($analytics['casino_participation']) ? "EMPTY" : "HAS DATA") . "\n";
echo "- Promotional ads: " . count($analytics['promotional_ads'] ?? []) . " records\n";
echo "- Spin wheels: " . count($analytics['spin_wheels'] ?? []) . " records\n";
echo "- Pizza trackers: " . count($analytics['pizza_trackers'] ?? []) . " records\n";
echo "- QR performance: " . count($analytics['qr_performance'] ?? []) . " records\n";

if (!empty($analytics['casino_participation'])) {
    echo "Casino enabled: " . ($analytics['casino_participation']['casino_enabled'] ? "YES" : "NO") . "\n";
}

// Debug each feature
echo "\n2. Feature analysis:\n";

// Casino
if (!empty($analytics['casino_participation'])) {
    $casino = $analytics['casino_participation'];
    echo "🎰 CASINO: Enabled=" . ($casino['casino_enabled'] ? "YES" : "NO") . "\n";
    if ($casino['casino_enabled']) {
        echo "   Revenue data: " . (isset($analytics['casino_revenue']) ? "YES" : "NO") . "\n";
        if (isset($analytics['casino_revenue'])) {
            echo "   Total revenue: $" . $analytics['casino_revenue']['total_casino_revenue'] . "\n";
        }
    }
}

// Promotional ads
if (!empty($analytics['promotional_ads'])) {
    echo "📢 PROMOTIONAL ADS:\n";
    foreach ($analytics['promotional_ads'] as $ad) {
        echo "   Type: {$ad['feature_type']}, Ads: {$ad['total_ads']}, Views: {$ad['total_views']}, CTR: {$ad['ctr']}%\n";
    }
}

// Spin wheels
if (!empty($analytics['spin_wheels'])) {
    echo "🎡 SPIN WHEELS:\n";
    foreach ($analytics['spin_wheels'] as $wheel) {
        echo "   Wheel: {$wheel['name']}, Spins: {$wheel['total_spins']}, Wins: {$wheel['total_wins']}\n";
    }
}

// Pizza trackers
if (!empty($analytics['pizza_trackers'])) {
    echo "🍕 PIZZA TRACKERS:\n";
    foreach ($analytics['pizza_trackers'] as $tracker) {
        echo "   Tracker: {$tracker['name']}, Progress: {$tracker['progress_percent']}%, Complete: " . ($tracker['is_complete'] ? "YES" : "NO") . "\n";
    }
}

// QR codes
if (!empty($analytics['qr_performance'])) {
    echo "📱 QR CODES:\n";
    foreach ($analytics['qr_performance'] as $qr) {
        echo "   Type: {$qr['qr_type']}, Codes: {$qr['total_qr_codes']}, Scans: {$qr['total_scans']}\n";
    }
}

echo "\n3. Generating insights...\n";
$insights = $aiAssistant->generateInsights($analytics);

echo "✅ Insights generated: " . count($insights['recommendations']) . " recommendations\n";

if (!empty($insights['recommendations'])) {
    echo "\n4. Generated recommendations:\n";
    foreach ($insights['recommendations'] as $i => $insight) {
        echo "   " . ($i + 1) . ". {$insight['title']} (Priority: {$insight['priority']})\n";
        echo "      Description: {$insight['description']}\n";
        echo "      Action: " . ($insight['action'] ?? 'None') . "\n\n";
    }
} else {
    echo "\n❌ NO INSIGHTS GENERATED - Debugging...\n";
    
    // Check if conditions are met for each insight type
    echo "Checking insight conditions:\n";
    
    // Casino insights
    if (empty($analytics['casino_participation'])) {
        echo "- Casino: NO DATA\n";
    } else {
        $casino = $analytics['casino_participation'];
        if (!$casino['casino_enabled']) {
            echo "- Casino: DISABLED (should generate insight)\n";
        } else {
            echo "- Casino: ENABLED\n";
            if (isset($analytics['casino_revenue'])) {
                echo "  Revenue: $" . $analytics['casino_revenue']['total_casino_revenue'] . "\n";
            }
        }
    }
    
    // Promotional ads insights
    if (empty($analytics['promotional_ads'])) {
        echo "- Promotional Ads: NO DATA (should generate insight)\n";
    } else {
        $total_ads = array_sum(array_column($analytics['promotional_ads'], 'total_ads'));
        echo "- Promotional Ads: $total_ads total ads\n";
    }
}

echo "\n=== TEST COMPLETE ===\n";
?> 