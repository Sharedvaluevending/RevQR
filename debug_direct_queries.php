<?php
require_once 'html/core/config.php';

echo "=== DIRECT QUERY DEBUG ===\n\n";

$business_id = 1;

echo "Testing business_id: $business_id\n\n";

// Test 1: Casino participation
echo "1. Casino participation query:\n";
$stmt = $pdo->prepare("SELECT * FROM business_casino_participation WHERE business_id = ?");
$stmt->execute([$business_id]);
$casino_data = $stmt->fetch();
if ($casino_data) {
    echo "✅ Found casino data: casino_enabled = " . $casino_data['casino_enabled'] . "\n";
    var_dump($casino_data);
} else {
    echo "❌ No casino data found\n";
}

echo "\n2. Promotional ads query:\n";
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
$promo_data = $stmt->fetchAll();
if ($promo_data) {
    echo "✅ Found promotional ads data:\n";
    foreach ($promo_data as $row) {
        echo "   Type: {$row['feature_type']}, Ads: {$row['total_ads']}, Views: {$row['total_views']}\n";
    }
} else {
    echo "❌ No promotional ads data found\n";
}

echo "\n3. Spin wheels query:\n";
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
$spin_data = $stmt->fetchAll();
if ($spin_data) {
    echo "✅ Found spin wheels data:\n";
    foreach ($spin_data as $row) {
        echo "   Wheel: {$row['name']}, Spins: {$row['total_spins']}\n";
    }
} else {
    echo "❌ No spin wheels data found\n";
}

echo "\n4. Pizza trackers query:\n";
$stmt = $pdo->prepare("
    SELECT 
        pt.*,
        ROUND((pt.current_revenue / pt.revenue_goal) * 100, 1) as progress_percent,
        (pt.revenue_goal - pt.current_revenue) as remaining_amount,
        CASE WHEN pt.current_revenue >= pt.revenue_goal THEN 1 ELSE 0 END as is_complete
    FROM pizza_trackers pt
    WHERE pt.business_id = ?
");
$stmt->execute([$business_id]);
$pizza_data = $stmt->fetchAll();
if ($pizza_data) {
    echo "✅ Found pizza trackers data:\n";
    foreach ($pizza_data as $row) {
        echo "   Tracker: {$row['name']}, Progress: {$row['progress_percent']}%\n";
    }
} else {
    echo "❌ No pizza trackers data found\n";
}

echo "\n5. QR performance query:\n";
$stmt = $pdo->prepare("
    SELECT 
        qr.qr_type,
        COUNT(qr.id) as total_qr_codes,
        COUNT(qs.id) as total_scans,
        COUNT(CASE WHEN qs.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as recent_scans
    FROM qr_codes qr
    LEFT JOIN qr_scans qs ON qr.id = qs.qr_code_id
    WHERE qr.business_id = ?
    GROUP BY qr.qr_type
");
$stmt->execute([$business_id]);
$qr_data = $stmt->fetchAll();
if ($qr_data) {
    echo "✅ Found QR performance data:\n";
    foreach ($qr_data as $row) {
        echo "   Type: {$row['qr_type']}, Codes: {$row['total_qr_codes']}, Scans: {$row['total_scans']}\n";
    }
} else {
    echo "❌ No QR performance data found\n";
}

echo "\n=== TEST COMPLETE ===\n";
?> 