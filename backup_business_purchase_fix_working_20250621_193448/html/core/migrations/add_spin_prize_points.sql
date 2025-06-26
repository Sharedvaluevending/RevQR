-- Migration: Add spin prize points tracking
-- Date: 2025-01-11
-- Description: Adds proper tracking and awarding of spin wheel prize points

USE `revenueqr`;

-- Add a prize_points column to spin_results to track actual points awarded
ALTER TABLE `spin_results` 
ADD COLUMN `prize_points` INT DEFAULT 0 COMMENT 'Actual points awarded from this prize (separate from base spin points)';

-- Update existing records based on their prize_won text
UPDATE `spin_results` SET `prize_points` = 500 WHERE `prize_won` = '500 Points!';
UPDATE `spin_results` SET `prize_points` = 200 WHERE `prize_won` = '200 Points';
UPDATE `spin_results` SET `prize_points` = 50 WHERE `prize_won` = '50 Points';
UPDATE `spin_results` SET `prize_points` = -20 WHERE `prize_won` = '-20 Points';
UPDATE `spin_results` SET `prize_points` = 0 WHERE `prize_won` IN ('Try Again', 'Lord Pixel!', 'Extra Vote', 'Lose All Votes');

-- Add index for better performance
CREATE INDEX `idx_spin_results_prize_points` ON `spin_results`(`user_id`, `prize_points`); 