<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/functions.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';

// Require user role
require_role('user');

// Cache-busting headers to ensure fresh data
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Set up filter and ordering
$filter = $_GET['filter'] ?? 'level';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Set up ordering based on filter
switch ($filter) {
    case 'votes':
        $orderBy = 'total_votes DESC, total_activity DESC, user_id ASC';
        $title = 'Vote Leaders';
        $subtitle = 'Users ranked by total votes cast';
        $primaryMetric = 'total_votes';
        $primaryLabel = 'Total Votes';
        break;
        
    case 'spins':
        $orderBy = 'total_spins DESC, total_activity DESC, user_id ASC';
        $title = 'Spin Leaders';
        $subtitle = 'Users ranked by total spins';
        $primaryMetric = 'total_spins';
        $primaryLabel = 'Total Spins';
        break;
        
    case 'wins':
        $orderBy = 'real_wins DESC, total_activity DESC, user_id ASC';
        $title = 'Win Leaders';
        $subtitle = 'Users ranked by spin wheel wins';
        $primaryMetric = 'real_wins';
        $primaryLabel = 'Real Wins';
        break;
        
    case 'activity':
        $orderBy = 'total_activity DESC, user_id ASC';
        $title = 'Most Active';
        $subtitle = 'Users ranked by total engagement';
        $primaryMetric = 'total_activity';
        $primaryLabel = 'Total Actions';
        break;
        
    case 'streak':
        $orderBy = 'activity_days DESC, total_activity DESC, user_id ASC';
        $title = 'Streak Champions';
        $subtitle = 'Users ranked by consistent activity';
        $primaryMetric = 'activity_days';
        $primaryLabel = 'Active Days';
        break;
        
    case 'points':
        $orderBy = 'total_activity DESC, user_id ASC'; // Will be sorted by points in PHP
        $title = 'QR Coin Leaders';
        $subtitle = 'Users ranked by total QR coins earned';
        $primaryMetric = 'user_points';
        $primaryLabel = 'QR Coins';
        break;
        
    default: // level
        $orderBy = 'total_activity DESC, user_id ASC'; // Will be sorted by level in PHP
        $title = 'Level Leaders';
        $subtitle = 'Users ranked by level and experience';
        $primaryMetric = 'user_level';
        $primaryLabel = 'Level';
        break;
}

// Improved query to get leaderboard data - STRICT activity filtering to prevent duplicates
$stmt = $pdo->prepare("
    SELECT 
        u.id as user_id,
        u.username, 
        u.email,
        COALESCE(u.equipped_avatar, 1) as equipped_avatar,
        COALESCE(vote_stats.total_votes, 0) as total_votes,
        COALESCE(vote_stats.votes_in, 0) as votes_in,
        COALESCE(vote_stats.votes_out, 0) as votes_out,
        COALESCE(vote_stats.voting_days, 0) as voting_days,
        COALESCE(spin_stats.total_spins, 0) as total_spins,
        COALESCE(spin_stats.big_wins, 0) as big_wins,
        COALESCE(spin_stats.real_wins, 0) as real_wins,
        COALESCE(spin_stats.losses, 0) as losses,
        COALESCE(spin_stats.spin_days, 0) as spin_days,
        COALESCE(spin_stats.total_prize_points, 0) as total_prize_points,
        COALESCE(latest_ip.voter_ip, '') as voter_ip,
        (COALESCE(vote_stats.total_votes, 0) + COALESCE(spin_stats.total_spins, 0)) as total_activity,
        GREATEST(COALESCE(vote_stats.voting_days, 0), COALESCE(spin_stats.spin_days, 0)) as activity_days
    FROM users u
    
    -- Get user's most recent IP for display
    LEFT JOIN (
        SELECT 
            user_id,
            voter_ip,
            ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY created_at DESC) as rn
        FROM (
            SELECT user_id, voter_ip, created_at FROM votes WHERE user_id IS NOT NULL
            UNION ALL
            SELECT user_id, user_ip as voter_ip, spin_time as created_at FROM spin_results WHERE user_id IS NOT NULL
        ) ip_union
    ) latest_ip ON u.id = latest_ip.user_id AND latest_ip.rn = 1
    
    -- Vote stats - strict user_id matching
    LEFT JOIN (
        SELECT 
            user_id,
            COUNT(*) as total_votes,
            COUNT(CASE WHEN vote_type IN ('in', 'vote_in') THEN 1 END) as votes_in,
            COUNT(CASE WHEN vote_type IN ('out', 'vote_out') THEN 1 END) as votes_out,
            COUNT(DISTINCT DATE(created_at)) as voting_days
        FROM votes 
        WHERE user_id IS NOT NULL AND user_id > 0
        GROUP BY user_id
    ) vote_stats ON u.id = vote_stats.user_id
    
    -- Spin stats - strict user_id matching with prize points
    LEFT JOIN (
        SELECT 
            user_id,
            COUNT(*) as total_spins,
            COUNT(CASE WHEN is_big_win = 1 THEN 1 END) as big_wins,
            COUNT(CASE WHEN prize_won NOT IN ('No Prize', 'Lose All Votes', 'Try Again') THEN 1 END) as real_wins,
            COUNT(CASE WHEN prize_won IN ('No Prize', 'Lose All Votes') THEN 1 END) as losses,
            COUNT(DISTINCT DATE(spin_time)) as spin_days,
            COALESCE(SUM(prize_points), 0) as total_prize_points
        FROM spin_results 
        WHERE user_id IS NOT NULL AND user_id > 0
        GROUP BY user_id
    ) spin_stats ON u.id = spin_stats.user_id
    
    -- STRICT filtering: Only users with actual activity (votes OR spins > 0)
    WHERE u.id IS NOT NULL AND u.id > 0
      AND (
          (vote_stats.total_votes IS NOT NULL AND vote_stats.total_votes > 0) OR 
          (spin_stats.total_spins IS NOT NULL AND spin_stats.total_spins > 0)
      )
      -- Additional filter: exclude obvious test/inactive accounts
      AND COALESCE(TRIM(u.username), '') != ''
      AND u.username NOT LIKE 'test%'
      AND u.username NOT LIKE 'dummy%'
    
    ORDER BY {$orderBy}
