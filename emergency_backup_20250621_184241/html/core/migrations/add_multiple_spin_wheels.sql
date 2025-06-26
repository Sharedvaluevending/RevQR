-- Migration: Add Multiple Spin Wheels Support
-- Date: 2024-01-28
-- Description: Adds support for multiple spin wheels per business

-- Start transaction for safety
START TRANSACTION;

-- 1. Add spin_wheel_id to rewards table for multiple wheel support
ALTER TABLE rewards ADD COLUMN spin_wheel_id INT NULL AFTER list_id;
ALTER TABLE rewards ADD INDEX idx_rewards_spin_wheel (spin_wheel_id);

-- 2. Add spin_wheel_id to spin_results for tracking which wheel was used
ALTER TABLE spin_results ADD COLUMN spin_wheel_id INT NULL AFTER machine_id;
ALTER TABLE spin_results ADD INDEX idx_spin_results_wheel (spin_wheel_id);

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
    machine_id INT NULL, 
    qr_code_id INT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes for performance
    INDEX idx_spin_wheels_business (business_id),
    INDEX idx_spin_wheels_campaign (campaign_id),
    INDEX idx_spin_wheels_machine (machine_id),
    INDEX idx_spin_wheels_qr (qr_code_id),
    INDEX idx_spin_wheels_active (is_active),
    
    -- Foreign key constraints
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL,
    FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE SET NULL,
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

-- 6. Add foreign key constraints for new columns (after table creation)
ALTER TABLE rewards 
ADD FOREIGN KEY (spin_wheel_id) REFERENCES spin_wheels(id) ON DELETE SET NULL;

ALTER TABLE spin_results 
ADD FOREIGN KEY (spin_wheel_id) REFERENCES spin_wheels(id) ON DELETE SET NULL;

-- 7. Create default spin wheels for existing businesses with rewards
INSERT INTO spin_wheels (business_id, name, description, wheel_type, is_active)
SELECT DISTINCT 
    b.id as business_id,
    CONCAT(b.name, ' - Default Wheel') as name,
    'Auto-created default spin wheel for existing rewards' as description,
    'campaign' as wheel_type,
    TRUE as is_active
FROM businesses b
WHERE EXISTS (
    SELECT 1 FROM rewards r 
    WHERE r.machine_id IN (
        SELECT m.id FROM machines m WHERE m.business_id = b.id
    )
);

-- 8. Link existing rewards to their business's default wheel
UPDATE rewards r
JOIN machines m ON r.machine_id = m.id
JOIN businesses b ON m.business_id = b.id
JOIN spin_wheels sw ON sw.business_id = b.id AND sw.name LIKE CONCAT(b.name, ' - Default Wheel')
SET r.spin_wheel_id = sw.id
WHERE r.spin_wheel_id IS NULL;

-- Commit transaction
COMMIT;

-- Verification queries (commented out for production)
-- SELECT 'Migration completed successfully' as status;
-- SELECT COUNT(*) as spin_wheels_created FROM spin_wheels;
-- SELECT COUNT(*) as rewards_linked FROM rewards WHERE spin_wheel_id IS NOT NULL; 