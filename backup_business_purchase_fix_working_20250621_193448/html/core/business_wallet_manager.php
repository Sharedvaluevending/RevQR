<?php
/**
 * Business Wallet Manager
 * Handles QR coin wallet operations for businesses
 */

class BusinessWalletManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get business wallet information
     */
    public function getWallet($business_id) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM business_wallets 
            WHERE business_id = ?
        ");
        $stmt->execute([$business_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get wallet balance only
     */
    public function getBalance($business_id) {
        $stmt = $this->pdo->prepare("
            SELECT qr_coin_balance FROM business_wallets 
            WHERE business_id = ?
        ");
        $stmt->execute([$business_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['qr_coin_balance'] ?? 0;
    }
    
    /**
     * Add QR coins to business wallet (earning)
     */
    public function addCoins($business_id, $amount, $category, $description, $reference_id = null, $reference_type = null, $metadata = null) {
        try {
            $this->pdo->beginTransaction();
            
            // Get current balance
            $current_balance = $this->getBalance($business_id);
            $new_balance = $current_balance + $amount;
            
            // Update wallet
            $stmt = $this->pdo->prepare("
                UPDATE business_wallets SET 
                    qr_coin_balance = ?,
                    total_earned_all_time = total_earned_all_time + ?,
                    last_transaction_at = NOW(),
                    updated_at = NOW()
                WHERE business_id = ?
            ");
            $stmt->execute([$new_balance, $amount, $business_id]);
            
            // Record transaction
            $stmt = $this->pdo->prepare("
                INSERT INTO business_qr_transactions 
                (business_id, transaction_type, category, amount, balance_before, balance_after, description, metadata, reference_id, reference_type)
                VALUES (?, 'earning', ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $business_id, $category, $amount, $current_balance, $new_balance, 
                $description, json_encode($metadata), $reference_id, $reference_type
            ]);
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Business wallet add coins error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Subtract QR coins from business wallet (spending)
     */
    public function spendCoins($business_id, $amount, $category, $description, $reference_id = null, $reference_type = null, $metadata = null) {
        try {
            $this->pdo->beginTransaction();
            
            // Get current balance
            $current_balance = $this->getBalance($business_id);
            
            // Check if sufficient balance
            if ($current_balance < $amount) {
                $this->pdo->rollBack();
                return false; // Insufficient funds
            }
            
            $new_balance = $current_balance - $amount;
            
            // Update wallet
            $stmt = $this->pdo->prepare("
                UPDATE business_wallets SET 
                    qr_coin_balance = ?,
                    total_spent_all_time = total_spent_all_time + ?,
                    last_transaction_at = NOW(),
                    updated_at = NOW()
                WHERE business_id = ?
            ");
            $stmt->execute([$new_balance, $amount, $business_id]);
            
            // Record transaction
            $stmt = $this->pdo->prepare("
                INSERT INTO business_qr_transactions 
                (business_id, transaction_type, category, amount, balance_before, balance_after, description, metadata, reference_id, reference_type)
                VALUES (?, 'spending', ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $business_id, $category, -$amount, $current_balance, $new_balance, 
                $description, json_encode($metadata), $reference_id, $reference_type
            ]);
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Business wallet spend coins error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get recent transactions
     */
    public function getRecentTransactions($business_id, $limit = 10) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM business_qr_transactions 
            WHERE business_id = ? 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$business_id, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get wallet statistics
     */
    public function getWalletStats($business_id) {
        $wallet = $this->getWallet($business_id);
        
        // Get 30-day stats
        $stmt = $this->pdo->prepare("
            SELECT 
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as earnings_30d,
                SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as spending_30d,
                COUNT(*) as transactions_30d
            FROM business_qr_transactions 
            WHERE business_id = ? 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute([$business_id]);
        $stats_30d = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return array_merge($wallet ?: [], $stats_30d ?: []);
    }
    
    /**
     * Initialize wallet for new business
     */
    public function initializeWallet($business_id) {
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO business_wallets (business_id, qr_coin_balance, total_earned_all_time, total_spent_all_time)
            VALUES (?, 0, 0, 0)
        ");
        return $stmt->execute([$business_id]);
    }
    
    /**
     * Record casino revenue share
     */
    public function recordCasinoRevenue($business_id, $play_id, $revenue_amount, $play_details = []) {
        $description = "Casino revenue share from player activity";
        $metadata = array_merge([
            'revenue_share_rate' => 0.10,
            'source' => 'casino_play'
        ], $play_details);
        
        return $this->addCoins(
            $business_id, 
            $revenue_amount, 
            'casino_revenue_share', 
            $description, 
            $play_id, 
            'casino_play', 
            $metadata
        );
    }
    
    /**
     * Record store sale revenue
     */
    public function recordStoreRevenue($business_id, $sale_id, $qr_coins_earned, $sale_details = []) {
        $description = "QR coins earned from store sale";
        $metadata = array_merge([
            'source' => 'store_sale'
        ], $sale_details);
        
        return $this->addCoins(
            $business_id, 
            $qr_coins_earned, 
            'store_sales', 
            $description, 
            $sale_id, 
            'store_sale', 
            $metadata
        );
    }
}
?> 