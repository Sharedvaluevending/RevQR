<?php
/**
 * Store Manager for QR Coin Economy 2.0
 * Handles business discount stores and QR coin store operations
 * 
 * @author QR Coin Economy Team
 * @version 1.0
 * @date 2025-01-17
 */

require_once __DIR__ . '/config_manager.php';
require_once __DIR__ . '/qr_coin_manager.php';
require_once __DIR__ . '/business_qr_manager.php';

class StoreManager {
    
    /**
     * Get all business store items available to users
     * 
     * @param bool $active_only Whether to only return active items
     * @return array Store items from all businesses
     */
    public static function getAllBusinessStoreItems($active_only = true) {
        global $pdo;
        
        try {
            $where_clause = "WHERE 1=1";
            $params = [];
            
            if ($active_only) {
                $where_clause .= " AND bsi.is_active = 1 AND (bsi.valid_until IS NULL OR bsi.valid_until > NOW())";
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    bsi.id,
                    bsi.business_id,
                    bsi.item_name,
                    bsi.item_description,
                    bsi.regular_price_cents,
                    bsi.discount_percentage,
                    bsi.qr_coin_cost,
                    bsi.category,
                    bsi.stock_quantity,
                    bsi.max_per_user,
                    bsi.is_active,
                    bsi.valid_from,
                    bsi.valid_until,
                    b.name as business_name,
                    b.logo_path
                FROM business_store_items bsi
                JOIN businesses b ON bsi.business_id = b.id
                {$where_clause}
                ORDER BY b.name ASC, bsi.qr_coin_cost ASC
            ");
            $stmt->execute($params);
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("StoreManager::getAllBusinessStoreItems() error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get business store items for a specific business
     * 
     * @param int $business_id Business ID
     * @param bool $active_only Whether to only return active items
     * @return array Store items
     */
    public static function getBusinessStoreItems($business_id, $active_only = true) {
        global $pdo;
        
        if (!$business_id) return [];
        
        try {
            $where_clause = "WHERE business_id = ?";
            $params = [$business_id];
            
            if ($active_only) {
                $where_clause .= " AND is_active = 1 AND (valid_until IS NULL OR valid_until > NOW())";
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    item_name,
                    item_description,
                    regular_price_cents,
                    discount_percentage,
                    qr_coin_cost,
                    category,
                    stock_quantity,
                    max_per_user,
                    is_active,
                    valid_from,
                    valid_until
                FROM business_store_items 
                {$where_clause}
                ORDER BY qr_coin_cost ASC
            ");
            $stmt->execute($params);
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("StoreManager::getBusinessStoreItems() error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Purchase a business store item
     * 
     * @param int $user_id User ID
     * @param int $store_item_id Store item ID
     * @return array Purchase result
     */
    public static function purchaseBusinessItem($user_id, $store_item_id) {
        global $pdo;
        
        if (!$user_id || !$store_item_id) {
            return ['success' => false, 'message' => 'Invalid parameters'];
        }
        
        try {
            $pdo->beginTransaction();
            
            // Get item details
            $stmt = $pdo->prepare("
                SELECT 
                    bsi.*,
                    b.name as business_name 
                FROM business_store_items bsi
                JOIN businesses b ON bsi.business_id = b.id
                WHERE bsi.id = ? AND bsi.is_active = 1 
                AND (bsi.valid_until IS NULL OR bsi.valid_until > NOW())
            ");
            $stmt->execute([$store_item_id]);
            $item = $stmt->fetch();
            
            if (!$item) {
                $pdo->rollback();
                return ['success' => false, 'message' => 'Item not found or unavailable'];
            }
            
            // Check if user has enough QR coins
            $user_balance = QRCoinManager::getBalance($user_id);
            if ($user_balance < $item['qr_coin_cost']) {
                $pdo->rollback();
                return ['success' => false, 'message' => 'Insufficient QR coins'];
            }
            
            // Check purchase limits
            if ($item['max_per_user'] > 0) {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) 
                    FROM user_store_purchases 
                    WHERE user_id = ? AND store_item_id = ? AND status != 'cancelled'
                ");
                $stmt->execute([$user_id, $store_item_id]);
                $purchase_count = $stmt->fetchColumn();
                
                if ($purchase_count >= $item['max_per_user']) {
                    $pdo->rollback();
                    return ['success' => false, 'message' => 'Purchase limit reached'];
                }
            }
            
            // Check stock
            if ($item['stock_quantity'] >= 0) {
                if ($item['stock_quantity'] <= 0) {
                    $pdo->rollback();
                    return ['success' => false, 'message' => 'Out of stock'];
                }
                
                // Decrease stock
                $stmt = $pdo->prepare("
                    UPDATE business_store_items 
                    SET stock_quantity = stock_quantity - 1 
                    WHERE id = ? AND stock_quantity > 0
                ");
                $stmt->execute([$store_item_id]);
                
                if ($stmt->rowCount() === 0) {
                    $pdo->rollback();
                    return ['success' => false, 'message' => 'Item out of stock'];
                }
            }
            
            // Generate unique purchase code
            $purchase_code = self::generatePurchaseCode();
            
            // Create purchase record
            $discount_amount_cents = (int) (($item['regular_price_cents'] * $item['discount_percentage']) / 100);
            
            $stmt = $pdo->prepare("
                INSERT INTO user_store_purchases 
                (user_id, business_id, store_item_id, qr_coins_spent, discount_amount_cents, purchase_code)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id,
                $item['business_id'],
                $store_item_id,
                $item['qr_coin_cost'],
                $discount_amount_cents,
                $purchase_code
            ]);
            
                        // Spend QR coins
            $spent = QRCoinManager::spendCoins(
                $user_id,
                $item['qr_coin_cost'],
                'business_store',
                "Purchased: {$item['item_name']} from {$item['business_name']}",
                [
                    'store_item_id' => $store_item_id,
                    'business_id' => $item['business_id'],
                    'purchase_code' => $purchase_code,
                    'discount_percentage' => $item['discount_percentage']
                ],
                $store_item_id,
                'business_store_purchase'
            );

            if (!$spent) {
                $pdo->rollback();
                return ['success' => false, 'message' => 'Failed to process payment'];
            }

            // Credit the business wallet (90% of QR coins paid by user)
            $business_earning = (int) ($item['qr_coin_cost'] * 0.9); // 90% to business, 10% platform fee
            self::creditBusinessWallet(
                $item['business_id'],
                $business_earning,
                'store_sale',
                "Store sale: {$item['item_name']} (Purchase code: {$purchase_code})",
                [
                    'user_id' => $user_id,
                    'store_item_id' => $store_item_id,
                    'purchase_code' => $purchase_code,
                    'original_qr_cost' => $item['qr_coin_cost'],
                    'platform_fee' => $item['qr_coin_cost'] - $business_earning
                ],
                $pdo->lastInsertId(),
                'store_purchase'
            );

            $pdo->commit();
            
            return [
                'success' => true,
                'message' => 'Purchase successful!',
                'purchase_code' => $purchase_code,
                'item_name' => $item['item_name'],
                'discount_amount' => $discount_amount_cents / 100,
                'qr_coins_spent' => $item['qr_coin_cost']
            ];
            
        } catch (PDOException $e) {
            $pdo->rollback();
            error_log("StoreManager::purchaseBusinessItem() error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error'];
        }
    }
    
    /**
     * Get QR store items
     * 
     * @param string $category Optional category filter
     * @param bool $active_only Whether to only return active items
     * @return array QR store items
     */
    public static function getQRStoreItems($category = null, $active_only = true) {
        global $pdo;
        
        try {
            $where_clause = "WHERE 1=1";
            $params = [];
            
            if ($category) {
                $where_clause .= " AND item_type = ?";
                $params[] = $category;
            }
            
            if ($active_only) {
                $where_clause .= " AND is_active = 1 AND (valid_until IS NULL OR valid_until > NOW())";
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    item_type,
                    item_name,
                    item_description,
                    image_url,
                    qr_coin_cost,
                    item_data,
                    rarity,
                    stock_quantity,
                    purchase_limit_per_user
                FROM qr_store_items 
                {$where_clause}
                ORDER BY 
                    CASE rarity 
                        WHEN 'legendary' THEN 1 
                        WHEN 'epic' THEN 2 
                        WHEN 'rare' THEN 3 
                        WHEN 'common' THEN 4 
                    END,
                    qr_coin_cost ASC
            ");
            $stmt->execute($params);
            
            $items = $stmt->fetchAll();
            
            // Parse JSON data
            foreach ($items as &$item) {
                if ($item['item_data']) {
                    $item['item_data'] = json_decode($item['item_data'], true);
                }
            }
            
            return $items;
            
        } catch (PDOException $e) {
            error_log("StoreManager::getQRStoreItems() error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Purchase a QR store item
     * 
     * @param int $user_id User ID
     * @param int $qr_store_item_id QR store item ID
     * @param int $quantity Quantity to purchase
     * @return array Purchase result
     */
    public static function purchaseQRStoreItem($user_id, $qr_store_item_id, $quantity = 1) {
        global $pdo;
        
        if (!$user_id || !$qr_store_item_id || $quantity <= 0) {
            return ['success' => false, 'message' => 'Invalid parameters'];
        }
        
        try {
            $pdo->beginTransaction();
            
            // Get item details
            $stmt = $pdo->prepare("
                SELECT * FROM qr_store_items 
                WHERE id = ? AND is_active = 1 
                AND (valid_until IS NULL OR valid_until > NOW())
            ");
            $stmt->execute([$qr_store_item_id]);
            $item = $stmt->fetch();
            
            if (!$item) {
                $pdo->rollback();
                return ['success' => false, 'message' => 'Item not found or unavailable'];
            }
            
            $total_cost = $item['qr_coin_cost'] * $quantity;
            
            // Check if user has enough QR coins
            $user_balance = QRCoinManager::getBalance($user_id);
            if ($user_balance < $total_cost) {
                $pdo->rollback();
                return ['success' => false, 'message' => 'Insufficient QR coins'];
            }
            
            // Check purchase limits
            if ($item['purchase_limit_per_user'] > 0) {
                $stmt = $pdo->prepare("
                    SELECT COALESCE(SUM(quantity), 0) 
                    FROM user_qr_store_purchases 
                    WHERE user_id = ? AND qr_store_item_id = ? AND status != 'refunded'
                ");
                $stmt->execute([$user_id, $qr_store_item_id]);
                $purchased_quantity = $stmt->fetchColumn();
                
                if (($purchased_quantity + $quantity) > $item['purchase_limit_per_user']) {
                    $pdo->rollback();
                    return ['success' => false, 'message' => 'Purchase limit exceeded'];
                }
            }
            
            // Check stock
            if ($item['stock_quantity'] >= 0) {
                if ($item['stock_quantity'] < $quantity) {
                    $pdo->rollback();
                    return ['success' => false, 'message' => 'Insufficient stock'];
                }
                
                // Decrease stock
                $stmt = $pdo->prepare("
                    UPDATE qr_store_items 
                    SET stock_quantity = stock_quantity - ? 
                    WHERE id = ? AND stock_quantity >= ?
                ");
                $stmt->execute([$quantity, $qr_store_item_id, $quantity]);
                
                if ($stmt->rowCount() === 0) {
                    $pdo->rollback();
                    return ['success' => false, 'message' => 'Insufficient stock'];
                }
            }
            
            // Create purchase record
            $item_data = json_decode($item['item_data'], true);
            $expires_at = null;
            
            // Set expiry based on item type
            if (isset($item_data['duration_days'])) {
                $expires_at = date('Y-m-d H:i:s', strtotime("+{$item_data['duration_days']} days"));
            } elseif (isset($item_data['duration_hours'])) {
                $expires_at = date('Y-m-d H:i:s', strtotime("+{$item_data['duration_hours']} hours"));
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO user_qr_store_purchases 
                (user_id, qr_store_item_id, qr_coins_spent, quantity, item_data, expires_at)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id,
                $qr_store_item_id,
                $total_cost,
                $quantity,
                $item['item_data'],
                $expires_at
            ]);
            
            // Spend QR coins
            $spent = QRCoinManager::spendCoins(
                $user_id,
                $total_cost,
                'qr_store',
                "Purchased: {$quantity}x {$item['item_name']}",
                [
                    'qr_store_item_id' => $qr_store_item_id,
                    'item_type' => $item['item_type'],
                    'quantity' => $quantity,
                    'rarity' => $item['rarity']
                ],
                $qr_store_item_id,
                'qr_store_purchase'
            );
            
            if (!$spent) {
                $pdo->rollback();
                return ['success' => false, 'message' => 'Failed to process payment'];
            }
            
            // Apply item effects immediately for applicable items
            self::applyItemEffects($user_id, $item, $quantity);
            
            $pdo->commit();
            
            return [
                'success' => true,
                'message' => 'Purchase successful!',
                'item_name' => $item['item_name'],
                'quantity' => $quantity,
                'qr_coins_spent' => $total_cost,
                'expires_at' => $expires_at
            ];
            
        } catch (PDOException $e) {
            $pdo->rollback();
            error_log("StoreManager::purchaseQRStoreItem() error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error'];
        }
    }
    
    /**
     * Apply immediate effects for purchased items
     * 
     * @param int $user_id User ID
     * @param array $item Item data
     * @param int $quantity Quantity purchased
     */
    private static function applyItemEffects($user_id, $item, $quantity) {
        $item_data = json_decode($item['item_data'], true);
        
        switch ($item['item_type']) {
            case 'avatar':
                // Set avatar as equipped (requires avatar system integration)
                break;
                
            case 'boost':
                // QR coin boost effects would be applied in earning calculations
                break;
                
            case 'insurance':
                // Streak protection would be checked in streak breaking logic
                break;
                
            default:
                // Other items have passive effects
                break;
        }
    }
    
    /**
     * Get user's purchase history
     * 
     * @param int $user_id User ID
     * @param string $store_type 'business' or 'qr' or 'all'
     * @param int $limit Number of purchases to return
     * @return array Purchase history
     */
    public static function getUserPurchaseHistory($user_id, $store_type = 'all', $limit = 50) {
        global $pdo;
        
        if (!$user_id) return [];
        
        try {
            $purchases = [];
            
            if ($store_type === 'business' || $store_type === 'all') {
                $stmt = $pdo->prepare("
                    SELECT 
                        'business' as store_type,
                        usp.id,
                        usp.qr_coins_spent,
                        usp.purchase_code,
                        usp.status,
                        usp.created_at,
                        bsi.item_name,
                        bsi.discount_percentage,
                        b.name as business_name
                    FROM user_store_purchases usp
                    JOIN business_store_items bsi ON usp.store_item_id = bsi.id
                    JOIN businesses b ON usp.business_id = b.id
                    WHERE usp.user_id = ?
                    ORDER BY usp.created_at DESC
                    LIMIT ?
                ");
                $stmt->execute([$user_id, $limit]);
                $business_purchases = $stmt->fetchAll();
                $purchases = array_merge($purchases, $business_purchases);
            }
            
            if ($store_type === 'qr' || $store_type === 'all') {
                $stmt = $pdo->prepare("
                    SELECT 
                        'qr' as store_type,
                        uqsp.id,
                        uqsp.qr_coins_spent,
                        uqsp.quantity,
                        uqsp.status,
                        uqsp.expires_at,
                        uqsp.created_at,
                        qsi.item_name,
                        qsi.item_type,
                        qsi.rarity
                    FROM user_qr_store_purchases uqsp
                    JOIN qr_store_items qsi ON uqsp.qr_store_item_id = qsi.id
                    WHERE uqsp.user_id = ?
                    ORDER BY uqsp.created_at DESC
                    LIMIT ?
                ");
                $stmt->execute([$user_id, $limit]);
                $qr_purchases = $stmt->fetchAll();
                $purchases = array_merge($purchases, $qr_purchases);
            }
            
            // Sort by created_at
            usort($purchases, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });
            
            return array_slice($purchases, 0, $limit);
            
        } catch (PDOException $e) {
            error_log("StoreManager::getUserPurchaseHistory() error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Generate unique purchase code
     * 
     * @return string 8-character alphanumeric code
     */
    private static function generatePurchaseCode() {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $code;
    }
    
    /**
     * Validate and redeem a purchase code
     * 
     * @param string $purchase_code Purchase code
     * @param int $business_user_id Business user processing the redemption
     * @return array Redemption result
     */
    public static function redeemPurchaseCode($purchase_code, $business_user_id) {
        global $pdo;
        
        if (!$purchase_code || !$business_user_id) {
            return ['success' => false, 'message' => 'Invalid parameters'];
        }
        
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                SELECT 
                    usp.*,
                    bsi.item_name,
                    bsi.discount_percentage,
                    u.username
                FROM user_store_purchases usp
                JOIN business_store_items bsi ON usp.store_item_id = bsi.id
                JOIN users u ON usp.user_id = u.id
                WHERE usp.purchase_code = ? AND usp.status = 'pending'
            ");
            $stmt->execute([$purchase_code]);
            $purchase = $stmt->fetch();
            
            if (!$purchase) {
                $pdo->rollback();
                return ['success' => false, 'message' => 'Invalid or already used code'];
            }
            
            // Update purchase status
            $stmt = $pdo->prepare("
                UPDATE user_store_purchases 
                SET status = 'redeemed', redeemed_at = NOW(), redeemed_by = ?
                WHERE id = ?
            ");
            $stmt->execute([$business_user_id, $purchase['id']]);
            
            $pdo->commit();
            
            return [
                'success' => true,
                'message' => 'Code redeemed successfully',
                'item_name' => $purchase['item_name'],
                'discount_percentage' => $purchase['discount_percentage'],
                'customer_username' => $purchase['username'],
                'discount_amount' => $purchase['discount_amount_cents'] / 100
            ];
            
        } catch (PDOException $e) {
            $pdo->rollback();
            error_log("StoreManager::redeemPurchaseCode() error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error'];
        }
    }
    
    /**
     * Get QR store statistics
     * 
     * @return array QR store statistics
     */
    public static function getQRStoreStats() {
        global $pdo;
        
        try {
            // Get total QR store items
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM qr_store_items WHERE is_active = 1");
            $stmt->execute();
            $total_items = $stmt->fetchColumn();
            
            // Get total QR store purchases
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_qr_store_purchases WHERE status != 'cancelled'");
            $stmt->execute();
            $total_purchases = $stmt->fetchColumn();
            
            // Get total QR coins spent in QR store
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(qr_coins_spent), 0) FROM user_qr_store_purchases WHERE status != 'cancelled'");
            $stmt->execute();
            $total_coins_spent = $stmt->fetchColumn();
            
            return [
                'total_items' => (int) $total_items,
                'total_purchases' => (int) $total_purchases,
                'total_coins_spent' => (int) $total_coins_spent
            ];
            
        } catch (PDOException $e) {
            error_log("StoreManager::getQRStoreStats() error: " . $e->getMessage());
            return [
                'total_items' => 0,
                'total_purchases' => 0,
                'total_coins_spent' => 0
            ];
        }
    }
    
    /**
     * Get all business store statistics (for admin)
     * 
     * @return array Business store statistics
     */
    public static function getAllBusinessStoreStats() {
        global $pdo;
        
        try {
            // Get total business store items
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM business_store_items WHERE is_active = 1");
            $stmt->execute();
            $total_items = $stmt->fetchColumn();
            
            // Get total business store purchases
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_store_purchases WHERE status != 'cancelled'");
            $stmt->execute();
            $total_sales = $stmt->fetchColumn();
            
            // Get total QR coins spent in business stores
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(qr_coins_spent), 0) FROM user_store_purchases WHERE status != 'cancelled'");
            $stmt->execute();
            $total_coins_spent = $stmt->fetchColumn();
            
            // Get total discount value provided
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(discount_amount_cents), 0) FROM user_store_purchases WHERE status != 'cancelled'");
            $stmt->execute();
            $total_discount_value = $stmt->fetchColumn();
            
            return [
                'total_items' => (int) $total_items,
                'total_sales' => (int) $total_sales,
                'total_coins_spent' => (int) $total_coins_spent,
                'total_discount_value_cents' => (int) $total_discount_value
            ];
            
        } catch (PDOException $e) {
            error_log("StoreManager::getAllBusinessStoreStats() error: " . $e->getMessage());
            return [
                'total_items' => 0,
                'total_sales' => 0,
                'total_coins_spent' => 0,
                'total_discount_value_cents' => 0
            ];
        }
    }
    
    /**
     * Get business store statistics for a specific business
     * 
     * @param int $business_id Business ID
     * @return array Business store statistics
     */
    public static function getBusinessStoreStats($business_id) {
        global $pdo;
        
        if (!$business_id) {
            return [
                'total_items' => 0,
                'total_sales' => 0,
                'total_coins_earned' => 0,
                'total_discount_value_cents' => 0
            ];
        }
        
        try {
            // Get total store items for this business
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM business_store_items WHERE business_id = ? AND is_active = 1");
            $stmt->execute([$business_id]);
            $total_items = $stmt->fetchColumn();
            
            // Get total sales for this business
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_store_purchases WHERE business_id = ? AND status != 'cancelled'");
            $stmt->execute([$business_id]);
            $total_sales = $stmt->fetchColumn();
            
            // Get total QR coins earned from sales
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(qr_coins_spent), 0) FROM user_store_purchases WHERE business_id = ? AND status != 'cancelled'");
            $stmt->execute([$business_id]);
            $total_coins_earned = $stmt->fetchColumn();
            
            // Get total discount value provided
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(discount_amount_cents), 0) FROM user_store_purchases WHERE business_id = ? AND status != 'cancelled'");
            $stmt->execute([$business_id]);
            $total_discount_value = $stmt->fetchColumn();
            
            return [
                'total_items' => (int) $total_items,
                'total_sales' => (int) $total_sales,
                'total_coins_earned' => (int) $total_coins_earned,
                'total_discount_value_cents' => (int) $total_discount_value
            ];
            
        } catch (PDOException $e) {
            error_log("StoreManager::getBusinessStoreStats() error: " . $e->getMessage());
            return [
                'total_items' => 0,
                'total_sales' => 0,
                'total_coins_earned' => 0,
                'total_discount_value_cents' => 0
            ];
        }
    }
    
    /**
     * Credit QR coins to business wallet
     * 
     * @param int $business_id Business ID
     * @param int $amount Amount of QR coins to credit
     * @param string $category Transaction category
     * @param string $description Transaction description
     * @param array $metadata Optional metadata
     * @param int $reference_id Optional reference ID
     * @param string $reference_type Optional reference type
     * @return bool Success status
     */
    private static function creditBusinessWallet($business_id, $amount, $category, $description, $metadata = [], $reference_id = null, $reference_type = null) {
        global $pdo;
        
        if (!$business_id || $amount <= 0) {
            return false;
        }
        
        try {
            // Ensure business wallet exists
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO business_wallets (business_id, qr_coin_balance, total_earned_all_time, total_spent_all_time)
                VALUES (?, 0, 0, 0)
            ");
            $stmt->execute([$business_id]);
            
            // Get current balance with lock
            $stmt = $pdo->prepare("
                SELECT qr_coin_balance FROM business_wallets 
                WHERE business_id = ? FOR UPDATE
            ");
            $stmt->execute([$business_id]);
            $current_balance = $stmt->fetchColumn() ?: 0;
            $new_balance = $current_balance + $amount;
            
            // Update wallet balance
            $stmt = $pdo->prepare("
                UPDATE business_wallets SET 
                    qr_coin_balance = ?,
                    total_earned_all_time = total_earned_all_time + ?,
                    last_transaction_at = NOW(),
                    updated_at = NOW()
                WHERE business_id = ?
            ");
            $stmt->execute([$new_balance, $amount, $business_id]);
            
            // Record transaction
            $stmt = $pdo->prepare("
                INSERT INTO business_qr_transactions 
                (business_id, transaction_type, category, amount, balance_before, balance_after, description, metadata, reference_id, reference_type)
                VALUES (?, 'earning', ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $business_id,
                $category,
                $amount,
                $current_balance,
                $new_balance,
                $description,
                json_encode($metadata),
                $reference_id,
                $reference_type
            ]);
            
            // Update revenue sources for today
            $stmt = $pdo->prepare("
                INSERT INTO business_revenue_sources 
                (business_id, source_type, date_period, qr_coins_earned, transaction_count, metadata)
                VALUES (?, 'store_sales', CURDATE(), ?, 1, ?)
                ON DUPLICATE KEY UPDATE 
                    qr_coins_earned = qr_coins_earned + VALUES(qr_coins_earned),
                    transaction_count = transaction_count + 1,
                    updated_at = NOW()
            ");
            $stmt->execute([
                $business_id,
                $amount,
                json_encode($metadata)
            ]);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("StoreManager::creditBusinessWallet() error: " . $e->getMessage());
            return false;
        }
    }
}
?> 