-- QR Coin Economy 2.0 - Phase 1: Foundation Database Tables
-- Safe to run - will not break existing functionality
-- Date: 2025-01-17

USE revenueqr;

-- Start transaction for safety
START TRANSACTION;

-- 1. Configuration System - Allows dynamic economic settings
CREATE TABLE IF NOT EXISTS config_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'int', 'float', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_setting_key (setting_key),
    INDEX idx_setting_type (setting_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. QR Coin Transaction Tracking - Complete audit trail
CREATE TABLE IF NOT EXISTS qr_coin_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    transaction_type ENUM('earning', 'spending', 'adjustment', 'business_purchase', 'migration') NOT NULL,
    category VARCHAR(50) NOT NULL,
    amount INT NOT NULL, -- Can be negative for spending
    description VARCHAR(255),
    metadata JSON,
    reference_id INT NULL, -- Links to purchases, votes, spins, etc.
    reference_type VARCHAR(50) NULL, -- 'vote', 'spin', 'purchase', etc.
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_transactions (user_id, created_at),
    INDEX idx_transaction_type (transaction_type),
    INDEX idx_reference (reference_type, reference_id),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Business Subscription System
CREATE TABLE IF NOT EXISTS business_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    tier ENUM('starter', 'professional', 'enterprise') NOT NULL DEFAULT 'starter',
    status ENUM('trial', 'active', 'cancelled', 'suspended', 'expired') DEFAULT 'trial',
    billing_cycle ENUM('monthly', 'yearly') DEFAULT 'monthly',
    monthly_price_cents INT NOT NULL, -- Store in cents to avoid float issues
    currency VARCHAR(3) DEFAULT 'USD',
    current_period_start DATE NOT NULL,
    current_period_end DATE NOT NULL,
    features JSON,
    qr_coin_allowance INT NOT NULL DEFAULT 1000,
    qr_coins_used INT DEFAULT 0,
    machine_limit INT DEFAULT 3,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    INDEX idx_business_subscription (business_id, status),
    INDEX idx_subscription_period (current_period_end),
    INDEX idx_tier_status (tier, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Payment Processing (Placeholder for Stripe/Nayax)
CREATE TABLE IF NOT EXISTS business_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    subscription_id INT NOT NULL,
    amount_cents INT NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    payment_method ENUM('stripe', 'nayax', 'manual', 'trial') NOT NULL,
    payment_reference VARCHAR(255), -- Stripe payment_intent_id, Nayax transaction_id, etc.
    status ENUM('pending', 'processing', 'completed', 'failed', 'refunded', 'cancelled') DEFAULT 'pending',
    failure_reason TEXT NULL,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (subscription_id) REFERENCES business_subscriptions(id) ON DELETE CASCADE,
    INDEX idx_business_payments (business_id, status),
    INDEX idx_payment_reference (payment_reference),
    INDEX idx_payment_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Economy Metrics Tracking
CREATE TABLE IF NOT EXISTS economy_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metric_date DATE NOT NULL,
    total_coins_issued INT DEFAULT 0,
    total_coins_burned INT DEFAULT 0,
    active_users INT DEFAULT 0,
    business_revenue_cents INT DEFAULT 0,
    avg_coin_value_cents DECIMAL(10,4) DEFAULT 0.0010, -- Default: $0.001 per coin
    inflation_rate DECIMAL(5,4) DEFAULT 0.0000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_date (metric_date),
    INDEX idx_metric_date (metric_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Insert Default Configuration Values
INSERT IGNORE INTO config_settings (setting_key, setting_value, setting_type, description) VALUES
-- Economic Settings
('qr_coin_vote_base', '5', 'int', 'Base QR coins earned per vote'),
('qr_coin_spin_base', '15', 'int', 'Base QR coins earned per spin'),
('qr_coin_vote_bonus', '25', 'int', 'Daily bonus for voting'),
('qr_coin_spin_bonus', '50', 'int', 'Daily bonus for spinning'),
('qr_coin_decay_rate', '0.02', 'float', 'Monthly decay rate for large balances'),
('qr_coin_decay_threshold', '50000', 'int', 'Balance threshold for decay to apply'),

-- Economy Mode
('economy_mode', 'legacy', 'string', 'Current economy mode: legacy, transition, new'),
('migration_enabled', 'false', 'boolean', 'Whether to migrate existing points to new system'),

-- Business Subscription Pricing (in cents)
('subscription_starter_monthly', '4900', 'int', 'Starter tier monthly price in cents'),
('subscription_professional_monthly', '14900', 'int', 'Professional tier monthly price in cents'),
('subscription_enterprise_monthly', '39900', 'int', 'Enterprise tier monthly price in cents'),

-- Business QR Coin Allowances
('subscription_starter_coins', '1000', 'int', 'Monthly QR coin allowance for Starter tier'),
('subscription_professional_coins', '3000', 'int', 'Monthly QR coin allowance for Professional tier'),
('subscription_enterprise_coins', '8000', 'int', 'Monthly QR coin allowance for Enterprise tier'),

-- Machine Limits
('subscription_starter_machines', '3', 'int', 'Machine limit for Starter tier'),
('subscription_professional_machines', '10', 'int', 'Machine limit for Professional tier'),
('subscription_enterprise_machines', '999', 'int', 'Machine limit for Enterprise tier (unlimited)'),

-- Store Settings
('qr_store_enabled', 'false', 'boolean', 'Whether QR coin store is enabled'),
('qr_store_base_url', 'https://revenueqr.sharedvaluevending.com', 'string', 'Base URL for QR store links'),

-- Avatar Pricing (new system)
('avatar_qr_easybake_cost', '75000', 'int', 'QR Easybake avatar cost in QR coins'),
('avatar_basic_cost', '25000', 'int', 'Basic premium avatar cost'),
('avatar_rare_cost', '50000', 'int', 'Rare avatar cost'),
('avatar_legendary_cost', '100000', 'int', 'Legendary avatar cost'),

-- Discount Pricing
('discount_5_percent_cost', '15000', 'int', '5% machine discount cost'),
('discount_10_percent_cost', '35000', 'int', '10% machine discount cost'),
('discount_15_percent_cost', '60000', 'int', '15% machine discount cost'),
('discount_20_percent_cost', '100000', 'int', '20% machine discount cost'),

-- Feature Pricing
('extra_spins_daily_cost', '1000', 'int', 'Cost for extra daily spins'),
('vote_multiplier_cost', '2500', 'int', 'Cost for 2x voting points (24 hours)'),
('streak_insurance_cost', '5000', 'int', 'Cost for streak protection'),
('premium_analytics_cost', '10000', 'int', 'Cost for premium analytics access'),

-- System Flags
('maintenance_mode', 'false', 'boolean', 'System maintenance mode'),
('new_registrations_enabled', 'true', 'boolean', 'Whether new user registrations are allowed'),
('debug_mode', 'false', 'boolean', 'Debug mode for development');

-- 7. Create default trial subscriptions for existing businesses
INSERT IGNORE INTO business_subscriptions (business_id, tier, status, billing_cycle, monthly_price_cents, current_period_start, current_period_end, qr_coin_allowance, machine_limit, features)
SELECT 
    id as business_id,
    'starter' as tier,
    'trial' as status,
    'monthly' as billing_cycle,
    0 as monthly_price_cents, -- Trial is free
    CURDATE() as current_period_start,
    DATE_ADD(CURDATE(), INTERVAL 30 DAY) as current_period_end,
    1000 as qr_coin_allowance,
    3 as machine_limit,
    JSON_OBJECT(
        'qr_generation', 'basic',
        'analytics', 'basic',
        'support', 'email',
        'api_access', false,
        'white_label', false
    ) as features
FROM businesses 
WHERE NOT EXISTS (
    SELECT 1 FROM business_subscriptions WHERE business_id = businesses.id
);

-- Commit the transaction
COMMIT;

-- Show results
SELECT 'Foundation tables created successfully!' as status;
SELECT COUNT(*) as config_settings_count FROM config_settings;
SELECT COUNT(*) as business_subscriptions_count FROM business_subscriptions;

-- Show current economy settings
SELECT setting_key, setting_value, setting_type, description 
FROM config_settings 
WHERE setting_key LIKE 'qr_coin_%' OR setting_key LIKE 'economy_%'
ORDER BY setting_key; 