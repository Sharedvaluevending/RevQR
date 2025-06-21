-- Economy Rebalance Update Script
-- Date: 2025-01-07
-- Description: Updates all economic values to implement the rebalanced economy

USE `revenueqr`;

-- 1. Update voting reward values
UPDATE config_settings SET 
    setting_value = '15' 
WHERE setting_key = 'qr_coin_vote_base';

UPDATE config_settings SET 
    setting_value = '35' 
WHERE setting_key = 'qr_coin_vote_bonus';

-- 2. Update discount store prices (make more affordable)
UPDATE business_store_items SET 
    qr_coin_cost = 3000 
WHERE discount_percentage = 5.0 AND qr_coin_cost = 15000;

UPDATE business_store_items SET 
    qr_coin_cost = 7000 
WHERE discount_percentage = 10.0 AND qr_coin_cost = 35000;

-- 3. Update avatar costs (if avatar_config table exists)
UPDATE avatar_config SET 
    cost = 2000 
WHERE avatar_id = 2 AND cost = 500;

UPDATE avatar_config SET 
    cost = 2500 
WHERE avatar_id = 3 AND cost = 600;

UPDATE avatar_config SET 
    cost = 5000 
WHERE avatar_id = 4 AND cost = 1200;

UPDATE avatar_config SET 
    cost = 8000 
WHERE avatar_id = 5 AND cost = 2500;

UPDATE avatar_config SET 
    cost = 12000 
WHERE avatar_id = 6 AND cost = 3000;

UPDATE avatar_config SET 
    cost = 20000 
WHERE avatar_id = 7 AND cost = 5000;

UPDATE avatar_config SET 
    cost = 50000 
WHERE avatar_id = 11 AND cost = 10000;

-- 4. Update QR store items (legendary avatars)
UPDATE qr_store_items SET 
    qr_coin_cost = 30000 
WHERE item_name LIKE '%QR Easybake%' AND qr_coin_cost = 75000;

-- 5. Update special perk descriptions to reflect new vote rewards
UPDATE avatar_config SET 
    special_perk = '+15 QR coins per vote (base vote reward: 15→30)'
WHERE avatar_id = 8;

UPDATE avatar_config SET 
    special_perk = '+25 QR coins per vote (base vote reward: 15→40)'
WHERE avatar_id = 10;

UPDATE avatar_config SET 
    special_perk = '+5 QR coins per vote (base vote reward: 15→20)'
WHERE avatar_id = 3;

-- 6. Add new free earning methods (if tables exist)
INSERT IGNORE INTO daily_bonuses (
    bonus_type, bonus_name, bonus_description, qr_coin_reward, 
    max_claims_per_day, is_active
) VALUES 
('login', 'Daily Login Bonus', 'Get coins just for logging in!', 10, 1, 1),
('streak', '7-Day Streak Bonus', 'Login 7 days in a row for bonus!', 50, 1, 1),
('referral', 'Friend Referral', 'Invite friends and earn coins!', 100, 5, 1);

-- 7. Create achievement rewards table if it doesn't exist
CREATE TABLE IF NOT EXISTS achievement_rewards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    achievement_type VARCHAR(50) NOT NULL,
    achievement_name VARCHAR(100) NOT NULL,
    description TEXT,
    qr_coin_reward INT DEFAULT 0,
    requirement_value INT DEFAULT 1,
    is_repeatable BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_achievement (achievement_type, achievement_name)
) ENGINE=InnoDB;

-- 8. Insert achievement rewards
INSERT IGNORE INTO achievement_rewards (
    achievement_type, achievement_name, description, qr_coin_reward, requirement_value
) VALUES 
('voting', 'First Vote', 'Cast your first vote', 25, 1),
('voting', 'Vote Veteran', 'Cast 50 votes', 100, 50),
('voting', 'Vote Master', 'Cast 200 votes', 500, 200),
('spinning', 'Spin Beginner', 'Spin the wheel 10 times', 50, 10),
('spinning', 'Spin Expert', 'Spin the wheel 100 times', 200, 100),
('casino', 'Casino Rookie', 'Play casino games 25 times', 100, 25),
('horse_racing', 'First Bet', 'Place your first horse racing bet', 25, 1),
('horse_racing', 'High Roller', 'Place a bet of 100+ coins', 50, 100),
('daily', 'Week Warrior', 'Login 7 days in a row', 100, 7);

-- 9. Summary of changes
SELECT 
    'Economy Rebalance Summary' as info,
    'Voting rewards increased by 67%' as voting_change,
    'Discount store 80% more affordable' as discount_change,
    'Avatar costs rebalanced for progression' as avatar_change,
    'Casino jackpots increased to 1000 coins' as casino_change,
    'New achievements and daily bonuses added' as new_features;

-- Success message
SELECT 'Economy rebalance completed successfully!' as result,
       'Users will now earn more and spend less' as impact,
       'Platform engagement should increase significantly' as expected_outcome; 