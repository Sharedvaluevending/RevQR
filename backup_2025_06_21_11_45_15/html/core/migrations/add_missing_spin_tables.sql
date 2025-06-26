-- Migration: Add Missing Spin Wheels Tables
-- Date: 2024-01-28
-- Description: Adds missing tables for spin wheel management

-- Start transaction for safety
START TRANSACTION;

-- 1. Create spin_wheels table for multiple wheel management
CREATE TABLE spin_wheels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    business_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    wheel_type ENUM('campaign', 'machine', 'qr_standalone') DEFAULT 'campaign',
    campaign_id INT NULL,
    machine_name VARCHAR(255) NULL,
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
    
    -- Foreign key constraints
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE SET NULL,
    FOREIGN KEY (qr_code_id) REFERENCES qr_codes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Update QR code types to include 'spin_wheel' if not already there
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

-- 3. Check if voting_lists needs spin columns
SET @col_exists = (SELECT COUNT(*) FROM information_schema.columns 
                   WHERE table_schema = 'revenueqr' 
                   AND table_name = 'voting_lists' 
                   AND column_name = 'spin_enabled');

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE voting_lists 
     ADD COLUMN spin_enabled BOOLEAN DEFAULT FALSE AFTER description,
     ADD COLUMN spin_trigger_count INT DEFAULT 3 AFTER spin_enabled',
    'SELECT "Spin columns already exist in voting_lists" as status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. Add foreign key constraint for rewards.spin_wheel_id if it doesn't exist
SET @constraint_exists = (SELECT COUNT(*) FROM information_schema.table_constraints 
                          WHERE table_schema = 'revenueqr' 
                          AND table_name = 'rewards' 
                          AND constraint_name LIKE '%spin_wheel%');

SET @sql = IF(@constraint_exists = 0,
    'ALTER TABLE rewards 
     ADD FOREIGN KEY (spin_wheel_id) REFERENCES spin_wheels(id) ON DELETE SET NULL',
    'SELECT "Foreign key constraint already exists for rewards.spin_wheel_id" as status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. Add foreign key constraint for spin_results.spin_wheel_id if it doesn't exist
SET @constraint_exists = (SELECT COUNT(*) FROM information_schema.table_constraints 
                          WHERE table_schema = 'revenueqr' 
                          AND table_name = 'spin_results' 
                          AND constraint_name LIKE '%spin_wheel%');

SET @sql = IF(@constraint_exists = 0,
    'ALTER TABLE spin_results 
     ADD FOREIGN KEY (spin_wheel_id) REFERENCES spin_wheels(id) ON DELETE SET NULL',
    'SELECT "Foreign key constraint already exists for spin_results.spin_wheel_id" as status'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 6. Create default spin wheels for existing businesses
INSERT INTO spin_wheels (business_id, name, description, wheel_type, is_active)
SELECT DISTINCT 
    b.id as business_id,
    CONCAT(b.name, ' - Default Wheel') as name,
    'Auto-created default spin wheel for existing business' as description,
    'campaign' as wheel_type,
    TRUE as is_active
FROM businesses b;

-- Commit transaction
COMMIT;

-- Verification
SELECT 'Migration completed successfully' as status;
SELECT COUNT(*) as total_spin_wheels FROM spin_wheels;
SELECT COUNT(*) as total_businesses FROM businesses; 