<?php
/**
 * Business QR Manager for QR Coin Economy 2.0
 * Handles business subscriptions, QR coin allowances, and business-specific operations
 * 
 * @author QR Coin Economy Team
 * @version 1.0
 * @date 2025-01-17
 */

require_once __DIR__ . '/config_manager.php';
require_once __DIR__ . '/qr_coin_manager.php';

class BusinessQRManager {
    
    /**
     * Get business subscription details
     * 
     * @param int $business_id Business ID
     * @return array|false Subscription details or false if not found
     */
    public static function getSubscription($business_id) {
        global $pdo;
        
        if (!$business_id) return false;
        
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM business_subscriptions 
                WHERE business_id = ? AND status IN ('trial', 'active')
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt->execute([$business_id]);
            $subscription = $stmt->fetch();
            
            if ($subscription && $subscription['features']) {
                $subscription['features'] = json_decode($subscription['features'], true);
            }
            
            return $subscription;
            
        } catch (PDOException $e) {
            error_log("BusinessQRManager::getSubscription() error for business $business_id: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if business can spend QR coins
     * 
     * @param int $business_id Business ID
     * @param int $amount Amount to spend
     * @return bool Whether business has enough allowance
     */
    public static function canSpendCoins($business_id, $amount) {
        $subscription = self::getSubscription($business_id);
        if (!$subscription) {
            return false;
        }
        
        $available = $subscription['qr_coin_allowance'] - $subscription['qr_coins_used'];
        return $available >= $amount;
    }
    
    /**
     * Spend business QR coins
     * 
     * @param int $business_id Business ID
     * @param int $amount Amount to spend
     * @param string $category Spending category
     * @param string $description Description
     * @param array $metadata Optional metadata
     * @return bool Success status
     */
    public static function spendCoins($business_id, $amount, $category, $description, $metadata = null) {
        if (!self::canSpendCoins($business_id, $amount)) {
            return false;
        }
        
        global $pdo;
        
        try {
            $pdo->beginTransaction();
            
            // Update subscription usage
            $stmt = $pdo->prepare("
                UPDATE business_subscriptions 
                SET qr_coins_used = qr_coins_used + ? 
                WHERE business_id = ? AND status IN ('trial', 'active')
            ");
            $stmt->execute([$amount, $business_id]);
            
            // Log transaction for business owner
            $business_owner_id = self::getBusinessOwner($business_id);
            if ($business_owner_id) {
                QRCoinManager::addTransaction(
                    $business_owner_id,
                    'business_purchase',
                    $category,
                    -$amount,
                    $description,
                    array_merge($metadata ?? [], ['business_id' => $business_id])
                );
            }
            
            $pdo->commit();
            return true;
            
        } catch (PDOException $e) {
            $pdo->rollback();
            error_log("BusinessQRManager::spendCoins() error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get business owner user ID
     * 
     * @param int $business_id Business ID
     * @return int|false User ID or false if not found
     */
    public static function getBusinessOwner($business_id) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("SELECT user_id FROM businesses WHERE id = ?");
            $stmt->execute([$business_id]);
            return $stmt->fetchColumn();
            
        } catch (PDOException $e) {
            error_log("BusinessQRManager::getBusinessOwner() error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create or upgrade business subscription
     * 
     * @param int $business_id Business ID
     * @param string $tier Subscription tier (starter, professional, enterprise)
     * @param string $billing_cycle Billing cycle (monthly, yearly)
     * @return bool Success status
     */
    public static function createSubscription($business_id, $tier, $billing_cycle = 'monthly') {
        global $pdo;
        
        $pricing = ConfigManager::getSubscriptionPricing();
        if (!isset($pricing[$tier])) {
            return false;
        }
        
        $tier_config = $pricing[$tier];
        $monthly_price = $tier_config['monthly_cents'];
        
        // Calculate yearly discount (20% off)
        if ($billing_cycle === 'yearly') {
            $monthly_price = (int) ($monthly_price * 0.8);
        }
        
        try {
            $pdo->beginTransaction();
            
            // End current subscription
            $stmt = $pdo->prepare("
                UPDATE business_subscriptions 
                SET status = 'cancelled' 
                WHERE business_id = ? AND status IN ('trial', 'active')
            ");
            $stmt->execute([$business_id]);
            
            // Create new subscription
            $stmt = $pdo->prepare("
                INSERT INTO business_subscriptions 
                (business_id, tier, status, billing_cycle, monthly_price_cents, 
                 current_period_start, current_period_end, qr_coin_allowance, machine_limit, features)
                VALUES (?, ?, 'active', ?, ?, CURDATE(), ?, ?, ?, ?)
            ");
            
            $period_end = $billing_cycle === 'yearly' ? 
                'DATE_ADD(CURDATE(), INTERVAL 1 YEAR)' : 
                'DATE_ADD(CURDATE(), INTERVAL 1 MONTH)';
            
            $features = self::getTierFeatures($tier);
            
            $stmt = $pdo->prepare("
                INSERT INTO business_subscriptions 
                (business_id, tier, status, billing_cycle, monthly_price_cents, 
                 current_period_start, current_period_end, qr_coin_allowance, machine_limit, features)
                VALUES (?, ?, 'active', ?, ?, CURDATE(), {$period_end}, ?, ?, ?)
            ");
            
            $stmt->execute([
                $business_id,
                $tier,
                $billing_cycle,
                $monthly_price,
                $tier_config['qr_coins'],
                $tier_config['machines'],
                json_encode($features)
            ]);
            
            $pdo->commit();
            return true;
            
        } catch (PDOException $e) {
            $pdo->rollback();
            error_log("BusinessQRManager::createSubscription() error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get features for subscription tier
     * 
     * @param string $tier Subscription tier
     * @return array Features configuration
     */
    private static function getTierFeatures($tier) {
        $base_features = [
            'qr_generation' => 'basic',
            'analytics' => 'basic',
            'support' => 'email',
            'api_access' => false,
            'white_label' => false,
            'priority_support' => false,
            'custom_branding' => false,
            'advanced_analytics' => false,
            'webhook_notifications' => false
        ];
        
        switch ($tier) {
            case 'professional':
                return array_merge($base_features, [
                    'qr_generation' => 'enhanced',
                    'analytics' => 'advanced',
                    'custom_branding' => true,
                    'webhook_notifications' => true
                ]);
                
            case 'enterprise':
                return array_merge($base_features, [
                    'qr_generation' => 'premium',
                    'analytics' => 'premium',
                    'support' => 'priority',
                    'api_access' => true,
                    'white_label' => true,
                    'priority_support' => true,
                    'custom_branding' => true,
                    'advanced_analytics' => true,
                    'webhook_notifications' => true
                ]);
                
            default: // starter
                return $base_features;
        }
    }
    
    /**
     * Calculate QR coin cost for items using business pricing calculator
     * 
     * @param float $item_price Item price in USD
     * @param float $desired_discount Desired discount percentage (0.05 = 5%)
     * @param int $user_base_size Estimated user base size
     * @return array Pricing calculation
     */
    public static function calculateQRCoinCost($item_price, $desired_discount, $user_base_size = 100) {
        // Simple 1:1 conversion - no complex multipliers
        $coin_value_usd = (float) ConfigManager::get('nayax_qr_coin_rate', 0.001); // 1 QR coin = $0.001
        $discount_amount = $item_price * $desired_discount;
        
        // Direct conversion: discount amount ÷ coin value = required coins
        $final_cost = (int) ceil($discount_amount / $coin_value_usd);
        
        return [
            'qr_coin_cost' => $final_cost,
            'discount_amount_usd' => $discount_amount,
            'coin_value_usd' => $coin_value_usd,
            'demand_multiplier' => 1.0, // No multiplier - direct conversion
            'scarcity_factor' => 1.0,   // No multiplier - direct conversion
            'breakdown' => [
                'base_cost' => $final_cost,
                'demand_adjustment' => 0,
                'scarcity_adjustment' => 0
            ]
        ];
    }
    
    /**
     * Get business usage statistics
     * 
     * @param int $business_id Business ID
     * @return array Usage statistics
     */
    public static function getUsageStats($business_id) {
        global $pdo;
        
        $subscription = self::getSubscription($business_id);
        if (!$subscription) {
            return ['error' => 'No active subscription'];
        }
        
        try {
            // Get machine count
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as machine_count 
                FROM voting_lists 
                WHERE business_id = ?
            ");
            $stmt->execute([$business_id]);
            $machine_count = $stmt->fetchColumn();
            
            // Get QR code generation stats
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as qr_codes_generated
                FROM qr_codes 
                WHERE business_id = ? AND created_at >= ?
            ");
            $stmt->execute([$business_id, $subscription['current_period_start']]);
            $qr_codes_generated = $stmt->fetchColumn();
            
            return [
                'subscription' => [
                    'tier' => $subscription['tier'],
                    'status' => $subscription['status'],
                    'period_start' => $subscription['current_period_start'],
                    'period_end' => $subscription['current_period_end']
                ],
                'usage' => [
                    'machines_used' => $machine_count,
                    'machines_limit' => $subscription['machine_limit'],
                    'qr_coins_used' => $subscription['qr_coins_used'],
                    'qr_coins_allowance' => $subscription['qr_coin_allowance'],
                    'qr_codes_generated' => $qr_codes_generated
                ],
                'percentages' => [
                    'machines_used_pct' => min(100, ($machine_count / max($subscription['machine_limit'], 1)) * 100),
                    'qr_coins_used_pct' => min(100, ($subscription['qr_coins_used'] / max($subscription['qr_coin_allowance'], 1)) * 100)
                ]
            ];
            
        } catch (PDOException $e) {
            error_log("BusinessQRManager::getUsageStats() error: " . $e->getMessage());
            return ['error' => 'Database error'];
        }
    }
    
    /**
     * Reset monthly QR coin allowance (for cron job)
     * 
     * @return array Reset results
     */
    public static function resetMonthlyAllowances() {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                UPDATE business_subscriptions 
                SET qr_coins_used = 0 
                WHERE status = 'active' 
                AND current_period_end <= CURDATE()
            ");
            $stmt->execute();
            
            $affected_rows = $stmt->rowCount();
            
            // Update period dates for monthly subscriptions
            $stmt = $pdo->prepare("
                UPDATE business_subscriptions 
                SET 
                    current_period_start = CURDATE(),
                    current_period_end = CASE 
                        WHEN billing_cycle = 'yearly' THEN DATE_ADD(CURDATE(), INTERVAL 1 YEAR)
                        ELSE DATE_ADD(CURDATE(), INTERVAL 1 MONTH)
                    END
                WHERE status = 'active' 
                AND current_period_end <= CURDATE()
            ");
            $stmt->execute();
            
            return [
                'status' => 'completed',
                'reset_count' => $affected_rows,
                'date' => date('Y-m-d H:i:s')
            ];
            
        } catch (PDOException $e) {
            error_log("BusinessQRManager::resetMonthlyAllowances() error: " . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Check if business has feature access
     * 
     * @param int $business_id Business ID
     * @param string $feature Feature name
     * @return bool Whether business has access to feature
     */
    public static function hasFeature($business_id, $feature) {
        $subscription = self::getSubscription($business_id);
        if (!$subscription) {
            return false;
        }
        
        $features = $subscription['features'] ?? [];
        return isset($features[$feature]) && $features[$feature];
    }
    
    /**
     * Upgrade business to trial period (for new businesses)
     * 
     * @param int $business_id Business ID
     * @return bool Success status
     */
    public static function startTrial($business_id) {
        global $pdo;
        
        try {
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO business_subscriptions 
                (business_id, tier, status, billing_cycle, monthly_price_cents, 
                 current_period_start, current_period_end, qr_coin_allowance, machine_limit, features)
                VALUES (?, 'starter', 'trial', 'monthly', 0, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 1000, 3, ?)
            ");
            
            $features = json_encode(self::getTierFeatures('starter'));
            return $stmt->execute([$business_id, $features]);
            
        } catch (PDOException $e) {
            error_log("BusinessQRManager::startTrial() error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all business subscriptions (for admin dashboard)
     * 
     * @param string $status_filter Optional status filter ('active', 'trial', 'cancelled', 'all')
     * @return array All business subscriptions
     */
    public static function getAllSubscriptions($status_filter = 'all') {
        global $pdo;
        
        try {
            $where_clause = "WHERE 1=1";
            $params = [];
            
            if ($status_filter !== 'all') {
                $where_clause .= " AND bs.status = ?";
                $params[] = $status_filter;
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    bs.*,
                    b.name as business_name,
                    b.logo_path,
                    u.username as owner_username
                FROM business_subscriptions bs
                JOIN businesses b ON bs.business_id = b.id
                LEFT JOIN users u ON b.user_id = u.id
                {$where_clause}
                ORDER BY bs.created_at DESC
            ");
            $stmt->execute($params);
            $subscriptions = $stmt->fetchAll();
            
            // Decode features JSON for each subscription
            foreach ($subscriptions as &$subscription) {
                if ($subscription['features']) {
                    $subscription['features'] = json_decode($subscription['features'], true);
                }
            }
            
            return $subscriptions;
            
        } catch (PDOException $e) {
            error_log("BusinessQRManager::getAllSubscriptions() error: " . $e->getMessage());
            return [];
        }
    }
}
?> 