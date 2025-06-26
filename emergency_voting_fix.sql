-- EMERGENCY VOTING SYSTEM RESTORATION
-- These are the EXACT fixes that made everything work on June 15th

-- 1. Fix vote type standardization (CRITICAL)
ALTER TABLE votes MODIFY vote_type ENUM('vote_in', 'vote_out') NOT NULL DEFAULT 'vote_in';

-- 2. Add performance indexes (CRITICAL)
ALTER TABLE votes ADD INDEX idx_item_vote_type (item_id, vote_type);
ALTER TABLE votes ADD INDEX idx_campaign_vote_type (campaign_id, vote_type);
ALTER TABLE votes ADD INDEX idx_machine_vote_type (machine_id, vote_type);
ALTER TABLE votes ADD INDEX idx_voter_ip_date (voter_ip, created_at);

-- 3. Fix constraint violations (CRITICAL)
UPDATE votes SET machine_id = 0 WHERE machine_id IS NULL;
UPDATE votes SET campaign_id = 0 WHERE campaign_id IS NULL;

-- 4. Clean up inconsistent vote data
UPDATE votes SET vote_type = 'vote_in' WHERE vote_type IN ('in', 'yes', 'up', 'like');
UPDATE votes SET vote_type = 'vote_out' WHERE vote_type IN ('out', 'no', 'down', 'dislike');

-- Verify the fixes
SELECT 'Vote types fixed' as status, COUNT(*) as total_votes, vote_type 
FROM votes 
GROUP BY vote_type;

SELECT 'Constraint violations fixed' as status, 
       COUNT(*) as votes_with_valid_constraints
FROM votes 
WHERE machine_id IS NOT NULL AND campaign_id IS NOT NULL; 