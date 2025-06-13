-- Fix Phase 3 Schema Issues
-- Add missing event_type column and create additional tables

-- Add event_type column to existing sync log table
ALTER TABLE unified_inventory_sync_log 
ADD COLUMN event_type varchar(100) DEFAULT 'sync' COMMENT 'Type of sync event' AFTER sync_type;

-- Update existing records to have event_type based on sync_type
UPDATE unified_inventory_sync_log 
SET event_type = CASE 
    WHEN sync_type = 'manual_sale' THEN 'manual_sale_trigger'
    WHEN sync_type = 'nayax_transaction' THEN 'nayax_webhook_received'
    WHEN sync_type = 'manual_restock' THEN 'inventory_reconciliation'
    WHEN sync_type = 'nayax_restock' THEN 'nayax_stock_update'
    WHEN sync_type = 'daily_sync' THEN 'daily_batch_sync'
    WHEN sync_type = 'manual_sync' THEN 'manual_sync_trigger'
    ELSE 'sync'
END;

-- Alter to make event_type NOT NULL
ALTER TABLE unified_inventory_sync_log 
MODIFY COLUMN event_type varchar(100) NOT NULL COMMENT 'Type of sync event';

-- Add indexes for better performance
ALTER TABLE unified_inventory_sync_log 
ADD INDEX IF NOT EXISTS idx_event_type (event_type),
ADD INDEX IF NOT EXISTS idx_business_created (business_id, created_at);

-- Nayax webhook log table for debugging and replay
CREATE TABLE IF NOT EXISTS nayax_webhook_log (
    id int(11) AUTO_INCREMENT PRIMARY KEY,
    payload longtext NOT NULL COMMENT 'Raw webhook payload',
    processed tinyint(1) DEFAULT 0 COMMENT 'Whether webhook was successfully processed',
    processing_error text DEFAULT NULL COMMENT 'Error message if processing failed',
    received_at timestamp DEFAULT CURRENT_TIMESTAMP COMMENT 'When webhook was received',
    processed_at timestamp NULL DEFAULT NULL COMMENT 'When webhook was processed',
    
    INDEX idx_received_at (received_at),
    INDEX idx_processed (processed),
    INDEX idx_processed_at (processed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Nayax webhook processing log';

-- Performance monitoring table for daily sync metrics
CREATE TABLE IF NOT EXISTS unified_sync_performance_log (
    id int(11) AUTO_INCREMENT PRIMARY KEY,
    date date NOT NULL COMMENT 'Date of metrics',
    total_mappings int(11) DEFAULT 0 COMMENT 'Total active mappings',
    sync_health_percent decimal(5,2) DEFAULT 0.00 COMMENT 'Percentage of synced mappings',
    total_sync_events int(11) DEFAULT 0 COMMENT 'Total sync events for the day',
    average_sync_time_ms int(11) DEFAULT NULL COMMENT 'Average sync processing time',
    error_count int(11) DEFAULT 0 COMMENT 'Number of sync errors',
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_date (date),
    INDEX idx_date (date),
    INDEX idx_sync_health (sync_health_percent)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Daily sync performance metrics';

-- Views for easy monitoring and reporting
CREATE OR REPLACE VIEW sync_health_dashboard AS
SELECT 
    b.id as business_id,
    b.business_name,
    COUNT(uim.id) as total_mappings,
    COUNT(CASE WHEN uis.sync_status = 'synced' THEN 1 END) as synced_mappings,
    COUNT(CASE WHEN uis.sync_status = 'partial' THEN 1 END) as partial_mappings,
    COUNT(CASE WHEN uis.sync_status = 'unsynced' THEN 1 END) as unsynced_mappings,
    ROUND((COUNT(CASE WHEN uis.sync_status = 'synced' THEN 1 END) / GREATEST(COUNT(uim.id), 1)) * 100, 2) as sync_health_percent,
    SUM(uis.total_available_qty) as total_inventory,
    MAX(uis.last_synced_at) as last_sync_time
FROM businesses b
LEFT JOIN unified_item_mapping uim ON b.id = uim.business_id AND uim.deleted_at IS NULL
LEFT JOIN unified_inventory_status uis ON uim.id = uis.mapping_id
GROUP BY b.id, b.business_name;

-- Recent sync activity view
CREATE OR REPLACE VIEW recent_sync_activity AS
SELECT 
    uisl.business_id,
    b.business_name,
    uisl.event_type,
    COUNT(*) as event_count,
    MAX(uisl.created_at) as last_occurrence,
    MIN(uisl.created_at) as first_occurrence
FROM unified_inventory_sync_log uisl
JOIN businesses b ON uisl.business_id = b.id
WHERE uisl.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY uisl.business_id, b.business_name, uisl.event_type
ORDER BY uisl.business_id, event_count DESC;

-- Insert default performance metrics for existing businesses
INSERT IGNORE INTO unified_sync_performance_log (date, total_mappings, sync_health_percent, total_sync_events)
SELECT 
    CURDATE(),
    COUNT(uim.id),
    ROUND(
        (COUNT(CASE WHEN uis.sync_status = 'synced' THEN 1 END) / GREATEST(COUNT(uim.id), 1)) * 100, 
        2
    ),
    0
FROM unified_item_mapping uim
LEFT JOIN unified_inventory_status uis ON uim.id = uis.mapping_id
WHERE uim.deleted_at IS NULL;

-- SUCCESS MESSAGE
SELECT 'Phase 3 schema fix applied successfully!' as status,
       'Real-time sync engine is now ready' as message; 