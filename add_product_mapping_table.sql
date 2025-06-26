-- =============================================================================
-- PRODUCT MAPPING TABLE - NAYAX INTEGRATION
-- Links QR Store items to specific Nayax machine products
-- Based on: https://developerhub.nayax.com/reference/get-machine-products
-- =============================================================================

-- Create product_mapping table
CREATE TABLE IF NOT EXISTS product_mapping (
    id INT PRIMARY KEY AUTO_INCREMENT,
    business_id INT NOT NULL,
    
    -- QR Store Item Reference
    qr_store_item_id INT NOT NULL COMMENT 'Links to qr_store_items.id',
    
    -- Nayax Machine Product Reference
    nayax_machine_id VARCHAR(50) NOT NULL COMMENT 'Nayax machine identifier',
    nayax_product_selection VARCHAR(10) NOT NULL COMMENT 'Product selection code (A1, B2, etc.)',
    nayax_product_name VARCHAR(255) NULL COMMENT 'Product name from Nayax API',
    nayax_product_price DECIMAL(10,2) NULL COMMENT 'Original product price from Nayax',
    
    -- Mapping Configuration
    mapping_type ENUM('direct', 'substitute', 'bundle') DEFAULT 'direct' COMMENT 'Type of mapping relationship',
    confidence_score INT DEFAULT 95 COMMENT 'Confidence level 0-100',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Whether this mapping is active',
    
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT NULL COMMENT 'User who created the mapping',
    
    -- Foreign Keys
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (qr_store_item_id) REFERENCES qr_store_items(id) ON DELETE CASCADE,
    
    -- Indexes
    INDEX idx_business_mapping (business_id),
    INDEX idx_qr_store_item (qr_store_item_id),
    INDEX idx_nayax_machine (nayax_machine_id),
    INDEX idx_product_selection (nayax_machine_id, nayax_product_selection),
    INDEX idx_active_mappings (business_id, is_active),
    INDEX idx_mapping_type (mapping_type),
    
    -- Ensure unique mapping per QR store item
    UNIQUE KEY unique_qr_item_mapping (qr_store_item_id),
    
    -- Ensure unique machine product mapping per business
    UNIQUE KEY unique_machine_product (business_id, nayax_machine_id, nayax_product_selection)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Maps QR Store items to Nayax machine products';

-- =============================================================================
-- SAMPLE DATA - Remove in production
-- =============================================================================

-- Note: Sample data will be added through the Product Mapper interface
-- This ensures proper validation and API integration

-- =============================================================================
-- INDEXES FOR PERFORMANCE
-- =============================================================================

-- Composite index for quick lookups during purchase processing
CREATE INDEX idx_product_lookup ON product_mapping (business_id, nayax_machine_id, is_active);

-- Index for reporting and analytics
CREATE INDEX idx_mapping_analytics ON product_mapping (business_id, mapping_type, created_at);

-- =============================================================================
-- CONFIGURATION NOTES
-- =============================================================================

/*
MAPPING TYPES:
- 'direct': 1:1 mapping between QR item and Nayax product
- 'substitute': QR item can substitute for the Nayax product
- 'bundle': QR item is part of a bundle that includes the Nayax product

CONFIDENCE SCORES:
- 95-100: High confidence, auto-created or manually verified
- 80-94: Medium confidence, requires review
- 60-79: Low confidence, needs manual verification
- 0-59: Very low confidence, likely needs correction

USAGE:
1. Business creates QR store items
2. Nayax machines are synced and products cached
3. Product Mapper interface creates mappings between QR items and machine products
4. When customers purchase QR items, the mapping determines which machine product they receive
5. Discount store can show machine-specific items based on these mappings
*/ 