-- CRITICAL VOTING SYSTEM FIXES
-- Fix all voting inconsistencies and standardize vote types

START TRANSACTION;

-- 1. BACKUP INCONSISTENT VOTES BEFORE FIXING
CREATE TABLE IF NOT EXISTS votes_backup_before_fix AS 
SELECT * FROM votes WHERE vote_type NOT IN ('vote_in', 'vote_out') LIMIT 0;

INSERT INTO votes_backup_before_fix 
SELECT * FROM votes WHERE vote_type NOT IN ('vote_in', 'vote_out');

-- 2. STANDARDIZE VOTE TYPES - Fix all inconsistent enum values
UPDATE votes SET vote_type = 'vote_in' 
WHERE vote_type IN ('in', 'IN', 'yes', 'YES', 'up', 'UP', 'like', 'LIKE', '1', 'true', 'TRUE');

UPDATE votes SET vote_type = 'vote_out' 
WHERE vote_type IN ('out', 'OUT', 'no', 'NO', 'down', 'DOWN', 'dislike', 'DISLIKE', '0', 'false', 'FALSE');

-- 3. DELETE ANY REMAINING INVALID VOTE TYPES
DELETE FROM votes WHERE vote_type NOT IN ('vote_in', 'vote_out');

-- 4. FIX NULL FOREIGN KEY CONSTRAINTS
-- Set default values for NOT NULL constraints
UPDATE votes SET machine_id = 0 WHERE machine_id IS NULL;
UPDATE votes SET campaign_id = 0 WHERE campaign_id IS NULL;

-- 5. ENSURE VOTE_TYPE ENUM IS PROPERLY DEFINED
ALTER TABLE votes MODIFY vote_type ENUM('vote_in', 'vote_out') NOT NULL DEFAULT 'vote_in';

-- 6. ADD MISSING INDEXES FOR PERFORMANCE
-- Check and add index for item_id + vote_type (crucial for vote counting)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'votes' 
     AND index_name = 'idx_item_vote_type') > 0,
    'SELECT "Index idx_item_vote_type already exists" as message',
    'ALTER TABLE votes ADD INDEX idx_item_vote_type (item_id, vote_type)'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add index for campaign_id + vote_type
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'votes' 
     AND index_name = 'idx_campaign_vote_type') > 0,
    'SELECT "Index idx_campaign_vote_type already exists" as message',
    'ALTER TABLE votes ADD INDEX idx_campaign_vote_type (campaign_id, vote_type)'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add index for machine_id + vote_type
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'votes' 
     AND index_name = 'idx_machine_vote_type') > 0,
    'SELECT "Index idx_machine_vote_type already exists" as message',
    'ALTER TABLE votes ADD INDEX idx_machine_vote_type (machine_id, vote_type)'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Check and add index for voter_ip + created_at (for rate limiting)
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM information_schema.STATISTICS 
     WHERE table_schema = DATABASE() 
     AND table_name = 'votes' 
     AND index_name = 'idx_voter_ip_date') > 0,
    'SELECT "Index idx_voter_ip_date already exists" as message',
    'ALTER TABLE votes ADD INDEX idx_voter_ip_date (voter_ip, created_at)'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 7. VERIFY FIXES - GENERATE REPORT
SELECT 'VOTING_SYSTEM_FIX_RESULTS' as report_type;
SELECT 'VOTE_TYPES_AFTER_FIX' as check_name, vote_type, COUNT(*) as count 
FROM votes GROUP BY vote_type;

SELECT 'NULL_CONSTRAINT_CHECK' as check_name,
       SUM(CASE WHEN machine_id IS NULL THEN 1 ELSE 0 END) as null_machine_ids,
       SUM(CASE WHEN campaign_id IS NULL THEN 1 ELSE 0 END) as null_campaign_ids,
       SUM(CASE WHEN item_id IS NULL THEN 1 ELSE 0 END) as null_item_ids,
       SUM(CASE WHEN vote_type IS NULL THEN 1 ELSE 0 END) as null_vote_types
FROM votes;

SELECT 'BACKUP_INFO' as check_name,
       COUNT(*) as inconsistent_votes_backed_up
FROM votes_backup_before_fix;

-- 8. FINAL SUMMARY
SELECT 
    'VOTING_SYSTEM_STATUS' as status_check,
    'FIXED' as status,
    COUNT(*) as total_votes,
    COUNT(CASE WHEN vote_type = 'vote_in' THEN 1 END) as vote_in_count,
    COUNT(CASE WHEN vote_type = 'vote_out' THEN 1 END) as vote_out_count
FROM votes;

COMMIT;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS=1;

-- Log completion
SELECT 'VOTING_INCONSISTENCIES_FIXED' as result, NOW() as completed_at; 