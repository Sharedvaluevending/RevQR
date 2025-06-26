-- Migration 003: Fix QR Types and Foreign Key Consistency (Final Version)
-- Addresses frontend/backend QR type mismatches and foreign key issues

-- Step 1: QR types enum is already correct (confirmed)

-- Step 2: Add foreign key for machine_id (there isn't one currently)
ALTER TABLE qr_codes 
ADD CONSTRAINT qr_codes_machine_fk 
FOREIGN KEY (machine_id) REFERENCES machines(id) ON DELETE SET NULL;

-- Step 3: Add business_id column 
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