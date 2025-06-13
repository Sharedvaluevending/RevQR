<?php
/**
 * QR Coin Manager for QR Coin Economy 2.0
 * Handles all QR coin transactions, balances, and economic operations
 * 
 * @author QR Coin Economy Team
 * @version 1.0
 * @date 2025-01-17
 */

require_once __DIR__ . '/config_manager.php';

class QRCoinManager {
    
    /**
     * Get user's current QR coin balance
     * 
     * @param int $user_id User ID
     * @return int Current balance in QR coins
     */
    public static function getBalance($user_id) {
        global $pdo;
        
        if (!$user_id) return 0;
        
        try {
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(amount), 0) as balance 
                FROM qr_coin_transactions 
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
            return (int) $stmt->fetchColumn();
            
        } catch (PDOException $e) {
            error_log("QRCoinManager::getBalance() error for user $user_id: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Add a QR coin transaction
     * 
     * @param int $user_id User ID
     * @param string $type Transaction type (earning, spending, adjustment, business_purchase, migration)
     * @param string $category Transaction category (voting, spinning, purchase, etc.)
     * @param int $amount Amount (positive for earning, negative for spending)
     * @param string $description Optional description
     * @param array $metadata Optional metadata
     * @param int $reference_id Optional reference ID
     * @param string $reference_type Optional reference type
     * @return bool Success status
     */
    public static function addTransaction($user_id, $type, $category, $amount, $description = null, $metadata = null, $reference_id = null, $reference_type = null) {
        global $pdo;
        
        if (!$user_id || $amount == 0) {
            return false;
        }
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO qr_coin_transactions 
                (user_id, transaction_type, category, amount, description, metadata, reference_id, reference_type)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            return $stmt->execute([
                $user_id,
                $type,
                $category,
                (int) $amount,
                $description,
                $metadata ? json_encode($metadata) : null,
                $reference_id,
                $reference_type
            ]);
            
        } catch (PDOException $e) {
            error_log("QRCoinManager::addTransaction() error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Award QR coins for voting (replaces hard-coded logic)
     * 
     * @param int $user_id User ID
     * @param int $vote_id Vote ID for reference
     * @param bool $is_daily_bonus Whether this includes daily bonus
     * @return bool Success status
     */
    public static function awardVoteCoins($user_id, $vote_id, $is_daily_bonus = false) {
        if (!$user_id) return false;
        
        $economic_settings = ConfigManager::getEconomicSettings();
        $base_amount = $economic_settings['qr_coin_vote_base'] ?? 5;
        $bonus_amount = $is_daily_bonus ? ($economic_settings['qr_coin_vote_bonus'] ?? 25) : 0;
        $total_amount = $base_amount + $bonus_amount;
        
        $description = $is_daily_bonus ? 
            "Vote reward + daily bonus: {$base_amount} + {$bonus_amount} coins" :
            "Vote reward: {$base_amount} coins";
            
        return self::addTransaction(
            $user_id,
            'earning',
            'voting',
            $total_amount,
            $description,
            [
                'base_amount' => $base_amount,
                'bonus_amount' => $bonus_amount,
                'daily_bonus' => $is_daily_bonus
            ],
            $vote_id,
            'vote'
        );
    }
    
    /**
     * Award QR coins for spinning (replaces hard-coded logic)
     * 
     * @param int $user_id User ID
     * @param int $spin_id Spin result ID for reference
     * @param int $prize_points Points from prize (can be negative)
     * @param bool $is_daily_bonus Whether this includes daily bonus
     * @param bool $is_super_spin Whether this is a special super spin
     * @return bool Success status
     */
    public static function awardSpinCoins($user_id, $spin_id, $prize_points = 0, $is_daily_bonus = false, $is_super_spin = false) {
        if (!$user_id) return false;
        
        $economic_settings = ConfigManager::getEconomicSettings();
        $base_amount = $economic_settings['qr_coin_spin_base'] ?? 15;
        $bonus_amount = $is_daily_bonus ? ($economic_settings['qr_coin_spin_bonus'] ?? 50) : 0;
        $super_bonus = $is_super_spin ? 420 : 0; // QR Easybake special bonus
        
        $total_amount = $base_amount + $bonus_amount + $super_bonus + $prize_points;
        
        $description_parts = ["Spin reward: {$base_amount} coins"];
        if ($bonus_amount > 0) $description_parts[] = "daily bonus: {$bonus_amount} coins";
        if ($super_bonus > 0) $description_parts[] = "super spin bonus: {$super_bonus} coins";
        if ($prize_points != 0) $description_parts[] = "prize: {$prize_points} coins";
        
        $description = implode(', ', $description_parts);
        
        return self::addTransaction(
            $user_id,
            'earning',
            'spinning',
            $total_amount,
            $description,
            [
                'base_amount' => $base_amount,
                'bonus_amount' => $bonus_amount,
                'super_bonus' => $super_bonus,
                'prize_points' => $prize_points,
                'daily_bonus' => $is_daily_bonus,
                'super_spin' => $is_super_spin
            ],
            $spin_id,
            'spin'
        );
    }
    
    /**
     * Spend QR coins (with balance check)
     * 
     * @param int $user_id User ID
     * @param int $amount Amount to spend (positive number)
     * @param string $category Spending category
     * @param string $description Description of purchase
     * @param array $metadata Optional metadata
     * @param int $reference_id Optional reference ID
     * @param string $reference_type Optional reference type
     * @return bool Success status
     */
    public static function spendCoins($user_id, $amount, $category, $description, $metadata = null, $reference_id = null, $reference_type = null) {
        if (!$user_id || $amount <= 0) {
            return false;
        }
        
        // Check if user has enough balance
        $current_balance = self::getBalance($user_id);
        if ($current_balance < $amount) {
            return false;
        }
        
        return self::addTransaction(
            $user_id,
            'spending',
            $category,
            -$amount, // Negative for spending
            $description,
            $metadata,
            $reference_id,
            $reference_type
        );
    }
    
    /**
     * Smart spend with safeguards against overspending
     * 
     * @param int $user_id User ID
     * @param int $amount Amount to spend (positive number)
     * @param string $category Spending category
     * @param string $description Description of purchase
     * @param array $metadata Optional metadata
     * @param int $reference_id Optional reference ID
     * @param string $reference_type Optional reference type
     * @param bool $allow_negative Allow spending into negative (with limits)
     * @return array Result with success status and warnings
     */
    public static function smartSpend($user_id, $amount, $category, $description, $metadata = null, $reference_id = null, $reference_type = null, $allow_negative = false) {
        if (!$user_id || $amount <= 0) {
            return ['success' => false, 'error' => 'Invalid user or amount'];
        }
        
        $current_balance = self::getBalance($user_id);
        $result_balance = $current_balance - $amount;
        
        // Get user's activity stats to determine spending limits
        require_once __DIR__ . '/functions.php';
        $stats = getUserStats($user_id);
        $activity_points = ($stats['voting_stats']['total_votes'] * 10) + 
                          ($stats['voting_stats']['voting_days'] * 50) + 
                          ($stats['spin_stats']['spin_days'] * 100);
        
        // Define spending safeguards
        $max_negative_allowed = -($activity_points * 0.5); // Can't go more negative than 50% of earned activity
        $warning_threshold = $activity_points * 0.1; // Warn when balance would drop below 10% of activity points
        
        // Check if purchase would exceed limits
        if (!$allow_negative && $result_balance < 0) {
            return [
                'success' => false, 
                'error' => 'Insufficient balance',
                'current_balance' => $current_balance,
                'required_amount' => $amount
            ];
        }
        
        if ($allow_negative && $result_balance < $max_negative_allowed) {
            return [
                'success' => false, 
                'error' => 'Purchase would exceed spending limit',
                'current_balance' => $current_balance,
                'max_negative_allowed' => $max_negative_allowed,
                'activity_points' => $activity_points
            ];
        }
        
        // Apply the transaction
        $transaction_success = self::addTransaction(
            $user_id,
            'spending',
            $category,
            -$amount,
            $description,
            array_merge($metadata ?? [], ['smart_spend' => true, 'activity_protection' => $activity_points]),
            $reference_id,
            $reference_type
        );
        
        $warnings = [];
        if ($result_balance < $warning_threshold && $result_balance >= 0) {
            $warnings[] = 'Low balance warning: Consider earning more QR coins through voting and spinning';
        }
        
        if ($result_balance < 0) {
            $warnings[] = 'Negative balance: Your level may be protected by activity history';
        }
        
        return [
            'success' => $transaction_success,
            'new_balance' => self::getBalance($user_id),
            'warnings' => $warnings,
            'activity_protection' => $activity_points
        ];
    }
    
    /**
     * Get user's transaction history
     * 
     * @param int $user_id User ID
     * @param int $limit Number of transactions to return
     * @param int $offset Offset for pagination
     * @param string $type_filter Optional transaction type filter
     * @return array Transaction history
     */
    public static function getTransactionHistory($user_id, $limit = 50, $offset = 0, $type_filter = null) {
        global $pdo;
        
        if (!$user_id) return [];
        
        try {
            $where_clause = "WHERE user_id = ?";
            $params = [$user_id];
            
            if ($type_filter) {
                $where_clause .= " AND transaction_type = ?";
                $params[] = $type_filter;
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    transaction_type,
                    category,
                    amount,
                    description,
                    metadata,
                    reference_id,
                    reference_type,
                    created_at
                FROM qr_coin_transactions 
                {$where_clause}
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?
            ");
            
            $params[] = $limit;
            $params[] = $offset;
            $stmt->execute($params);
            
            $transactions = $stmt->fetchAll();
            
            // Parse metadata JSON
            foreach ($transactions as &$transaction) {
                if ($transaction['metadata']) {
                    $transaction['metadata'] = json_decode($transaction['metadata'], true);
                }
            }
            
            return $transactions;
            
        } catch (PDOException $e) {
            error_log("QRCoinManager::getTransactionHistory() error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Migrate existing user points to new transaction system
     * 
     * @param int $user_id User ID
     * @param int $legacy_points Legacy points from getUserStats()
     * @return bool Success status
     */
    public static function migrateLegacyPoints($user_id, $legacy_points) {
        if (!$user_id || $legacy_points <= 0) {
            return false;
        }
        
        // Check if user has already been migrated
        $existing_balance = self::getBalance($user_id);
        if ($existing_balance > 0) {
            return true; // Already migrated
        }
        
        return self::addTransaction(
            $user_id,
            'migration',
            'legacy_points',
            $legacy_points,
            "Migration of legacy points to new QR coin system",
            [
                'migration_date' => date('Y-m-d H:i:s'),
                'legacy_points' => $legacy_points
            ]
        );
    }
    
    /**
     * Apply monthly decay to large balances
     * 
     * @return array Results of decay operation
     */
    public static function applyMonthlyDecay() {
        global $pdo;
        
        $decay_rate = ConfigManager::get('qr_coin_decay_rate', 0.02);
        $decay_threshold = ConfigManager::get('qr_coin_decay_threshold', 50000);
        
        if ($decay_rate <= 0 || $decay_threshold <= 0) {
            return ['status' => 'disabled', 'affected_users' => 0];
        }
        
        try {
            // Get users with balances over threshold
            $stmt = $pdo->prepare("
                SELECT 
                    user_id,
                    SUM(amount) as balance
                FROM qr_coin_transactions 
                GROUP BY user_id
                HAVING balance >= ?
            ");
            $stmt->execute([$decay_threshold]);
            $users_to_decay = $stmt->fetchAll();
            
            $affected_users = 0;
            $total_decayed = 0;
            
            foreach ($users_to_decay as $user) {
                $decay_amount = (int) floor($user['balance'] * $decay_rate);
                if ($decay_amount > 0) {
                    self::addTransaction(
                        $user['user_id'],
                        'adjustment',
                        'monthly_decay',
                        -$decay_amount,
                        "Monthly decay: {$decay_rate}% on balance over {$decay_threshold}",
                        [
                            'decay_rate' => $decay_rate,
                            'original_balance' => $user['balance'],
                            'decay_amount' => $decay_amount
                        ]
                    );
                    $affected_users++;
                    $total_decayed += $decay_amount;
                }
            }
            
            return [
                'status' => 'completed',
                'affected_users' => $affected_users,
                'total_decayed' => $total_decayed,
                'decay_rate' => $decay_rate,
                'threshold' => $decay_threshold
            ];
            
        } catch (PDOException $e) {
            error_log("QRCoinManager::applyMonthlyDecay() error: " . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get balance with legacy fallback
     * Supports transition from old system to new system
     * 
     * @param int $user_id User ID
     * @param array $legacy_stats Legacy stats from getUserStats()
     * @return int Combined balance
     */
    public static function getBalanceWithLegacy($user_id, $legacy_stats = null) {
        $new_balance = self::getBalance($user_id);
        $economy_mode = ConfigManager::get('economy_mode', 'legacy');
        
        switch ($economy_mode) {
            case 'new':
                return $new_balance;
                
            case 'transition':
                if (!$legacy_stats) {
                    require_once __DIR__ . '/functions.php';
                    $legacy_stats = getUserStats($user_id);
                }
                return max($new_balance, $legacy_stats['user_points'] ?? 0);
                
            case 'legacy':
            default:
                if (!$legacy_stats) {
                    require_once __DIR__ . '/functions.php';
                    $legacy_stats = getUserStats($user_id);
                }
                return $legacy_stats['user_points'] ?? 0;
        }
    }
    
    /**
     * Get spending summary for a user
     * 
     * @param int $user_id User ID
     * @param string $period Period: 'day', 'week', 'month', 'year', 'all'
     * @return array Spending summary
     */
    public static function getSpendingSummary($user_id, $period = 'month') {
        global $pdo;
        
        if (!$user_id) return [];
        
        $date_conditions = [
            'day' => "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)",
            'week' => "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)",
            'month' => "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)",
            'year' => "AND created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)",
            'all' => ""
        ];
        
        $date_condition = $date_conditions[$period] ?? $date_conditions['month'];
        
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    transaction_type,
                    category,
                    COUNT(*) as transaction_count,
                    SUM(ABS(amount)) as total_amount
                FROM qr_coin_transactions 
                WHERE user_id = ? {$date_condition}
                GROUP BY transaction_type, category
                ORDER BY total_amount DESC
            ");
            $stmt->execute([$user_id]);
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("QRCoinManager::getSpendingSummary() error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Calculate QR coin value in USD
     * 
     * @param int $coin_amount Amount of QR coins
     * @return float USD value
     */
    public static function calculateUSDValue($coin_amount) {
        // Get current coin value from economy metrics or config
        $coin_value_cents = ConfigManager::get('avg_coin_value_cents', 0.1); // Default: $0.001 per coin
        return ($coin_amount * $coin_value_cents) / 100; // Convert cents to dollars
    }
    
    /**
     * Get economy overview for admin dashboard
     * 
     * @return array Economy overview statistics
     */
    public static function getEconomyOverview() {
        global $pdo;
        
        try {
            // Calculate total coins issued (positive transactions)
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(amount), 0) as total_issued
                FROM qr_coin_transactions 
                WHERE amount > 0
            ");
            $stmt->execute();
            $total_issued = $stmt->fetchColumn();
            
            // Calculate total coins spent (negative transactions)
            $stmt = $pdo->prepare("
                SELECT COALESCE(SUM(ABS(amount)), 0) as total_spent
                FROM qr_coin_transactions 
                WHERE amount < 0
            ");
            $stmt->execute();
            $total_spent = $stmt->fetchColumn();
            
            // Get active users count (users with at least one transaction in last 30 days)
            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT user_id) as active_users
                FROM qr_coin_transactions 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stmt->execute();
            $active_users = $stmt->fetchColumn();
            
            // Get total unique users who have ever had QR coin activity
            $stmt = $pdo->prepare("
                SELECT COUNT(DISTINCT user_id) as total_users
                FROM qr_coin_transactions
            ");
            $stmt->execute();
            $total_users = $stmt->fetchColumn();
            
            return [
                'total_coins_issued' => (int) $total_issued,
                'total_coins_spent' => (int) $total_spent,
                'coins_in_circulation' => (int) ($total_issued - $total_spent),
                'active_users' => (int) $active_users,
                'total_users' => (int) $total_users,
                'circulation_rate' => $total_issued > 0 ? (($total_issued - $total_spent) / $total_issued) * 100 : 0,
                'avg_balance_per_user' => $active_users > 0 ? (($total_issued - $total_spent) / $active_users) : 0
            ];
            
        } catch (PDOException $e) {
            error_log("QRCoinManager::getEconomyOverview() error: " . $e->getMessage());
            return [
                'total_coins_issued' => 0,
                'total_coins_spent' => 0,
                'coins_in_circulation' => 0,
                'active_users' => 0,
                'total_users' => 0,
                'circulation_rate' => 0,
                'avg_balance_per_user' => 0
            ];
        }
    }
}
?> 