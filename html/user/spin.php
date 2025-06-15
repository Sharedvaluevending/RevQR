<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/functions.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';
require_once __DIR__ . '/../core/config_manager.php';

// Require user role
require_role('user');

// Get user's points and votes totals (using same comprehensive system as dashboard)
$user_id = $_SESSION['user_id']; // Use logged-in user ID instead of just IP

// Get QR Coin balance (NEW SYSTEM - same as dashboard)
$user_points = QRCoinManager::getBalance($user_id);

// Get comprehensive stats for other metrics (votes, spins, etc.)
$stats = getUserStats($user_id, get_client_ip());
$voting_stats = $stats['voting_stats'];
$spin_stats = $stats['spin_stats'];
$user_votes = $voting_stats['total_votes'];

// Get user data and avatar info
$user_data = $_SESSION['user_data'] ?? ['username' => 'User'];

// Get equipped avatar (same system as dashboard and profile)
$equipped_avatar_id = getUserEquippedAvatar();
$avatar_filename = getAvatarFilename($equipped_avatar_id);

$message = '';
$message_type = '';

// Check for try again message
if (isset($_GET['try_again']) && $_GET['try_again'] == '1') {
    $message = "😔 Try Again! You didn't win anything on your last spin, but it didn't count against your daily limit. You can spin again!";
    $message_type = "info";
}

// Check if user has already spun today
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM spin_results 
    WHERE user_id = ? AND DATE(spin_time) = CURDATE()
");
$stmt->execute([$user_id]);
$daily_spins_used = $stmt->fetchColumn();

// Check for active spin packs that give extra spins and update used packs
$extra_spins_available = 0;
$spin_pack_info = null;

