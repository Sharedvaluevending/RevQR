-- QR Code System Unification - Phase 1: Schema Updates
-- Adds missing QR types and prepares schema for unification

USE revenueqr;

-- Step 1: Add missing QR types to enum
ALTER TABLE qr_codes MODIFY COLUMN qr_type ENUM(
    'static',
    'dynamic', 
    'dynamic_voting',
    'dynamic_vending',
    'machine_sales',
    'promotion',
    'spin_wheel',
    'pizza_tracker',
    'cross_promo',
    'stackable'
) NOT NULL DEFAULT 'static';

-- Step 2: Add missing url column if not exists
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = 'revenueqr' 
                   AND TABLE_NAME = 'qr_codes' 
                   AND COLUMN_NAME = 'url');

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE qr_codes ADD COLUMN url VARCHAR(500) NULL AFTER machine_name',
    'SELECT "URL column already exists" as status');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 3: Add enhanced QR options column for advanced features
SET @options_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
                       WHERE TABLE_SCHEMA = 'revenueqr' 
                       AND TABLE_NAME = 'qr_codes' 
                       AND COLUMN_NAME = 'qr_options');

SET @sql = IF(@options_exists = 0, 
    'ALTER TABLE qr_codes ADD COLUMN qr_options JSON NULL AFTER url',
    'SELECT "QR options column already exists" as status');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 4: Add performance indexes
CREATE INDEX IF NOT EXISTS idx_qr_codes_type_status ON qr_codes(qr_type, status);
CREATE INDEX IF NOT EXISTS idx_qr_codes_business_type ON qr_codes(business_id, qr_type);
CREATE INDEX IF NOT EXISTS idx_qr_codes_machine_type ON qr_codes(machine_id, qr_type);

-- Step 5: Update qr_campaigns table to match
ALTER TABLE qr_campaigns MODIFY COLUMN qr_type ENUM(
    'static',
    'dynamic', 
    'dynamic_voting',
    'dynamic_vending',
    'machine_sales',
    'promotion',
    'spin_wheel',
    'pizza_tracker',
    'cross_promo',
    'stackable'
) NOT NULL DEFAULT 'dynamic';

-- Step 6: Create unified QR generation log table
CREATE TABLE IF NOT EXISTS qr_generation_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    qr_code_id INT NOT NULL,
    generation_method VARCHAR(50) NOT NULL,
    api_version VARCHAR(10) NOT NULL DEFAULT 'v1',
    generation_time DECIMAL(8,4) NOT NULL,
    file_size INT NOT NULL,
    options_used JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_log_qr_code (qr_code_id),
    INDEX idx_log_method (generation_method),
    FOREIGN KEY (qr_code_id) REFERENCES qr_codes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 7: Log this migration
INSERT INTO migration_log (phase, step, status, message) 
VALUES ('qr_unification', 1, 'success', 'Schema updated with missing QR types and enhanced columns'); 