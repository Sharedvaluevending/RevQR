<?php
require_once 'html/core/config.php';
require_once 'html/core/ai_assistant.php';

echo "=== SIMPLIFIED ANALYTICS TEST ===\n\n";

class TestAIAssistant extends AIAssistant {
    public function getSimplifiedAnalytics($business_id, $pdo) {
        $analytics = [
            'revenue_trend' => 0,
            'total_sales' => 0,
            'avg_sale_value' => 0,
            'date_range' => 'last 30 days'
        ];
        
        try {
            echo "Adding casino participation...\n";
            $stmt = $pdo->prepare("SELECT * FROM business_casino_participation WHERE business_id = ?");
            $stmt->execute([$business_id]);
            $analytics['casino_participation'] = $stmt->fetch() ?: [];
            echo "✅ Casino participation added\n";
            
            echo "Adding casino revenue...\n";
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
            echo "✅ Casino revenue added\n";
            
            echo "Adding promotional ads...\n";
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
            echo "✅ Promotional ads added\n";
            
            echo "Adding spin wheels...\n";
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
            echo "✅ Spin wheels added\n";
            
            echo "Adding pizza trackers (simplified)...\n";
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
            $analytics['pizza_trackers'] = $stmt->fetchAll();
            echo "✅ Pizza trackers added\n";
            
            echo "Adding QR performance...\n";
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
            $analytics['qr_performance'] = $stmt->fetchAll();
            echo "✅ QR performance added\n";
            
            // Set some required fields
            $analytics['low_stock_count'] = 0;
            $analytics['low_stock_items'] = [];
            $analytics['optimization_score'] = 50; // Default score for testing
            
        } catch (Exception $e) {
            echo "❌ EXCEPTION: " . $e->getMessage() . "\n";
            echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
            return $analytics;
        }
        
        return $analytics;
    }
}

$testAI = new TestAIAssistant();
$business_id = 1;

$analytics = $testAI->getSimplifiedAnalytics($business_id, $pdo);

echo "\nFinal analytics check:\n";
echo "- Casino participation: " . (!empty($analytics['casino_participation']) ? "YES" : "NO") . "\n";
echo "- Promotional ads: " . count($analytics['promotional_ads'] ?? []) . " records\n";
echo "- Spin wheels: " . count($analytics['spin_wheels'] ?? []) . " records\n";
echo "- Pizza trackers: " . count($analytics['pizza_trackers'] ?? []) . " records\n";
echo "- QR performance: " . count($analytics['qr_performance'] ?? []) . " records\n";

if (!empty($analytics['casino_participation']) || count($analytics['promotional_ads']) > 0) {
    echo "\n✅ Testing insights generation...\n";
    $insights = $testAI->generateInsights($analytics);
    echo "Generated insights: " . count($insights['recommendations']) . "\n";
    
    foreach ($insights['recommendations'] as $i => $insight) {
        echo "  " . ($i + 1) . ". {$insight['title']}\n";
    }
} else {
    echo "\n❌ No feature data available for insights\n";
}

echo "\n=== TEST COMPLETE ===\n";
?> 