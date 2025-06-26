<?php

/**
 * Get user's current avatar perks with day restrictions
 */
function getActiveAvatarPerks($user_id) {
    global $pdo;
    
    if (!$user_id) return ["perks" => [], "avatar_name" => "QR Ted"];
    
    try {
        // Get equipped avatar
        $stmt = $pdo->prepare("SELECT equipped_avatar FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $equipped_avatar = $stmt->fetchColumn() ?: 1;
        
        // Get avatar config with perks
        $stmt = $pdo->prepare("
            SELECT name, perk_data, day_restrictions 
            FROM avatar_config 
            WHERE avatar_id = ? AND is_active = 1
        ");
        $stmt->execute([$equipped_avatar]);
        $avatar = $stmt->fetch();
        
        if (!$avatar) {
            return ["perks" => [], "avatar_name" => "QR Ted"];
        }
        
        $perks = $avatar["perk_data"] ? json_decode($avatar["perk_data"], true) : [];
        $day_restrictions = $avatar["day_restrictions"] ? json_decode($avatar["day_restrictions"], true) : null;
        
        // Check day restrictions
        if ($day_restrictions && isset($day_restrictions["active_days"])) {
            $current_day = strtolower(date("l"));
            if (!in_array($current_day, $day_restrictions["active_days"])) {
                return [
                    "perks" => [], 
                    "avatar_name" => $avatar["name"],
                    "day_restricted" => true,
                    "restriction_info" => $day_restrictions["description"]
                ];
            }
        }
        
        return [
            "perks" => $perks ?: [],
            "avatar_name" => $avatar["name"],
            "day_restricted" => false
        ];
        
    } catch (Exception $e) {
        error_log("getActiveAvatarPerks error: " . $e->getMessage());
        return ["perks" => [], "avatar_name" => "QR Ted"];
    }
}

/**
 * Calculate vote earnings with avatar perks
 */
function calculateVoteEarningsWithPerks($user_id, $base_amount = 5, $bonus_amount = 0) {
    $perk_info = getActiveAvatarPerks($user_id);
    $perks = $perk_info["perks"];
    
    $enhanced_base = $base_amount;
    $enhanced_bonus = $bonus_amount;
    $perk_details = [];
    
    // Apply vote bonus
    if (isset($perks["vote_bonus"])) {
        $enhanced_base += $perks["vote_bonus"];
        $perk_details[] = "+{$perks["vote_bonus"]} vote bonus";
    }
    
    // Apply activity multiplier (Monday motivation)
    if (isset($perks["activity_multiplier"])) {
        $enhanced_base = round($enhanced_base * $perks["activity_multiplier"]);
        $enhanced_bonus = round($enhanced_bonus * $perks["activity_multiplier"]);
        $perk_details[] = "x{$perks["activity_multiplier"]} activity multiplier";
    }
    
    // Apply daily bonus multiplier
    if (isset($perks["daily_bonus_multiplier"]) && $bonus_amount > 0) {
        $enhanced_bonus = round($enhanced_bonus * $perks["daily_bonus_multiplier"]);
        $perk_details[] = "x{$perks["daily_bonus_multiplier"]} daily bonus";
    }
    
    // Apply weekend earnings multiplier
    if (isset($perks["weekend_earnings_multiplier"])) {
        $current_day = strtolower(date("l"));
        if (in_array($current_day, ["saturday", "sunday"])) {
            $enhanced_base = round($enhanced_base * $perks["weekend_earnings_multiplier"]);
            $enhanced_bonus = round($enhanced_bonus * $perks["weekend_earnings_multiplier"]);
            $perk_details[] = "x{$perks["weekend_earnings_multiplier"]} weekend multiplier";
        }
    }
    
    return [
        "base_amount" => $enhanced_base,
        "bonus_amount" => $enhanced_bonus,
        "total_amount" => $enhanced_base + $enhanced_bonus,
        "perk_details" => $perk_details,
        "avatar_name" => $perk_info["avatar_name"],
        "day_restricted" => $perk_info["day_restricted"] ?? false
    ];
}

/**
 * Calculate spin earnings with avatar perks
 */
function calculateSpinEarningsWithPerks($user_id, $base_amount = 15, $bonus_amount = 0, $prize_amount = 0) {
    $perk_info = getActiveAvatarPerks($user_id);
    $perks = $perk_info["perks"];
    
    $enhanced_base = $base_amount;
    $enhanced_bonus = $bonus_amount;
    $enhanced_prize = $prize_amount;
    $perk_details = [];
    
    // Apply spin bonus
    if (isset($perks["spin_bonus"])) {
        $enhanced_base += $perks["spin_bonus"];
        $perk_details[] = "+{$perks["spin_bonus"]} spin bonus";
    }
    
    // Apply prize multiplier
    if (isset($perks["spin_prize_multiplier"]) && $prize_amount > 0) {
        $enhanced_prize = round($enhanced_prize * $perks["spin_prize_multiplier"]);
        $perk_details[] = "x{$perks["spin_prize_multiplier"]} prize multiplier";
    }
    
    // Apply activity multiplier
    if (isset($perks["activity_multiplier"])) {
        $enhanced_base = round($enhanced_base * $perks["activity_multiplier"]);
        $enhanced_bonus = round($enhanced_bonus * $perks["activity_multiplier"]);
        $enhanced_prize = round($enhanced_prize * $perks["activity_multiplier"]);
        $perk_details[] = "x{$perks["activity_multiplier"]} activity multiplier";
    }
    
    // Apply weekend earnings multiplier
    if (isset($perks["weekend_earnings_multiplier"])) {
        $current_day = strtolower(date("l"));
        if (in_array($current_day, ["saturday", "sunday"])) {
            $enhanced_base = round($enhanced_base * $perks["weekend_earnings_multiplier"]);
            $enhanced_bonus = round($enhanced_bonus * $perks["weekend_earnings_multiplier"]);
            $perk_details[] = "x{$perks["weekend_earnings_multiplier"]} weekend multiplier";
        }
    }
    
    return [
        "base_amount" => $enhanced_base,
        "bonus_amount" => $enhanced_bonus,
        "prize_amount" => $enhanced_prize,
        "total_amount" => $enhanced_base + $enhanced_bonus + $enhanced_prize,
        "perk_details" => $perk_details,
        "avatar_name" => $perk_info["avatar_name"],
        "day_restricted" => $perk_info["day_restricted"] ?? false
    ];
}
