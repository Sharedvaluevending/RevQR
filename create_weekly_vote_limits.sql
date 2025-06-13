-- Create Weekly Vote Limits Table
-- Date: 2025-01-07
-- Description: Creates table to track weekly voting limits and support "Lose All Votes" penalty

USE `revenueqr`;

-- Create user_weekly_vote_limits table if it doesn't exist
CREATE TABLE IF NOT EXISTS `user_weekly_vote_limits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `week_year` VARCHAR(10) NOT NULL COMMENT 'Format: YYYY-WW (e.g., 2025-01)',
    `votes_used` INT DEFAULT 0,
    `vote_limit` INT DEFAULT 2 COMMENT 'Default weekly vote limit',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY `unique_user_week` (`user_id`, `week_year`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_week_year` (`week_year`),
    
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Tracks weekly voting limits and usage per user';

-- Show table structure
DESCRIBE user_weekly_vote_limits; 