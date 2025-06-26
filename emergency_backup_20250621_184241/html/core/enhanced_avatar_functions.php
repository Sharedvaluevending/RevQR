<?php

/**
 * Enhanced QRCoinManager with Avatar Perk Support
 * Replaces existing awardVoteCoins and awardSpinCoins with perk-aware versions
 */

/**
 * Get user's avatar perks for current day
 */
function getUserAvatarPerks($user_id, $activity_type = "general") {
    global $pdo;
    
    if (!$user_id) return ["perks" => [], "avatar_name" => "QR Ted"];
    
    try {
        // Get equipped avatar
        $stmt = $pdo->prepare("SELECT equipped_avatar FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $equipped_avatar = $stmt->fetchColumn() ?: 1;
        
        // Get avatar config
        $stmt = $pdo->prepare("
            SELECT name, perk_data, day_restrictions 
            FROM avatar_config 
            WHERE avatar_id = ? AND is_active = 1
        ");
        $stmt->execute([$equipped_avatar]);
        $avatar = $stmt->fetch();
        
        if (!$avatar || !$avatar["perk_data"]) {
            return ["perks" => [], "avatar_name" => $avatar["name"] ?? "QR Ted"];
        }
        
        $perks = json_decode($avatar["perk_data"], true) ?: [];
        $day_restrictions = json_decode($avatar["day_restrictions"], true);
        
        // Check day restrictions
        if ($day_restrictions && isset($day_restrictions["active_days"])) {
            $current_day = strtolower(date("l"));
            if (!in_array($current_day, $day_restrictions["active_days"])) {
                return [
                    "perks" => [], 
                    "avatar_name" => $avatar["name"],
                    "day_restricted" => true,
                    "restriction_info" => $day_restrictions["description"] ?? "Not active today"
                ];
            }
        }
        
        return ["perks" => $perks, "avatar_name" => $avatar["name"]];
        
    } catch (Exception $e) {
        error_log("getUserAvatarPerks error: " . $e->getMessage());
        return ["perks" => [], "avatar_name" => "QR Ted"];
    }
}

/**
 * Enhanced vote coin award with avatar perks
 */
function awardVoteCoinsWithPerks($user_id, $vote_id, $is_daily_bonus = false) {
    if (!$user_id) return false;
    
    $economic_settings = ConfigManager::getEconomicSettings();
    $base_amount = $economic_settings["qr_coin_vote_base"] ?? 5;
    $bonus_amount = $is_daily_bonus ? ($economic_settings["qr_coin_vote_bonus"] ?? 25) : 0;
    
    // Apply avatar perks
    $avatar_info = getUserAvatarPerks($user_id, "vote");
    $perks = $avatar_info["perks"];
    
    // Apply vote bonus perk
    if (isset($perks["vote_bonus"])) {
        $base_amount += $perks["vote_bonus"];
    }
    
    // Apply activity multiplier (Monday motivation, etc.)
    if (isset($perks["activity_multiplier"])) {
        $base_amount = round($base_amount * $perks["activity_multiplier"]);
        $bonus_amount = round($bonus_amount * $perks["activity_multiplier"]);
    }
    
    // Apply daily bonus multiplier
    if (isset($perks["daily_bonus_multiplier"]) && $bonus_amount > 0) {
        $bonus_amount = round($bonus_amount * $perks["daily_bonus_multiplier"]);
    }
    
    // Apply weekend earnings multiplier
    if (isset($perks["weekend_earnings_multiplier"])) {
        $current_day = strtolower(date("l"));
        if (in_array($current_day, ["saturday", "sunday"])) {
            $base_amount = round($base_amount * $perks["weekend_earnings_multiplier"]);
            $bonus_amount = round($bonus_amount * $perks["weekend_earnings_multiplier"]);
        }
    }
    
    $total_amount = $base_amount + $bonus_amount;
    
    $description = $is_daily_bonus ? 
        "Vote reward + daily bonus: {$base_amount} + {$bonus_amount} coins" :
        "Vote reward: {$base_amount} coins";
    
    if (!empty($perks)) {
        $description .= " (Avatar: {$avatar_info["avatar_name"]})";
    }
    
    return QRCoinManager::addTransaction(
        $user_id,
        "earning",
        "voting",
        $total_amount,
        $description,
        [
            "base_amount" => $base_amount,
            "bonus_amount" => $bonus_amount,
            "daily_bonus" => $is_daily_bonus,
            "avatar_perks" => $perks,
            "avatar_name" => $avatar_info["avatar_name"]
        ],
        $vote_id,
        "vote"
    );
}

/**
 * Enhanced spin coin award with avatar perks
 */
function awardSpinCoinsWithPerks($user_id, $spin_id, $prize_points = 0, $is_daily_bonus = false, $is_super_spin = false) {
    if (!$user_id) return false;
    
    $economic_settings = ConfigManager::getEconomicSettings();
    $base_amount = $economic_settings["qr_coin_spin_base"] ?? 15;
    $bonus_amount = $is_daily_bonus ? ($economic_settings["qr_coin_spin_bonus"] ?? 50) : 0;
    $super_bonus = $is_super_spin ? 420 : 0;
    
    // Apply avatar perks
    $avatar_info = getUserAvatarPerks($user_id, "spin");
    $perks = $avatar_info["perks"];
    
    // Apply spin bonus perk
    if (isset($perks["spin_bonus"])) {
        $base_amount += $perks["spin_bonus"];
    }
    
    // Apply prize multiplier
    if (isset($perks["spin_prize_multiplier"]) && $prize_points > 0) {
        $prize_points = round($prize_points * $perks["spin_prize_multiplier"]);
    }
    
    // Apply activity multiplier
    if (isset($perks["activity_multiplier"])) {
        $base_amount = round($base_amount * $perks["activity_multiplier"]);
        $bonus_amount = round($bonus_amount * $perks["activity_multiplier"]);
        $prize_points = round($prize_points * $perks["activity_multiplier"]);
    }
    
    // Apply weekend earnings multiplier
    if (isset($perks["weekend_earnings_multiplier"])) {
        $current_day = strtolower(date("l"));
        if (in_array($current_day, ["saturday", "sunday"])) {
            $base_amount = round($base_amount * $perks["weekend_earnings_multiplier"]);
            $bonus_amount = round($bonus_amount * $perks["weekend_earnings_multiplier"]);
        }
    }
    
    $total_amount = $base_amount + $bonus_amount + $super_bonus + $prize_points;
    
    $description_parts = ["Spin reward: {$base_amount} coins"];
    if ($bonus_amount > 0) $description_parts[] = "daily bonus: {$bonus_amount} coins";
    if ($super_bonus > 0) $description_parts[] = "super spin bonus: {$super_bonus} coins";
    if ($prize_points != 0) $description_parts[] = "prize: {$prize_points} coins";
    
    if (!empty($perks)) {
        $description_parts[] = "Avatar: {$avatar_info["avatar_name"]}";
    }
    
    $description = implode(", ", $description_parts);
    
    return QRCoinManager::addTransaction(
        $user_id,
        "earning",
        "spinning",
        $total_amount,
        $description,
        [
            "base_amount" => $base_amount,
            "bonus_amount" => $bonus_amount,
            "super_bonus" => $super_bonus,
            "prize_points" => $prize_points,
            "daily_bonus" => $is_daily_bonus,
            "super_spin" => $is_super_spin,
            "avatar_perks" => $perks,
            "avatar_name" => $avatar_info["avatar_name"]
        ],
        $spin_id,
        "spin"
    );
}

/**
 * Process cashback for losses (Posty avatar perk)
 * Call this when user loses QR coins on spins or casino
 */
function processCashbackOnLoss($user_id, $loss_amount, $loss_type = 'spin', $reference_id = null) {
    if (!$user_id || $loss_amount <= 0) return false;
    
    // Get avatar perks
    $avatar_info = getUserAvatarPerks($user_id, "loss");
    $perks = $avatar_info["perks"];
    
    // Check if user has cashback perk (Posty avatar)
    if (!isset($perks["loss_cashback_percentage"])) {
        return false; // No cashback perk
    }
    
    $cashback_percentage = $perks["loss_cashback_percentage"];
    $cashback_amount = round($loss_amount * ($cashback_percentage / 100));
    
    if ($cashback_amount <= 0) return false;
    
    $description = "Posty cashback: {$cashback_percentage}% of {$loss_amount} coin loss ({$loss_type})";
    
    return QRCoinManager::addTransaction(
        $user_id,
        "earning",
        "cashback",
        $cashback_amount,
        $description,
        [
            "original_loss" => $loss_amount,
            "cashback_percentage" => $cashback_percentage,
            "loss_type" => $loss_type,
            "avatar_name" => $avatar_info["avatar_name"]
        ],
        $reference_id,
        $loss_type . "_cashback"
    );
}
