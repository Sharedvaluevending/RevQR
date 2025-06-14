<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/functions.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';
require_once __DIR__ . '/../core/store_manager.php';
// Simple weekly vote limit system (2 votes per week)
$user_id = $_SESSION['user_id'] ?? null;
$voter_ip = $_SERVER['REMOTE_ADDR'];

// Get user's weekly vote count
$weekly_votes_used = 0;
$weekly_vote_limit = 2;

if ($user_id) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as weekly_votes
        FROM votes 
        WHERE user_id = ? 
        AND YEARWEEK(created_at, 1) = YEARWEEK(NOW(), 1)
    ");
    $stmt->execute([$user_id]);
    $weekly_votes_used = (int) $stmt->fetchColumn();
} else {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as weekly_votes
        FROM votes 
        WHERE voter_ip = ? 
        AND user_id IS NULL
        AND YEARWEEK(created_at, 1) = YEARWEEK(NOW(), 1)
    ");
    $stmt->execute([$voter_ip]);
    $weekly_votes_used = (int) $stmt->fetchColumn();
}

$votes_remaining = max(0, $weekly_vote_limit - $weekly_votes_used);

// Simple vote status for display
$vote_status = [
    'votes_used' => $weekly_votes_used,
    'votes_remaining' => $votes_remaining,
    'weekly_limit' => $weekly_vote_limit,
    'qr_balance' => QRCoinManager::getBalance($_SESSION['user_id'])
];

// Require user role
require_role('user');

// Get user data including username
$stmt = $pdo->prepare("SELECT username, role, created_at FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_data = $stmt->fetch();

// Get equipped avatar filename
$equipped_avatar_id = getUserEquippedAvatar();
$avatar_filename = getAvatarFilename($equipped_avatar_id);

// Get QR Coin Economy Data (using user_points for consistency across pages)
$user_points = QRCoinManager::getBalance($_SESSION['user_id']);
$qr_spending_data = QRCoinManager::getSpendingSummary($_SESSION['user_id'], 'all');
$store_enabled = ConfigManager::get('qr_store_enabled', false);
$business_store_enabled = ConfigManager::get('business_store_enabled', false);

// Calculate spending statistics from grouped data
$qr_stats = [
    'total_spent' => 0,
    'earned_today' => 0
];

// Sum up total spending from all categories
foreach ($qr_spending_data as $category_data) {
    if ($category_data['transaction_type'] === 'spending') {
        $qr_stats['total_spent'] += $category_data['total_amount'];
    }
}

// Get today's earnings
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(amount), 0) as earned_today
    FROM qr_coin_transactions 
    WHERE user_id = ? AND transaction_type = 'earning' AND DATE(created_at) = CURDATE()
");
$stmt->execute([$_SESSION['user_id']]);
$qr_stats['earned_today'] = $stmt->fetchColumn();

// Get user's discount savings (both QR coins and CAD value)
$savings_data = [
    'total_qr_coins_used' => 0,
    'total_savings_cad' => 0.00,
    'total_purchases' => 0,
    'redeemed_savings_cad' => 0.00,
    'pending_savings_cad' => 0.00
];

try {
    // Get data from business_purchases table
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(bp.qr_coins_spent), 0) as total_qr_coins,
            COALESCE(SUM((bsi.regular_price_cents * bp.discount_percentage / 100)), 0) as total_savings_cents,
            COUNT(*) as total_purchases,
            COALESCE(SUM(CASE WHEN bp.status = 'redeemed' THEN (bsi.regular_price_cents * bp.discount_percentage / 100) ELSE 0 END), 0) as redeemed_savings_cents,
            COALESCE(SUM(CASE WHEN bp.status = 'pending' THEN (bsi.regular_price_cents * bp.discount_percentage / 100) ELSE 0 END), 0) as pending_savings_cents
        FROM business_purchases bp
        JOIN business_store_items bsi ON bp.business_store_item_id = bsi.id
        WHERE bp.user_id = ? AND bp.status != 'expired'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $business_savings = $stmt->fetch();
    
    if ($business_savings && $business_savings['total_purchases'] > 0) {
        $savings_data['total_qr_coins_used'] += $business_savings['total_qr_coins'];
        $savings_data['total_savings_cad'] += $business_savings['total_savings_cents'] / 100;
        $savings_data['total_purchases'] += $business_savings['total_purchases'];
        $savings_data['redeemed_savings_cad'] += $business_savings['redeemed_savings_cents'] / 100;
        $savings_data['pending_savings_cad'] += $business_savings['pending_savings_cents'] / 100;
    }
    
    // Get data from user_store_purchases table (newer format)
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(qr_coins_spent), 0) as total_qr_coins,
            COALESCE(SUM(discount_amount_cents), 0) as total_savings_cents,
            COUNT(*) as total_purchases,
            COALESCE(SUM(CASE WHEN status = 'redeemed' THEN discount_amount_cents ELSE 0 END), 0) as redeemed_savings_cents,
            COALESCE(SUM(CASE WHEN status = 'pending' THEN discount_amount_cents ELSE 0 END), 0) as pending_savings_cents
        FROM user_store_purchases
        WHERE user_id = ? AND status != 'expired'
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $store_savings = $stmt->fetch();
    
    if ($store_savings && $store_savings['total_purchases'] > 0) {
        $savings_data['total_qr_coins_used'] += $store_savings['total_qr_coins'];
        $savings_data['total_savings_cad'] += $store_savings['total_savings_cents'] / 100;
        $savings_data['total_purchases'] += $store_savings['total_purchases'];
        $savings_data['redeemed_savings_cad'] += $store_savings['redeemed_savings_cents'] / 100;
        $savings_data['pending_savings_cad'] += $store_savings['pending_savings_cents'] / 100;
    }
    
} catch (Exception $e) {
    error_log("Error calculating user savings: " . $e->getMessage());
    // Keep default values
}

// Get recent QR coin transactions
$recent_qr_transactions = QRCoinManager::getTransactionHistory($_SESSION['user_id'], 5);

// Get available store items count for user
$available_business_stores = 0;
$available_qr_items = 0;
if ($business_store_enabled) {
    $business_items = StoreManager::getAllBusinessStoreItems();
    $available_business_stores = count($business_items);
}
if ($store_enabled) {
    $qr_items = StoreManager::getQRStoreItems();
    $available_qr_items = count($qr_items);
}

// Get comprehensive stats for other metrics (votes, spins, days)  
$stats = getUserStats($_SESSION['user_id'], get_client_ip());
$voting_stats = $stats['voting_stats'];
$spin_stats = $stats['spin_stats'];
// Note: user_points is already fetched from QRCoinManager above

// Calculate user level using QR coin balance (consistent with new system)
$level_data = calculateUserLevel($voting_stats['total_votes'], $user_points, $voting_stats['voting_days'], $spin_stats['spin_days'], $_SESSION['user_id']);
$user_level = $level_data['level'];
$level_progress = $level_data['progress'];
$points_to_next = $level_data['points_to_next'];

