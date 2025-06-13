<?php
/**
 * Nayax Discount Manager
 * Handles discount code generation, validation, and redemption for Nayax machines
 * 
 * @author RevenueQR Team
 * @version 1.0
 * @date 2025-01-17
 */

require_once __DIR__ . '/qr_coin_manager.php';
require_once __DIR__ . '/business_wallet_manager.php';

class NayaxDiscountManager {
    
    private $pdo;
    private $config;
    
    public function __construct($pdo = null) {
        global $pdo;
        $global_pdo = $pdo;
        $this->pdo = $pdo ?: $global_pdo;
        $this->config = $this->loadConfig();
    }
    
    /**
     * Load configuration
     */
    private function loadConfig() {
        return [
            'code_length' => (int) ConfigManager::get('nayax_discount_code_length', 8),
            'code_prefix' => ConfigManager::get('nayax_discount_code_prefix', 'RQR'),
            'max_discount_percent' => (float) ConfigManager::get('nayax_max_discount_percent', 50.0),
            'min_discount_percent' => (float) ConfigManager::get('nayax_min_discount_percent', 5.0),
            'code_expiry_hours' => (int) ConfigManager::get('nayax_code_expiry_hours', 24),
            'max_uses_per_code' => (int) ConfigManager::get('nayax_max_uses_per_code', 1),
            'commission_rate' => (float) ConfigManager::get('nayax_commission_rate', 0.10)
        ];
    }
    
