-- Add Posty Avatar to Avatar Config
-- Date: 2025-06-11
-- Description: Adds Posty avatar with 5% cashback on spin/casino losses, unlocked after spending 50,000 QR coins

USE `revenueqr`;

-- Insert Posty avatar configuration
INSERT INTO `avatar_config` (
    `avatar_id`, 
    `name`, 
    `filename`, 
    `description`, 
    `cost`, 
    `rarity`, 
    `unlock_method`, 
    `unlock_requirement`, 
    `special_perk`, 
    `perk_data`,
    `is_active`
) VALUES (
    16,
    'Posty',
    'posty.png',
    'Legendary Posty avatar - Unlocked after spending 50,000 QR coins!',
    0,
    'legendary',
    'milestone',
    JSON_OBJECT('spending_required', 50000),
    '5% cashback on all spin wheel and casino losses',
    JSON_OBJECT('loss_cashback_percentage', 5),
    TRUE
) ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `filename` = VALUES(`filename`),
    `description` = VALUES(`description`),
    `special_perk` = VALUES(`special_perk`),
    `perk_data` = VALUES(`perk_data`),
    `unlock_requirement` = VALUES(`unlock_requirement`),
    `updated_at` = CURRENT_TIMESTAMP;

-- Verify the avatar was added
SELECT 
    avatar_id,
    name,
    filename,
    rarity,
    unlock_method,
    special_perk,
    perk_data
FROM avatar_config 
WHERE avatar_id = 16; 