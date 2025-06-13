-- NAYAX INTEGRATION - PHASE 1: Database Foundation
-- Safe to run - will not break existing functionality
-- Date: 2025-01-17
-- Dependencies: Existing revenueqr database with QR Coin Economy 2.0

USE revenueqr;

-- Start transaction for safety
START TRANSACTION;

-- =============================================================================
-- 1. NAYAX MACHINE INTEGRATION
-- =============================================================================

-- Map platform machines to Nayax machines
CREATE TABLE IF NOT EXISTS nayax_machines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    platform_machine_id INT NULL COMMENT 'Link to existing voting_lists/machines',
    nayax_machine_id VARCHAR(50) NOT NULL UNIQUE COMMENT 'Nayax Machine ID like 54265',
    nayax_device_id VARCHAR(50) NOT NULL COMMENT 'Nayax Device ID like 25795395',
    machine_name VARCHAR(255) NOT NULL COMMENT 'Human readable name',
    machine_serial VARCHAR(100) NULL,
    device_serial VARCHAR(100) NULL COMMENT 'Device Serial from events',
    location_code VARCHAR(50) NULL,
    location_description VARCHAR(255) NULL,
    status ENUM('active', 'inactive', 'maintenance', 'error') DEFAULT 'active',
    last_seen_at TIMESTAMP NULL COMMENT 'Last event received',
    machine_config JSON NULL COMMENT 'Nayax machine configuration',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (platform_machine_id) REFERENCES voting_lists(id) ON DELETE SET NULL,
    INDEX idx_business_machines (business_id, status),
    INDEX idx_nayax_machine (nayax_machine_id),
    INDEX idx_device_id (nayax_device_id),
    INDEX idx_last_seen (last_seen_at),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 2. NAYAX TRANSACTION STORAGE
-- =============================================================================