    /**
     * Purchase a discount code with QR coins
     */
    public function purchaseDiscountCode($user_id, $qr_store_item_id, $nayax_machine_id = null) {
        try {
            $this->pdo->beginTransaction();
            
            // Get QR store item details
            $stmt = $this->pdo->prepare("
                SELECT qsi.*, bsi.nayax_machine_id, bsi.discount_code_prefix, bsi.discount_percent,
                       b.id as business_id, b.name as business_name
                FROM qr_store_items qsi
                LEFT JOIN business_store_items bsi ON qsi.business_store_item_id = bsi.id
                LEFT JOIN businesses b ON bsi.business_id = b.id
                WHERE qsi.id = ? AND qsi.nayax_compatible = 1 AND qsi.is_active = 1
            ");
            $stmt->execute([$qr_store_item_id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$item) {
                throw new Exception('Item not found or not Nayax compatible');
            }
            
            // Check if machine ID matches (if specified)
            if ($nayax_machine_id && $item['nayax_machine_id'] && $item['nayax_machine_id'] !== $nayax_machine_id) {
                throw new Exception('Item not available for this machine');
            }
            
            // Check user's QR coin balance
            $user_balance = QRCoinManager::getBalance($user_id);
            if ($user_balance < $item['qr_coin_price']) {
                throw new Exception('Insufficient QR coins');
            }
            
            // Deduct QR coins from user
            $success = QRCoinManager::addTransaction(
                $user_id,
                'spending',
                'discount_purchase',
                -$item['qr_coin_price'],
                "Purchased discount code: {$item['item_name']}",
                [
                    'qr_store_item_id' => $qr_store_item_id,
                    'business_id' => $item['business_id'],
                    'discount_percent' => $item['discount_percent']
                ],
                null,
                'discount_purchase'
            );
            
            if (!$success) {
                throw new Exception('Failed to deduct QR coins');
            }
            
            // Generate discount code
            $discount_code = $this->generateDiscountCode($item['discount_code_prefix'] ?? null);
            $expiry_time = date('Y-m-d H:i:s', strtotime("+{$this->config['code_expiry_hours']} hours"));
            
            // Store purchase record
            $stmt = $this->pdo->prepare("
                INSERT INTO user_store_purchases 
                (user_id, qr_store_item_id, business_store_item_id, qr_coins_spent, 
                 discount_code, discount_percent, expires_at, max_uses, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
            ");
            
            $stmt->execute([
                $user_id,
                $qr_store_item_id,
                $item['business_store_item_id'],
                $item['qr_coin_price'],
                $discount_code,
                $item['discount_percent'],
                $expiry_time,
                $this->config['max_uses_per_code']
            ]);
            
            $purchase_id = $this->pdo->lastInsertId();
            
            // Award business revenue share
            if ($item['business_id']) {
                $business_share_coins = round($item['qr_coin_price'] * (1 - $this->config['commission_rate']));
                
                $business_wallet = new BusinessWalletManager($this->pdo);
                $business_wallet->addCoins(
                    $item['business_id'],
                    $business_share_coins,
                    'discount_sales',
                    "Discount code sale: {$item['item_name']}",
                    $purchase_id,
                    'discount_purchase',
                    [
                        'discount_code' => $discount_code,
                        'original_price' => $item['qr_coin_price'],
                        'commission_rate' => $this->config['commission_rate']
                    ]
                );
            }
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'purchase_id' => $purchase_id,
                'discount_code' => $discount_code,
                'discount_percent' => $item['discount_percent'],
                'expires_at' => $expiry_time,
                'item_name' => $item['item_name'],
                'business_name' => $item['business_name']
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            error_log("NayaxDiscountManager::purchaseDiscountCode() error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate a unique discount code
     */
    private function generateDiscountCode($prefix = null) {
        $prefix = $prefix ?: $this->config['code_prefix'];
        $max_attempts = 10;
        
        for ($i = 0; $i < $max_attempts; $i++) {
            $code = $prefix . strtoupper(bin2hex(random_bytes($this->config['code_length'] / 2)));
            
            // Ensure uniqueness
            $stmt = $this->pdo->prepare("SELECT id FROM user_store_purchases WHERE discount_code = ?");
            $stmt->execute([$code]);
            
            if (!$stmt->fetch()) {
                return $code;
            }
        }
        
        throw new Exception('Failed to generate unique discount code');
    }
    
    /**
     * Validate a discount code
     */
    public function validateDiscountCode($discount_code, $nayax_machine_id = null) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT usp.*, qsi.item_name, bsi.nayax_machine_id, u.username,
                       b.id as business_id, b.name as business_name
                FROM user_store_purchases usp
                JOIN qr_store_items qsi ON usp.qr_store_item_id = qsi.id
                LEFT JOIN business_store_items bsi ON usp.business_store_item_id = bsi.id
                LEFT JOIN businesses b ON bsi.business_id = b.id
                LEFT JOIN users u ON usp.user_id = u.id
                WHERE usp.discount_code = ? AND usp.status = 'active'
            ");
            
            $stmt->execute([$discount_code]);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$purchase) {
                return [
                    'valid' => false,
                    'error' => 'Invalid or inactive discount code'
                ];
            }
            
            // Check expiry
            if (strtotime($purchase['expires_at']) < time()) {
                return [
                    'valid' => false,
                    'error' => 'Discount code has expired'
                ];
            }
            
            // Check usage limit
            if ($purchase['uses_count'] >= $purchase['max_uses']) {
                return [
                    'valid' => false,
                    'error' => 'Discount code usage limit exceeded'
                ];
            }
            
            // Check machine compatibility (if specified)
            if ($nayax_machine_id && $purchase['nayax_machine_id'] && 
                $purchase['nayax_machine_id'] !== $nayax_machine_id) {
                return [
                    'valid' => false,
                    'error' => 'Discount code not valid for this machine'
                ];
            }
            
            return [
                'valid' => true,
                'purchase_id' => $purchase['id'],
                'user_id' => $purchase['user_id'],
                'username' => $purchase['username'],
                'discount_percent' => $purchase['discount_percent'],
                'item_name' => $purchase['item_name'],
                'business_name' => $purchase['business_name'],
                'expires_at' => $purchase['expires_at'],
                'uses_remaining' => $purchase['max_uses'] - $purchase['uses_count']
            ];
            
        } catch (Exception $e) {
            error_log("NayaxDiscountManager::validateDiscountCode() error: " . $e->getMessage());
            return [
                'valid' => false,
                'error' => 'System error during validation'
            ];
        }
    }
    
    /**
     * Redeem a discount code (mark as used)
     */
    public function redeemDiscountCode($discount_code, $transaction_amount_cents, $nayax_transaction_id = null) {
        try {
            $this->pdo->beginTransaction();
            
            // Validate the code first
            $validation = $this->validateDiscountCode($discount_code);
            if (!$validation['valid']) {
                throw new Exception($validation['error']);
            }
            
            // Calculate discount amount
            $discount_amount_cents = round($transaction_amount_cents * ($validation['discount_percent'] / 100));
            $final_amount_cents = $transaction_amount_cents - $discount_amount_cents;
            
            // Update usage count
            $stmt = $this->pdo->prepare("
                UPDATE user_store_purchases 
                SET uses_count = uses_count + 1,
                    last_used_at = CURRENT_TIMESTAMP,
                    nayax_transaction_id = ?
                WHERE discount_code = ?
            ");
            
            $stmt->execute([$nayax_transaction_id, $discount_code]);
            
            // Mark as used if max uses reached
            if (($validation['uses_remaining'] - 1) <= 0) {
                $stmt = $this->pdo->prepare("
                    UPDATE user_store_purchases 
                    SET status = 'used'
                    WHERE discount_code = ?
                ");
                $stmt->execute([$discount_code]);
            }
            
            // Award small QR coin bonus for successful redemption
            $bonus_coins = 10;
            QRCoinManager::addTransaction(
                $validation['user_id'],
                'earning',
                'discount_redemption',
                $bonus_coins,
                "Discount redemption bonus: {$validation['item_name']}",
                [
                    'discount_code' => $discount_code,
                    'original_amount_cents' => $transaction_amount_cents,
                    'discount_amount_cents' => $discount_amount_cents,
                    'final_amount_cents' => $final_amount_cents,
                    'discount_percent' => $validation['discount_percent']
                ],
                $nayax_transaction_id,
                'discount_redemption'
            );
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'discount_applied' => true,
                'original_amount_cents' => $transaction_amount_cents,
                'discount_amount_cents' => $discount_amount_cents,
                'final_amount_cents' => $final_amount_cents,
                'discount_percent' => $validation['discount_percent'],
                'bonus_coins_awarded' => $bonus_coins,
                'uses_remaining' => $validation['uses_remaining'] - 1
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            error_log("NayaxDiscountManager::redeemDiscountCode() error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get user's active discount codes
     */
    public function getUserDiscountCodes($user_id, $active_only = true) {
        try {
            $where_clause = "WHERE usp.user_id = ?";
            $params = [$user_id];
            
            if ($active_only) {
                $where_clause .= " AND usp.status = 'active' AND usp.expires_at > NOW() AND usp.uses_count < usp.max_uses";
            }
            
            $stmt = $this->pdo->prepare("
                SELECT usp.*, qsi.item_name, b.name as business_name,
                       (usp.max_uses - usp.uses_count) as uses_remaining
                FROM user_store_purchases usp
                JOIN qr_store_items qsi ON usp.qr_store_item_id = qsi.id
                LEFT JOIN business_store_items bsi ON usp.business_store_item_id = bsi.id
                LEFT JOIN businesses b ON bsi.business_id = b.id
                {$where_clause}
                ORDER BY usp.created_at DESC
            ");
            
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("NayaxDiscountManager::getUserDiscountCodes() error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get business discount code analytics
     */
    public function getBusinessDiscountAnalytics($business_id, $days = 30) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_codes_sold,
                    COUNT(CASE WHEN usp.uses_count > 0 THEN 1 END) as codes_redeemed,
                    SUM(usp.qr_coins_spent) as total_coins_revenue,
                    AVG(usp.discount_percent) as avg_discount_percent,
                    SUM(CASE WHEN usp.uses_count > 0 THEN usp.qr_coins_spent ELSE 0 END) as redeemed_coins_value
                FROM user_store_purchases usp
                JOIN business_store_items bsi ON usp.business_store_item_id = bsi.id
                WHERE bsi.business_id = ? 
                AND usp.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                AND usp.discount_code IS NOT NULL
            ");
            
            $stmt->execute([$business_id, $days]);
            $analytics = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Calculate redemption rate
            $analytics['redemption_rate'] = $analytics['total_codes_sold'] > 0 ? 
                round(($analytics['codes_redeemed'] / $analytics['total_codes_sold']) * 100, 2) : 0;
            
            return $analytics;
            
        } catch (Exception $e) {
            error_log("NayaxDiscountManager::getBusinessDiscountAnalytics() error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Clean up expired discount codes
     */
    public function cleanupExpiredCodes() {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE user_store_purchases 
                SET status = 'expired' 
                WHERE status = 'active' 
                AND expires_at < NOW()
                AND discount_code IS NOT NULL
            ");
            
            $stmt->execute();
            return $stmt->rowCount();
            
        } catch (Exception $e) {
            error_log("NayaxDiscountManager::cleanupExpiredCodes() error: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get discount code statistics
     */
    public function getDiscountCodeStats() {
        try {
            $stats = [];
            
            // Active codes
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM user_store_purchases 
                WHERE status = 'active' AND expires_at > NOW() 
                AND discount_code IS NOT NULL
            ");
            $stmt->execute();
            $stats['active_codes'] = $stmt->fetchColumn();
            
            // Codes created today
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM user_store_purchases 
                WHERE DATE(created_at) = CURDATE() 
                AND discount_code IS NOT NULL
            ");
            $stmt->execute();
            $stats['codes_created_today'] = $stmt->fetchColumn();
            
            // Codes redeemed today
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM user_store_purchases 
                WHERE DATE(last_used_at) = CURDATE() 
                AND discount_code IS NOT NULL
            ");
            $stmt->execute();
            $stats['codes_redeemed_today'] = $stmt->fetchColumn();
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("NayaxDiscountManager::getDiscountCodeStats() error: " . $e->getMessage());
            return [];
        }
    }
}
?> 