-- Add user_id column to spin_results table for better user tracking
ALTER TABLE `spin_results` 
ADD COLUMN `user_id` INT NULL AFTER `id`,
ADD INDEX `idx_spin_results_user` (`user_id`),
ADD FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;

-- Update existing records to try to match IP addresses to user sessions (optional)
-- This is a best-effort migration - some data may not be recoverable
-- UPDATE spin_results SET user_id = (SELECT id FROM users WHERE ... ) WHERE user_ip = ...;

-- Note: After this migration, update all code to use user_id instead of user_ip for logged-in users 