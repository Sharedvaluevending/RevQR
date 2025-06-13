-- Phase 1: Safe Database Schema Migration
-- This phase creates backups and compatibility layers WITHOUT breaking existing functionality

USE revenueqr;

-- Step 1: Create backup tables
CREATE TABLE IF NOT EXISTS qr_codes_backup AS SELECT * FROM qr_codes;
CREATE TABLE IF NOT EXISTS voting_lists_backup AS SELECT * FROM voting_lists;
CREATE TABLE IF NOT EXISTS machines_backup AS SELECT * FROM machines WHERE 1=0; -- Structure only

-- Step 2: Add business_id to qr_codes safely (if not exists)
SET @col_exists = (SELECT COUNT(*) FROM information_schema.COLUMNS 
                   WHERE TABLE_SCHEMA = 'revenueqr' 
                   AND TABLE_NAME = 'qr_codes' 
                   AND COLUMN_NAME = 'business_id');

SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE qr_codes ADD COLUMN business_id INT NULL AFTER id', 
    'SELECT "business_id column already exists" as status');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 3: Populate business_id in qr_codes from existing relationships
UPDATE qr_codes qr 
LEFT JOIN campaigns c ON qr.campaign_id = c.id 
LEFT JOIN voting_lists vl ON qr.machine_id = vl.id 
SET qr.business_id = COALESCE(c.business_id, vl.business_id) 
WHERE qr.business_id IS NULL;

-- Step 4: Create compatibility views to maintain backward compatibility
CREATE OR REPLACE VIEW machines_unified AS
SELECT 
    id,
    business_id,
    name,
    description as location,
    'voting_list' as source_table,
    created_at,
    updated_at
FROM voting_lists
UNION ALL
SELECT 
    id + 10000 as id, -- Offset to avoid ID conflicts
    business_id,
    name,
    location,
    'machine' as source_table,
    created_at,
    updated_at
FROM machines 
WHERE EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'machines' AND table_schema = 'revenueqr');

-- Step 5: Create safe QR codes view with proper joins
CREATE OR REPLACE VIEW qr_codes_safe AS
SELECT 
    qr.*,
    COALESCE(qr.business_id, c.business_id, vl.business_id) as safe_business_id,
    COALESCE(qr.machine_name, vl.name, m.name) as safe_machine_name,
    COALESCE(qr.machine_location, vl.description, m.location) as safe_machine_location
FROM qr_codes qr
LEFT JOIN campaigns c ON qr.campaign_id = c.id
LEFT JOIN voting_lists vl ON qr.machine_id = vl.id
LEFT JOIN machines m ON qr.machine_id = m.id;

-- Step 6: Log migration status
CREATE TABLE IF NOT EXISTS migration_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phase VARCHAR(50),
    step VARCHAR(100),
    status ENUM('started', 'completed', 'failed'),
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO migration_log (phase, step, status, message) 
VALUES ('phase1', 'backup_and_compatibility', 'completed', 'Created backups and compatibility views'); 