<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/functions.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';
require_once __DIR__ . '/../core/promotional_ads_manager.php';
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
    'weekly_limit' => $weekly_vote_limit
];

// Require user role
require_role('user');

$user_id = $_SESSION['user_id']; // Use logged-in user ID instead of just IP

// Get user data and avatar info (same system as other pages)
$user_data = $_SESSION['user_data'] ?? ['username' => 'User'];

// Get equipped avatar (same system as dashboard, profile, and spin)
$equipped_avatar_id = getUserEquippedAvatar();
$avatar_filename = getAvatarFilename($equipped_avatar_id);

// Get QR Coin balance (NEW SYSTEM - consistent with dashboard)
$user_points = QRCoinManager::getBalance($user_id);

// Get comprehensive stats for other metrics
$stats = getUserStats($user_id, get_client_ip());
$user_stats = $stats['voting_stats'];
$spin_stats = $stats['spin_stats'];

// Get user's success rate (items they voted IN that are still active vs voted OUT that are inactive)
$stmt = $pdo->prepare("
    SELECT 
        v.vote_type,
        i.status,
        COUNT(*) as count
    FROM votes v
    JOIN items i ON v.item_id = i.id
    WHERE v.user_id = ? OR v.voter_ip = ?
    GROUP BY v.vote_type, i.status
");
$stmt->execute([$user_id, get_client_ip()]);
$success_data = $stmt->fetchAll();

// Calculate success rates
$vote_in_active = 0;
$vote_in_inactive = 0;
$vote_out_active = 0;
$vote_out_inactive = 0;

foreach ($success_data as $row) {
    if (in_array($row['vote_type'], ['in', 'vote_in']) && $row['status'] === 'active') {
        $vote_in_active = $row['count'];
    } elseif (in_array($row['vote_type'], ['in', 'vote_in']) && $row['status'] === 'inactive') {
        $vote_in_inactive = $row['count'];
    } elseif (in_array($row['vote_type'], ['out', 'vote_out']) && $row['status'] === 'active') {
        $vote_out_active = $row['count'];
    } elseif (in_array($row['vote_type'], ['out', 'vote_out']) && $row['status'] === 'inactive') {
        $vote_out_inactive = $row['count'];
    }
}

$vote_in_success_rate = ($vote_in_active + $vote_in_inactive) > 0 ? 
    ($vote_in_active / ($vote_in_active + $vote_in_inactive)) * 100 : 0;
$vote_out_success_rate = ($vote_out_active + $vote_out_inactive) > 0 ? 
    ($vote_out_inactive / ($vote_out_active + $vote_out_inactive)) * 100 : 0;

// ENHANCED: Get QR Coin earning breakdown from voting
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
$stmt->execute([$user_id]);
$coin_analytics = $stmt->fetchAll();

// Process coin analytics
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

// ENHANCED: Get user's most voted items with business impact
$stmt = $pdo->prepare("
    SELECT 
        i.name as item_name,
        i.price as item_price,
        m.name as machine_name,
        b.name as business_name,
        b.id as business_id,
        v.vote_type,
        i.status,
        COUNT(*) as vote_count,
        MAX(v.created_at) as last_vote,
        -- Get total votes for this item from all users
        (SELECT COUNT(*) FROM votes v2 WHERE v2.item_id = i.id) as total_item_votes,
        -- Get user's influence percentage
        ROUND((COUNT(*) * 100.0) / NULLIF((SELECT COUNT(*) FROM votes v3 WHERE v3.item_id = i.id), 0), 1) as influence_percentage
    FROM votes v
    JOIN items i ON v.item_id = i.id
    JOIN machines m ON i.machine_id = m.id
    JOIN businesses b ON m.business_id = b.id
    WHERE v.user_id = ? OR v.voter_ip = ?
    GROUP BY i.id, v.vote_type
    ORDER BY vote_count DESC, last_vote DESC
    LIMIT 10
");
$stmt->execute([$user_id, get_client_ip()]);
$top_voted_items = $stmt->fetchAll();

// ENHANCED: Get voting device and browser analytics
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(device_type, 'Unknown') as device_type,
        COALESCE(browser, 'Unknown') as browser,
        vote_type,
        COUNT(*) as vote_count,
        MAX(DATE(created_at)) as latest_vote_date
    FROM votes 
    WHERE user_id = ? OR voter_ip = ?
    GROUP BY COALESCE(device_type, 'Unknown'), COALESCE(browser, 'Unknown'), vote_type
    ORDER BY vote_count DESC
");
$stmt->execute([$user_id, get_client_ip()]);
$device_analytics = $stmt->fetchAll();

// ENHANCED: Get user's store purchase analytics
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
$stmt->execute([$user_id]);
$store_purchases = $stmt->fetchAll();

// Calculate store analytics
$total_store_purchases = count($store_purchases);
$total_store_spent = array_sum(array_column($store_purchases, 'qr_coins_spent'));

// ENHANCED: Get voting trends by day of week and hour with business context
$stmt = $pdo->prepare("
    SELECT 
        DAYNAME(v.created_at) as day_name,
        HOUR(v.created_at) as hour,
        COUNT(*) as vote_count,
        COUNT(CASE WHEN v.vote_type = 'vote_in' THEN 1 END) as votes_in,
        COUNT(CASE WHEN v.vote_type = 'vote_out' THEN 1 END) as votes_out,
        COUNT(DISTINCT b.id) as businesses_influenced
    FROM votes v
    JOIN items i ON v.item_id = i.id
    JOIN machines m ON i.machine_id = m.id  
    JOIN businesses b ON m.business_id = b.id
    WHERE v.user_id = ? OR v.voter_ip = ?
    GROUP BY DAYNAME(v.created_at), HOUR(v.created_at)
    ORDER BY vote_count DESC
    LIMIT 8
");
$stmt->execute([$user_id, get_client_ip()]);
$voting_patterns = $stmt->fetchAll();

// ENHANCED: Get comparison with other users (more detailed ranking)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as rank_position
    FROM (
        SELECT user_id, COUNT(*) as vote_count
        FROM votes
        WHERE user_id IS NOT NULL
        GROUP BY user_id
        HAVING vote_count > ?
    ) as higher_voters
");
$stmt->execute([$user_stats['total_votes']]);
$user_rank = $stmt->fetchColumn() + 1;

// Get total registered voters for better context
$stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) as total_voters FROM votes WHERE user_id IS NOT NULL");
$total_voters = $stmt->fetchColumn();

// ENHANCED: Get recent trending items with engagement scores
$stmt = $pdo->query("
    SELECT 
        i.name as item_name,
        i.price as item_price,
        m.name as machine_name,
        b.name as business_name,
        COUNT(*) as recent_votes,
        COUNT(CASE WHEN v.vote_type IN ('in', 'vote_in') THEN 1 END) as votes_in,
        COUNT(CASE WHEN v.vote_type IN ('out', 'vote_out') THEN 1 END) as votes_out,
        COUNT(DISTINCT v.user_id) as unique_voters,
        -- Engagement score based on vote diversity and recency
        ROUND(
            (COUNT(*) * 0.4) + 
            (COUNT(DISTINCT v.user_id) * 0.6) + 
            (DATEDIFF(NOW(), MAX(v.created_at)) * -0.1)
        , 1) as engagement_score
    FROM votes v
    JOIN items i ON v.item_id = i.id
    JOIN machines m ON i.machine_id = m.id
    JOIN businesses b ON m.business_id = b.id
    WHERE v.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY i.id
    ORDER BY engagement_score DESC, recent_votes DESC
    LIMIT 5
");
$trending_items = $stmt->fetchAll();

// ENHANCED: Get user's voting streaks with reward context
$stmt = $pdo->prepare("
    SELECT 
        DATE(v.created_at) as vote_date,
        COUNT(*) as votes_that_day,
        -- Check if user earned daily bonus that day
        MAX(CASE 
            WHEN EXISTS(
                SELECT 1 FROM qr_coin_transactions t 
                WHERE t.user_id = ? 
                AND DATE(t.created_at) = DATE(v.created_at)
                AND t.category = 'daily_vote'
            ) THEN 1 
            ELSE 0 
        END) as earned_daily_bonus
    FROM votes v
    WHERE v.user_id = ? OR v.voter_ip = ?
    GROUP BY DATE(v.created_at)
    ORDER BY vote_date DESC
");
$stmt->execute([$user_id, $user_id, get_client_ip()]);
$voting_dates_detailed = $stmt->fetchAll();

// Calculate current streak (improved logic)
$current_streak = 0;
$max_streak = 0;
$temp_streak = 0;
$today = new DateTime();
$total_voting_days = count($voting_dates_detailed);

if (!empty($voting_dates_detailed)) {
    foreach ($voting_dates_detailed as $i => $date_data) {
        $vote_date = new DateTime($date_data['vote_date']);
        
        if ($i === 0) {
            // Check if most recent vote was today or yesterday
            $diff = $today->diff($vote_date)->days;
            if ($diff <= 1) {
                $current_streak = 1;
                $temp_streak = 1;
            }
        } else {
            $prev_date = new DateTime($voting_dates_detailed[$i-1]['vote_date']);
            $diff = $prev_date->diff($vote_date)->days;
            
            if ($diff === 1) {
                $temp_streak++;
                if ($i < 7) $current_streak = $temp_streak; // Only count as current if recent
            } else {
                $max_streak = max($max_streak, $temp_streak);
                $temp_streak = 1;
            }
        }
    }
    $max_streak = max($max_streak, $temp_streak);
}

// ENHANCED: Cross-reference with business engagement
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
$stmt->execute([$user_id, get_client_ip()]);
$business_engagement = $stmt->fetchAll();

// Get promotional ads for vote page
$adsManager = new PromotionalAdsManager($pdo);
$promotional_ads = $adsManager->getAdsForPage('vote', 2);

// Track ad views for analytics
foreach ($promotional_ads as $ad) {
    $adsManager->trackView($ad['id'], $user_id, 'vote');
}

// ENHANCED: Get recent weekly winners to display
$stmt = $pdo->prepare("
    SELECT 
        ww.item_name,
        ww.vote_count,
        ww.winner_type,
        ww.week_year,
        vl.name as machine_name,
        vl.location,
        b.name as business_name,
        DATE_FORMAT(STR_TO_DATE(CONCAT(ww.week_year, ' Monday'), '%X-%V %W'), '%M %e') as week_start_formatted
    FROM weekly_winners ww
    JOIN voting_lists vl ON ww.voting_list_id = vl.id
    JOIN businesses b ON vl.business_id = b.id
    ORDER BY ww.created_at DESC, ww.vote_count DESC
    LIMIT 12
");
$stmt->execute();
$recent_winners = $stmt->fetchAll();

// Group winners by week for better display
$winners_by_week = [];
foreach ($recent_winners as $winner) {
    $week_key = $winner['week_year'];
    if (!isset($winners_by_week[$week_key])) {
        $winners_by_week[$week_key] = [
            'week_formatted' => $winner['week_start_formatted'],
            'winners' => []
        ];
    }
    $winners_by_week[$week_key]['winners'][] = $winner;
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
.vote-history-table {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 12px !important;
    color: #ffffff;
}

.vote-history-table th {
    background: rgba(255, 255, 255, 0.1) !important;
    border: none !important;
    color: #ffffff !important;
    font-weight: 600;
}

.vote-history-table td {
    background: transparent !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    color: #ffffff !important;
}
</style>

<!-- User Avatar Header Section -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex align-items-center justify-content-between p-3 rounded" style="background: linear-gradient(135deg, rgba(25, 118, 210, 0.1) 0%, rgba(21, 101, 192, 0.05) 100%);">
            <div class="d-flex align-items-center">
                <div class="me-3">
                    <img src="../assets/img/avatars/<?php echo $avatar_filename; ?>" 
                         alt="User Avatar"
                         class="rounded-circle border border-3 border-primary"
                         style="width: 80px; height: 80px; object-fit: cover;"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <div class="d-none text-primary" style="font-size: 80px;">
                        <i class="bi bi-person-circle"></i>
                    </div>
                </div>
                <div>
                    <h2 class="mb-1">
                        <i class="bi bi-ballot-fill me-2 text-primary"></i>
                        Voting Dashboard
                    </h2>
                    <p class="text-muted mb-0">Welcome back, <?php echo htmlspecialchars($user_data['username']); ?>! Track your voting impact and analytics.</p>
                </div>
            </div>
            <div class="text-end">
                <div class="small text-muted">Current Balance</div>
                <div class="fw-bold h4 text-success mb-0">
                    <img src="../img/qrCoin.png" alt="QR Coin" style="width: 1.2em; height: 1.2em;" class="me-1">
                    <?php echo number_format($user_points); ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Weekly Winners Section -->
<?php if (!empty($recent_winners)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-trophy-fill text-warning me-2"></i>🏆 Weekly Voting Winners
                    </h5>
                    <span class="badge bg-success">Live Results</span>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">See the impact of community voting! These items won (or lost) based on your collective votes.</p>
                
                <?php foreach ($winners_by_week as $week_key => $week_data): ?>
                    <div class="mb-4">
                        <h6 class="text-primary mb-3">
                            <i class="bi bi-calendar-week me-2"></i>Week of <?php echo $week_data['week_formatted']; ?>, 2025
                        </h6>
                        
                        <div class="row g-3">
                            <?php foreach ($week_data['winners'] as $winner): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card h-100 border-start border-4 <?php echo $winner['winner_type'] === 'vote_in' ? 'border-success' : 'border-danger'; ?> bg-light">
                                        <div class="card-body p-3">
                                            <div class="d-flex align-items-start justify-content-between mb-2">
                                                <div class="flex-grow-1">
                                                    <h6 class="card-title mb-1 fw-bold">
                                                        <?php echo htmlspecialchars($winner['item_name']); ?>
                                                    </h6>
                                                    <p class="text-muted small mb-1">
                                                        <i class="bi bi-building me-1"></i><?php echo htmlspecialchars($winner['business_name']); ?>
                                                    </p>
                                                    <p class="text-muted small mb-2">
                                                        <i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($winner['machine_name']); ?>
                                                        <?php if ($winner['location']): ?>
                                                            - <?php echo htmlspecialchars($winner['location']); ?>
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                                <div class="text-end">
                                                    <?php if ($winner['winner_type'] === 'vote_in'): ?>
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-arrow-up-circle-fill me-1"></i>VOTED IN
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">
                                                            <i class="bi bi-arrow-down-circle-fill me-1"></i>VOTED OUT
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div class="text-primary fw-bold">
                                                    <i class="bi bi-people-fill me-1"></i>
                                                    <?php echo number_format($winner['vote_count']); ?> votes
                                                </div>
                                                <?php if ($winner['winner_type'] === 'vote_in'): ?>
                                                    <small class="text-success">
                                                        <i class="bi bi-check-circle-fill me-1"></i>New item in machine
                                                    </small>
                                                <?php else: ?>
                                                    <small class="text-danger">
                                                        <i class="bi bi-x-circle-fill me-1"></i>Remove from machine
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div class="text-center mt-4">
                    <div class="alert alert-info bg-info bg-opacity-10 border-info">
                        <h6 class="mb-2">
                            <i class="bi bi-info-circle me-2"></i>How Winners Are Determined
                        </h6>
                        <p class="mb-0 small">
                            Every week, the items with the most <strong>votes IN</strong> become new items in the machines, 
                            while items with the most <strong>votes OUT</strong> are removed. Winners take about a week to enter the machines. Your vote matters!
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Top Voted Items and Trends -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-heart-fill me-2"></i>Your Most Voted Items</h5>
            </div>
            <div class="card-body">
                <?php if (empty($top_voted_items)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-inbox display-1 text-muted mb-3"></i>
                        <p class="text-muted">No voting history yet</p>
                        <small>Scan QR codes at vending machines to start voting!</small>
                    </div>
                <?php else: ?>
                    <?php foreach ($top_voted_items as $item): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                            <div>
                                <h6 class="mb-1"><?php echo htmlspecialchars($item['item_name']); ?></h6>
                                <small class="text-muted">
                                    <i class="bi bi-building me-1"></i><?php echo htmlspecialchars($item['business_name']); ?> • 
                                    <i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($item['machine_name']); ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-<?php echo in_array($item['vote_type'], ['in', 'vote_in']) ? 'success' : 'danger'; ?>">
                                    <?php echo in_array($item['vote_type'], ['in', 'vote_in']) ? 'Voted IN' : 'Voted OUT'; ?>
                                </span>
                                <div class="small text-muted"><?php echo $item['vote_count']; ?> votes</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-trending-up me-2"></i>Trending This Week</h5>
            </div>
            <div class="card-body">
                <?php if (empty($trending_items)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-graph-up display-1 text-muted mb-3"></i>
                        <p class="text-muted">No trending data yet</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($trending_items as $item): ?>
                        <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($item['item_name']); ?></h6>
                                        <small class="text-muted">
                                            <i class="bi bi-building me-1"></i><?php echo htmlspecialchars($item['business_name']); ?>
                                            <?php if ($item['item_price'] > 0): ?>
                                                • <i class="bi bi-cash me-1"></i>$<?php echo number_format($item['item_price'], 2); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <span class="badge bg-warning">
                                        <i class="bi bi-lightning-bolt-fill me-1"></i><?php echo $item['engagement_score']; ?>
                                    </span>
                                </div>
                                <div class="d-flex gap-1 mb-1 mt-2">
                                    <span class="badge bg-success"><?php echo $item['votes_in']; ?> IN</span>
                                    <span class="badge bg-danger"><?php echo $item['votes_out']; ?> OUT</span>
                                    <span class="badge bg-info"><?php echo $item['unique_voters']; ?> voters</span>
                                </div>
                                <small class="text-muted"><?php echo $item['recent_votes']; ?> total votes this week</small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Voting Patterns -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Your Voting Patterns</h5>
            </div>
            <div class="card-body">
                <?php if (empty($voting_patterns)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-calendar-week display-1 text-muted mb-3"></i>
                        <p class="text-muted">Not enough data for pattern analysis</p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($voting_patterns as $pattern): ?>
                            <div class="col-md-2 mb-3">
                                <div class="text-center p-3 border rounded">
                                    <i class="bi bi-clock text-primary mb-2"></i>
                                    <h6><?php echo $pattern['day_name']; ?></h6>
                                    <p class="mb-0"><?php echo sprintf('%02d:00', $pattern['hour']); ?></p>
                                    <small class="text-muted"><?php echo $pattern['vote_count']; ?> votes</small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Insight:</strong> Your most active voting times help businesses understand peak engagement periods.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Weekly Winners Section -->
<?php if (!empty($recent_winners)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-trophy-fill text-warning me-2"></i>🏆 Weekly Voting Winners
                    </h5>
                    <span class="badge bg-success">Live Results</span>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">See the impact of community voting! These items won (or lost) based on your collective votes.</p>
                
                <?php foreach ($winners_by_week as $week_key => $week_data): ?>
                    <div class="mb-4">
                        <h6 class="text-primary mb-3">
                            <i class="bi bi-calendar-week me-2"></i>Week of <?php echo $week_data['week_formatted']; ?>, 2025
                        </h6>
                        
                        <div class="row g-3">
                            <?php foreach ($week_data['winners'] as $winner): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card h-100 border-start border-4 <?php echo $winner['winner_type'] === 'vote_in' ? 'border-success' : 'border-danger'; ?> bg-light">
                                        <div class="card-body p-3">
                                            <div class="d-flex align-items-start justify-content-between mb-2">
                                                <div class="flex-grow-1">
                                                    <h6 class="card-title mb-1 fw-bold">
                                                        <?php echo htmlspecialchars($winner['item_name']); ?>
                                                    </h6>
                                                    <p class="text-muted small mb-1">
                                                        <i class="bi bi-building me-1"></i><?php echo htmlspecialchars($winner['business_name']); ?>
                                                    </p>
                                                    <p class="text-muted small mb-2">
                                                        <i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($winner['machine_name']); ?>
                                                        <?php if ($winner['location']): ?>
                                                            - <?php echo htmlspecialchars($winner['location']); ?>
                                                        <?php endif; ?>
                                                    </p>
                                                </div>
                                                <div class="text-end">
                                                    <?php if ($winner['winner_type'] === 'vote_in'): ?>
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-arrow-up-circle-fill me-1"></i>VOTED IN
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">
                                                            <i class="bi bi-arrow-down-circle-fill me-1"></i>VOTED OUT
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="d-flex align-items-center justify-content-between">
                                                <div class="text-primary fw-bold">
                                                    <i class="bi bi-people-fill me-1"></i>
                                                    <?php echo number_format($winner['vote_count']); ?> votes
                                                </div>
                                                <?php if ($winner['winner_type'] === 'vote_in'): ?>
                                                    <small class="text-success">
                                                        <i class="bi bi-check-circle-fill me-1"></i>New item in machine
                                                    </small>
                                                <?php else: ?>
                                                    <small class="text-danger">
                                                        <i class="bi bi-x-circle-fill me-1"></i>Remove from machine
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div class="text-center mt-4">
                    <div class="alert alert-info bg-info bg-opacity-10 border-info">
                        <h6 class="mb-2">
                            <i class="bi bi-info-circle me-2"></i>How Winners Are Determined
                        </h6>
                        <p class="mb-0 small">
                            Every week, the items with the most <strong>votes IN</strong> become new items in the machines, 
                            while items with the most <strong>votes OUT</strong> are removed. Winners take about a week to enter the machines. Your vote matters!
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ENHANCED ANALYTICS SECTIONS -->



<!-- Enhanced Item Influence & Device Analytics -->
<div class="row mb-4">
    <!-- Item Influence Details -->
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-bullseye me-2"></i>Your Item Influence
                    </h5>
                    <span class="badge bg-info">Your Impact</span>
                </div>
            </div>
            <div class="card-body bg-white">
                <?php if (empty($top_voted_items)): ?>
                    <div class="text-center py-4">
                        <i class="bi bi-inbox display-1 text-muted mb-3"></i>
                        <p class="text-muted">No voting history yet</p>
                        <small>Scan QR codes at vending machines to start voting!</small>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm vote-history-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Your Vote</th>
                                    <th>Your Influence</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($top_voted_items, 0, 8) as $item): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($item['business_name']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo in_array($item['vote_type'], ['in', 'vote_in']) ? 'success' : 'danger'; ?> me-1">
                                                <?php echo in_array($item['vote_type'], ['in', 'vote_in']) ? 'IN' : 'OUT'; ?>
                                            </span>
                                            <small><?php echo $item['vote_count']; ?>x</small>
                                        </td>
                                        <td>
                                            <div class="progress" style="height: 6px; background-color: rgba(255, 255, 255, 0.2);">
                                                <div class="progress-bar bg-info" style="width: <?php echo min($item['influence_percentage'], 100); ?>%"></div>
                                            </div>
                                            <small><?php echo $item['influence_percentage']; ?>% influence</small>
                                        </td>
                                        <td>
                                            <?php if ($item['item_price']): ?>
                                                <span class="fw-bold">$<?php echo number_format($item['item_price'], 2); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $item['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                <?php echo ucfirst($item['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Device & Timing Analytics -->
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-phone me-2"></i>Usage Analytics
                    </h5>
                    <span class="badge bg-secondary">Stats</span>
                </div>
            </div>
            <div class="card-body bg-white">
                <!-- Voting Streak Info -->
                <div class="mb-3 p-3 bg-primary bg-opacity-10 rounded">
                    <div class="text-center">
                        <i class="bi bi-fire text-warning mb-2" style="font-size: 2rem;"></i>
                        <h4 class="text-primary mb-1"><?php echo $current_streak; ?></h4>
                        <small class="text-muted">Day Voting Streak</small>
                        <div class="mt-2">
                            <small class="text-muted">Best: <?php echo $max_streak; ?> days</small>
                        </div>
                    </div>
                </div>

                <!-- Device Breakdown -->
                <?php if (!empty($device_analytics)): ?>
                    <h6 class="mb-2">
                        <i class="bi bi-laptop me-1"></i>Device Breakdown
                    </h6>
                    <?php 
                    $device_summary = [];
                    foreach ($device_analytics as $device) {
                        if (!isset($device_summary[$device['device_type']])) {
                            $device_summary[$device['device_type']] = 0;
                        }
                        $device_summary[$device['device_type']] += $device['vote_count'];
                    }
                    arsort($device_summary);
                    ?>
                    <?php foreach (array_slice($device_summary, 0, 3) as $device => $count): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small"><?php echo ucfirst($device); ?></span>
                            <span class="badge bg-info"><?php echo $count; ?> votes</span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Total Voting Days -->
                <div class="mt-3 pt-3 border-top">
                    <div class="text-center">
                        <i class="bi bi-calendar-check text-success mb-2"></i>
                        <div class="fw-bold"><?php echo $total_voting_days; ?></div>
                        <small class="text-muted">Total Voting Days</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Business Promotional Ads -->
<?php if (!empty($promotional_ads)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card bg-transparent border-0">
            <div class="card-header bg-transparent border-0 pb-2">
                <h6 class="mb-0 text-muted">
                    <i class="bi bi-megaphone me-2"></i>Featured by Local Businesses
                </h6>
            </div>
            <div class="card-body pt-0">
                <div class="row g-3">
                    <?php foreach ($promotional_ads as $ad): ?>
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm h-100" 
                             style="background: linear-gradient(135deg, <?php echo $ad['background_color']; ?> 0%, <?php echo $ad['background_color']; ?>dd 100%); color: <?php echo $ad['text_color']; ?>;">
                            <div class="card-body">
                                <div class="d-flex align-items-start">
                                    <?php if ($ad['business_logo']): ?>
                                        <img src="<?php echo APP_URL . '/' . htmlspecialchars($ad['business_logo']); ?>" 
                                             alt="<?php echo htmlspecialchars($ad['business_name']); ?>" 
                                             class="rounded me-3" 
                                             style="width: 50px; height: 50px; object-fit: contain; background: white; padding: 5px;">
                                    <?php else: ?>
                                        <div class="rounded me-3 d-flex align-items-center justify-content-center text-white" 
                                             style="width: 50px; height: 50px; background: rgba(255,255,255,0.2);">
                                            <i class="bi bi-building fs-4"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="mb-0" style="color: <?php echo $ad['text_color']; ?>;">
                                                <?php echo htmlspecialchars($ad['ad_title']); ?>
                                            </h6>
                                            <span class="badge" style="background: rgba(255,255,255,0.2); color: <?php echo $ad['text_color']; ?>;">
                                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $ad['feature_type']))); ?>
                                            </span>
                                        </div>
                                        
                                        <p class="small mb-3 opacity-75" style="color: <?php echo $ad['text_color']; ?>;">
                                            <?php echo htmlspecialchars($ad['ad_description']); ?>
                                        </p>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="opacity-50" style="color: <?php echo $ad['text_color']; ?>;">
                                                <?php echo htmlspecialchars($ad['business_name']); ?>
                                            </small>
                                            <a href="<?php echo htmlspecialchars($ad['ad_cta_url'] ?: '#'); ?>" 
                                               class="btn btn-sm"
                                               style="background: rgba(255,255,255,0.2); color: <?php echo $ad['text_color']; ?>; border: 1px solid rgba(255,255,255,0.3);"
                                               onclick="trackAdClick(<?php echo $ad['id']; ?>)">
                                                <i class="bi bi-arrow-right me-1"></i><?php echo htmlspecialchars($ad['ad_cta_text']); ?>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Engagement Hub -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card bg-gradient bg-secondary text-white">
            <div class="card-header bg-transparent border-0">
                <h5 class="mb-0"><i class="bi bi-stars me-2"></i>🎯 Engagement Hub - More Ways to Earn & Win!</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <!-- Spin Wheel -->
                    <div class="col-md-3">
                        <div class="card bg-white bg-opacity-15 border-0 h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-trophy-fill display-4 text-warning mb-3"></i>
                                <h6 class="text-white mb-2">🎲 Spin to Win</h6>
                                <p class="text-white-50 small mb-3">Daily spins for QR Coins & prizes</p>
                                <a href="<?php echo APP_URL; ?>/user/spin.php" class="btn btn-warning btn-sm">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Spin Now
                                </a>
                                <div class="mt-2">
                                    <span class="badge bg-warning text-dark">+25 QR Coins</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Casino -->
                    <div class="col-md-3">
                        <div class="card bg-white bg-opacity-15 border-0 h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-dice-5-fill display-4 text-danger mb-3"></i>
                                <h6 class="text-white mb-2">🎰 QR Casino</h6>
                                <p class="text-white-50 small mb-3">Slot machines & jackpots</p>
                                <a href="<?php echo APP_URL; ?>/casino/index.php" class="btn btn-danger btn-sm">
                                    <i class="bi bi-play-circle me-1"></i>Play Now
                                </a>
                                <div class="mt-2">
                                    <span class="badge bg-danger">10 Free Spins</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pizza Tracker -->
                    <div class="col-md-3">
                        <div class="card bg-white bg-opacity-15 border-0 h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-stopwatch display-4 text-info mb-3"></i>
                                <h6 class="text-white mb-2">🍕 Pizza Tracker</h6>
                                <p class="text-white-50 small mb-3">Track your food orders</p>
                                <a href="<?php echo APP_URL; ?>/public/pizza-tracker.php" class="btn btn-info btn-sm">
                                    <i class="bi bi-search me-1"></i>Track Order
                                </a>
                                <div class="mt-2">
                                    <span class="badge bg-info">Real-time</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- QR Avatars -->
                    <div class="col-md-3">
                        <div class="card bg-white bg-opacity-15 border-0 h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-person-badge-fill display-4 text-primary mb-3"></i>
                                <h6 class="text-white mb-2">👤 QR Avatars</h6>
                                <p class="text-white-50 small mb-3">Customize your profile</p>
                                <a href="<?php echo APP_URL; ?>/user/avatars.php" class="btn btn-primary btn-sm">
                                    <i class="bi bi-palette me-1"></i>Customize
                                </a>
                                <div class="mt-2">
                                    <span class="badge bg-primary">Unlock More</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- QR Stores & Shopping -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card bg-gradient bg-success text-white">
            <div class="card-header bg-transparent border-0">
                <h5 class="mb-0"><i class="bi bi-shop me-2"></i>🛒 QR Stores - Spend Your Coins!</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <!-- QR Store -->
                    <div class="col-md-4">
                        <div class="card bg-white bg-opacity-15 border-0 h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-gem display-4 text-warning mb-3"></i>
                                <h6 class="text-white mb-2">💎 QR Store</h6>
                                <p class="text-white-50 small mb-3">Premium avatars & features</p>
                                <a href="<?php echo APP_URL; ?>/user/qr-store.php" class="btn btn-warning btn-sm">
                                    <i class="bi bi-gem me-1"></i>Browse Store
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Business Stores -->
                    <div class="col-md-4">
                        <div class="card bg-white bg-opacity-15 border-0 h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-building display-4 text-info mb-3"></i>
                                <h6 class="text-white mb-2">🏢 Business Discounts</h6>
                                <p class="text-white-50 small mb-3">Local vending discounts</p>
                                <a href="<?php echo APP_URL; ?>/user/business-stores.php" class="btn btn-info btn-sm">
                                    <i class="bi bi-shop me-1"></i>Find Deals
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Transaction History -->
                    <div class="col-md-4">
                        <div class="card bg-white bg-opacity-15 border-0 h-100">
                            <div class="card-body text-center">
                                <i class="bi bi-clock-history display-4 text-secondary mb-3"></i>
                                <h6 class="text-white mb-2">📋 My Purchases</h6>
                                <p class="text-white-50 small mb-3">Transaction history</p>
                                <a href="<?php echo APP_URL; ?>/user/qr-transactions.php" class="btn btn-light btn-sm">
                                    <i class="bi bi-list me-1"></i>View History
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Voting Action Section -->
<div id="voting-section" class="row">
    <div class="col-12">
        <div class="card bg-gradient bg-primary text-white">
            <div class="card-header bg-transparent border-0">
                <h4 class="text-white mb-0">
                    <i class="bi bi-qr-code-scan me-2"></i>Ready to Vote?
                </h4>
            </div>
            <div class="card-body text-center py-4">
                <i class="bi bi-qr-code-scan display-1 mb-3"></i>
                <h3>Start Voting Now!</h3>
                <p class="mb-4">Scan QR codes at vending machines to cast your votes and improve these statistics!</p>
                
                <!-- Simple Voting Status -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="bg-white bg-opacity-15 rounded p-3">
                            <i class="bi bi-check-circle-fill text-success mb-2" style="font-size: 2rem;"></i>
                            <h5 class="text-white"><?php echo $vote_status['votes_used']; ?></h5>
                            <small class="text-white-50">Votes used this week</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-white bg-opacity-15 rounded p-3">
                            <i class="bi bi-star-fill text-info mb-2" style="font-size: 2rem;"></i>
                            <h5 class="text-white"><?php echo $vote_status['votes_remaining']; ?></h5>
                            <small class="text-white-50">Votes remaining</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="bg-white bg-opacity-15 rounded p-3">
                            <i class="bi bi-calendar-week text-warning mb-2" style="font-size: 2rem;"></i>
                            <h5 class="text-white"><?php echo $vote_status['weekly_limit']; ?></h5>
                            <small class="text-white-50">Weekly limit</small>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="d-flex gap-3 justify-content-center flex-wrap mb-3">
                    <a href="<?php echo APP_URL; ?>/user/qr-store.php?category=vote_pack" class="btn btn-warning btn-lg">
                        <i class="bi bi-gift-fill me-2"></i>Get More Vote Packs
                    </a>
                    <a href="<?php echo APP_URL; ?>/user/dashboard.php" class="btn btn-light btn-lg">
                        <i class="bi bi-speedometer2 me-2"></i>Back to Dashboard
                    </a>
                </div>
                
                <!-- Additional Navigation -->
                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <a href="<?php echo APP_URL; ?>/user/leaderboard.php?filter=votes" class="btn btn-outline-light">
                        <i class="bi bi-trophy me-2"></i>View Leaderboard
                    </a>
                    <a href="<?php echo APP_URL; ?>/user/result.php" class="btn btn-outline-light">
                        <i class="bi bi-graph-up me-2"></i>My Results
                    </a>
                                            <a href="<?php echo APP_URL; ?>/user/qr-store.php" class="btn btn-outline-light">
                            <i class="bi bi-shop me-2"></i>QR Store
                        </a>
                </div>
                
                <!-- Instructions -->
                <div class="mt-4 p-3 bg-white bg-opacity-10 rounded">
                    <h6 class="text-white mb-2">
                        <i class="bi bi-info-circle me-2"></i>How to Vote
                    </h6>
                    <div class="text-white-50 small text-start">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <strong>1.</strong> Find a RevenueQR-enabled vending machine
                                </div>
                                <div class="mb-2">
                                    <strong>2.</strong> Scan the QR code on the machine
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <strong>3.</strong> Browse items and vote IN or OUT
                                </div>
                                <div class="mb-2">
                                    <strong>4.</strong> Earn QR coins for each vote!
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Track promotional ad clicks
function trackAdClick(adId) {
    fetch('/api/track-ad-click.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            ad_id: adId,
            user_id: <?php echo $user_id ?? 'null'; ?>
        })
    }).catch(error => {
        console.log('Ad tracking error:', error);
    });
}

// Add some interactive elements
document.addEventListener('DOMContentLoaded', function() {
    // Animate the metric cards
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        setTimeout(() => {
            card.style.transition = 'all 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });
    
    // Add tooltips for better UX
    const tooltipElements = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipElements.forEach(element => {
        new bootstrap.Tooltip(element);
    });
});
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 