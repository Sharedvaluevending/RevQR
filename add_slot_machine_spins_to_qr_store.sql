-- Add Slot Machine Spins to QR Store
-- Date: 2025-01-11
-- Description: Add daily slot machine spins as purchasable items in QR store

-- Add slot_pack as a new item type for casino spins
ALTER TABLE qr_store_items 
MODIFY COLUMN item_type ENUM(
    'avatar', 
    'spin_pack', 
    'slot_pack',
    'vote_pack', 
    'multiplier', 
    'insurance', 
    'analytics', 
    'boost'
) NOT NULL;

-- Insert slot machine spin pack items
INSERT INTO qr_store_items (
    item_type, 
    item_name, 
    item_description, 
    qr_coin_cost, 
    item_data, 
    rarity, 
    is_active
) VALUES
(
    'slot_pack',
    'Extra Casino Spins (3)',
    'Get 3 additional casino spins per day for 7 days across all casino businesses',
    800,
    JSON_OBJECT(
        'duration_days', 7,
        'spins_per_day', 3,
        'applies_to', 'all_casinos'
    ),
    'common',
    1
),
(
    'slot_pack',
    'Extra Casino Spins (5)',
    'Get 5 additional casino spins per day for 7 days across all casino businesses',
    1200,
    JSON_OBJECT(
        'duration_days', 7,
        'spins_per_day', 5,
        'applies_to', 'all_casinos'
    ),
    'rare',
    1
),
(
    'slot_pack',
    'Premium Casino Spins (10)',
    'Get 10 additional casino spins per day for 14 days across all casino businesses',
    2500,
    JSON_OBJECT(
        'duration_days', 14,
        'spins_per_day', 10,
        'applies_to', 'all_casinos'
    ),
    'epic',
    1
),
(
    'slot_pack',
    'VIP Casino Spins (20)',
    'Get 20 additional casino spins per day for 30 days across all casino businesses',
    5000,
    JSON_OBJECT(
        'duration_days', 30,
        'spins_per_day', 20,
        'applies_to', 'all_casinos'
    ),
    'legendary',
    1
),
(
    'slot_pack',
    'Daily Casino Boost',
    'Get 2 additional casino spins per day for 3 days - Perfect for trying out new casinos!',
    300,
    JSON_OBJECT(
        'duration_days', 3,
        'spins_per_day', 2,
        'applies_to', 'all_casinos'
    ),
    'common',
    1
);

-- Create casino_user_spin_packs table to track slot machine spin pack usage
CREATE TABLE IF NOT EXISTS casino_user_spin_packs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    business_id INT NOT NULL,
    purchase_id INT NOT NULL COMMENT 'References user_qr_store_purchases.id',
    pack_data JSON NOT NULL COMMENT 'Copy of pack data at time of use',
    spins_used INT DEFAULT 0,
    spins_available INT NOT NULL,
    date_activated DATE NOT NULL,
    expires_at TIMESTAMP NULL,
    status ENUM('active', 'used', 'expired') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (purchase_id) REFERENCES user_qr_store_purchases(id) ON DELETE CASCADE,
    
    INDEX idx_user_business_active (user_id, business_id, status),
    INDEX idx_expires (expires_at),
    INDEX idx_purchase (purchase_id),
    UNIQUE KEY unique_user_business_purchase (user_id, business_id, purchase_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Success message
SELECT 'Slot machine spin packs successfully added to QR store!' AS status; 