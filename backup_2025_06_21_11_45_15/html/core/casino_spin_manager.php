<?php
/**
 * Casino Spin Manager
 * Manages slot machine spin packs purchased from QR store
 */

class CasinoSpinManager {
    private static $pdo;
    
    public static function init($pdo_connection) {
        self::$pdo = $pdo_connection;
    }
    
    /**
     * Check and activate slot machine spin packs for a user
     * 
     * @param int $user_id User ID
     * @return array Active spin pack info
     */
    public static function getActiveSpinPacks($user_id) {
        if (!self::$pdo) {
            global $pdo;
            self::$pdo = $pdo;
        }
        
        try {
            // First, mark expired purchases as 'used'
            $stmt = self::$pdo->prepare("
                UPDATE user_qr_store_purchases uqsp
                JOIN qr_store_items qsi ON uqsp.qr_store_item_id = qsi.id
                SET uqsp.status = 'used'
                WHERE uqsp.user_id = ? 
                AND qsi.item_type = 'slot_pack' 
                AND uqsp.status = 'active'
                AND uqsp.expires_at IS NOT NULL 
                AND uqsp.expires_at <= NOW()
            ");
            $stmt->execute([$user_id]);
            
            // Get active slot packs for this user (FIFO - first purchased, first used)
            $stmt = self::$pdo->prepare("
                SELECT uqsp.*, qsi.item_name, qsi.item_data
                FROM user_qr_store_purchases uqsp
                JOIN qr_store_items qsi ON uqsp.qr_store_item_id = qsi.id
                WHERE uqsp.user_id = ? 
                AND qsi.item_type = 'slot_pack' 
                AND uqsp.status = 'active'
                AND (uqsp.expires_at IS NULL OR uqsp.expires_at > NOW())
                ORDER BY uqsp.created_at ASC
            ");
            $stmt->execute([$user_id]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("CasinoSpinManager::getActiveSpinPacks() error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Calculate available casino spins for a user at a specific business
     * 
     * @param int $user_id User ID
     * @param int $business_id Business ID
     * @return array Spin availability info
     */
    public static function getAvailableSpins($user_id, $business_id) {
        if (!self::$pdo) {
            global $pdo;
            self::$pdo = $pdo;
        }
        
        try {
            // Get business casino settings
            $stmt = self::$pdo->prepare("
                SELECT casino_enabled, max_daily_plays 
                FROM business_casino_settings 
                WHERE business_id = ?
            ");
            $stmt->execute([$business_id]);
            $casino_settings = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$casino_settings || !$casino_settings['casino_enabled']) {
                return [
                    'base_spins' => 0,
                    'bonus_spins' => 0,
                    'total_spins' => 0,
                    'spins_used' => 0,
                    'spins_remaining' => 0,
                    'active_packs' => []
                ];
            }
            
            $base_daily_limit = $casino_settings['max_daily_plays'];
            
            // Get today's plays for this business
            $stmt = self::$pdo->prepare("
                SELECT COALESCE(plays_count, 0) as plays_today
                FROM casino_daily_limits 
                WHERE user_id = ? AND business_id = ? AND play_date = CURDATE()
            ");
            $stmt->execute([$user_id, $business_id]);
            $plays_today = $stmt->fetchColumn() ?: 0;
            
            // FIXED: Simplified spin pack calculation
            $active_packs = self::getActiveSpinPacks($user_id);
            $bonus_spins = 0;
            $pack_info = [];
            
            foreach ($active_packs as $pack) {
                $pack_data = json_decode($pack['item_data'], true);
                $spins_per_day = $pack_data['spins_per_day'] ?? 0;
                
                // FIXED: Simple logic - just add the daily spin allowance from each active pack
                // No complex calculation needed - just give the user their daily bonus spins
                if ($spins_per_day > 0) {
                    $bonus_spins += $spins_per_day;
                    $pack_info[] = [
                        'name' => $pack['item_name'],
                        'spins_available' => $spins_per_day,
                        'expires_at' => $pack['expires_at']
                    ];
                }
            }
            
            $total_spins_available = $base_daily_limit + $bonus_spins;
            $spins_remaining = max(0, $total_spins_available - $plays_today);
            
            return [
                'base_spins' => $base_daily_limit,
                'bonus_spins' => $bonus_spins,
                'total_spins' => $total_spins_available,
                'spins_used' => $plays_today,
                'spins_remaining' => $spins_remaining,
                'active_packs' => $pack_info
            ];
            
        } catch (PDOException $e) {
            error_log("CasinoSpinManager::getAvailableSpins() error: " . $e->getMessage());
            return [
                'base_spins' => 0,
                'bonus_spins' => 0,
                'total_spins' => 0,
                'spins_used' => 0,
                'spins_remaining' => 0,
                'active_packs' => []
            ];
        }
    }
    
    /**
     * Check if user can play at casino (has spins available)
     * 
     * @param int $user_id User ID
     * @param int $business_id Business ID
     * @return bool Can play
     */
    public static function canPlay($user_id, $business_id) {
        $spin_info = self::getAvailableSpins($user_id, $business_id);
        return $spin_info['spins_remaining'] > 0;
    }
    
    /**
     * Record a casino play and update spin usage
     * This should be called after a successful casino play
     * 
     * @param int $user_id User ID
     * @param int $business_id Business ID
     * @return bool Success
     */
    public static function recordCasinoPlay($user_id, $business_id) {
        if (!self::$pdo) {
            global $pdo;
            self::$pdo = $pdo;
        }
        
        try {
            // This is handled by the existing casino_daily_limits table
            // The casino system already tracks plays in casino_daily_limits
            // We just need to ensure our spin pack calculations are accurate
            
            // Update any spin packs that should be marked as used
            $active_packs = self::getActiveSpinPacks($user_id);
            
            foreach ($active_packs as $pack) {
                $pack_data = json_decode($pack['item_data'], true);
                $duration_days = $pack_data['duration_days'] ?? 7;
                
                // Check if pack has expired by duration
                $pack_end_date = date('Y-m-d H:i:s', strtotime($pack['created_at'] . " + {$duration_days} days"));
                if (strtotime($pack_end_date) <= time()) {
                    $stmt = self::$pdo->prepare("
                        UPDATE user_qr_store_purchases 
                        SET status = 'used', expires_at = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$pack_end_date, $pack['id']]);
                }
            }
            
            return true;
            
        } catch (PDOException $e) {
            error_log("CasinoSpinManager::recordCasinoPlay() error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get spin pack status for display
     * 
     * @param int $user_id User ID
     * @return array Display-ready spin pack info
     */
    public static function getSpinPackStatus($user_id) {
        $active_packs = self::getActiveSpinPacks($user_id);
        
        if (empty($active_packs)) {
            return [
                'has_packs' => false,
                'message' => 'No active casino spin packs'
            ];
        }
        
        $total_bonus_spins = 0;
        $earliest_expiry = null;
        
        foreach ($active_packs as $pack) {
            $pack_data = json_decode($pack['item_data'], true);
            $total_bonus_spins += $pack_data['spins_per_day'] ?? 0;
            
            if ($pack['expires_at']) {
                if (!$earliest_expiry || strtotime($pack['expires_at']) < strtotime($earliest_expiry)) {
                    $earliest_expiry = $pack['expires_at'];
                }
            }
        }
        
        return [
            'has_packs' => true,
            'total_bonus_spins' => $total_bonus_spins,
            'pack_count' => count($active_packs),
            'earliest_expiry' => $earliest_expiry,
            'message' => "You have {$total_bonus_spins} bonus casino spins per day from " . count($active_packs) . " active pack(s)"
        ];
    }
} 