-- Migration 003: Fix QR Types - Working Version
-- Addresses business_id tracking (machines is a VIEW, can't add FK)

-- Step 1: QR types enum is already correct (confirmed)

-- Step 2: Skip machine foreign key (machines is a VIEW, not a table)
-- Cannot add foreign key to voting_lists because that breaks the abstraction

-- Step 3: Add business_id column 
ALTER TABLE qr_codes ADD COLUMN business_id INT NULL AFTER id;
ALTER TABLE qr_codes ADD INDEX idx_qr_codes_business (business_id);

-- Step 4: Add foreign key for business_id
ALTER TABLE qr_codes 
ADD CONSTRAINT qr_codes_business_fk 
FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE;

-- Step 5: Update existing records to have proper business_id
-- Since machines is a view of voting_lists, we use voting_lists directly
UPDATE qr_codes qr 
LEFT JOIN voting_lists vl ON qr.machine_id = vl.id 
SET qr.business_id = vl.business_id 
WHERE qr.business_id IS NULL AND vl.business_id IS NOT NULL;

-- Step 6: Create indexes for performance
CREATE INDEX idx_qr_codes_machine_name ON qr_codes(machine_name);
CREATE INDEX idx_qr_codes_type ON qr_codes(qr_type);
CREATE INDEX idx_qr_codes_status ON qr_codes(status); 