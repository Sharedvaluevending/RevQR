-- Business Wallet System for QR Coin Economy
-- Date: 2025-06-08
-- Purpose: Track business QR coin balances and transactions

USE revenueqr;

-- Start transaction for safety
START TRANSACTION;

-- 1. Business QR Coin Wallet - Core balance tracking
CREATE TABLE IF NOT EXISTS business_wallets (
    business_id INT PRIMARY KEY,
    qr_coin_balance INT DEFAULT 0 COMMENT 'Current QR coin balance',
    total_earned_all_time INT DEFAULT 0 COMMENT 'Total QR coins earned since inception',
    total_spent_all_time INT DEFAULT 0 COMMENT 'Total QR coins spent since inception',
    last_transaction_at TIMESTAMP NULL COMMENT 'Last wallet activity timestamp',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    INDEX idx_balance (qr_coin_balance),
    INDEX idx_last_activity (last_transaction_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Business QR Coin Transactions - Complete audit trail
CREATE TABLE IF NOT EXISTS business_qr_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    transaction_type ENUM('earning', 'spending', 'adjustment', 'refund', 'bonus') NOT NULL,
    category VARCHAR(50) NOT NULL COMMENT 'revenue_share, store_sale, subscription_fee, etc.',
    amount INT NOT NULL COMMENT 'Positive for earnings, negative for spending',
    balance_before INT NOT NULL COMMENT 'Balance before this transaction',
    balance_after INT NOT NULL COMMENT 'Balance after this transaction',
    description VARCHAR(255) NOT NULL,
    metadata JSON COMMENT 'Additional transaction details',
    reference_id INT NULL COMMENT 'Links to casino_plays, store_purchases, etc.',
    reference_type VARCHAR(50) NULL COMMENT 'casino_play, store_purchase, subscription, etc.',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    INDEX idx_business_transactions (business_id, created_at),
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_category (category),
    INDEX idx_reference (reference_type, reference_id),
    INDEX idx_balance_tracking (business_id, balance_after, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Business Revenue Sources - Track where QR coins come from
CREATE TABLE IF NOT EXISTS business_revenue_sources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    source_type ENUM('casino_revenue_share', 'store_sales', 'promotional_bonus', 'referral_bonus', 'manual_adjustment') NOT NULL,
    date_period DATE NOT NULL,
    qr_coins_earned INT DEFAULT 0,
    transaction_count INT DEFAULT 0,
    metadata JSON COMMENT 'Source-specific details',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_business_source_date (business_id, source_type, date_period),
    INDEX idx_business_revenue (business_id, date_period),
    INDEX idx_source_type (source_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Initialize wallets for existing businesses
INSERT INTO business_wallets (business_id, qr_coin_balance, total_earned_all_time, total_spent_all_time)
SELECT 
    id as business_id,
    0 as qr_coin_balance, -- Start with 0, will calculate from transactions
    0 as total_earned_all_time,
    0 as total_spent_all_time
FROM businesses 
WHERE NOT EXISTS (
    SELECT 1 FROM business_wallets WHERE business_id = businesses.id
);

-- 5. Create functions for wallet operations
DELIMITER //

-- Function to update business wallet balance
CREATE OR REPLACE FUNCTION update_business_wallet(
    p_business_id INT,
    p_amount INT,
    p_transaction_type ENUM('earning', 'spending', 'adjustment', 'refund', 'bonus'),
    p_category VARCHAR(50),
    p_description VARCHAR(255),
    p_reference_id INT,
    p_reference_type VARCHAR(50),
    p_metadata JSON
) RETURNS BOOLEAN
READS SQL DATA
MODIFIES SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_current_balance INT DEFAULT 0;
    DECLARE v_new_balance INT DEFAULT 0;
    DECLARE v_error_occurred BOOLEAN DEFAULT FALSE;
    
    DECLARE CONTINUE HANDLER FOR SQLEXCEPTION 
    BEGIN
        SET v_error_occurred = TRUE;
        ROLLBACK;
    END;
    
    START TRANSACTION;
    
    -- Get current balance with row lock
    SELECT qr_coin_balance INTO v_current_balance
    FROM business_wallets 
    WHERE business_id = p_business_id
    FOR UPDATE;
    
    -- Calculate new balance
    SET v_new_balance = v_current_balance + p_amount;
    
    -- Prevent negative balances for spending transactions
    IF p_transaction_type = 'spending' AND v_new_balance < 0 THEN
        ROLLBACK;
        RETURN FALSE;
    END IF;
    
    -- Update wallet balance and totals
    UPDATE business_wallets SET
        qr_coin_balance = v_new_balance,
        total_earned_all_time = total_earned_all_time + GREATEST(p_amount, 0),
        total_spent_all_time = total_spent_all_time + GREATEST(-p_amount, 0),
        last_transaction_at = NOW(),
        updated_at = NOW()
    WHERE business_id = p_business_id;
    
    -- Record transaction
    INSERT INTO business_qr_transactions (
        business_id, transaction_type, category, amount, 
        balance_before, balance_after, description, 
        metadata, reference_id, reference_type
    ) VALUES (
        p_business_id, p_transaction_type, p_category, p_amount,
        v_current_balance, v_new_balance, p_description,
        p_metadata, p_reference_id, p_reference_type
    );
    
    IF v_error_occurred THEN
        RETURN FALSE;
    END IF;
    
    COMMIT;
    RETURN TRUE;
END //

DELIMITER ;

-- 6. Create a view for business wallet summary
CREATE OR REPLACE VIEW business_wallet_summary AS
SELECT 
    b.id as business_id,
    b.name as business_name,
    bw.qr_coin_balance,
    bw.total_earned_all_time,
    bw.total_spent_all_time,
    bw.last_transaction_at,
    
    -- Recent earnings (last 30 days)
    COALESCE(SUM(CASE WHEN bqt.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
                      AND bqt.amount > 0 THEN bqt.amount ELSE 0 END), 0) as earnings_30d,
    
    -- Recent spending (last 30 days)
    COALESCE(SUM(CASE WHEN bqt.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) 
                      AND bqt.amount < 0 THEN ABS(bqt.amount) ELSE 0 END), 0) as spending_30d,
    
    -- Transaction counts
    COUNT(CASE WHEN bqt.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as transactions_30d,
    
    -- Revenue breakdown
    COALESCE(casino_earnings.amount, 0) as casino_earnings_30d,
    COALESCE(store_earnings.amount, 0) as store_earnings_30d
    
FROM businesses b
LEFT JOIN business_wallets bw ON b.id = bw.business_id
LEFT JOIN business_qr_transactions bqt ON b.id = bqt.business_id
LEFT JOIN (
    SELECT business_id, SUM(qr_coins_earned) as amount
    FROM business_revenue_sources 
    WHERE source_type = 'casino_revenue_share' 
    AND date_period >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY business_id
) casino_earnings ON b.id = casino_earnings.business_id
LEFT JOIN (
    SELECT business_id, SUM(qr_coins_earned) as amount
    FROM business_revenue_sources 
    WHERE source_type = 'store_sales' 
    AND date_period >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY business_id
) store_earnings ON b.id = store_earnings.business_id
GROUP BY b.id, b.name, bw.qr_coin_balance, bw.total_earned_all_time, 
         bw.total_spent_all_time, bw.last_transaction_at, 
         casino_earnings.amount, store_earnings.amount;

-- 7. Insert some example revenue data to demonstrate the system
-- This would normally be populated by the actual revenue-generating activities

INSERT IGNORE INTO business_revenue_sources (business_id, source_type, date_period, qr_coins_earned, transaction_count, metadata)
SELECT 
    id as business_id,
    'casino_revenue_share' as source_type,
    CURDATE() as date_period,
    FLOOR(RAND() * 500) + 100 as qr_coins_earned, -- Random earnings between 100-600
    FLOOR(RAND() * 20) + 5 as transaction_count, -- Random play count
    JSON_OBJECT(
        'revenue_share_rate', 0.10,
        'total_plays_at_location', FLOOR(RAND() * 20) + 5,
        'avg_bet_amount', FLOOR(RAND() * 10) + 5
    ) as metadata
FROM businesses
WHERE EXISTS (SELECT 1 FROM business_casino_settings WHERE business_id = businesses.id AND casino_enabled = 1);

-- 8. Initialize wallet balances from revenue sources
UPDATE business_wallets bw
SET qr_coin_balance = (
    SELECT COALESCE(SUM(qr_coins_earned), 0)
    FROM business_revenue_sources brs 
    WHERE brs.business_id = bw.business_id
),
total_earned_all_time = (
    SELECT COALESCE(SUM(qr_coins_earned), 0)
    FROM business_revenue_sources brs 
    WHERE brs.business_id = bw.business_id
),
updated_at = NOW();

-- Commit the transaction
COMMIT;

-- Show results
SELECT 'Business Wallet System created successfully!' as status;
SELECT COUNT(*) as business_wallets_created FROM business_wallets;
SELECT COUNT(*) as revenue_sources_initialized FROM business_revenue_sources;

-- Show sample wallet data
SELECT 
    business_name,
    qr_coin_balance,
    total_earned_all_time,
    casino_earnings_30d,
    store_earnings_30d
FROM business_wallet_summary 
WHERE qr_coin_balance > 0
LIMIT 5; 