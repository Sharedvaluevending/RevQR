-- Fix Discount Purchase Database Structure
-- Run this to fix the missing columns preventing discount purchases

-- Ensure user_store_purchases table has all required columns
ALTER TABLE user_store_purchases 
ADD COLUMN IF NOT EXISTS business_store_item_id INT NULL AFTER qr_store_item_id,
ADD COLUMN IF NOT EXISTS discount_code VARCHAR(20) NULL,
ADD COLUMN IF NOT EXISTS discount_percent DECIMAL(5,2) NULL,
ADD COLUMN IF NOT EXISTS expires_at DATETIME NULL,
ADD COLUMN IF NOT EXISTS max_uses INT DEFAULT 1,
ADD COLUMN IF NOT EXISTS uses_count INT DEFAULT 0;

-- Ensure business_store_items table exists with proper structure
CREATE TABLE IF NOT EXISTS business_store_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    item_description TEXT,
    regular_price_cents INT NOT NULL DEFAULT 0,
    discount_percentage DECIMAL(5,2) NOT NULL,
    qr_coin_cost INT NOT NULL,
    category VARCHAR(50) DEFAULT 'discount',
    stock_quantity INT DEFAULT -1,
    max_per_user INT DEFAULT 1,
    is_active BOOLEAN DEFAULT TRUE,
    valid_from DATETIME NULL,
    valid_until DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Add some sample discount items if none exist
INSERT IGNORE INTO business_store_items 
(business_id, item_name, item_description, regular_price_cents, discount_percentage, qr_coin_cost, category, is_active)
SELECT 1, '5% Off Any Item', 'Get 5% discount on any purchase', 500, 5.00, 25, 'discount', 1
WHERE NOT EXISTS (SELECT 1 FROM business_store_items WHERE category = 'discount' LIMIT 1);

INSERT IGNORE INTO business_store_items 
(business_id, item_name, item_description, regular_price_cents, discount_percentage, qr_coin_cost, category, is_active)
SELECT 1, '10% Off Any Item', 'Get 10% discount on any purchase', 500, 10.00, 45, 'discount', 1
WHERE NOT EXISTS (SELECT 1 FROM business_store_items WHERE discount_percentage = 10.00 LIMIT 1);

INSERT IGNORE INTO business_store_items 
(business_id, item_name, item_description, regular_price_cents, discount_percentage, qr_coin_cost, category, is_active)
SELECT 1, '15% Off Any Item', 'Get 15% discount on any purchase', 500, 15.00, 65, 'discount', 1
WHERE NOT EXISTS (SELECT 1 FROM business_store_items WHERE discount_percentage = 15.00 LIMIT 1);

-- Ensure businesses table has at least one entry
INSERT IGNORE INTO businesses (id, name, email, status) 
VALUES (1, 'Sample Business', 'sample@business.com', 'active');

SELECT 'Database structure fixed! Discount purchases should now work.' as Status; 