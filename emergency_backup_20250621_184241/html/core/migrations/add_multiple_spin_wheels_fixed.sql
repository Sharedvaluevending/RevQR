-- Migration: Add Multiple Spin Wheels Support (Fixed)
-- Date: 2024-01-28
-- Description: Adds support for multiple spin wheels per business

-- Start transaction for safety
START TRANSACTION;

-- 1. First fix the machines table to have proper primary key if needed
ALTER TABLE machines MODIFY id INT AUTO_INCREMENT PRIMARY KEY;

-- 2. Add spin_wheel_id to rewards table for multiple wheel support
ALTER TABLE rewards ADD COLUMN spin_wheel_id INT NULL AFTER list_id;

-- 3. Add spin_wheel_id to spin_results for tracking which wheel was used
ALTER TABLE spin_results ADD COLUMN spin_wheel_id INT NULL AFTER machine_id;

-- 4. Add spin settings to voting_lists (missing from current schema)
ALTER TABLE voting_lists 
ADD COLUMN spin_enabled BOOLEAN DEFAULT FALSE AFTER description,
ADD COLUMN spin_trigger_count INT DEFAULT 3 AFTER spin_enabled;

-- 5. Create spin_wheels table for multiple wheel management
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

-- 6. Update QR code types to include 'spin_wheel'
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

-- 7. Add indexes for new columns
ALTER TABLE rewards ADD INDEX idx_rewards_spin_wheel (spin_wheel_id);
ALTER TABLE spin_results ADD INDEX idx_spin_results_wheel (spin_wheel_id);

-- 8. Add foreign key constraints for new columns (after table creation)
ALTER TABLE rewards 
ADD FOREIGN KEY (spin_wheel_id) REFERENCES spin_wheels(id) ON DELETE SET NULL;

ALTER TABLE spin_results 
ADD FOREIGN KEY (spin_wheel_id) REFERENCES spin_wheels(id) ON DELETE SET NULL;

-- 9. Create default spin wheels for existing businesses
INSERT INTO spin_wheels (business_id, name, description, wheel_type, is_active)
SELECT DISTINCT 
    b.id as business_id,
    CONCAT(b.name, ' - Default Wheel') as name,
    'Auto-created default spin wheel for existing rewards' as description,
    'campaign' as wheel_type,
    TRUE as is_active
FROM businesses b
WHERE b.id IN (SELECT DISTINCT business_id FROM rewards WHERE business_id IS NOT NULL)
   OR b.id IN (SELECT DISTINCT r.machine_id FROM rewards r WHERE r.machine_id IS NOT NULL);

-- 10. Link existing rewards to their business's default wheel where possible
-- First, for rewards that have direct business_id
UPDATE rewards r
JOIN spin_wheels sw ON sw.business_id = r.machine_id AND sw.name LIKE '% - Default Wheel'
SET r.spin_wheel_id = sw.id
WHERE r.spin_wheel_id IS NULL AND r.machine_id IS NOT NULL;

-- Commit transaction
COMMIT;

-- Verification
SELECT 'Migration completed successfully' as status;
SELECT COUNT(*) as total_spin_wheels FROM spin_wheels;
SELECT COUNT(*) as rewards_with_wheels FROM rewards WHERE spin_wheel_id IS NOT NULL; 