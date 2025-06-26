<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';

// Require business role
require_role('business');

$business_id = $_SESSION['business_id'];

// Fetch business casino settings
$stmt = $pdo->prepare("SELECT * FROM business_casino_settings WHERE business_id = ?");
$stmt->execute([$business_id]);
$casino_settings = $stmt->fetch() ?: ['casino_enabled' => 0];

// Fetch business info
$stmt = $pdo->prepare("SELECT name, logo_path FROM businesses WHERE id = ?");
$stmt->execute([$business_id]);
$business = $stmt->fetch();

// If casino is not enabled, redirect to settings
if (!$casino_settings['casino_enabled']) {
    $_SESSION['info'] = 'Please enable the casino in your business settings first.';
    header('Location: ' . APP_URL . '/business/settings.php');
    exit;
}

// Fetch casino metrics for different periods
$periods = [
    'today' => "DATE(played_at) = CURDATE()",
    '7days' => "played_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
    '30days' => "played_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
    'all_time' => "1=1"
];

$metrics = [];
foreach ($periods as $period => $condition) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_plays,
            COALESCE(SUM(bet_amount), 0) as total_bets,
            COALESCE(SUM(win_amount), 0) as total_winnings,
            COALESCE(AVG(bet_amount), 0) as avg_bet,
            COUNT(DISTINCT user_id) as unique_players,
            COUNT(CASE WHEN is_jackpot = 1 THEN 1 END) as jackpot_wins,
            COALESCE(MAX(win_amount), 0) as biggest_win
        FROM casino_plays 
        WHERE business_id = ? AND $condition
    ");
    $stmt->execute([$business_id]);
    $metrics[$period] = $stmt->fetch();
    
    // Calculate house edge
    if ($metrics[$period]['total_bets'] > 0) {
        $metrics[$period]['house_edge'] = (($metrics[$period]['total_bets'] - $metrics[$period]['total_winnings']) / $metrics[$period]['total_bets']) * 100;
    } else {
        $metrics[$period]['house_edge'] = 0;
    }
}