// First, mark any fully used spin packs as 'used'
$stmt_check_used = $pdo->prepare("
    UPDATE user_qr_store_purchases uqsp
    JOIN qr_store_items qsi ON uqsp.qr_store_item_id = qsi.id
    SET uqsp.status = 'used'
    WHERE uqsp.user_id = ? 
    AND qsi.item_type = 'spin_pack' 
    AND uqsp.status = 'active'
    AND uqsp.created_at < CURDATE()
    AND (
        SELECT COUNT(*) 
        FROM spin_results sr 
        WHERE sr.user_id = uqsp.user_id 
        AND DATE(sr.spin_time) = DATE(uqsp.created_at)
    ) >= (1 + JSON_EXTRACT(qsi.item_data, '$.spins_per_day'))
");
$stmt_check_used->execute([$user_id]);

// Get the oldest active spin pack (FIFO - first purchased, first used)
$stmt_spin_packs = $pdo->prepare("
    SELECT uqsp.*, qsi.item_name, qsi.item_data
    FROM user_qr_store_purchases uqsp
    JOIN qr_store_items qsi ON uqsp.qr_store_item_id = qsi.id
    WHERE uqsp.user_id = ? 
    AND qsi.item_type = 'spin_pack' 
    AND uqsp.status = 'active'
    AND (uqsp.expires_at IS NULL OR uqsp.expires_at > NOW())
    ORDER BY uqsp.created_at ASC
    LIMIT 1
");
$stmt_spin_packs->execute([$user_id]);
$active_spin_pack = $stmt_spin_packs->fetch();

if ($active_spin_pack) {
    $pack_data = json_decode($active_spin_pack['item_data'], true);
    $pack_spins_per_day = $pack_data['spins_per_day'] ?? 0;
    
    // Check if this pack has been fully used since it was created
    $stmt_spins_since_pack = $pdo->prepare("
        SELECT COUNT(*) FROM spin_results 
        WHERE user_id = ? 
        AND spin_time >= ?
    ");
    $stmt_spins_since_pack->execute([$user_id, $active_spin_pack['created_at']]);
    $spins_used_since_pack_created = $stmt_spins_since_pack->fetchColumn();
    
    $total_spins_from_this_pack = 1 + $pack_spins_per_day; // base + pack spins
    
    if ($spins_used_since_pack_created >= $total_spins_from_this_pack) {
        // Mark this pack as used and look for next active pack
        $stmt_mark_used = $pdo->prepare("UPDATE user_qr_store_purchases SET status = 'used' WHERE id = ?");
        $stmt_mark_used->execute([$active_spin_pack['id']]);
        
        // Get next active pack
        $stmt_next_pack = $pdo->prepare("
            SELECT uqsp.*, qsi.item_name, qsi.item_data
            FROM user_qr_store_purchases uqsp
            JOIN qr_store_items qsi ON uqsp.qr_store_item_id = qsi.id
            WHERE uqsp.user_id = ? 
            AND qsi.item_type = 'spin_pack' 
            AND uqsp.status = 'active'
            AND (uqsp.expires_at IS NULL OR uqsp.expires_at > NOW())
            ORDER BY uqsp.created_at ASC
            LIMIT 1
        ");
        $stmt_next_pack->execute([$user_id]);
        $active_spin_pack = $stmt_next_pack->fetch();
    }
    
    if ($active_spin_pack) {
        $pack_data = json_decode($active_spin_pack['item_data'], true);
        $extra_spins_available = $pack_data['spins_per_day'] ?? 0;
        $spin_pack_info = [
            'name' => $active_spin_pack['item_name'],
            'spins_per_day' => $extra_spins_available,
            'expires_at' => $active_spin_pack['expires_at']
        ];
    }
}

// Calculate total spins available today
$total_spins_allowed = 1 + $extra_spins_available; // 1 base daily spin + extra spins from packs

// Calculate spins used specifically for the current active pack
if ($active_spin_pack) {
    // For spin packs, we should only count TODAY's spins, not all spins since pack creation
    // Daily spin packs reset each day during their duration
    $pack_data = json_decode($active_spin_pack['item_data'], true);
    $duration_days = $pack_data['duration_days'] ?? 7;
    
    // Check if pack is still within its duration
    $pack_start_date = date('Y-m-d', strtotime($active_spin_pack['created_at']));
    $pack_end_date = date('Y-m-d', strtotime($pack_start_date . " + {$duration_days} days"));
    $today = date('Y-m-d');
    
    if ($today > $pack_end_date) {
        // Pack has expired by duration, mark as used
        $stmt_expire_pack = $pdo->prepare("UPDATE user_qr_store_purchases SET status = 'used' WHERE id = ?");
        $stmt_expire_pack->execute([$active_spin_pack['id']]);
        
        // No active pack anymore
        $active_spin_pack = null;
        $extra_spins_available = 0;
        $total_spins_allowed = 1; // Just base daily spin
        $spins_remaining = max(0, 1 - $daily_spins_used);
    } else {
        // Pack is still active, count only TODAY's spins
        $spins_remaining = max(0, $total_spins_allowed - $daily_spins_used);
    }
} else {
    // No active pack, just use daily limit
    $spins_remaining = max(0, $total_spins_allowed - $daily_spins_used);
}

// DEBUG: Add detailed spin pack information for troubleshooting
$debug_info = [];
if ($active_spin_pack) {
    $pack_data = json_decode($active_spin_pack['item_data'], true);
    $duration_days = $pack_data['duration_days'] ?? 7;
    $pack_start_date = date('Y-m-d', strtotime($active_spin_pack['created_at']));
    $pack_end_date = date('Y-m-d', strtotime($pack_start_date . " + {$duration_days} days"));
    
    $debug_info = [
        'pack_name' => $active_spin_pack['item_name'],
        'pack_created' => $active_spin_pack['created_at'],
        'pack_expires' => $active_spin_pack['expires_at'],
        'pack_duration_days' => $duration_days,
        'pack_start_date' => $pack_start_date,
        'pack_end_date' => $pack_end_date,
        'today_date' => date('Y-m-d'),
        'pack_spins_per_day' => $pack_spins_per_day ?? 0,
        'base_daily_spins' => 1,
        'total_spins_allowed' => $total_spins_allowed,
        'daily_spins_used_today' => $daily_spins_used,
        'spins_remaining_calculated' => $spins_remaining,
        'pack_status' => $active_spin_pack['status'],
        'calculation_method' => 'daily_reset_logic'
    ];
}

// Check if user has QR Easybake avatar equipped and can use monthly super spin
$has_easybake_super_spin = false;
$current_month = date('Y-m');
if ($equipped_avatar_id == 15) { // QR Easybake avatar ID
    $stmt_super = $pdo->prepare("
        SELECT COUNT(*) FROM easybake_super_spins 
        WHERE user_id = ? AND spin_month = ?
    ");
    $stmt_super->execute([$user_id, $current_month]);
    $has_easybake_super_spin = ($stmt_super->fetchColumn() == 0);
}

// Determine if user can spin
if ($spins_remaining > 0) {
    $can_spin = true;
    if ($daily_spins_used > 0 && $extra_spins_available > 0) {
        $message = "Extra spin from your spin pack! " . $spins_remaining . " spins remaining today.";
        $message_type = "info";
    }
} elseif ($has_easybake_super_spin) {
    $message = "Daily spins used, but you can use your monthly QR Easybake super spin! (4×2×0 multiplier)";
    $message_type = "info";
    $can_spin = true;
} else {
    $message = "You've used all your spins for today! Come back tomorrow.";
    $message_type = "warning";
    $can_spin = false;
}

// Handle spin submission for the 8 specific prizes including Lord Pixel
$specific_rewards = [
    ['name' => 'Lord Pixel!', 'rarity_level' => 11, 'weight' => 1, 'special' => 'lord_pixel', 'points' => 0], // Ultra rare Lord Pixel avatar unlock + spin again
    ['name' => 'Try Again', 'rarity_level' => 2, 'weight' => 20, 'special' => 'spin_again', 'points' => 0],
    ['name' => 'Extra Vote', 'rarity_level' => 2, 'weight' => 15, 'points' => 0],
    ['name' => '50 QR Coins', 'rarity_level' => 3, 'weight' => 20, 'points' => 50],
    ['name' => '-20 QR Coins', 'rarity_level' => 5, 'weight' => 15, 'points' => -20],
    ['name' => '200 QR Coins', 'rarity_level' => 7, 'weight' => 12, 'points' => 200],
    ['name' => 'Lose All Votes', 'rarity_level' => 8, 'weight' => 10, 'points' => 0],
    ['name' => '500 QR Coins!', 'rarity_level' => 10, 'weight' => 7, 'points' => 500]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_spin) {
    // Check if this is a QR Easybake super spin
    $is_super_spin = false;
    $daily_spins_count = $pdo->prepare("SELECT COUNT(*) FROM spin_results WHERE user_id = ? AND DATE(spin_time) = CURDATE()");
    $daily_spins_count->execute([$user_id]);
    if ($daily_spins_count->fetchColumn() >= $total_spins_allowed && $has_easybake_super_spin) {
        $is_super_spin = true;
    }
    
    // Randomly select a reward based on weights
    $total_weight = array_sum(array_column($specific_rewards, 'weight'));
    $random = mt_rand(1, $total_weight);
    $current_weight = 0;
    $selected_reward = null;
    
    foreach ($specific_rewards as $reward) {
        $current_weight += $reward['weight'];
        if ($random <= $current_weight) {
            $selected_reward = $reward;
            break;
        }
    }
    
    if ($selected_reward) {
        $should_record_spin = true; // Flag to determine if we should record this spin
        
        // Apply QR Easybake super spin multiplier (4×2×0 = 420!) if applicable
        if ($is_super_spin) {
            // Special 4×2×0 multiplier that gives 420 points regardless of prize
            $selected_reward['points'] = 420; // 420 themed bonus for QR Easybake!
        }
        
        // Handle special cases first
        if (isset($selected_reward['special'])) {
            if ($selected_reward['special'] === 'lord_pixel') {
                // Unlock Lord Pixel avatar (avatar ID 9) in database
                try {
                    // Save avatar unlock to database
                    $stmt_unlock_avatar = $pdo->prepare("INSERT INTO user_avatars (user_id, avatar_id, unlocked_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE unlocked_at = NOW()");
                    $stmt_unlock_avatar->execute([$user_id, 9]);
                    
                    // Auto-equip Lord Pixel avatar
                    $_SESSION['equipped_avatar'] = 9;
                    $stmt_equip = $pdo->prepare("UPDATE users SET equipped_avatar = ? WHERE id = ?");
                    $stmt_equip->execute([9, $user_id]);
                    
                    // Set session flag for immediate display
                    $_SESSION['lord_pixel_unlocked'] = true;
                    
                    $message = "🎉 LEGENDARY WIN! You unlocked Lord Pixel avatar and can spin again! 🎉";
                    $message_type = "success";
                } catch (Exception $e) {
                    $message = "🎉 You won Lord Pixel! But there was an error unlocking it: " . $e->getMessage();
                    $message_type = "warning";
                    error_log("Lord Pixel unlock error: " . $e->getMessage());
                }
                // Don't set can_spin to false - allow another spin
            } elseif ($selected_reward['special'] === 'spin_again') {
                $message = "😔 Try Again! You didn't win anything, but this spin doesn't count against your daily limit. Spin again!";
                $message_type = "info";
                // This DOES NOT count as using your daily spin - you can try again!
                $should_record_spin = false; // Don't record this spin in the database
                $can_spin = true; // Keep ability to spin again
                
                // Force reload to refresh the page without consuming spin count
                header("Location: " . $_SERVER['PHP_SELF'] . "?try_again=1");
                exit;
            }
        } else {
            // Regular prizes
            $is_big_win = ($selected_reward['rarity_level'] >= 8) ? 1 : 0;
            
            // Handle "Extra Vote" prize
            if ($selected_reward['name'] === 'Extra Vote') {
                // Grant an additional vote for this week
                try {
                    $current_week = date('Y-W');
                    $stmt_vote = $pdo->prepare("
                        INSERT INTO user_weekly_vote_limits (user_id, week_year, votes_used, vote_limit) 
                        VALUES (?, ?, 0, 3) 
                        ON DUPLICATE KEY UPDATE vote_limit = vote_limit + 1
                    ");
                    $stmt_vote->execute([$user_id, $current_week]);
                    
                    $message = "🗳️ AWESOME! You earned an extra vote this week! You can now vote one additional time.";
                    $message_type = "success";
                } catch (Exception $e) {
                    // Fallback: If weekly limits table doesn't exist, just show message
                    $message = "🗳️ You won an Extra Vote! This would give you an additional vote this week.";
                    $message_type = "info";
                    error_log("Weekly vote limits not implemented for Extra Vote: " . $e->getMessage());
                }
            } elseif ($selected_reward['name'] === 'Lose All Votes') {
                // CHECK FOR VOTE PROTECTION FIRST
                $has_vote_protection = false;
                
                // Check if user has QR James (ID 2) or QR Terry (ID 7) equipped - both have vote protection
                if ($equipped_avatar_id == 2 || $equipped_avatar_id == 7) {
                    $has_vote_protection = true;
                }
                
                if ($has_vote_protection) {
                    // PROTECTED! Convert penalty to bonus
                    $avatar_name = ($equipped_avatar_id == 2) ? "QR James" : "QR Terry";
                    $message = "🛡️ PROTECTED! Your {$avatar_name} avatar saved you from losing all votes! You get -20 coins instead as compensation!";
                    $message_type = "success";
                    
                    // Don't reset votes, but still give the -20 coin "prize" as intended
                    // This maintains the prize structure while providing protection
                } else {
                    // NO PROTECTION - Apply full penalty
                    try {
                        // Insert or update weekly vote limit to max for current week (blocks further voting)
                        $current_week = date('Y-W');
                        $stmt_limit = $pdo->prepare("
                            INSERT INTO user_weekly_vote_limits (user_id, week_year, votes_used, vote_limit) 
                            VALUES (?, ?, 999, 2) 
                            ON DUPLICATE KEY UPDATE votes_used = 999
                        ");
                        $stmt_limit->execute([$user_id, $current_week]);
                        
                        $message = "💀 OH NO! You lost your weekly voting privileges! Your voting history is safe, but you can't vote again until next week.";
                        $message_type = "danger";
                    } catch (Exception $e) {
                        // Fallback: If weekly limits table doesn't exist, just show message without blocking
                        $message = "💀 You got the 'Lose All Votes' penalty! Better luck next time.";
                        $message_type = "warning";
                        error_log("Weekly vote limits not implemented: " . $e->getMessage());
                    }
                }
            } else {
                if ($is_super_spin) {
                    $message = "🔥 QR EASYBAKE SUPER SPIN! You won: " . $selected_reward['name'] . " + 420 bonus points! 🔥";
                    $message_type = "success";
                } else {
                    $message = "Congratulations! You won: " . $selected_reward['name'];
                    $message_type = $is_big_win ? "success" : "success";
                    if (strpos($selected_reward['name'], '-') !== false) {
                        $message_type = "warning";
                    }
                }
            }
            // Update can_spin status based on remaining spins
            $remaining_after_spin = $spins_remaining - 1;
            $can_spin = ($remaining_after_spin > 0); // Can spin again if there are spins left
        }
        
        // Only record the spin result if it should count against daily limit
        if ($should_record_spin) {
            $stmt = $pdo->prepare("
                INSERT INTO spin_results (user_id, user_ip, prize_won, is_big_win, prize_points, spin_time) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $is_big_win = ($selected_reward['rarity_level'] >= 8) ? 1 : 0;
            $prize_points = $selected_reward['points'] ?? 0;
            $stmt->execute([$user_id, get_client_ip(), $selected_reward['name'], $is_big_win, $prize_points]);
            $spin_result_id = $pdo->lastInsertId();
            
            // Award base spinning reward (every spin gets base QR coins)
            $economic_settings = ConfigManager::getEconomicSettings();
            $base_spin_amount = $economic_settings['qr_coin_spin_base'] ?? 15;
            $is_first_spin_today = ($daily_spins_used == 1); // First spin today gets bonus
            $bonus_amount = $is_first_spin_today ? ($economic_settings['qr_coin_spin_bonus'] ?? 50) : 0;
            $total_base_reward = $base_spin_amount + $bonus_amount;
            
            $base_description = $is_first_spin_today ? 
                "Daily spin reward + bonus: {$base_spin_amount} + {$bonus_amount} QR coins" :
                "Daily spin reward: {$base_spin_amount} QR coins";
                
            QRCoinManager::addTransaction(
                $user_id,
                'earning',
                'spinning',
                $total_base_reward,
                $base_description,
                [
                    'base_amount' => $base_spin_amount,
                    'bonus_amount' => $bonus_amount,
                    'daily_bonus' => $is_first_spin_today,
                    'spin_result_id' => $spin_result_id
                ],
                $spin_result_id,
                'spin'
            );
            
            // Award additional QR coins for prize (if any)
            if ($prize_points != 0) {
                $description = $is_super_spin ? 
                    "Spin prize: {$selected_reward['name']} (Super Spin)" : 
                    "Spin prize: {$selected_reward['name']}";
                    
                QRCoinManager::addTransaction(
                    $user_id,
                    'earning',
                    'spinning',
                    $prize_points,
                    $description,
                    [
                        'spin_result_id' => $spin_result_id,
                        'prize_name' => $selected_reward['name'],
                        'rarity_level' => $selected_reward['rarity_level'],
                        'is_super_spin' => $is_super_spin
                    ],
                    $spin_result_id,
                    'spin'
                );
            }
            
            // Record QR Easybake super spin usage
            if ($is_super_spin) {
                $stmt_super = $pdo->prepare("
                    INSERT INTO easybake_super_spins (user_id, spin_month, multiplier_used, prize_won, prize_points) 
                    VALUES (?, ?, '4x2x0', ?, ?)
                ");
                $stmt_super->execute([$user_id, $current_month, $selected_reward['name'], $prize_points]);
            }
        }
    }
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="row mb-4" style="margin-top: 20px;">
    <div class="col-12">
        <div class="d-flex align-items-center mb-3" style="min-height: 120px; padding: 15px;">
            <div class="me-4" style="min-width: 120px; max-width: 120px;">
                <img src="../assets/img/avatars/<?php echo $avatar_filename; ?>" 
                     alt="User Avatar"
                     class="img-fluid"
                     style="max-width: 100px; height: auto;"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                <i class="bi bi-person-circle text-primary" style="font-size: 6rem; display: none;"></i>
            </div>
            <div class="flex-grow-1">
                <h1 class="h3 mb-2"><i class="bi bi-trophy-fill text-warning me-2"></i>Spin to Win</h1>
                <p class="text-muted mb-0">Try your luck on the daily prize wheel, <?php echo htmlspecialchars($user_data['username']); ?>!</p>
            </div>
        </div>
    </div>
</div>

<!-- User Stats -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <img src="../img/qrCoin.png" alt="QR Coin" class="mb-2" style="width: 4rem; height: 4rem;">
                <h2 class="mb-0"><?php echo number_format($user_points); ?></h2>
                <p class="mb-0">Total QR Coins</p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <i class="bi bi-check2-all display-4 mb-2"></i>
                <h2 class="mb-0"><?php echo number_format($user_votes); ?></h2>
                <p class="mb-0">Total Votes</p>
            </div>
        </div>
    </div>
</div>

<!-- Spin Pack Info -->
<?php if ($spin_pack_info): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-success">
            <div class="card-header bg-success text-white">
                <h6 class="mb-0"><i class="bi bi-gift me-2"></i>Active Spin Pack</h6>
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h6 class="text-success mb-1"><?php echo htmlspecialchars($spin_pack_info['name']); ?></h6>
                        <p class="mb-1">
                            <strong>Spins Today:</strong> 
                            <span class="badge bg-primary"><?php echo $daily_spins_used; ?></span> used of 
                            <span class="badge bg-success"><?php echo $total_spins_allowed; ?></span> total
                        </p>
                        <p class="mb-0">
                            <strong>Remaining:</strong> 
                            <span class="badge bg-warning"><?php echo $spins_remaining; ?></span> spins left today
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <p class="mb-1"><strong>Expires:</strong></p>
                        <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($spin_pack_info['expires_at'])); ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<!-- No Active Spin Pack Info -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-info">
            <div class="card-body text-center">
                <h6 class="mb-1">
                    <strong>Daily Spins:</strong> 
                    <span class="badge bg-primary"><?php echo $daily_spins_used; ?></span> used of 
                    <span class="badge bg-success"><?php echo $total_spins_allowed; ?></span> total
                </h6>
                <p class="mb-0">
                    <strong>Remaining:</strong> 
                    <span class="badge bg-warning"><?php echo $spins_remaining; ?></span> spins left today
                </p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- DEBUG: Spin Pack Math Troubleshooting -->
<?php if (!empty($debug_info)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-warning bg-light">
            <div class="card-header bg-warning text-dark">
                <h6 class="mb-0"><i class="bi bi-bug me-2"></i>DEBUG: Spin Pack Math Check</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary">Pack Information:</h6>
                        <ul class="list-unstyled small">
                            <li><strong>Pack Name:</strong> <?php echo htmlspecialchars($debug_info['pack_name']); ?></li>
                            <li><strong>Pack Status:</strong> <span class="badge bg-info"><?php echo $debug_info['pack_status']; ?></span></li>
                            <li><strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($debug_info['pack_created'])); ?></li>
                            <li><strong>Duration:</strong> <?php echo $debug_info['pack_duration_days']; ?> days</li>
                            <li><strong>Start Date:</strong> <?php echo $debug_info['pack_start_date']; ?></li>
                            <li><strong>End Date:</strong> <?php echo $debug_info['pack_end_date']; ?></li>
                            <li><strong>Today:</strong> <?php echo $debug_info['today_date']; ?></li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-success">Spin Calculations:</h6>
                        <ul class="list-unstyled small">
                            <li><strong>Calculation Method:</strong> <span class="badge bg-success"><?php echo $debug_info['calculation_method']; ?></span></li>
                            <li><strong>Base Daily Spins:</strong> <span class="badge bg-secondary"><?php echo $debug_info['base_daily_spins']; ?></span></li>
                            <li><strong>Pack Spins/Day:</strong> <span class="badge bg-primary"><?php echo $debug_info['pack_spins_per_day']; ?></span></li>
                            <li><strong>Total Allowed Today:</strong> <span class="badge bg-success"><?php echo $debug_info['total_spins_allowed']; ?></span></li>
                            <li><strong>Used Today:</strong> <span class="badge bg-warning"><?php echo $debug_info['daily_spins_used_today']; ?></span></li>
                            <li><strong>Remaining Today:</strong> <span class="badge bg-info"><?php echo $debug_info['spins_remaining_calculated']; ?></span></li>
                        </ul>
                    </div>
                </div>
                <hr>
                <div class="alert alert-success mb-0">
                    <strong>✅ FIXED Math:</strong> Today's Allowed (<?php echo $debug_info['total_spins_allowed']; ?>) - Used Today (<?php echo $debug_info['daily_spins_used_today']; ?>) = Remaining (<?php echo $debug_info['spins_remaining_calculated']; ?>)
                    <br><small class="text-muted"><strong>Note:</strong> Spin packs now reset daily during their <?php echo $debug_info['pack_duration_days']; ?>-day duration period.</small>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
        <i class="bi bi-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'warning' ? 'exclamation-triangle' : 'info-circle'); ?> me-2"></i>
        <?php echo $message; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Enhanced Spin Wheel -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card gradient-card-primary shadow-lg">
            <div class="card-body text-center">
                <h5 class="mb-3"><i class="bi bi-trophy-fill text-warning me-2"></i>Daily Prize Wheel</h5>
                <div class="row align-items-center">
                    <div class="col-8">
                        <div id="wheel-container" class="spin-wheel-container mb-3 mx-auto" style="max-width:380px;">
                            <canvas id="prize-wheel" width="380" height="380"></canvas>
                        </div>
                    </div>
                    <div class="col-4">
                        <!-- Lord Pixel Display -->
                        <div class="card bg-dark text-white border-warning h-100">
                            <div class="card-body text-center p-2">
                                <div class="mb-2">
                                    <img src="../assets/img/avatars/qrLordPixel.png" alt="Lord Pixel" 
                                         class="img-fluid rounded-circle border border-warning" 
                                         style="width: 60px; height: 60px; box-shadow: 0 0 15px #FFD700;" 
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                    <i class="bi bi-person-badge-fill text-warning" style="font-size: 3rem; display: none;"></i>
                                </div>
                                <h6 class="text-warning mb-1">Lord Pixel</h6>
                                <p class="small text-white-50 mb-2">Ultra Rare Avatar</p>
                                <div class="badge bg-gradient text-white mb-2" style="background: linear-gradient(45deg, #8A2BE2, #FF1493, #FFD700) !important;">
                                    0.1% Chance
                                </div>
                                <div class="small text-info">
                                    <i class="bi bi-lightning-charge me-1"></i>
                                    <strong>Perk:</strong><br>
                                    Pixel master powers<br>
                                    + Extra spin chance
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="spin-message" class="mb-3"></div>
                
                <?php if ($can_spin): ?>
                    <?php if ($has_easybake_super_spin && $equipped_avatar_id == 15): ?>
                        <div class="alert alert-warning mb-3">
                            <i class="bi bi-lightning-charge-fill me-2"></i>
                            <strong>QR Easybake Super Spin Available!</strong><br>
                            <small>Monthly 4×2×0 multiplier ready - guarantees 420 bonus points!</small>
                        </div>
                    <?php endif; ?>
                    <form method="POST" id="spin-form">
                        <button type="submit" class="btn btn-warning btn-lg spin-btn">
                            <i class="bi bi-trophy me-2"></i>
                            <?php echo ($has_easybake_super_spin && $equipped_avatar_id == 15) ? 'Super Spin Now!' : 'Spin Now'; ?>
                        </button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle me-2"></i>You can spin again tomorrow!
                    </div>
                <?php endif; ?>
                <div class="mt-2">
                    <small class="text-muted">One spin per day • Prizes are based on rarity</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header bg-dark text-white">
                <h6 class="mb-0"><i class="bi bi-list-stars me-2"></i>Prize Rarity & Odds</h6>
            </div>
            <div class="card-body">
                <div class="rarity-legend">
                    <div class="rarity-item d-flex align-items-center justify-content-between mb-3 p-2 rounded" style="background: linear-gradient(90deg, #8A2BE2, #FF1493, #FFD700);">
                        <div class="d-flex align-items-center">
                            <img src="../assets/img/avatars/qrLordPixel.png" alt="Lord Pixel" class="me-2" style="width: 20px; height: 20px; border-radius: 50%;" onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-block';">
                            <i class="bi bi-person-badge-fill text-white me-2" style="font-size: 1.2em; display: none;"></i>
                            <div>
                                <div class="text-white fw-bold">Lord Pixel Avatar!</div>
                                <div class="text-white-50 small">Ultra rare avatar unlock + spin again</div>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="text-warning">
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                            </div>
                            <small class="text-white-50">0.1% chance</small>
                        </div>
                    </div>

                    <div class="rarity-item d-flex align-items-center justify-content-between mb-3 p-2 rounded" style="background: linear-gradient(90deg, #28a745, #20c997);">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-arrow-clockwise text-white me-2" style="font-size: 1.2em;"></i>
                            <div>
                                <div class="text-white fw-bold">Try Again</div>
                                <div class="text-white-50 small">Get another spin</div>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="text-warning">
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                            </div>
                            <small class="text-white-50">20% chance</small>
                        </div>
                    </div>
                    
                    <div class="rarity-item d-flex align-items-center justify-content-between mb-3 p-2 rounded" style="background: linear-gradient(90deg, #17a2b8, #20c997);">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-plus-circle text-white me-2" style="font-size: 1.2em;"></i>
                            <div>
                                <div class="text-white fw-bold">Extra Vote</div>
                                <div class="text-white-50 small">Additional vote this week</div>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="text-warning">
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                            </div>
                            <small class="text-white-50">15% chance</small>
                        </div>
                    </div>
                    
                    <div class="rarity-item d-flex align-items-center justify-content-between mb-3 p-2 rounded" style="background: linear-gradient(90deg, #007bff, #6f42c1);">
                        <div class="d-flex align-items-center">
                            <img src="../img/qrCoin.png" alt="QR Coin" class="me-2" style="width: 1.2em; height: 1.2em; filter: brightness(0) invert(1);">
                            <div>
                                <div class="text-white fw-bold">50 QR Coins</div>
                                <div class="text-white-50 small">Earn reward coins</div>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="text-warning">
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                            </div>
                            <small class="text-white-50">20% chance</small>
                        </div>
                    </div>
                    
                    <div class="rarity-item d-flex align-items-center justify-content-between mb-3 p-2 rounded" style="background: linear-gradient(90deg, #ffc107, #fd7e14);">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-dash-circle text-white me-2" style="font-size: 1.2em;"></i>
                            <div>
                                <div class="text-white fw-bold">-20 QR Coins</div>
                                <div class="text-white-50 small">Lose reward coins</div>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="text-warning">
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                            </div>
                            <small class="text-white-50">15% chance</small>
                        </div>
                    </div>
                    
                    <div class="rarity-item d-flex align-items-center justify-content-between mb-3 p-2 rounded" style="background: linear-gradient(90deg, #dc3545, #e83e8c);">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-gem text-white me-2" style="font-size: 1.2em;"></i>
                            <div>
                                <div class="text-white fw-bold">200 QR Coins</div>
                                <div class="text-white-50 small">Big coin reward</div>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="text-warning">
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                            </div>
                            <small class="text-white-50">12% chance</small>
                        </div>
                    </div>
                    
                    <div class="rarity-item d-flex align-items-center justify-content-between mb-3 p-2 rounded" style="background: linear-gradient(90deg, #343a40, #6c757d);">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-x-octagon text-danger me-2" style="font-size: 1.2em;"></i>
                            <div>
                                <div class="text-white fw-bold">Lose All Votes</div>
                                <div class="text-white-50 small">Weekly votes reset</div>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="text-danger">
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                            </div>
                            <small class="text-white-50">10% chance</small>
                        </div>
                    </div>
                    
                    <div class="rarity-item d-flex align-items-center justify-content-between p-2 rounded" style="background: linear-gradient(90deg, #fd7e14, #ffd700, #ff6b6b);">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-trophy-fill text-white me-2" style="font-size: 1.2em;"></i>
                            <div>
                                <div class="text-white fw-bold">500 QR Coins!</div>
                                <div class="text-white-50 small">JACKPOT PRIZE!</div>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="text-warning">
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                            </div>
                            <small class="text-white-50">7% chance</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Enhanced Prize Wheel Logic
(function() {
    // Define the 8 specific prizes with gradient colors including Lord Pixel
    const rewards = [
        { name: 'Lord Pixel!', rarity_level: 11, colors: ['#8A2BE2', '#FF1493', '#FFD700'], special: 'lord_pixel' }, // Ultra rare Lord Pixel
        { name: 'Try Again', rarity_level: 2, colors: ['#28a745', '#20c997'], special: 'spin_again' },
        { name: 'Extra Vote', rarity_level: 2, colors: ['#17a2b8', '#20c997'] },
        { name: '50 QR Coins', rarity_level: 3, colors: ['#007bff', '#6f42c1'] },
        { name: '-20 QR Coins', rarity_level: 5, colors: ['#ffc107', '#fd7e14'] },
        { name: '200 QR Coins', rarity_level: 7, colors: ['#dc3545', '#e83e8c'] },
        { name: 'Lose All Votes', rarity_level: 8, colors: ['#343a40', '#6c757d'] },
        { name: '500 QR Coins!', rarity_level: 10, colors: ['#fd7e14', '#ffd700', '#ff6b6b'] }
    ];
    
    const canvas = document.getElementById('prize-wheel');
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    
    // Mobile responsive canvas sizing
    function setupCanvasSize() {
        const container = document.getElementById('wheel-container');
        const containerWidth = container.offsetWidth;
        let canvasSize;
        
        if (window.innerWidth <= 576) {
            canvasSize = Math.min(280, containerWidth - 20);
        } else if (window.innerWidth <= 768) {
            canvasSize = Math.min(340, containerWidth - 20);
        } else {
            canvasSize = Math.min(380, containerWidth - 20);
        }
        
        canvas.width = canvasSize;
        canvas.height = canvasSize;
        canvas.style.width = canvasSize + 'px';
        canvas.style.height = canvasSize + 'px';
        
        return {
            centerX: canvasSize / 2,
            centerY: canvasSize / 2,
            radius: (canvasSize / 2) - 25
        };
    }
    
    let dimensions = setupCanvasSize();
    let { centerX, centerY, radius } = dimensions;
    let rotation = 0, spinning = false;
    
    // Responsive font sizing
    function getResponsiveFontSize(baseFontSize) {
        const scaleFactor = canvas.width / 380;
        return Math.max(10, Math.floor(baseFontSize * scaleFactor));
    }
    
    // Create gradient
    function createGradient(colors, startAngle, endAngle) {
        const gradientAngle = startAngle + (endAngle - startAngle) / 2;
        const x1 = centerX + Math.cos(gradientAngle) * radius * 0.3;
        const y1 = centerY + Math.sin(gradientAngle) * radius * 0.3;
        const x2 = centerX + Math.cos(gradientAngle) * radius * 0.9;
        const y2 = centerY + Math.sin(gradientAngle) * radius * 0.9;
        
        const gradient = ctx.createLinearGradient(x1, y1, x2, y2);
        colors.forEach((color, index) => {
            gradient.addColorStop(index / (colors.length - 1), color);
        });
        return gradient;
    }
    
    function drawWheel() {
        const sliceAngle = (2 * Math.PI) / rewards.length;
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        // Draw outer rim gradient
        const rimGradient = ctx.createRadialGradient(centerX, centerY, radius - 15, centerX, centerY, radius + 10);
        rimGradient.addColorStop(0, 'rgba(255, 215, 0, 0.8)'); // Gold inner
        rimGradient.addColorStop(0.5, 'rgba(255, 69, 0, 0.9)'); // Orange red middle
        rimGradient.addColorStop(1, 'rgba(139, 0, 139, 1)'); // Dark magenta outer
        
        ctx.beginPath();
        ctx.arc(centerX, centerY, radius + 8, 0, 2 * Math.PI);
        ctx.fillStyle = rimGradient;
        ctx.fill();
        
        rewards.forEach((reward, index) => {
            const startAngle = index * sliceAngle + rotation;
            const endAngle = startAngle + sliceAngle;
            
            // Draw slice with gradient
            ctx.beginPath();
            ctx.moveTo(centerX, centerY);
            ctx.arc(centerX, centerY, radius, startAngle, endAngle);
            ctx.closePath();
            
            // Create and apply gradient
            const gradient = createGradient(reward.colors, startAngle, endAngle);
            ctx.fillStyle = gradient;
            ctx.fill();
            
            // Black border
            ctx.strokeStyle = '#000';
            ctx.lineWidth = Math.max(2, 4 * (canvas.width / 380));
            ctx.stroke();
            
            // Draw text with better styling or QR ED avatar
            ctx.save();
            ctx.translate(centerX, centerY);
            ctx.rotate(startAngle + sliceAngle / 2);
            
            if (reward.special === 'lord_pixel') {
                // Draw Lord Pixel with special golden text and larger font
                ctx.textAlign = 'right';
                ctx.fillStyle = '#FFD700'; // Gold text for Lord Pixel
                ctx.font = `bold ${getResponsiveFontSize(16)}px Arial`;
                ctx.shadowColor = '#000';
                ctx.shadowBlur = 5;
                ctx.shadowOffsetX = 2;
                ctx.shadowOffsetY = 2;
                
                const textRadius = radius - (15 * (canvas.width / 380));
                
                // Draw "Lord Pixel!" in larger, golden text
                ctx.fillText('Lord Pixel!', textRadius, 0);
                
                // Add sparkle effect with smaller text
                ctx.font = `bold ${getResponsiveFontSize(10)}px Arial`;
                ctx.fillStyle = '#FFFFFF';
                ctx.fillText('✨ULTRA RARE✨', textRadius, 15);
            } else {
                // Regular text rendering for other prizes
                ctx.textAlign = 'right';
                ctx.fillStyle = '#fff';
                ctx.font = `bold ${getResponsiveFontSize(14)}px Arial`;
                ctx.shadowColor = '#000';
                ctx.shadowBlur = 3;
                ctx.shadowOffsetX = 1;
                ctx.shadowOffsetY = 1;
                
                const textRadius = radius - (18 * (canvas.width / 380));
                
                // Split text for better fit
                const words = reward.name.split(' ');
                if (words.length > 1) {
                    ctx.fillText(words[0], textRadius, -5);
                    ctx.fillText(words.slice(1).join(' '), textRadius, 12);
                } else {
                    ctx.fillText(reward.name, textRadius, 3);
                }
            }
            
            ctx.restore();
        });
        
        // Center hub with gradient
        const centerRadius = 18 * (canvas.width / 380);
        const centerGradient = ctx.createRadialGradient(centerX, centerY, 0, centerX, centerY, centerRadius);
        centerGradient.addColorStop(0, '#fff');
        centerGradient.addColorStop(1, '#ddd');
        
        ctx.beginPath();
        ctx.arc(centerX, centerY, centerRadius, 0, 2 * Math.PI);
        ctx.fillStyle = centerGradient;
        ctx.fill();
        ctx.strokeStyle = '#000';
        ctx.lineWidth = Math.max(2, 4 * (canvas.width / 380));
        ctx.stroke();
        
        // Draw enhanced pointer
        const pointerSize = 15 * (canvas.width / 380);
        const pointerGradient = ctx.createLinearGradient(centerX + radius - pointerSize, centerY, centerX + radius + pointerSize, centerY);
        pointerGradient.addColorStop(0, '#333');
        pointerGradient.addColorStop(1, '#000');
        
        ctx.beginPath();
        ctx.moveTo(centerX + radius - pointerSize, centerY);
        ctx.lineTo(centerX + radius + pointerSize, centerY - pointerSize);
        ctx.lineTo(centerX + radius + pointerSize, centerY + pointerSize);
        ctx.closePath();
        ctx.fillStyle = pointerGradient;
        ctx.fill();
        ctx.strokeStyle = '#000';
        ctx.lineWidth = 2;
        ctx.stroke();
    }
    
    // Handle window resize
    window.addEventListener('resize', function() {
        dimensions = setupCanvasSize();
        centerX = dimensions.centerX;
        centerY = dimensions.centerY;
        radius = dimensions.radius;
        drawWheel();
    });
    
    drawWheel();

    // Handle spin form submission
    const canSpin = <?php echo $can_spin ? 'true' : 'false'; ?>;
    const spinBtn = document.querySelector('.spin-btn');
    const msgDiv = document.getElementById('spin-message');
    
    if (document.getElementById('spin-form')) {
        document.getElementById('spin-form').addEventListener('submit', function(e) {
            e.preventDefault();
            if (!canSpin || spinning) return;
            spinning = true;
            spinBtn.disabled = true;
            
            // Animate spin
            const spinDuration = 5000;
            const startTime = Date.now();
            const startRotation = rotation;
            const spinAngle = 10 + Math.random() * 5;
            
            function animate() {
                const elapsed = Date.now() - startTime;
                const progress = Math.min(elapsed / spinDuration, 1);
                const easeOut = t => 1 - Math.pow(1 - t, 3);
                rotation = startRotation + (spinAngle * 2 * Math.PI * easeOut(progress));
                drawWheel();
                if (progress < 1) {
                    requestAnimationFrame(animate);
                } else {
                    spinning = false;
                    // Submit form to actually spin (server-side)
                    e.target.submit();
                }
            }
            animate();
        });
    }
})();
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 