-- Store complete Nayax transaction data
CREATE TABLE IF NOT EXISTS nayax_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    nayax_machine_id VARCHAR(50) NOT NULL,
    nayax_transaction_id VARCHAR(50) NOT NULL UNIQUE COMMENT 'Nayax TransactionId',
    user_id INT NULL COMMENT 'Linked platform user',
    card_string VARCHAR(50) NULL COMMENT 'Card String for user identification',
    transaction_type ENUM('sale', 'qr_coin_purchase', 'discount_redemption', 'refund') NOT NULL,
    amount_cents INT NOT NULL COMMENT 'Transaction amount in cents',
    currency VARCHAR(3) DEFAULT 'USD',
    payment_method VARCHAR(100) NULL COMMENT 'Payment method description',
    machine_time TIMESTAMP NULL COMMENT 'Machine timestamp',
    settlement_time TIMESTAMP NULL COMMENT 'Settlement timestamp',
    status ENUM('pending', 'completed', 'failed', 'cancelled', 'voided') DEFAULT 'pending',
    transaction_data JSON NOT NULL COMMENT 'Complete Nayax transaction JSON',
    processed_at TIMESTAMP NULL COMMENT 'When we processed this transaction',
    qr_coins_awarded INT DEFAULT 0 COMMENT 'QR coins given for this transaction',
    platform_commission_cents INT DEFAULT 0 COMMENT 'Platform commission earned',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_nayax_transaction (nayax_transaction_id),
    INDEX idx_business_transactions (business_id, created_at),
    INDEX idx_machine_transactions (nayax_machine_id, created_at),
    INDEX idx_user_transactions (user_id, created_at),
    INDEX idx_card_string (card_string),
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_status (status),
    INDEX idx_processed (processed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 3. AWS SQS EVENTS STORAGE
-- =============================================================================

-- Store AWS SQS events from Nayax
CREATE TABLE IF NOT EXISTS nayax_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NULL,
    nayax_machine_id VARCHAR(50) NOT NULL,
    nayax_device_id VARCHAR(50) NOT NULL,
    event_source_id VARCHAR(10) NOT NULL COMMENT 'Event source like 6 for Device',
    event_code VARCHAR(10) NOT NULL COMMENT 'Event code like 1 for Power up',
    event_name VARCHAR(100) NOT NULL COMMENT 'Human readable event name',
    event_date_gmt TIMESTAMP NOT NULL COMMENT 'Event timestamp from Nayax',
    event_date_machine TIMESTAMP NOT NULL COMMENT 'Machine local time',
    event_data TEXT NOT NULL COMMENT 'Full event data from eventData field',
    sqs_message_id VARCHAR(100) NULL COMMENT 'SQS message ID for deduplication',
    processed BOOLEAN DEFAULT FALSE,
    processed_at TIMESTAMP NULL,
    processing_error TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE SET NULL,
    INDEX idx_machine_events (nayax_machine_id, event_date_gmt),
    INDEX idx_device_events (nayax_device_id, event_date_gmt),
    INDEX idx_event_type (event_code, event_name),
    INDEX idx_processed (processed, processed_at),
    INDEX idx_sqs_message (sqs_message_id),
    INDEX idx_created_at (created_at),
    INDEX idx_business_events (business_id, event_date_gmt)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 4. QR COIN PACKS FOR NAYAX MACHINES
-- =============================================================================

-- QR Coin Packs sold through Nayax machines
CREATE TABLE IF NOT EXISTS nayax_qr_coin_products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    nayax_machine_id VARCHAR(50) NOT NULL,
    product_name VARCHAR(255) NOT NULL COMMENT 'Display name like "1000 QR Coins"',
    product_description TEXT NULL,
    qr_coin_amount INT NOT NULL COMMENT 'Number of QR coins in pack',
    price_cents INT NOT NULL COMMENT 'Price in cents',
    currency VARCHAR(3) DEFAULT 'USD',
    nayax_product_code VARCHAR(50) NULL COMMENT 'Product code in Nayax system',
    bonus_percentage DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Bonus coins percentage',
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    valid_from DATETIME DEFAULT CURRENT_TIMESTAMP,
    valid_until DATETIME NULL,
    max_per_user_per_day INT DEFAULT -1 COMMENT '-1 = unlimited',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    INDEX idx_machine_products (nayax_machine_id, is_active),
    INDEX idx_business_products (business_id, is_active),
    INDEX idx_price_range (price_cents),
    INDEX idx_coin_amount (qr_coin_amount),
    INDEX idx_product_code (nayax_product_code),
    INDEX idx_valid_period (valid_from, valid_until)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 5. USER CARD MAPPING SYSTEM
-- =============================================================================

-- Map Nayax card strings to platform users
CREATE TABLE IF NOT EXISTS nayax_user_cards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    card_string VARCHAR(50) NOT NULL COMMENT 'Card String from Nayax like 0077020491',
    card_type VARCHAR(100) NULL COMMENT 'Card type description',
    card_first_4 VARCHAR(4) NULL,
    card_last_4 VARCHAR(4) NULL,
    status ENUM('active', 'inactive', 'blocked') DEFAULT 'active',
    first_used_at TIMESTAMP NULL,
    last_used_at TIMESTAMP NULL,
    total_transactions INT DEFAULT 0,
    total_spent_cents INT DEFAULT 0,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_card_string (card_string),
    INDEX idx_user_cards (user_id, status),
    INDEX idx_card_lookup (card_string),
    INDEX idx_card_usage (last_used_at, status),
    INDEX idx_transaction_stats (total_transactions, total_spent_cents)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 6. AWS CONFIGURATION STORAGE
-- =============================================================================

-- Store AWS SQS configuration
CREATE TABLE IF NOT EXISTS nayax_aws_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NULL COMMENT 'NULL for global config',
    config_key VARCHAR(100) NOT NULL,
    config_value TEXT NOT NULL,
    config_type ENUM('string', 'json', 'encrypted') DEFAULT 'string',
    description TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_business_config (business_id, config_key),
    INDEX idx_config_lookup (config_key, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 7. ENHANCE EXISTING STORE TABLES
-- =============================================================================

-- Add Nayax compatibility to business store items (using safe column addition)
SET @sql = (SELECT CASE 
    WHEN COUNT(*) = 0 THEN 'ALTER TABLE business_store_items ADD COLUMN nayax_machine_id VARCHAR(50) NULL COMMENT "Specific Nayax machine for redemption"'
    ELSE 'SELECT "nayax_machine_id column already exists" as status'
END 
FROM information_schema.columns 
WHERE table_schema = 'revenueqr' AND table_name = 'business_store_items' AND column_name = 'nayax_machine_id');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT CASE 
    WHEN COUNT(*) = 0 THEN 'ALTER TABLE business_store_items ADD COLUMN nayax_product_code VARCHAR(50) NULL COMMENT "Product code for Nayax integration"'
    ELSE 'SELECT "nayax_product_code column already exists" as status'
END 
FROM information_schema.columns 
WHERE table_schema = 'revenueqr' AND table_name = 'business_store_items' AND column_name = 'nayax_product_code');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT CASE 
    WHEN COUNT(*) = 0 THEN 'ALTER TABLE business_store_items ADD COLUMN discount_code_prefix VARCHAR(10) DEFAULT "DSC" COMMENT "Prefix for generated discount codes"'
    ELSE 'SELECT "discount_code_prefix column already exists" as status'
END 
FROM information_schema.columns 
WHERE table_schema = 'revenueqr' AND table_name = 'business_store_items' AND column_name = 'discount_code_prefix');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add Nayax compatibility to QR store items
SET @sql = (SELECT CASE 
    WHEN COUNT(*) = 0 THEN 'ALTER TABLE qr_store_items ADD COLUMN nayax_compatible BOOLEAN DEFAULT TRUE COMMENT "Can be purchased with Nayax payments"'
    ELSE 'SELECT "nayax_compatible column already exists" as status'
END 
FROM information_schema.columns 
WHERE table_schema = 'revenueqr' AND table_name = 'qr_store_items' AND column_name = 'nayax_compatible');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT CASE 
    WHEN COUNT(*) = 0 THEN 'ALTER TABLE qr_store_items ADD COLUMN nayax_integration_data JSON NULL COMMENT "Nayax-specific configuration"'
    ELSE 'SELECT "nayax_integration_data column already exists" as status'
