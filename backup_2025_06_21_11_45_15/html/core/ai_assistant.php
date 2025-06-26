<?php

class AIAssistant {
    private $api_key;
    private $api_url = 'https://api.deepseek.com/chat/completions';
    
    public function __construct() {
        $this->api_key = 'sk-243ea165c22b4c3ca4992c29220a95f1';
    }
    
    /**
     * Get comprehensive business analytics data
     */
    public function getBusinessAnalytics($business_id, $pdo) {
        $analytics = [];
        
        try {
            // First check if there's data in the last 30 days
            $stmt = $pdo->prepare("SELECT COUNT(*) as recent_count FROM sales WHERE business_id = ? AND sale_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
            $stmt->execute([$business_id]);
            $recent_data = $stmt->fetch();
            
            // If no recent data, use last 90 days or all time data
            $date_filter = "";
            $date_range_description = "last 30 days";
            
            if ($recent_data['recent_count'] == 0) {
                // Check for data in last 90 days
                $stmt = $pdo->prepare("SELECT COUNT(*) as data_count FROM sales WHERE business_id = ? AND sale_time >= DATE_SUB(NOW(), INTERVAL 90 DAY)");
                $stmt->execute([$business_id]);
                $medium_data = $stmt->fetch();
                
                if ($medium_data['data_count'] > 0) {
                    $date_filter = "AND sale_time >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
                    $date_range_description = "last 90 days";
                } else {
                    $date_filter = ""; // Use all-time data
                    $date_range_description = "all time";
                }
            } else {
                $date_filter = "AND sale_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            }
            
            // Revenue trends
            $stmt = $pdo->prepare("
                SELECT 
                    SUM(quantity * sale_price) as total_revenue,
                    COUNT(*) as total_sales,
                    AVG(quantity * sale_price) as avg_sale_value
                FROM sales 
                WHERE business_id = ? 
                " . $date_filter . "
            ");
            $stmt->execute([$business_id]);
            $revenue_data = $stmt->fetch();
            
            // Calculate weekly revenue (adjust based on date range)
            $weeks_divisor = ($date_range_description == "all time") ? 8 : (($date_range_description == "last 90 days") ? 12 : 4);
            $analytics['revenue_trend'] = ($revenue_data['total_revenue'] ?? 0) / $weeks_divisor;
            $analytics['total_sales'] = $revenue_data['total_sales'] ?? 0;
            $analytics['avg_sale_value'] = $revenue_data['avg_sale_value'] ?? 0;
            $analytics['date_range'] = $date_range_description;
            
            // NEW: Casino participation and performance
            $stmt = $pdo->prepare("SELECT * FROM business_casino_participation WHERE business_id = ?");
            $stmt->execute([$business_id]);
            $analytics['casino_participation'] = $stmt->fetch() ?: [];
            
            // NEW: Casino revenue analytics (if participating)
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
            
            // NEW: Promotional Ads Performance
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
            
            // NEW: Spin Wheel Performance
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
            
            // NEW: Pizza Tracker Performance
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
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY tracker_id
                ) ptc ON pt.id = ptc.tracker_id
                WHERE pt.business_id = ?
            ");
            $stmt->execute([$business_id]);
            $analytics['pizza_trackers'] = $stmt->fetchAll();
            
            // NEW: QR Code Performance by Type
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
            
            // NEW: Campaign Performance with Features
            $stmt = $pdo->prepare("
                SELECT 
                    c.*,
                    COUNT(DISTINCT cvl.voting_list_id) as linked_lists,
                    COUNT(DISTINCT v.id) as total_votes,
                    MAX(sw.id) as has_spin_wheel,
                    MAX(pt.id) as has_pizza_tracker,
                    MAX(pt.progress_percent) as progress_percent
                FROM campaigns c
                LEFT JOIN campaign_voting_lists cvl ON c.id = cvl.campaign_id
                LEFT JOIN votes v ON cvl.voting_list_id = v.machine_id 
                    AND v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                LEFT JOIN spin_wheels sw ON c.id = sw.campaign_id
                LEFT JOIN (
                    SELECT id, campaign_id, 
                           ROUND((current_revenue / revenue_goal) * 100, 1) as progress_percent
                    FROM pizza_trackers 
                    WHERE is_active = 1
                ) pt ON c.id = pt.campaign_id
                WHERE c.business_id = ?
                GROUP BY c.id
            ");
            $stmt->execute([$business_id]);
            $analytics['campaign_performance'] = $stmt->fetchAll();
            
            // Enhanced top selling items by revenue - with actual item names
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
                " . $date_filter . "
                GROUP BY s.item_id
                ORDER BY total_revenue DESC
                LIMIT 15
            ");
            $stmt->execute([$business_id]);
            $analytics['top_items'] = $stmt->fetchAll();
            
            // Get voting data for business machines with engagement metrics
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
                GROUP BY vli.id, vli.item_name, vli.retail_price, vli.inventory, vl.spin_enabled, vl.spin_trigger_count
                ORDER BY total_votes DESC, approval_rate DESC
                LIMIT 20
            ");
            $stmt->execute([$business_id]);
            $analytics['voting_data'] = $stmt->fetchAll();
            
            // Enhanced machine performance data with engagement features
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
                    MAX(sw.id) as has_spin_wheel,
                    MAX(pt.id) as has_pizza_tracker
                FROM voting_lists vl
                LEFT JOIN votes v ON vl.id = v.machine_id AND v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                LEFT JOIN sales s ON vl.id = s.machine_id AND s.sale_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                LEFT JOIN qr_codes qrc ON vl.id = qrc.machine_id
                LEFT JOIN qr_scans qs ON qrc.id = qs.qr_code_id AND qs.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                LEFT JOIN spin_wheels sw ON vl.name = sw.machine_name
                LEFT JOIN pizza_trackers pt ON vl.id = pt.machine_id
                WHERE vl.business_id = ?
                GROUP BY vl.id, vl.location, vl.name, vl.spin_enabled, vl.spin_trigger_count
                ORDER BY machine_revenue DESC
            ");
            $stmt->execute([$business_id]);
            $analytics['machine_performance'] = $stmt->fetchAll();
            
            // Low stock items with enhanced context
            $stmt = $pdo->prepare("
                SELECT 
                    vli.item_name,
                    vli.inventory as current_stock,
                    vli.retail_price,
                    vli.cost_price,
                    (vli.retail_price - COALESCE(vli.cost_price, 0)) as profit_margin,
                    vl.name as machine_name,
                    vl.location,
                    sales_data.total_sold_30d,
                    vote_data.approval_rate
                FROM voting_list_items vli
                JOIN voting_lists vl ON vli.voting_list_id = vl.id
                LEFT JOIN (
                    SELECT item_id, SUM(quantity) as total_sold_30d
                    FROM sales 
                    WHERE business_id = ? AND sale_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY item_id
                ) sales_data ON vli.id = sales_data.item_id
                LEFT JOIN (
                                         SELECT item_id, 
                           ROUND(AVG(CASE WHEN vote_type = 'vote_in' THEN 1 ELSE 0 END) * 100, 1) as approval_rate
                    FROM votes v
                    JOIN voting_lists vl ON v.machine_id = vl.id
                    WHERE vl.business_id = ? AND v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY item_id
                ) vote_data ON vli.id = vote_data.item_id
                WHERE vl.business_id = ? 
                AND vli.inventory < 10
                ORDER BY vli.inventory ASC, sales_data.total_sold_30d DESC
            ");
            $stmt->execute([$business_id, $business_id, $business_id]);
            $analytics['low_stock_items'] = $stmt->fetchAll();
            $analytics['low_stock_count'] = count($analytics['low_stock_items']);
            
            // Daily sales trends with engagement metrics
            $stmt = $pdo->prepare("
                SELECT 
                    DATE(sale_time) as sale_date,
                    SUM(quantity * sale_price) as daily_revenue,
                    COUNT(*) as daily_sales,
                    COUNT(DISTINCT machine_id) as active_machines
                FROM sales 
                WHERE business_id = ? 
                " . $date_filter . "
                GROUP BY DATE(sale_time)
                ORDER BY DATE(sale_time) DESC
                LIMIT 30
            ");
            $stmt->execute([$business_id]);
            $daily_trends = $stmt->fetchAll();
            
            // Add day names and engagement data
            foreach ($daily_trends as &$trend) {
                $trend['day_name'] = date('l', strtotime($trend['sale_date']));
                
                // Get votes for this day
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as daily_votes 
                    FROM votes v
                    JOIN voting_lists vl ON v.machine_id = vl.id
                    WHERE vl.business_id = ? 
                    AND DATE(v.created_at) = ?
                ");
                $stmt->execute([$business_id, $trend['sale_date']]);
                $vote_data = $stmt->fetch();
                $trend['daily_votes'] = $vote_data['daily_votes'] ?? 0;
            }
            $analytics['daily_trends'] = $daily_trends;
            
            // Enhanced item performance analysis with all new features
            $sales_date_filter = str_replace("AND sale_time", "AND s.sale_time", $date_filter);
            $stmt = $pdo->prepare("
                SELECT 
                    vli.item_name,
                    vli.retail_price as current_price,
                    vli.cost_price,
                    (vli.retail_price - COALESCE(vli.cost_price, 0)) as margin_amount,
                    CASE 
                        WHEN vli.retail_price > 0 THEN ROUND(((vli.retail_price - COALESCE(vli.cost_price, 0)) / vli.retail_price * 100), 2)
                        ELSE 0 
                    END as margin_percentage,
                    vli.retail_price as avg_selling_price,
                    vli.inventory as current_stock,
                    COALESCE(sales_data.total_sold, 0) as total_sold,
                    COALESCE(vote_data.total_votes, 0) as vote_count,
                    COALESCE(vote_data.approval_rate, 0) as customer_approval,
                    vl.name as machine_name,
                    vl.location,
                    vl.spin_enabled,
                    CASE WHEN sales_data.total_sold > 0 AND vli.inventory > 0 
                         THEN ROUND(sales_data.total_sold / (sales_data.total_sold + vli.inventory) * 100, 1)
                         ELSE 0 
                    END as turnover_rate
                FROM voting_list_items vli
                JOIN voting_lists vl ON vli.voting_list_id = vl.id
                LEFT JOIN (
                    SELECT 
                        s.item_id,
                        SUM(s.quantity) as total_sold
                    FROM sales s
                    WHERE s.business_id = ?
                    " . $sales_date_filter . "
                    GROUP BY s.item_id
                ) sales_data ON vli.id = sales_data.item_id
                LEFT JOIN (
                    SELECT 
                        v.item_id,
                        COUNT(*) as total_votes,
                        ROUND(AVG(CASE WHEN v.vote_type = 'vote_in' THEN 1 ELSE 0 END) * 100, 1) as approval_rate
                    FROM votes v
                    JOIN voting_lists vl ON v.machine_id = vl.id
                    WHERE vl.business_id = ?
                    AND v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY v.item_id
                ) vote_data ON vli.id = vote_data.item_id
                WHERE vl.business_id = ?
                GROUP BY vli.id, vli.item_name, vli.retail_price, vli.cost_price, vli.inventory, vl.name, vl.location, vl.spin_enabled
                ORDER BY COALESCE(sales_data.total_sold, 0) DESC
                LIMIT 25
            ");
            $stmt->execute([$business_id, $business_id, $business_id]);
            $analytics['item_performance'] = $stmt->fetchAll();
            
            // Price competitiveness analysis
            $stmt = $pdo->prepare("
                SELECT 
                    vli.item_name,
                    vli.retail_price,
                    vli.cost_price,
                    AVG(vli2.retail_price) as market_avg_price,
                    COUNT(vli2.id) as competitors_count
                FROM voting_list_items vli
                JOIN voting_lists vl ON vli.voting_list_id = vl.id
                LEFT JOIN voting_list_items vli2 ON vli.item_name = vli2.item_name AND vli.id != vli2.id
                WHERE vl.business_id = ?
                GROUP BY vli.id, vli.item_name, vli.retail_price, vli.cost_price
                HAVING COUNT(vli2.id) > 0
                ORDER BY vli.retail_price DESC
            ");
            $stmt->execute([$business_id]);
            $analytics['price_analysis'] = $stmt->fetchAll();
            
            // Calculate optimization score
            $analytics['optimization_score'] = $this->calculateOptimizationScore($analytics);
            
        } catch (Exception $e) {
            error_log("Error getting business analytics: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            // Return default analytics
            $analytics = [
                'revenue_trend' => 0,
                'total_sales' => 0,
                'avg_sale_value' => 0,
                'low_stock_count' => 0,
                'low_stock_items' => [],
                'top_items' => [],
                'daily_trends' => [],
                'item_performance' => [],
                'voting_data' => [],
                'machine_performance' => [],
                'price_analysis' => [],
                'optimization_score' => 50,
                'date_range' => 'last 30 days',
                'error_message' => $e->getMessage() // Add error for debugging
            ];
        }
        
        return $analytics;
    }
    
    /**
     * Generate AI insights based on analytics data
     */
    public function generateInsights($analytics) {
        $insights = [
            'recommendations' => [],
            'sales_opportunities' => []
        ];
        
        // NEW: Casino Participation Insights
        if (!empty($analytics['casino_participation'])) {
            $casino_data = $analytics['casino_participation'];
            if (!$casino_data['casino_enabled']) {
                $insights['recommendations'][] = [
                    'title' => '🎰 Untapped Casino Revenue Stream',
                    'description' => 'Enable casino participation to earn 10% of all QR Coins spent by customers at your location. Zero setup, automatic revenue.',
                    'action' => 'Go to Settings → Casino Participation and enable casino features',
                    'impact' => 'Potential $200-800+ monthly passive revenue from customer casino play',
                    'priority' => 'medium',
                    'icon' => 'bi-piggy-bank',
                    'color' => 'success'
                ];
            } else {
                // Casino is enabled, analyze performance
                $casino_revenue = $analytics['casino_revenue'] ?? ['total_casino_revenue' => 0, 'total_plays' => 0];
                if ($casino_revenue['total_casino_revenue'] > 0) {
                    $insights['recommendations'][] = [
                        'title' => '🎰 Casino Revenue Success',
                        'description' => "Great! You've earned $" . number_format($casino_revenue['total_casino_revenue'], 2) . " from {$casino_revenue['total_plays']} casino plays this month.",
                        'action' => 'Consider creating promotional ads to drive more casino traffic',
                        'impact' => 'Could increase casino revenue by 25-50% with targeted promotion',
                        'priority' => 'medium',
                        'icon' => 'bi-graph-up-arrow',
                        'color' => 'success'
                    ];
                } else {
                    $insights['recommendations'][] = [
                        'title' => '🎰 Casino Feature Underutilized',
                        'description' => 'Casino is enabled but generating no revenue. Customers may not know about it.',
                        'action' => 'Create promotional ads and display casino QR codes prominently',
                        'impact' => 'Drive awareness and capture potential casino revenue',
                        'priority' => 'medium',
                        'icon' => 'bi-megaphone',
                        'color' => 'warning'
                    ];
                }
            }
        }
        
        // NEW: Promotional Ads Performance
        if (!empty($analytics['promotional_ads'])) {
            $total_ads = array_sum(array_column($analytics['promotional_ads'], 'total_ads'));
            $active_ads = array_sum(array_column($analytics['promotional_ads'], 'active_ads'));
            $total_views = array_sum(array_column($analytics['promotional_ads'], 'total_views'));
            $total_clicks = array_sum(array_column($analytics['promotional_ads'], 'total_clicks'));
            
            if ($total_ads == 0) {
                $insights['recommendations'][] = [
                    'title' => '📢 Missing Promotional Opportunities',
                    'description' => 'You haven\'t created any promotional ads yet. Drive traffic to your features with targeted user promotions.',
                    'action' => 'Visit Promotional Ads Manager and create your first campaign',
                    'impact' => 'Increase feature engagement by 30-60% with promotional visibility',
                    'priority' => 'medium',
                    'icon' => 'bi-megaphone-fill',
                    'color' => 'info'
                ];
            } else {
                $ctr = $total_views > 0 ? round(($total_clicks / $total_views) * 100, 1) : 0;
                if ($ctr < 2) {
                    $insights['recommendations'][] = [
                        'title' => '📢 Low Ad Performance',
                        'description' => "Your promotional ads have a {$ctr}% click rate. Industry average is 3-5%.",
                        'action' => 'Improve ad copy, adjust targeting, or try different call-to-action buttons',
                        'impact' => 'Higher CTR = more feature engagement and revenue',
                        'priority' => 'medium',
                        'icon' => 'bi-graph-down',
                        'color' => 'warning'
                    ];
                } elseif ($ctr > 5) {
                    $insights['recommendations'][] = [
                        'title' => '📢 Excellent Ad Performance!',
                        'description' => "Outstanding! Your ads have a {$ctr}% click rate with {$total_views} views and {$total_clicks} clicks.",
                        'action' => 'Consider increasing ad budgets or creating more campaigns',
                        'impact' => 'Scale successful campaigns for even more engagement',
                        'priority' => 'low',
                        'icon' => 'bi-trophy',
                        'color' => 'success'
                    ];
                }
            }
        }
        
        // NEW: Spin Wheel Insights
        if (!empty($analytics['spin_wheels'])) {
            foreach ($analytics['spin_wheels'] as $wheel) {
                if ($wheel['total_spins'] > 0) {
                    $win_rate = $wheel['total_wins'] > 0 ? round(($wheel['total_wins'] / $wheel['total_spins']) * 100, 1) : 0;
                    if ($win_rate < 10) {
                        $insights['recommendations'][] = [
                            'title' => '🎡 Spin Wheel Needs Tuning',
                            'description' => "'{$wheel['name']}' has only {$win_rate}% win rate from {$wheel['total_spins']} spins. Low win rates discourage engagement.",
                            'action' => 'Increase win rates to 15-25% or add more small prizes',
                            'impact' => 'Better engagement and repeat customers',
                            'priority' => 'medium',
                            'icon' => 'bi-gear',
                            'color' => 'warning'
                        ];
                    }
                } else {
                    $insights['recommendations'][] = [
                        'title' => '🎡 Unused Spin Wheel',
                        'description' => "'{$wheel['name']}' has received no spins yet.",
                        'action' => 'Promote the spin wheel or check if it\'s properly linked to campaigns',
                        'impact' => 'Activate this engagement tool to boost customer interaction',
                        'priority' => 'low',
                        'icon' => 'bi-question-circle',
                        'color' => 'info'
                    ];
                }
            }
        }
        
        // NEW: Pizza Tracker Insights
        if (!empty($analytics['pizza_trackers'])) {
            foreach ($analytics['pizza_trackers'] as $tracker) {
                if ($tracker['is_complete']) {
                    $insights['recommendations'][] = [
                        'title' => '🍕 Pizza Goal Achieved!',
                        'description' => "'{$tracker['name']}' reached its $" . number_format($tracker['revenue_goal'], 0) . " goal! Great customer engagement.",
                        'action' => 'Reset tracker for another round or create a new challenge',
                        'impact' => 'Maintain momentum with fresh goals',
                        'priority' => 'low',
                        'icon' => 'bi-check-circle',
                        'color' => 'success'
                    ];
                } elseif ($tracker['progress_percent'] < 25 && $tracker['created_at'] < date('Y-m-d', strtotime('-30 days'))) {
                    $insights['recommendations'][] = [
                        'title' => '🍕 Slow Pizza Progress',
                        'description' => "'{$tracker['name']}' is only {$tracker['progress_percent']}% complete after 30+ days.",
                        'action' => 'Lower the goal, increase promotion, or add incentives',
                        'impact' => 'Re-energize customer participation',
                        'priority' => 'medium',
                        'icon' => 'bi-speedometer2',
                        'color' => 'warning'
                    ];
                } elseif ($tracker['progress_percent'] > 75) {
                    $insights['recommendations'][] = [
                        'title' => '🍕 Pizza Goal Almost There!',
                        'description' => "'{$tracker['name']}' is {$tracker['progress_percent']}% complete! Just $" . number_format($tracker['remaining_amount'], 2) . " to go.",
                        'action' => 'Push promotion to cross the finish line',
                        'impact' => 'Complete the goal and celebrate success',
                        'priority' => 'medium',
                        'icon' => 'bi-flag',
                        'color' => 'info'
                    ];
                }
            }
        }
        
        // NEW: QR Code Performance Analysis
        if (!empty($analytics['qr_performance'])) {
            $qr_insights = [];
            foreach ($analytics['qr_performance'] as $qr_type) {
                if ($qr_type['total_scans'] == 0 && $qr_type['total_qr_codes'] > 0) {
                    $qr_insights[] = $qr_type['qr_type'];
                }
            }
            
            if (!empty($qr_insights)) {
                $insights['recommendations'][] = [
                    'title' => '📱 Unused QR Codes',
                    'description' => 'You have QR codes for ' . implode(', ', $qr_insights) . ' that haven\'t been scanned.',
                    'action' => 'Check QR code placement, size, and visibility at your locations',
                    'impact' => 'Increase customer engagement and feature usage',
                    'priority' => 'medium',
                    'icon' => 'bi-qr-code',
                    'color' => 'info'
                ];
            }
        }
        
        // Enhanced stock recommendations with specific item details
        if ($analytics['low_stock_count'] > 0) {
            $low_stock_details = array_slice($analytics['low_stock_items'], 0, 3);
            $item_list = implode(', ', array_map(function($item) {
                return $item['item_name'] . ' (' . $item['current_stock'] . ' left)';
            }, $low_stock_details));
            
            $insights['recommendations'][] = [
                'title' => 'Critical Stock Alert',
                'description' => "Urgent: {$analytics['low_stock_count']} items are running low. Priority items: {$item_list}",
                'action' => 'Restock these items immediately to prevent lost sales',
                'impact' => 'Prevent up to $' . number_format(array_sum(array_map(function($item) {
                    return $item['retail_price'] * 10; // Estimate 10 lost sales per item
                }, $low_stock_details)), 2) . ' in lost revenue',
                'priority' => 'high',
                'icon' => 'bi-exclamation-triangle',
                'color' => 'danger'
            ];
        }
        
        // Voting-based customer preference insights
        if (!empty($analytics['voting_data'])) {
            $high_approval_items = array_filter($analytics['voting_data'], function($item) {
                return $item['approval_rate'] >= 70 && $item['total_votes'] >= 5;
            });
            
            if (!empty($high_approval_items)) {
                $top_approved = array_slice($high_approval_items, 0, 2);
                $item_names = implode(' and ', array_map(function($item) {
                    return $item['item_name'] . ' (' . $item['approval_rate'] . '% approval)';
                }, $top_approved));
                
                $insights['recommendations'][] = [
                    'title' => 'Customer Favorites Identified',
                    'description' => "Customers love: {$item_names}. These items have high approval ratings from recent votes.",
                    'action' => 'Increase stock and consider featuring these items prominently',
                    'impact' => 'Potential 20-30% increase in sales for these items',
                    'priority' => 'medium',
                    'icon' => 'bi-heart',
                    'color' => 'success'
                ];
            }
            
            // Identify unpopular items
            $low_approval_items = array_filter($analytics['voting_data'], function($item) {
                return $item['approval_rate'] < 40 && $item['total_votes'] >= 5;
            });
            
            if (!empty($low_approval_items)) {
                $unpopular = array_slice($low_approval_items, 0, 2);
                $unpopular_names = implode(' and ', array_map(function($item) {
                    return $item['item_name'] . ' (' . $item['approval_rate'] . '% approval)';
                }, $unpopular));
                
                $insights['recommendations'][] = [
                    'title' => 'Consider Replacing Unpopular Items',
                    'description' => "Customer feedback shows low satisfaction with: {$unpopular_names}",
                    'action' => 'Consider replacing these items or running promotions to clear inventory',
                    'impact' => 'Free up space for more popular, profitable items',
                    'priority' => 'medium',
                    'icon' => 'bi-arrow-repeat',
                    'color' => 'warning'
                ];
            }
        }
        
        // Machine performance insights
        if (!empty($analytics['machine_performance'])) {
            $top_machine = $analytics['machine_performance'][0];
            $bottom_machines = array_filter($analytics['machine_performance'], function($machine) {
                return $machine['machine_revenue'] < 100; // Low revenue threshold
            });
            
            if ($top_machine['machine_revenue'] > 0) {
                $insights['recommendations'][] = [
                    'title' => 'Top Performing Location: ' . $top_machine['location'],
                    'description' => "Your {$top_machine['location']} machine generated $" . number_format($top_machine['machine_revenue'], 2) . " this month. Apply successful strategies from this location to others.",
                    'action' => 'Analyze what makes this location successful and replicate the strategy',
                    'impact' => 'Potential to boost other locations by 15-25%',
                    'priority' => 'medium',
                    'icon' => 'bi-geo-alt',
                    'color' => 'info'
                ];
            }
            
            if (!empty($bottom_machines)) {
                $low_performers = array_slice($bottom_machines, 0, 2);
                $location_list = implode(', ', array_map(function($machine) {
                    return $machine['location'];
                }, $low_performers));
                
                $insights['recommendations'][] = [
                    'title' => 'Underperforming Locations Need Attention',
                    'description' => "These locations have low revenue: {$location_list}. They may need different product mix or repositioning.",
                    'action' => 'Review product selection and consider relocating or changing inventory',
                    'impact' => 'Improve revenue by $200-500 per month per location',
                    'priority' => 'medium',
                    'icon' => 'bi-exclamation-circle',
                    'color' => 'warning'
                ];
            }
        }
        
        // Price optimization based on market analysis
        if (!empty($analytics['price_analysis'])) {
            $overpriced_items = array_filter($analytics['price_analysis'], function($item) {
                return $item['retail_price'] > ($item['market_avg_price'] * 1.2) && $item['market_avg_price'] > 0;
            });
            
            $underpriced_items = array_filter($analytics['price_analysis'], function($item) {
                return $item['retail_price'] < ($item['market_avg_price'] * 0.8) && $item['market_avg_price'] > 0;
            });
            
            if (!empty($underpriced_items)) {
                $underpriced = array_slice($underpriced_items, 0, 2);
                $item_details = implode(', ', array_map(function($item) {
                    return $item['item_name'] . ' ($' . number_format($item['retail_price'], 2) . ' vs $' . number_format($item['market_avg_price'], 2) . ' avg)';
                }, $underpriced));
                
                $insights['recommendations'][] = [
                    'title' => 'Price Increase Opportunity',
                    'description' => "These items are priced below market average: {$item_details}",
                    'action' => 'Consider gradual price increases to market levels',
                    'impact' => 'Potential $' . number_format(array_sum(array_map(function($item) {
                        return ($item['market_avg_price'] - $item['retail_price']) * 50; // Estimate 50 sales per month
                    }, $underpriced)), 2) . ' additional monthly revenue',
                    'priority' => 'high',
                    'icon' => 'bi-arrow-up-circle',
                    'color' => 'success'
                ];
            }
        }
        
        // High-margin item promotion opportunities
        if (!empty($analytics['item_performance'])) {
            $high_margin_items = array_filter($analytics['item_performance'], function($item) {
                return $item['margin_percentage'] > 60 && $item['current_stock'] > 0;
            });
            
            if (!empty($high_margin_items)) {
                $top_margin = array_slice($high_margin_items, 0, 3);
                $margin_details = implode(', ', array_map(function($item) {
                    return $item['item_name'] . ' (' . $item['margin_percentage'] . '% margin)';
                }, $top_margin));
                
                $insights['recommendations'][] = [
                    'title' => 'Promote High-Margin Winners',
                    'description' => "Focus marketing on these profitable items: {$margin_details}",
                    'action' => 'Create promotions and prominent placement for high-margin items',
                    'impact' => 'Increase profit margins by 10-15% overall',
                    'priority' => 'medium',
                    'icon' => 'bi-trophy',
                    'color' => 'warning'
                ];
            }
        }
        
        // Generate enhanced sales opportunities based on actual business data
        $insights['sales_opportunities'] = $this->generateAdvancedSalesOpportunities($analytics);
        
        return $insights;
    }
    
    /**
     * Generate advanced sales opportunities using actual business data
     */
    private function generateAdvancedSalesOpportunities($analytics) {
        $opportunities = [];
        
        // 1. Customer-Driven Bundle Opportunities
        if (!empty($analytics['voting_data'])) {
            $popular_items = array_filter($analytics['voting_data'], function($item) {
                return $item['approval_rate'] >= 70 && $item['total_votes'] >= 5;
            });
            
            if (count($popular_items) >= 2) {
                $top_items = array_slice($popular_items, 0, 2);
                $item_names = implode(' + ', array_column($top_items, 'item_name'));
                $avg_approval = count($top_items) > 0 ? round(array_sum(array_column($top_items, 'approval_rate')) / count($top_items), 1) : 0;
                
                $opportunities[] = [
                    'title' => 'High-Approval Bundle Strategy',
                    'description' => "Create combo: {$item_names} (average {$avg_approval}% customer approval)",
                    'revenue_increase' => 25,
                    'difficulty' => 'Easy',
                    'data_driven' => true,
                    'action' => "Bundle these customer favorites with 10-15% discount"
                ];
            }
        }
        
        // 2. Location Performance Optimization
        if (!empty($analytics['machine_performance']) && count($analytics['machine_performance']) >= 2) {
            $top_location = $analytics['machine_performance'][0];
            $bottom_locations = array_filter($analytics['machine_performance'], function($machine) use ($top_location) {
                return $machine['machine_revenue'] < ($top_location['machine_revenue'] * 0.5);
            });
            
            if (!empty($bottom_locations) && $top_location['machine_revenue'] > 0) {
                $underperforming = array_slice($bottom_locations, 0, 2);
                $location_names = implode(', ', array_column($underperforming, 'location'));
                $revenue_gap = isset($underperforming[0]['machine_revenue']) ? 
                    ($top_location['machine_revenue'] - $underperforming[0]['machine_revenue']) : 
                    $top_location['machine_revenue'];
                
                $opportunities[] = [
                    'title' => 'Location Revenue Gap Analysis',
                    'description' => "Optimize {$location_names} using {$top_location['location']} strategy",
                    'revenue_increase' => $top_location['machine_revenue'] > 0 ? round(($revenue_gap / $top_location['machine_revenue']) * 100) : 0,
                    'difficulty' => 'Medium',
                    'data_driven' => true,
                    'action' => "Replicate top location's product mix and positioning"
                ];
            }
        }
        
        // 3. Price Optimization Opportunities
        if (!empty($analytics['price_analysis'])) {
            $underpriced_items = array_filter($analytics['price_analysis'], function($item) {
                return $item['retail_price'] < ($item['market_avg_price'] * 0.8) && $item['market_avg_price'] > 0;
            });
            
            if (!empty($underpriced_items)) {
                $underpriced_items_indexed = array_values($underpriced_items);
                if (isset($underpriced_items_indexed[0])) {
                    $item = $underpriced_items_indexed[0];
                    $price_increase = ($item['market_avg_price'] ?? 0) - ($item['retail_price'] ?? 0);
                    $monthly_potential = $price_increase * 50; // Estimate 50 sales per month
                    
                    $opportunities[] = [
                        'title' => 'Strategic Price Increase',
                        'description' => "Increase {$item['item_name']} from \${$item['retail_price']} to \${$item['market_avg_price']} (market rate)",
                        'revenue_increase' => ($item['retail_price'] ?? 0) > 0 ? round(($price_increase / $item['retail_price']) * 100) : 0,
                        'difficulty' => 'Easy',
                        'data_driven' => true,
                        'action' => "Potential \${$monthly_potential}/month additional revenue"
                    ];
                }
            }
        }
        
        // 4. Inventory Turnover Optimization
        if (!empty($analytics['item_performance'])) {
            $slow_movers = array_filter($analytics['item_performance'], function($item) {
                return $item['current_stock'] > 10 && $item['total_sold'] < 3;
            });
            $fast_movers = array_filter($analytics['item_performance'], function($item) {
                return $item['total_sold'] > 5 && $item['current_stock'] < 5;
            });
            
            if (!empty($slow_movers) && !empty($fast_movers)) {
                $slow_movers_indexed = array_values($slow_movers);
                $fast_movers_indexed = array_values($fast_movers);
                if (isset($slow_movers_indexed[0]) && isset($fast_movers_indexed[0])) {
                    $slow_item = $slow_movers_indexed[0]['item_name'] ?? 'slow-moving item';
                    $fast_item = $fast_movers_indexed[0]['item_name'] ?? 'fast-moving item';
                    
                    $opportunities[] = [
                        'title' => 'Inventory Rebalancing',
                        'description' => "Reduce {$slow_item} stock, increase {$fast_item} inventory",
                        'revenue_increase' => 20,
                        'difficulty' => 'Easy',
                        'data_driven' => true,
                        'action' => "Optimize stock allocation based on sales velocity"
                    ];
                }
            }
        }
        
        // 5. Customer Satisfaction to Sales Conversion
        if (!empty($analytics['voting_data']) && !empty($analytics['item_performance'])) {
            foreach ($analytics['voting_data'] as $voted_item) {
                if (($voted_item['approval_rate'] ?? 0) >= 80) {
                    // Find corresponding sales performance
                    $sales_performance = array_filter($analytics['item_performance'], function($item) use ($voted_item) {
                        return ($item['item_name'] ?? '') === ($voted_item['item_name'] ?? '');
                    });
                    
                    if (!empty($sales_performance)) {
                        $sales_performance_indexed = array_values($sales_performance);
                        if (isset($sales_performance_indexed[0])) {
                            $perf_item = $sales_performance_indexed[0];
                            if (($perf_item['total_sold'] ?? 0) < 5) { // High approval but low sales
                                $opportunities[] = [
                                    'title' => 'High-Satisfaction Low-Sales Boost',
                                    'description' => "{$voted_item['item_name']} has {$voted_item['approval_rate']}% approval but low sales",
                                    'revenue_increase' => 30,
                                    'difficulty' => 'Medium',
                                    'data_driven' => true,
                                    'action' => "Increase visibility, better placement, or promotional pricing"
                                ];
                                break; // Only suggest one of these
                            }
                        }
                    }
                }
            }
        }
        
        // 6. Margin Maximization Strategy
        if (!empty($analytics['item_performance'])) {
            $high_margin_items = array_filter($analytics['item_performance'], function($item) {
                return $item['margin_percentage'] > 60 && $item['current_stock'] > 0;
            });
            
            if (!empty($high_margin_items)) {
                $top_margin = array_slice($high_margin_items, 0, 3);
                $margin_items = implode(', ', array_column($top_margin, 'item_name'));
                $avg_margin = count($top_margin) > 0 ? round(array_sum(array_column($top_margin, 'margin_percentage')) / count($top_margin), 1) : 0;
                
                $opportunities[] = [
                    'title' => 'High-Margin Focus Strategy',
                    'description' => "Promote {$margin_items} (avg {$avg_margin}% margin)",
                    'revenue_increase' => 15,
                    'difficulty' => 'Easy',
                    'data_driven' => true,
                    'action' => "Feature high-margin items prominently to boost profits"
                ];
            }
        }
        
        // Fallback to generic opportunities if no data-driven ones found
        if (empty($opportunities)) {
            $opportunities = [
                [
                    'title' => 'Data Collection Opportunity',
                    'description' => 'Implement customer voting and sales tracking for personalized insights',
                    'revenue_increase' => 10,
                    'difficulty' => 'Medium',
                    'data_driven' => false,
                    'action' => 'Start collecting customer feedback and detailed sales data'
                ],
                [
                    'title' => 'Basic Bundle Strategy',
                    'description' => 'Create simple combo deals to increase transaction value',
                    'revenue_increase' => 15,
                    'difficulty' => 'Easy',
                    'data_driven' => false,
                    'action' => 'Test bundling complementary items with 10% discount'
                ]
            ];
        }
        
        return array_slice($opportunities, 0, 4); // Return top 4 opportunities
    }
    
    /**
     * Calculate business optimization score
     */
    private function calculateOptimizationScore($analytics) {
        $score = 30; // Lower base score to account for new features
        
        // Revenue trend factor (15 points max)
        if ($analytics['revenue_trend'] > 1000) $score += 15;
        elseif ($analytics['revenue_trend'] > 500) $score += 10;
        elseif ($analytics['revenue_trend'] > 200) $score += 5;
        
        // Stock management factor (15 points max)
        if ($analytics['low_stock_count'] == 0) $score += 15;
        elseif ($analytics['low_stock_count'] <= 2) $score += 8;
        elseif ($analytics['low_stock_count'] <= 5) $score += 3;
        else $score -= 5;
        
        // Sales consistency factor (10 points max)
        if ($analytics['total_sales'] > 100) $score += 10;
        elseif ($analytics['total_sales'] > 50) $score += 6;
        elseif ($analytics['total_sales'] > 20) $score += 3;
        
        // NEW: Casino participation (10 points max)
        if (!empty($analytics['casino_participation']) && $analytics['casino_participation']['casino_enabled']) {
            $score += 5; // Basic participation
            if (!empty($analytics['casino_revenue']) && $analytics['casino_revenue']['total_casino_revenue'] > 0) {
                $score += 5; // Actually generating revenue
            }
        }
        
        // NEW: Promotional ads effectiveness (10 points max)
        if (!empty($analytics['promotional_ads'])) {
            $total_ads = array_sum(array_column($analytics['promotional_ads'], 'total_ads'));
            $total_views = array_sum(array_column($analytics['promotional_ads'], 'total_views'));
            $total_clicks = array_sum(array_column($analytics['promotional_ads'], 'total_clicks'));
            
            if ($total_ads > 0) {
                $score += 3; // Has promotional ads
                if ($total_views > 100) {
                    $score += 3; // Good visibility
                    $ctr = ($total_views > 0 && $total_clicks > 0) ? ($total_clicks / $total_views) * 100 : 0;
                    if ($ctr > 3) {
                        $score += 4; // Good performance
                    } elseif ($ctr > 1) {
                        $score += 2;
                    }
                }
            }
        }
        
        // NEW: Engagement features (10 points max)
        $engagement_score = 0;
        if (!empty($analytics['spin_wheels'])) {
            $engagement_score += 3;
            $active_wheels = array_filter($analytics['spin_wheels'], function($wheel) {
                return $wheel['total_spins'] > 0;
            });
            if (count($active_wheels) > 0) {
                $engagement_score += 2;
            }
        }
        if (!empty($analytics['pizza_trackers'])) {
            $engagement_score += 3;
            $active_trackers = array_filter($analytics['pizza_trackers'], function($tracker) {
                return $tracker['progress_percent'] > 0;
            });
            if (count($active_trackers) > 0) {
                $engagement_score += 2;
            }
        }
        $score += min(10, $engagement_score);
        
        // NEW: QR Code utilization (5 points max)
        if (!empty($analytics['qr_performance'])) {
            $total_scans = array_sum(array_column($analytics['qr_performance'], 'total_scans'));
            if ($total_scans > 50) {
                $score += 5;
            } elseif ($total_scans > 20) {
                $score += 3;
            } elseif ($total_scans > 5) {
                $score += 1;
            }
        }
        
        // NEW: Campaign effectiveness (5 points max)
        if (!empty($analytics['campaign_performance'])) {
            $active_campaigns = array_filter($analytics['campaign_performance'], function($campaign) {
                return $campaign['status'] === 'active' && $campaign['total_votes'] > 0;
            });
            if (count($active_campaigns) > 2) {
                $score += 5;
            } elseif (count($active_campaigns) > 0) {
                $score += 3;
            }
        }
        
        // Enhanced item performance (10 points max)
        if (!empty($analytics['item_performance'])) {
            $high_margin_items = array_filter($analytics['item_performance'], function($item) {
                return $item['margin_percentage'] > 50;
            });
            $high_turnover_items = array_filter($analytics['item_performance'], function($item) {
                return isset($item['turnover_rate']) && $item['turnover_rate'] > 70;
            });
            
            if (count($high_margin_items) > 5 && count($high_turnover_items) > 3) {
                $score += 10;
            } elseif (count($high_margin_items) > 3 || count($high_turnover_items) > 2) {
                $score += 6;
            } elseif (count($high_margin_items) > 1 || count($high_turnover_items) > 1) {
                $score += 3;
            }
        }
        
        return min(100, max(0, $score));
    }
    
    /**
     * Send message to AI API for chat functionality
     */
    public function sendChatMessage($message, $business_context = []) {
        $context = "You are an AI assistant for a vending machine business. ";
        $context .= "Help the business owner optimize their operations, increase profits, and make data-driven decisions. ";
        $context .= "Be specific, actionable, and focus on practical business advice. ";
        
        if (!empty($business_context)) {
            $context .= "Business context: ";
            
            if (isset($business_context['date_range'])) {
                $context .= "Data period: " . $business_context['date_range'] . ". ";
            }
            
            if (isset($business_context['revenue']) && $business_context['revenue'] > 0) {
                $context .= "Total revenue: $" . number_format($business_context['revenue'], 2) . ". ";
            }
            
            if (isset($business_context['total_sales']) && $business_context['total_sales'] > 0) {
                $context .= "Total sales transactions: " . $business_context['total_sales'] . ". ";
            }
            
            if (isset($business_context['avg_sale_value']) && $business_context['avg_sale_value'] > 0) {
                $context .= "Average sale value: $" . number_format($business_context['avg_sale_value'], 2) . ". ";
            }
            
            if (isset($business_context['low_stock_count']) && $business_context['low_stock_count'] > 0) {
                $context .= "Items needing restock: " . $business_context['low_stock_count'] . ". ";
                
                if (isset($business_context['low_stock_items']) && !empty($business_context['low_stock_items'])) {
                    $low_stock_details = array_map(function($item) {
                        return $item['item_name'] . " (" . $item['current_stock'] . " left at " . $item['location'] . ")";
                    }, array_slice($business_context['low_stock_items'], 0, 3));
                    $context .= "Critical low stock items: " . implode(', ', $low_stock_details) . ". ";
                }
            }
            
            if (isset($business_context['top_items']) && !empty($business_context['top_items'])) {
                $top_items_info = array_map(function($item) {
                    return $item['item_name'] . " ($" . number_format($item['total_revenue'], 2) . " revenue)";
                }, array_slice($business_context['top_items'], 0, 3));
                $context .= "Top selling items: " . implode(', ', $top_items_info) . ". ";
            }
            
            if (isset($business_context['highest_margin_items']) && !empty($business_context['highest_margin_items'])) {
                $items = array_slice($business_context['highest_margin_items'], 0, 3);
                $margin_info = array_map(function($item) {
                    return $item['item_name'] . " (" . $item['margin_percentage'] . "% margin)";
                }, $items);
                $context .= "Highest margin items: " . implode(', ', $margin_info) . ". ";
            }
            
            // Add voting data context
            if (isset($business_context['voting_data']) && !empty($business_context['voting_data'])) {
                $popular_items = array_filter($business_context['voting_data'], function($item) {
                    return $item['approval_rate'] >= 70;
                });
                $unpopular_items = array_filter($business_context['voting_data'], function($item) {
                    return $item['approval_rate'] < 40;
                });
                
                if (!empty($popular_items)) {
                    $popular_names = array_map(function($item) {
                        return $item['item_name'] . " (" . $item['approval_rate'] . "% approval)";
                    }, array_slice($popular_items, 0, 3));
                    $context .= "Customer favorites (high approval): " . implode(', ', $popular_names) . ". ";
                }
                
                if (!empty($unpopular_items)) {
                    $unpopular_names = array_map(function($item) {
                        return $item['item_name'] . " (" . $item['approval_rate'] . "% approval)";
                    }, array_slice($unpopular_items, 0, 2));
                    $context .= "Items customers dislike: " . implode(', ', $unpopular_names) . ". ";
                }
            }
            
            // Add machine performance context
            if (isset($business_context['machine_performance']) && !empty($business_context['machine_performance'])) {
                $top_machine = $business_context['machine_performance'][0];
                $bottom_machines = array_filter($business_context['machine_performance'], function($machine) {
                    return $machine['machine_revenue'] < 100;
                });
                
                if ($top_machine['machine_revenue'] > 0) {
                    $context .= "Best performing location: " . $top_machine['location'] . " ($" . number_format($top_machine['machine_revenue'], 2) . " revenue). ";
                }
                
                if (!empty($bottom_machines)) {
                    $poor_locations = array_map(function($machine) {
                        return $machine['location'] . " ($" . number_format($machine['machine_revenue'], 2) . ")";
                    }, array_slice($bottom_machines, 0, 2));
                    $context .= "Underperforming locations: " . implode(', ', $poor_locations) . ". ";
                }
            }
            
            // Add price analysis context
            if (isset($business_context['price_analysis']) && !empty($business_context['price_analysis'])) {
                $underpriced = array_filter($business_context['price_analysis'], function($item) {
                    return $item['retail_price'] < ($item['market_avg_price'] * 0.8);
                });
                $overpriced = array_filter($business_context['price_analysis'], function($item) {
                    return $item['retail_price'] > ($item['market_avg_price'] * 1.2);
                });
                
                if (!empty($underpriced)) {
                    $under_names = array_map(function($item) {
                        return $item['item_name'] . " ($" . number_format($item['retail_price'], 2) . " vs $" . number_format($item['market_avg_price'], 2) . " market avg)";
                    }, array_slice($underpriced, 0, 2));
                    $context .= "Underpriced items: " . implode(', ', $under_names) . ". ";
                }
            }
        }
        
        // Determine response type and provide better contextual responses
        $response_type = 'default';
        if (stripos($message, 'pric') !== false) $response_type = 'pricing';
        elseif (stripos($message, 'stock') !== false || stripos($message, 'inventor') !== false) $response_type = 'stock';
        elseif (stripos($message, 'combo') !== false || stripos($message, 'bundle') !== false) $response_type = 'combo';
        elseif (stripos($message, 'sales') !== false || stripos($message, 'revenue') !== false) $response_type = 'sales';
        elseif (stripos($message, 'margin') !== false || stripos($message, 'profit') !== false) $response_type = 'margin';
        elseif (stripos($message, 'vote') !== false || stripos($message, 'customer') !== false) $response_type = 'voting';
        elseif (stripos($message, 'location') !== false || stripos($message, 'machine') !== false) $response_type = 'location';
        
        // Enhanced demo responses with actual business data
        $demo_responses = [
            'pricing' => $this->generatePricingAdvice($business_context),
            'stock' => $this->generateStockAdvice($business_context),
            'combo' => $this->generateComboAdvice($business_context),
            'sales' => $this->generateSalesAdvice($business_context),
            'margin' => $this->generateMarginAdvice($business_context),
            'voting' => $this->generateVotingAdvice($business_context),
            'location' => $this->generateLocationAdvice($business_context),
            'default' => $this->generateGeneralAdvice($business_context)
        ];
        
        // Try API call first, fall back to demo responses
        $data = [
            'model' => 'deepseek-chat',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $context
                ],
                [
                    'role' => 'user',
                    'content' => $message
                ]
            ],
            'max_tokens' => 500,
            'temperature' => 0.7
        ];
        
        $headers = [
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->api_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200 && $response) {
            $decoded = json_decode($response, true);
            if (isset($decoded['choices'][0]['message']['content'])) {
                return [
                    'success' => true,
                    'response' => trim($decoded['choices'][0]['message']['content'])
                ];
            }
        }
        
        // Fallback to enhanced demo response
        return [
            'success' => true,
            'response' => $demo_responses[$response_type]
        ];
    }
    
    private function generateSalesAdvice($business_context) {
        if (empty($business_context) || !isset($business_context['revenue'])) {
            return "To analyze your sales performance, I need access to your recent sales data. Please ensure your system is recording transactions properly.";
        }
        
        $revenue = $business_context['revenue'] ?? 0;
        $sales_count = $business_context['total_sales'] ?? 0;
        $avg_sale = $business_context['avg_sale_value'] ?? 0;
        $date_range = $business_context['date_range'] ?? 'recent period';
        
        $advice = "Based on your {$date_range} data: You've generated $" . number_format($revenue, 2) . " in revenue from {$sales_count} transactions, with an average sale value of $" . number_format($avg_sale, 2) . ". ";
        
        if (!empty($business_context['top_items'])) {
            $top_item = $business_context['top_items'][0];
            $advice .= "Your top performer is {$top_item['item_name']} with $" . number_format($top_item['total_revenue'], 2) . " in revenue. ";
        }
        
        if ($avg_sale < 3.00) {
            $advice .= "Consider bundle deals to increase your average transaction value above $3.00.";
        } elseif ($avg_sale > 5.00) {
            $advice .= "Great average transaction value! Focus on increasing transaction frequency.";
        }
        
        return $advice;
    }
    
    private function generateMarginAdvice($business_context) {
        if (empty($business_context['highest_margin_items'])) {
            return "To provide margin analysis, I need information about your product costs and selling prices.";
        }
        
        $high_margin_items = array_slice($business_context['highest_margin_items'], 0, 3);
        $advice = "Your highest margin items are: ";
        
        foreach ($high_margin_items as $index => $item) {
            if ($index > 0) $advice .= ", ";
            $advice .= $item['item_name'] . " (" . $item['margin_percentage'] . "% margin)";
        }
        
        $advice .= ". Focus on promoting these high-margin items to maximize profitability. Consider placement strategies and promotional campaigns for these products.";
        
        return $advice;
    }
    
    private function generatePricingAdvice($business_context) {
        if (isset($business_context['price_analysis']) && !empty($business_context['price_analysis'])) {
            $underpriced = array_filter($business_context['price_analysis'], function($item) {
                return $item['retail_price'] < ($item['market_avg_price'] * 0.8) && $item['market_avg_price'] > 0;
            });
            $overpriced = array_filter($business_context['price_analysis'], function($item) {
                return $item['retail_price'] > ($item['market_avg_price'] * 1.2) && $item['market_avg_price'] > 0;
            });
            
            $advice = "Price optimization analysis: ";
            
            if (!empty($underpriced)) {
                $under_items = array_slice($underpriced, 0, 2);
                $under_details = array_map(function($item) {
                    return $item['item_name'] . " ($" . number_format($item['retail_price'], 2) . " vs $" . number_format($item['market_avg_price'], 2) . " market avg)";
                }, $under_items);
                $potential_increase = array_sum(array_map(function($item) {
                    return ($item['market_avg_price'] - $item['retail_price']) * 50; // Estimate 50 sales per month
                }, $under_items));
                $advice .= "Underpriced items: " . implode(', ', $under_details) . ". Consider gradual price increases for potential $" . number_format($potential_increase, 2) . " additional monthly revenue. ";
            }
            
            if (!empty($overpriced)) {
                $over_items = array_slice($overpriced, 0, 2);
                $over_details = array_map(function($item) {
                    return $item['item_name'] . " ($" . number_format($item['retail_price'], 2) . " vs $" . number_format($item['market_avg_price'], 2) . " market avg)";
                }, $over_items);
                $advice .= "Overpriced items: " . implode(', ', $over_details) . ". Consider reducing prices to improve competitiveness. ";
            }
            
            if (empty($underpriced) && empty($overpriced)) {
                $advice .= "Your pricing appears competitive relative to market averages. ";
            }
            
            return $advice . "Test 5-10% price adjustments on select items and monitor sales velocity changes.";
        }
        
        return "Based on your current product mix, consider implementing dynamic pricing. Test 5-10% price increases on high-demand items and monitor sales velocity. Bundle slow-moving items with popular ones to maintain overall revenue.";
    }
    
    private function generateStockAdvice($business_context) {
        $stock_count = $business_context['low_stock_count'] ?? 0;
        if ($stock_count > 0) {
            $advice = "You have {$stock_count} items running low on stock. ";
            
            if (isset($business_context['low_stock_items']) && !empty($business_context['low_stock_items'])) {
                $critical_items = array_slice($business_context['low_stock_items'], 0, 3);
                $item_details = array_map(function($item) {
                    return $item['item_name'] . " (" . $item['current_stock'] . " left at " . $item['location'] . ")";
                }, $critical_items);
                $advice .= "Critical items: " . implode(', ', $item_details) . ". ";
                
                $total_potential_loss = array_sum(array_map(function($item) {
                    return $item['retail_price'] * 10; // Estimate 10 lost sales per item
                }, $critical_items));
                
                $advice .= "Prioritize restocking to prevent approximately $" . number_format($total_potential_loss, 2) . " in potential lost revenue. ";
            }
            
            $advice .= "Consider implementing automated reorder points based on sales velocity.";
            return $advice;
        }
        return "Your stock levels look good. Monitor sales velocity and implement automated reorder points to maintain optimal inventory levels.";
    }
    
    private function generateComboAdvice($business_context) {
        $combos = [];
        
        // First, try to use voting data for popular items
        if (!empty($business_context['voting_data'])) {
            $popular_items = array_filter($business_context['voting_data'], function($item) {
                return $item['approval_rate'] >= 60 && $item['total_votes'] >= 3;
            });
            
            if (count($popular_items) >= 2) {
                $popular_sorted = array_slice($popular_items, 0, 4); // Get top 4 popular items
                
                // Create combo recommendations
                $combo1_items = [$popular_sorted[0]['item_name'], $popular_sorted[1]['item_name']];
                $combos[] = "**Combo 1: Customer Favorites Bundle** - " . implode(' + ', $combo1_items) . 
                           " (both have " . $popular_sorted[0]['approval_rate'] . "%+ customer approval). " .
                           "Offer 10% discount on this combo to boost sales of your most loved items.";
                
                if (count($popular_sorted) >= 3) {
                    // Create a second combo with different pairing
                    if (count($popular_sorted) >= 4) {
                        $combo2_items = [$popular_sorted[0]['item_name'], $popular_sorted[2]['item_name']];
                    } else {
                        $combo2_items = [$popular_sorted[1]['item_name'], $popular_sorted[2]['item_name']];
                    }
                    $combos[] = "**Combo 2: High-Approval Mix** - " . implode(' + ', $combo2_items) . 
                               " (complementary popular items). Offer 15% discount to increase average transaction value by 25-30%.";
                }
            }
        }
        
        // Fallback to top selling items if we don't have enough voting data
        if (empty($combos) && !empty($business_context['top_items']) && count($business_context['top_items']) >= 2) {
            $item1 = $business_context['top_items'][0]['item_name'];
            $item2 = $business_context['top_items'][1]['item_name'];
            $combos[] = "**Combo 1: Best Sellers Bundle** - {$item1} + {$item2} (your top revenue generators). Offer 10-15% discount.";
            
            if (count($business_context['top_items']) >= 3) {
                $item3 = $business_context['top_items'][2]['item_name'];
                $combos[] = "**Combo 2: Revenue Booster** - {$item1} + {$item3}. Bundle your #1 seller with #3 to move more inventory.";
            }
        }
        
        // If we have low stock items, suggest clearance combos
        if (!empty($business_context['low_stock_items']) && !empty($business_context['voting_data'])) {
            $low_stock_item = $business_context['low_stock_items'][0]['item_name'];
            $popular_items = array_filter($business_context['voting_data'], function($item) {
                return $item['approval_rate'] >= 70;
            });
            
            if (!empty($popular_items)) {
                $popular_item = $popular_items[0]['item_name'];
                $combos[] = "**Clearance Combo**: {$low_stock_item} + {$popular_item} - Pair your low-stock {$low_stock_item} with popular {$popular_item} at 20% discount to clear inventory.";
            }
        }
        
        if (!empty($combos)) {
            return "Here are 2 specific combo ideas based on your actual inventory and customer preferences:\n\n" . 
                   implode("\n\n", array_slice($combos, 0, 2)) . 
                   "\n\n💡 **Pro tip**: Test these combos for 1-2 weeks and track which combination increases your average transaction value the most.";
        }
        
        return "To create specific combo recommendations, I need more sales data or customer voting information. Consider encouraging customers to vote on items so I can identify the most popular products for combo deals.";
    }
    
    private function generateGeneralAdvice($business_context) {
        if (empty($business_context) || !isset($business_context['revenue'])) {
            return "I'm ready to help optimize your vending machine business! I can analyze sales data, suggest pricing strategies, recommend inventory management, and identify growth opportunities. What specific area would you like me to focus on?";
        }
        
        $revenue = $business_context['revenue'] ?? 0;
        $date_range = $business_context['date_range'] ?? 'recent period';
        
        return "Your vending business has generated $" . number_format($revenue, 2) . " in revenue over the {$date_range}. I can help you optimize pricing, manage inventory, create promotions, and identify growth opportunities. What specific aspect of your business would you like to improve?";
    }
    
    private function generateVotingAdvice($business_context) {
        if (empty($business_context['voting_data'])) {
            return "To provide customer preference insights, I need access to your voting data. Once customers start voting on items, I can help you identify popular products and items that need attention.";
        }
        
        $voting_data = $business_context['voting_data'];
        $popular_items = array_filter($voting_data, function($item) {
            return $item['approval_rate'] >= 70 && $item['total_votes'] >= 5;
        });
        $unpopular_items = array_filter($voting_data, function($item) {
            return $item['approval_rate'] < 40 && $item['total_votes'] >= 5;
        });
        
        $advice = "Based on customer voting data: ";
        
        if (!empty($popular_items)) {
            $top_approved = array_slice($popular_items, 0, 2);
            $popular_names = array_map(function($item) {
                return $item['item_name'] . " (" . $item['approval_rate'] . "% approval)";
            }, $top_approved);
            $advice .= "Customers love: " . implode(' and ', $popular_names) . ". Consider featuring these prominently and increasing stock. ";
        }
        
        if (!empty($unpopular_items)) {
            $disliked = array_slice($unpopular_items, 0, 2);
            $unpopular_names = array_map(function($item) {
                return $item['item_name'] . " (" . $item['approval_rate'] . "% approval)";
            }, $disliked);
            $advice .= "Items needing attention: " . implode(' and ', $unpopular_names) . ". Consider replacing or running clearance promotions.";
        }
        
        if (empty($popular_items) && empty($unpopular_items)) {
            $advice .= "You need more customer votes to identify clear preferences. Consider encouraging more voting through incentives or campaigns.";
        }
        
        return $advice;
    }
    
    private function generateLocationAdvice($business_context) {
        if (empty($business_context['machine_performance'])) {
            return "To provide location-specific advice, I need data about your machine performance across different locations.";
        }
        
        $machines = $business_context['machine_performance'];
        $top_machine = $machines[0];
        $bottom_machines = array_filter($machines, function($machine) {
            return $machine['machine_revenue'] < 100;
        });
        
        $advice = "Location performance analysis: ";
        
        if ($top_machine['machine_revenue'] > 0) {
            $advice .= "Your {$top_machine['location']} location is performing best with $" . number_format($top_machine['machine_revenue'], 2) . " revenue. ";
            
            if (count($machines) > 1) {
                $advice .= "Study what makes this location successful - foot traffic, product mix, pricing, or positioning - and apply these strategies to other locations. ";
            }
        }
        
        if (!empty($bottom_machines)) {
            $poor_performers = array_slice($bottom_machines, 0, 2);
            $location_details = array_map(function($machine) {
                return $machine['location'] . " ($" . number_format($machine['machine_revenue'], 2) . ")";
            }, $poor_performers);
            $advice .= "Underperforming locations need attention: " . implode(', ', $location_details) . ". Consider different product selections, repositioning machines, or promotional campaigns.";
        }
        
        return $advice;
    }
    
    /**
     * Generate specific business recommendations
     */
    public function generateSpecificRecommendations($type, $business_id, $pdo) {
        $analytics = $this->getBusinessAnalytics($business_id, $pdo);
        
        switch ($type) {
            case 'pricing':
                return $this->generatePricingRecommendations($analytics);
            case 'stocking':
                return $this->generateStockingRecommendations($analytics);
            case 'combos':
                return $this->generateComboRecommendations($analytics);
            case 'trends':
                return $this->generateTrendAnalysis($analytics);
            default:
                return ['error' => 'Unknown recommendation type'];
        }
    }
    
    private function generatePricingRecommendations($analytics) {
        $recommendations = [];
        
        foreach ($analytics['item_performance'] as $item) {
            if ($item['total_sold'] > 0) {
                $price_variance = abs($item['current_price'] - $item['avg_selling_price']);
                if ($price_variance > 0.50) {
                    $recommendations[] = [
                        'item' => $item['item_name'],
                        'current_price' => $item['current_price'],
                        'suggested_price' => $item['avg_selling_price'],
                        'reason' => 'Align with historical selling price'
                    ];
                }
            }
        }
        
        return $recommendations;
    }
    
    private function generateStockingRecommendations($analytics) {
        $recommendations = [];
        
        foreach ($analytics['item_performance'] as $item) {
            $velocity = $item['total_sold'] / 30; // Sales per day
            $stock_days = $velocity > 0 ? $item['current_stock'] / $velocity : 999;
            
            if ($stock_days < 7) {
                $recommended_stock = ceil($velocity * 14); // 2 weeks stock
                $recommendations[] = [
                    'item' => $item['item_name'],
                    'current_stock' => $item['current_stock'],
                    'recommended_stock' => $recommended_stock,
                    'urgency' => $stock_days < 3 ? 'High' : 'Medium',
                    'reason' => "Only {$stock_days} days of stock remaining"
                ];
            }
        }
        
        return $recommendations;
    }
    
    private function generateComboRecommendations($analytics) {
        // This would analyze which items are frequently bought together
        // For now, return some general combo suggestions based on top items
        $combos = [];
        
        if (count($analytics['top_items']) >= 2) {
            $combos[] = [
                'items' => [$analytics['top_items'][0]['item_name'], $analytics['top_items'][1]['item_name']],
                'suggested_discount' => '10%',
                'expected_increase' => '25% in transaction value'
            ];
        }
        
        return $combos;
    }
    
    private function generateTrendAnalysis($analytics) {
        return [
            'daily_trends' => $analytics['daily_trends'],
            'revenue_trend' => $analytics['revenue_trend'],
            'top_performers' => array_slice($analytics['top_items'], 0, 5),
            'insights' => [
                'peak_day' => !empty($analytics['daily_trends']) ? 
                    array_reduce($analytics['daily_trends'], function($carry, $day) {
                        return ($carry === null || $day['daily_revenue'] > $carry['daily_revenue']) ? $day['day_name'] : $carry;
                    }) : 'Unknown',
                'optimization_score' => $analytics['optimization_score']
            ]
        ];
    }
}

?> 