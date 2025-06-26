<?php
/**
 * Loot Box Manager for Fortnite-style Loot Boxes
 * Handles loot box opening, reward distribution, and mechanics
 * 
 * @author Revenue QR Team  
 * @version 1.0
 * @date 2025-01-17
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/qr_coin_manager.php';

class LootBoxManager {
    
    /**
     * Open a loot box and distribute rewards
     * 
     * @param int $user_id User ID
     * @param int $purchase_id Purchase ID from user_qr_store_purchases
     * @return array Opening result with rewards
     */
    public static function openLootBox($user_id, $purchase_id) {
        global $pdo;
        
        if (!$user_id || !$purchase_id) {
            return ['success' => false, 'message' => 'Invalid parameters'];
        }
        
        try {
            $pdo->beginTransaction();
            
            // Get purchase and item details
            $stmt = $pdo->prepare("
                SELECT 
                    uqsp.*, 
                    qsi.item_name, 
                    qsi.item_type, 
                    qsi.item_data, 
                    qsi.rarity
                FROM user_qr_store_purchases uqsp
                JOIN qr_store_items qsi ON uqsp.qr_store_item_id = qsi.id
                WHERE uqsp.id = ? AND uqsp.user_id = ? AND qsi.item_type = 'loot_box' AND uqsp.status = 'active'
            ");
            $stmt->execute([$purchase_id, $user_id]);
            $purchase = $stmt->fetch();
            
            if (!$purchase) {
                $pdo->rollback();
                return ['success' => false, 'message' => 'Loot box not found or already opened'];
            }
            
            // Check if already opened
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM loot_box_openings WHERE purchase_id = ?");
            $stmt->execute([$purchase_id]);
            if ($stmt->fetchColumn() > 0) {
                $pdo->rollback();
                return ['success' => false, 'message' => 'Loot box already opened'];
            }
            
            $item_data = json_decode($purchase['item_data'], true);
            $rarity = $purchase['rarity'];
            
            // Generate rewards based on rarity
            $rewards = self::generateRewards($rarity, $item_data);
            
            // Mark purchase as used
            $stmt = $pdo->prepare("UPDATE user_qr_store_purchases SET status = 'used' WHERE id = ?");
            $stmt->execute([$purchase_id]);
            
            // Create loot box opening record
            $stmt = $pdo->prepare("
                INSERT INTO loot_box_openings 
                (user_id, purchase_id, qr_store_item_id, rewards_json, total_rewards, opened_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $user_id,
                $purchase_id,
                $purchase['qr_store_item_id'],
                json_encode($rewards),
                count($rewards)
            ]);
            
            // Distribute rewards
            $distributed_rewards = [];
            foreach ($rewards as $reward) {
                $result = self::distributeReward($user_id, $reward, $purchase_id);
                if ($result['success']) {
                    $distributed_rewards[] = $reward;
                }
            }
            
            $pdo->commit();
            
            return [
                'success' => true,
                'message' => 'Loot box opened successfully!',
                'loot_box_name' => $purchase['item_name'],
                'rarity' => $rarity,
                'rewards' => $distributed_rewards,
                'total_rewards' => count($distributed_rewards)
            ];
            
        } catch (PDOException $e) {
            $pdo->rollback();
            error_log("LootBoxManager::openLootBox() error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to open loot box'];
        }
    }
    
    /**
     * Generate rewards based on loot box rarity and data
     * 
     * @param string $rarity Loot box rarity (common, rare, legendary)
     * @param array $item_data Item configuration data
     * @return array Generated rewards
     */
    private static function generateRewards($rarity, $item_data) {
        $rewards = [];
        $min_rewards = $item_data['min_rewards'] ?? 3;
        $max_rewards = $item_data['max_rewards'] ?? 5;
        $reward_count = rand($min_rewards, $max_rewards);
        
        for ($i = 0; $i < $reward_count; $i++) {
            $reward = self::generateSingleReward($rarity, $item_data);
            if ($reward) {
                $rewards[] = $reward;
            }
        }
        
        // Ensure at least one good reward for rare+ boxes
        if ($rarity !== 'common' && !self::hasGoodReward($rewards)) {
            $rewards[] = self::generateGuaranteedReward($rarity, $item_data);
        }
        
        return $rewards;
    }
    
    /**
     * Generate a single reward
     * 
     * @param string $rarity Loot box rarity
     * @param array $item_data Item configuration
     * @return array|null Reward data
     */
    private static function generateSingleReward($rarity, $item_data) {
        $possible_rewards = $item_data['possible_rewards'] ?? ['qr_coins'];
        $reward_ranges = $item_data['reward_ranges'] ?? [];
        
        // Weight rewards based on rarity
        $weights = self::getRewardWeights($rarity);
        $reward_type = self::weightedRandomSelect($possible_rewards, $weights);
        
        switch ($reward_type) {
            case 'qr_coins':
            case 'massive_qr_coins':
                $range = $reward_ranges['qr_coins'] ?? [10, 50];
                $amount = rand($range[0], $range[1]);
                
                // Bonus for higher rarities
                if ($rarity === 'rare') $amount = (int)($amount * 1.5);
                if ($rarity === 'legendary') $amount = (int)($amount * 2.5);
                
                return [
                    'type' => 'qr_coins',
                    'amount' => $amount,
                    'display' => $amount . ' QR Coins',
                    'rarity' => $rarity,
                    'icon' => 'bi-coin'
                ];
                
            case 'spins':
            case 'premium_spins':
                $range = $reward_ranges['spins'] ?? [1, 3];
                $amount = rand($range[0], $range[1]);
                
                if ($rarity === 'rare') $amount = (int)($amount * 1.3);
                if ($rarity === 'legendary') $amount = (int)($amount * 2);
                
                return [
                    'type' => 'spins',
                    'amount' => $amount,
                    'display' => $amount . ' Spin Wheel Spins',
                    'rarity' => $rarity,
                    'icon' => 'bi-arrow-repeat'
                ];
                
            case 'votes':
            case 'premium_votes':
                $range = $reward_ranges['votes'] ?? [1, 5];
                $amount = rand($range[0], $range[1]);
                
                if ($rarity === 'rare') $amount = (int)($amount * 1.5);
                if ($rarity === 'legendary') $amount = (int)($amount * 3);
                
                return [
                    'type' => 'votes',
                    'amount' => $amount,
                    'display' => $amount . ' Premium Votes',
                    'rarity' => $rarity,
                    'icon' => 'bi-hand-thumbs-up'
                ];
                
            case 'small_boosts':
            case 'premium_boosts':
            case 'exclusive_boosts':
                $boosts = [
                    'spin_multiplier' => ['display' => '24h Spin Multiplier (2x)', 'duration' => 24],
                    'vote_bonus' => ['display' => '48h Vote Bonus (+50%)', 'duration' => 48],
                    'lucky_charm' => ['display' => '72h Lucky Charm (+10% spin luck)', 'duration' => 72]
                ];
                
                $boost_key = array_rand($boosts);
                $boost = $boosts[$boost_key];
                
                return [
                    'type' => 'boost',
                    'boost_type' => $boost_key,
                    'duration' => $boost['duration'],
                    'display' => $boost['display'],
                    'rarity' => $rarity,
                    'icon' => 'bi-lightning'
                ];
                
            default:
                // Fallback to QR coins
                return [
                    'type' => 'qr_coins',
                    'amount' => rand(10, 50),
                    'display' => rand(10, 50) . ' QR Coins',
                    'rarity' => 'common',
                    'icon' => 'bi-coin'
                ];
        }
    }
    
    /**
     * Get reward weights based on rarity
     * 
     * @param string $rarity Loot box rarity
     * @return array Reward weights
     */
    private static function getRewardWeights($rarity) {
        switch ($rarity) {
            case 'common':
                return [
                    'qr_coins' => 50,
                    'spins' => 30,
                    'votes' => 20
                ];
                
            case 'rare':
                return [
                    'qr_coins' => 40,
                    'spins' => 25,
                    'votes' => 20,
                    'premium_boosts' => 15
                ];
                
            case 'legendary':
                return [
                    'massive_qr_coins' => 30,
                    'premium_spins' => 25,
                    'premium_votes' => 20,
                    'exclusive_boosts' => 15,
                    'avatars' => 10
                ];
                
            default:
                return ['qr_coins' => 100];
        }
    }
    
    /**
     * Weighted random selection
     * 
     * @param array $options Options to select from
     * @param array $weights Weights for each option
     * @return string Selected option
     */
    private static function weightedRandomSelect($options, $weights) {
        $total_weight = 0;
        $cumulative_weights = [];
        
        foreach ($options as $option) {
            $weight = $weights[$option] ?? 10; // Default weight
            $total_weight += $weight;
            $cumulative_weights[$option] = $total_weight;
        }
        
        $random = rand(1, $total_weight);
        
        foreach ($cumulative_weights as $option => $weight) {
            if ($random <= $weight) {
                return $option;
            }
        }
        
        return $options[0]; // Fallback
    }
    
    /**
     * Check if rewards contain a good reward
     * 
     * @param array $rewards Generated rewards
     * @return bool Whether rewards contain a good reward
     */
    private static function hasGoodReward($rewards) {
        foreach ($rewards as $reward) {
            if ($reward['type'] === 'boost' || 
                ($reward['type'] === 'qr_coins' && $reward['amount'] >= 200) ||
                ($reward['type'] === 'spins' && $reward['amount'] >= 5)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Generate a guaranteed good reward for rare+ boxes
     * 
     * @param string $rarity Loot box rarity
     * @param array $item_data Item configuration
     * @return array Guaranteed reward
     */
    private static function generateGuaranteedReward($rarity, $item_data) {
        if ($rarity === 'legendary') {
            return [
                'type' => 'qr_coins',
                'amount' => rand(1000, 2000),
                'display' => rand(1000, 2000) . ' QR Coins (GUARANTEED)',
                'rarity' => 'legendary',
                'icon' => 'bi-gem'
            ];
        } else {
            return [
                'type' => 'boost',
                'boost_type' => 'spin_multiplier',
                'duration' => 48,
                'display' => '48h Spin Multiplier (2x) - GUARANTEED',
                'rarity' => 'rare',
                'icon' => 'bi-lightning'
            ];
        }
    }
    
    /**
     * Distribute a reward to user
     * 
     * @param int $user_id User ID
     * @param array $reward Reward data
     * @param int $purchase_id Purchase ID for reference
     * @return array Distribution result
     */
    private static function distributeReward($user_id, $reward, $purchase_id) {
        global $pdo;
        
        try {
            switch ($reward['type']) {
                case 'qr_coins':
                    QRCoinManager::addTransaction(
                        $user_id,
                        'earning',
                        'loot_box_reward',
                        $reward['amount'],
                        "Loot Box Reward: " . $reward['display'],
                        ['purchase_id' => $purchase_id, 'reward_type' => 'qr_coins']
                    );
                    break;
                    
                case 'spins':
                    // Add to user's spin balance (would need spin system integration)
                    $stmt = $pdo->prepare("
                        INSERT INTO user_spin_bonuses (user_id, source, spins_awarded, expires_at, created_at)
                        VALUES (?, 'loot_box', ?, DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())
                        ON DUPLICATE KEY UPDATE spins_awarded = spins_awarded + VALUES(spins_awarded)
                    ");
                    $stmt->execute([$user_id, $reward['amount']]);
                    break;
                    
                case 'votes':
                    // Add to user's vote pack balance
                    $stmt = $pdo->prepare("
                        INSERT INTO user_vote_packs (user_id, purchase_id, votes_total, votes_used, votes_remaining, source, expires_at)
                        VALUES (?, ?, ?, 0, ?, 'loot_box', DATE_ADD(NOW(), INTERVAL 90 DAY))
                    ");
                    $stmt->execute([$user_id, $purchase_id, $reward['amount'], $reward['amount']]);
                    break;
                    
                case 'boost':
                    // Add boost to user's active boosts
                    $expires_at = date('Y-m-d H:i:s', strtotime("+{$reward['duration']} hours"));
                    $stmt = $pdo->prepare("
                        INSERT INTO user_active_boosts 
                        (user_id, boost_type, boost_value, source, source_id, expires_at)
                        VALUES (?, ?, ?, 'loot_box', ?, ?)
                    ");
                    $stmt->execute([
                        $user_id, 
                        $reward['boost_type'], 
                        json_encode(['multiplier' => 2, 'duration' => $reward['duration']]),
                        $purchase_id,
                        $expires_at
                    ]);
                    break;
            }
            
            return ['success' => true];
            
        } catch (PDOException $e) {
            error_log("LootBoxManager::distributeReward() error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to distribute reward'];
        }
    }
    
    /**
     * Get user's loot box opening history
     * 
     * @param int $user_id User ID
     * @param int $limit Number of openings to return
     * @return array Opening history
     */
    public static function getUserOpeningHistory($user_id, $limit = 20) {
        global $pdo;
        
        if (!$user_id) return [];
        
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    lbo.*,
                    qsi.item_name as loot_box_name,
                    qsi.rarity
                FROM loot_box_openings lbo
                JOIN qr_store_items qsi ON lbo.qr_store_item_id = qsi.id
                WHERE lbo.user_id = ?
                ORDER BY lbo.opened_at DESC
                LIMIT ?
            ");
            $stmt->execute([$user_id, $limit]);
            
            return $stmt->fetchAll();
            
        } catch (PDOException $e) {
            error_log("LootBoxManager::getUserOpeningHistory() error: " . $e->getMessage());
            return [];
        }
    }
} 