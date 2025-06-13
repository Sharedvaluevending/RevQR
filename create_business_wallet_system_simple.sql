-- Business Wallet System for QR Coin Economy (Simplified Version)
-- Date: 2025-06-08
-- Purpose: Track business QR coin balances and transactions

USE revenueqr;

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
    0 as qr_coin_balance,
    0 as total_earned_all_time,
    0 as total_spent_all_time
FROM businesses 
WHERE NOT EXISTS (
    SELECT 1 FROM business_wallets WHERE business_id = businesses.id
);

-- 5. Insert some sample revenue data for businesses with casino enabled
INSERT IGNORE INTO business_revenue_sources (business_id, source_type, date_period, qr_coins_earned, transaction_count, metadata)
SELECT 
    bcs.business_id,
    'casino_revenue_share' as source_type,
    CURDATE() as date_period,
    FLOOR(RAND() * 500) + 100 as qr_coins_earned,
    FLOOR(RAND() * 20) + 5 as transaction_count,
    JSON_OBJECT(
        'revenue_share_rate', 0.10,
        'total_plays_at_location', FLOOR(RAND() * 20) + 5,
        'avg_bet_amount', FLOOR(RAND() * 10) + 5
    ) as metadata
FROM business_casino_settings bcs
WHERE bcs.casino_enabled = 1;

-- 6. Initialize wallet balances from revenue sources
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

-- Show results
SELECT 'Business Wallet System created successfully!' as status;
SELECT COUNT(*) as business_wallets_created FROM business_wallets;
SELECT COUNT(*) as revenue_sources_initialized FROM business_revenue_sources;

-- Show sample wallet data
SELECT 
    b.name as business_name,
    bw.qr_coin_balance,
    bw.total_earned_all_time,
    bw.last_transaction_at
FROM business_wallets bw
JOIN businesses b ON bw.business_id = b.id
WHERE bw.qr_coin_balance > 0
ORDER BY bw.qr_coin_balance DESC
LIMIT 5; 