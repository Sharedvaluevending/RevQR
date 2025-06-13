-- QR Coin Economy 2.0 - Phase 2: Business & QR Stores
-- Business discount stores and user QR coin purchasing system
-- Date: 2025-01-17

USE revenueqr;

-- Start transaction for safety
START TRANSACTION;

-- 1. Business Store Items - What businesses can sell for QR coins
CREATE TABLE IF NOT EXISTS business_store_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    item_description TEXT,
    regular_price_cents INT NOT NULL, -- Regular price in cents
    discount_percentage DECIMAL(5,2) NOT NULL, -- 5.00 = 5%
    qr_coin_cost INT NOT NULL, -- Cost in QR coins
    category ENUM('discount', 'food', 'beverage', 'snack', 'combo', 'other') DEFAULT 'discount',
    stock_quantity INT DEFAULT -1, -- -1 = unlimited
    is_active BOOLEAN DEFAULT TRUE,
    machine_id INT NULL, -- Optional: specific machine
    valid_from DATETIME DEFAULT CURRENT_TIMESTAMP,
    valid_until DATETIME NULL, -- NULL = no expiry
    max_per_user INT DEFAULT 1, -- Max purchases per user
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (machine_id) REFERENCES voting_lists(id) ON DELETE SET NULL,
    INDEX idx_business_active (business_id, is_active),
    INDEX idx_category (category),
    INDEX idx_valid_period (valid_from, valid_until),
    INDEX idx_qr_cost (qr_coin_cost)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. User Store Purchases - Track what users bought
