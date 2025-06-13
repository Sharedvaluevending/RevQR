-- =============================================================================
-- UNIFIED INVENTORY SYSTEM - PHASE 1: FOUNDATION
-- Creates the core tables to map items between Manual and Nayax systems
-- =============================================================================

-- 1. UNIFIED ITEM MAPPING TABLE
-- Maps individual items across Manual and Nayax systems
CREATE TABLE IF NOT EXISTS unified_item_mapping (
    id INT PRIMARY KEY AUTO_INCREMENT,
    business_id INT NOT NULL,
    
    -- Manual System References
    master_item_id INT NULL COMMENT 'Links to master_items table',
    voting_list_item_id INT NULL COMMENT 'Specific machine item from voting_list_items',
    
    -- Nayax System References  
    nayax_machine_id VARCHAR(50) NULL COMMENT 'Nayax machine identifier',
    nayax_product_code VARCHAR(50) NULL COMMENT 'Product code in Nayax system',
    nayax_slot_position VARCHAR(10) NULL COMMENT 'Physical slot position (A1, B2, etc.)',
    
    -- Unified Item Properties (standardized across systems)
    unified_name VARCHAR(255) NOT NULL COMMENT 'Display name across all systems',
    unified_category VARCHAR(100) NULL COMMENT 'Standardized category name',
    unified_price DECIMAL(10,2) NULL COMMENT 'Standard selling price across systems',
    unified_cost DECIMAL(10,2) NULL COMMENT 'Standard cost basis for calculations',
    unified_sku VARCHAR(100) NULL COMMENT 'Internal SKU for tracking',
    
    -- Mapping Configuration
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Whether this mapping is active',
    sync_inventory BOOLEAN DEFAULT TRUE COMMENT 'Auto-sync stock levels between systems',
    sync_pricing BOOLEAN DEFAULT FALSE COMMENT 'Auto-sync prices between systems',
    mapping_confidence ENUM('high','medium','low') DEFAULT 'high' COMMENT 'Confidence level of the mapping',
    
    -- Business Rules
    auto_created BOOLEAN DEFAULT FALSE COMMENT 'Whether mapping was auto-generated',
    requires_verification BOOLEAN DEFAULT FALSE COMMENT 'Needs business owner verification',
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    mapped_by INT NULL COMMENT 'User ID who created this mapping',
    last_verified_at TIMESTAMP NULL COMMENT 'Last time mapping was verified',
    
    -- Indexes and Constraints
    UNIQUE KEY unique_manual_item (business_id, voting_list_item_id),
    UNIQUE KEY unique_nayax_item (business_id, nayax_machine_id, nayax_slot_position),
    INDEX idx_business_mapping (business_id),
    INDEX idx_unified_name (unified_name),
    INDEX idx_master_item (master_item_id),
    INDEX idx_active_mappings (business_id, is_active),
    INDEX idx_sync_inventory (sync_inventory),
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (master_item_id) REFERENCES master_items(id) ON DELETE SET NULL,
    FOREIGN KEY (voting_list_item_id) REFERENCES voting_list_items(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Maps items between Manual and Nayax systems for unified inventory tracking';

-- 2. UNIFIED INVENTORY STATUS TABLE
-- Tracks real-time inventory status across both systems
CREATE TABLE IF NOT EXISTS unified_inventory_status (
    id INT PRIMARY KEY AUTO_INCREMENT,
    unified_mapping_id INT NOT NULL,
    business_id INT NOT NULL,
    
    -- Current Stock Levels
    manual_stock_qty INT DEFAULT 0 COMMENT 'Current manual stock from warehouse_inventory',
    nayax_estimated_qty INT DEFAULT 0 COMMENT 'Estimated Nayax stock based on sales patterns',
    nayax_last_restock_qty INT DEFAULT 0 COMMENT 'Last known restock amount for Nayax',
    nayax_last_restock_date TIMESTAMP NULL COMMENT 'When Nayax machine was last restocked',
    
    -- Calculated Combined Metrics (updated by triggers/procedures)
    total_available_qty INT DEFAULT 0 COMMENT 'Total estimated available across both systems',
    low_stock_threshold INT DEFAULT 5 COMMENT 'Alert threshold for low stock',
    reorder_point INT DEFAULT 10 COMMENT 'When to reorder inventory',
    max_capacity INT DEFAULT 100 COMMENT 'Maximum capacity for this item',
    
    -- Sales Performance Tracking
    manual_sales_today INT DEFAULT 0,
    nayax_sales_today INT DEFAULT 0,
    total_sales_today INT DEFAULT 0,
    
    manual_sales_week INT DEFAULT 0,
    nayax_sales_week INT DEFAULT 0,
    total_sales_week INT DEFAULT 0,
    
    manual_sales_month INT DEFAULT 0,
    nayax_sales_month INT DEFAULT 0,
    total_sales_month INT DEFAULT 0,
    
    -- Performance Metrics
    avg_daily_sales DECIMAL(8,2) DEFAULT 0.00 COMMENT 'Average daily sales across both systems',
    days_until_empty INT NULL COMMENT 'Estimated days until out of stock',
    velocity_score DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Sales velocity score (0-100)',
    
    -- System Sync Status
    last_manual_update TIMESTAMP NULL COMMENT 'Last time manual data was synced',
    last_nayax_update TIMESTAMP NULL COMMENT 'Last time Nayax data was synced',
    sync_status ENUM('synced','partial','error','pending') DEFAULT 'synced',
    sync_error_message TEXT NULL COMMENT 'Details of any sync errors',
    
    -- Alerts and Notifications
    low_stock_alert_sent BOOLEAN DEFAULT FALSE,
    out_of_stock_alert_sent BOOLEAN DEFAULT FALSE,
    last_alert_sent TIMESTAMP NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes and Constraints
    UNIQUE KEY unique_mapping_inventory (unified_mapping_id),
    INDEX idx_business_inventory (business_id),
    INDEX idx_stock_levels (total_available_qty, low_stock_threshold),
    INDEX idx_low_stock_alerts (business_id, total_available_qty, low_stock_threshold),
    INDEX idx_sync_status (sync_status),
    INDEX idx_sales_performance (total_sales_today, total_sales_week),
    INDEX idx_velocity_score (velocity_score),
    
    FOREIGN KEY (unified_mapping_id) REFERENCES unified_item_mapping(id) ON DELETE CASCADE,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Real-time inventory status tracking across Manual and Nayax systems';

-- 3. INVENTORY SYNC LOG TABLE
-- Tracks all sync operations for debugging and auditing
CREATE TABLE IF NOT EXISTS unified_inventory_sync_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    business_id INT NOT NULL,
    unified_mapping_id INT NOT NULL,
    
    -- Sync Operation Details
    sync_type ENUM('manual_sale','nayax_transaction','manual_restock','nayax_restock','daily_sync','manual_sync') NOT NULL,
    operation_type ENUM('stock_update','sales_update','price_update','full_sync') NOT NULL,
    
    -- Change Details
    old_manual_stock INT NULL,
    new_manual_stock INT NULL,
    old_nayax_stock INT NULL,
    new_nayax_stock INT NULL,
    
    quantity_changed INT DEFAULT 0 COMMENT 'Quantity that changed (+/-)',
    trigger_source VARCHAR(100) NULL COMMENT 'What triggered this sync (webhook, manual entry, etc.)',
    
    -- Status and Results
    sync_status ENUM('success','partial','failed') DEFAULT 'success',
    error_message TEXT NULL,
    processing_time_ms INT NULL COMMENT 'How long sync took in milliseconds',
    
    -- Reference Data
    reference_transaction_id INT NULL COMMENT 'Related transaction that triggered sync',
    reference_data JSON NULL COMMENT 'Additional context data',
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NULL COMMENT 'User who initiated sync (if manual)',
    
    -- Indexes
    INDEX idx_business_sync_log (business_id, created_at),
    INDEX idx_mapping_sync_log (unified_mapping_id, created_at),
    INDEX idx_sync_type (sync_type, created_at),
    INDEX idx_sync_status (sync_status),
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (unified_mapping_id) REFERENCES unified_item_mapping(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Audit log for all inventory sync operations';

-- =============================================================================
-- HELPER PROCEDURES AND FUNCTIONS
-- =============================================================================

-- Procedure to update calculated fields in unified_inventory_status
DELIMITER //
CREATE OR REPLACE PROCEDURE UpdateUnifiedInventoryCalculations(IN mapping_id INT)
BEGIN
    UPDATE unified_inventory_status 
    SET 
        total_available_qty = manual_stock_qty + nayax_estimated_qty,
        total_sales_today = manual_sales_today + nayax_sales_today,
        total_sales_week = manual_sales_week + nayax_sales_week,
        total_sales_month = manual_sales_month + nayax_sales_month,
        days_until_empty = CASE 
            WHEN avg_daily_sales > 0 THEN CEIL((manual_stock_qty + nayax_estimated_qty) / avg_daily_sales)
            ELSE NULL 
        END,
        updated_at = CURRENT_TIMESTAMP
    WHERE unified_mapping_id = mapping_id;
    
    -- Update velocity score based on recent sales performance
    UPDATE unified_inventory_status 
    SET velocity_score = LEAST(100, (total_sales_week / 7.0) * 10)
    WHERE unified_mapping_id = mapping_id;
END//
DELIMITER ;

-- Function to get unified inventory status for a business
DELIMITER //
CREATE OR REPLACE FUNCTION GetBusinessInventoryAlert(business_id INT) 
RETURNS JSON
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE result JSON;
    
    SELECT JSON_OBJECT(
        'total_items', COUNT(*),
        'low_stock_count', SUM(CASE WHEN total_available_qty <= low_stock_threshold THEN 1 ELSE 0 END),
        'out_of_stock_count', SUM(CASE WHEN total_available_qty = 0 THEN 1 ELSE 0 END),
        'sync_errors', SUM(CASE WHEN sync_status = 'error' THEN 1 ELSE 0 END),
        'last_updated', MAX(updated_at)
    ) INTO result
    FROM unified_inventory_status 
    WHERE business_id = business_id;
    
    RETURN result;
END//
DELIMITER ;

-- =============================================================================
-- INITIAL DATA MIGRATION SETUP
-- =============================================================================

-- Insert sample mappings for businesses that have both manual and Nayax systems
-- This will be done via PHP migration script, but structure is here for reference

-- =============================================================================
-- VERIFICATION QUERIES
-- =============================================================================

-- Check table creation
SELECT 
    TABLE_NAME, 
    TABLE_ROWS, 
    CREATE_TIME,
    TABLE_COMMENT
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME IN ('unified_item_mapping', 'unified_inventory_status', 'unified_inventory_sync_log');

-- Sample query to show unified inventory for a business
-- SELECT 
--     uim.unified_name,
--     uim.unified_category,
--     uis.manual_stock_qty,
--     uis.nayax_estimated_qty,
--     uis.total_available_qty,
--     uis.total_sales_today,
--     uis.sync_status,
--     CASE 
--         WHEN uim.voting_list_item_id IS NOT NULL AND uim.nayax_product_code IS NOT NULL THEN 'Unified'
--         WHEN uim.nayax_product_code IS NOT NULL THEN 'Nayax Only'
--         WHEN uim.voting_list_item_id IS NOT NULL THEN 'Manual Only'
--         ELSE 'Unmapped'
--     END as system_type
-- FROM unified_item_mapping uim
-- LEFT JOIN unified_inventory_status uis ON uim.id = uis.unified_mapping_id
-- WHERE uim.business_id = ? AND uim.is_active = 1
-- ORDER BY uim.unified_category, uim.unified_name;

-- =============================================================================
-- SUCCESS MESSAGE
-- =============================================================================
SELECT 'Phase 1 Unified Inventory System tables created successfully!' as status; 