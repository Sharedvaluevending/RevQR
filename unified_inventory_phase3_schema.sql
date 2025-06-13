-- Phase 3: Real-Time Sync Engine Database Schema
-- Additional tables for webhook logging, performance tracking, and enhanced sync logging

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

-- Enhanced sync events table for detailed logging
CREATE TABLE IF NOT EXISTS unified_sync_events_detailed (
    id int(11) AUTO_INCREMENT PRIMARY KEY,
    business_id int(11) NOT NULL,
    mapping_id int(11) DEFAULT NULL COMMENT 'Related mapping ID if applicable',
    event_category enum('sync', 'error', 'alert', 'performance', 'mapping') DEFAULT 'sync',
    event_type varchar(100) NOT NULL COMMENT 'Specific event type',
    event_data json DEFAULT NULL COMMENT 'Detailed event data',
    processing_time_ms int(11) DEFAULT NULL COMMENT 'Time taken to process event',
    severity enum('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    resolved tinyint(1) DEFAULT 0 COMMENT 'Whether issue was resolved',
    resolved_at timestamp NULL DEFAULT NULL,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_business_id (business_id),
    INDEX idx_mapping_id (mapping_id),
    INDEX idx_event_category (event_category),
    INDEX idx_event_type (event_type),
    INDEX idx_severity (severity),
    INDEX idx_created_at (created_at),
    INDEX idx_resolved (resolved),
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (mapping_id) REFERENCES unified_item_mapping(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detailed sync events and alerts';

-- Sync queue table for batch processing
CREATE TABLE IF NOT EXISTS unified_sync_queue (
    id int(11) AUTO_INCREMENT PRIMARY KEY,
    business_id int(11) NOT NULL,
    sync_type enum('manual_sale', 'nayax_transaction', 'inventory_update', 'reconciliation') NOT NULL,
    priority enum('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    payload json NOT NULL COMMENT 'Data to be processed',
    status enum('pending', 'processing', 'completed', 'failed', 'retrying') DEFAULT 'pending',
    attempts int(11) DEFAULT 0 COMMENT 'Number of processing attempts',
    max_attempts int(11) DEFAULT 3 COMMENT 'Maximum retry attempts',
    scheduled_at timestamp DEFAULT CURRENT_TIMESTAMP COMMENT 'When to process this item',
    started_at timestamp NULL DEFAULT NULL,
    completed_at timestamp NULL DEFAULT NULL,
    error_message text DEFAULT NULL,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_business_id (business_id),
    INDEX idx_sync_type (sync_type),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_scheduled_at (scheduled_at),
    INDEX idx_attempts (attempts),
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Queue for sync operations';

-- Sync conflicts table for tracking and resolving data conflicts
CREATE TABLE IF NOT EXISTS unified_sync_conflicts (
    id int(11) AUTO_INCREMENT PRIMARY KEY,
    business_id int(11) NOT NULL,
    mapping_id int(11) NOT NULL,
    conflict_type enum('quantity_mismatch', 'price_discrepancy', 'missing_data', 'duplicate_transaction') NOT NULL,
    manual_value varchar(255) DEFAULT NULL COMMENT 'Value from manual system',
    nayax_value varchar(255) DEFAULT NULL COMMENT 'Value from Nayax system',
    suggested_resolution text DEFAULT NULL,
    resolution_action enum('use_manual', 'use_nayax', 'average', 'custom', 'ignore') DEFAULT NULL,
    resolved tinyint(1) DEFAULT 0,
    resolved_by int(11) DEFAULT NULL COMMENT 'User ID who resolved',
    resolved_at timestamp NULL DEFAULT NULL,
    notes text DEFAULT NULL,
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_business_id (business_id),
    INDEX idx_mapping_id (mapping_id),
    INDEX idx_conflict_type (conflict_type),
    INDEX idx_resolved (resolved),
    INDEX idx_created_at (created_at),
    
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (mapping_id) REFERENCES unified_item_mapping(id) ON DELETE CASCADE,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sync conflicts requiring resolution';

-- Update existing sync log table to ensure it has the event_type column
-- (This fixes the error we encountered in testing)
ALTER TABLE unified_inventory_sync_log 
MODIFY COLUMN event_type varchar(100) NOT NULL COMMENT 'Type of sync event';

-- Add indexes to existing tables for better performance
ALTER TABLE unified_inventory_sync_log 
ADD INDEX IF NOT EXISTS idx_event_type (event_type),
ADD INDEX IF NOT EXISTS idx_business_created (business_id, created_at);

ALTER TABLE unified_item_mapping 
ADD INDEX IF NOT EXISTS idx_business_created (business_id, created_at),
ADD INDEX IF NOT EXISTS idx_manual_item (manual_item_id),
ADD INDEX IF NOT EXISTS idx_nayax_machine (nayax_machine_id);

ALTER TABLE unified_inventory_status 
ADD INDEX IF NOT EXISTS idx_sync_status (sync_status),
ADD INDEX IF NOT EXISTS idx_stock_levels (total_available_qty),
ADD INDEX IF NOT EXISTS idx_last_synced (last_synced_at);

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

-- Create default sync settings for businesses
CREATE TABLE IF NOT EXISTS business_sync_settings (
    id int(11) AUTO_INCREMENT PRIMARY KEY,
    business_id int(11) NOT NULL,
    auto_sync_enabled tinyint(1) DEFAULT 1 COMMENT 'Enable automatic sync',
    webhook_notifications tinyint(1) DEFAULT 1 COMMENT 'Send webhook notifications',
    email_alerts tinyint(1) DEFAULT 1 COMMENT 'Send email alerts for conflicts',
    low_stock_alerts tinyint(1) DEFAULT 1 COMMENT 'Send low stock alerts',
    sync_frequency_minutes int(11) DEFAULT 15 COMMENT 'How often to sync in minutes',
    conflict_resolution_strategy enum('manual', 'auto_manual', 'auto_nayax', 'auto_average') DEFAULT 'manual',
    webhook_url varchar(500) DEFAULT NULL COMMENT 'Custom webhook URL for notifications',
    created_at timestamp DEFAULT CURRENT_TIMESTAMP,
    updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_business (business_id),
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sync configuration per business';

-- Insert default settings for existing businesses
INSERT IGNORE INTO business_sync_settings (business_id)
SELECT id FROM businesses;

-- SUCCESS MESSAGE
SELECT 'Phase 3 database schema created successfully!' as status,
       'Real-time sync engine tables and views are ready' as message; 