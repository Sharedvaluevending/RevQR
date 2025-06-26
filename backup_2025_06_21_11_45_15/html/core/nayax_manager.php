<?php
/**
 * Nayax Manager - Core Integration Service
 * Handles all Nayax machine integration, transactions, and user management
 * 
 * @author RevenueQR Team
 * @version 1.0
 * @date 2025-01-17
 */

require_once __DIR__ . '/config_manager.php';
require_once __DIR__ . '/qr_coin_manager.php';
require_once __DIR__ . '/business_wallet_manager.php';

class NayaxManager {
    
    private $pdo;
    private $config;
    
    public function __construct($pdo = null) {
        global $pdo;
        $global_pdo = $pdo;
        $this->pdo = $pdo ?: $global_pdo;
        $this->config = $this->loadConfig();
    }
    
    /**
     * Load Nayax configuration settings
     */
    private function loadConfig() {
        return [
            'integration_enabled' => ConfigManager::get('nayax_integration_enabled', false),
            'webhook_secret' => ConfigManager::get('nayax_webhook_secret', 'change_this_secret_key'),
            'commission_rate' => (float) ConfigManager::get('nayax_commission_rate', 0.10),
            'qr_coin_rate' => (float) ConfigManager::get('nayax_qr_coin_rate', 0.005),
            'min_purchase_cents' => (int) ConfigManager::get('nayax_min_purchase_cents', 100),
            'max_purchase_cents' => (int) ConfigManager::get('nayax_max_purchase_cents', 10000),
            'reward_rate' => (float) ConfigManager::get('nayax_reward_rate', 0.02),
            'auto_user_creation' => ConfigManager::get('nayax_auto_user_creation', true),
            'event_processing_enabled' => ConfigManager::get('nayax_event_processing_enabled', true),
            'discount_code_length' => (int) ConfigManager::get('nayax_discount_code_length', 8)
        ];
    }
    
    // ==========================================================================
    // MACHINE MANAGEMENT
    // ==========================================================================
    
    /**
     * Register a new Nayax machine
     */
    public function registerMachine($business_id, $nayax_machine_id, $nayax_device_id, $machine_name, $config = []) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO nayax_machines 
                (business_id, nayax_machine_id, nayax_device_id, machine_name, machine_config, status)
                VALUES (?, ?, ?, ?, ?, 'active')
                ON DUPLICATE KEY UPDATE
                machine_name = VALUES(machine_name),
                machine_config = VALUES(machine_config),
                updated_at = CURRENT_TIMESTAMP
            ");
            
            $result = $stmt->execute([
                $business_id,
                $nayax_machine_id,
                $nayax_device_id,
                $machine_name,
                json_encode($config)
            ]);
            
            if ($result) {
                $machine_id = $this->pdo->lastInsertId() ?: $this->getMachineIdByNayaxId($nayax_machine_id);
                
                // Create default QR coin products for this machine
                $this->createDefaultQRCoinProducts($business_id, $nayax_machine_id);
                
                return [
                    'success' => true,
                    'machine_id' => $machine_id,
                    'message' => 'Machine registered successfully'
                ];
            }
            
