<?php
/**
 * Nayax Revenue Optimizer
 * Intelligent optimization engine for maximizing revenue and customer engagement
 * 
 * @author RevenueQR Team
 * @version 1.0
 * @date 2025-01-17
 */

class NayaxOptimizer {
    
    private $pdo;
    private $analytics_engine;
    
    public function __construct($pdo, $analytics_engine = null) {
        $this->pdo = $pdo;
        $this->analytics_engine = $analytics_engine;
    }
    
    /**
     * Get comprehensive optimization recommendations for a business
     */
    public function getOptimizationRecommendations($business_id, $days = 30) {
        $recommendations = [
            'pricing' => $this->analyzePricingOptimization($business_id, $days),
            'inventory' => $this->analyzeInventoryOptimization($business_id, $days),
            'promotions' => $this->analyzePromotionalOpportunities($business_id, $days),
            'customer_engagement' => $this->analyzeCustomerEngagement($business_id, $days),
            'machine_performance' => $this->analyzeMachineOptimization($business_id, $days),
            'seasonal' => $this->analyzeSeasonalOpportunities($business_id, $days),
            'competitive' => $this->analyzeCompetitivePosition($business_id, $days)
        ];
        
        // Calculate overall optimization score
        $optimization_score = $this->calculateOptimizationScore($recommendations);
        
        return [
            'business_id' => $business_id,
            'generated_at' => date('Y-m-d H:i:s'),
            'optimization_score' => $optimization_score,
            'recommendations' => $recommendations,
            'priority_actions' => $this->prioritizeActions($recommendations),
            'quick_wins' => $this->identifyQuickWins($recommendations),
            'revenue_projections' => $this->projectRevenueImpact($business_id, $recommendations)
        ];
    }
    
