<?php
/**
 * Nayax Analytics Engine
 * Advanced business intelligence and analytics for Nayax integration
 * 
 * @author RevenueQR Team
 * @version 1.0
 * @date 2025-01-17
 */

class NayaxAnalyticsEngine {
    
    private $pdo;
    private $cache_duration = 300; // 5 minutes
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get comprehensive business analytics for a specific time period
     */
    public function getBusinessAnalytics($business_id, $days = 30, $include_predictions = true) {
        $cache_key = "nayax_analytics_{$business_id}_{$days}";
        
        // Try to get from cache first
        $cached = $this->getFromCache($cache_key);
        if ($cached) {
            return $cached;
        }
        
        $analytics = [
            'period' => $days,
            'business_id' => $business_id,
            'generated_at' => date('Y-m-d H:i:s'),
            'revenue' => $this->getRevenueAnalytics($business_id, $days),
            'transactions' => $this->getTransactionAnalytics($business_id, $days),
            'qr_coins' => $this->getQRCoinAnalytics($business_id, $days),
            'discounts' => $this->getDiscountAnalytics($business_id, $days),
            'machines' => $this->getMachineAnalytics($business_id, $days),
            'customers' => $this->getCustomerAnalytics($business_id, $days),
            'trends' => $this->getTrendAnalytics($business_id, $days),
            'performance' => $this->getPerformanceMetrics($business_id, $days)
        ];
        
        if ($include_predictions) {
            $analytics['predictions'] = $this->getPredictiveAnalytics($business_id, $analytics);
            $analytics['recommendations'] = $this->getRecommendations($business_id, $analytics);
        }
        
        // Cache the results
        $this->saveToCache($cache_key, $analytics);
        
        return $analytics;
    }
    
