-- Avatar Economy Rebalance Migration
-- Date: 2025-01-07
-- Description: Updates avatar costs to be balanced with QR coin economy earning rates

USE `revenueqr`;

-- Create avatar_unlocks table if it doesn't exist (for tracking purchased avatars)
CREATE TABLE IF NOT EXISTS `avatar_unlocks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `avatar_id` INT NOT NULL,
    `unlock_method` ENUM('purchase', 'achievement', 'spin_wheel', 'milestone') DEFAULT 'purchase',
    `cost_paid` INT DEFAULT 0 COMMENT 'QR coins spent (0 for free unlocks)',
    `unlocked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY `unique_user_avatar` (`user_id`, `avatar_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_unlocks` (`user_id`),
    INDEX `idx_avatar_id` (`avatar_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create avatar_config table for dynamic avatar management
CREATE TABLE IF NOT EXISTS `avatar_config` (
    `avatar_id` INT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL,
    `filename` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `cost` INT DEFAULT 0 COMMENT 'QR coin cost (0 for free/achievement avatars)',
    `rarity` ENUM('common', 'rare', 'epic', 'legendary', 'ultra_rare', 'mythical', 'special') DEFAULT 'common',
    `unlock_method` ENUM('purchase', 'achievement', 'spin_wheel', 'milestone', 'free') DEFAULT 'purchase',
    `unlock_requirement` JSON COMMENT 'Requirements for achievement/milestone unlocks',
    `special_perk` TEXT COMMENT 'Description of avatar special abilities',
    `perk_data` JSON COMMENT 'Structured perk data for calculations',
    `is_active` BOOLEAN DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert rebalanced avatar data
INSERT INTO `avatar_config` (`avatar_id`, `name`, `filename`, `description`, `cost`, `rarity`, `unlock_method`, `unlock_requirement`, `special_perk`, `perk_data`) VALUES
-- Free Common Avatars
(1, 'QR Ted', 'qrted.png', 'Classic Ted QR code avatar - Free starter avatar', 0, 'common', 'free', NULL, 'None (Starter avatar)', NULL),
(12, 'QR Steve', 'qrsteve.png', 'Classic Steve QR code avatar - Available for everyone', 0, 'common', 'free', NULL, 'None (Free avatar)', NULL),
(13, 'QR Bob', 'qrbob.png', 'Classic Bob QR code avatar - Available for everyone', 0, 'common', 'free', NULL, 'None (Free avatar)', NULL),

-- Affordable Tier (1 week earnings)
(2, 'QR James', 'qrjames.png', 'Cool James QR code avatar with spin protection', 500, 'rare', 'purchase', NULL, 'Vote protection (immune to \"Lose All Votes\")', JSON_OBJECT('vote_protection', true)),
(3, 'QR Mike', 'qrmike.png', 'Awesome Mike QR code avatar with vote bonus', 600, 'rare', 'purchase', NULL, '+5 QR coins per vote (base vote reward: 5→10)', JSON_OBJECT('vote_bonus', 5)),

-- Monthly Tier
(4, 'QR Kevin', 'qrkevin.png', 'Elite Kevin QR code avatar with spin power', 1200, 'epic', 'purchase', NULL, '+10 QR coins per spin (base spin reward: 15→25)', JSON_OBJECT('spin_bonus', 10)),
(5, 'QR Tim', 'qrtim.png', 'Legendary Tim QR code avatar with daily bonus boost', 2500, 'epic', 'purchase', NULL, '+20% daily bonus multiplier (Vote bonus: 25→30, Spin bonus: 50→60)', JSON_OBJECT('daily_bonus_multiplier', 1.2)),
(6, 'QR Bush', 'qrbush.png', 'Mythical Bush QR code avatar with ultimate luck', 3000, 'legendary', 'purchase', NULL, '+10% better spin prizes (50→55, 200→220, 500→550)', JSON_OBJECT('spin_prize_multiplier', 1.1)),

-- Premium Tier
(7, 'QR Terry', 'qrterry.png', 'Godlike Terry QR code avatar - The ultimate avatar', 5000, 'legendary', 'purchase', NULL, 'Combined: +5 per vote, +10 per spin, vote protection', JSON_OBJECT('vote_bonus', 5, 'spin_bonus', 10, 'vote_protection', true)),

-- Achievement Unlocks
(8, 'QR ED', 'qred.png', 'Elite QR ED avatar - Unlocked for dedicated voters!', 0, 'epic', 'achievement', JSON_OBJECT('votes_required', 200), '+15 QR coins per vote (base vote reward: 5→20)', JSON_OBJECT('vote_bonus', 15)),
(10, 'QR NED', 'qrned.png', 'Legendary Pixel Master avatar - For the ultimate voters!', 0, 'legendary', 'achievement', JSON_OBJECT('votes_required', 500), '+25 QR coins per vote (base vote reward: 5→30)', JSON_OBJECT('vote_bonus', 25)),

-- Special Unlocks
(9, 'Lord Pixel', 'qrLordPixel.png', 'Ultra-rare Lord Pixel avatar - Only obtainable through the spin wheel!', 0, 'ultra_rare', 'spin_wheel', NULL, 'Immune to spin penalties + extra spin chance', JSON_OBJECT('spin_immunity', true, 'extra_spin_chance', 0.1)),
(15, 'QR Easybake', 'qrEasybake.png', 'Epic 420 milestone avatar', 0, 'ultra_rare', 'milestone', JSON_OBJECT('votes_required', 420, 'spins_required', 420, 'points_required', 420), '+15 per vote, +25 per spin, monthly super spin (guarantees 420 bonus)', JSON_OBJECT('vote_bonus', 15, 'spin_bonus', 25, 'monthly_super_spin', true)),

-- Mythical Tier
(11, 'QR Clayton', 'qrClayton.png', 'Mythical Clayton QR code avatar - The ultimate weekend warrior!', 10000, 'mythical', 'purchase', NULL, 'Weekend warrior: 5 spins on weekends + double weekend earnings', JSON_OBJECT('weekend_spins', 5, 'weekend_earnings_multiplier', 2)),

-- Special For Sale
(14, 'QR Ryan', 'qrRyan.png', 'Premium QR Ryan avatar - Double activity boost!', 0, 'special', 'purchase', NULL, 'Double points on all activity and spins for 2 days', JSON_OBJECT('activity_multiplier', 2, 'duration_days', 2))

ON DUPLICATE KEY UPDATE
    `cost` = VALUES(`cost`),
    `description` = VALUES(`description`),
    `special_perk` = VALUES(`special_perk`),
    `perk_data` = VALUES(`perk_data`),
    `updated_at` = CURRENT_TIMESTAMP;

-- Summary of changes
SELECT 
    'Avatar Economy Rebalance Summary' as info,
    COUNT(*) as total_avatars,
    COUNT(CASE WHEN cost = 0 THEN 1 END) as free_avatars,
    COUNT(CASE WHEN cost > 0 AND cost <= 1000 THEN 1 END) as affordable_avatars,
    COUNT(CASE WHEN cost > 1000 AND cost <= 5000 THEN 1 END) as premium_avatars,
    COUNT(CASE WHEN cost > 5000 THEN 1 END) as mythical_avatars
FROM avatar_config;

-- Show cost distribution
SELECT 
    rarity,
    COUNT(*) as count,
    MIN(cost) as min_cost,
    MAX(cost) as max_cost,
    AVG(cost) as avg_cost
FROM avatar_config
GROUP BY rarity
ORDER BY 
    CASE rarity
        WHEN 'common' THEN 1
        WHEN 'rare' THEN 2
        WHEN 'epic' THEN 3
        WHEN 'legendary' THEN 4
        WHEN 'ultra_rare' THEN 5
        WHEN 'mythical' THEN 6
        WHEN 'special' THEN 7
    END;

-- Success message
SELECT 'Avatar economy rebalance completed successfully!' as result; 