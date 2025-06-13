-- Migration: Simplify Business Casino System to Unified Revenue QR Casino
-- Date: 2025-06-08
-- Purpose: Remove complex business-specific casino configurations and create unified system

USE `revenueqr`;

-- Create simplified business casino participation table
CREATE TABLE IF NOT EXISTS `business_casino_participation` (
    `business_id` INT PRIMARY KEY,
    `casino_enabled` BOOLEAN DEFAULT FALSE,
    `revenue_share_percentage` DECIMAL(5,2) DEFAULT 10.00 COMMENT 'Percentage of local casino activity revenue',
    `featured_promotion` VARCHAR(255) NULL COMMENT 'Optional promotional text for this location',
    `location_bonus_multiplier` DECIMAL(3,2) DEFAULT 1.00 COMMENT 'Optional location-specific bonus (1.0 = no bonus)',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
    INDEX `idx_casino_participation_enabled` (`casino_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrate existing business casino settings to simplified system
INSERT INTO `business_casino_participation` 
(business_id, casino_enabled, revenue_share_percentage, created_at)
SELECT 
    business_id,
    casino_enabled,
    10.00 as revenue_share_percentage, -- Default 10% revenue share
    created_at
FROM `business_casino_settings` 
WHERE casino_enabled = 1
ON DUPLICATE KEY UPDATE 
    casino_enabled = VALUES(casino_enabled),
    updated_at = CURRENT_TIMESTAMP;

-- Create unified casino settings table (platform-wide)
CREATE TABLE IF NOT EXISTS `casino_unified_settings` (
    `id` INT PRIMARY KEY DEFAULT 1,
    `platform_name` VARCHAR(100) DEFAULT 'Revenue QR Casino',
    `base_daily_spins` INT DEFAULT 10 COMMENT 'Free spins per user per day',
    `min_bet` INT DEFAULT 1,
    `max_bet` INT DEFAULT 50,
    `house_edge_target` DECIMAL(5,2) DEFAULT 5.00 COMMENT 'Target platform profit percentage',
    `jackpot_threshold` INT DEFAULT 25 COMMENT 'Multiplier threshold for jackpot classification',
    `location_requirement` BOOLEAN DEFAULT TRUE COMMENT 'Must be at business location to play',
    `max_location_bonus` DECIMAL(3,2) DEFAULT 1.50 COMMENT 'Maximum location bonus multiplier',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default unified settings
INSERT INTO `casino_unified_settings` 
(id, platform_name, base_daily_spins, min_bet, max_bet, house_edge_target, jackpot_threshold)
VALUES (1, 'Revenue QR Casino', 10, 1, 50, 5.00, 25)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Create unified prize pool (replacing business-specific prizes)
CREATE TABLE IF NOT EXISTS `casino_unified_prizes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `prize_name` VARCHAR(255) NOT NULL,
    `prize_type` ENUM('qr_coins', 'bonus_spins', 'multiplier_boost') DEFAULT 'qr_coins',
    `base_value` INT NOT NULL COMMENT 'Base prize value',
    `win_probability` DECIMAL(6,3) NOT NULL COMMENT 'Win probability percentage (0.001-99.999)',
    `multiplier_min` INT DEFAULT 1,
    `multiplier_max` INT DEFAULT 5,
    `is_jackpot` BOOLEAN DEFAULT FALSE,
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_casino_unified_prizes_active` (`is_active`),
    INDEX `idx_casino_unified_prizes_probability` (`win_probability`),
    INDEX `idx_casino_unified_prizes_jackpot` (`is_jackpot`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert unified prize pool
INSERT INTO `casino_unified_prizes` 
(prize_name, prize_type, base_value, win_probability, multiplier_min, multiplier_max, is_jackpot) VALUES
('Small Win', 'qr_coins', 2, 25.000, 2, 4, 0),
('Medium Win', 'qr_coins', 5, 15.000, 3, 6, 0),
('Big Win', 'qr_coins', 10, 8.000, 5, 10, 0),
('Epic Win', 'qr_coins', 15, 4.000, 8, 15, 0),
('Legendary Win', 'qr_coins', 25, 2.000, 12, 25, 1),
('Mythical Jackpot', 'qr_coins', 50, 0.500, 25, 50, 1),
('Ultimate Jackpot', 'qr_coins', 100, 0.100, 50, 100, 1)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- Add location tracking to casino plays
ALTER TABLE `casino_plays` 
ADD COLUMN `business_revenue_share` DECIMAL(8,2) DEFAULT 0.00 COMMENT 'Revenue shared with business location',
ADD COLUMN `location_bonus_applied` DECIMAL(3,2) DEFAULT 1.00 COMMENT 'Location bonus multiplier applied',
ADD COLUMN `unified_prize_id` INT NULL COMMENT 'Reference to unified prize won';

-- Add index separately to avoid syntax issues
ALTER TABLE `casino_plays` 
ADD INDEX `idx_casino_plays_revenue_share` (`business_revenue_share`);

-- Create business casino revenue tracking
CREATE TABLE IF NOT EXISTS `business_casino_revenue` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `business_id` INT NOT NULL,
    `date_period` DATE NOT NULL,
    `total_plays_at_location` INT DEFAULT 0,
    `total_bets_at_location` DECIMAL(10,2) DEFAULT 0.00,
    `total_winnings_at_location` DECIMAL(10,2) DEFAULT 0.00,
    `revenue_share_earned` DECIMAL(10,2) DEFAULT 0.00,
    `bonus_multiplier_avg` DECIMAL(3,2) DEFAULT 1.00,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`business_id`) REFERENCES `businesses`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_business_date` (`business_id`, `date_period`),
    INDEX `idx_business_casino_revenue_date` (`date_period`),
    INDEX `idx_business_casino_revenue_business` (`business_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create a view for simplified business casino stats
CREATE OR REPLACE VIEW `business_casino_summary` AS
SELECT 
    b.id as business_id,
    b.name as business_name,
    b.logo_path,
    bcp.casino_enabled,
    bcp.revenue_share_percentage,
    bcp.featured_promotion,
    bcp.location_bonus_multiplier,
    COALESCE(SUM(bcr.total_plays_at_location), 0) as total_plays_30d,
    COALESCE(SUM(bcr.total_bets_at_location), 0) as total_bets_30d,
    COALESCE(SUM(bcr.revenue_share_earned), 0) as revenue_earned_30d,
    COALESCE(AVG(bcr.bonus_multiplier_avg), 1.00) as avg_bonus_multiplier
FROM businesses b
LEFT JOIN business_casino_participation bcp ON b.id = bcp.business_id
LEFT JOIN business_casino_revenue bcr ON b.id = bcr.business_id 
    AND bcr.date_period >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY b.id, b.name, b.logo_path, bcp.casino_enabled, 
         bcp.revenue_share_percentage, bcp.featured_promotion, bcp.location_bonus_multiplier;

-- Add comments explaining the new system
ALTER TABLE `business_casino_participation` COMMENT = 'Simplified business casino participation - just enable/disable and revenue sharing';
ALTER TABLE `casino_unified_settings` COMMENT = 'Platform-wide casino settings controlled by admin';
ALTER TABLE `casino_unified_prizes` COMMENT = 'Unified prize pool for all casino locations';
ALTER TABLE `business_casino_revenue` COMMENT = 'Tracks revenue sharing for businesses based on local casino activity';

-- Migration complete notification
SELECT 'Casino system migration completed!' as status,
       'Business-specific configurations simplified to unified system' as description,
       'Businesses now just enable/disable participation for automatic revenue sharing' as benefit; 