// Get user's rank compared to others - fixed calculation
$user_total_activity = $voting_stats['total_votes'] + $spin_stats['total_spins'];
$stmt = $pdo->prepare("
    SELECT COUNT(*) as rank_position
    FROM (
        SELECT voter_ip, COUNT(*) as total_activity
        FROM (
            SELECT voter_ip FROM votes WHERE voter_ip IS NOT NULL
            UNION ALL 
            SELECT user_ip as voter_ip FROM spin_results WHERE user_ip IS NOT NULL
        ) as all_activity 
        GROUP BY voter_ip
        HAVING total_activity > ?
    ) as higher_users
");
$stmt->execute([$user_total_activity]);
$user_rank = $stmt->fetchColumn() + 1;

// Get today's activity for streak calculation
$stmt = $pdo->prepare("SELECT COUNT(*) FROM votes WHERE (user_id = ? OR voter_ip = ?) AND DATE(created_at) = CURDATE()");
$stmt->execute([$_SESSION['user_id'], get_client_ip()]);
$votes_today = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM spin_results WHERE (user_id = ? OR user_ip = ?) AND DATE(spin_time) = CURDATE()");
$stmt->execute([$_SESSION['user_id'], get_client_ip()]);
$spins_today = $stmt->fetchColumn();

// Get activity streak calculation
$stmt = $pdo->prepare("
    SELECT DATE(created_at) as activity_date FROM votes WHERE user_id = ? OR voter_ip = ?
    UNION 
    SELECT DATE(spin_time) as activity_date FROM spin_results WHERE user_id = ? OR user_ip = ?
    ORDER BY activity_date DESC
");
$stmt->execute([$_SESSION['user_id'], get_client_ip(), $_SESSION['user_id'], get_client_ip()]);
$activity_dates = $stmt->fetchAll(PDO::FETCH_COLUMN);

$current_streak = 0;
$today = new DateTime();
if (!empty($activity_dates)) {
    foreach ($activity_dates as $i => $date) {
        $activity_date = new DateTime($date);
        $diff = $today->diff($activity_date)->days;
        
        if ($i === 0 && $diff <= 1) {
            $current_streak = 1;
        } elseif ($i > 0) {
            $prev_date = new DateTime($activity_dates[$i-1]);
            $date_diff = $prev_date->diff($activity_date)->days;
            if ($date_diff === 1) {
                $current_streak++;
            } else {
                break;
            }
        }
    }
}

// Pagination for Recent Activity
$items_per_page = 5;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $items_per_page;

// Get total count of activities for pagination
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM (
        SELECT spin_time as created_at 
        FROM spin_results 
        WHERE user_id = ? OR user_ip = ? 
        UNION ALL 
        SELECT v.created_at as created_at 
        FROM votes v 
        WHERE v.user_id = ? OR v.voter_ip = ? 
    ) as all_activities
");
$stmt->execute([$_SESSION['user_id'], get_client_ip(), $_SESSION['user_id'], get_client_ip()]);
$total_activities = $stmt->fetchColumn();
$total_pages = ceil($total_activities / $items_per_page);

// Get user's recent activity for Recent Activity section with pagination
$stmt = $pdo->prepare("
    SELECT 'spin' as type, prize_won as achievement, spin_time as created_at 
    FROM spin_results 
    WHERE user_id = ? OR user_ip = ? 
    UNION ALL 
    SELECT 'vote' as type, CONCAT('Voted for ', i.name) as achievement, v.created_at as created_at 
    FROM votes v 
    JOIN items i ON v.item_id = i.id 
    WHERE v.user_id = ? OR v.voter_ip = ? 
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->execute([$_SESSION['user_id'], get_client_ip(), $_SESSION['user_id'], get_client_ip(), $items_per_page, $offset]);
$recent_activity = $stmt->fetchAll();

// Get recent achievements/highlights
$recent_achievements = [];

// Check for voting milestones
if ($voting_stats['total_votes'] >= 100) {
    $recent_achievements[] = ['type' => 'milestone', 'title' => 'Voting Champion', 'desc' => '100+ votes cast', 'icon' => 'trophy-fill', 'color' => 'warning'];
} elseif ($voting_stats['total_votes'] >= 50) {
    $recent_achievements[] = ['type' => 'milestone', 'title' => 'Active Voter', 'desc' => '50+ votes cast', 'icon' => 'award-fill', 'color' => 'success'];
} elseif ($voting_stats['total_votes'] >= 10) {
    $recent_achievements[] = ['type' => 'milestone', 'title' => 'Getting Started', 'desc' => '10+ votes cast', 'icon' => 'check-circle-fill', 'color' => 'primary'];
}

// Check for spin achievements
if ($spin_stats['total_spins'] >= 30) {
    $recent_achievements[] = ['type' => 'milestone', 'title' => 'Spin Master', 'desc' => '30+ spins completed', 'icon' => 'stars', 'color' => 'warning'];
} elseif ($spin_stats['total_spins'] >= 10) {
    $recent_achievements[] = ['type' => 'milestone', 'title' => 'Lucky Player', 'desc' => '10+ spins completed', 'icon' => 'dice-6-fill', 'color' => 'info'];
}

// Check for streak achievements
if ($current_streak >= 7) {
    $recent_achievements[] = ['type' => 'streak', 'title' => 'Weekly Warrior', 'desc' => '7+ day streak', 'icon' => 'fire', 'color' => 'danger'];
} elseif ($current_streak >= 3) {
    $recent_achievements[] = ['type' => 'streak', 'title' => 'Consistent Player', 'desc' => '3+ day streak', 'icon' => 'calendar-check', 'color' => 'success'];
}

// ENHANCED: Get QR Coin earning breakdown for analytics
$stmt = $pdo->prepare("
    SELECT 
        transaction_type,
        category,
        COUNT(*) as transaction_count,
        SUM(amount) as total_amount,
        AVG(amount) as avg_amount
    FROM qr_coin_transactions 
    WHERE user_id = ? 
    GROUP BY transaction_type, category
    ORDER BY total_amount DESC
");
$stmt->execute([$_SESSION['user_id']]);
$coin_analytics = $stmt->fetchAll();

// Process coin analytics for dashboard
$total_earned = 0;
$total_spent = 0;
$earning_breakdown = [];
$spending_breakdown = [];

foreach ($coin_analytics as $transaction) {
    if ($transaction['transaction_type'] === 'earning') {
        $total_earned += $transaction['total_amount'];
        $earning_breakdown[$transaction['category']] = $transaction;
    } elseif ($transaction['transaction_type'] === 'spending') {
        $total_spent += abs($transaction['total_amount']);
        $spending_breakdown[$transaction['category']] = $transaction;
    }
}

// Get user's store purchase analytics for dashboard
$stmt = $pdo->prepare("
    SELECT 
        p.qr_coins_spent,
        p.quantity,
        p.status,
        p.created_at,
        s.item_name,
        s.item_type as category,
        s.qr_coin_cost as item_price
    FROM user_qr_store_purchases p
    JOIN qr_store_items s ON p.qr_store_item_id = s.id
    WHERE p.user_id = ?
    ORDER BY p.created_at DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$store_purchases = $stmt->fetchAll();

// Calculate store analytics for dashboard
$total_store_purchases = count($store_purchases);
$total_store_spent = array_sum(array_column($store_purchases, 'qr_coins_spent'));

// ENHANCED: Cross-reference with business engagement for dashboard
$stmt = $pdo->prepare("
    SELECT 
        b.name as business_name,
        b.id as business_id,
        COUNT(DISTINCT v.id) as votes_cast,
        COUNT(DISTINCT m.id) as machines_voted_on,
        COUNT(DISTINCT i.id) as items_influenced,
        MAX(v.created_at) as last_interaction,
        AVG(i.price) as avg_item_price
    FROM votes v
    JOIN items i ON v.item_id = i.id
    JOIN machines m ON i.machine_id = m.id
    JOIN businesses b ON m.business_id = b.id
    WHERE v.user_id = ? OR v.voter_ip = ?
    GROUP BY b.id, b.name
    ORDER BY votes_cast DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id'], get_client_ip()]);
$business_engagement = $stmt->fetchAll();

// Get quick insights for engagement
$insights = [];

if ($voting_stats['total_votes'] > 0) {
    $vote_in_percentage = round(($voting_stats['votes_in'] / $voting_stats['total_votes']) * 100);
    if ($vote_in_percentage > 70) {
        $insights[] = "You're optimistic! {$vote_in_percentage}% of your votes are for adding items.";
    } elseif ($vote_in_percentage < 30) {
        $insights[] = "You're selective! {$vote_in_percentage}% of your votes are for adding items.";
    }
}

if ($user_rank <= 10) {
    $insights[] = "Amazing! You're in the top 10 most active users.";
} elseif ($user_rank <= 50) {
    $insights[] = "Great job! You're in the top 50 most active users.";
}

if ($current_streak >= 5) {
    $insights[] = "You're on fire! {$current_streak} days of consistent activity.";
}

// ADD PREDICTIVE INSIGHTS
$predictive_insights = [];

// Calculate level prediction
if ($voting_stats['total_votes'] > 0 || $spin_stats['total_spins'] > 0) {
    // Calculate daily average activity over last 7 days
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as recent_activity_count
        FROM (
            SELECT created_at FROM votes WHERE (user_id = ? OR voter_ip = ?) AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            UNION ALL
            SELECT spin_time FROM spin_results WHERE (user_id = ? OR user_ip = ?) AND spin_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ) as activity
    ");
    $stmt->execute([$_SESSION['user_id'], get_client_ip(), $_SESSION['user_id'], get_client_ip()]);
    $recent_activity_count = $stmt->fetchColumn();
    
    $daily_average = $recent_activity_count / 7;
    $daily_coins = ($daily_average * 17.5); // Average of vote (10) + spin (25)
    
    if ($daily_coins > 0) {
        $days_to_next_level = ceil($points_to_next / $daily_coins);
        if ($days_to_next_level <= 30) {
            $predictive_insights[] = "At your pace, Level " . ($user_level + 1) . " in {$days_to_next_level} days!";
        }
        
        $monthly_prediction = $daily_coins * 30;
        $current_month_coins = $user_points; // Simplified for demo
        $predictive_insights[] = "Trending toward " . number_format($current_month_coins + $monthly_prediction) . " QR coins this month";
    }
}

// GET REAL-TIME COMMUNITY ACTIVITY
$community_activity = [];
try {
    // Get recent achievements (last 2 hours, anonymized)
    $stmt = $pdo->prepare("
        SELECT 
            'coin_earned' as type,
            CONCAT(SUBSTRING(u.username, 1, 1), '***', SUBSTRING(u.username, -1, 1)) as anonymous_name,
            t.amount as amount,
            t.created_at
        FROM qr_coin_transactions t
        LEFT JOIN users u ON t.user_id = u.id
        WHERE t.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR) 
        AND t.amount > 0
        AND t.amount >= 50
        ORDER BY t.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_earnings = $stmt->fetchAll();
    
    foreach ($recent_earnings as $earning) {
        $name = $earning['anonymous_name'] ?: 'Someone';
        $community_activity[] = [
            'message' => "{$name} just earned {$earning['amount']} QR coins!",
            'time' => $earning['created_at'],
            'type' => 'coin_earned',
            'icon' => 'coin'
        ];
    }
    
    // Get personal bests today
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT voter_ip) as users_with_pb
        FROM (
            SELECT voter_ip, COUNT(*) as daily_votes
            FROM votes 
            WHERE DATE(created_at) = CURDATE()
            GROUP BY voter_ip
            HAVING daily_votes >= 10
        ) as high_activity_users
    ");
    $stmt->execute();
    $pb_count = $stmt->fetchColumn();
    
    if ($pb_count > 0) {
        $community_activity[] = [
            'message' => "{$pb_count} users beat their personal best today!",
            'time' => date('Y-m-d H:i:s'),
            'type' => 'achievement',
            'icon' => 'trophy'
        ];
    }
    
    // Get recent level ups
    $stmt = $pdo->prepare("
        SELECT 
            CONCAT(SUBSTRING(u.username, 1, 1), '***', SUBSTRING(u.username, -1, 1)) as anonymous_name,
            'level_up' as activity_type,
            NOW() as activity_time
        FROM users u
        WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        LIMIT 3
    ");
    $stmt->execute();
    $recent_levels = $stmt->fetchAll();
    
    foreach ($recent_levels as $level) {
        $name = $level['anonymous_name'] ?: 'Someone';
        $community_activity[] = [
            'message' => "{$name} just leveled up!",
            'time' => $level['activity_time'],
            'type' => 'level_up',
            'icon' => 'star'
        ];
    }
    
} catch (Exception $e) {
    error_log("Community activity error: " . $e->getMessage());
}

$hasSpunToday = $spins_today > 0;

require_once __DIR__ . '/../core/includes/header.php';
?>

<!-- Flash Messages -->
<?php if (isset($_SESSION['flash_message'])): ?>
    <div class="row mb-3">
        <div class="col-12">
            <div class="alert alert-<?php echo $_SESSION['flash_type'] ?? 'info'; ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-<?php echo $_SESSION['flash_type'] === 'success' ? 'check-circle' : 'info-circle'; ?> me-2"></i>
                <?php echo htmlspecialchars($_SESSION['flash_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
    </div>
    <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
<?php endif; ?>



<!-- User Level and Progress Card -->
<div class="row mb-4" style="margin-top: 20px;">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center mb-3">
                            <!-- Equipped Avatar on the left -->
                            <div class="me-4" style="min-width: 80px; max-width: 80px;">
                                <img src="../assets/img/avatars/<?php echo $avatar_filename; ?>" 
                                     alt="User Avatar"
                                     class="img-fluid"
                                     style="max-width: 80px; height: auto;"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                <i class="bi bi-person-circle text-primary" style="font-size: 4rem; display: none;"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1 text-primary"><?php echo htmlspecialchars($user_data['username'] ?? 'User'); ?></h6>
                                <?php if ($user_level < 100): ?>
                                    <p class="mb-2">
                                        Progress to Level <?php echo $user_level + 1; ?>
                                    </p>
                                    <div class="progress mb-2" style="height: 12px;">
                                        <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $level_progress; ?>%"></div>
                                    </div>
                                    <small class="text-muted">
                                        <?php echo number_format($level_progress, 1); ?>% complete 
                                        • <?php echo number_format($points_to_next); ?> QR coins needed
                                    </small>
                                <?php else: ?>
                                    <p class="text-warning mb-2">🎉 MAX LEVEL ACHIEVED! 🎉</p>
                                    <div class="progress mb-2" style="height: 12px;">
                                        <div class="progress-bar bg-warning" role="progressbar" style="width: 100%"></div>
                                    </div>
                                    <small class="text-muted">You've reached the highest level possible!</small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="d-flex align-items-center justify-content-center">
                            <?php 
                            // Determine which badge to show based on level
                            $badge_level = 1;
                            $badge_name = "Novice";
                            if ($user_level >= 40) {
                                $badge_level = 40;
                                $badge_name = "Legend";
                            } elseif ($user_level >= 30) {
                                $badge_level = 30;
                                $badge_name = "Master";
                            } elseif ($user_level >= 20) {
                                $badge_level = 20;
                                $badge_name = "Veteran";
                            } elseif ($user_level >= 10) {
                                $badge_level = 10;
                                $badge_name = "Explorer";
                            }
                            ?>
                            <img src="../assets/qrlvl/lvl<?php echo $badge_level; ?>.png" 
                                 alt="Level <?php echo $badge_level; ?> Badge" 
                                 class="me-3" 
                                 style="width: 80px; height: 80px;">
                            <div>
                                <div class="h5 mb-1" style="color: #FFFFFF; text-shadow: 2px 2px 0px #000000, -2px -2px 0px #000000, 2px -2px 0px #000000, -2px 2px 0px #000000;"><?php echo $badge_name; ?></div>
                                <div class="h1 mb-0" style="color: #FFD700; text-shadow: 2px 2px 0px #000000, -2px -2px 0px #000000, 2px -2px 0px #000000, -2px 2px 0px #000000;"><?php echo $user_level; ?></div>
                                <small class="text-muted">of 100</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Stats Overview -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-gradient bg-primary text-white h-100">
            <div class="card-body text-center">
                <img src="../img/qrCoin.png" alt="QR Coin" class="mb-2" style="width: 4rem; height: 4rem;">
                <h2 class="mb-0" data-balance-display><?php echo number_format($user_points); ?></h2>
                <p class="mb-0">QR Coin Balance</p>
                <small class="opacity-75">Earn: Vote (+10) | Spin (+25)</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-gradient bg-success text-white h-100">
            <div class="card-body text-center">
                <i class="bi bi-graph-up display-4 mb-2"></i>
                <h2 class="mb-0">#<?php echo number_format($user_rank); ?></h2>
                <p class="mb-0">Your Rank</p>
                <small class="opacity-75">Among all users</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-gradient bg-warning text-dark h-100">
            <div class="card-body text-center">
                <img src="../img/qractivity.png" alt="QR Activity" class="mb-2" style="width: 7.3rem; height: 7.3rem; margin-top: -20px;">
                <h2 class="mb-0"><?php echo $current_streak; ?></h2>
                <p class="mb-0">Day Streak</p>
                <small class="opacity-75">Keep it going!</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-gradient bg-info text-white h-100">
            <div class="card-body text-center">
                <i class="bi bi-calendar-day display-4 mb-2"></i>
                <h2 class="mb-0"><?php echo $votes_today + $spins_today; ?></h2>
                <p class="mb-0">Today's Impact</p>
                <small class="opacity-75">Votes + Spins</small>
            </div>
        </div>
    </div>
</div>

<!-- Savings Section -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card bg-gradient bg-success text-white h-100">
            <div class="card-body text-center">
                <i class="bi bi-piggy-bank display-4 mb-2"></i>
                <h2 class="mb-0">$<?php echo number_format($savings_data['total_savings_cad'], 2); ?> CAD</h2>
                <p class="mb-0">Total Savings</p>
                <small class="opacity-75"><?php echo number_format($savings_data['total_qr_coins_used']); ?> QR coins invested</small>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card bg-gradient bg-dark text-white h-100">
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <i class="bi bi-check-circle display-6 text-success"></i>
                        <h4 class="mb-0">$<?php echo number_format($savings_data['redeemed_savings_cad'], 2); ?></h4>
                        <small>Redeemed</small>
                    </div>
                    <div class="col-6">
                        <i class="bi bi-clock display-6 text-warning"></i>
                        <h4 class="mb-0">$<?php echo number_format($savings_data['pending_savings_cad'], 2); ?></h4>
                        <small>Pending</small>
                    </div>
                </div>
                <hr class="my-2 opacity-50">
                <p class="text-center mb-0 small">
                    <i class="bi bi-receipt me-1"></i><?php echo $savings_data['total_purchases']; ?> discount purchases
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Enhanced Vote Status Panel & Personal Insights -->
<div class="row mb-4">
    <!-- Your Voting Power Card (Half Screen) -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-ballot-fill me-2"></i>Your Voting Power
                    </h5>
                    <span class="badge bg-warning text-dark">New System!</span>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <!-- Left Column - Voting Power -->
                    <div class="col-md-6">
                        <!-- Daily Free Vote -->
                        <!-- Votes Used This Week -->
                        <div class="alert alert-<?php echo $vote_status['votes_used'] >= $vote_status['weekly_limit'] ? 'danger' : 'success'; ?> border-start border-4 border-<?php echo $vote_status['votes_used'] >= $vote_status['weekly_limit'] ? 'danger' : 'success'; ?> bg-<?php echo $vote_status['votes_used'] >= $vote_status['weekly_limit'] ? 'danger' : 'success'; ?> bg-opacity-10 mb-2 p-3">
                            <div class="d-flex align-items-center justify-content-center">
                                <div class="me-3">
                                    <img src="../assets/page/giftbox.png" alt="Vote Box" style="width: 48px; height: 48px;">
                                </div>
                                <div class="text-start">
                                    <h4 class="mb-0"><?php echo $vote_status['votes_used']; ?></h4>
                                    <h6 class="mb-1">Vote<?php echo $vote_status['votes_used'] != 1 ? 's' : ''; ?> Used</h6>
                                    <div class="small text-muted">
                                        <i class="bi bi-clock me-1"></i>This week
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Votes Remaining This Week -->
                        <div class="alert alert-<?php echo $vote_status['votes_remaining'] > 0 ? 'info' : 'warning'; ?> border-start border-4 border-<?php echo $vote_status['votes_remaining'] > 0 ? 'info' : 'warning'; ?> bg-<?php echo $vote_status['votes_remaining'] > 0 ? 'info' : 'warning'; ?> bg-opacity-10 mb-2 p-3">
                            <div class="d-flex align-items-center justify-content-center">
                                <div class="me-3">
                                    <img src="../assets/page/star.png" alt="Star" style="width: 48px; height: 48px;">
                                </div>
                                <div class="text-start">
                                    <h4 class="mb-0"><?php echo $vote_status['votes_remaining']; ?></h4>
                                    <h6 class="mb-1">Vote<?php echo $vote_status['votes_remaining'] != 1 ? 's' : ''; ?> Remaining</h6>
                                    <div class="small text-muted">
                                        <i class="bi bi-coin me-1"></i>Earns 30 QR coins each
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Weekly Limit -->
                        <div class="alert alert-secondary border-start border-4 border-secondary bg-secondary bg-opacity-10 mb-2 p-3">
                            <div class="d-flex align-items-center justify-content-center">
                                <div class="me-3">
                                    <img src="../assets/page/votepre.png" alt="Weekly Limit" style="width: 48px; height: 48px;">
                                </div>
                                <div class="text-start">
                                    <h4 class="mb-0"><?php echo $vote_status['weekly_limit']; ?></h4>
                                    <h6 class="mb-1">Weekly Limit</h6>
                                    <div class="small text-muted">
                                        <i class="bi bi-calendar-week me-1"></i>Resets Monday
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Column - Voting Metrics -->
                    <div class="col-md-6">
                        <div class="row g-2">
                            <div class="col-12">
                                <div class="alert alert-primary border-start border-4 border-primary bg-primary bg-opacity-10 mb-2 p-3">
                                    <div class="text-center">
                                        <i class="bi bi-trophy-fill text-primary me-2"></i>
                                        <strong><?php echo number_format($voting_stats['total_votes']); ?></strong>
                                        <div class="small text-muted mt-1">Total Votes Cast</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <div class="alert alert-secondary border-start border-4 border-secondary bg-secondary bg-opacity-10 mb-2 p-3">
                                    <div class="text-center">
                                        <i class="bi bi-calendar-week text-secondary me-2"></i>
                                        <strong><?php echo $voting_stats['voting_days']; ?></strong>
                                        <div class="small text-muted mt-1">Active Days</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-6">
                                <div class="alert alert-success border-start border-4 border-success bg-success bg-opacity-10 mb-2 p-2">
                                    <div class="text-center">
                                        <i class="bi bi-plus-circle-fill text-success"></i>
                                        <strong class="d-block"><?php echo number_format($voting_stats['votes_in']); ?></strong>
                                        <div class="small text-muted">IN</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-6">
                                <div class="alert alert-danger border-start border-4 border-danger bg-danger bg-opacity-10 mb-2 p-2">
                                    <div class="text-center">
                                        <i class="bi bi-dash-circle-fill text-danger"></i>
                                        <strong class="d-block"><?php echo number_format($voting_stats['votes_out']); ?></strong>
                                        <div class="small text-muted">OUT</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vote Status Summary -->
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="text-center p-2 bg-light bg-opacity-50 rounded">
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="small">
                                        <strong>Used:</strong> <?php echo $vote_status['votes_used']; ?>/<?php echo $vote_status['weekly_limit']; ?>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="small">
                                        <strong>Remaining:</strong> <?php echo $vote_status['votes_remaining']; ?>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="small">
                                        <strong>QR Balance:</strong> <?php echo number_format($vote_status['qr_balance']); ?>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center">
                                        <a href="<?php echo APP_URL; ?>/vote.php" class="btn btn-light btn-sm">
                                            <i class="bi bi-ballot-fill me-1"></i>Vote Now
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- How It Works Info (Compact) -->
                <div class="mt-2">
                    <div class="text-center">
                        <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#votingExplanation" aria-expanded="false">
                            <i class="bi bi-info-circle me-1"></i>How It Works
                        </button>
                    </div>
                    <div class="collapse mt-2" id="votingExplanation">
                        <div class="card bg-light bg-opacity-50 border-0">
                            <div class="card-body p-2">
                                <div class="small">
                                    <div class="mb-2">
                                        <i class="bi bi-calendar-week text-primary me-1"></i>
                                        <strong>Simple System:</strong> 2 votes per week maximum
                                    </div>
                                    <div class="mb-2">
                                        <i class="bi bi-coin text-success me-1"></i>
                                        <strong>Reward:</strong> 30 QR coins per vote
                                    </div>
                                    <div>
                                        <i class="bi bi-arrow-clockwise text-info me-1"></i>
                                        <strong>Reset:</strong> Every Monday at midnight
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Personal Insights & Predictions Card (Half Screen) -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-lightbulb me-2"></i>Personal Insights & Predictions</h5>
            </div>
            <div class="card-body">
                <?php if (empty($insights) && empty($predictive_insights)): ?>
                    <div class="text-center py-3">
                        <i class="bi bi-graph-up display-4 text-muted mb-2"></i>
                        <p class="text-muted">Keep engaging to unlock personalized insights!</p>
                    </div>
                <?php else: ?>
                    <!-- Predictive Insights Section -->
                    <?php if (!empty($predictive_insights)): ?>
                        <div class="mb-3">
                            <h6 class="text-primary mb-2">
                                <i class="bi bi-graph-up-arrow me-2"></i>Your Predictions
                                <span class="badge bg-primary ms-2 pulse-badge">AI</span>
                            </h6>
                            <div style="max-height: 200px; overflow-y: auto;">
                                <?php foreach ($predictive_insights as $prediction): ?>
                                    <div class="alert alert-info border-start border-4 border-info bg-info bg-opacity-10 mb-2 prediction-alert p-2">
                                        <i class="bi bi-crystal-ball me-2"></i>
                                        <strong class="small"><?php echo htmlspecialchars($prediction); ?></strong>
                                        <small class="d-block text-muted mt-1">
                                            <i class="bi bi-clock me-1"></i>Updated based on your last 7 days
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Personal Insights Section -->
                    <?php if (!empty($insights)): ?>
                        <div>
                            <h6 class="text-success mb-2">
                                <i class="bi bi-lightbulb-fill me-2"></i>Your Insights
                            </h6>
                            <div style="max-height: 150px; overflow-y: auto;">
                                <?php foreach ($insights as $insight): ?>
                                    <div class="alert alert-success border-start border-4 border-success bg-success bg-opacity-10 mb-2 p-2">
                                        <i class="bi bi-check-circle me-2"></i>
                                        <span class="small"><?php echo htmlspecialchars($insight); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- QR Coin Wallet & Store Access -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card gradient-card-success shadow-lg">
            <div class="card-header bg-transparent border-0">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="text-white mb-0">
                        <i class="bi bi-wallet2 me-2"></i>QR Coin Wallet & Stores
                    </h5>
                    <div class="d-flex align-items-center">
                        <img src="../img/qrCoin.png" alt="QR Coin" style="width: 2rem; height: 2rem;" class="me-2">
                        <h4 class="text-warning mb-0" data-balance-display><?php echo number_format($user_points); ?></h4>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <!-- QR Coin Stats -->
                    <div class="col-md-4">
                        <div class="d-flex align-items-center p-3 bg-white bg-opacity-10 rounded">
                            <div class="flex-shrink-0">
                                <i class="bi bi-graph-up-arrow display-6 text-warning"></i>
                            </div>
                            <div class="ms-3 text-white">
                                <h6 class="mb-1">Earned Today</h6>
                                <h4 class="mb-0">+<?php echo number_format($qr_stats['earned_today'] ?? 0); ?></h4>
                                <small class="opacity-75">QR Coins</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Spending Stats -->
                    <div class="col-md-4">
                        <div class="d-flex align-items-center p-3 bg-white bg-opacity-10 rounded">
                            <div class="flex-shrink-0">
                                <i class="bi bi-cart-check display-6 text-info"></i>
                            </div>
                            <div class="ms-3 text-white">
                                <h6 class="mb-1">Spent Total</h6>
                                <h4 class="mb-0"><?php echo number_format($qr_stats['total_spent'] ?? 0); ?></h4>
                                <small class="opacity-75">QR Coins</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Store Access -->
                    <div class="col-md-4">
                        <div class="d-flex align-items-center p-3 bg-white bg-opacity-10 rounded">
                            <div class="flex-shrink-0">
                                <i class="bi bi-shop display-6 text-success"></i>
                            </div>
                            <div class="ms-3 text-white">
                                <h6 class="mb-1">Available Stores</h6>
                                <h4 class="mb-0"><?php echo $available_business_stores + $available_qr_items; ?></h4>
                                <small class="opacity-75">Items to browse</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Store Actions -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card bg-white bg-opacity-15 border-0 h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-building display-4 text-warning mb-3"></i>
                                <h6 class="text-white mb-2">Business Discount Stores</h6>
                                <p class="text-white-50 small mb-3">Get discounts at local vending machines</p>
                                <?php if ($business_store_enabled && $available_business_stores > 0): ?>
                                    <a href="business-stores.php" class="btn btn-warning btn-sm">
                                        <i class="bi bi-shop me-1"></i>Browse <?php echo $available_business_stores; ?> Stores
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-outline-light btn-sm" disabled>
                                        <i class="bi bi-clock me-1"></i>Coming Soon
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card bg-white bg-opacity-15 border-0 h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-gem display-4 text-info mb-3"></i>
                                <h6 class="text-white mb-2">QR Store</h6>
                                <p class="text-white-50 small mb-3">Premium avatars, boosts & features</p>
                                <?php if ($store_enabled && $available_qr_items > 0): ?>
                                    <a href="qr-store.php" class="btn btn-info btn-sm">
                                        <i class="bi bi-gem me-1"></i>Browse <?php echo $available_qr_items; ?> Items
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-outline-light btn-sm" disabled>
                                        <i class="bi bi-clock me-1"></i>Coming Soon
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- User Guide & Help -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="text-center p-3 bg-white bg-opacity-10 rounded">
                            <div class="d-flex justify-content-center align-items-center gap-3">
                                <a href="user-guide.php" class="btn btn-outline-light btn-sm">
                                    <i class="bi bi-book-half me-1"></i>User Guide
                                </a>
                                <a href="qr-transactions.php" class="btn btn-outline-light btn-sm">
                                    <i class="bi bi-wallet me-1"></i>QR Wallet
                                </a>
                                <a href="my-purchases.php" class="btn btn-outline-light btn-sm">
                                    <i class="bi bi-bag-check me-1"></i>My Purchases
                                </a>
                                <a href="qr-transactions.php" class="btn btn-outline-light btn-sm">
                                    <i class="bi bi-clock-history me-1"></i>Recent Transactions
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Horse Racing Card -->
<div class="row mb-4">
    <div class="col-12">
        <?php
        // Get user's recent racing activity
        $stmt = $pdo->prepare("
            SELECT br.id, br.race_name, br.race_type, br.start_time, br.end_time, br.status,
                   CASE 
                       WHEN br.start_time <= NOW() AND br.end_time >= NOW() THEN 'LIVE'
                       WHEN br.start_time > NOW() THEN 'UPCOMING'
                       ELSE 'FINISHED'
                   END as race_status,
                   TIMESTAMPDIFF(SECOND, NOW(), br.start_time) as time_to_start,
                   COUNT(rb.id) as user_bets
            FROM business_races br
            LEFT JOIN race_bets rb ON br.id = rb.race_id AND rb.user_id = ?
            WHERE br.status IN ('approved', 'active', 'completed')
            GROUP BY br.id
            ORDER BY 
                CASE WHEN br.start_time <= NOW() AND br.end_time >= NOW() THEN 1
                     WHEN br.start_time > NOW() THEN 2
                     ELSE 3 END,
                br.start_time ASC
            LIMIT 1
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $featured_race = $stmt->fetch();
        
        // Get user racing stats
        $stmt = $pdo->prepare("
            SELECT total_races_participated, total_qr_coins_won, win_rate
            FROM user_racing_stats WHERE user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $racing_stats = $stmt->fetch() ?: ['total_races_participated' => 0, 'total_qr_coins_won' => 0, 'win_rate' => 0];
        ?>
        
        <div class="card border-0" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center mb-3">
                            <div class="me-3">
                                <img src="/horse-racing/assets/img/racetrophy.png" alt="Race Trophy" style="width: 60px; height: 60px;">
                            </div>
                            <div>
                                <h4 class="text-white mb-1">🏇 Horse Racing Arena</h4>
                                <p class="text-white-50 mb-0">Bet on races powered by real vending machine data!</p>
                            </div>
                        </div>
                        
                        <?php if ($featured_race): ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-warning mb-1">
                                        <?php if ($featured_race['race_status'] === 'LIVE'): ?>
                                            🔴 LIVE NOW
                                        <?php elseif ($featured_race['race_status'] === 'UPCOMING'): ?>
                                            ⏰ UPCOMING
                                        <?php else: ?>
                                            🏁 RECENT
                                        <?php endif; ?>
                                    </h6>
                                    <div class="fw-bold"><?php echo htmlspecialchars($featured_race['race_name']); ?></div>
                                    <small class="text-white-50">
                                        <?php echo ucfirst($featured_race['race_type']); ?> Race
                                        <?php if ($featured_race['user_bets'] > 0): ?>
                                            • You have <?php echo $featured_race['user_bets']; ?> bet<?php echo $featured_race['user_bets'] > 1 ? 's' : ''; ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <div class="col-md-6">
                                    <?php if ($featured_race['race_status'] === 'LIVE'): ?>
                                        <a href="../horse-racing/race-live.php?id=<?php echo $featured_race['id']; ?>" 
                                           class="btn btn-warning btn-sm">
                                            <i class="bi bi-eye"></i> Watch Live
                                        </a>
                                    <?php elseif ($featured_race['race_status'] === 'UPCOMING'): ?>
                                        <div class="text-white-50 small mb-1">
                                            Starts in: <?php echo gmdate("H:i:s", $featured_race['time_to_start']); ?>
                                        </div>
                                        <a href="../horse-racing/betting.php?race_id=<?php echo $featured_race['id']; ?>" 
                                           class="btn btn-light btn-sm">
                                            <i class="bi bi-currency-dollar"></i> Place Bets
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-3">
                                <p class="text-white-50 mb-2">No active races at the moment</p>
                                <small class="text-white-50">Check back soon for exciting horse races!</small>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="text-center">
                            <div class="row">
                                <div class="col-4">
                                    <div class="text-warning fw-bold h4 mb-1"><?php echo $racing_stats['total_races_participated']; ?></div>
                                    <small class="text-white-50">Races</small>
                                </div>
                                <div class="col-4">
                                    <div class="text-warning fw-bold h4 mb-1"><?php echo number_format($racing_stats['total_qr_coins_won']); ?></div>
                                    <small class="text-white-50">Coins Won</small>
                                </div>
                                <div class="col-4">
                                    <div class="text-warning fw-bold h4 mb-1"><?php echo number_format($racing_stats['win_rate'], 1); ?>%</div>
                                    <small class="text-white-50">Win Rate</small>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <a href="../horse-racing/" class="btn btn-light btn-sm">
                                    <i class="bi bi-trophy"></i> Racing Arena
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Today's Opportunities -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-target me-2"></i>Today's Opportunities</h5>
                <span class="badge bg-primary"><?php echo date('M j, Y'); ?></span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center p-3 border rounded <?php echo $votes_today > 0 ? 'bg-light border-success' : ''; ?>">
                            <div class="flex-shrink-0">
                                <i class="bi bi-check2-square display-6 text-<?php echo $votes_today > 0 ? 'success' : 'primary'; ?>"></i>
                            </div>
                            <div class="ms-3 flex-grow-1">
                                <h6 class="mb-1">Vote for Items</h6>
                                <p class="text-muted mb-2 small">Help improve vending selections</p>
                                <?php if ($votes_today > 0): ?>
                                    <span class="badge bg-success"><i class="bi bi-check me-1"></i><?php echo $votes_today; ?> votes today</span>
                                <?php else: ?>
                                    <span class="badge bg-primary">+10 QR coins per vote</span>
                                <?php endif; ?>
                            </div>
                            <div class="flex-shrink-0">
                                <a href="<?php echo APP_URL; ?>/user/vote.php" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-graph-up me-1"></i>Analytics
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-center p-3 border rounded <?php echo $hasSpunToday ? 'bg-light border-warning' : ''; ?>">
                            <div class="flex-shrink-0">
                                <i class="bi bi-trophy display-6 text-<?php echo $hasSpunToday ? 'warning' : 'warning'; ?>"></i>
                            </div>
                            <div class="ms-3 flex-grow-1">
                                <h6 class="mb-1">Daily Spin</h6>
                                <p class="text-muted mb-2 small">Try your luck for prizes</p>
                                <?php if ($hasSpunToday): ?>
                                    <span class="badge bg-warning text-dark"><i class="bi bi-check me-1"></i>Spun today</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">+25 QR coins guaranteed</span>
                                <?php endif; ?>
                            </div>
                            <div class="flex-shrink-0">
                                <a href="<?php echo APP_URL; ?>/user/spin.php" class="btn btn-sm btn-<?php echo $hasSpunToday ? 'outline-warning' : 'warning'; ?>">
                                    <i class="bi bi-<?php echo $hasSpunToday ? 'check-circle' : 'trophy'; ?> me-1"></i><?php echo $hasSpunToday ? 'Done' : 'Spin'; ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if ($votes_today === 0 && !$hasSpunToday): ?>
                    <div class="alert alert-info mt-3 mb-0">
                        <i class="bi bi-lightbulb me-2"></i>
                        <strong>Pro Tip:</strong> Complete both activities today to earn bonus streak QR coins and climb the leaderboard!
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="mb-0"><i class="bi bi-star-fill me-2 text-warning"></i>Achievements</h6>
            </div>
            <div class="card-body">
                <?php if (empty($recent_achievements)): ?>
                    <div class="text-center py-3">
                        <i class="bi bi-trophy display-1 text-muted mb-2"></i>
                        <p class="text-muted small mb-0">Start voting and spinning to unlock achievements!</p>
                    </div>
                <?php else: ?>
                    <?php foreach (array_slice($recent_achievements, 0, 3) as $achievement): ?>
                        <div class="d-flex align-items-center mb-3 pb-2 <?php echo !end($recent_achievements) ? 'border-bottom' : ''; ?>">
                            <i class="bi bi-<?php echo $achievement['icon']; ?> text-<?php echo $achievement['color']; ?> fs-4 me-3"></i>
                            <div>
                                <h6 class="mb-0"><?php echo $achievement['title']; ?></h6>
                                <small class="text-muted"><?php echo $achievement['desc']; ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Community Activity Feed -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="bi bi-people-fill me-2 text-info"></i>Community Activity
                </h6>
                <span class="badge bg-info">Live</span>
            </div>
            <div class="card-body">
                <?php if (empty($community_activity)): ?>
                    <div class="text-center py-3">
                        <i class="bi bi-activity display-4 text-muted mb-2"></i>
                        <p class="text-muted small">No recent activity</p>
                        <small class="text-muted">Check back soon!</small>
                    </div>
                <?php else: ?>
                    <div class="community-feed">
                        <div class="row">
                            <?php foreach (array_slice($community_activity, 0, 6) as $index => $activity): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="d-flex align-items-start p-2 bg-light bg-opacity-10 rounded">
                                        <div class="flex-shrink-0 me-2">
                                            <?php
                                            $icon_class = match($activity['type']) {
                                                'coin_earned' => 'bi-coin text-warning',
                                                'achievement' => 'bi-trophy-fill text-warning',
                                                'level_up' => 'bi-star-fill text-info',
                                                default => 'bi-activity text-primary'
                                            };
                                            ?>
                                            <i class="bi <?php echo $icon_class; ?> fs-5"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="small fw-medium mb-1">
                                                <?php echo htmlspecialchars($activity['message']); ?>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo date('H:i', strtotime($activity['time'])); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="text-center mt-3">
                        <a href="leaderboard.php" class="btn btn-outline-info btn-sm">
                            <i class="bi bi-trophy me-1"></i>View Leaderboard
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row">
    <div class="col-12">
        <div class="card bg-gradient bg-dark text-white">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="mb-2"><i class="bi bi-qr-code-scan me-2"></i>Ready for More Action?</h5>
                        <p class="mb-0 opacity-75">Scan QR codes at vending machines to vote, spin the wheel for prizes, or check out your detailed analytics!</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="d-flex gap-2 justify-content-end">
                            <a href="<?php echo APP_URL; ?>/user/vote.php" class="btn btn-light btn-sm">
                                <i class="bi bi-graph-up me-1"></i>Analytics
                            </a>
                            <a href="<?php echo APP_URL; ?>/user/spin.php" class="btn btn-warning btn-sm">
                                <i class="bi bi-trophy me-1"></i>Spin
                            </a>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- QR Coin Analytics & Business Impact (Moved from Vote Page) -->
<div class="row mb-4">
    <!-- QR Coin Analytics -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-piggy-bank me-2"></i>QR Coin Analytics
                    </h5>
                    <span class="badge bg-success"><?php echo number_format($user_points); ?> Total</span>
                </div>
            </div>
            <div class="card-body">
                <!-- Earning vs Spending Summary -->
                <div class="row mb-3">
                    <div class="col-6">
                        <div class="text-center p-3 bg-success bg-opacity-10 rounded">
                            <i class="bi bi-arrow-down-circle text-success mb-2" style="font-size: 2rem;"></i>
                            <h5 class="text-success mb-1"><?php echo number_format($total_earned); ?></h5>
                            <small class="text-muted">Total Earned</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center p-3 bg-danger bg-opacity-10 rounded">
                            <i class="bi bi-arrow-up-circle text-danger mb-2" style="font-size: 2rem;"></i>
                            <h5 class="text-danger mb-1"><?php echo number_format($total_spent); ?></h5>
                            <small class="text-muted">Total Spent</small>
                        </div>
                    </div>
                </div>

                <!-- Top Earning Categories -->
                <?php if (!empty($earning_breakdown)): ?>
                    <h6 class="mb-2">
                        <i class="bi bi-graph-up me-1"></i>Top Earning Sources
                    </h6>
                    <?php foreach (array_slice($earning_breakdown, 0, 3) as $category => $data): ?>
                        <?php 
                        // Clean the category name to prevent display issues
                        $clean_category = htmlspecialchars(trim(ucfirst(str_replace(['_', '\\'], ' ', $category))));
                        ?>
                        <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                            <div>
                                <span class="fw-semibold"><?php echo $clean_category; ?></span>
                                <small class="text-muted d-block"><?php echo number_format($data['transaction_count']); ?> transactions</small>
                            </div>
                            <span class="badge bg-success">+<?php echo number_format($data['total_amount']); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="text-center py-3 text-muted">
                        <i class="bi bi-coin me-2"></i>No earning history yet
                    </div>
                <?php endif; ?>

                <!-- Recent Store Purchases -->
                <?php if ($total_store_purchases > 0): ?>
                    <div class="mt-3 pt-3 border-top">
                        <h6 class="mb-2">
                            <i class="bi bi-shop me-1"></i>Recent Store Activity
                        </h6>
                        <div class="small">
                            <div class="d-flex justify-content-between">
                                <span>Store Purchases:</span>
                                <span class="fw-bold"><?php echo $total_store_purchases; ?> items</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Coins Spent:</span>
                                <span class="fw-bold text-danger"><?php echo number_format($total_store_spent); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Business Impact -->
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-buildings me-2"></i>Business Impact
                    </h5>
                    <span class="badge bg-info">Your Influence</span>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($business_engagement)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-building display-1 text-muted mb-3"></i>
                        <p class="text-muted">No business interactions yet</p>
                        <small class="text-muted">Start voting to see your impact!</small>
                    </div>
                <?php else: ?>
                    <?php foreach ($business_engagement as $business): ?>
                        <div class="mb-3 p-3 border rounded">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="mb-0"><?php echo htmlspecialchars($business['business_name']); ?></h6>
                                <span class="badge bg-primary"><?php echo $business['votes_cast']; ?> votes</span>
                            </div>
                            <div class="row g-2 small text-muted">
                                <div class="col-6">
                                    <i class="bi bi-gear me-1"></i><?php echo $business['machines_voted_on']; ?> machines
                                </div>
                                <div class="col-6">
                                    <i class="bi bi-box me-1"></i><?php echo $business['items_influenced']; ?> items
                                </div>
                                <div class="col-6">
                                    <i class="bi bi-cash me-1"></i>$<?php echo number_format($business['avg_item_price'], 2); ?> avg
                                </div>
                                <div class="col-6">
                                    <i class="bi bi-clock me-1"></i><?php echo date('M j', strtotime($business['last_interaction'])); ?> last
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="alert alert-success mt-3">
                        <i class="bi bi-award me-2"></i>
                        <strong>Community Impact:</strong> You've influenced <?php echo array_sum(array_column($business_engagement, 'items_influenced')); ?> 
                        products across <?php echo count($business_engagement); ?> businesses!
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity Section -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Activity</h5>
                <?php if ($total_activities > 0): ?>
                    <span class="badge bg-primary"><?php echo $total_activities; ?> total activities</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($recent_activity)): ?>
                    <div class="text-center py-4">
                        <img src="../img/qractivity.png" alt="QR Activity" class="mb-3" style="width: 6rem; height: 6rem; opacity: 0.5;">
                        <h5 class="text-muted">No activity yet</h5>
                        <p class="text-muted">Start voting or spinning to see your activities here!</p>
                        <div class="d-flex gap-2 justify-content-center">
                            <a href="<?php echo APP_URL; ?>/user/vote.php" class="btn btn-primary">Vote Now</a>
                            <a href="<?php echo APP_URL; ?>/user/spin.php" class="btn btn-warning">Spin Now</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($recent_activity as $activity): ?>
                            <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                                <div class="flex-shrink-0">
                                    <?php if ($activity['type'] === 'spin'): ?>
                                        <i class="bi bi-trophy-fill text-warning fs-4"></i>
                                    <?php else: ?>
                                        <i class="bi bi-check-circle-fill text-success fs-4"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="ms-3">
                                    <div class="fw-medium"><?php echo htmlspecialchars($activity['achievement']); ?></div>
                                    <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination Controls -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Recent Activity Pagination" class="mt-4">
                            <ul class="pagination pagination-sm justify-content-center">
                                <!-- Previous Page -->
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </span>
                                    </li>
                                <?php endif; ?>
                                
                                <!-- Page Numbers -->
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                if ($start_page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=1">1</a>
                                    </li>
                                    <?php if ($start_page > 2): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($end_page < $total_pages): ?>
                                    <?php if ($end_page < $total_pages - 1): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    <?php endif; ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a>
                                    </li>
                                <?php endif; ?>
                                
                                <!-- Next Page -->
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </span>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                        
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                Showing <?php echo ($offset + 1); ?>-<?php echo min($offset + $items_per_page, $total_activities); ?> 
                                of <?php echo $total_activities; ?> activities
                            </small>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animate cards on load
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 150);
    });
    
    // Animate progress bars
    setTimeout(() => {
        const progressBars = document.querySelectorAll('.progress-bar');
        progressBars.forEach(bar => {
            const width = bar.style.width;
            bar.style.width = '0%';
            setTimeout(() => {
                bar.style.transition = 'width 1.5s ease-in-out';
                bar.style.width = width;
            }, 100);
        });
    }, 800);
    
    // LIVE COMMUNITY ACTIVITY UPDATES
    function updateCommunityFeed() {
        const feedContainer = document.querySelector('.community-feed');
        if (!feedContainer) return;
        
        fetch('/user/api/community-activity.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.activities.length > 0) {
                    // Create new activity elements
                    data.activities.forEach((activity, index) => {
                        // Check if this activity is already shown
                        const existingActivities = Array.from(feedContainer.querySelectorAll('.small.fw-medium')).map(el => el.textContent);
                        if (existingActivities.includes(activity.message)) return;
                        
                        // Create new activity element
                        const activityElement = document.createElement('div');
                        activityElement.className = 'd-flex align-items-start mb-3 pb-2 border-bottom border-opacity-25 new-activity';
                        activityElement.innerHTML = `
                            <div class="flex-shrink-0 me-2">
                                <i class="bi ${getIconClass(activity.type)} fs-5"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="small fw-medium mb-1">${activity.message}</div>
                                <small class="text-muted">${new Date(activity.time).toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'})}</small>
                            </div>
                        `;
                        
                        // Add with animation
                        activityElement.style.opacity = '0';
                        activityElement.style.transform = 'translateX(-20px)';
                        feedContainer.insertBefore(activityElement, feedContainer.firstChild);
                        
                        setTimeout(() => {
                            activityElement.style.transition = 'all 0.5s ease';
                            activityElement.style.opacity = '1';
                            activityElement.style.transform = 'translateX(0)';
                        }, 100);
                        
                        // Remove oldest if more than 4
                        const activities = feedContainer.querySelectorAll('.d-flex.align-items-start');
                        if (activities.length > 4) {
                            activities[activities.length - 1].remove();
                        }
                    });
                }
            })
            .catch(error => console.log('Feed update failed:', error));
    }
    
    function getIconClass(type) {
        const iconMap = {
            'coin_earned': 'bi-coin text-warning',
            'achievement': 'bi-trophy-fill text-warning', 
            'level_up': 'bi-star-fill text-info'
        };
        return iconMap[type] || 'bi-activity text-primary';
    }
    
    // Update every 30 seconds
    setInterval(updateCommunityFeed, 30000);
    
    // PULSE ANIMATION FOR LIVE BADGE
    const liveBadge = document.querySelector('.badge.bg-info');
    if (liveBadge) {
        setInterval(() => {
            liveBadge.style.transform = 'scale(1.1)';
            setTimeout(() => {
                liveBadge.style.transform = 'scale(1)';
            }, 200);
        }, 3000);
    }
    
    // LEVEL UP DETECTION AND REWARDS
    checkForLevelUp();
});

function checkForLevelUp() {
    const currentLevel = <?php echo $user_level; ?>;
    const storedLevel = localStorage.getItem('userLevel');
    
    if (storedLevel && parseInt(storedLevel) < currentLevel) {
        // User leveled up!
        showLevelUpPopup(currentLevel);
        grantLevelUpRewards(currentLevel);
    }
    
    // Store current level
    localStorage.setItem('userLevel', currentLevel);
}

function showLevelUpPopup(level) {
    // Determine badge for this level
    let badgeLevel = 1;
    if (level >= 40) badgeLevel = 40;
    else if (level >= 30) badgeLevel = 30;
    else if (level >= 20) badgeLevel = 20;
    else if (level >= 10) badgeLevel = 10;
    
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = 'levelUpModal';
    modal.innerHTML = `
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center bg-gradient" style="background: linear-gradient(135deg, #FFD700, #FFA500);">
                <div class="modal-header border-0 justify-content-center">
                    <h4 class="modal-title text-dark fw-bold">🎉 LEVEL UP! 🎉</h4>
                </div>
                <div class="modal-body">
                    <img src="../assets/qrlvl/lvl${badgeLevel}.png" 
                         alt="Level ${badgeLevel} Badge" 
                         style="width: 120px; height: 120px; margin-bottom: 20px;">
                    <h2 class="text-dark fw-bold mb-3" style="text-shadow: 2px 2px 0px #000000;">Level ${level}</h2>
                    <div class="alert alert-success">
                        <h5 class="text-success mb-2">🎁 Level Up Rewards!</h5>
                        <ul class="list-unstyled mb-0">
                            <li><i class="bi bi-coin text-warning"></i> <strong>100 QR Coins</strong></li>
                            <li><i class="bi bi-dice-6-fill text-primary"></i> <strong>Free Spin Wheel</strong></li>
                            <li><i class="bi bi-suit-spade-fill text-danger"></i> <strong>Free Slot Spin</strong></li>
                            <li><i class="bi bi-ballot-fill text-info"></i> <strong>Free Vote</strong></li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <button type="button" class="btn btn-dark btn-lg" data-bs-dismiss="modal">
                        <i class="bi bi-check-circle-fill me-2"></i>Awesome!
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    const modalInstance = new bootstrap.Modal(modal);
    modalInstance.show();
    
    // Remove modal after closing
    modal.addEventListener('hidden.bs.modal', () => {
        document.body.removeChild(modal);
    });
}

function grantLevelUpRewards(level) {
    // Grant the rewards via AJAX
    fetch('api/grant-level-rewards.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            level: level,
            rewards: {
                qr_coins: 100,
                free_spin_wheel: 1,
                free_slot_spin: 1,
                free_vote: 1
            }
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Level up rewards granted successfully');
            // Update balance display
            updateBalanceDisplay();
        }
    })
    .catch(error => {
        console.error('Error granting level up rewards:', error);
    });
}

function updateBalanceDisplay() {
    // Refresh QR coin balance displays
    const balanceElements = document.querySelectorAll('[data-balance-display]');
    fetch('api/get-balance.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                balanceElements.forEach(element => {
                    element.textContent = data.balance.toLocaleString();
                });
            }
        })
        .catch(error => console.error('Error updating balance:', error));
}
</script>

<style>
/* Enhanced Community Feed Styling */
.community-feed {
    max-height: 300px;
    overflow-y: auto;
}

.community-feed::-webkit-scrollbar {
    width: 4px;
}

.community-feed::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 2px;
}

.community-feed::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 2px;
}

.community-feed .border-bottom:last-child {
    border-bottom: none !important;
}

/* Predictive Insights Styling */
.alert.border-start {
    border-left-width: 4px !important;
    padding-left: 1rem;
}

.alert-info {
    background: linear-gradient(135deg, rgba(13, 202, 240, 0.1), rgba(13, 202, 240, 0.05)) !important;
    border-color: rgba(13, 202, 240, 0.3) !important;
    color: rgba(255, 255, 255, 0.9) !important;
}

.alert-success {
    background: linear-gradient(135deg, rgba(25, 135, 84, 0.1), rgba(25, 135, 84, 0.05)) !important;
    border-color: rgba(25, 135, 84, 0.3) !important;
    color: rgba(255, 255, 255, 0.9) !important;
}

/* Live Badge Animation */
.badge.bg-info {
    animation: pulse-glow 2s infinite;
    transition: transform 0.2s ease;
}

/* AI Badge Pulse */
.pulse-badge {
    animation: pulse-scale 1.5s infinite;
    font-size: 0.65rem;
    letter-spacing: 0.5px;
}

@keyframes pulse-scale {
    0%, 100% { 
        transform: scale(1);
        box-shadow: 0 0 0 0 rgba(13, 110, 253, 0.7);
    }
    50% { 
        transform: scale(1.05);
        box-shadow: 0 0 0 4px rgba(13, 110, 253, 0);
    }
}

/* Prediction Alert Enhancements */
.prediction-alert {
    position: relative;
    overflow: hidden;
}

.prediction-alert:before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
    animation: shimmer 3s infinite;
}

@keyframes shimmer {
    0% { left: -100%; }
    100% { left: 100%; }
}

@keyframes pulse-glow {
    0%, 100% { box-shadow: 0 0 5px rgba(13, 202, 240, 0.5); }
    50% { box-shadow: 0 0 15px rgba(13, 202, 240, 0.8), 0 0 25px rgba(13, 202, 240, 0.4); }
}

/* New Activity Animation */
.new-activity {
    background: linear-gradient(90deg, rgba(13, 202, 240, 0.1), transparent) !important;
    border-left: 3px solid #0dcaf0 !important;
    padding-left: 0.5rem !important;
    margin-left: -0.5rem !important;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .community-feed {
        max-height: 200px;
    }
    
    .alert.border-start {
        padding-left: 0.75rem;
        font-size: 0.9rem;
    }
}
</style>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 