");

$stmt->execute();
$all_leaderboard_data = $stmt->fetchAll();

// Get total count for pagination
$total_users = count($all_leaderboard_data);

// Calculate levels and user_points for each user
foreach ($all_leaderboard_data as &$user) {
    // Get user points using QRCoinManager
    $user['user_points'] = QRCoinManager::getBalance($user['user_id']);
    
    // Ensure equipped_avatar has fallback value
    if (empty($user['equipped_avatar'])) {
        $user['equipped_avatar'] = 1;
    }
    
    $level_data = calculateUserLevel(
        $user['total_votes'], 
        $user['user_points'], 
        $user['voting_days'], 
        $user['spin_days'],
        $user['user_id']
    );
    $user['user_level'] = $level_data['level'];
    $user['level_progress'] = $level_data['progress'];
}

// Sort by the appropriate metric in PHP since user_points and user_level are calculated
switch ($filter) {
    case 'points':
        usort($all_leaderboard_data, function($a, $b) {
            if ($a['user_points'] == $b['user_points']) {
                return $b['total_activity'] - $a['total_activity'];
            }
            return $b['user_points'] - $a['user_points'];
        });
        break;
        
    case 'level':
        usort($all_leaderboard_data, function($a, $b) {
            if ($a['user_level'] == $b['user_level']) {
                if ($a['user_points'] == $b['user_points']) {
                    return $b['total_activity'] - $a['total_activity'];
                }
                return $b['user_points'] - $a['user_points'];
            }
            return $b['user_level'] - $a['user_level'];
        });
        break;
        
    default:
        // Other filters are already sorted by the SQL query
        break;
}

// Apply pagination
$leaderboard_data = array_slice($all_leaderboard_data, $offset, $per_page);
$total_pages = ceil($total_users / $per_page);

// Get current user's position in this leaderboard (from full data)
$current_user_rank = 0;
foreach ($all_leaderboard_data as $index => $user) {
    if ($user['user_id'] == $_SESSION['user_id'] || 
        (!$user['user_id'] && $user['voter_ip'] == get_client_ip())) {
        $current_user_rank = $index + 1;
        break;
    }
}

include '../core/includes/header.php';
?>

