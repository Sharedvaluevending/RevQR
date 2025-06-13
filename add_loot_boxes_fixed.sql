-- Add Fortnite-style Loot Boxes to QR Store
-- First add 'loot_box' to the item_type enum
ALTER TABLE qr_store_items MODIFY COLUMN item_type 
ENUM('avatar','spin_pack','slot_pack','vote_pack','multiplier','insurance','analytics','boost','loot_box') NOT NULL;

-- Add the loot boxes with proper pricing based on current economy
INSERT INTO qr_store_items (item_name, item_type, qr_coin_cost, item_description, item_data, rarity, is_active) VALUES
(
    'Common Loot Box', 
    'loot_box', 
    300, 
    'A mysterious common loot box! Contains 3-5 random rewards including QR coins, spins, or bonus votes. Perfect for daily surprises!', 
    '{"min_rewards": 3, "max_rewards": 5, "rarity": "common", "possible_rewards": ["qr_coins", "spins", "votes", "small_boosts"], "reward_ranges": {"qr_coins": [50, 200], "spins": [1, 3], "votes": [1, 5]}, "image": "/assets/qrstore/commonlootad.png"}', 
    'common',
    1
),
(
    'Rare Loot Box', 
    'loot_box', 
    750, 
    'A glowing rare loot box! Contains 4-6 premium rewards with better odds for valuable items. Rare items and bigger bonuses await!', 
    '{"min_rewards": 4, "max_rewards": 6, "rarity": "rare", "possible_rewards": ["qr_coins", "spins", "votes", "premium_boosts", "small_avatars"], "reward_ranges": {"qr_coins": [200, 500], "spins": [3, 8], "votes": [5, 15]}, "image": "/assets/qrstore/rarelootad.png"}', 
    'rare',
    1
),
(
    'Legendary Loot Box', 
    'loot_box', 
    2000, 
    'The ULTIMATE legendary loot box! Contains 5-8 epic rewards with guaranteed rare items, massive QR coin bonuses, and chances for exclusive avatars!', 
    '{"min_rewards": 5, "max_rewards": 8, "rarity": "legendary", "possible_rewards": ["massive_qr_coins", "premium_spins", "premium_votes", "avatars", "exclusive_boosts"], "reward_ranges": {"qr_coins": [1000, 3000], "spins": [10, 25], "votes": [20, 50]}, "image": "/assets/qrstore/ledgenarylootad.png"}', 
    'legendary',
    1
);

SELECT 'Fortnite-style Loot Boxes Added Successfully!' as Status; 