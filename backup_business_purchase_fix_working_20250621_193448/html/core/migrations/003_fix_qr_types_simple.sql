-- Migration 003: Fix QR Types and Foreign Key Consistency (Simple Version)
-- Addresses frontend/backend QR type mismatches and foreign key issues

-- Step 1: Drop existing foreign key constraints (using known names)
DROP INDEX qr_codes_ibfk_2 ON qr_codes;
ALTER TABLE qr_codes DROP FOREIGN KEY qr_codes_ibfk_2;

-- Step 2: Add the correct foreign key relationship to machines table
ALTER TABLE qr_codes 
ADD CONSTRAINT qr_codes_machine_fk 
FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE SET NULL;

-- Step 3: Add business_id column (this will fail if column exists, which is fine)
ALTER TABLE qr_codes ADD COLUMN business_id INT NULL AFTER id;
ALTER TABLE qr_codes ADD INDEX idx_qr_codes_business (business_id);

-- Step 4: Add foreign key for business_id
ALTER TABLE qr_codes 
ADD CONSTRAINT qr_codes_business_fk 
FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE;

-- Step 5: Update existing records to have proper business_id
UPDATE qr_codes qr 
LEFT JOIN machines m ON qr.machine_id = m.id 
SET qr.business_id = m.business_id 
WHERE qr.business_id IS NULL AND m.business_id IS NOT NULL;

-- Step 6: Create indexes for performance
CREATE INDEX idx_qr_codes_machine_name ON qr_codes(machine_name);
CREATE INDEX idx_qr_codes_type ON qr_codes(qr_type);
CREATE INDEX idx_qr_codes_status ON qr_codes(status); 