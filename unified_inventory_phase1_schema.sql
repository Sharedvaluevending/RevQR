-- UNIFIED INVENTORY SYSTEM - PHASE 1: FOUNDATION
-- Creates the core tables to map items between Manual and Nayax systems

-- 1. UNIFIED ITEM MAPPING TABLE
-- Maps individual items across Manual and Nayax systems
CREATE TABLE IF NOT EXISTS unified_item_mapping (
    id INT PRIMARY KEY AUTO_INCREMENT,
    business_id INT NOT NULL,
    
    -- Manual System References
    master_item_id INT NULL,
    voting_list_item_id INT NULL,
    
    -- Nayax System References  
    nayax_machine_id VARCHAR(50) NULL,
    nayax_product_code VARCHAR(50) NULL,
    nayax_slot_position VARCHAR(10) NULL,
    
    -- Unified Item Properties
    unified_name VARCHAR(255) NOT NULL,
    unified_category VARCHAR(100) NULL,
    unified_price DECIMAL(10,2) NULL,
    unified_cost DECIMAL(10,2) NULL,
    unified_sku VARCHAR(100) NULL,
    
    -- Mapping Configuration
    is_active BOOLEAN DEFAULT TRUE,
    sync_inventory BOOLEAN DEFAULT TRUE,
    sync_pricing BOOLEAN DEFAULT FALSE,
    mapping_confidence ENUM('high','medium','low') DEFAULT 'high',
    auto_created BOOLEAN DEFAULT FALSE,
    requires_verification BOOLEAN DEFAULT FALSE,
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    mapped_by INT NULL,
    last_verified_at TIMESTAMP NULL,
    
    -- Indexes and Constraints
    UNIQUE KEY unique_manual_item (business_id, voting_list_item_id),
    UNIQUE KEY unique_nayax_item (business_id, nayax_machine_id, nayax_slot_position),
    INDEX idx_business_mapping (business_id),
    INDEX idx_unified_name (unified_name),
    INDEX idx_active_mappings (business_id, is_active),
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
);

-- 2. UNIFIED INVENTORY STATUS TABLE
-- Tracks real-time inventory status across both systems
CREATE TABLE IF NOT EXISTS unified_inventory_status (
    id INT PRIMARY KEY AUTO_INCREMENT,
    unified_mapping_id INT NOT NULL,
    business_id INT NOT NULL,
    
    -- Current Stock Levels
    manual_stock_qty INT DEFAULT 0,
    nayax_estimated_qty INT DEFAULT 0,
    nayax_last_restock_qty INT DEFAULT 0,
    nayax_last_restock_date TIMESTAMP NULL,
    
    -- Combined Metrics
    total_available_qty INT DEFAULT 0,
    low_stock_threshold INT DEFAULT 5,
    reorder_point INT DEFAULT 10,
    max_capacity INT DEFAULT 100,
    
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
    avg_daily_sales DECIMAL(8,2) DEFAULT 0.00,
    days_until_empty INT NULL,
    velocity_score DECIMAL(5,2) DEFAULT 0.00,
    
    -- System Sync Status
    last_manual_update TIMESTAMP NULL,
    last_nayax_update TIMESTAMP NULL,
    sync_status ENUM('synced','partial','error','pending') DEFAULT 'synced',
    sync_error_message TEXT NULL,
    
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
    INDEX idx_sync_status (sync_status),
    
    FOREIGN KEY (unified_mapping_id) REFERENCES unified_item_mapping(id) ON DELETE CASCADE,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
);

-- 3. INVENTORY SYNC LOG TABLE
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
    quantity_changed INT DEFAULT 0,
    trigger_source VARCHAR(100) NULL,
    
    -- Status and Results
    sync_status ENUM('success','partial','failed') DEFAULT 'success',
    error_message TEXT NULL,
    processing_time_ms INT NULL,
    
    -- Reference Data
    reference_transaction_id INT NULL,
    reference_data JSON NULL,
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NULL,
    
    -- Indexes
    INDEX idx_business_sync_log (business_id, created_at),
    INDEX idx_mapping_sync_log (unified_mapping_id, created_at),
    INDEX idx_sync_type (sync_type, created_at),
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (unified_mapping_id) REFERENCES unified_item_mapping(id) ON DELETE CASCADE
);

-- Verification
SELECT 'Phase 1 Unified Inventory System tables created successfully!' as status; 