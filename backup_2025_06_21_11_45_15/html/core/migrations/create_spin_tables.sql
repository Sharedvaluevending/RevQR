-- Create spin_results table
CREATE TABLE IF NOT EXISTS `spin_results` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_ip` VARCHAR(45) NOT NULL,
    `prize_won` VARCHAR(255) NOT NULL,
    `is_big_win` BOOLEAN DEFAULT FALSE,
    `business_id` INT NULL,
    `machine_id` INT NULL,
    `spin_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_spin_results_ip` (`user_ip`),
    INDEX `idx_spin_results_business` (`business_id`),
    INDEX `idx_spin_results_machine` (`machine_id`),
    INDEX `idx_spin_results_time` (`spin_time`),
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`machine_id`) REFERENCES `machines`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update rewards table to match expected schema
ALTER TABLE `rewards` 
ADD COLUMN `name` VARCHAR(255) NOT NULL AFTER `id`,
ADD COLUMN `rarity_level` INT NOT NULL DEFAULT 1 AFTER `description`,
ADD COLUMN `active` BOOLEAN DEFAULT TRUE AFTER `rarity_level`,
ADD COLUMN `image_url` VARCHAR(255) NULL AFTER `active`,
ADD COLUMN `code` VARCHAR(100) NULL AFTER `image_url`,
ADD COLUMN `link` VARCHAR(255) NULL AFTER `code`,
ADD COLUMN `list_id` INT NULL AFTER `link`,
ADD COLUMN `prize_type` VARCHAR(50) NULL AFTER `list_id`,
ADD INDEX `idx_rewards_list` (`list_id`),
ADD INDEX `idx_rewards_active` (`active`),
ADD INDEX `idx_rewards_rarity` (`rarity_level`);

-- Add foreign key for list_id if lists table exists
-- ALTER TABLE `rewards` ADD FOREIGN KEY (`list_id`) REFERENCES `lists`(`id`) ON DELETE SET NULL;

-- Add spin settings to lists table if it doesn't exist
ALTER TABLE `voting_lists` 
ADD COLUMN `spin_enabled` BOOLEAN DEFAULT FALSE AFTER `description`,
ADD COLUMN `spin_trigger_count` INT DEFAULT 3 AFTER `spin_enabled`; 