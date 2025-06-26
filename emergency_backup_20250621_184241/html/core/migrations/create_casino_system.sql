-- Casino System Database Schema
-- Date: 2025-01-11
-- Description: Complete casino system with slot machines, business controls, and QR avatar integration

USE `revenueqr`;

-- Casino Games Management
CREATE TABLE IF NOT EXISTS `casino_games` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `game_type` ENUM('slot_machine', 'roulette', 'blackjack') DEFAULT 'slot_machine',
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT,
    `min_bet` INT DEFAULT 1 COMMENT 'Minimum QR coins to play',
    `max_bet` INT DEFAULT 100,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_casino_games_type` (`game_type`),
    INDEX `idx_casino_games_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Business Casino Settings
CREATE TABLE IF NOT EXISTS `business_casino_settings` (
    `business_id` INT PRIMARY KEY,
    `casino_enabled` BOOLEAN DEFAULT FALSE,
    `max_daily_plays` INT DEFAULT 10,
    `house_edge` DECIMAL(5,4) DEFAULT 0.0500 COMMENT '5% house edge',
    `jackpot_multiplier` DECIMAL(8,2) DEFAULT 100.00,
    `min_bet` INT DEFAULT 1,
    `max_bet` INT DEFAULT 50,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
    INDEX `idx_casino_enabled` (`casino_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Casino Prize Pools (Business Configurable)
CREATE TABLE IF NOT EXISTS `casino_prizes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `business_id` INT NOT NULL,
    `game_id` INT NOT NULL,
    `prize_name` VARCHAR(255) NOT NULL,
    `prize_type` ENUM('qr_coins', 'discount', 'product', 'service') DEFAULT 'qr_coins',
    `prize_value` INT NOT NULL COMMENT 'QR coins value or percentage for discounts',
    `prize_description` TEXT,
    `probability_weight` INT DEFAULT 1,
    `max_wins_per_day` INT DEFAULT NULL,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`game_id`) REFERENCES `casino_games`(`id`) ON DELETE CASCADE,
    INDEX `idx_casino_prizes_business` (`business_id`),
    INDEX `idx_casino_prizes_active` (`is_active`),
    INDEX `idx_casino_prizes_weight` (`probability_weight`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Casino Play History
CREATE TABLE IF NOT EXISTS `casino_plays` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `business_id` INT NOT NULL,
    `game_id` INT NOT NULL,
    `bet_amount` INT NOT NULL,
    `symbols_result` JSON COMMENT 'Avatar symbols that appeared',
    `prize_won` VARCHAR(255) NULL,
    `prize_type` ENUM('qr_coins', 'discount', 'product', 'service') NULL,
    `win_amount` INT DEFAULT 0,
    `is_jackpot` BOOLEAN DEFAULT FALSE,
    `played_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`game_id`) REFERENCES `casino_games`(`id`) ON DELETE CASCADE,
    INDEX `idx_casino_plays_user` (`user_id`),
    INDEX `idx_casino_plays_business` (`business_id`),
    INDEX `idx_casino_plays_date` (`played_at`),
    INDEX `idx_casino_plays_jackpot` (`is_jackpot`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Casino Daily Limits Tracking
CREATE TABLE IF NOT EXISTS `casino_daily_limits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `business_id` INT NOT NULL,
    `play_date` DATE NOT NULL,
    `plays_count` INT DEFAULT 0,
    `total_bet` INT DEFAULT 0,
    `total_won` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_user_business_date` (`user_id`, `business_id`, `play_date`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
    INDEX `idx_daily_limits_date` (`play_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default slot machine game
INSERT IGNORE INTO `casino_games` (`id`, `name`, `description`, `min_bet`, `max_bet`) VALUES 
(1, 'QR Avatar Slots', 'Classic 3-reel slot machine featuring your collected QR avatars as symbols', 1, 50);

-- Insert default prizes for businesses to customize
INSERT IGNORE INTO `casino_prizes` (`business_id`, `game_id`, `prize_name`, `prize_type`, `prize_value`, `prize_description`, `probability_weight`) 
SELECT 
    b.id,
    1,
    'Small Win',
    'qr_coins',
    10,
    '10 QR Coins bonus',
    50
FROM `businesses` b 
WHERE NOT EXISTS (
    SELECT 1 FROM `casino_prizes` cp WHERE cp.business_id = b.id
);

-- Add casino type to existing campaign types if the column exists
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE table_name = 'qr_campaigns' AND column_name = 'type' AND table_schema = DATABASE()) > 0,
    "ALTER TABLE `qr_campaigns` MODIFY COLUMN `type` ENUM('vote','promo','casino') NOT NULL",
    "SELECT 'qr_campaigns.type column does not exist' as note"
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt; 