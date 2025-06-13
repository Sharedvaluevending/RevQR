<?php
/**
 * Horse Racing System - My Races Page
 * Shows user's racing history and activity  
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';  
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';

// Require user to be logged in
require_login();

$user_id = $_SESSION['user_id'];
$user_balance = QRCoinManager::getBalance($user_id);

// Get user's racing statistics
$stmt = $pdo->prepare("
    SELECT * FROM user_racing_stats WHERE user_id = ?
");
$stmt->execute([$user_id]);
$user_stats = $stmt->fetch() ?: [
    'total_races_participated' => 0,
    'total_qr_coins_won' => 0,
    'total_qr_coins_bet' => 0,
    'win_rate' => 0.00,
    'current_streak' => 0,
    'biggest_win_amount' => 0
];

// Get user's bet history with pagination
$page = $_GET['page'] ?? 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$stmt = $pdo->prepare("
    SELECT rb.*, br.race_name, br.race_type, rh.horse_name, 
           rr.finish_position, b.name as business_name,
           CASE 
               WHEN br.start_time <= NOW() AND br.end_time >= NOW() THEN 'LIVE'
               WHEN br.start_time > NOW() THEN 'UPCOMING' 
               ELSE 'FINISHED'
           END as race_status
    FROM race_bets rb
    JOIN business_races br ON rb.race_id = br.id
    JOIN race_horses rh ON rb.horse_id = rh.id
    JOIN businesses b ON br.business_id = b.id
    LEFT JOIN race_results rr ON rh.id = rr.horse_id AND rr.race_id = br.id
    WHERE rb.user_id = ?
    ORDER BY rb.bet_placed_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$user_id, $limit, $offset]);
$bet_history = $stmt->fetchAll();

// Get total count for pagination
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM race_bets rb WHERE rb.user_id = ?
");
$stmt->execute([$user_id]);
$total_bets = $stmt->fetchColumn();
$total_pages = ceil($total_bets / $limit);

// Get active races user can join
$stmt = $pdo->prepare("
    SELECT br.*, b.name as business_name, COUNT(rh.id) as horse_count,
           CASE 
               WHEN br.start_time <= NOW() AND br.end_time >= NOW() THEN 'LIVE'
               WHEN br.start_time > NOW() THEN 'UPCOMING'
               ELSE 'FINISHED'
           END as race_status
    FROM business_races br
    JOIN businesses b ON br.business_id = b.id
    LEFT JOIN race_horses rh ON br.id = rh.race_id
    WHERE br.status IN ('approved', 'active') 
    AND br.start_time > NOW()
    AND br.id NOT IN (
        SELECT DISTINCT race_id FROM race_bets WHERE user_id = ?
    )
    GROUP BY br.id
    ORDER BY br.start_time ASC
    LIMIT 5
");
$stmt->execute([$user_id]);
$available_races = $stmt->fetchAll();

require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
.racing-hero {
    background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
}

.stats-card {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 16px !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
    color: #ffffff;
}

.history-table {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 12px !important;
    color: #ffffff;
}

.history-table th {
    background: rgba(255, 255, 255, 0.1) !important;
    border: none !important;
    color: #ffffff !important;
    font-weight: 600;
}

.history-table td {
    background: transparent !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    color: #ffffff !important;
}

.race-card {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 12px !important;
    color: #ffffff;
    transition: all 0.3s ease;
}

.race-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
}
</style>

<div class="racing-hero text-center">
    <div class="container">
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb justify-content-center">
                <li class="breadcrumb-item"><a href="../" class="text-white-50">Home</a></li>
                <li class="breadcrumb-item"><a href="index.php" class="text-white-50">Horse Racing</a></li>
                <li class="breadcrumb-item active text-white" aria-current="page">My Races</li>
            </ol>
        </nav>
        
        <h1 class="display-4 mb-3">🏇 My Racing Activity</h1>
        <p class="lead">Track your racing performance and discover new opportunities</p>
        <div class="d-inline-block bg-warning text-dark px-3 py-2 rounded-pill fw-bold">
            <img src="../img/qrCoin.png" alt="QR Coin" style="width: 20px; height: 20px;" class="me-2">
            Current Balance: <?php echo number_format($user_balance); ?> QR Coins
        </div>
    </div>
</div>

<div class="container">
    <!-- User Stats Overview -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="stats-card p-4">
                <h5 class="mb-3"><i class="bi bi-graph-up me-2"></i>Your Racing Statistics</h5>
                <div class="row text-center">
                    <div class="col-md-2">
                        <h3 class="text-info mb-1"><?php echo $user_stats['total_races_participated']; ?></h3>
                        <small>Races Joined</small>
                    </div>
                    <div class="col-md-2">
                        <h3 class="text-warning mb-1"><?php echo number_format($user_stats['total_qr_coins_won']); ?></h3>
                        <small>QR Coins Won</small>
                    </div>
                    <div class="col-md-2">
                        <h3 class="text-danger mb-1"><?php echo number_format($user_stats['total_qr_coins_bet']); ?></h3>
                        <small>QR Coins Bet</small>
                    </div>
                    <div class="col-md-2">
                        <h3 class="text-success mb-1"><?php echo number_format($user_stats['win_rate'], 1); ?>%</h3>
                        <small>Win Rate</small>
                    </div>
                    <div class="col-md-2">
                        <h3 class="text-primary mb-1"><?php echo $user_stats['current_streak']; ?></h3>
                        <small>Current Streak</small>
                    </div>
                    <div class="col-md-2">
                        <h3 class="text-light mb-1"><?php echo number_format($user_stats['biggest_win_amount']); ?></h3>
                        <small>Biggest Win</small>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <div class="text-center">
                            <?php 
                            $net_profit = $user_stats['total_qr_coins_won'] - $user_stats['total_qr_coins_bet'];
                            $profit_class = $net_profit >= 0 ? 'text-success' : 'text-danger';
                            ?>
                            <h4 class="<?php echo $profit_class; ?> mb-1">
                                <?php echo $net_profit >= 0 ? '+' : ''; ?><?php echo number_format($net_profit); ?>
                            </h4>
                            <small>Net Profit/Loss</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-center">
                            <a href="leaderboard.php" class="btn btn-outline-warning">
                                <i class="bi bi-trophy"></i> View Leaderboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Bet History -->
        <div class="col-md-8">
            <h4 class="text-white mb-3">📊 Your Betting History</h4>
            
            <?php if (empty($bet_history)): ?>
                <div class="stats-card p-5 text-center">
                    <i class="bi bi-ticket display-1 text-muted mb-3"></i>
                    <h5>No racing activity yet</h5>
                    <p class="text-white-50">Start your racing journey by placing your first bet!</p>
                    <a href="index.php" class="btn btn-primary">
                        <i class="bi bi-play-fill"></i> Browse Races
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table history-table mb-0">
                        <thead>
                            <tr>
                                <th>Race</th>
                                <th>Horse</th>
                                <th>Type</th>
                                <th>Bet Amount</th>
                                <th>Status</th>
                                <th>Result</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bet_history as $bet): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($bet['race_name']); ?></strong>
                                            <br><small class="text-white-50"><?php echo htmlspecialchars($bet['business_name']); ?></small>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($bet['horse_name']); ?></td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo ucfirst($bet['bet_type'] ?? 'Win'); ?></span>
                                    </td>
                                    <td><?php echo number_format($bet['bet_amount_qr_coins']); ?> coins</td>
                                    <td>
                                        <?php if ($bet['status'] === 'won'): ?>
                                            <span class="badge bg-success">Won</span>
                                        <?php elseif ($bet['status'] === 'lost'): ?>
                                            <span class="badge bg-danger">Lost</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">
                                                <?php echo $bet['race_status'] === 'LIVE' ? 'Live' : 'Pending'; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($bet['status'] === 'won'): ?>
                                            <span class="text-success fw-bold">
                                                +<?php echo number_format($bet['potential_winnings']); ?> coins
                                            </span>
                                        <?php elseif ($bet['status'] === 'lost'): ?>
                                            <span class="text-danger">
                                                Position: <?php echo $bet['finish_position'] ?? 'N/A'; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-warning">
                                                <?php echo number_format($bet['potential_winnings']); ?> potential
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?php echo date('M j, Y g:i A', strtotime($bet['bet_placed_at'])); ?></small>
                                    </td>
                                    <td>
                                        <a href="race-details.php?id=<?php echo $bet['race_id']; ?>" 
                                           class="btn btn-outline-light btn-sm">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Available Races Sidebar -->
        <div class="col-md-4">
            <h4 class="text-white mb-3">🏁 Available Races</h4>
            
            <?php if (empty($available_races)): ?>
                <div class="stats-card p-4 text-center">
                    <i class="bi bi-hourglass display-4 text-muted mb-2"></i>
                    <p class="text-white-50">No new races available</p>
                    <small>Check back soon for new racing opportunities!</small>
                </div>
            <?php else: ?>
                <?php foreach ($available_races as $race): ?>
                    <div class="race-card p-3 mb-3">
                        <h6 class="mb-1"><?php echo htmlspecialchars($race['race_name']); ?></h6>
                        <small class="text-white-50 d-block mb-2">
                            <?php echo htmlspecialchars($race['business_name']); ?>
                        </small>
                        
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-info"><?php echo $race['horse_count']; ?> horses</span>
                            <span class="text-warning fw-bold"><?php echo number_format($race['prize_pool_qr_coins']); ?> coins</span>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <a href="race-details.php?id=<?php echo $race['id']; ?>" 
                               class="btn btn-outline-light btn-sm flex-fill">
                                <i class="bi bi-info-circle"></i> Details
                            </a>
                            <a href="betting.php?race_id=<?php echo $race['id']; ?>" 
                               class="btn btn-primary btn-sm flex-fill">
                                <i class="bi bi-currency-dollar"></i> Bet
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="text-center mt-3">
                <a href="index.php" class="btn btn-outline-warning">
                    <i class="bi bi-collection"></i> View All Races
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?>