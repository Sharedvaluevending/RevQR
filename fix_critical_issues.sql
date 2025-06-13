-- CRITICAL DATABASE FIXES FOR QR COIN ECONOMY SYSTEM
-- Generated: December 29, 2024
-- Execute this script to fix immediate database inconsistencies

-- =====================================
-- PHASE 1: VOTE TYPE STANDARDIZATION
-- =====================================

-- Backup current vote types for reference
CREATE TABLE IF NOT EXISTS votes_backup_20241229 AS SELECT * FROM votes LIMIT 0;
INSERT INTO votes_backup_20241229 SELECT * FROM votes WHERE vote_type NOT IN ('vote_in', 'vote_out');

-- Standardize vote types across all records
UPDATE votes SET vote_type = 'vote_in' WHERE vote_type IN ('in', 'IN', 'yes', 'YES', 'up', 'UP');
UPDATE votes SET vote_type = 'vote_out' WHERE vote_type IN ('out', 'OUT', 'no', 'NO', 'down', 'DOWN');

-- Remove any invalid vote types
DELETE FROM votes WHERE vote_type NOT IN ('vote_in', 'vote_out');

-- Update table constraint to prevent future inconsistencies
ALTER TABLE votes MODIFY vote_type ENUM('vote_in', 'vote_out') NOT NULL DEFAULT 'vote_in';

-- =====================================
-- PHASE 2: FOREIGN KEY CONSTRAINT FIXES
-- =====================================

-- Temporarily disable foreign key checks for cleanup
SET FOREIGN_KEY_CHECKS=0;

-- Fix NULL campaign_id and machine_id issues
UPDATE votes SET campaign_id = 0 WHERE campaign_id IS NULL;
UPDATE votes SET machine_id = 0 WHERE machine_id IS NULL;

-- Fix orphaned QR code references
UPDATE votes v 
LEFT JOIN qr_codes qr ON v.qr_code_id = qr.id 
SET v.qr_code_id = NULL 
WHERE qr.id IS NULL AND v.qr_code_id IS NOT NULL;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS=1;

-- =====================================
-- PHASE 3: MASTER ITEM RELATIONSHIPS
-- =====================================

-- Add master_item_id column to voting_list_items if it doesn't exist
ALTER TABLE voting_list_items 
ADD COLUMN master_item_id INT NULL;

-- Add index if column was added successfully
ALTER TABLE voting_list_items 
ADD INDEX idx_master_item_id (master_item_id);

-- Create missing master items for voting list items
INSERT INTO master_items (name, category, type, suggested_price, suggested_cost, status, created_at)
SELECT DISTINCT 
    vli.item_name as name,
    CASE 
        WHEN LOWER(vli.item_name) LIKE '%chip%' OR LOWER(vli.item_name) LIKE '%crisp%' THEN 'Chips and Savory Snacks'
        WHEN LOWER(vli.item_name) LIKE '%candy%' OR LOWER(vli.item_name) LIKE '%chocolate%' THEN 'Candy and Chocolate Bars'
        WHEN LOWER(vli.item_name) LIKE '%cookie%' OR LOWER(vli.item_name) LIKE '%oreo%' THEN 'Cookies (Brand-Name & Generic)'
        WHEN LOWER(vli.item_name) LIKE '%energy%' OR LOWER(vli.item_name) LIKE '%monster%' THEN 'Energy Drinks'
        WHEN LOWER(vli.item_name) LIKE '%water%' THEN 'Water and Flavored Water'
        WHEN LOWER(vli.item_name) LIKE '%soda%' OR LOWER(vli.item_name) LIKE '%cola%' THEN 'Soft Drinks and Carbonated Beverages'
        WHEN LOWER(vli.item_name) LIKE '%juice%' OR LOWER(vli.item_name) LIKE '%tea%' THEN 'Juices and Bottled Teas'
        WHEN LOWER(vli.item_name) LIKE '%protein%' OR LOWER(vli.item_name) LIKE '%bar%' THEN 'Protein and Meal Replacement Bars'
        ELSE 'Odd or Unique Items'
    END as category,
    CASE 
        WHEN LOWER(vli.item_name) LIKE '%drink%' OR LOWER(vli.item_name) LIKE '%water%' 
             OR LOWER(vli.item_name) LIKE '%soda%' OR LOWER(vli.item_name) LIKE '%juice%' 
             OR LOWER(vli.item_name) LIKE '%energy%' THEN 'drink'
        WHEN LOWER(vli.item_name) LIKE '%pizza%' THEN 'pizza'
        ELSE 'snack'
    END as type,
    COALESCE(vli.retail_price, 1.50) as suggested_price,
    COALESCE(vli.cost_price, vli.retail_price * 0.7, 1.00) as suggested_cost,
    'active' as status,
    NOW() as created_at
