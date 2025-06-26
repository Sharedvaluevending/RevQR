<?php
/**
 * Business System Detector
 * Detects which systems (Manual/Nayax) are available for each business
 * Provides routing and capability detection for seamless vendor experience
 */

require_once __DIR__ . '/config.php';

class BusinessSystemDetector {
    private static $pdo;
    private static $cache = [];
    
    public static function init($pdo) {
        self::$pdo = $pdo;
    }
    
    /**
     * Get complete business system capabilities
     */
    public static function getBusinessCapabilities($business_id) {
        // Check cache first
        $cache_key = "business_capabilities_{$business_id}";
        if (isset(self::$cache[$cache_key])) {
            return self::$cache[$cache_key];
        }
        
        try {
            // Get manual machines count
            $manual_stmt = self::$pdo->prepare("
                SELECT COUNT(*) as count 
                FROM voting_lists 
                WHERE business_id = ?
            ");
            $manual_stmt->execute([$business_id]);
            $manual_count = $manual_stmt->fetch()['count'] ?? 0;
            
            // Get Nayax machines count
            $nayax_stmt = self::$pdo->prepare("
                SELECT COUNT(*) as count 
                FROM nayax_machines 
                WHERE business_id = ? AND status != 'inactive'
            ");
            $nayax_stmt->execute([$business_id]);
            $nayax_count = $nayax_stmt->fetch()['count'] ?? 0;
            
            // Determine primary system based on machine count and activity
            $primary_system = self::determinePrimarySystem($business_id, $manual_count, $nayax_count);
            
            // Build capabilities object
            $capabilities = [
                'business_id' => $business_id,
                'has_manual' => $manual_count > 0,
                'has_nayax' => $nayax_count > 0,
                'manual_count' => $manual_count,
                'nayax_count' => $nayax_count,
                'total_machines' => $manual_count + $nayax_count,
                'primary_system' => $primary_system,
                'is_unified' => $manual_count > 0 && $nayax_count > 0,
                'system_mode' => self::getSystemMode($manual_count, $nayax_count),
                'available_features' => self::getAvailableFeatures($manual_count > 0, $nayax_count > 0),
                'dashboard_config' => self::getDashboardConfig($manual_count > 0, $nayax_count > 0),
                'navigation_config' => self::getNavigationConfig($manual_count > 0, $nayax_count > 0)
            ];
            
            // Cache result for 5 minutes
            self::$cache[$cache_key] = $capabilities;
            
            return $capabilities;
            
        } catch (Exception $e) {
            error_log("BusinessSystemDetector Error: " . $e->getMessage());
            return self::getDefaultCapabilities($business_id);
        }
    }
    
    /**
     * Determine the primary system based on usage and machine count
     */
    private static function determinePrimarySystem($business_id, $manual_count, $nayax_count) {
        if ($manual_count == 0 && $nayax_count == 0) {
            return 'none';
        }
        
        if ($manual_count == 0) {
            return 'nayax';
        }
        
        if ($nayax_count == 0) {
            return 'manual';
        }
        
        // Both systems exist - check recent activity to determine primary
        try {
            // Check recent manual activity (votes in last 30 days)
            $manual_activity_stmt = self::$pdo->prepare("
                SELECT COUNT(*) as count 
                FROM votes v
                JOIN voting_lists vl ON v.voting_list_id = vl.id
                WHERE vl.business_id = ? 
                AND v.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $manual_activity_stmt->execute([$business_id]);
            $manual_activity = $manual_activity_stmt->fetch()['count'] ?? 0;
            
            // Check recent Nayax activity (transactions in last 30 days)
            $nayax_activity_stmt = self::$pdo->prepare("
                SELECT COUNT(*) as count 
                FROM nayax_transactions nt
                JOIN nayax_machines nm ON nt.machine_id = nm.nayax_machine_id
                WHERE nm.business_id = ? 
                AND nt.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $nayax_activity_stmt->execute([$business_id]);
            $nayax_activity = $nayax_activity_stmt->fetch()['count'] ?? 0;
            
            // Determine primary based on activity
            if ($nayax_activity > $manual_activity * 2) {
                return 'nayax';
            } elseif ($manual_activity > $nayax_activity * 2) {
                return 'manual';
            } else {
                // Close activity - use machine count
                return $nayax_count >= $manual_count ? 'nayax' : 'manual';
            }
            
        } catch (Exception $e) {
            // Fallback to machine count
            return $nayax_count >= $manual_count ? 'nayax' : 'manual';
        }
    }
    
    /**
     * Get system mode description
     */
    private static function getSystemMode($manual_count, $nayax_count) {
        if ($manual_count > 0 && $nayax_count > 0) {
            return 'unified';
        } elseif ($nayax_count > 0) {
            return 'nayax_only';
        } elseif ($manual_count > 0) {
            return 'manual_only';
        } else {
            return 'no_machines';
        }
    }
    
    /**
     * Get available features based on system capabilities
     */
    private static function getAvailableFeatures($has_manual, $has_nayax) {
        $features = [];
        
        if ($has_manual) {
            $features = array_merge($features, [
                'qr_generation',
                'voting_campaigns',
                'manual_sales',
                'spin_wheel',
                'casino_games',
                'user_avatars',
                'qr_coin_economy',
                'promotional_campaigns'
            ]);
        }
        
        if ($has_nayax) {
            $features = array_merge($features, [
                'real_time_monitoring',
                'automated_transactions',
                'advanced_analytics',
                'customer_intelligence',
                'inventory_tracking',
                'payment_processing',
                'machine_diagnostics',
                'revenue_optimization'
            ]);
        }
        
        if ($has_manual && $has_nayax) {
            $features = array_merge($features, [
                'cross_system_analytics',
                'unified_reporting',
                'customer_journey_tracking',
                'inventory_synchronization',
                'performance_comparison',
                'upgrade_recommendations'
            ]);
        }
        
        return array_unique($features);
    }
    
    /**
     * Get dashboard configuration based on available systems
     */
    private static function getDashboardConfig($has_manual, $has_nayax) {
        $config = [
            'cards' => [],
            'layout' => 'default',
            'primary_metrics' => [],
            'secondary_metrics' => []
        ];
        
        if ($has_manual && $has_nayax) {
            // Unified dashboard
            $config['layout'] = 'unified';
            $config['cards'] = [
                'system_overview',
                'unified_analytics',
                'machine_performance',
                'revenue_comparison',
                'customer_insights',
                'inventory_status',
                'recent_activity',
                'recommendations'
            ];
        } elseif ($has_nayax) {
            // Nayax-focused dashboard
            $config['layout'] = 'nayax_focused';
            $config['cards'] = [
                'nayax_overview',
                'machine_status',
                'revenue_analytics',
                'customer_intelligence',
                'transaction_history',
                'alerts_notifications'
            ];
        } elseif ($has_manual) {
            // Manual-focused dashboard
            $config['layout'] = 'manual_focused';
            $config['cards'] = [
                'campaign_overview',
                'voting_analytics',
                'qr_performance',
                'engagement_metrics',
                'casino_stats',
                'recent_votes'
            ];
        } else {
            // Setup dashboard
            $config['layout'] = 'setup';
            $config['cards'] = [
                'welcome_setup',
                'system_selection',
                'getting_started'
            ];
        }
        
        return $config;
    }
    
    /**
     * Get navigation configuration
     */
    private static function getNavigationConfig($has_manual, $has_nayax) {
        $config = [
            'show_manual_nav' => $has_manual,
            'show_nayax_nav' => $has_nayax,
            'primary_nav' => $has_manual && $has_nayax ? 'unified' : ($has_nayax ? 'nayax' : 'manual'),
            'nav_items' => []
        ];
        
        if ($has_manual) {
            $config['nav_items'] = array_merge($config['nav_items'], [
                'campaigns',
                'qr_manager',
                'voting_results',
                'spin_wheel',
                'casino'
            ]);
        }
        
        if ($has_nayax) {
            $config['nav_items'] = array_merge($config['nav_items'], [
                'nayax_analytics',
                'machine_monitoring',
                'customer_intelligence',
                'revenue_reports'
            ]);
        }
        
        if ($has_manual && $has_nayax) {
            $config['nav_items'] = array_merge($config['nav_items'], [
                'unified_analytics',
                'system_comparison',
                'upgrade_recommendations'
            ]);
        }
        
        return $config;
    }
    
    /**
     * Get default capabilities for error cases
     */
    private static function getDefaultCapabilities($business_id) {
        return [
            'business_id' => $business_id,
            'has_manual' => false,
            'has_nayax' => false,
            'manual_count' => 0,
            'nayax_count' => 0,
            'total_machines' => 0,
            'primary_system' => 'none',
            'is_unified' => false,
            'system_mode' => 'no_machines',
            'available_features' => [],
            'dashboard_config' => ['layout' => 'setup', 'cards' => ['welcome_setup']],
            'navigation_config' => ['show_manual_nav' => false, 'show_nayax_nav' => false]
        ];
    }
    
    /**
     * Quick check if business has specific capability
     */
    public static function hasCapability($business_id, $capability) {
        $capabilities = self::getBusinessCapabilities($business_id);
        return in_array($capability, $capabilities['available_features']);
    }
    
    /**
     * Get recommended next steps for business
     */
    public static function getRecommendations($business_id) {
        $capabilities = self::getBusinessCapabilities($business_id);
        $recommendations = [];
        
        if ($capabilities['total_machines'] == 0) {
            $recommendations[] = [
                'type' => 'setup',
                'title' => 'Set Up Your First Machine',
                'description' => 'Add your first vending machine to start using the platform',
                'action' => 'Add Machine',
                'url' => '/business/machines.php?action=add'
            ];
        } elseif ($capabilities['system_mode'] == 'manual_only') {
            $recommendations[] = [
                'type' => 'upgrade',
                'title' => 'Consider Nayax Integration',
                'description' => 'Upgrade to real-time monitoring and automated transactions',
                'action' => 'Learn More',
                'url' => '/business/nayax-info.php'
            ];
        } elseif ($capabilities['system_mode'] == 'nayax_only') {
            $recommendations[] = [
                'type' => 'enhance',
                'title' => 'Add QR Campaign Features',
                'description' => 'Enhance customer engagement with voting and gamification',
                'action' => 'Explore QR Features',
                'url' => '/business/qr-features.php'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Clear cache for a business (call when machines are added/removed)
     */
    public static function clearCache($business_id = null) {
        if ($business_id) {
            unset(self::$cache["business_capabilities_{$business_id}"]);
        } else {
            self::$cache = [];
        }
    }
}

// Initialize with global PDO if available
if (isset($pdo)) {
    BusinessSystemDetector::init($pdo);
}
?> 