<!-- Custom Leaderboard Styling -->
<style>
.bg-gradient-primary {
    background: linear-gradient(135deg, #1976d2 0%, #1565c0 50%, #0d47a1 100%) !important;
}

.leaderboard-rank-1 { background: linear-gradient(135deg, #ffd700, #ffb300) !important; }
.leaderboard-rank-2 { background: linear-gradient(135deg, #c0c0c0, #9e9e9e) !important; }
.leaderboard-rank-3 { background: linear-gradient(135deg, #cd7f32, #8d5524) !important; }

.trophy-glow {
    text-shadow: 0 0 10px rgba(255, 215, 0, 0.5);
}

.leaderboard-avatar {
    transition: all 0.3s ease;
}

.leaderboard-avatar:hover {
    transform: scale(1.05);
    filter: brightness(1.1);
}

/* Enhanced trophy rankings */
.trophy-glow {
    animation: trophy-pulse 2s ease-in-out infinite;
}

@keyframes trophy-pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.02); }
}

/* Completely disable all hover background effects */
.table-hover tbody tr:hover {
    background-color: transparent !important;
    background: transparent !important;
    transform: translateY(-1px);
    transition: all 0.2s ease;
}

.table-hover tbody tr:hover td {
    background-color: transparent !important;
    background: transparent !important;
}

.table-responsive {
    border-radius: 12px;
    overflow: hidden;
}

.filter-btn-group .btn {
    border-radius: 25px !important;
    font-weight: 500;
    transition: all 0.3s ease;
}

.filter-btn-group .btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

/* Table text visibility fixes - transparent like vote page */
.table-responsive .table {
    background-color: transparent !important;
}

.table-responsive .table td,
.table-responsive .table th {
    color: rgba(255, 255, 255, 0.9) !important;
    border-color: rgba(255, 255, 255, 0.15) !important;
    background-color: rgba(255, 255, 255, 0.05) !important;
}

.table-responsive .table-dark th {
    background-color: rgba(30, 60, 114, 0.8) !important;
    color: #ffffff !important;
    border-color: rgba(255, 255, 255, 0.15) !important;
}

.table-responsive .table-warning {
    background-color: rgba(255, 193, 7, 0.15) !important;
}

.table-responsive .table-warning td {
    color: rgba(255, 255, 255, 0.95) !important;
    background-color: rgba(255, 193, 7, 0.15) !important;
}

/* Ensure badges and text elements are visible on transparent background */
.badge {
    color: #ffffff !important;
}

.table .text-success {
    color: #4caf50 !important;
    font-size: 1.1em !important;
    font-weight: 600 !important;
    text-shadow: 
        -1px -1px 0 #000,
        1px -1px 0 #000,
        -1px 1px 0 #000,
        1px 1px 0 #000 !important;
}

.table .text-danger {
    color: #f44336 !important;
    font-size: 1.1em !important;
    font-weight: 600 !important;
    text-shadow: 
        -1px -1px 0 #000,
        1px -1px 0 #000,
        -1px 1px 0 #000,
        1px 1px 0 #000 !important;
}

.table .text-warning {
    color: #ff9800 !important;
    font-size: 1.1em !important;
    font-weight: 600 !important;
    text-shadow: 
        -1px -1px 0 #000,
        1px -1px 0 #000,
        -1px 1px 0 #000,
        1px 1px 0 #000 !important;
}

/* Enhanced styling for primary metric numbers */
.table .fw-bold {
    font-size: 1.15em !important;
    text-shadow: 
        -1px -1px 0 #000,
        1px -1px 0 #000,
        -1px 1px 0 #000,
        1px 1px 0 #000 !important;
}

/* Special styling for points column */
.table .text-success.fw-bold {
    color: #4caf50 !important;
    font-size: 1.2em !important;
    font-weight: 700 !important;
    text-shadow: 
        -1px -1px 0 #000,
        1px -1px 0 #000,
        -1px 1px 0 #000,
        1px 1px 0 #000 !important;
}

/* Primary metric column styling */
.table .primary-metric {
    font-size: 1.25em !important;
    font-weight: 700 !important;
    text-shadow: 
        -1px -1px 0 #000,
        1px -1px 0 #000,
        -1px 1px 0 #000,
        1px 1px 0 #000 !important;
}

.table .text-muted {
    color: rgba(255, 255, 255, 0.7) !important;
}

.table .small {
    color: rgba(255, 255, 255, 0.8) !important;
}

/* Pagination styling */
.pagination .page-link {
    background-color: rgba(255, 255, 255, 0.1) !important;
    border-color: rgba(255, 255, 255, 0.2) !important;
    color: rgba(255, 255, 255, 0.9) !important;
}

.pagination .page-link:hover {
    background-color: rgba(255, 255, 255, 0.2) !important;
    border-color: rgba(255, 255, 255, 0.3) !important;
    color: #ffffff !important;
}

.pagination .page-item.active .page-link {
    background-color: #1976d2 !important;
    border-color: #1976d2 !important;
    color: #ffffff !important;
}

.pagination .page-item.disabled .page-link {
    background-color: rgba(255, 255, 255, 0.05) !important;
    border-color: rgba(255, 255, 255, 0.1) !important;
    color: rgba(255, 255, 255, 0.5) !important;
}

@media (max-width: 768px) {
    .table-responsive table {
        font-size: 0.875rem;
    }
    
    .table-responsive th, 
    .table-responsive td {
        padding: 0.5rem 0.25rem;
    }
}
</style>

<div class="container py-4">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card bg-gradient-primary text-white">
                <div class="card-body text-center">
                    <h1 class="h2 mb-1">
                        <i class="bi bi-trophy-fill text-warning me-2"></i>
                        <?php echo $title; ?>
                    </h1>
                    <p class="mb-0 opacity-90"><?php echo $subtitle; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Navigation -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-center gap-2 filter-btn-group">
                        <?php
                        $filters = [
                            'level' => ['Level Leaders', 'trophy', 'warning'],
                            'votes' => ['Top Voters', 'check2-square', 'primary'],
                            'spins' => ['Spin Masters', 'arrow-repeat', 'info'],
                            'wins' => ['Lucky Winners', 'star-fill', 'success'],
                            'activity' => ['Most Active', 'lightning', 'danger'],
                            'streak' => ['Streak Champions', 'fire', 'warning'],
                            'points' => ['Point Leaders', 'coin', 'success']
                        ];
                        
                        foreach ($filters as $key => $data):
                            $active = $filter === $key ? 'btn-' . $data[2] : 'btn-outline-' . $data[2];
                        ?>
                        <a href="?filter=<?php echo $key; ?>" class="btn <?php echo $active; ?> btn-sm">
                            <i class="bi bi-<?php echo $data[1]; ?> me-1"></i>
                            <span class="d-none d-sm-inline"><?php echo $data[0]; ?></span>
                            <span class="d-sm-none"><?php echo explode(' ', $data[0])[0]; ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Your Position Card -->
    <?php if ($current_user_rank > 0): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-info d-flex align-items-center">
                <i class="bi bi-info-circle-fill me-2"></i>
                <strong>Your Position:</strong> You're ranked #<?php echo $current_user_rank; ?> in <?php echo strtolower($title); ?>!
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Leaderboard -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">
                        <i class="bi bi-list-ol me-2"></i>
                        Rankings
                    </h5>
                    <div class="text-muted small">
                        Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $per_page, $total_users); ?> of <?php echo $total_users; ?> users
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th width="60"><i class="bi bi-trophy-fill text-warning me-1"></i>#</th>
                                    <th width="80"><i class="bi bi-person-circle text-info me-1"></i>Avatar</th>
                                    <th><i class="bi bi-person-badge text-primary me-1"></i>Player</th>
                                    <th><i class="bi bi-star-fill text-warning me-1"></i>Level</th>
                                    <th><img src="../img/qrCoin.png" alt="QR Coin" class="me-1" style="width: 1em; height: 1em;">QR Coins</th>
                                    <th><i class="bi bi-hand-thumbs-up text-primary me-1"></i>Votes</th>
                                    <th><i class="bi bi-arrow-clockwise text-info me-1"></i>Spins</th>
                                    <th><i class="bi bi-lightning-charge text-warning me-1"></i>Activity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($leaderboard_data)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted">
                                            <i class="bi bi-inbox display-4 d-block mb-2"></i>
                                            No leaderboard data available yet.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($leaderboard_data as $index => $user): 
                                        $rank = ($offset + $index + 1); // Adjust rank for pagination
                                        $isCurrentUser = ($user['user_id'] == $_SESSION['user_id']) || 
                                                        (!$user['user_id'] && $user['voter_ip'] == get_client_ip());
                                        
                                        // Enhanced rank styling with trophy colors
                                        $rankBadge = '';
                                        $trophyIcon = '';
                                        if ($rank == 1) {
                                            $rankBadge = 'text-dark';
                                            $trophyIcon = '<i class="bi bi-trophy-fill me-1" style="color: #FFD700; text-shadow: 0 0 10px rgba(255, 215, 0, 0.8);"></i>';
                                        } elseif ($rank == 2) {
                                            $rankBadge = 'text-dark';
                                            $trophyIcon = '<i class="bi bi-trophy-fill me-1" style="color: #C0C0C0; text-shadow: 0 0 10px rgba(192, 192, 192, 0.8);"></i>';
                                        } elseif ($rank == 3) {
                                            $rankBadge = 'text-dark';
                                            $trophyIcon = '<i class="bi bi-trophy-fill me-1" style="color: #CD7F32; text-shadow: 0 0 10px rgba(205, 127, 50, 0.8);"></i>';
                                        } else {
                                            $rankBadge = 'bg-light text-dark';
                                            $trophyIcon = '';
                                        }
                                        
                                        $avatarFile = getAvatarFilename($user['equipped_avatar'] ?? 1);
                                    ?>
                                    <tr>
                                        <td>
                                            <span class="badge <?php echo $rankBadge; ?> fs-6" style="<?php echo $rank <= 3 ? 'background: linear-gradient(135deg, rgba(255,255,255,0.9), rgba(255,255,255,0.7)); border: 2px solid ' . ($rank == 1 ? '#FFD700' : ($rank == 2 ? '#C0C0C0' : '#CD7F32')) . ';' : ''; ?>">
                                                <?php echo $trophyIcon; ?>
                                                <?php echo $rank; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <img src="../assets/img/avatars/<?php echo $avatarFile; ?>" 
                                                 alt="Avatar" 
                                                 class="leaderboard-avatar <?php echo $rank <= 3 ? 'trophy-glow' : ''; ?>" 
                                                 style="width: 80px; height: 80px; object-fit: cover; border-radius: 12px; <?php echo $rank <= 3 ? 'border: 3px solid ' . ($rank == 1 ? '#FFD700' : ($rank == 2 ? '#C0C0C0' : '#CD7F32')) . '; box-shadow: 0 0 15px rgba(' . ($rank == 1 ? '255,215,0' : ($rank == 2 ? '192,192,192' : '205,127,50')) . ', 0.6);' : 'border: 2px solid rgba(255,255,255,0.3);'; ?>"
                                                 onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAiIGhlaWdodD0iODAiIHZpZXdCb3g9IjAgMCA4MCA4MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPHJlY3Qgd2lkdGg9IjgwIiBoZWlnaHQ9IjgwIiBmaWxsPSIjZTllY2VmIi8+Cjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBkb21pbmFudC1iYXNlbGluZT0ibWlkZGxlIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmaWxsPSIjNmM3NTdkIj5RUjwvdGV4dD4KPHN2Zz4=';">
                                        </td>
                                        <td>
                                            <div class="fw-semibold">
                                                <?php echo htmlspecialchars($user['username'] ?? 'Unknown User'); ?>
                                                <?php if ($isCurrentUser): ?>
                                                    <span class="badge bg-primary ms-1"><i class="bi bi-star-fill me-1"></i>You</span>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted">
                                                <?php if ($user['user_id']): ?>
                                                    <i class="bi bi-person-check-fill me-1 text-success"></i>Registered User
                                                <?php else: ?>
                                                    <i class="bi bi-globe2 me-1 text-info"></i>IP: <?php echo htmlspecialchars(substr($user['voter_ip'] ?? '', -8)); ?>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-gradient text-white fs-6" style="background: linear-gradient(135deg, #1976d2, #1565c0) !important; padding: 8px 12px;">
                                                <i class="bi bi-star-fill me-1 text-warning"></i><?php echo $user['user_level']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-success fs-5">
                                                <img src="../img/qrCoin.png" alt="QR Coin" class="me-1" style="width: 1em; height: 1em;"><?php echo number_format($user['user_points']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fw-semibold text-primary fs-6"><?php echo number_format($user['total_votes']); ?></span>
                                            <?php if ($user['total_votes'] > 0): ?>
                                                <div class="small text-muted">
                                                    <span class="text-success"><i class="bi bi-arrow-up-circle-fill me-1"></i><?php echo $user['votes_in']; ?> in</span> • 
                                                    <span class="text-danger"><i class="bi bi-arrow-down-circle-fill me-1"></i><?php echo $user['votes_out']; ?> out</span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="fw-semibold text-info fs-6"><?php echo number_format($user['total_spins']); ?></span>
                                            <?php if ($user['real_wins'] > 0 || $user['losses'] > 0): ?>
                                                <div class="small">
                                                    <?php if ($user['real_wins'] > 0): ?>
                                                        <span class="text-success">
                                                            <i class="bi bi-trophy-fill me-1"></i><?php echo $user['real_wins']; ?> wins
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if ($user['real_wins'] > 0 && $user['losses'] > 0): ?> • <?php endif; ?>
                                                    <?php if ($user['losses'] > 0): ?>
                                                        <span class="text-danger">
                                                            <i class="bi bi-x-circle-fill me-1"></i><?php echo $user['losses']; ?> losses
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="small">
                                                <div class="fw-bold text-warning"><i class="bi bi-lightning-charge-fill me-1"></i><?php echo $user['total_activity']; ?> total</div>
                                                <div class="text-muted"><i class="bi bi-calendar-check me-1"></i><?php echo $user['activity_days']; ?> days</div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="row mt-4">
        <div class="col-12">
            <nav aria-label="Leaderboard pagination">
                <ul class="pagination justify-content-center">
                    <!-- Previous Page -->
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo $page - 1; ?>">
                                <i class="bi bi-chevron-left"></i> Previous
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link"><i class="bi bi-chevron-left"></i> Previous</span>
                        </li>
                    <?php endif; ?>

                    <!-- Page Numbers -->
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?filter=<?php echo $filter; ?>&page=1">1</a>
                        </li>
                        <?php if ($start_page > 2): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                            <li class="page-item disabled">
                                <span class="page-link">...</span>
                            </li>
                        <?php endif; ?>
                        <li class="page-item">
                            <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a>
                        </li>
                    <?php endif; ?>

                    <!-- Next Page -->
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo $page + 1; ?>">
                                Next <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="page-item disabled">
                            <span class="page-link">Next <i class="bi bi-chevron-right"></i></span>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </div>
    <?php endif; ?>

    <!-- Stats Summary -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h6 class="card-title">
                        <i class="bi bi-graph-up me-2"></i>
                        Community Stats
                    </h6>
                    <div class="row text-center">
                        <div class="col-6 col-md-3">
                            <div class="fw-bold h5" style="color: #1976d2 !important;">
                                <?php echo number_format(array_sum(array_column($all_leaderboard_data, 'total_votes'))); ?>
                            </div>
                            <small style="color: #6c757d !important;">Total Votes</small>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="fw-bold h5" style="color: #17a2b8 !important;">
                                <?php echo number_format(array_sum(array_column($all_leaderboard_data, 'total_spins'))); ?>
                            </div>
                            <small style="color: #6c757d !important;">Total Spins</small>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="fw-bold h5" style="color: #198754 !important;">
                                <?php echo number_format(array_sum(array_column($all_leaderboard_data, 'user_points'))); ?>
                            </div>
                            <small style="color: #6c757d !important;">Total QR Coins</small>
                        </div>
                        <div class="col-6 col-md-3">
                            <div class="fw-bold h5" style="color: #fd7e14 !important;">
                                <?php echo count($all_leaderboard_data); ?>
                            </div>
                            <small style="color: #6c757d !important;">Active Users</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh leaderboard data every 30 seconds to ensure updates
setInterval(function() {
    // Only refresh if user is actively viewing the page
    if (!document.hidden) {
        // Add cache busting parameter
        const currentUrl = new URL(window.location);
        currentUrl.searchParams.set('_t', Date.now());
        
        // Check if data is stale by making a quick AJAX request
        fetch(currentUrl.toString(), {
            method: 'HEAD',
            cache: 'no-cache'
        }).then(() => {
            // If we're still on the same page, do a soft refresh
            if (window.location.pathname.includes('leaderboard.php')) {
                const hasActivity = document.querySelector('.table tbody tr');
                if (hasActivity) {
                    // Only refresh if there's been recent activity
                    location.reload(true);
                }
            }
        }).catch(err => {
            console.log('Auto-refresh check failed:', err);
        });
    }
}, 30000); // 30 seconds

// Handle filter changes with better UX
document.addEventListener('DOMContentLoaded', function() {
    const filterButtons = document.querySelectorAll('.filter-btn-group .btn');
    
    filterButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Add loading state
            button.innerHTML = '<i class="spinner-border spinner-border-sm me-2"></i>Loading...';
            
            // Navigate to the new filter
            setTimeout(() => {
                window.location.href = this.href;
            }, 100);
        });
    });
    
    // Add real-time timestamp
    const timestamp = document.createElement('small');
    timestamp.className = 'text-muted ms-2';
    timestamp.innerHTML = '<i class="bi bi-clock me-1"></i>Last updated: ' + new Date().toLocaleTimeString();
    
    const cardTitle = document.querySelector('h3.card-title');
    if (cardTitle) {
        cardTitle.appendChild(timestamp);
    }
});

// Prevent duplicate submissions and improve performance
window.addEventListener('beforeunload', function() {
    // Clear any pending refreshes
    if (window.refreshTimer) {
        clearInterval(window.refreshTimer);
    }
});
</script>

<?php include '../core/includes/footer.php'; ?> 