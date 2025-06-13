-- Migration: Add Spin Wheels Support (Simplified)
-- Date: 2024-01-28
-- Description: Adds support for multiple spin wheels per business

-- Start transaction for safety
START TRANSACTION;

-- 1. Add spin_wheel_id to rewards table for multiple wheel support
ALTER TABLE rewards ADD COLUMN spin_wheel_id INT NULL AFTER list_id;

-- 2. Add spin_wheel_id to spin_results for tracking which wheel was used
ALTER TABLE spin_results ADD COLUMN spin_wheel_id INT NULL AFTER machine_id;

-- 3. Add spin settings to voting_lists (missing from current schema)
ALTER TABLE voting_lists 
ADD COLUMN spin_enabled BOOLEAN DEFAULT FALSE AFTER description,
ADD COLUMN spin_trigger_count INT DEFAULT 3 AFTER spin_enabled;

-- 4. Create spin_wheels table for multiple wheel management
CREATE TABLE spin_wheels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    wheel_type ENUM('campaign', 'machine', 'qr_standalone') DEFAULT 'campaign',
    campaign_id INT NULL,
    machine_name VARCHAR(255) NULL,  -- Use machine name instead of ID since machines is a view
    qr_code_id INT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes for performance
    INDEX idx_spin_wheels_business (business_id),
    INDEX idx_spin_wheels_campaign (campaign_id),
    INDEX idx_spin_wheels_machine_name (machine_name),
    INDEX idx_spin_wheels_qr (qr_code_id),
    INDEX idx_spin_wheels_active (is_active),
    
    -- Foreign key constraints (only for existing base tables)
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL,
    FOREIGN KEY (qr_code_id) REFERENCES qr_codes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Update QR code types to include 'spin_wheel'
ALTER TABLE qr_codes 
MODIFY qr_type ENUM(
    'static',
    'dynamic',
    'dynamic_voting',
    'dynamic_vending',
    'machine_sales',
    'promotion',
    'spin_wheel',
    'cross_promo',
    'stackable'
) NOT NULL;

-- 6. Add indexes for new columns
ALTER TABLE rewards ADD INDEX idx_rewards_spin_wheel (spin_wheel_id);
ALTER TABLE spin_results ADD INDEX idx_spin_results_wheel (spin_wheel_id);

-- 7. Add foreign key constraints for new columns
ALTER TABLE rewards 
ADD FOREIGN KEY (spin_wheel_id) REFERENCES spin_wheels(id) ON DELETE SET NULL;

ALTER TABLE spin_results 
ADD FOREIGN KEY (spin_wheel_id) REFERENCES spin_wheels(id) ON DELETE SET NULL;

-- 8. Create default spin wheels for existing businesses
INSERT INTO spin_wheels (business_id, name, description, wheel_type, is_active)
SELECT DISTINCT 
    b.id as business_id,
    CONCAT(b.name, ' - Default Wheel') as name,
    'Auto-created default spin wheel for existing business' as description,
    'campaign' as wheel_type,
    TRUE as is_active
FROM businesses b;

-- 9. Link existing rewards to their business's default wheel
UPDATE rewards r
SET r.spin_wheel_id = (
    SELECT sw.id 
    FROM spin_wheels sw 
    JOIN businesses b ON sw.business_id = b.id
    WHERE sw.name LIKE CONCAT(b.name, ' - Default Wheel')
    AND b.id = COALESCE(
        (SELECT business_id FROM campaign_voting_lists cvl 
         JOIN campaigns c ON cvl.campaign_id = c.id 
         WHERE cvl.voting_list_id = r.list_id LIMIT 1),
        (SELECT business_id FROM voting_lists vl WHERE vl.id = r.list_id LIMIT 1),
        1  -- fallback to first business if no link found
    )
    LIMIT 1
)
WHERE r.spin_wheel_id IS NULL;

-- Commit transaction
COMMIT;

-- Verification
SELECT 'Migration completed successfully' as status;
SELECT COUNT(*) as total_spin_wheels FROM spin_wheels;
SELECT COUNT(*) as rewards_with_wheels FROM rewards WHERE spin_wheel_id IS NOT NULL; 