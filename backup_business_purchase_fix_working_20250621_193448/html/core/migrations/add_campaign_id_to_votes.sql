-- Add campaign_id column to votes table if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'votes' 
     AND COLUMN_NAME = 'campaign_id') = 0,
    'ALTER TABLE votes ADD COLUMN campaign_id INT NULL AFTER list_id',
    'SELECT "campaign_id column already exists" as message'
));

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add index for campaign_id if it doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'votes' 
     AND INDEX_NAME = 'idx_votes_campaign') = 0,
    'CREATE INDEX idx_votes_campaign ON votes(campaign_id)',
    'SELECT "idx_votes_campaign index already exists" as message'
));

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt; 