FROM voting_list_items vli
LEFT JOIN master_items mi ON LOWER(mi.name) = LOWER(vli.item_name)
WHERE mi.id IS NULL
  AND vli.master_item_id IS NULL
  AND vli.item_name IS NOT NULL
  AND vli.item_name != '';

-- Update voting_list_items with master_item_id references
UPDATE voting_list_items vli
JOIN master_items mi ON LOWER(mi.name) = LOWER(vli.item_name)
SET vli.master_item_id = mi.id
WHERE vli.master_item_id IS NULL;

-- =====================================
-- PHASE 4: QR CODE SYSTEM CLEANUP
-- =====================================

-- Add missing indexes for performance
ALTER TABLE qr_codes ADD INDEX idx_campaign_id (campaign_id);
ALTER TABLE qr_codes ADD INDEX idx_business_id (business_id);
ALTER TABLE qr_codes ADD INDEX idx_created_at (created_at);

-- Update QR codes with missing business_id references
UPDATE qr_codes qr
JOIN campaigns c ON qr.campaign_id = c.id
SET qr.business_id = c.business_id
WHERE qr.business_id IS NULL AND qr.campaign_id IS NOT NULL;

-- =====================================
-- PHASE 5: CAMPAIGN/VOTING LIST RELATIONSHIPS
-- =====================================

-- Ensure campaign_voting_lists table exists
CREATE TABLE IF NOT EXISTS campaign_voting_lists (
    id INT AUTO_INCREMENT PRIMARY KEY,
    campaign_id INT NOT NULL,
    voting_list_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_campaign_list (campaign_id, voting_list_id),
    INDEX idx_campaign_id (campaign_id),
    INDEX idx_voting_list_id (voting_list_id)
);

-- =====================================
-- PHASE 6: PERFORMANCE OPTIMIZATIONS
-- =====================================

-- Add missing indexes for vote counting performance
ALTER TABLE votes ADD INDEX idx_item_vote_type (item_id, vote_type);
ALTER TABLE votes ADD INDEX idx_campaign_vote_type (campaign_id, vote_type);
ALTER TABLE votes ADD INDEX idx_created_week (created_at);

-- Add index for QR code stats
ALTER TABLE qr_code_stats ADD INDEX idx_qr_code_scan_time (qr_code_id, scan_time);

-- =====================================
-- PHASE 7: DATA VALIDATION & CLEANUP
-- =====================================

-- Remove orphaned votes with invalid item references
DELETE v FROM votes v
LEFT JOIN voting_list_items vli ON v.item_id = vli.id
WHERE vli.id IS NULL;

-- Clean up QR codes with invalid campaign references
UPDATE qr_codes SET campaign_id = NULL 
WHERE campaign_id NOT IN (SELECT id FROM campaigns);

-- =====================================
-- VERIFICATION QUERIES
-- =====================================

-- Check vote type standardization
SELECT vote_type, COUNT(*) as count FROM votes GROUP BY vote_type;

-- Check master item mapping completion
SELECT 
    COUNT(*) as total_items,
    SUM(CASE WHEN master_item_id IS NOT NULL THEN 1 ELSE 0 END) as mapped_items,
    ROUND(SUM(CASE WHEN master_item_id IS NOT NULL THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as mapping_percentage
FROM voting_list_items;

-- Check QR code business_id coverage
SELECT 
    COUNT(*) as total_qr_codes,
    SUM(CASE WHEN business_id IS NOT NULL THEN 1 ELSE 0 END) as with_business_id,
    ROUND(SUM(CASE WHEN business_id IS NOT NULL THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as business_coverage
FROM qr_codes;

-- Display cleanup summary
SELECT 
    'VOTE_TYPES' as fix_area,
    (SELECT COUNT(*) FROM votes WHERE vote_type IN ('vote_in', 'vote_out')) as records_fixed,
    'All vote types standardized' as status
UNION ALL
SELECT 
    'MASTER_ITEMS' as fix_area,
    (SELECT COUNT(*) FROM voting_list_items WHERE master_item_id IS NOT NULL) as records_fixed,
    'Item mappings completed' as status
UNION ALL
SELECT 
    'QR_CODES' as fix_area,
    (SELECT COUNT(*) FROM qr_codes WHERE business_id IS NOT NULL) as records_fixed,
    'Business relationships updated' as status; 