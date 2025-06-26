-- Add user_id column to votes table for better user tracking
ALTER TABLE `votes` 
ADD COLUMN `user_id` INT NULL AFTER `id`,
ADD INDEX `idx_votes_user_id` (`user_id`),
ADD FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;

-- Update vote_type enum values to be cleaner ('in', 'out' instead of 'vote_in', 'vote_out')
ALTER TABLE `votes` MODIFY COLUMN `vote_type` ENUM('in', 'out', 'vote_in', 'vote_out') NOT NULL;

-- Note: After this migration, new votes will use 'in'/'out' while old votes keep 'vote_in'/'vote_out'
-- The queries will handle both formats for backward compatibility 