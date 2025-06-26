-- NAYAX BUSINESS INTEGRATION SCHEMA UPDATES
-- This adds business-level access token management and enhanced machine inventory integration
-- Date: 2025-01-17

USE revenueqr;

START TRANSACTION;

-- =============================================================================
-- 1. BUSINESS NAYAX CREDENTIALS TABLE
-- =============================================================================

-- Store encrypted Nayax access tokens per business
CREATE TABLE IF NOT EXISTS business_nayax_credentials (
    id INT PRIMARY KEY AUTO_INCREMENT,
    business_id INT NOT NULL,
    access_token VARBINARY(255) NOT NULL COMMENT 'Encrypted Nayax access token',
    api_url VARCHAR(255) DEFAULT 'https://lynx.nayax.com/operational/api/v1' COMMENT 'Nayax API endpoint',
    is_active BOOLEAN DEFAULT TRUE,
    last_sync_at TIMESTAMP NULL COMMENT 'Last successful machine sync',
    total_machines INT DEFAULT 0 COMMENT 'Cached machine count',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_business_nayax (business_id),
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    INDEX idx_active_credentials (is_active, last_sync_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 2. ENHANCE EXISTING NAYAX_MACHINES TABLE
-- =============================================================================

-- Add missing columns to nayax_machines if they don't exist
SET @sql = (SELECT CASE 
    WHEN COUNT(*) = 0 THEN 'ALTER TABLE nayax_machines ADD COLUMN location VARCHAR(255) DEFAULT "" COMMENT "Machine location description"'
    ELSE 'SELECT "location column already exists" as status'
END 
FROM information_schema.columns 
WHERE table_schema = DATABASE() AND table_name = 'nayax_machines' AND column_name = 'location');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT CASE 
    WHEN COUNT(*) = 0 THEN 'ALTER TABLE nayax_machines ADD COLUMN device_info JSON NULL COMMENT "Complete device information from Nayax API"'
    ELSE 'SELECT "device_info column already exists" as status'
END 
FROM information_schema.columns 
WHERE table_schema = DATABASE() AND table_name = 'nayax_machines' AND column_name = 'device_info');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (SELECT CASE 
    WHEN COUNT(*) = 0 THEN 'ALTER TABLE nayax_machines ADD COLUMN last_sync_at TIMESTAMP NULL COMMENT "Last inventory sync timestamp"'
    ELSE 'SELECT "last_sync_at column already exists" as status'
END 
FROM information_schema.columns 
WHERE table_schema = DATABASE() AND table_name = 'nayax_machines' AND column_name = 'last_sync_at');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =============================================================================
-- 3. MACHINE INVENTORY CACHE TABLE
-- =============================================================================

-- Cache machine inventory data from Nayax API
CREATE TABLE IF NOT EXISTS nayax_machine_inventory (
    id INT PRIMARY KEY AUTO_INCREMENT,
    machine_id VARCHAR(50) NOT NULL COMMENT 'Nayax machine ID',
    business_id INT NOT NULL,
    inventory_data JSON COMMENT 'Complete inventory response from Nayax API',
    product_count INT DEFAULT 0 COMMENT 'Number of products in machine',
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_machine_inventory (machine_id, business_id),
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    INDEX idx_business_inventory (business_id, last_updated),
    INDEX idx_machine_lookup (machine_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- 4. ENHANCE BUSINESS_STORE_ITEMS FOR NAYAX INTEGRATION
-- =============================================================================

-- Add Nayax item selection column if missing
SET @sql = (SELECT CASE 
    WHEN COUNT(*) = 0 THEN 'ALTER TABLE business_store_items ADD COLUMN nayax_item_selection VARCHAR(10) NULL COMMENT "Machine selection code (A1, B2, etc.)"'
    ELSE 'SELECT "nayax_item_selection column already exists" as status'
END 
FROM information_schema.columns 
WHERE table_schema = DATABASE() AND table_name = 'business_store_items' AND column_name = 'nayax_item_selection');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add original product price for discount calculations
SET @sql = (SELECT CASE 
    WHEN COUNT(*) = 0 THEN 'ALTER TABLE business_store_items ADD COLUMN original_price_cents INT NULL COMMENT "Original product price from Nayax for discount calculation"'
    ELSE 'SELECT "original_price_cents column already exists" as status'
END 
FROM information_schema.columns 
WHERE table_schema = DATABASE() AND table_name = 'business_store_items' AND column_name = 'original_price_cents');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add max uses tracking
SET @sql = (SELECT CASE 
    WHEN COUNT(*) = 0 THEN 'ALTER TABLE business_store_items ADD COLUMN max_uses INT DEFAULT 100 COMMENT "Maximum number of times this discount can be used"'
    ELSE 'SELECT "max_uses column already exists" as status'
END 
FROM information_schema.columns 
WHERE table_schema = DATABASE() AND table_name = 'business_store_items' AND column_name = 'max_uses');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add current uses tracking
SET @sql = (SELECT CASE 
    WHEN COUNT(*) = 0 THEN 'ALTER TABLE business_store_items ADD COLUMN current_uses INT DEFAULT 0 COMMENT "Current number of times this discount has been used"'
    ELSE 'SELECT "current_uses column already exists" as status'
END 
FROM information_schema.columns 
WHERE table_schema = DATABASE() AND table_name = 'business_store_items' AND column_name = 'current_uses');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =============================================================================
-- 5. ADD PERFORMANCE INDEXES
-- =============================================================================

-- Add indexes for better performance
CREATE INDEX idx_business_store_nayax_machine ON business_store_items(nayax_machine_id);
CREATE INDEX idx_business_store_selection ON business_store_items(nayax_item_selection);
CREATE INDEX idx_business_store_discount_type ON business_store_items(category, is_active);
CREATE INDEX idx_nayax_machines_business_status ON nayax_machines(business_id, status);

-- =============================================================================
-- 6. CREATE INITIAL CONFIGURATION DATA
-- =============================================================================

-- Insert default configuration values
INSERT IGNORE INTO config_settings (setting_key, setting_value, setting_type, description) VALUES
('nayax_business_integration_enabled', 'true', 'boolean', 'Enable business-level Nayax integration'),
('nayax_inventory_sync_interval', '3600', 'int', 'Inventory sync interval in seconds (1 hour)'),
('nayax_max_discount_percent', '50', 'int', 'Maximum discount percentage allowed'),
('nayax_min_discount_percent', '5', 'int', 'Minimum discount percentage allowed'),
('nayax_default_qr_coin_price', '50', 'int', 'Default QR coin price for discounts'),
('nayax_connection_timeout', '30', 'int', 'API connection timeout in seconds');

COMMIT;

-- =============================================================================
-- 7. VERIFICATION QUERY
-- =============================================================================

SELECT 'NAYAX BUSINESS INTEGRATION SCHEMA COMPLETED!' as status;

-- Show what we created
SELECT 'business_nayax_credentials' as table_name, COUNT(*) as record_count FROM business_nayax_credentials
UNION ALL
SELECT 'nayax_machine_inventory', COUNT(*) FROM nayax_machine_inventory
UNION ALL
SELECT 'nayax_machines', COUNT(*) FROM nayax_machines
UNION ALL
SELECT 'business_store_items (discount category)', COUNT(*) FROM business_store_items WHERE category = 'discount'; 