END 
FROM information_schema.columns 
WHERE table_schema = 'revenueqr' AND table_name = 'qr_store_items' AND column_name = 'nayax_integration_data');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add Nayax transaction reference to user store purchases
SET @sql = (SELECT CASE 
    WHEN COUNT(*) = 0 THEN 'ALTER TABLE user_store_purchases ADD COLUMN nayax_transaction_id VARCHAR(50) NULL COMMENT "Reference to Nayax transaction"'
    ELSE 'SELECT "nayax_transaction_id column already exists" as status'
END 
FROM information_schema.columns 
WHERE table_schema = 'revenueqr' AND table_name = 'user_store_purchases' AND column_name = 'nayax_transaction_id');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =============================================================================
-- 8. INSERT SAMPLE DATA AND CONFIGURATION
-- =============================================================================

-- Insert AWS SQS configuration
INSERT IGNORE INTO nayax_aws_config (business_id, config_key, config_value, config_type, description) VALUES
(NULL, 'aws_region', 'us-east-1', 'string', 'AWS region for SQS'),
(NULL, 'sqs_queue_url', 'https://sqs.us-east-1.amazonaws.com/ACCOUNT/nayax-events', 'string', 'SQS queue URL for Nayax events'),
(NULL, 'aws_access_key_id', 'YOUR_ACCESS_KEY', 'encrypted', 'AWS access key for SQS'),
(NULL, 'aws_secret_access_key', 'YOUR_SECRET_KEY', 'encrypted', 'AWS secret key for SQS'),
(NULL, 'polling_interval_seconds', '300', 'string', 'How often to poll SQS (5 minutes)'),
(NULL, 'max_messages_per_poll', '10', 'string', 'Maximum messages to process per poll'),
(NULL, 'message_visibility_timeout', '300', 'string', 'SQS message visibility timeout'),
(NULL, 'event_retention_days', '90', 'string', 'How long to keep event data');

-- Insert sample QR coin packs for existing businesses
INSERT IGNORE INTO nayax_qr_coin_products (business_id, nayax_machine_id, product_name, product_description, qr_coin_amount, price_cents)
SELECT 
    b.id as business_id,
    'SAMPLE_MACHINE' as nayax_machine_id,
    '500 QR Coins Starter Pack' as product_name,
    'Perfect for trying our discount system - includes 50 bonus coins!' as product_description,
    550 as qr_coin_amount, -- 500 + 50 bonus
    250 as price_cents -- $2.50
