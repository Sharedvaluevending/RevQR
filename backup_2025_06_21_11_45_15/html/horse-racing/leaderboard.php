<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/functions.php';

// Require user role
require_role('user');

// Cache-busting headers to ensure fresh data
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Get filter and pagination parameters
$filter = $_GET['filter'] ?? 'winnings';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 25;
$offset = ($page - 1) * $per_page;

$valid_filters = ['winnings', 'races', 'winrate', 'bets', 'streak', 'total_earned'];
if (!in_array($filter, $valid_filters)) {
    $filter = 'winnings';
}

// Build leaderboard query based on filter
switch($filter) {
    case 'races':
        $orderBy = 'total_races_participated DESC, total_qr_coins_won DESC, user_id ASC';
        $title = '🏁 Race Participation';
        $subtitle = 'Most active racing participants';
        $primaryMetric = 'total_races_participated';
        $primaryLabel = 'Races';
        $icon = 'bi-flag-fill';
        break;
        
    case 'winrate':
        $orderBy = 'win_rate DESC, total_races_participated DESC, user_id ASC';
        $title = '🎯 Win Rate Champions';
        $subtitle = 'Best betting success rates (min. 5 races)';
        $primaryMetric = 'win_rate';
        $primaryLabel = '% Win Rate';
        $icon = 'bi-target';
        $minRaces = 5;
        break;
        
    case 'bets':
        $orderBy = 'total_bets_placed DESC, total_qr_coins_bet DESC, user_id ASC';
        $title = '💰 Top Bettors';
        $subtitle = 'Most bets placed across all races';
        $primaryMetric = 'total_bets_placed';
        $primaryLabel = 'Bets Placed';
        $icon = 'bi-currency-dollar';
        break;
        
    case 'streak':
        $orderBy = 'current_streak DESC, total_qr_coins_won DESC, user_id ASC';
        $title = '🔥 Current Streaks';
        $subtitle = 'Longest current winning streaks';
        $primaryMetric = 'current_streak';
        $primaryLabel = 'Win Streak';
        $icon = 'bi-fire';
        break;
        
    case 'total_earned':
        $orderBy = 'total_qr_coins_bet DESC, total_bets_placed DESC, user_id ASC';
        $title = '🎲 High Rollers';
        $subtitle = 'Total QR coins wagered in racing';
        $primaryMetric = 'total_qr_coins_bet';
        $primaryLabel = 'Total Wagered';
        $icon = 'bi-dice-6-fill';
        break;
        
    default: // winnings
        $orderBy = 'total_qr_coins_won DESC, win_rate DESC, user_id ASC';
        $title = '🏆 Top Winners';
        $subtitle = 'Biggest QR coin winners in horse racing';
        $primaryMetric = 'total_qr_coins_won';
        $primaryLabel = 'QR Coins Won';
        $icon = 'bi-trophy-fill';
        break;
}

// Get racing leaderboard data
$whereClause = '';
if (isset($minRaces)) {
    $whereClause = " AND urs.total_races_participated >= $minRaces";
}

