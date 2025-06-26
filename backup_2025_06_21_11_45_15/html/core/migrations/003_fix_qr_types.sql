-- Migration 003: Fix QR Types and Foreign Key Consistency
-- Addresses frontend/backend QR type mismatches and foreign key issues

-- Step 1: QR types enum is already correct, skip this step

-- Step 2: Fix foreign key reference (should point to machines, not voting_lists)
-- First, check if the foreign key exists and drop it
ALTER TABLE qr_codes DROP FOREIGN KEY IF EXISTS qr_codes_ibfk_1;
ALTER TABLE qr_codes DROP FOREIGN KEY IF EXISTS qr_codes_machine_fk;

-- Add the correct foreign key relationship to machines table
ALTER TABLE qr_codes 
ADD CONSTRAINT qr_codes_machine_fk 
FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE SET NULL;

-- Step 3: Add business_id tracking to qr_codes for multi-tenant isolation (if not exists)
SET @count = (SELECT COUNT(*) FROM information_schema.COLUMNS 
              WHERE TABLE_SCHEMA = 'revenueqr' 
              AND TABLE_NAME = 'qr_codes' 
              AND COLUMN_NAME = 'business_id');

SET @sql = IF(@count = 0, 
    'ALTER TABLE qr_codes ADD COLUMN business_id INT NULL AFTER id, ADD INDEX idx_qr_codes_business (business_id)', 
    'SELECT "business_id column already exists" as status');

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key for business_id (if column was added)
SET @sql2 = IF(@count = 0, 
    'ALTER TABLE qr_codes ADD CONSTRAINT qr_codes_business_fk FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE', 
    'SELECT "business_id foreign key not needed" as status');

PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- Step 4: Add validation constraints (conditional)
SET @constraint_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
                         WHERE TABLE_SCHEMA = 'revenueqr' 
                         AND TABLE_NAME = 'qr_codes' 
                         AND CONSTRAINT_NAME = 'chk_machine_or_name');

SET @sql3 = IF(@constraint_exists = 0, 
    'ALTER TABLE qr_codes ADD CONSTRAINT chk_machine_or_name CHECK ((machine_id IS NOT NULL) OR (machine_name IS NOT NULL AND machine_name != \'\') OR (qr_type IN (\'static\', \'dynamic\')))', 
    'SELECT "constraint already exists" as status');

PREPARE stmt3 FROM @sql3;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;

-- Step 5: Update existing records to have proper business_id (only if business_id column was just added)
UPDATE qr_codes qr 
LEFT JOIN machines m ON qr.machine_id = m.id 
SET qr.business_id = m.business_id 
WHERE qr.business_id IS NULL AND m.business_id IS NOT NULL;

-- Step 6: Create indexes for performance (if they don't exist)
CREATE INDEX IF NOT EXISTS idx_qr_codes_machine_name ON qr_codes(machine_name);
CREATE INDEX IF NOT EXISTS idx_qr_codes_type ON qr_codes(qr_type);
CREATE INDEX IF NOT EXISTS idx_qr_codes_status ON qr_codes(status); 