    /**
     * Analyze pricing optimization opportunities
     */
    private function analyzePricingOptimization($business_id, $days) {
        // Get current pricing data from business store items
        $stmt = $this->pdo->prepare("
            SELECT 
                bsi.id,
                bsi.item_name,
                bsi.qr_coin_cost,
                bsi.discount_percentage,
                bsi.regular_price_cents,
                COUNT(CASE WHEN nt.transaction_type = 'discount_redemption' THEN 1 END) as redemption_count,
                SUM(CASE WHEN nt.transaction_type = 'discount_redemption' THEN nt.amount_cents END) as total_discount_value,
                AVG(CASE WHEN nt.transaction_type = 'discount_redemption' THEN nt.amount_cents END) as avg_discount_value,
                COUNT(CASE WHEN nt.transaction_type = 'sale' THEN 1 END) as regular_sales
            FROM business_store_items bsi
            LEFT JOIN nayax_transactions nt ON JSON_CONTAINS(nt.transaction_data, JSON_OBJECT('item_name', bsi.item_name))
                AND nt.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND nt.status = 'completed'
            JOIN nayax_machines nm ON nm.business_id = bsi.business_id
            WHERE bsi.business_id = ? 
            GROUP BY bsi.id
            ORDER BY redemption_count DESC
        ");
        $stmt->execute([$business_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $pricing_recommendations = [];
        
        foreach ($items as $item) {
            $recommendations = [];
            
            // Calculate redemption rate
            $total_activity = $item['redemption_count'] + $item['regular_sales'];
            $redemption_rate = $total_activity > 0 ? $item['redemption_count'] / $total_activity : 0;
            
            // Analyze redemption performance
            if ($redemption_rate < 0.2 && $item['qr_coin_cost'] > 100) {
                $recommendations[] = [
                    'type' => 'reduce_price',
                    'current_price' => $item['qr_coin_cost'],
                    'suggested_price' => max(50, $item['qr_coin_cost'] * 0.8),
                    'reason' => 'Low redemption rate (' . round($redemption_rate * 100, 1) . '%) suggests QR coin cost is too high',
                    'impact' => 'medium',
                    'confidence' => 0.75
                ];
            }
            
            if ($redemption_rate > 0.6 && $item['redemption_count'] > 5) {
                $recommendations[] = [
                    'type' => 'increase_price',
                    'current_price' => $item['qr_coin_cost'],
                    'suggested_price' => $item['qr_coin_cost'] * 1.15,
                    'reason' => 'High redemption rate (' . round($redemption_rate * 100, 1) . '%) suggests room for price increase',
                    'impact' => 'high',
                    'confidence' => 0.85
                ];
            }
            
            // Analyze discount effectiveness
            if ($item['discount_percentage'] && $redemption_rate < 0.3) {
                $recommendations[] = [
                    'type' => 'increase_discount',
                    'current_discount' => $item['discount_percentage'],
                    'suggested_discount' => min(50, $item['discount_percentage'] + 5),
                    'reason' => 'Current discount not driving sufficient redemptions',
                    'impact' => 'medium',
                    'confidence' => 0.7
                ];
            }
            
            if (!empty($recommendations)) {
                $pricing_recommendations[] = [
                    'item_id' => $item['id'],
                    'item_name' => $item['item_name'],
                    'current_metrics' => [
                        'qr_coin_cost' => $item['qr_coin_cost'],
                        'discount_percentage' => $item['discount_percentage'],
                        'redemption_rate' => $redemption_rate,
                        'redemption_count' => $item['redemption_count'],
                        'regular_sales' => $item['regular_sales']
                    ],
                    'recommendations' => $recommendations
                ];
            }
        }
        
        return [
            'total_items_analyzed' => count($items),
            'items_with_recommendations' => count($pricing_recommendations),
            'recommendations' => $pricing_recommendations,
            'overall_pricing_health' => $this->calculatePricingHealth($items)
        ];
    }
    
    /**
     * Analyze inventory optimization opportunities
     */
    private function analyzeInventoryOptimization($business_id, $days) {
        // Get inventory performance data from business store items
        $stmt = $this->pdo->prepare("
            SELECT 
                bsi.id,
                bsi.item_name,
                bsi.category,
                COUNT(CASE WHEN nt.transaction_type = 'discount_redemption' THEN 1 END) as demand_score,
                SUM(CASE WHEN nt.transaction_type = 'discount_redemption' THEN nt.amount_cents END) as revenue_contribution,
                AVG(CASE WHEN nt.transaction_type = 'discount_redemption' THEN nt.amount_cents END) as avg_transaction_value,
                COUNT(CASE WHEN nt.transaction_type = 'sale' THEN 1 END) as regular_sales,
                COUNT(nt.id) as total_activity
            FROM business_store_items bsi
            LEFT JOIN nayax_transactions nt ON JSON_CONTAINS(nt.transaction_data, JSON_OBJECT('item_name', bsi.item_name))
                AND nt.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND nt.status = 'completed'
            WHERE bsi.business_id = ? 
            GROUP BY bsi.id
                         ORDER BY demand_score DESC
        ");
        $stmt->execute([$business_id]);
        $inventory_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $inventory_recommendations = [];
        
        // High performers - promote more
        $top_performers = array_slice($inventory_data, 0, 3);
        foreach ($top_performers as $item) {
            if ($item['demand_score'] > 5) {
                $inventory_recommendations[] = [
                    'type' => 'promote_high_performer',
                    'item_id' => $item['id'],
                    'item_name' => $item['item_name'],
                    'action' => 'Increase visibility and stock',
                    'reason' => "High demand ({$item['demand_score']} purchases)",
                    'impact' => 'high',
                    'priority' => 'high'
                ];
            }
        }
        
        // Low performers - investigate or remove
        $low_performers = array_filter($inventory_data, function($item) {
            return $item['demand_score'] == 0;
        });
        
        foreach ($low_performers as $item) {
            $inventory_recommendations[] = [
                'type' => 'investigate_low_performer',
                'item_id' => $item['id'],
                'item_name' => $item['item_name'],
                'action' => 'Review pricing or remove from inventory',
                'reason' => 'Zero purchases in analysis period',
                'impact' => 'medium',
                'priority' => 'medium'
            ];
        }
        
        // Category analysis
        $category_performance = [];
        foreach ($inventory_data as $item) {
            $category = $item['category'] ?: 'Uncategorized';
            if (!isset($category_performance[$category])) {
                $category_performance[$category] = [
                    'total_demand' => 0,
                    'total_revenue' => 0,
                    'item_count' => 0
                ];
            }
            $category_performance[$category]['total_demand'] += $item['demand_score'];
            $category_performance[$category]['total_revenue'] += $item['revenue_contribution'];
            $category_performance[$category]['item_count']++;
        }
        
        // Find underrepresented high-performing categories
        foreach ($category_performance as $category => $performance) {
            $avg_demand_per_item = $performance['item_count'] > 0 ? 
                $performance['total_demand'] / $performance['item_count'] : 0;
            
            if ($avg_demand_per_item > 3 && $performance['item_count'] < 3) {
                $inventory_recommendations[] = [
                    'type' => 'expand_category',
                    'category' => $category,
                    'action' => 'Add more items to this category',
                    'reason' => "High-performing category with only {$performance['item_count']} items",
                    'impact' => 'medium',
                    'priority' => 'medium'
                ];
            }
        }
        
        return [
            'total_items' => count($inventory_data),
            'high_performers' => count($top_performers),
            'low_performers' => count($low_performers),
            'category_performance' => $category_performance,
            'recommendations' => $inventory_recommendations
        ];
    }
    
    /**
     * Analyze promotional opportunities
     */
    private function analyzePromotionalOpportunities($business_id, $days) {
        // Get transaction patterns for promotional timing
        $stmt = $this->pdo->prepare("
            SELECT 
                HOUR(nt.created_at) as hour,
                DAYOFWEEK(nt.created_at) as day_of_week,
                DATE(nt.created_at) as date,
                COUNT(*) as transaction_count,
                SUM(nt.amount_cents) as revenue_cents,
                AVG(nt.amount_cents) as avg_transaction_value
            FROM nayax_transactions nt
            JOIN nayax_machines nm ON nt.nayax_machine_id = nm.nayax_machine_id
            WHERE nm.business_id = ? 
            AND nt.status = 'completed'
            AND nt.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY hour, day_of_week, date
            ORDER BY transaction_count DESC
        ");
        $stmt->execute([$business_id, $days]);
        $patterns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $promotional_opportunities = [];
        
        // Identify low-activity periods for promotions
        $hourly_activity = [];
        foreach ($patterns as $pattern) {
            $hour = $pattern['hour'];
            if (!isset($hourly_activity[$hour])) {
                $hourly_activity[$hour] = ['count' => 0, 'total_transactions' => 0];
            }
            $hourly_activity[$hour]['count']++;
            $hourly_activity[$hour]['total_transactions'] += $pattern['transaction_count'];
        }
        
        // Calculate average activity per hour
        foreach ($hourly_activity as $hour => $data) {
            $hourly_activity[$hour]['avg_transactions'] = $data['count'] > 0 ? $data['total_transactions'] / $data['count'] : 0;
        }
        
        // Find low-activity hours for promotions
        $overall_avg = count($hourly_activity) > 0 ? array_sum(array_column($hourly_activity, 'avg_transactions')) / count($hourly_activity) : 0;
        
        foreach ($hourly_activity as $hour => $data) {
            if ($data['avg_transactions'] < $overall_avg * 0.6) {
                $promotional_opportunities[] = [
                    'type' => 'time_based_promotion',
                    'target_time' => $hour . ':00 - ' . ($hour + 1) . ':00',
                    'current_activity' => round($data['avg_transactions'], 1),
                    'opportunity' => 'Happy hour discount or special offers',
                    'potential_lift' => '25-40%',
                    'impact' => 'medium',
                    'implementation_effort' => 'low'
                ];
            }
        }
        
        // Seasonal/weekly opportunities
        $day_names = ['', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $daily_activity = [];
        
        foreach ($patterns as $pattern) {
            $day = $pattern['day_of_week'];
            if (!isset($daily_activity[$day])) {
                $daily_activity[$day] = ['count' => 0, 'total_transactions' => 0];
            }
            $daily_activity[$day]['count']++;
            $daily_activity[$day]['total_transactions'] += $pattern['transaction_count'];
        }
        
        foreach ($daily_activity as $day => $data) {
            $daily_activity[$day]['avg_transactions'] = $data['count'] > 0 ? $data['total_transactions'] / $data['count'] : 0;
        }
        
        $daily_avg = count($daily_activity) > 0 ? array_sum(array_column($daily_activity, 'avg_transactions')) / count($daily_activity) : 0;
        
        foreach ($daily_activity as $day => $data) {
            if ($data['avg_transactions'] < $daily_avg * 0.7) {
                $promotional_opportunities[] = [
                    'type' => 'day_based_promotion',
                    'target_day' => $day_names[$day],
                    'current_activity' => round($data['avg_transactions'], 1),
                    'opportunity' => $day_names[$day] . ' special offers or themed promotions',
                    'potential_lift' => '15-30%',
                    'impact' => 'medium',
                    'implementation_effort' => 'medium'
                ];
            }
        }
        
        // Bundle opportunities
        $promotional_opportunities[] = [
            'type' => 'bundle_promotion',
            'opportunity' => 'Create QR coin bundle packs with bonus coins',
            'description' => 'Offer bonus coins for larger purchases (e.g., buy 500 coins, get 50 free)',
            'potential_lift' => '20-35%',
            'impact' => 'high',
            'implementation_effort' => 'medium'
        ];
        
        return [
            'total_opportunities' => count($promotional_opportunities),
            'opportunities' => $promotional_opportunities,
            'best_promotion_times' => $this->findBestPromotionTimes($hourly_activity),
            'seasonal_insights' => $this->analyzeSeasonalTrends($patterns)
        ];
    }
    
    /**
     * Analyze customer engagement optimization
     */
    private function analyzeCustomerEngagement($business_id, $days) {
        // Get customer engagement metrics from transaction data
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(DISTINCT nt.user_id) as total_customers,
                COUNT(nt.id) as total_transactions,
                SUM(nt.amount_cents) as total_revenue_cents,
                AVG(nt.amount_cents) as avg_transaction_value,
                COUNT(CASE WHEN nt.transaction_type = 'qr_coin_purchase' THEN 1 END) as qr_purchases,
                COUNT(CASE WHEN nt.transaction_type = 'sale' THEN 1 END) as regular_sales
            FROM nayax_transactions nt
            JOIN nayax_machines nm ON nt.nayax_machine_id = nm.nayax_machine_id
            WHERE nm.business_id = ? 
            AND nt.user_id IS NOT NULL
            AND nt.status = 'completed'
            AND nt.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$business_id]);
        $engagement_metrics = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $engagement_recommendations = [];
        
        // QR adoption rate
        $qr_adoption_rate = $engagement_metrics['total_transactions'] > 0 ? 
            ($engagement_metrics['qr_purchases'] / $engagement_metrics['total_transactions']) * 100 : 0;
        
        if ($qr_adoption_rate < 30) {
            $engagement_recommendations[] = [
                'type' => 'improve_qr_adoption',
                'issue' => 'Low QR coin adoption',
                'current_rate' => round($qr_adoption_rate, 1) . '%',
                'target_rate' => '40%+',
                'recommendations' => [
                    'Promote QR coin benefits more prominently',
                    'Offer first-time QR user bonuses',
                    'Create educational content about QR coins',
                    'Implement referral rewards for QR usage'
                ],
                'impact' => 'high',
                'priority' => 'high'
            ];
        }
        
        // Transaction frequency (based on customers vs transactions)
        $avg_transactions_per_customer = $engagement_metrics['total_customers'] > 0 ? 
            $engagement_metrics['total_transactions'] / $engagement_metrics['total_customers'] : 0;
        
        if ($avg_transactions_per_customer < 2) {
            $engagement_recommendations[] = [
                'type' => 'increase_frequency',
                'issue' => 'Low transaction frequency',
                'current_frequency' => round($avg_transactions_per_customer, 1) . ' transactions per customer',
                'target_frequency' => '3+ transactions per customer',
                'recommendations' => [
                    'Send reminder notifications',
                    'Offer limited-time discounts',
                    'Create urgency with expiring offers',
                    'Implement loyalty rewards'
                ],
                'impact' => 'medium',
                'priority' => 'medium'
            ];
        }
        
        // Revenue per customer
        $avg_revenue_per_customer = $engagement_metrics['total_customers'] > 0 ? 
            $engagement_metrics['total_revenue_cents'] / $engagement_metrics['total_customers'] / 100 : 0;
        
        if ($avg_revenue_per_customer < 20) {
            $engagement_recommendations[] = [
                'type' => 'increase_customer_value',
                'issue' => 'Low revenue per customer',
                'current_value' => '$' . round($avg_revenue_per_customer, 2),
                'target_value' => '$30+',
                'recommendations' => [
                    'Promote higher-value items',
                    'Create bundle offers',
                    'Implement upselling strategies',
                    'Offer volume discounts'
                ],
                'impact' => 'high',
                'priority' => 'medium'
            ];
        }
        
        return [
            'engagement_metrics' => $engagement_metrics,
            'engagement_score' => $this->calculateEngagementScore($engagement_metrics),
            'recommendations' => $engagement_recommendations,
            'retention_opportunities' => $this->identifyRetentionOpportunities($business_id, $days)
        ];
    }
    
    /**
     * Analyze machine performance optimization
     */
    private function analyzeMachineOptimization($business_id, $days) {
        // Get machine performance data
        $stmt = $this->pdo->prepare("
            SELECT 
                nm.nayax_machine_id,
                nm.machine_name,
                nm.location_description as location,
                COUNT(nt.id) as total_transactions,
                SUM(nt.amount_cents) as total_revenue_cents,
                AVG(nt.amount_cents) as avg_transaction_value,
                COUNT(CASE WHEN nt.transaction_type = 'qr_coin_purchase' THEN 1 END) as qr_transactions,
                COUNT(CASE WHEN nt.transaction_type = 'sale' THEN 1 END) as regular_transactions,
                MAX(nt.created_at) as last_transaction,
                DATEDIFF(NOW(), MAX(nt.created_at)) as days_since_last_transaction
            FROM nayax_machines nm
            LEFT JOIN nayax_transactions nt ON nm.nayax_machine_id = nt.nayax_machine_id 
                AND nt.status = 'completed'
                AND nt.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            WHERE nm.business_id = ?
            GROUP BY nm.nayax_machine_id
            ORDER BY total_revenue_cents DESC
        ");
        $stmt->execute([$days, $business_id]);
        $machines = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $machine_recommendations = [];
        
        foreach ($machines as $machine) {
            $recommendations = [];
            
            // Inactive machines
            if ($machine['total_transactions'] == 0) {
                $recommendations[] = [
                    'type' => 'activate_machine',
                    'priority' => 'high',
                    'issue' => 'No transactions recorded',
                    'actions' => [
                        'Check machine connectivity',
                        'Verify QR code placement',
                        'Test payment processing',
                        'Review machine location and visibility'
                    ]
                ];
            }
            
            // Low QR adoption
            $qr_adoption_rate = $machine['total_transactions'] > 0 ? 
                ($machine['qr_transactions'] / $machine['total_transactions']) * 100 : 0;
            
            if ($qr_adoption_rate < 20 && $machine['total_transactions'] > 0) {
                $recommendations[] = [
                    'type' => 'improve_qr_adoption',
                    'priority' => 'medium',
                    'current_rate' => round($qr_adoption_rate, 1) . '%',
                    'target_rate' => '40%+',
                    'actions' => [
                        'Improve QR code visibility',
                        'Add promotional signage',
                        'Offer first-time QR user bonus',
                        'Train staff on QR promotion'
                    ]
                ];
            }
            
            // Stale machines
            if ($machine['days_since_last_transaction'] > 7 && $machine['total_transactions'] > 0) {
                $recommendations[] = [
                    'type' => 'reactivate_machine',
                    'priority' => 'medium',
                    'issue' => "No activity for {$machine['days_since_last_transaction']} days",
                    'actions' => [
                        'Check for technical issues',
                        'Refresh promotional materials',
                        'Consider location change',
                        'Launch reactivation campaign'
                    ]
                ];
            }
            
            if (!empty($recommendations)) {
                $machine_recommendations[] = [
                    'machine_id' => $machine['nayax_machine_id'],
                    'machine_name' => $machine['machine_name'],
                    'location' => $machine['location'],
                    'performance_metrics' => [
                        'total_transactions' => $machine['total_transactions'],
                        'total_revenue' => $machine['total_revenue_cents'] / 100,
                        'qr_adoption_rate' => round($qr_adoption_rate, 1),
                        'days_since_last_transaction' => $machine['days_since_last_transaction']
                    ],
                    'recommendations' => $recommendations
                ];
            }
        }
        
        return [
            'total_machines' => count($machines),
            'machines_needing_attention' => count($machine_recommendations),
            'recommendations' => $machine_recommendations,
            'performance_summary' => $this->calculateMachinePerformanceSummary($machines)
        ];
    }
    
    /**
     * Calculate optimization score
     */
    private function calculateOptimizationScore($recommendations) {
        $total_score = 100;
        
        // Deduct points for each category with issues
        foreach ($recommendations as $category => $data) {
            if (isset($data['recommendations']) && !empty($data['recommendations'])) {
                $total_score -= count($data['recommendations']) * 5;
            }
        }
        
        return max(0, min(100, $total_score));
    }
    
    /**
     * Prioritize actions based on impact and effort
     */
    private function prioritizeActions($recommendations) {
        $all_actions = [];
        
        foreach ($recommendations as $category => $data) {
            if (isset($data['recommendations'])) {
                foreach ($data['recommendations'] as $rec) {
                    if (is_array($rec) && isset($rec['impact'])) {
                        $all_actions[] = array_merge($rec, ['category' => $category]);
                    }
                }
            }
        }
        
        // Sort by impact (high > medium > low) and effort (low > medium > high)
        usort($all_actions, function($a, $b) {
            $impact_order = ['high' => 3, 'medium' => 2, 'low' => 1];
            $effort_order = ['low' => 3, 'medium' => 2, 'high' => 1];
            
            $a_score = ($impact_order[$a['impact']] ?? 0) + ($effort_order[$a['effort'] ?? 'medium'] ?? 0);
            $b_score = ($impact_order[$b['impact']] ?? 0) + ($effort_order[$b['effort'] ?? 'medium'] ?? 0);
            
            return $b_score - $a_score;
        });
        
        return array_slice($all_actions, 0, 10); // Top 10 priority actions
    }
    
    /**
     * Identify quick wins
     */
    private function identifyQuickWins($recommendations) {
        $quick_wins = [];
        
        foreach ($recommendations as $category => $data) {
            if (isset($data['recommendations'])) {
                foreach ($data['recommendations'] as $rec) {
                    if (is_array($rec) && 
                        isset($rec['impact'], $rec['effort']) && 
                        $rec['impact'] !== 'low' && 
                        ($rec['effort'] === 'low' || $rec['implementation_effort'] === 'low')) {
                        $quick_wins[] = array_merge($rec, ['category' => $category]);
                    }
                }
            }
        }
        
        return array_slice($quick_wins, 0, 5); // Top 5 quick wins
    }
    
    /**
     * Project revenue impact of recommendations
     */
    private function projectRevenueImpact($business_id, $recommendations) {
        // Get current revenue baseline
        $stmt = $this->pdo->prepare("
            SELECT SUM(amount_cents) as current_revenue_cents
            FROM nayax_transactions nt
            JOIN nayax_machines nm ON nt.nayax_machine_id = nm.nayax_machine_id
            WHERE nm.business_id = ? 
            AND nt.status = 'completed'
            AND nt.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$business_id]);
        $current_revenue = $stmt->fetchColumn() ?: 0;
        
        // Estimate impact of implementing recommendations
        $potential_lift_ranges = [
            'pricing' => ['min' => 0.05, 'max' => 0.20],  // 5-20% lift
            'promotions' => ['min' => 0.10, 'max' => 0.30], // 10-30% lift
            'customer_engagement' => ['min' => 0.15, 'max' => 0.40], // 15-40% lift
            'machine_performance' => ['min' => 0.05, 'max' => 0.25]  // 5-25% lift
        ];
        
        $projected_impacts = [];
        
        foreach ($potential_lift_ranges as $category => $range) {
            if (isset($recommendations[$category]['recommendations']) && 
                !empty($recommendations[$category]['recommendations'])) {
                
                $conservative_lift = $range['min'];
                $optimistic_lift = $range['max'];
                
                $projected_impacts[$category] = [
                    'conservative_increase' => $current_revenue * $conservative_lift / 100,
                    'optimistic_increase' => $current_revenue * $optimistic_lift / 100,
                    'implementation_timeline' => $this->estimateImplementationTime($category)
                ];
            }
        }
        
        $total_conservative = array_sum(array_column($projected_impacts, 'conservative_increase'));
        $total_optimistic = array_sum(array_column($projected_impacts, 'optimistic_increase'));
        
        return [
            'current_monthly_revenue' => $current_revenue / 100,
            'conservative_projection' => ($current_revenue + $total_conservative) / 100,
            'optimistic_projection' => ($current_revenue + $total_optimistic) / 100,
            'potential_increase_range' => [
                'min_dollars' => $total_conservative / 100,
                'max_dollars' => $total_optimistic / 100,
                'min_percentage' => $current_revenue > 0 ? ($total_conservative / $current_revenue) * 100 : 0,
                'max_percentage' => $current_revenue > 0 ? ($total_optimistic / $current_revenue) * 100 : 0
            ],
            'category_impacts' => $projected_impacts
        ];
    }
    
    // Helper methods
    
    private function calculatePricingHealth($items) {
        if (empty($items)) return 'unknown';
        
        $total_usage_rate = array_sum(array_column($items, 'usage_rate')) / count($items);
        
        if ($total_usage_rate > 0.7) return 'excellent';
        if ($total_usage_rate > 0.5) return 'good';
        if ($total_usage_rate > 0.3) return 'fair';
        return 'poor';
    }
    
    private function findBestPromotionTimes($hourly_activity) {
        $best_times = [];
        
        foreach ($hourly_activity as $hour => $data) {
            $best_times[] = [
                'hour' => $hour,
                'avg_transactions' => $data['avg_transactions'],
                'promotion_potential' => $data['avg_transactions'] < 2 ? 'high' : 'medium'
            ];
        }
        
        usort($best_times, function($a, $b) {
            return $a['avg_transactions'] - $b['avg_transactions'];
        });
        
        return array_slice($best_times, 0, 3);
    }
    
    private function analyzeSeasonalTrends($patterns) {
        // Simple seasonal analysis - could be expanded
        $weekday_avg = 0;
        $weekend_avg = 0;
        $weekday_count = 0;
        $weekend_count = 0;
        
        foreach ($patterns as $pattern) {
            if ($pattern['day_of_week'] >= 2 && $pattern['day_of_week'] <= 6) {
                $weekday_avg += $pattern['transaction_count'];
                $weekday_count++;
            } else {
                $weekend_avg += $pattern['transaction_count'];
                $weekend_count++;
            }
        }
        
        $weekday_avg = $weekday_count > 0 ? $weekday_avg / $weekday_count : 0;
        $weekend_avg = $weekend_count > 0 ? $weekend_avg / $weekend_count : 0;
        
        return [
            'weekday_performance' => round($weekday_avg, 1),
            'weekend_performance' => round($weekend_avg, 1),
            'stronger_period' => $weekday_avg > $weekend_avg ? 'weekdays' : 'weekends',
            'seasonal_opportunity' => $weekday_avg > $weekend_avg ? 
                'Focus weekend promotions' : 'Enhance weekday offerings'
        ];
    }
    
    private function calculateEngagementScore($metrics) {
        $score = 50; // Base score
        
        // Calculate transactions per customer
        $transactions_per_customer = $metrics['total_customers'] > 0 ? 
            $metrics['total_transactions'] / $metrics['total_customers'] : 0;
        
        // Adjust based on transaction frequency
        if ($transactions_per_customer > 3) $score += 20;
        elseif ($transactions_per_customer > 2) $score += 10;
        elseif ($transactions_per_customer < 1.5) $score -= 10;
        
        // QR adoption rate
        $qr_adoption_rate = $metrics['total_transactions'] > 0 ? 
            ($metrics['qr_purchases'] / $metrics['total_transactions']) * 100 : 0;
        
        if ($qr_adoption_rate > 40) $score += 20;
        elseif ($qr_adoption_rate > 25) $score += 10;
        elseif ($qr_adoption_rate < 15) $score -= 10;
        
        // Revenue per customer
        $revenue_per_customer = $metrics['total_customers'] > 0 ? 
            $metrics['total_revenue_cents'] / $metrics['total_customers'] / 100 : 0;
        
        if ($revenue_per_customer > 30) $score += 10;
        elseif ($revenue_per_customer < 15) $score -= 10;
        
        return max(0, min(100, $score));
    }
    
    private function identifyRetentionOpportunities($business_id, $days) {
        // Get customer activity patterns from transactions
        $stmt = $this->pdo->prepare("
            SELECT 
                nt.user_id,
                COUNT(nt.id) as transaction_count,
                MAX(nt.created_at) as last_transaction,
                DATEDIFF(NOW(), MAX(nt.created_at)) as days_since_last_transaction,
                SUM(nt.amount_cents) as total_spent_cents
            FROM nayax_transactions nt
            JOIN nayax_machines nm ON nt.nayax_machine_id = nm.nayax_machine_id
            WHERE nm.business_id = ? 
            AND nt.user_id IS NOT NULL
            AND nt.status = 'completed'
            AND nt.created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY)
            GROUP BY nt.user_id
            HAVING days_since_last_transaction > 14
            ORDER BY transaction_count DESC, days_since_last_transaction ASC
            LIMIT 20
        ");
        $stmt->execute([$business_id]);
        $at_risk_customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'at_risk_count' => count($at_risk_customers),
            'at_risk_customers' => $at_risk_customers,
            'retention_strategies' => [
                'win_back_campaign' => 'Send personalized win-back offers',
                'limited_time_discount' => 'Offer 24-hour flash discounts',
                'loyalty_bonus' => 'Provide bonus coins for returning',
                'feedback_request' => 'Ask for feedback and address concerns'
            ]
        ];
    }
    
    private function calculateMachinePerformanceSummary($machines) {
        $total_machines = count($machines);
        $active_machines = count(array_filter($machines, function($m) { 
            return $m['total_transactions'] > 0; 
        }));
        $high_performers = count(array_filter($machines, function($m) { 
            return $m['total_transactions'] > 10; 
        }));
        
        return [
            'total_machines' => $total_machines,
            'active_machines' => $active_machines,
            'high_performers' => $high_performers,
            'activation_rate' => $total_machines > 0 ? ($active_machines / $total_machines) * 100 : 0,
            'performance_rate' => $total_machines > 0 ? ($high_performers / $total_machines) * 100 : 0
        ];
    }
    
    private function estimateImplementationTime($category) {
        $timelines = [
            'pricing' => '1-2 weeks',
            'inventory' => '2-4 weeks',
            'promotions' => '1-3 weeks',
            'customer_engagement' => '3-6 weeks',
            'machine_performance' => '1-4 weeks',
            'seasonal' => '2-8 weeks',
            'competitive' => '4-8 weeks'
        ];
        
        return $timelines[$category] ?? '2-4 weeks';
    }
    
    /**
     * Analyze seasonal opportunities (placeholder)
     */
    private function analyzeSeasonalOpportunities($business_id, $days) {
        return [
            'seasonal_trends' => [],
            'recommendations' => [],
            'best_promotion_periods' => []
        ];
    }
    
    /**
     * Analyze competitive position (placeholder)
     */
    private function analyzeCompetitivePosition($business_id, $days) {
        return [
            'competitive_analysis' => [],
            'recommendations' => [],
            'market_opportunities' => []
        ];
    }
}
?> 