$stmt = $pdo->prepare("
    SELECT 
        u.id as user_id,
        u.username,
        COALESCE(NULLIF(TRIM(u.username), ''), CONCAT('User_', u.id)) as display_name,
        COALESCE(u.equipped_avatar, 1) as equipped_avatar,
        
        -- Racing statistics
        COALESCE(urs.total_races_participated, 0) as total_races_participated,
        COALESCE(urs.total_bets_placed, 0) as total_bets_placed,
        COALESCE(urs.total_qr_coins_bet, 0) as total_qr_coins_bet,
        COALESCE(urs.total_qr_coins_won, 0) as total_qr_coins_won,
        COALESCE(urs.win_rate, 0.00) as win_rate,
        COALESCE(urs.favorite_horse_type, 'Unknown') as favorite_horse_type,
        COALESCE(urs.biggest_win_amount, 0) as biggest_win_amount,
        COALESCE(urs.current_streak, 0) as current_streak,
        COALESCE(urs.best_streak, 0) as best_streak,
        urs.last_race_participation,
        
        -- Net profit calculation
        (COALESCE(urs.total_qr_coins_won, 0) - COALESCE(urs.total_qr_coins_bet, 0)) as net_profit,
        
        -- Recent activity indicator
        CASE 
            WHEN urs.last_race_participation > DATE_SUB(NOW(), INTERVAL 7 DAYS) THEN 'recent'
            WHEN urs.last_race_participation > DATE_SUB(NOW(), INTERVAL 30 DAYS) THEN 'moderate'
            ELSE 'inactive'
        END as activity_status
        
    FROM users u
    LEFT JOIN user_racing_stats urs ON u.id = urs.user_id
    
    WHERE u.id IS NOT NULL AND u.id > 0
      AND urs.total_races_participated > 0
      {$whereClause}
      AND COALESCE(TRIM(u.username), '') != ''
      AND u.username NOT LIKE 'test%'
      AND u.username NOT LIKE 'dummy%'
    
    ORDER BY {$orderBy}
    LIMIT ? OFFSET ?
");

$stmt->execute([$per_page, $offset]);
$leaderboard_data = $stmt->fetchAll();

// Get total count for pagination
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM users u
    LEFT JOIN user_racing_stats urs ON u.id = urs.user_id
    WHERE u.id IS NOT NULL AND u.id > 0
      AND urs.total_races_participated > 0
      {$whereClause}
      AND COALESCE(TRIM(u.username), '') != ''
      AND u.username NOT LIKE 'test%'
      AND u.username NOT LIKE 'dummy%'
");
$countStmt->execute();
$total_users = $countStmt->fetchColumn();

// Calculate pagination
$total_pages = ceil($total_users / $per_page);

// Get current user's racing stats
$current_user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("
    SELECT urs.*, 
           (SELECT COUNT(*) + 1 
            FROM user_racing_stats urs2 
            WHERE urs2.total_qr_coins_won > urs.total_qr_coins_won) as rank_position
    FROM user_racing_stats urs 
    WHERE urs.user_id = ?
");
$stmt->execute([$current_user_id]);
$current_user_stats = $stmt->fetch();

// Get recent race highlights
$stmt = $pdo->prepare("
    SELECT br.race_name, br.start_time, rb.actual_winnings, rb.bet_amount_qr_coins,
           rh.horse_name, rh.slot_position
    FROM race_bets rb
    JOIN business_races br ON rb.race_id = br.id
    JOIN race_horses rh ON rb.horse_id = rh.id
    WHERE rb.user_id = ? AND rb.status = 'won' AND rb.actual_winnings > 0
    ORDER BY rb.bet_placed_at DESC
    LIMIT 5
");
$stmt->execute([$current_user_id]);
$recent_wins = $stmt->fetchAll();

require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
body {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
    min-height: 100vh;
    color: #ffffff;
}

.racing-header {
    background: linear-gradient(135deg, #8B4513 0%, #A0522D 50%, #CD853F 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
    position: relative;
    overflow: hidden;
}

.racing-header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
    opacity: 0.3;
}

.stats-card {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 16px !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
    color: #ffffff;
    transition: all 0.3s ease;
}

.stats-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4) !important;
    border: 1px solid rgba(255, 255, 255, 0.25) !important;
}

.leaderboard-card {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 16px !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
    color: #ffffff;
}

.filter-btn {
    background: rgba(255, 255, 255, 0.1) !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    color: #ffffff !important;
    border-radius: 8px !important;
    transition: all 0.3s ease;
}

.filter-btn:hover {
    background: rgba(255, 255, 255, 0.2) !important;
    transform: translateY(-1px);
}

.filter-btn.active {
    background: rgba(139, 69, 19, 0.8) !important;
    border: 1px solid rgba(160, 82, 45, 0.5) !important;
    color: #ffffff !important;
}

.rank-number {
    background: linear-gradient(45deg, #FFD700, #FFA500);
    color: #333;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    font-weight: bold;
}

.rank-1 { background: linear-gradient(45deg, #FFD700, #FFA500) !important; }
.rank-2 { background: linear-gradient(45deg, #C0C0C0, #A8A8A8) !important; }
.rank-3 { background: linear-gradient(45deg, #CD7F32, #B8860B) !important; }

.user-row {
    background: rgba(255, 255, 255, 0.08) !important;
    backdrop-filter: blur(10px) !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    border-radius: 12px !important;
    transition: all 0.3s ease;
    margin-bottom: 8px;
    padding: 12px 16px;
}

.user-row:hover {
    background: rgba(255, 255, 255, 0.15) !important;
    transform: translateY(-1px);
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
}

.avatar-display {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: 2px solid rgba(255, 255, 255, 0.3);
}

.metric-badge {
    background: rgba(139, 69, 19, 0.7);
    color: #ffffff;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.9rem;
    font-weight: bold;
}

.activity-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
}

.activity-recent { background: #28a745; }
.activity-moderate { background: #ffc107; }
.activity-inactive { background: #6c757d; }

.pagination-nav {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 12px !important;
    padding: 1rem;
}

.page-link {
    background: rgba(255, 255, 255, 0.1) !important;
    border: 1px solid rgba(255, 255, 255, 0.2) !important;
    color: #ffffff !important;
}

.page-link:hover {
    background: rgba(255, 255, 255, 0.2) !important;
    color: #ffffff !important;
}

.recent-wins {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 16px !important;
    color: #ffffff;
}

.leaderboard-table {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 12px !important;
    color: #ffffff;
}

.leaderboard-table th {
    background: rgba(255, 255, 255, 0.1) !important;
    border: none !important;
    color: #ffffff !important;
    font-weight: 600;
}

.leaderboard-table td {
    background: transparent !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    color: #ffffff !important;
}

.win-item {
    background: rgba(40, 167, 69, 0.2);
    border: 1px solid rgba(40, 167, 69, 0.3);
    border-radius: 8px;
    padding: 8px 12px;
    margin-bottom: 8px;
}
</style>

<div class="racing-header text-center">
    <div class="container position-relative">
        <h1 class="display-4 mb-3">🏇 Racing Leaderboard</h1>
        <p class="lead">Compete for glory in the ultimate horse racing championship</p>
        <div class="d-inline-flex align-items-center gap-3">
            <div class="d-flex align-items-center">
                <img src="../img/qrCoin.png" alt="QR Coin" style="width: 20px; height: 20px;" class="me-2">
                Total Prize Pool: <?php echo number_format(array_sum(array_column($leaderboard_data, 'total_qr_coins_won'))); ?> QR Coins
            </div>
            <div class="badge bg-warning text-dark p-2">
                Active Racers: <?php echo $total_users; ?>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <?php if ($current_user_stats): ?>
    <!-- Current User Stats -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="stats-card p-4">
                <h5 class="mb-3"><i class="bi bi-person-fill me-2"></i>Your Racing Performance</h5>
                <div class="row">
                    <div class="col-md-2 text-center">
                        <div class="rank-number mb-2">
                            #<?php echo $current_user_stats['rank_position']; ?>
                        </div>
                        <small>Current Rank</small>
                    </div>
                    <div class="col-md-2 text-center">
                        <div class="text-warning fw-bold h4 mb-1"><?php echo number_format($current_user_stats['total_qr_coins_won']); ?></div>
                        <small>QR Coins Won</small>
                    </div>
                    <div class="col-md-2 text-center">
                        <div class="text-info fw-bold h4 mb-1"><?php echo $current_user_stats['total_races_participated']; ?></div>
                        <small>Races Joined</small>
                    </div>
                    <div class="col-md-2 text-center">
                        <div class="text-success fw-bold h4 mb-1"><?php echo number_format($current_user_stats['win_rate'], 1); ?>%</div>
                        <small>Win Rate</small>
                    </div>
                    <div class="col-md-2 text-center">
                        <div class="text-danger fw-bold h4 mb-1"><?php echo $current_user_stats['current_streak']; ?></div>
                        <small>Current Streak</small>
                    </div>
                    <div class="col-md-2 text-center">
                        <div class="text-primary fw-bold h4 mb-1"><?php echo number_format($current_user_stats['biggest_win_amount']); ?></div>
                        <small>Biggest Win</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <!-- Filter Buttons -->
            <div class="d-flex flex-wrap gap-2 mb-4">
                <?php foreach ($valid_filters as $f): ?>
                    <?php 
                    $filter_labels = [
                        'winnings' => '🏆 Top Winners',
                        'races' => '🏁 Most Races', 
                        'winrate' => '🎯 Win Rate',
                        'bets' => '💰 Top Bettors',
                        'streak' => '🔥 Streaks',
                        'total_earned' => '🎲 High Rollers'
                    ];
                    ?>
                    <a href="?filter=<?php echo $f; ?>" 
                       class="btn filter-btn <?php echo $filter === $f ? 'active' : ''; ?>">
                        <?php echo $filter_labels[$f]; ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Leaderboard -->
            <div class="leaderboard-card">
                <div class="card-header p-4 border-0">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h4 class="mb-1"><i class="<?php echo $icon; ?> me-2"></i><?php echo $title; ?></h4>
                            <p class="text-white-50 mb-0"><?php echo $subtitle; ?></p>
                        </div>
                        <div class="text-end">
                            <div class="badge bg-secondary"><?php echo $total_users; ?> racers</div>
                        </div>
                    </div>
                </div>
                
                <div class="card-body p-0">
                    <?php if (empty($leaderboard_data)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-trophy display-1 text-muted mb-3"></i>
                            <h5>No racers found</h5>
                            <p class="text-white-50">Be the first to join a horse race!</p>
                            <a href="../horse-racing/" class="btn btn-primary">
                                <i class="bi bi-play-fill"></i> Join a Race
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($leaderboard_data as $index => $user): ?>
                            <?php 
                            $rank = $offset + $index + 1;
                            $is_current_user = ($user['user_id'] == $current_user_id);
                            $rank_class = '';
                            if ($rank <= 3) $rank_class = "rank-{$rank}";
                            ?>
                            <div class="user-row <?php echo $is_current_user ? 'border-warning' : ''; ?>">
                                <div class="d-flex align-items-center">
                                    <div class="rank-number <?php echo $rank_class; ?> me-3">
                                        <?php echo $rank; ?>
                                    </div>
                                    
                                    <img src="../assets/img/avatars/<?php echo getAvatarFilename($user['equipped_avatar']); ?>" 
                                         alt="Avatar" class="avatar-display me-3">
                                    
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center gap-2">
                                            <strong><?php echo htmlspecialchars($user['display_name']); ?></strong>
                                            <?php if ($is_current_user): ?>
                                                <span class="badge bg-warning text-dark">You</span>
                                            <?php endif; ?>
                                            <span class="activity-indicator activity-<?php echo $user['activity_status']; ?>" 
                                                  title="Activity: <?php echo ucfirst($user['activity_status']); ?>"></span>
                                        </div>
                                        <div class="text-white-50 small">
                                            <?php if ($user['favorite_horse_type'] !== 'Unknown'): ?>
                                                Favorite: <?php echo ucfirst($user['favorite_horse_type']); ?> • 
                                            <?php endif; ?>
                                            <?php echo $user['total_bets_placed']; ?> bets placed
                                        </div>
                                    </div>
                                    
                                    <div class="text-end">
                                        <div class="metric-badge mb-1">
                                            <?php 
                                            if ($primaryMetric === 'win_rate') {
                                                echo number_format($user[$primaryMetric], 1) . '%';
                                            } else {
                                                echo number_format($user[$primaryMetric]);
                                            }
                                            ?>
                                        </div>
                                        <div class="text-white-50 small">
                                            <?php 
                                            $net_profit = $user['total_qr_coins_won'] - $user['total_qr_coins_bet'];
                                            $profit_class = $net_profit >= 0 ? 'text-success' : 'text-danger';
                                            ?>
                                            <span class="<?php echo $profit_class; ?>">
                                                <?php echo $net_profit >= 0 ? '+' : ''; ?><?php echo number_format($net_profit); ?> profit
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination-nav mt-4">
                <nav aria-label="Leaderboard pagination">
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo max(1, $page - 1); ?>">Previous</a>
                        </li>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo min($total_pages, $page + 1); ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-md-4">
            <!-- Recent Wins -->
            <?php if (!empty($recent_wins)): ?>
            <div class="recent-wins p-4 mb-4">
                <h5 class="mb-3"><i class="bi bi-trophy-fill me-2 text-warning"></i>Your Recent Wins</h5>
                <?php foreach ($recent_wins as $win): ?>
                    <div class="win-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?php echo htmlspecialchars($win['horse_name']); ?></strong>
                                <br>
                                <small class="text-white-50"><?php echo $win['slot_position']; ?> • <?php echo date('M j', strtotime($win['start_time'])); ?></small>
                            </div>
                            <div class="text-end">
                                <div class="text-success fw-bold">+<?php echo number_format($win['actual_winnings']); ?></div>
                                <small class="text-white-50">bet: <?php echo number_format($win['bet_amount_qr_coins']); ?></small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="stats-card p-4">
                <h5 class="mb-3"><i class="bi bi-lightning-fill me-2"></i>Quick Actions</h5>
                <div class="d-grid gap-2">
                    <a href="../horse-racing/" class="btn btn-primary">
                        <i class="bi bi-play-fill me-2"></i>Join a Race
                    </a>
                    <a href="../horse-racing/betting.php" class="btn btn-outline-light">
                        <i class="bi bi-currency-dollar me-2"></i>Place Bets
                    </a>
                    <a href="../user/leaderboard.php" class="btn btn-outline-secondary">
                        <i class="bi bi-graph-up me-2"></i>Overall Leaderboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?>