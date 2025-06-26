-- Migration: Add equipped_avatar column to users table
-- Date: 2025-01-11
-- Description: Adds equipped_avatar column to track user's currently equipped avatar

USE `revenueqr`;

-- Add equipped_avatar column to users table
ALTER TABLE `users` 
ADD COLUMN `equipped_avatar` INT DEFAULT 1 COMMENT 'Currently equipped avatar ID (defaults to QR Ted)';

-- Add index for better performance
CREATE INDEX `idx_users_equipped_avatar` ON `users`(`equipped_avatar`);

-- Update existing users to have default avatar (QR Ted - ID 1)
UPDATE `users` SET `equipped_avatar` = 1 WHERE `equipped_avatar` IS NULL; 