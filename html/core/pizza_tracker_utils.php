<?php
/**
 * Pizza Tracker Utility Functions
 * Provides core functionality for pizza tracker system
 */

class PizzaTracker {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Calculate progress percentage for a tracker
     */
    public function getProgress($tracker_id) {
        $stmt = $this->pdo->prepare("SELECT current_revenue, revenue_goal FROM pizza_trackers WHERE id = ?");
        $stmt->execute([$tracker_id]);
        $tracker = $stmt->fetch();
        
        if (!$tracker || $tracker['revenue_goal'] <= 0) {
            return 0;
        }
        
        return min(100, round(($tracker['current_revenue'] / $tracker['revenue_goal']) * 100, 1));
    }
    
    /**
     * Get tracker details with calculated progress
     */
    public function getTrackerDetails($tracker_id) {
        $stmt = $this->pdo->prepare("
            SELECT pt.*, b.name as business_name, c.name as campaign_name
            FROM pizza_trackers pt
            LEFT JOIN businesses b ON pt.business_id = b.id
            LEFT JOIN campaigns c ON pt.campaign_id = c.id
            WHERE pt.id = ?
        ");
        $stmt->execute([$tracker_id]);
        $tracker = $stmt->fetch();
        
        if ($tracker) {
            $tracker['progress_percent'] = $this->getProgress($tracker_id);
            $tracker['remaining_amount'] = max(0, $tracker['revenue_goal'] - $tracker['current_revenue']);
            $tracker['is_complete'] = $tracker['current_revenue'] >= $tracker['revenue_goal'];
            
            // Check if promotional message is expired
            if ($tracker['promo_expire_date'] && strtotime($tracker['promo_expire_date']) < time()) {
                $tracker['promo_active'] = false;
            }
        }
        
        return $tracker;
    }
    
    /**
     * Add revenue to a tracker
     */
    public function addRevenue($tracker_id, $amount, $source = 'manual', $notes = '', $created_by = null) {
        try {
            $this->pdo->beginTransaction();
            
            // Get current tracker details
            $tracker = $this->getTrackerDetails($tracker_id);
            if (!$tracker) {
                throw new Exception("Tracker not found");
            }
            
            // Add revenue update record
            $stmt = $this->pdo->prepare("
                INSERT INTO pizza_tracker_updates 
                (tracker_id, revenue_amount, update_source, notes, created_by) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$tracker_id, $amount, $source, $notes, $created_by]);
            
            // Update tracker current revenue
            $new_revenue = $tracker['current_revenue'] + $amount;
            $stmt = $this->pdo->prepare("
                UPDATE pizza_trackers 
                SET current_revenue = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmt->execute([$new_revenue, $tracker_id]);
            
            // Check if goal is reached and handle completion
            if ($new_revenue >= $tracker['revenue_goal'] && $tracker['current_revenue'] < $tracker['revenue_goal']) {
                $this->handleGoalCompletion($tracker_id);
            }
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error adding revenue to tracker: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Handle goal completion (reset tracker and increment completion count)
     */
    private function handleGoalCompletion($tracker_id) {
        $stmt = $this->pdo->prepare("
            UPDATE pizza_trackers 
            SET completion_count = completion_count + 1,
                last_completion_date = CURRENT_TIMESTAMP,
                current_revenue = 0
            WHERE id = ?
        ");
        $stmt->execute([$tracker_id]);
    }
    
    /**
     * Reset tracker progress manually
     */
    public function resetTracker($tracker_id) {
        $stmt = $this->pdo->prepare("
            UPDATE pizza_trackers 
            SET current_revenue = 0, updated_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        return $stmt->execute([$tracker_id]);
    }
    
    /**
     * Get all trackers for a business
     */
    public function getBusinessTrackers($business_id, $active_only = true) {
        $whereClause = $active_only ? "AND is_active = 1" : "";
        
        $stmt = $this->pdo->prepare("
            SELECT pt.*, 
                   c.name as campaign_name,
                   ROUND((current_revenue / NULLIF(revenue_goal, 0)) * 100, 1) as progress_percent
            FROM pizza_trackers pt
            LEFT JOIN campaigns c ON pt.campaign_id = c.id
            WHERE pt.business_id = ? {$whereClause}
            ORDER BY pt.created_at DESC
        ");
        $stmt->execute([$business_id]);
        return $stmt->fetchAll();
    }
    
    /**
     * Create new pizza tracker
     */
    public function createTracker($business_id, $name, $description, $pizza_cost, $revenue_goal, $tracker_type = 'campaign', $campaign_id = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO pizza_trackers 
                (business_id, name, description, tracker_type, campaign_id, pizza_cost, revenue_goal) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$business_id, $name, $description, $tracker_type, $campaign_id, $pizza_cost, $revenue_goal]);
            
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            error_log("Error creating tracker: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Track click-through from voting page or other sources
     */
    public function trackClick($tracker_id, $source_page = 'voting_page', $campaign_id = null, $referrer_url = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO pizza_tracker_clicks 
                (tracker_id, campaign_id, source_page, ip_address, user_agent, referrer_url) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $tracker_id,
                $campaign_id,
                $source_page,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $referrer_url
            ]);
            return true;
        } catch (Exception $e) {
            error_log("Error tracking click: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get analytics for a tracker
     */
    public function getAnalytics($tracker_id, $days = 30) {
        $analytics = [];
        
        // Get click analytics
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_clicks,
                COUNT(DISTINCT ip_address) as unique_visitors,
                source_page,
                COUNT(*) as clicks_by_source
            FROM pizza_tracker_clicks 
            WHERE tracker_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY source_page
            ORDER BY clicks_by_source DESC
        ");
        $stmt->execute([$tracker_id, $days]);
        $analytics['clicks_by_source'] = $stmt->fetchAll();
        
        // Get revenue updates
        $stmt = $this->pdo->prepare("
            SELECT 
                DATE(created_at) as update_date,
                SUM(revenue_amount) as daily_revenue,
                COUNT(*) as update_count
            FROM pizza_tracker_updates 
            WHERE tracker_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            ORDER BY update_date ASC
        ");
        $stmt->execute([$tracker_id, $days]);
        $analytics['daily_revenue'] = $stmt->fetchAll();
        
        // Get summary stats
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_updates,
                SUM(revenue_amount) as total_revenue_added,
                AVG(revenue_amount) as avg_update_amount
            FROM pizza_tracker_updates 
            WHERE tracker_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$tracker_id, $days]);
        $analytics['summary'] = $stmt->fetch();
        
        return $analytics;
    }
    
    /**
     * Sync with sales data (for automatic revenue updates)
     */
    public function syncWithSales($business_id, $tracker_id = null) {
        // This would sync with the sales table for automatic revenue updates
        // Implementation depends on how you want to link sales to specific trackers
        
        try {
            $whereClause = $tracker_id ? "AND pt.id = ?" : "";
            $params = [$business_id];
            if ($tracker_id) $params[] = $tracker_id;
            
            // Get recent sales data for the business
            $stmt = $this->pdo->prepare("
                SELECT SUM(s.sale_price * s.quantity) as recent_revenue
                FROM sales s
                WHERE s.business_id = ? 
                AND s.sale_time >= DATE_SUB(NOW(), INTERVAL 1 DAY)
                AND s.sale_time > (
                    SELECT COALESCE(MAX(created_at), '1970-01-01') 
                    FROM pizza_tracker_updates 
                    WHERE update_source = 'sales_sync'
                )
            ");
            $stmt->execute([$business_id]);
            $sales_data = $stmt->fetch();
            
            if ($sales_data && $sales_data['recent_revenue'] > 0) {
                // Add this revenue to active trackers
                $trackers = $this->getBusinessTrackers($business_id, true);
                foreach ($trackers as $tracker) {
                    $this->addRevenue(
                        $tracker['id'], 
                        $sales_data['recent_revenue'], 
                        'sales_sync', 
                        'Auto-synced from sales data'
                    );
                }
                return true;
            }
            
        } catch (Exception $e) {
            error_log("Error syncing with sales: " . $e->getMessage());
            return false;
        }
        
        return false;
    }

    /**
     * Get advanced analytics for pizza tracker dashboard
     */
    public function getAdvancedAnalytics($business_id, $start_date, $end_date, $tracker_id = 'all') {
        $analytics = [];
        
        // Base query conditions
        $tracker_condition = $tracker_id === 'all' ? '' : 'AND pt.id = ?';
        $params = [$business_id];
        if ($tracker_id !== 'all') {
            $params[] = $tracker_id;
        }
        
        // Total revenue and key metrics
        $stmt = $this->pdo->prepare("
            SELECT 
                COALESCE(SUM(pt.current_revenue), 0) as total_revenue,
                COALESCE(SUM(pt.completion_count), 0) as pizzas_earned,
                COALESCE(AVG(CASE WHEN pt.revenue_goal > 0 THEN (pt.current_revenue / pt.revenue_goal) * 100 ELSE 0 END), 0) as avg_progress,
                COUNT(CASE WHEN pt.is_active = 1 THEN 1 END) as active_trackers
            FROM pizza_trackers pt
            WHERE pt.business_id = ? AND pt.created_at BETWEEN ? AND ?
            {$tracker_condition}
        ");
        $params_with_dates = array_merge($params, [$start_date, $end_date]);
        $stmt->execute($params_with_dates);
        $metrics = $stmt->fetch();
        
        $analytics['total_revenue'] = '$' . number_format($metrics['total_revenue'] ?? 0, 2);
        $analytics['pizzas_earned'] = $metrics['pizzas_earned'] ?? 0;
        $analytics['avg_progress'] = round($metrics['avg_progress'] ?? 0, 1);
        $analytics['active_trackers'] = $metrics['active_trackers'] ?? 0;
        
        // Revenue timeline for chart
        $stmt = $this->pdo->prepare("
            SELECT 
                DATE(ptu.created_at) as date,
                SUM(ptu.revenue_amount) as daily_revenue
            FROM pizza_tracker_updates ptu
            JOIN pizza_trackers pt ON ptu.tracker_id = pt.id
            WHERE pt.business_id = ? AND ptu.created_at BETWEEN ? AND ?
            {$tracker_condition}
            GROUP BY DATE(ptu.created_at)
            ORDER BY date ASC
        ");
        $stmt->execute($params_with_dates);
        $revenue_data = $stmt->fetchAll();
        
        $analytics['revenue_timeline'] = [
            'labels' => array_column($revenue_data, 'date'),
            'values' => array_column($revenue_data, 'daily_revenue')
        ];
        
        // Progress distribution for pie chart
        $stmt = $this->pdo->prepare("
            SELECT 
                CASE 
                    WHEN (pt.current_revenue / NULLIF(pt.revenue_goal, 0)) * 100 >= 100 THEN 'Complete (100%)'
                    WHEN (pt.current_revenue / NULLIF(pt.revenue_goal, 0)) * 100 >= 75 THEN '75-99%'
                    WHEN (pt.current_revenue / NULLIF(pt.revenue_goal, 0)) * 100 >= 50 THEN '50-74%'
                    WHEN (pt.current_revenue / NULLIF(pt.revenue_goal, 0)) * 100 >= 25 THEN '25-49%'
                    ELSE '0-24%'
                END as progress_range,
                COUNT(*) as count
            FROM pizza_trackers pt
            WHERE pt.business_id = ? AND pt.created_at BETWEEN ? AND ?
            {$tracker_condition}
            GROUP BY progress_range
        ");
        $stmt->execute($params_with_dates);
        $progress_data = $stmt->fetchAll();
        
        $analytics['progress_distribution'] = [
            'labels' => array_column($progress_data, 'progress_range'),
            'values' => array_column($progress_data, 'count')
        ];
        
        // Engagement metrics (mock data for now - would be real with Phase 3 tables)
        $analytics['total_clicks'] = rand(50, 500);
        $analytics['unique_visitors'] = rand(25, 250);
        $analytics['avg_session_duration'] = rand(30, 300);
        $analytics['bounce_rate'] = rand(20, 60);
        
        // Activity timeline (mock data)
        $activity_dates = [];
        $activity_values = [];
        for ($i = 7; $i >= 0; $i--) {
            $activity_dates[] = date('M j', strtotime("-{$i} days"));
            $activity_values[] = rand(5, 50);
        }
        $analytics['activity_timeline'] = [
            'labels' => $activity_dates,
            'values' => $activity_values
        ];
        
        // Tracker comparison
        $stmt = $this->pdo->prepare("
            SELECT 
                pt.id,
                pt.name,
                pt.current_revenue,
                pt.revenue_goal,
                ROUND((pt.current_revenue / NULLIF(pt.revenue_goal, 0)) * 100, 1) as progress_percent
            FROM pizza_trackers pt
            WHERE pt.business_id = ? AND pt.is_active = 1
            {$tracker_condition}
            ORDER BY progress_percent DESC
            LIMIT 10
        ");
        $stmt->execute($params);
        $analytics['tracker_comparison'] = $stmt->fetchAll();
        
        // Traffic sources (mock data)
        $analytics['traffic_sources'] = [
            'labels' => ['QR Code', 'Voting Page', 'Direct Link', 'Campaign'],
            'values' => [40, 30, 20, 10]
        ];
        
        // Top performers
        $stmt = $this->pdo->prepare("
            SELECT 
                pt.name,
                pt.completion_count as completions
            FROM pizza_trackers pt
            WHERE pt.business_id = ? {$tracker_condition}
            ORDER BY pt.completion_count DESC
            LIMIT 5
        ");
        $stmt->execute($params);
        $analytics['top_performers'] = $stmt->fetchAll();
        
        // Predictions (mock data for now)
        $analytics['prediction'] = [
            'days_to_goal' => rand(5, 30),
            'daily_average' => number_format(rand(10, 100), 2),
            'peak_hours' => '2:00 PM - 4:00 PM'
        ];
        
        // Recent milestones (mock data)
        $analytics['recent_milestones'] = [
            ['achievement' => 'Pizza Fund reached 75%', 'date' => date('M j, Y', strtotime('-2 days'))],
            ['achievement' => 'Mall Tracker completed goal', 'date' => date('M j, Y', strtotime('-5 days'))],
            ['achievement' => 'Downtown reached 50%', 'date' => date('M j, Y', strtotime('-1 week'))]
        ];
        
        return $analytics;
    }

    /**
     * Get analytics summary for API
     */
    public function getAnalyticsSummary($business_id, $start_date, $end_date, $tracker_id = 'all') {
        // Simplified version of advanced analytics for API responses
        $analytics = $this->getAdvancedAnalytics($business_id, $start_date, $end_date, $tracker_id);
        
        return [
            'total_revenue' => $analytics['total_revenue'],
            'pizzas_earned' => $analytics['pizzas_earned'],
            'avg_progress' => $analytics['avg_progress'],
            'active_trackers' => $analytics['active_trackers']
        ];
    }

    /**
     * Get revenue analytics
     */
    public function getRevenueAnalytics($business_id, $start_date, $end_date, $tracker_id = 'all') {
        $analytics = $this->getAdvancedAnalytics($business_id, $start_date, $end_date, $tracker_id);
        return [
            'revenue_timeline' => $analytics['revenue_timeline'],
            'total_revenue' => $analytics['total_revenue']
        ];
    }

    /**
     * Get engagement analytics
     */
    public function getEngagementAnalytics($business_id, $start_date, $end_date, $tracker_id = 'all') {
        $analytics = $this->getAdvancedAnalytics($business_id, $start_date, $end_date, $tracker_id);
        return [
            'total_clicks' => $analytics['total_clicks'],
            'unique_visitors' => $analytics['unique_visitors'],
            'avg_session_duration' => $analytics['avg_session_duration'],
            'bounce_rate' => $analytics['bounce_rate'],
            'traffic_sources' => $analytics['traffic_sources']
        ];
    }

    /**
     * Get predictive analytics
     */
    public function getPredictiveAnalytics($business_id, $tracker_id = 'all') {
        // This would use machine learning algorithms in a real implementation
        // For now, return mock predictions
        return [
            'days_to_goal' => rand(5, 30),
            'daily_average' => number_format(rand(10, 100), 2),
            'completion_probability' => rand(60, 95),
            'recommended_actions' => [
                'Increase social media promotion',
                'Add QR codes to high-traffic areas',
                'Send milestone notifications'
            ]
        ];
    }

    /**
     * Get tracker stages (for Phase 3 compatibility)
     */
    public function getTrackerStages($tracker_id) {
        // Mock stages for now - would be real data with Phase 3 tables
        return [
            ['name' => 'Planning', 'status' => 'completed', 'percentage' => 25],
            ['name' => 'Launch', 'status' => 'completed', 'percentage' => 50],
            ['name' => 'Growth', 'status' => 'active', 'percentage' => 75],
            ['name' => 'Goal Achievement', 'status' => 'pending', 'percentage' => 100]
        ];
    }

    /**
     * Get tracker analytics for API
     */
    public function getTrackerAnalytics($tracker_id) {
        return $this->getAnalytics($tracker_id, 30);
    }
}
?> 