            return ['success' => false, 'message' => 'Failed to register machine'];
            
        } catch (Exception $e) {
            error_log("NayaxManager::registerMachine() error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error'];
        }
    }
    
    /**
     * Get machine details by Nayax machine ID
     */
    public function getMachine($nayax_machine_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT nm.*, b.name as business_name
                FROM nayax_machines nm
                JOIN businesses b ON nm.business_id = b.id
                WHERE nm.nayax_machine_id = ?
            ");
            $stmt->execute([$nayax_machine_id]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("NayaxManager::getMachine() error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Update machine status
     */
    public function updateMachineStatus($nayax_machine_id, $status, $last_seen = null) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE nayax_machines 
                SET status = ?, last_seen_at = COALESCE(?, CURRENT_TIMESTAMP), updated_at = CURRENT_TIMESTAMP
                WHERE nayax_machine_id = ?
            ");
            
            return $stmt->execute([$status, $last_seen, $nayax_machine_id]);
            
        } catch (Exception $e) {
            error_log("NayaxManager::updateMachineStatus() error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get machines for a business
     */
    public function getBusinessMachines($business_id, $status = null) {
        try {
            $where_clause = "WHERE business_id = ?";
            $params = [$business_id];
            
            if ($status) {
                $where_clause .= " AND status = ?";
                $params[] = $status;
            }
            
            $stmt = $this->pdo->prepare("
                SELECT * FROM nayax_machines 
                {$where_clause}
                ORDER BY machine_name ASC
            ");
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("NayaxManager::getBusinessMachines() error: " . $e->getMessage());
            return [];
        }
    }
    
    // ==========================================================================
    // TRANSACTION PROCESSING
    // ==========================================================================
    
    /**
     * Process a Nayax transaction from webhook
     */
    public function processTransaction($transaction_data) {
        try {
            $this->pdo->beginTransaction();
            
            // Extract key data from Nayax transaction
            $nayax_transaction_id = $transaction_data['TransactionId'] ?? null;
            $nayax_machine_id = $transaction_data['MachineId'] ?? null;
            $amount_cents = round(($transaction_data['Data']['SeValue'] ?? 0) * 100);
            $card_string = $transaction_data['Data']['Card String'] ?? null;
            $payment_method = $transaction_data['Data']['Payment Method Description'] ?? null;
            
            if (!$nayax_transaction_id || !$nayax_machine_id) {
                throw new Exception('Missing required transaction data');
            }
            
            // Get machine and business info
            $machine = $this->getMachine($nayax_machine_id);
            if (!$machine) {
                throw new Exception("Unknown machine: {$nayax_machine_id}");
            }
            
            // Find or create user based on card string
            $user_id = null;
            if ($card_string && $this->config['auto_user_creation']) {
                $user_id = $this->findOrCreateUserByCard($card_string);
            }
            
            // Determine transaction type
            $transaction_type = $this->determineTransactionType($transaction_data);
            
            // Store transaction
            $stmt = $this->pdo->prepare("
                INSERT INTO nayax_transactions 
                (business_id, nayax_machine_id, nayax_transaction_id, user_id, card_string, 
                 transaction_type, amount_cents, currency, payment_method, machine_time, 
                 settlement_time, status, transaction_data, processed_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', ?, NOW())
            ");
            
            $stmt->execute([
                $machine['business_id'],
                $nayax_machine_id,
                $nayax_transaction_id,
                $user_id,
                $card_string,
                $transaction_type,
                $amount_cents,
                $transaction_data['Data']['Currency'] ?? 'USD',
                $payment_method,
                $transaction_data['MachineTime'] ?? null,
                $transaction_data['Data']['Settlement Time'] ?? null,
                json_encode($transaction_data)
            ]);
            
            $transaction_id = $this->pdo->lastInsertId();
            
            // Process based on transaction type
            $processing_result = $this->processTransactionByType($transaction_type, $transaction_data, $user_id, $machine);
            
            // Update transaction with processing results
            if ($processing_result['qr_coins_awarded'] > 0) {
                $stmt = $this->pdo->prepare("
                    UPDATE nayax_transactions 
                    SET qr_coins_awarded = ?, platform_commission_cents = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $processing_result['qr_coins_awarded'],
                    $processing_result['commission_cents'],
                    $transaction_id
                ]);
            }
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'transaction_id' => $transaction_id,
                'qr_coins_awarded' => $processing_result['qr_coins_awarded'],
                'message' => 'Transaction processed successfully'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollback();
            error_log("NayaxManager::processTransaction() error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Determine transaction type based on product or amount
     */
    private function determineTransactionType($transaction_data) {
        // Check if this is a QR coin pack purchase based on product codes
        $product_code = $transaction_data['Data']['Catalog Number'] ?? '';
        
        if (strpos($product_code, 'QR_COIN') !== false) {
            return 'qr_coin_purchase';
        }
        
        // Check if this is a discount redemption (would have special indicators)
        if (isset($transaction_data['Data']['Discount_Code']) || 
            isset($transaction_data['Custom']['discount_applied'])) {
            return 'discount_redemption';
        }
        
        // Default to regular sale
        return 'sale';
    }
    
    /**
     * Process transaction based on its type
     */
    private function processTransactionByType($type, $transaction_data, $user_id, $machine) {
        $result = ['qr_coins_awarded' => 0, 'commission_cents' => 0];
        
        switch ($type) {
            case 'qr_coin_purchase':
                $result = $this->processQRCoinPurchase($transaction_data, $user_id, $machine);
                break;
                
            case 'discount_redemption':
                $result = $this->processDiscountRedemption($transaction_data, $user_id, $machine);
                break;
                
            case 'sale':
                $result = $this->processRegularSale($transaction_data, $user_id, $machine);
                break;
        }
        
        return $result;
    }
    
    /**
     * Process QR coin pack purchase
     */
    private function processQRCoinPurchase($transaction_data, $user_id, $machine) {
        if (!$user_id) {
            return ['qr_coins_awarded' => 0, 'commission_cents' => 0];
        }
        
        // Find the QR coin product
        $amount_cents = round(($transaction_data['Data']['SeValue'] ?? 0) * 100);
        $qr_coin_product = $this->findQRCoinProductByPrice($machine['nayax_machine_id'], $amount_cents);
        
        if (!$qr_coin_product) {
            error_log("No QR coin product found for amount: {$amount_cents} cents");
            return ['qr_coins_awarded' => 0, 'commission_cents' => 0];
        }
        
        // Award QR coins to user
        $coins_to_award = $qr_coin_product['qr_coin_amount'];
        $success = QRCoinManager::addTransaction(
            $user_id,
            'earning',
            'nayax_purchase',
            $coins_to_award,
            "QR Coin Pack Purchase: {$qr_coin_product['product_name']}",
            [
                'nayax_transaction_id' => $transaction_data['TransactionId'],
                'machine_id' => $machine['nayax_machine_id'],
                'amount_paid_cents' => $amount_cents,
                'product_name' => $qr_coin_product['product_name']
            ],
            $transaction_data['TransactionId'],
            'nayax_purchase'
        );
        
        if (!$success) {
            throw new Exception('Failed to award QR coins to user');
        }
        
        // Calculate platform commission
        $commission_cents = round($amount_cents * $this->config['commission_rate']);
        
        // Award business revenue share (amount minus commission)
        $business_revenue_cents = $amount_cents - $commission_cents;
        $business_qr_coins = round($business_revenue_cents * 200); // $0.005 per coin
        
        $business_wallet = new BusinessWalletManager($this->pdo);
        $business_wallet->addCoins(
            $machine['business_id'],
            $business_qr_coins,
            'nayax_qr_coin_sales',
            "QR Coin Pack Sale Revenue: {$qr_coin_product['product_name']}",
            $transaction_data['TransactionId'],
            'nayax_transaction',
            [
                'sale_amount_cents' => $amount_cents,
                'commission_cents' => $commission_cents,
                'coins_sold' => $coins_to_award
            ]
        );
        
        return [
            'qr_coins_awarded' => $coins_to_award,
            'commission_cents' => $commission_cents
        ];
    }
    
    /**
     * Process discount redemption
     */
    private function processDiscountRedemption($transaction_data, $user_id, $machine) {
        // Award small QR coin bonus for using discount system
        $bonus_coins = 10; // Small bonus for engagement
        
        if ($user_id) {
            QRCoinManager::addTransaction(
                $user_id,
                'earning',
                'discount_redemption',
                $bonus_coins,
                "Discount Redemption Bonus",
                [
                    'nayax_transaction_id' => $transaction_data['TransactionId'],
                    'machine_id' => $machine['nayax_machine_id']
                ],
                $transaction_data['TransactionId'],
                'discount_redemption'
            );
        }
        
        return ['qr_coins_awarded' => $bonus_coins, 'commission_cents' => 0];
    }
    
    /**
     * Process regular sale with QR coin rewards
     */
    private function processRegularSale($transaction_data, $user_id, $machine) {
        if (!$user_id) {
            return ['qr_coins_awarded' => 0, 'commission_cents' => 0];
        }
        
        // Award QR coins based on purchase amount
        $amount_cents = round(($transaction_data['Data']['SeValue'] ?? 0) * 100);
        $reward_coins = round($amount_cents * $this->config['reward_rate'] / $this->config['qr_coin_rate']);
        
        if ($reward_coins > 0) {
            QRCoinManager::addTransaction(
                $user_id,
                'earning',
                'purchase_reward',
                $reward_coins,
                "Purchase Reward: " . ($amount_cents / 100) . " USD",
                [
                    'nayax_transaction_id' => $transaction_data['TransactionId'],
                    'machine_id' => $machine['nayax_machine_id'],
                    'amount_spent_cents' => $amount_cents,
                    'reward_rate' => $this->config['reward_rate']
                ],
                $transaction_data['TransactionId'],
                'purchase_reward'
            );
        }
        
        return ['qr_coins_awarded' => $reward_coins, 'commission_cents' => 0];
    }
    
    // ==========================================================================
    // USER MANAGEMENT
    // ==========================================================================
    
    /**
     * Find or create user by card string
     */
    private function findOrCreateUserByCard($card_string) {
        try {
            // First, check if card is already linked to a user
            $stmt = $this->pdo->prepare("SELECT user_id FROM nayax_user_cards WHERE card_string = ?");
            $stmt->execute([$card_string]);
            $existing = $stmt->fetch();
            
            if ($existing) {
                // Update usage statistics
                $stmt = $this->pdo->prepare("
                    UPDATE nayax_user_cards 
                    SET last_used_at = CURRENT_TIMESTAMP, total_transactions = total_transactions + 1
                    WHERE card_string = ?
                ");
                $stmt->execute([$card_string]);
                
                return $existing['user_id'];
            }
            
            // Create new user account
            $username = 'nayax_' . substr($card_string, -6) . '_' . time();
            $password_hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO users (username, password_hash, role) 
                VALUES (?, ?, 'user')
            ");
            $stmt->execute([$username, $password_hash]);
            $user_id = $this->pdo->lastInsertId();
            
            // Link card to user
            $this->linkCardToUser($user_id, $card_string);
            
            // Give welcome bonus
            QRCoinManager::addTransaction(
                $user_id,
                'earning',
                'welcome_bonus',
                1000, // 1000 coin welcome bonus
                "Welcome bonus for new Nayax user",
                ['card_string' => $card_string],
                null,
                'welcome_bonus'
            );
            
            return $user_id;
            
        } catch (Exception $e) {
            error_log("NayaxManager::findOrCreateUserByCard() error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Link a card string to a user
     */
    public function linkCardToUser($user_id, $card_string, $card_type = null) {
        try {
            $card_first_4 = substr($card_string, 0, 4);
            $card_last_4 = substr($card_string, -4);
            
            $stmt = $this->pdo->prepare("
                INSERT INTO nayax_user_cards 
                (user_id, card_string, card_type, card_first_4, card_last_4, first_used_at, last_used_at)
                VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                card_type = COALESCE(VALUES(card_type), card_type),
                last_used_at = CURRENT_TIMESTAMP
            ");
            
            return $stmt->execute([$user_id, $card_string, $card_type, $card_first_4, $card_last_4]);
            
        } catch (Exception $e) {
            error_log("NayaxManager::linkCardToUser() error: " . $e->getMessage());
            return false;
        }
    }
    
    // ==========================================================================
    // QR COIN PRODUCTS MANAGEMENT
    // ==========================================================================
    
    /**
     * Create default QR coin products for a machine
     */
    private function createDefaultQRCoinProducts($business_id, $nayax_machine_id) {
        $default_products = [
            [
                'name' => '500 QR Coins Starter Pack',
                'description' => 'Perfect for trying our discount system - includes 50 bonus coins!',
                'coins' => 550,
                'price_cents' => 250
            ],
            [
                'name' => '1000 QR Coins Popular Pack', 
                'description' => 'Most popular choice - includes 100 bonus coins and better value!',
                'coins' => 1100,
                'price_cents' => 500
            ],
            [
                'name' => '2500 QR Coins Value Pack',
                'description' => 'Best value! Includes 500 bonus coins - save more on discounts!',
                'coins' => 3000,
                'price_cents' => 1000
            ]
        ];
        
        foreach ($default_products as $product) {
            $this->createQRCoinProduct($business_id, $nayax_machine_id, $product);
        }
    }
    
    /**
     * Create a QR coin product
     */
    public function createQRCoinProduct($business_id, $nayax_machine_id, $product_data) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO nayax_qr_coin_products 
                (business_id, nayax_machine_id, product_name, product_description, 
                 qr_coin_amount, price_cents, bonus_percentage, nayax_product_code)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $bonus_percentage = 0;
            if ($product_data['coins'] > 0 && $product_data['price_cents'] > 0) {
                $base_coins = round($product_data['price_cents'] / ($this->config['qr_coin_rate'] * 100));
                $bonus_percentage = (($product_data['coins'] - $base_coins) / $base_coins) * 100;
            }
            
            return $stmt->execute([
                $business_id,
                $nayax_machine_id,
                $product_data['name'],
                $product_data['description'] ?? '',
                $product_data['coins'],
                $product_data['price_cents'],
                $bonus_percentage,
                $product_data['nayax_product_code'] ?? null
            ]);
            
        } catch (Exception $e) {
            error_log("NayaxManager::createQRCoinProduct() error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Find QR coin product by price
     */
    private function findQRCoinProductByPrice($nayax_machine_id, $price_cents) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM nayax_qr_coin_products 
                WHERE nayax_machine_id = ? AND price_cents = ? AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$nayax_machine_id, $price_cents]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("NayaxManager::findQRCoinProductByPrice() error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get QR coin products for a machine
     */
    public function getMachineQRCoinProducts($nayax_machine_id, $active_only = true) {
        try {
            $where_clause = "WHERE nayax_machine_id = ?";
            $params = [$nayax_machine_id];
            
            if ($active_only) {
                $where_clause .= " AND is_active = 1";
            }
            
            $stmt = $this->pdo->prepare("
                SELECT * FROM nayax_qr_coin_products 
                {$where_clause}
                ORDER BY price_cents ASC
            ");
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("NayaxManager::getMachineQRCoinProducts() error: " . $e->getMessage());
            return [];
        }
    }
    
    // ==========================================================================
    // UTILITY METHODS
    // ==========================================================================
    
    /**
     * Get machine ID by Nayax machine ID
     */
    private function getMachineIdByNayaxId($nayax_machine_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT id FROM nayax_machines WHERE nayax_machine_id = ?");
            $stmt->execute([$nayax_machine_id]);
            $result = $stmt->fetch();
            
            return $result ? $result['id'] : null;
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature($payload, $signature) {
        $expected_signature = hash_hmac('sha256', $payload, $this->config['webhook_secret']);
        return hash_equals($expected_signature, $signature);
    }
    
    /**
     * Get integration statistics
     */
    public function getIntegrationStats() {
        try {
            $stats = [];
            
            // Total machines
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM nayax_machines WHERE status = 'active'");
            $stmt->execute();
            $stats['active_machines'] = $stmt->fetchColumn();
            
            // Total transactions today
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM nayax_transactions 
                WHERE DATE(created_at) = CURDATE() AND status = 'completed'
            ");
            $stmt->execute();
            $stats['transactions_today'] = $stmt->fetchColumn();
            
            // QR coins awarded today
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(qr_coins_awarded), 0) FROM nayax_transactions 
                WHERE DATE(created_at) = CURDATE() AND status = 'completed'
            ");
            $stmt->execute();
            $stats['qr_coins_awarded_today'] = $stmt->fetchColumn();
            
            // Revenue today
            $stmt = $this->pdo->prepare("
                SELECT COALESCE(SUM(amount_cents), 0) FROM nayax_transactions 
                WHERE DATE(created_at) = CURDATE() AND status = 'completed'
            ");
            $stmt->execute();
            $stats['revenue_today_cents'] = $stmt->fetchColumn();
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("NayaxManager::getIntegrationStats() error: " . $e->getMessage());
            return [];
        }
    }
}
?> 