    /**
     * Revenue analytics with detailed breakdowns
     */
    private function getRevenueAnalytics($business_id, $days) {
        $stmt = $this->pdo->prepare("
            SELECT 
                DATE(nt.created_at) as date,
                COUNT(*) as transaction_count,
                SUM(nt.amount_cents) as total_revenue_cents,
                SUM(nt.qr_coins_awarded) as coins_awarded,
                SUM(nt.platform_commission_cents) as platform_fees_cents,
                AVG(nt.amount_cents) as avg_transaction_cents,
                MIN(nt.amount_cents) as min_transaction_cents,
                MAX(nt.amount_cents) as max_transaction_cents
            FROM nayax_transactions nt
            JOIN nayax_machines nm ON nt.nayax_machine_id = nm.nayax_machine_id
            WHERE nm.business_id = ? 
            AND nt.status = 'completed'
            AND nt.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(nt.created_at)
            ORDER BY date DESC
        ");
        $stmt->execute([$business_id, $days]);
        $daily_revenue = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate totals and averages
        $total_revenue = array_sum(array_column($daily_revenue, 'total_revenue_cents'));
        $total_transactions = array_sum(array_column($daily_revenue, 'transaction_count'));
        $total_fees = array_sum(array_column($daily_revenue, 'platform_fees_cents'));
        $total_coins = array_sum(array_column($daily_revenue, 'coins_awarded'));
        
        // Growth calculations
        $growth = $this->calculateGrowthMetrics($daily_revenue, 'total_revenue_cents');
        
        return [
            'total_revenue_cents' => $total_revenue,
            'total_revenue_dollars' => $total_revenue / 100,
            'total_transactions' => $total_transactions,
            'platform_fees_cents' => $total_fees,
            'platform_fees_dollars' => $total_fees / 100,
            'net_revenue_cents' => $total_revenue - $total_fees,
            'net_revenue_dollars' => ($total_revenue - $total_fees) / 100,
            'avg_transaction_value' => $total_transactions > 0 ? $total_revenue / $total_transactions / 100 : 0,
            'total_coins_awarded' => $total_coins,
            'daily_breakdown' => $daily_revenue,
            'growth_rate' => $growth['growth_rate'],
            'trend_direction' => $growth['trend'],
            'revenue_per_day' => count($daily_revenue) > 0 ? $total_revenue / count($daily_revenue) / 100 : 0
        ];
    }
    
    /**
     * Transaction analytics with patterns and insights
     */
    private function getTransactionAnalytics($business_id, $days) {
        // Hourly patterns
        $stmt = $this->pdo->prepare("
            SELECT 
                HOUR(nt.created_at) as hour,
                COUNT(*) as transaction_count,
                SUM(nt.amount_cents) as revenue_cents,
                AVG(nt.amount_cents) as avg_amount_cents
            FROM nayax_transactions nt
            JOIN nayax_machines nm ON nt.nayax_machine_id = nm.nayax_machine_id
            WHERE nm.business_id = ? 
            AND nt.status = 'completed'
            AND nt.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY HOUR(nt.created_at)
            ORDER BY hour
        ");
        $stmt->execute([$business_id, $days]);
        $hourly_patterns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Day of week patterns
        $stmt = $this->pdo->prepare("
            SELECT 
                DAYNAME(nt.created_at) as day_name,
                DAYOFWEEK(nt.created_at) as day_number,
                COUNT(*) as transaction_count,
                SUM(nt.amount_cents) as revenue_cents,
                AVG(nt.amount_cents) as avg_amount_cents
            FROM nayax_transactions nt
            JOIN nayax_machines nm ON nt.nayax_machine_id = nm.nayax_machine_id
            WHERE nm.business_id = ? 
            AND nt.status = 'completed'
            AND nt.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DAYOFWEEK(nt.created_at), DAYNAME(nt.created_at)
            ORDER BY day_number
        ");
        $stmt->execute([$business_id, $days]);
        $weekly_patterns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Payment method breakdown
        $stmt = $this->pdo->prepare("
            SELECT 
                nt.payment_method,
                COUNT(*) as transaction_count,
                SUM(nt.amount_cents) as revenue_cents,
                AVG(nt.amount_cents) as avg_amount_cents,
                ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER(), 2) as percentage
            FROM nayax_transactions nt
            JOIN nayax_machines nm ON nt.nayax_machine_id = nm.nayax_machine_id
            WHERE nm.business_id = ? 
            AND nt.status = 'completed'
            AND nt.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY nt.payment_method
            ORDER BY transaction_count DESC
        ");
        $stmt->execute([$business_id, $days]);
        $payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Find peak hours
        $peak_hour = !empty($hourly_patterns) ? 
            array_reduce($hourly_patterns, function($carry, $item) {
                return ($carry === null || $item['transaction_count'] > $carry['transaction_count']) ? $item : $carry;
            }) : null;
        
        return [
            'hourly_patterns' => $hourly_patterns,
            'weekly_patterns' => $weekly_patterns,
            'payment_methods' => $payment_methods,
            'peak_hour' => $peak_hour ? $peak_hour['hour'] . ':00' : 'N/A',
            'peak_hour_transactions' => $peak_hour ? $peak_hour['transaction_count'] : 0,
            'insights' => $this->generateTransactionInsights($hourly_patterns, $weekly_patterns, $payment_methods)
        ];
    }
    
    /**
     * QR Coin analytics and economy metrics
     */
    private function getQRCoinAnalytics($business_id, $days) {
        // QR coin purchases (users buying coins at machines)
        $stmt = $this->pdo->prepare("
            SELECT 
                DATE(nt.created_at) as date,
                COUNT(*) as coin_purchases,
                SUM(nt.qr_coins_awarded) as coins_sold,
                SUM(nt.amount_cents) as coin_revenue_cents
            FROM nayax_transactions nt
            JOIN nayax_machines nm ON nt.nayax_machine_id = nm.nayax_machine_id
            WHERE nm.business_id = ? 
            AND nt.transaction_type = 'qr_coin_purchase'
            AND nt.status = 'completed'
            AND nt.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(nt.created_at)
            ORDER BY date DESC
        ");
        $stmt->execute([$business_id, $days]);
        $coin_purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // QR coin redemptions (discount_redemption transactions)
        $stmt = $this->pdo->prepare("
            SELECT 
                DATE(nt.created_at) as date,
                COUNT(*) as redemptions,
                SUM(nt.amount_cents) as discount_value_cents,
                COUNT(*) as codes_used
            FROM nayax_transactions nt
            JOIN nayax_machines nm ON nt.nayax_machine_id = nm.nayax_machine_id
            WHERE nm.business_id = ? 
            AND nt.transaction_type = 'discount_redemption'
            AND nt.status = 'completed'
            AND nt.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(nt.created_at)
            ORDER BY date DESC
        ");
        $stmt->execute([$business_id, $days]);
        $coin_redemptions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate coin economy health
        $total_coins_sold = array_sum(array_column($coin_purchases, 'coins_sold'));
        $total_discount_value = array_sum(array_column($coin_redemptions, 'discount_value_cents'));
        $circulation_rate = $total_coins_sold > 0 ? ($total_discount_value / ($total_coins_sold * 10)) * 100 : 0; // Assuming 10 cents per coin
        
        return [
            'coin_purchases' => $coin_purchases,
            'coin_redemptions' => $coin_redemptions,
            'total_coins_sold' => $total_coins_sold,
            'total_discount_value_cents' => $total_discount_value,
            'total_discount_value_dollars' => $total_discount_value / 100,
            'estimated_coins_redeemed' => $total_discount_value / 10, // Assuming 10 cents per coin
            'circulation_rate' => round($circulation_rate, 2),
            'avg_coins_per_purchase' => count($coin_purchases) > 0 ? $total_coins_sold / count($coin_purchases) : 0,
            'economy_health' => $this->assessCoinEconomyHealth($circulation_rate, $total_coins_sold, $total_discount_value / 10)
        ];
    }
    
    /**
     * Discount analytics based on store items and redemption transactions
     */
    private function getDiscountAnalytics($business_id, $days) {
        $stmt = $this->pdo->prepare("
            SELECT 
                bsi.item_name,
                bsi.category,
                COUNT(CASE WHEN nt.transaction_type = 'discount_redemption' THEN 1 END) as redemptions,
                SUM(CASE WHEN nt.transaction_type = 'discount_redemption' THEN nt.amount_cents END) as discount_value_cents,
                AVG(bsi.discount_percentage) as avg_discount_percent,
                bsi.qr_coin_cost
            FROM business_store_items bsi
            LEFT JOIN nayax_transactions nt ON JSON_CONTAINS(nt.transaction_data, JSON_OBJECT('item_name', bsi.item_name))
                AND nt.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            JOIN nayax_machines nm ON nm.business_id = bsi.business_id
            WHERE bsi.business_id = ?
            GROUP BY bsi.id, bsi.item_name
            ORDER BY redemptions DESC
        ");
        $stmt->execute([$days, $business_id]);
        $discount_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Overall discount metrics from discount_redemption transactions
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(*) as total_redemptions,
                SUM(nt.amount_cents) as total_discount_value_cents
            FROM nayax_transactions nt
            JOIN nayax_machines nm ON nt.nayax_machine_id = nm.nayax_machine_id
            WHERE nm.business_id = ? 
            AND nt.transaction_type = 'discount_redemption'
            AND nt.status = 'completed'
            AND nt.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$business_id, $days]);
        $overall_metrics = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'discount_performance' => $discount_performance,
            'total_redemptions' => $overall_metrics['total_redemptions'] ?? 0,
            'total_discount_value_cents' => $overall_metrics['total_discount_value_cents'] ?? 0,
            'total_discount_value_dollars' => ($overall_metrics['total_discount_value_cents'] ?? 0) / 100,
            'top_performing_discount' => !empty($discount_performance) ? $discount_performance[0] : null,
            'optimization_opportunities' => $this->identifyDiscountOptimizations($discount_performance)
        ];
    }
    
    /**
     * Machine performance analytics
     */
    private function getMachineAnalytics($business_id, $days) {
        $stmt = $this->pdo->prepare("
            SELECT 
                nm.nayax_machine_id,
                nm.machine_name,
                COUNT(nt.id) as total_transactions,
                SUM(nt.amount_cents) as total_revenue_cents,
                SUM(nt.qr_coins_awarded) as total_coins_awarded,
                AVG(nt.amount_cents) as avg_transaction_value,
                COUNT(CASE WHEN DATE(nt.created_at) = CURDATE() THEN 1 END) as transactions_today,
                COUNT(CASE WHEN nt.transaction_type = 'qr_coin_purchase' THEN 1 END) as coin_purchases,
                COUNT(CASE WHEN nt.transaction_type = 'sale' THEN 1 END) as regular_purchases,
                MIN(nt.created_at) as first_transaction,
                MAX(nt.created_at) as last_transaction
            FROM nayax_machines nm
            LEFT JOIN nayax_transactions nt ON nm.nayax_machine_id = nt.nayax_machine_id 
                AND nt.status = 'completed'
                AND nt.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            WHERE nm.business_id = ?
            GROUP BY nm.nayax_machine_id, nm.machine_name
            ORDER BY total_revenue_cents DESC
        ");
        $stmt->execute([$days, $business_id]);
        $machine_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate machine rankings and insights
        foreach ($machine_performance as &$machine) {
            $machine['revenue_per_day'] = $days > 0 ? $machine['total_revenue_cents'] / $days / 100 : 0;
            $machine['transactions_per_day'] = $days > 0 ? $machine['total_transactions'] / $days : 0;
            $machine['qr_coin_adoption_rate'] = $machine['total_transactions'] > 0 ? 
                ($machine['coin_purchases'] / $machine['total_transactions']) * 100 : 0;
        }
        
        return [
            'machine_performance' => $machine_performance,
            'top_revenue_machine' => !empty($machine_performance) ? $machine_performance[0] : null,
            'total_machines' => count($machine_performance),
            'active_machines' => count(array_filter($machine_performance, function($m) { 
                return $m['total_transactions'] > 0; 
            })),
            'machine_insights' => $this->generateMachineInsights($machine_performance)
        ];
    }
    
    /**
     * Customer behavior analytics based on transaction data
     */
    private function getCustomerAnalytics($business_id, $days) {
        // User engagement with transactions (where user_id is available)
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(DISTINCT nt.user_id) as unique_customers,
                COUNT(nt.id) as total_purchases,
                SUM(nt.qr_coins_awarded) as total_coins_awarded,
                AVG(nt.amount_cents) as avg_purchase_amount_cents,
                SUM(nt.amount_cents) / COUNT(DISTINCT nt.user_id) as avg_spend_per_customer_cents
            FROM nayax_transactions nt
            JOIN nayax_machines nm ON nt.nayax_machine_id = nm.nayax_machine_id
            WHERE nm.business_id = ? 
            AND nt.user_id IS NOT NULL
            AND nt.status = 'completed'
            AND nt.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$business_id, $days]);
        $customer_overview = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Customer segmentation based on spending
        $stmt = $this->pdo->prepare("
            SELECT 
                CASE 
                    WHEN total_spend_cents >= 100000 THEN 'High Value' -- $1000+
                    WHEN total_spend_cents >= 50000 THEN 'Medium Value' -- $500-999
                    WHEN total_spend_cents >= 10000 THEN 'Low Value' -- $100-499
                    ELSE 'Trial' -- <$100
                END as customer_segment,
                COUNT(*) as customer_count,
                AVG(total_spend_cents) as avg_spend_cents,
                AVG(purchase_count) as avg_purchases
            FROM (
                SELECT 
                    nt.user_id,
                    SUM(nt.amount_cents) as total_spend_cents,
                    COUNT(nt.id) as purchase_count
                FROM nayax_transactions nt
                JOIN nayax_machines nm ON nt.nayax_machine_id = nm.nayax_machine_id
                WHERE nm.business_id = ? 
                AND nt.user_id IS NOT NULL
                AND nt.status = 'completed'
                AND nt.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                GROUP BY nt.user_id
            ) customer_stats
            GROUP BY customer_segment
            ORDER BY avg_spend_cents DESC
        ");
        $stmt->execute([$business_id, $days]);
        $customer_segments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'overview' => $customer_overview,
            'segments' => $customer_segments,
            'customer_insights' => $this->generateCustomerInsights($customer_overview, $customer_segments)
        ];
    }
    
    /**
     * Trend analysis and forecasting
     */
    private function getTrendAnalytics($business_id, $days) {
        // Revenue trend analysis
        $stmt = $this->pdo->prepare("
            SELECT 
                DATE(nt.created_at) as date,
                SUM(nt.amount_cents) as revenue_cents,
                COUNT(*) as transaction_count
            FROM nayax_transactions nt
            JOIN nayax_machines nm ON nt.nayax_machine_id = nm.nayax_machine_id
            WHERE nm.business_id = ? 
            AND nt.status = 'completed'
            AND nt.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(nt.created_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$business_id, $days]);
        $daily_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate trends
        $revenue_trend = $this->calculateTrendDirection($daily_data, 'revenue_cents');
        $transaction_trend = $this->calculateTrendDirection($daily_data, 'transaction_count');
        
        return [
            'daily_data' => $daily_data,
            'revenue_trend' => $revenue_trend,
            'transaction_trend' => $transaction_trend,
            'trend_strength' => $this->calculateTrendStrength($daily_data),
            'seasonality' => $this->detectSeasonality($daily_data)
        ];
    }
    
    /**
     * Overall performance metrics and KPIs
     */
    private function getPerformanceMetrics($business_id, $days) {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(DISTINCT nm.nayax_machine_id) as total_machines,
                COUNT(DISTINCT DATE(nt.created_at)) as active_days,
                COUNT(nt.id) as total_transactions,
                SUM(nt.amount_cents) as total_revenue_cents,
                SUM(nt.platform_commission_cents) as total_fees_cents,
                SUM(nt.qr_coins_awarded) as total_coins_awarded,
                AVG(nt.amount_cents) as avg_transaction_value,
                COUNT(CASE WHEN nt.transaction_type = 'qr_coin_purchase' THEN 1 END) as qr_purchases,
                COUNT(CASE WHEN nt.transaction_type = 'sale' THEN 1 END) as regular_purchases
            FROM nayax_machines nm
            LEFT JOIN nayax_transactions nt ON nm.nayax_machine_id = nt.nayax_machine_id 
                AND nt.status = 'completed'
                AND nt.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            WHERE nm.business_id = ?
        ");
        $stmt->execute([$days, $business_id]);
        $metrics = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate additional KPIs
        $qr_adoption_rate = $metrics['total_transactions'] > 0 ? 
            ($metrics['qr_purchases'] / $metrics['total_transactions']) * 100 : 0;
        
        $revenue_per_machine = $metrics['total_machines'] > 0 ? 
            $metrics['total_revenue_cents'] / $metrics['total_machines'] / 100 : 0;
        
        $transactions_per_day = $metrics['active_days'] > 0 ? 
            $metrics['total_transactions'] / $metrics['active_days'] : 0;
        
        return [
            'raw_metrics' => $metrics,
            'qr_adoption_rate' => round($qr_adoption_rate, 2),
            'revenue_per_machine' => round($revenue_per_machine, 2),
            'transactions_per_day' => round($transactions_per_day, 2),
            'net_revenue_dollars' => ($metrics['total_revenue_cents'] - $metrics['total_fees_cents']) / 100,
            'fee_percentage' => $metrics['total_revenue_cents'] > 0 ? 
                ($metrics['total_fees_cents'] / $metrics['total_revenue_cents']) * 100 : 0
        ];
    }
    
    /**
     * Predictive analytics and forecasting
     */
    private function getPredictiveAnalytics($business_id, $historical_data) {
        $revenue_data = $historical_data['revenue']['daily_breakdown'];
        
        if (count($revenue_data) < 7) {
            return [
                'revenue_forecast' => null,
                'confidence' => 'low',
                'message' => 'Insufficient data for accurate predictions'
            ];
        }
        
        // Simple linear regression for revenue forecasting
        $forecast = $this->forecastRevenue($revenue_data, 7); // 7-day forecast
        
        // Growth rate prediction
        $growth_prediction = $this->predictGrowthRate($revenue_data);
        
        return [
            'revenue_forecast' => $forecast,
            'growth_prediction' => $growth_prediction,
            'confidence' => count($revenue_data) >= 14 ? 'high' : 'medium',
            'next_week_revenue' => array_sum(array_column($forecast, 'predicted_revenue_cents')) / 100,
            'trend_outlook' => $this->analyzeTrendOutlook($revenue_data)
        ];
    }
    
    /**
     * Business optimization recommendations
     */
    private function getRecommendations($business_id, $analytics) {
        $recommendations = [];
        
        // Revenue optimization
        if ($analytics['performance']['qr_adoption_rate'] < 20) {
            $recommendations[] = [
                'type' => 'qr_adoption',
                'priority' => 'high',
                'title' => 'Increase QR Coin Adoption',
                'description' => 'QR coin adoption is low. Consider promoting coin packs with special offers.',
                'impact' => 'medium',
                'effort' => 'low'
            ];
        }
        
        // Machine performance
        $inactive_machines = array_filter($analytics['machines']['machine_performance'], function($m) {
            return $m['total_transactions'] == 0;
        });
        
        if (!empty($inactive_machines)) {
            $recommendations[] = [
                'type' => 'machine_activation',
                'priority' => 'medium',
                'title' => 'Activate Underperforming Machines',
                'description' => count($inactive_machines) . ' machines have no transactions. Check connectivity and promotions.',
                'impact' => 'high',
                'effort' => 'medium'
            ];
        }
        
        // Discount optimization
        if (isset($analytics['discounts']['total_redemptions']) && $analytics['discounts']['total_redemptions'] < 10) {
            $recommendations[] = [
                'type' => 'discount_optimization',
                'priority' => 'medium',
                'title' => 'Optimize Discount Strategy',
                'description' => 'Discount redemption rate is low. Consider adjusting discount percentages or item selection.',
                'impact' => 'medium',
                'effort' => 'low'
            ];
        }
        
        // Peak time optimization
        if (!empty($analytics['transactions']['hourly_patterns'])) {
            $peak_hour = array_reduce($analytics['transactions']['hourly_patterns'], function($carry, $item) {
                return ($carry === null || $item['transaction_count'] > $carry['transaction_count']) ? $item : $carry;
            });
            
            if ($peak_hour && $peak_hour['transaction_count'] > 0) {
                $recommendations[] = [
                    'type' => 'peak_optimization',
                    'priority' => 'low',
                    'title' => 'Leverage Peak Hours',
                    'description' => "Peak activity at {$peak_hour['hour']}:00. Consider special promotions during this time.",
                    'impact' => 'low',
                    'effort' => 'low'
                ];
            }
        }
        
        return $recommendations;
    }
    
    // Helper methods for calculations and insights
    
    private function calculateGrowthMetrics($data, $field) {
        if (count($data) < 2) {
            return ['growth_rate' => 0, 'trend' => 'insufficient_data'];
        }
        
        $recent = array_slice($data, 0, ceil(count($data) / 2));
        $older = array_slice($data, ceil(count($data) / 2));
        
        $recent_avg = array_sum(array_column($recent, $field)) / count($recent);
        $older_avg = array_sum(array_column($older, $field)) / count($older);
        
        $growth_rate = $older_avg > 0 ? (($recent_avg - $older_avg) / $older_avg) * 100 : 0;
        
        return [
            'growth_rate' => round($growth_rate, 2),
            'trend' => $growth_rate > 5 ? 'growing' : ($growth_rate < -5 ? 'declining' : 'stable')
        ];
    }
    
    private function generateTransactionInsights($hourly, $weekly, $payment) {
        $insights = [];
        
        // Peak hour insight
        if (!empty($hourly)) {
            $peak = array_reduce($hourly, function($carry, $item) {
                return ($carry === null || $item['transaction_count'] > $carry['transaction_count']) ? $item : $carry;
            });
            $insights[] = "Peak activity occurs at {$peak['hour']}:00 with {$peak['transaction_count']} transactions";
        }
        
        // Day pattern insight
        if (!empty($weekly)) {
            $busiest_day = array_reduce($weekly, function($carry, $item) {
                return ($carry === null || $item['transaction_count'] > $carry['transaction_count']) ? $item : $carry;
            });
            $insights[] = "{$busiest_day['day_name']} is the busiest day with {$busiest_day['transaction_count']} transactions";
        }
        
        // Payment method insight
        if (!empty($payment)) {
            $top_method = $payment[0];
            $insights[] = "{$top_method['payment_method']} is the preferred payment method ({$top_method['percentage']}%)";
        }
        
        return $insights;
    }
    
    private function assessCoinEconomyHealth($circulation_rate, $sold, $redeemed) {
        if ($circulation_rate > 80) return 'excellent';
        if ($circulation_rate > 60) return 'good';
        if ($circulation_rate > 40) return 'fair';
        if ($circulation_rate > 20) return 'poor';
        return 'critical';
    }
    
    private function identifyDiscountOptimizations($performance) {
        $opportunities = [];
        
        foreach ($performance as $item) {
            // Check for low redemption rates
            if (isset($item['redemptions']) && $item['redemptions'] < 5) {
                $opportunities[] = [
                    'item' => $item['item_name'],
                    'issue' => 'low_redemptions',
                    'suggestion' => 'Increase discount percentage or promote this item more actively'
                ];
            }
            
            // Check for high QR coin cost relative to discount
            if (isset($item['qr_coin_cost']) && isset($item['avg_discount_percent']) && 
                $item['qr_coin_cost'] > ($item['avg_discount_percent'] * 5)) {
                $opportunities[] = [
                    'item' => $item['item_name'],
                    'issue' => 'high_coin_cost',
                    'suggestion' => 'Reduce QR coin cost to improve value proposition'
                ];
            }
        }
        
        return $opportunities;
    }
    
    private function generateMachineInsights($performance) {
        $insights = [];
        $total_machines = count($performance);
        $active_machines = count(array_filter($performance, function($m) { return $m['total_transactions'] > 0; }));
        
        if ($active_machines < $total_machines) {
            $inactive = $total_machines - $active_machines;
            $insights[] = "{$inactive} machines are inactive and need attention";
        }
        
        if (!empty($performance)) {
            $top_performer = $performance[0];
            $revenue_dollars = $top_performer['total_revenue_cents'] / 100;
            $insights[] = "Top performer: {$top_performer['machine_name']} with \${$revenue_dollars} revenue";
        }
        
        return $insights;
    }
    
    private function generateCustomerInsights($overview, $segments) {
        $insights = [];
        
        if ($overview['unique_customers'] > 0) {
            $loyalty = $overview['avg_purchases_per_customer'];
            if ($loyalty > 3) {
                $insights[] = "Strong customer loyalty with {$loyalty} avg purchases per customer";
            } elseif ($loyalty < 1.5) {
                $insights[] = "Low customer retention - focus on engagement strategies";
            }
        }
        
        if (!empty($segments)) {
            $high_value = array_filter($segments, function($s) { return $s['customer_segment'] == 'High Value'; });
            if (!empty($high_value)) {
                $hv_count = $high_value[0]['customer_count'];
                $total_customers = array_sum(array_column($segments, 'customer_count'));
                $hv_percentage = ($hv_count / $total_customers) * 100;
                $insights[] = "High-value customers represent {$hv_percentage}% of your base";
            }
        }
        
        return $insights;
    }
    
    private function calculateTrendDirection($data, $field) {
        if (count($data) < 3) return 'insufficient_data';
        
        $values = array_column($data, $field);
        $recent_third = array_slice($values, 0, ceil(count($values) / 3));
        $older_third = array_slice($values, -ceil(count($values) / 3));
        
        $recent_avg = array_sum($recent_third) / count($recent_third);
        $older_avg = array_sum($older_third) / count($older_third);
        
        $change = (($recent_avg - $older_avg) / $older_avg) * 100;
        
        if ($change > 10) return 'strong_upward';
        if ($change > 5) return 'upward';
        if ($change > -5) return 'stable';
        if ($change > -10) return 'downward';
        return 'strong_downward';
    }
    
    private function calculateTrendStrength($data) {
        if (count($data) < 5) return 'weak';
        
        $values = array_column($data, 'revenue_cents');
        $correlation = $this->calculateCorrelation(range(1, count($values)), $values);
        
        if (abs($correlation) > 0.8) return 'very_strong';
        if (abs($correlation) > 0.6) return 'strong';
        if (abs($correlation) > 0.4) return 'moderate';
        if (abs($correlation) > 0.2) return 'weak';
        return 'very_weak';
    }
    
    private function detectSeasonality($data) {
        // Simple seasonality detection based on day of week patterns
        $day_performance = [];
        foreach ($data as $row) {
            $day = date('w', strtotime($row['date'])); // 0 = Sunday, 6 = Saturday
            $day_performance[$day] = ($day_performance[$day] ?? 0) + $row['revenue_cents'];
        }
        
        if (empty($day_performance)) {
            return [
                'strongest_day' => 'N/A',
                'weakest_day' => 'N/A',
                'has_weekly_pattern' => false
            ];
        }
        
        $max_day = array_keys($day_performance, max($day_performance))[0];
        $min_day = array_keys($day_performance, min($day_performance))[0];
        
        $day_names = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        
        return [
            'strongest_day' => $day_names[$max_day],
            'weakest_day' => $day_names[$min_day],
            'has_weekly_pattern' => (max($day_performance) / min($day_performance)) > 1.5
        ];
    }
    
    private function forecastRevenue($historical_data, $forecast_days) {
        // Simple linear regression forecast
        $x_values = range(1, count($historical_data));
        $y_values = array_column($historical_data, 'total_revenue_cents');
        
        $regression = $this->linearRegression($x_values, $y_values);
        
        $forecast = [];
        for ($i = 1; $i <= $forecast_days; $i++) {
            $next_x = count($historical_data) + $i;
            $predicted_revenue = $regression['slope'] * $next_x + $regression['intercept'];
            
            $forecast[] = [
                'date' => date('Y-m-d', strtotime("+{$i} days")),
                'predicted_revenue_cents' => max(0, round($predicted_revenue)),
                'confidence' => $this->calculateForecastConfidence($i, count($historical_data))
            ];
        }
        
        return $forecast;
    }
    
    private function predictGrowthRate($data) {
        $recent_week = array_slice($data, 0, 7);
        $previous_week = array_slice($data, 7, 7);
        
        if (count($recent_week) < 7 || count($previous_week) < 7) {
            return null;
        }
        
        $recent_revenue = array_sum(array_column($recent_week, 'total_revenue_cents'));
        $previous_revenue = array_sum(array_column($previous_week, 'total_revenue_cents'));
        
        $growth_rate = $previous_revenue > 0 ? (($recent_revenue - $previous_revenue) / $previous_revenue) * 100 : 0;
        
        return [
            'weekly_growth_rate' => round($growth_rate, 2),
            'projected_monthly' => round($growth_rate * 4, 2),
            'trend' => $growth_rate > 0 ? 'positive' : 'negative'
        ];
    }
    
    private function analyzeTrendOutlook($data) {
        $trend_direction = $this->calculateTrendDirection($data, 'total_revenue_cents');
        $trend_strength = $this->calculateTrendStrength($data);
        
        $outlook_map = [
            'strong_upward' => 'very_positive',
            'upward' => 'positive',
            'stable' => 'neutral',
            'downward' => 'concerning',
            'strong_downward' => 'urgent_attention_needed'
        ];
        
        return [
            'direction' => $trend_direction,
            'strength' => $trend_strength,
            'outlook' => $outlook_map[$trend_direction] ?? 'unclear',
            'recommendation' => $this->getTrendRecommendation($trend_direction, $trend_strength)
        ];
    }
    
    private function getTrendRecommendation($direction, $strength) {
        if ($direction === 'strong_upward') {
            return 'Excellent performance! Consider expanding successful strategies.';
        } elseif ($direction === 'upward') {
            return 'Good growth trend. Monitor and maintain current strategies.';
        } elseif ($direction === 'stable') {
            return 'Stable performance. Look for growth opportunities.';
        } elseif ($direction === 'downward') {
            return 'Declining trend detected. Review and adjust strategies.';
        } elseif ($direction === 'strong_downward') {
            return 'Urgent: Significant decline. Immediate action required.';
        } else {
            return 'Insufficient data for reliable trend analysis.';
        }
    }
    
    // Mathematical helper functions
    
    private function linearRegression($x, $y) {
        $n = count($x);
        $sum_x = array_sum($x);
        $sum_y = array_sum($y);
        $sum_xy = 0;
        $sum_x2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sum_xy += $x[$i] * $y[$i];
            $sum_x2 += $x[$i] * $x[$i];
        }
        
        $slope = ($n * $sum_xy - $sum_x * $sum_y) / ($n * $sum_x2 - $sum_x * $sum_x);
        $intercept = ($sum_y - $slope * $sum_x) / $n;
        
        return ['slope' => $slope, 'intercept' => $intercept];
    }
    
    private function calculateCorrelation($x, $y) {
        $n = count($x);
        if ($n !== count($y) || $n === 0) return 0;
        
        $sum_x = array_sum($x);
        $sum_y = array_sum($y);
        $sum_xy = 0;
        $sum_x2 = 0;
        $sum_y2 = 0;
        
        for ($i = 0; $i < $n; $i++) {
            $sum_xy += $x[$i] * $y[$i];
            $sum_x2 += $x[$i] * $x[$i];
            $sum_y2 += $y[$i] * $y[$i];
        }
        
        $numerator = $n * $sum_xy - $sum_x * $sum_y;
        $denominator = sqrt(($n * $sum_x2 - $sum_x * $sum_x) * ($n * $sum_y2 - $sum_y * $sum_y));
        
        return $denominator != 0 ? $numerator / $denominator : 0;
    }
    
    private function calculateForecastConfidence($days_ahead, $historical_count) {
        $base_confidence = min(100, ($historical_count / 30) * 100); // Max confidence with 30+ days
        $decay_factor = exp(-$days_ahead / 7); // Confidence decays over time
        return round($base_confidence * $decay_factor, 1);
    }
    
    // Caching methods
    
    private function getFromCache($key) {
        $cache_file = __DIR__ . "/../storage/cache/analytics_{$key}.json";
        
        if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $this->cache_duration) {
            return json_decode(file_get_contents($cache_file), true);
        }
        
        return null;
    }
    
    private function saveToCache($key, $data) {
        $cache_dir = __DIR__ . "/../storage/cache/";
        if (!is_dir($cache_dir)) {
            mkdir($cache_dir, 0755, true);
        }
        
        $cache_file = $cache_dir . "analytics_{$key}.json";
        file_put_contents($cache_file, json_encode($data));
    }
}
?> 