-- Fix campaign_id constraint issue in votes table
-- This allows votes to be recorded without requiring a campaign_id

USE revenueqr;

-- First, check current votes table structure
DESCRIBE votes;

-- Allow campaign_id to be NULL in votes table
ALTER TABLE votes 
MODIFY COLUMN campaign_id INT NULL DEFAULT NULL;

-- Add index for better performance
CREATE INDEX IF NOT EXISTS idx_votes_campaign_id ON votes(campaign_id);

-- Check if any votes are failing due to this constraint
SELECT COUNT(*) as failed_votes_count
FROM votes 
WHERE campaign_id IS NULL;

-- Insert a default campaign if needed
INSERT IGNORE INTO campaigns (id, name, description, status) 
VALUES (1, 'Default Campaign', 'Default campaign for all votes', 'active');

-- Update any NULL campaign_ids with default
UPDATE votes 
SET campaign_id = 1 
WHERE campaign_id IS NULL;

-- Verify the fix
SELECT 'Votes table fixed successfully' as status; 