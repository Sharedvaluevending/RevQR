<?php
require_once __DIR__ . '/html/core/config.php';

// Test the AI assistant analytics with detailed error reporting
echo "=== AI Assistant Exception Debug ===\n\n";

$test_business_id = 1;

echo "Testing individual queries from AI assistant...\n\n";

// Test 1: Revenue trends
echo "1. Testing revenue trends query:\n";
try {
    $stmt = $pdo->prepare("
        SELECT 
            SUM(quantity * sale_price) as total_revenue,
            COUNT(*) as total_sales,
            AVG(quantity * sale_price) as avg_sale_value
        FROM sales 
        WHERE business_id = ? 
        AND sale_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$test_business_id]);
    $revenue_data = $stmt->fetch();
    echo "   ✅ Success: Revenue: $" . number_format($revenue_data['total_revenue'] ?? 0, 2) . "\n";
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Test 2: Casino participation
echo "\n2. Testing casino participation query:\n";
try {
    $stmt = $pdo->prepare("SELECT * FROM business_casino_participation WHERE business_id = ?");
    $stmt->execute([$test_business_id]);
    $casino_data = $stmt->fetch();
    echo "   ✅ Success: Casino enabled: " . ($casino_data['casino_enabled'] ?? 'false') . "\n";
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Test 3: Promotional ads
echo "\n3. Testing promotional ads query:\n";
try {
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
    $stmt->execute([$test_business_id]);
    $promo_data = $stmt->fetchAll();
    echo "   ✅ Success: Found " . count($promo_data) . " promotional ad types\n";
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Test 4: Spin wheels
echo "\n4. Testing spin wheels query:\n";
try {
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
    $stmt->execute([$test_business_id]);
    $spin_data = $stmt->fetchAll();
    echo "   ✅ Success: Found " . count($spin_data) . " spin wheels\n";
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Test 5: Top selling items
echo "\n5. Testing top selling items query:\n";
try {
    $stmt = $pdo->prepare("
        SELECT 
            s.item_id,
            COALESCE(vli.item_name, CONCAT('Item #', s.item_id)) as item_name,
            SUM(s.quantity) as total_quantity,
            SUM(s.quantity * s.sale_price) as total_revenue,
            COUNT(*) as sale_count,
            AVG(s.sale_price) as avg_price,
            vli.retail_price as current_price,
            vli.cost_price,
            vli.inventory as current_stock
        FROM sales s
        LEFT JOIN voting_list_items vli ON s.item_id = vli.id
        WHERE s.business_id = ? 
        AND s.sale_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY s.item_id
        ORDER BY total_revenue DESC
        LIMIT 15
    ");
    $stmt->execute([$test_business_id]);
    $top_items = $stmt->fetchAll();
    echo "   ✅ Success: Found " . count($top_items) . " top selling items\n";
    if (!empty($top_items)) {
        echo "      First item: " . ($top_items[0]['item_name'] ?? 'Unknown') . " - $" . number_format($top_items[0]['total_revenue'] ?? 0, 2) . "\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Test 6: Voting data
echo "\n6. Testing voting data query:\n";
try {
    $stmt = $pdo->prepare("
        SELECT 
            vli.item_name,
            vli.id as item_id,
            SUM(CASE WHEN v.vote_type = 'vote_in' THEN 1 ELSE 0 END) as vote_in_count,
            SUM(CASE WHEN v.vote_type = 'vote_out' THEN 1 ELSE 0 END) as vote_out_count,
            COUNT(*) as total_votes,
            ROUND(AVG(CASE WHEN v.vote_type = 'vote_in' THEN 1 ELSE 0 END) * 100, 1) as approval_rate,
            vli.retail_price,
            vli.inventory,
            vl.spin_enabled,
            vl.spin_trigger_count
        FROM votes v
        JOIN voting_list_items vli ON v.item_id = vli.id
        JOIN voting_lists vl ON v.machine_id = vl.id
        WHERE vl.business_id = ? 
        AND v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY vli.id, vli.item_name
        ORDER BY total_votes DESC, approval_rate DESC
        LIMIT 20
    ");
    $stmt->execute([$test_business_id]);
    $voting_data = $stmt->fetchAll();
    echo "   ✅ Success: Found " . count($voting_data) . " voting items\n";
    if (!empty($voting_data)) {
        echo "      First item: " . ($voting_data[0]['item_name'] ?? 'Unknown') . " - " . ($voting_data[0]['total_votes'] ?? 0) . " votes\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Test 7: Machine performance
echo "\n7. Testing machine performance query:\n";
try {
    $stmt = $pdo->prepare("
        SELECT 
            vl.id as machine_id,
            vl.location,
            vl.name as machine_name,
            vl.spin_enabled,
            vl.spin_trigger_count,
            COUNT(DISTINCT v.id) as total_votes,
            COUNT(DISTINCT s.id) as total_sales,
            COALESCE(SUM(s.quantity * s.sale_price), 0) as machine_revenue,
            COUNT(DISTINCT qs.id) as qr_scans,
            sw.id as has_spin_wheel,
            pt.id as has_pizza_tracker
        FROM voting_lists vl
        LEFT JOIN votes v ON vl.id = v.machine_id AND v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        LEFT JOIN sales s ON vl.id = s.machine_id AND s.sale_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        LEFT JOIN qr_codes qrc ON vl.id = qrc.machine_id
        LEFT JOIN qr_scans qs ON qrc.id = qs.qr_code_id AND qs.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        LEFT JOIN spin_wheels sw ON vl.id = sw.voting_list_id
        LEFT JOIN pizza_trackers pt ON vl.id = pt.voting_list_id
        WHERE vl.business_id = ?
        GROUP BY vl.id, vl.location, vl.name
        ORDER BY machine_revenue DESC
    ");
    $stmt->execute([$test_business_id]);
    $machine_data = $stmt->fetchAll();
    echo "   ✅ Success: Found " . count($machine_data) . " machines\n";
    if (!empty($machine_data)) {
        echo "      First machine: " . ($machine_data[0]['machine_name'] ?? 'Unknown') . " - $" . number_format($machine_data[0]['machine_revenue'] ?? 0, 2) . "\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Exception Debug Complete ===\n";
?> 