CREATE TABLE IF NOT EXISTS user_store_purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    business_id INT NOT NULL,
    store_item_id INT NOT NULL,
    qr_coins_spent INT NOT NULL,
    discount_amount_cents INT NOT NULL,
    purchase_code VARCHAR(20) UNIQUE NOT NULL, -- Unique code for redemption
    status ENUM('pending', 'redeemed', 'expired', 'cancelled') DEFAULT 'pending',
    redeemed_at TIMESTAMP NULL,
    redeemed_by INT NULL, -- Business user who processed redemption
    machine_id INT NULL, -- Where it was redeemed
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (store_item_id) REFERENCES business_store_items(id) ON DELETE CASCADE,
    FOREIGN KEY (redeemed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (machine_id) REFERENCES voting_lists(id) ON DELETE SET NULL,
    INDEX idx_user_purchases (user_id, created_at),
    INDEX idx_business_sales (business_id, status),
    INDEX idx_purchase_code (purchase_code),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. QR Store Items - Platform-wide QR coin items (avatars, spins, etc.)
CREATE TABLE IF NOT EXISTS qr_store_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_type ENUM('avatar', 'spin_pack', 'vote_pack', 'multiplier', 'insurance', 'analytics', 'boost') NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    item_description TEXT,
    qr_coin_cost INT NOT NULL,
    item_data JSON, -- Specific data per item type
    rarity ENUM('common', 'rare', 'epic', 'legendary') DEFAULT 'common',
    is_active BOOLEAN DEFAULT TRUE,
    is_limited BOOLEAN DEFAULT FALSE,
    stock_quantity INT DEFAULT -1, -- -1 = unlimited
    purchase_limit_per_user INT DEFAULT -1, -- -1 = unlimited
    valid_from DATETIME DEFAULT CURRENT_TIMESTAMP,
    valid_until DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_item_type (item_type, is_active),
    INDEX idx_rarity (rarity),
    INDEX idx_cost (qr_coin_cost),
    INDEX idx_limited (is_limited, stock_quantity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. User QR Store Purchases - Track platform purchases
CREATE TABLE IF NOT EXISTS user_qr_store_purchases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    qr_store_item_id INT NOT NULL,
    qr_coins_spent INT NOT NULL,
    quantity INT DEFAULT 1,
    status ENUM('active', 'used', 'expired', 'refunded') DEFAULT 'active',
    item_data JSON, -- Copy of item data at purchase time
    expires_at TIMESTAMP NULL,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (qr_store_item_id) REFERENCES qr_store_items(id) ON DELETE CASCADE,
    INDEX idx_user_qr_purchases (user_id, status),
    INDEX idx_expiry (expires_at),
    INDEX idx_item_purchases (qr_store_item_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Store Analytics - Track store performance
CREATE TABLE IF NOT EXISTS store_analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NULL, -- NULL for platform-wide analytics
    date DATE NOT NULL,
    total_sales_qr_coins INT DEFAULT 0,
    total_sales_usd_cents INT DEFAULT 0,
    total_transactions INT DEFAULT 0,
    top_selling_item_id INT NULL,
    avg_transaction_size_coins INT DEFAULT 0,
    unique_customers INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_business_date (business_id, date),
    INDEX idx_date (date),
    INDEX idx_business_analytics (business_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Add new QR code type for store items
ALTER TABLE qr_codes 
ADD COLUMN IF NOT EXISTS store_item_id INT NULL,
ADD COLUMN IF NOT EXISTS qr_store_item_id INT NULL,
ADD CONSTRAINT fk_qr_store_item FOREIGN KEY (store_item_id) REFERENCES business_store_items(id) ON DELETE CASCADE,
ADD CONSTRAINT fk_qr_store_qr_item FOREIGN KEY (qr_store_item_id) REFERENCES qr_store_items(id) ON DELETE CASCADE;

-- Extend qr_type enum to include store types
ALTER TABLE qr_codes MODIFY qr_type ENUM('voting', 'pizza_tracker', 'spin_wheel', 'business_store', 'qr_store', 'discount_voucher') NOT NULL;

-- 7. Insert sample business store items for existing businesses
INSERT IGNORE INTO business_store_items (business_id, item_name, item_description, regular_price_cents, discount_percentage, qr_coin_cost, category, machine_id)
SELECT 
    b.id as business_id,
    CONCAT('5% Discount - ', b.business_name) as item_name,
    'Get 5% off your next purchase at this vending machine' as item_description,
    500 as regular_price_cents, -- $5.00 average item
    5.00 as discount_percentage,
    15000 as qr_coin_cost, -- 15,000 QR coins for 5% discount
    'discount' as category,
    NULL as machine_id
FROM businesses b
WHERE EXISTS (SELECT 1 FROM business_subscriptions bs WHERE bs.business_id = b.id);

INSERT IGNORE INTO business_store_items (business_id, item_name, item_description, regular_price_cents, discount_percentage, qr_coin_cost, category, machine_id)
SELECT 
    b.id as business_id,
    CONCAT('10% Discount - ', b.business_name) as item_name,
    'Get 10% off your next purchase at this vending machine' as item_description,
    500 as regular_price_cents,
    10.00 as discount_percentage,
    35000 as qr_coin_cost, -- 35,000 QR coins for 10% discount
    'discount' as category,
    NULL as machine_id
FROM businesses b
WHERE EXISTS (SELECT 1 FROM business_subscriptions bs WHERE bs.business_id = b.id);

-- 8. Insert sample QR store items
INSERT IGNORE INTO qr_store_items (item_type, item_name, item_description, qr_coin_cost, item_data, rarity) VALUES
('avatar', 'QR Easybake Avatar', 'Exclusive QR Easybake character avatar', 75000, JSON_OBJECT('avatar_id', 'qr_easybake', 'special_effects', true), 'legendary'),
('avatar', 'Golden Crown Avatar', 'Shiny golden crown avatar', 50000, JSON_OBJECT('avatar_id', 'golden_crown', 'glow_effect', true), 'epic'),
('avatar', 'Silver Star Avatar', 'Silver star avatar with sparkles', 25000, JSON_OBJECT('avatar_id', 'silver_star', 'sparkle_effect', true), 'rare'),
('spin_pack', 'Extra Daily Spins (5)', 'Get 5 additional spins per day for 7 days', 1000, JSON_OBJECT('spins_per_day', 5, 'duration_days', 7), 'common'),
('vote_pack', 'Vote Multiplier 2x', 'Double voting points for 24 hours', 2500, JSON_OBJECT('multiplier', 2, 'duration_hours', 24), 'rare'),
('insurance', 'Streak Protection', 'Protect your voting/spinning streak from breaking', 5000, JSON_OBJECT('protections', 3, 'duration_days', 30), 'epic'),
('analytics', 'Premium Analytics', 'Advanced analytics and insights for 30 days', 10000, JSON_OBJECT('features', ['detailed_stats', 'comparisons', 'trends'], 'duration_days', 30), 'rare'),
('boost', 'QR Coin Boost 25%', 'Get 25% more QR coins for 48 hours', 7500, JSON_OBJECT('boost_percentage', 25, 'duration_hours', 48), 'epic');

-- 9. Add configuration for store features
INSERT IGNORE INTO config_settings (setting_key, setting_value, setting_type, description) VALUES
('business_store_enabled', 'false', 'boolean', 'Whether business discount stores are enabled'),
('qr_store_enabled', 'false', 'boolean', 'Whether platform QR coin store is enabled'),
('store_commission_rate', '0.10', 'float', 'Platform commission rate on business store sales'),
('max_discount_percentage', '20.00', 'float', 'Maximum discount percentage businesses can offer'),
('min_qr_coin_purchase', '1000', 'int', 'Minimum QR coins required for store purchases'),
('purchase_code_expiry_days', '30', 'int', 'Days before unused purchase codes expire'),
('store_analytics_enabled', 'true', 'boolean', 'Whether to track store analytics'),
('daily_purchase_limit_enabled', 'true', 'boolean', 'Whether to enforce daily purchase limits');

-- Commit the transaction
COMMIT;

-- Show results
SELECT 'Phase 2 store tables created successfully!' as status;
SELECT COUNT(*) as business_store_items FROM business_store_items;
SELECT COUNT(*) as qr_store_items FROM qr_store_items;
SELECT COUNT(*) as new_configs FROM config_settings WHERE setting_key LIKE '%store%';

-- Show sample business items
SELECT 
    bsi.item_name,
    b.business_name,
    bsi.discount_percentage,
    bsi.qr_coin_cost
FROM business_store_items bsi
JOIN businesses b ON bsi.business_id = b.id
LIMIT 5; 