FROM businesses b 
WHERE EXISTS (SELECT 1 FROM business_subscriptions bs WHERE bs.business_id = b.id)
LIMIT 5;

INSERT IGNORE INTO nayax_qr_coin_products (business_id, nayax_machine_id, product_name, product_description, qr_coin_amount, price_cents)
SELECT 
    b.id as business_id,
    'SAMPLE_MACHINE' as nayax_machine_id,
    '1000 QR Coins Popular Pack' as product_name,
    'Most popular choice - includes 100 bonus coins and better value!' as product_description,
    1100 as qr_coin_amount, -- 1000 + 100 bonus
    500 as price_cents -- $5.00
FROM businesses b 
WHERE EXISTS (SELECT 1 FROM business_subscriptions bs WHERE bs.business_id = b.id)
LIMIT 5;

INSERT IGNORE INTO nayax_qr_coin_products (business_id, nayax_machine_id, product_name, product_description, qr_coin_amount, price_cents)
SELECT 
    b.id as business_id,
    'SAMPLE_MACHINE' as nayax_machine_id,
    '2500 QR Coins Value Pack' as product_name,
    'Best value! Includes 500 bonus coins - save more on discounts!' as product_description,
    3000 as qr_coin_amount, -- 2500 + 500 bonus
    1000 as price_cents -- $10.00
FROM businesses b 
WHERE EXISTS (SELECT 1 FROM business_subscriptions bs WHERE bs.business_id = b.id)
LIMIT 5;

-- Insert Nayax-specific configuration settings
INSERT IGNORE INTO config_settings (setting_key, setting_value, setting_type, description) VALUES
('nayax_integration_enabled', 'false', 'boolean', 'Whether Nayax integration is enabled'),
('nayax_webhook_secret', 'change_this_secret_key', 'string', 'Secret key for Nayax webhook verification'),
('nayax_commission_rate', '0.10', 'float', 'Platform commission rate on Nayax transactions'),
('nayax_qr_coin_rate', '0.005', 'float', 'USD to QR coin conversion rate (1 coin = $0.005)'),
('nayax_min_purchase_cents', '100', 'int', 'Minimum purchase amount in cents'),
('nayax_max_purchase_cents', '10000', 'int', 'Maximum purchase amount in cents'),
('nayax_reward_rate', '0.02', 'float', 'QR coin reward rate for purchases (2%)'),
('nayax_auto_user_creation', 'true', 'boolean', 'Auto-create users from card strings'),
('nayax_event_processing_enabled', 'true', 'boolean', 'Whether to process AWS SQS events'),
('nayax_discount_code_length', '8', 'int', 'Length of generated discount codes');

-- Update existing QR store items to be Nayax compatible
UPDATE qr_store_items SET nayax_compatible = TRUE WHERE item_type IN ('avatar', 'spin_pack', 'vote_pack');

-- Commit the transaction
COMMIT;

-- =============================================================================
-- 9. VERIFICATION AND SUMMARY
-- =============================================================================

-- Show created tables
SELECT 'NAYAX INTEGRATION PHASE 1 COMPLETED SUCCESSFULLY!' as status;

SELECT 
    'Database Tables Created' as category,
    COUNT(*) as count
FROM information_schema.tables 
WHERE table_schema = 'revenueqr' 
AND table_name LIKE 'nayax_%';

SELECT 
    'Configuration Settings Added' as category,
    COUNT(*) as count
FROM config_settings 
WHERE setting_key LIKE 'nayax_%';

SELECT 
    'Sample QR Coin Products Created' as category,
    COUNT(*) as count
FROM nayax_qr_coin_products;

-- Show table structures for verification
SELECT 'CREATED TABLES:' as info;
SHOW TABLES LIKE 'nayax_%';

-- Show sample data
SELECT 'SAMPLE QR COIN PACKS:' as info;
SELECT 
    nqcp.product_name,
    nqcp.qr_coin_amount,
    nqcp.price_cents / 100 as price_usd,
    b.name as business_name
FROM nayax_qr_coin_products nqcp
JOIN businesses b ON nqcp.business_id = b.id
LIMIT 5;

SELECT 'CONFIGURATION READY:' as info;
SELECT setting_key, setting_value, description 
FROM config_settings 
WHERE setting_key LIKE 'nayax_%' 
ORDER BY setting_key; 