<?php
require_once 'html/core/config.php';

echo "=== STEP BY STEP DEBUG ===\n\n";

$business_id = 1;

try {
    // Step 1: Basic revenue data
    echo "Step 1: Testing basic revenue query...\n";
    $stmt = $pdo->prepare("
        SELECT 
            SUM(quantity * sale_price) as total_revenue,
            COUNT(*) as total_sales,
            AVG(quantity * sale_price) as avg_sale_value
        FROM sales 
        WHERE business_id = ? 
        AND sale_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$business_id]);
    $revenue_data = $stmt->fetch();
    echo "✅ Step 1 passed\n";

    // Step 2: Casino participation
    echo "Step 2: Testing casino participation...\n";
    $stmt = $pdo->prepare("SELECT * FROM business_casino_participation WHERE business_id = ?");
    $stmt->execute([$business_id]);
    $analytics['casino_participation'] = $stmt->fetch() ?: [];
    echo "✅ Step 2 passed\n";
    
    // Step 3: Casino revenue (if enabled)
    echo "Step 3: Testing casino revenue...\n";
    if (!empty($analytics['casino_participation']['casino_enabled'])) {
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(SUM(revenue_share_earned), 0) as total_casino_revenue,
                COALESCE(SUM(total_plays_at_location), 0) as total_plays,
                COALESCE(AVG(revenue_share_earned), 0) as avg_play_revenue
            FROM business_casino_revenue 
            WHERE business_id = ? 
            AND date_period >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$business_id]);
        $analytics['casino_revenue'] = $stmt->fetch() ?: ['total_casino_revenue' => 0, 'total_plays' => 0, 'avg_play_revenue' => 0];
    }
    echo "✅ Step 3 passed\n";
    
    // Step 4: Promotional ads
    echo "Step 4: Testing promotional ads...\n";
    $stmt = $pdo->prepare("
        SELECT 
            pa.feature_type,
            COUNT(pa.id) as total_ads,
            SUM(CASE WHEN pa.is_active = 1 THEN 1 ELSE 0 END) as active_ads,
            COALESCE(SUM(bav.clicked), 0) as total_clicks,
            COALESCE(COUNT(bav.id), 0) as total_views,
            CASE WHEN COUNT(bav.id) > 0 THEN ROUND((SUM(bav.clicked) / COUNT(bav.id)) * 100, 2) ELSE 0 END as ctr
        FROM business_promotional_ads pa
        LEFT JOIN business_ad_views bav ON pa.id = bav.ad_id 
            AND bav.view_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        WHERE pa.business_id = ?
        GROUP BY pa.feature_type
    ");
    $stmt->execute([$business_id]);
    $analytics['promotional_ads'] = $stmt->fetchAll();
    echo "✅ Step 4 passed\n";
    
    // Step 5: Spin wheels
    echo "Step 5: Testing spin wheels...\n";
    $stmt = $pdo->prepare("
        SELECT 
            sw.*,
            COUNT(sr.id) as total_spins,
            COALESCE(SUM(CASE WHEN sr.is_big_win = 1 OR sr.prize_points > 0 THEN 1 ELSE 0 END), 0) as total_wins,
            COALESCE(AVG(sr.prize_points), 0) as avg_prize_value
        FROM spin_wheels sw
        LEFT JOIN spin_results sr ON sw.id = sr.spin_wheel_id 
            AND sr.spin_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        WHERE sw.business_id = ?
        GROUP BY sw.id
    ");
    $stmt->execute([$business_id]);
    $analytics['spin_wheels'] = $stmt->fetchAll();
    echo "✅ Step 5 passed\n";

    // Step 6: Pizza trackers with complex subqueries
    echo "Step 6: Testing pizza trackers (with subqueries)...\n";
    $stmt = $pdo->prepare("
        SELECT 
            pt.*,
            ROUND((pt.current_revenue / pt.revenue_goal) * 100, 1) as progress_percent,
            (pt.revenue_goal - pt.current_revenue) as remaining_amount,
            CASE WHEN pt.current_revenue >= pt.revenue_goal THEN 1 ELSE 0 END as is_complete,
            ptu.update_count,
            ptc.click_count
        FROM pizza_trackers pt
        LEFT JOIN (
            SELECT tracker_id, COUNT(*) as update_count 
            FROM pizza_tracker_updates 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY tracker_id
        ) ptu ON pt.id = ptu.tracker_id
        LEFT JOIN (
            SELECT tracker_id, COUNT(*) as click_count
            FROM pizza_tracker_clicks
            WHERE click_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
            GROUP BY tracker_id
        ) ptc ON pt.id = ptc.tracker_id
        WHERE pt.business_id = ?
    ");
    $stmt->execute([$business_id]);
    $analytics['pizza_trackers'] = $stmt->fetchAll();
    echo "✅ Step 6 passed\n";
    
    echo "\n✅ ALL STEPS PASSED! Data is available:\n";
    echo "- Casino participation: " . (!empty($analytics['casino_participation']) ? "YES" : "NO") . "\n";
    echo "- Promotional ads: " . count($analytics['promotional_ads'] ?? []) . " records\n";
    echo "- Spin wheels: " . count($analytics['spin_wheels'] ?? []) . " records\n";
    echo "- Pizza trackers: " . count($analytics['pizza_trackers'] ?? []) . " records\n";
    
} catch (Exception $e) {
    echo "❌ EXCEPTION at step: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== DEBUG COMPLETE ===\n";
?> 