// Fetch recent plays
$stmt = $pdo->prepare("
    SELECT 
        cp.*,
        u.username,
        ua.avatar_id as user_avatar
    FROM casino_plays cp
    LEFT JOIN users u ON cp.user_id = u.id
    LEFT JOIN user_avatars ua ON cp.user_id = ua.user_id AND ua.is_equipped = 1
    WHERE cp.business_id = ?
    ORDER BY cp.played_at DESC
    LIMIT 20
");
$stmt->execute([$business_id]);
$recent_plays = $stmt->fetchAll();

// Fetch daily stats for chart
$stmt = $pdo->prepare("
    SELECT 
        DATE(played_at) as play_date,
        COUNT(*) as plays,
        SUM(bet_amount) as total_bet,
        SUM(win_amount) as total_win
    FROM casino_plays 
    WHERE business_id = ? AND played_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY DATE(played_at)
    ORDER BY play_date DESC
");
$stmt->execute([$business_id]);
$daily_stats = $stmt->fetchAll();

// Fetch top players
$stmt = $pdo->prepare("
    SELECT 
        u.username,
        ua.avatar_id,
        COUNT(*) as total_plays,
        SUM(cp.bet_amount) as total_bet,
        SUM(cp.win_amount) as total_winnings,
        MAX(cp.win_amount) as biggest_win
    FROM casino_plays cp
    LEFT JOIN users u ON cp.user_id = u.id
    LEFT JOIN user_avatars ua ON cp.user_id = ua.user_id AND ua.is_equipped = 1
    WHERE cp.business_id = ? AND cp.played_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY cp.user_id, u.username, ua.avatar_id
    ORDER BY total_plays DESC
    LIMIT 10
");
$stmt->execute([$business_id]);
$top_players = $stmt->fetchAll();

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-0"><i class="bi bi-dice-5-fill text-danger me-2"></i>Casino Analytics</h1>
                    <p class="text-muted">Monitor your casino performance and player engagement</p>
                </div>
                <div>
                    <a href="<?php echo APP_URL; ?>/business/settings.php" class="btn btn-outline-primary">
                        <i class="bi bi-gear me-1"></i>Casino Settings
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Period Selection Tabs -->
    <ul class="nav nav-pills mb-4" id="periodTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="today-tab" data-bs-toggle="pill" data-bs-target="#today" type="button">Today</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="week-tab" data-bs-toggle="pill" data-bs-target="#week" type="button">7 Days</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="month-tab" data-bs-toggle="pill" data-bs-target="#month" type="button">30 Days</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="alltime-tab" data-bs-toggle="pill" data-bs-target="#alltime" type="button">All Time</button>
        </li>
    </ul>

    <!-- Metrics Content -->
    <div class="tab-content" id="periodTabContent">
        <?php foreach ($periods as $period => $condition): ?>
        <div class="tab-pane fade <?php echo $period === 'today' ? 'show active' : ''; ?>" id="<?php echo $period === '7days' ? 'week' : ($period === '30days' ? 'month' : $period); ?>" role="tabpanel">
            
            <!-- Metrics Cards -->
            <div class="row g-4 mb-5">
                <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="bi bi-controller display-4 text-primary mb-2"></i>
                            <h3 class="text-primary"><?php echo number_format($metrics[$period]['total_plays']); ?></h3>
                            <p class="mb-0 small">Total Plays</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="bi bi-coin display-4 text-warning mb-2"></i>
                            <h3 class="text-warning"><?php echo number_format($metrics[$period]['total_bets']); ?></h3>
                            <p class="mb-0 small">QR Coins Bet</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="bi bi-trophy display-4 text-success mb-2"></i>
                            <h3 class="text-success"><?php echo number_format($metrics[$period]['total_winnings']); ?></h3>
                            <p class="mb-0 small">QR Coins Won</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="bi bi-people display-4 text-info mb-2"></i>
                            <h3 class="text-info"><?php echo number_format($metrics[$period]['unique_players']); ?></h3>
                            <p class="mb-0 small">Unique Players</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="bi bi-star display-4 text-danger mb-2"></i>
                            <h3 class="text-danger"><?php echo number_format($metrics[$period]['jackpot_wins']); ?></h3>
                            <p class="mb-0 small">Jackpots</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="bi bi-percent display-4 text-secondary mb-2"></i>
                            <h3 class="text-secondary"><?php echo number_format($metrics[$period]['house_edge'], 1); ?>%</h3>
                            <p class="mb-0 small">House Edge</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Revenue Analysis -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h6 class="mb-0"><i class="bi bi-graph-up me-1"></i>Revenue Analysis</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total Revenue:</span>
                                <strong class="text-success">+<?php echo number_format($metrics[$period]['total_bets'] - $metrics[$period]['total_winnings']); ?> coins</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Average Bet:</span>
                                <span><?php echo number_format($metrics[$period]['avg_bet'], 1); ?> coins</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Biggest Win:</span>
                                <span class="text-warning"><?php echo number_format($metrics[$period]['biggest_win']); ?> coins</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Revenue per Play:</span>
                                <span><?php echo $metrics[$period]['total_plays'] > 0 ? number_format(($metrics[$period]['total_bets'] - $metrics[$period]['total_winnings']) / $metrics[$period]['total_plays'], 1) : '0'; ?> coins</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-info text-white">
                            <h6 class="mb-0"><i class="bi bi-bar-chart me-1"></i>Performance Metrics</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Win Rate:</span>
                                <span><?php echo $metrics[$period]['total_plays'] > 0 ? number_format(($metrics[$period]['total_plays'] - ($metrics[$period]['total_bets'] - $metrics[$period]['total_winnings'])) / $metrics[$period]['total_plays'] * 100, 1) : '0'; ?>%</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Jackpot Rate:</span>
                                <span><?php echo $metrics[$period]['total_plays'] > 0 ? number_format($metrics[$period]['jackpot_wins'] / $metrics[$period]['total_plays'] * 100, 2) : '0'; ?>%</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Engagement:</span>
                                <span><?php echo $metrics[$period]['unique_players'] > 0 ? number_format($metrics[$period]['total_plays'] / $metrics[$period]['unique_players'], 1) : '0'; ?> plays/player</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Status:</span>
                                <span class="badge bg-<?php echo $metrics[$period]['house_edge'] > 3 ? 'success' : ($metrics[$period]['house_edge'] > 0 ? 'warning' : 'danger'); ?>">
                                    <?php echo $metrics[$period]['house_edge'] > 3 ? 'Profitable' : ($metrics[$period]['house_edge'] > 0 ? 'Breaking Even' : 'Losing'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="mb-0"><i class="bi bi-gear me-1"></i>Current Settings</h6>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Daily Limit:</span>
                                <span><?php echo $casino_settings['max_daily_plays']; ?> plays</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Jackpot Multiplier:</span>
                                <span><?php echo $casino_settings['jackpot_multiplier']; ?>x</span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Status:</span>
                                <span class="badge bg-success">Active</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Today's Plays:</span>
                                <span><?php echo number_format($metrics['today']['total_plays']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Charts and Tables -->
    <div class="row">
        <!-- Daily Activity Chart -->
        <div class="col-md-8 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h6 class="mb-0"><i class="bi bi-graph-up me-1"></i>Daily Activity (Last 30 Days)</h6>
                </div>
                <div class="card-body">
                    <canvas id="dailyChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Top Players -->
        <div class="col-md-4 mb-4">
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h6 class="mb-0"><i class="bi bi-trophy me-1"></i>Top Players (30d)</h6>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <?php foreach (array_slice($top_players, 0, 5) as $index => $player): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <span class="badge bg-<?php echo $index < 3 ? ['warning', 'secondary', 'dark'][$index] : 'light text-dark'; ?> me-2">
                                    <?php echo $index + 1; ?>
                                </span>
                                <div>
                                    <strong><?php echo htmlspecialchars($player['username'] ?? 'Anonymous'); ?></strong>
                                    <br><small class="text-muted"><?php echo $player['total_plays']; ?> plays</small>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="text-success">+<?php echo number_format($player['total_winnings']); ?></div>
                                <small class="text-muted"><?php echo number_format($player['total_bet']); ?> bet</small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Plays -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h6 class="mb-0"><i class="bi bi-clock me-1"></i>Recent Plays</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Player</th>
                                    <th>Time</th>
                                    <th>Bet</th>
                                    <th>Result</th>
                                    <th>Multiplier</th>
                                    <th>Win Amount</th>
                                    <th>Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_plays as $play): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if ($play['user_avatar']): ?>
                                                <img src="<?php echo APP_URL; ?>/assets/img/avatars/<?php echo getAvatarFilename($play['user_avatar']); ?>" 
                                                     alt="Avatar" class="rounded-circle me-2" style="width: 24px; height: 24px;">
                                            <?php endif; ?>
                                            <strong><?php echo htmlspecialchars($play['username'] ?? 'Anonymous'); ?></strong>
                                        </div>
                                    </td>
                                    <td><small><?php echo date('M d, H:i', strtotime($play['played_at'])); ?></small></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo APP_URL; ?>/img/qrCoin.png" style="width: 16px; height: 16px;" class="me-1">
                                            <?php echo $play['bet_amount']; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                        $symbols = json_decode($play['symbols_result'], true);
                                        if ($symbols && is_array($symbols)) {
                                            echo '<small>';
                                            foreach (array_slice($symbols, 0, 3) as $symbol) {
                                                echo htmlspecialchars($symbol['name'] ?? 'Symbol') . ' ';
                                            }
                                            echo '</small>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($play['win_amount'] > 0): ?>
                                            <span class="badge bg-success"><?php echo number_format($play['win_amount'] / $play['bet_amount'], 1); ?>x</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">0x</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($play['win_amount'] > 0): ?>
                                            <div class="d-flex align-items-center text-success">
                                                <img src="<?php echo APP_URL; ?>/img/qrCoin.png" style="width: 16px; height: 16px;" class="me-1">
                                                <strong>+<?php echo number_format($play['win_amount']); ?></strong>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($play['is_jackpot']): ?>
                                            <span class="badge bg-danger">JACKPOT</span>
                                        <?php elseif ($play['win_amount'] > $play['bet_amount'] * 5): ?>
                                            <span class="badge bg-warning">BIG WIN</span>
                                        <?php elseif ($play['win_amount'] > 0): ?>
                                            <span class="badge bg-success">WIN</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">LOSS</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Daily Activity Chart
const ctx = document.getElementById('dailyChart').getContext('2d');
const dailyData = <?php echo json_encode(array_reverse($daily_stats)); ?>;

new Chart(ctx, {
    type: 'line',
    data: {
        labels: dailyData.map(d => new Date(d.play_date).toLocaleDateString()),
        datasets: [{
            label: 'Plays',
            data: dailyData.map(d => d.plays),
            borderColor: '#007bff',
            backgroundColor: 'rgba(0, 123, 255, 0.1)',
            tension: 0.4
        }, {
            label: 'Total Bet',
            data: dailyData.map(d => d.total_bet),
            borderColor: '#ffc107',
            backgroundColor: 'rgba(255, 193, 7, 0.1)',
            tension: 0.4,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                position: 'left',
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                beginAtZero: true,
                grid: {
                    drawOnChartArea: false,
                },
            }
        }
    }
});
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 