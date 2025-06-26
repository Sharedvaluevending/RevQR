<?php
/**
 * Horse Racing System - Main User Interface
 * Real-time racing based on vending machine sales data
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';

// Require user to be logged in
require_login();

$user_id = $_SESSION['user_id'];
$user_balance = QRCoinManager::getBalance($user_id);

// Get active races
$stmt = $pdo->prepare("
    SELECT br.*, b.name as business_name, COUNT(rh.id) as horse_count,
           CASE 
               WHEN br.start_time <= NOW() AND br.end_time >= NOW() THEN 'LIVE'
               WHEN br.start_time > NOW() THEN 'UPCOMING'
               ELSE 'FINISHED'
           END as race_status,
           TIMESTAMPDIFF(SECOND, NOW(), br.start_time) as time_to_start,
           TIMESTAMPDIFF(SECOND, NOW(), br.end_time) as time_remaining
    FROM business_races br
    JOIN businesses b ON br.business_id = b.id
    LEFT JOIN race_horses rh ON br.id = rh.race_id
    WHERE br.status IN ('approved', 'active')
    GROUP BY br.id
    ORDER BY br.start_time ASC
");
$stmt->execute();
$races = $stmt->fetchAll();

// Get user's recent racing activity
$stmt = $pdo->prepare("
    SELECT rb.*, br.race_name, rh.horse_name, rr.finish_position
    FROM race_bets rb
    JOIN business_races br ON rb.race_id = br.id
    JOIN race_horses rh ON rb.horse_id = rh.id
    LEFT JOIN race_results rr ON rh.id = rr.horse_id AND rr.race_id = br.id
    WHERE rb.user_id = ?
    ORDER BY rb.bet_placed_at DESC
    LIMIT 10
");
$stmt->execute([$user_id]);
$recent_bets = $stmt->fetchAll();

// Get user racing stats
$stmt = $pdo->prepare("
    SELECT * FROM user_racing_stats WHERE user_id = ?
");
$stmt->execute([$user_id]);
$user_stats = $stmt->fetch() ?: [
    'total_races_participated' => 0,
    'total_qr_coins_won' => 0,
    'win_rate' => 0.00
];

require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
.racing-hero {
    background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
}

.race-card {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 16px !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
    transition: all 0.3s ease;
    color: #ffffff;
}

.race-card:hover {
    transform: translateY(-4px) !important;
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4) !important;
    border: 1px solid rgba(255, 255, 255, 0.25) !important;
}

.race-status-live {
    background: linear-gradient(45deg, #ff6b6b, #ee5a5a);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: bold;
    animation: pulse 2s infinite;
}

.race-status-upcoming {
    background: linear-gradient(45deg, #4ecdc4, #44a08d);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-weight: bold;
}

.race-status-finished {
    background: #6c757d;
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.7; }
    100% { opacity: 1; }
}

.horse-mini-icon {
    font-size: 1.2rem;
    margin-right: 0.5rem;
}

.qr-balance {
    background: linear-gradient(45deg, #ffd700, #ffed4a);
    color: #333;
    padding: 0.75rem 1.5rem;
    border-radius: 25px;
    font-weight: bold;
    box-shadow: 0 4px 15px rgba(255, 215, 0, 0.3);
}

.stats-card {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    color: white;
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
}
</style>

<div class="racing-hero text-center">
    <div class="container">
        <img src="/horse-racing/assets/img/racetrophy.png" alt="Race Trophy" style="width: 100px; height: 100px; margin-bottom: 1rem;">
        <h1 class="display-4 mb-3">🏇 Horse Racing Arena</h1>
        <p class="lead">Real races powered by real vending machine data!</p>
        <div class="qr-balance d-inline-block">
            <img src="../img/qrCoin.png" alt="QR Coin" style="width: 20px; height: 20px;" class="me-2">
            Your Balance: <?php echo number_format($user_balance); ?> QR Coins
        </div>
    </div>
</div>

<div class="container">
    <!-- Quick Races Section -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="race-card p-4" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h3 class="mb-2">⚡ Quick Races - NEW!</h3>
                        <p class="mb-2">6 simulated 1-minute races daily with instant results!</p>
                        <div class="row">
                            <div class="col-md-6">
                                <small class="text-white-50">
                                    <strong>Race Times:</strong><br>
                                    9:35 AM • 12:00 PM • 6:10 PM<br>
                                    9:05 PM • 2:10 AM • 5:10 AM
                                </small>
                            </div>
                            <div class="col-md-6">
                                <small class="text-white-50">
                                    <strong>Features:</strong><br>
                                    • 1-minute races<br>
                                    • Instant payouts<br>
                                    • New horses & jockeys
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-center">
                        <a href="quick-races.php" class="btn btn-warning btn-lg">
                            <i class="bi bi-lightning-charge"></i> Play Quick Races
                        </a>
                        <br><small class="text-white-50 mt-2">Fast-paced racing action!</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- User Stats Summary -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="stats-card">
                <div class="row text-center">
                    <div class="col-md-3">
                        <h3 class="mb-1"><?php echo $user_stats['total_races_participated']; ?></h3>
                        <small>Races Joined</small>
                    </div>
                    <div class="col-md-3">
                        <h3 class="mb-1"><?php echo number_format($user_stats['total_qr_coins_won']); ?></h3>
                        <small>QR Coins Won</small>
                    </div>
                    <div class="col-md-3">
                        <h3 class="mb-1"><?php echo number_format($user_stats['win_rate'], 1); ?>%</h3>
                        <small>Win Rate</small>
                    </div>
                    <div class="col-md-3">
                        <a href="leaderboard.php" class="btn btn-light btn-sm">
                            <i class="bi bi-trophy"></i> Leaderboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active & Upcoming Races -->
    <div class="row">
        <div class="col-md-8">
            <h2 class="mb-4" style="color: #fff;">🏁 Available Races</h2>
            
            <?php if (empty($races)): ?>
                <div class="text-center py-5">
                    <h4 class="text-muted">No active races at the moment</h4>
                    <p class="text-muted">Check back soon for exciting horse races!</p>
                </div>
            <?php else: ?>
                <?php foreach ($races as $race): ?>
                    <div class="race-card mb-4 p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h4 class="mb-1"><?php echo htmlspecialchars($race['race_name']); ?></h4>
                                <p class="text-muted mb-2">
                                    <i class="bi bi-building"></i> <?php echo htmlspecialchars($race['business_name']); ?>
                                </p>
                            </div>
                            <span class="race-status-<?php echo strtolower($race['race_status']); ?>">
                                <?php echo $race['race_status']; ?>
                            </span>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-4">
                                <small class="text-muted">Race Type</small>
                                <div class="fw-bold">
                                    <?php echo ucfirst($race['race_type']); ?> Race
                                </div>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Horses</small>
                                <div class="fw-bold">
                                    <span class="horse-mini-icon">🐎</span><?php echo $race['horse_count']; ?> Competing
                                </div>
                            </div>
                            <div class="col-md-4">
                                <small class="text-muted">Prize Pool</small>
                                <div class="fw-bold text-warning">
                                    <?php echo number_format($race['prize_pool_qr_coins']); ?> Coins
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <?php if ($race['race_status'] === 'LIVE'): ?>
                                <div class="alert alert-danger" style="background: rgba(220, 53, 69, 0.2) !important; backdrop-filter: blur(10px) !important; border: 1px solid rgba(220, 53, 69, 0.3) !important; color: #fff !important;">
                                    <i class="bi bi-broadcast"></i> 
                                    <strong>RACE IN PROGRESS!</strong> 
                                    Ends in <?php echo gmdate("H:i:s", $race['time_remaining']); ?>
                                </div>
                            <?php elseif ($race['race_status'] === 'UPCOMING'): ?>
                                <div class="alert alert-info" style="background: rgba(13, 202, 240, 0.2) !important; backdrop-filter: blur(10px) !important; border: 1px solid rgba(13, 202, 240, 0.3) !important; color: #fff !important;">
                                    <i class="bi bi-clock"></i> 
                                    <strong>Starts in:</strong> 
                                    <?php echo gmdate("H:i:s", $race['time_to_start']); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex gap-2">
                            <?php if ($race['race_status'] === 'LIVE'): ?>
                                <a href="race-live.php?id=<?php echo $race['id']; ?>" class="btn btn-danger">
                                    <i class="bi bi-eye"></i> Watch Live
                                </a>
                            <?php elseif ($race['race_status'] === 'UPCOMING'): ?>
                                <a href="betting.php?race_id=<?php echo $race['id']; ?>" class="btn btn-primary">
                                    <i class="bi bi-currency-dollar"></i> Place Bets
                                </a>
                            <?php endif; ?>
                            
                            <a href="race-details.php?id=<?php echo $race['id']; ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-info-circle"></i> Details
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Sidebar - Recent Activity -->
        <div class="col-md-4">
            <h4 class="mb-4" style="color: #fff;">📊 Recent Activity</h4>
            
            <?php if (empty($recent_bets)): ?>
                <div class="text-center py-4">
                    <p class="text-muted">No betting history yet</p>
                    <small>Place your first bet to see activity here!</small>
                </div>
            <?php else: ?>
                <?php foreach ($recent_bets as $bet): ?>
                    <div class="card mb-3" style="background: rgba(255, 255, 255, 0.12) !important; backdrop-filter: blur(20px) !important; border: 1px solid rgba(255, 255, 255, 0.15) !important; color: #fff !important;">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1"><?php echo htmlspecialchars($bet['race_name']); ?></h6>
                                    <small class="text-muted">
                                        Bet on: <?php echo htmlspecialchars($bet['horse_name']); ?>
                                    </small>
                                </div>
                                <div class="text-end">
                                    <?php if ($bet['status'] === 'won'): ?>
                                        <span class="badge bg-success">Won!</span>
                                        <div class="small text-success">
                                            +<?php echo number_format($bet['potential_winnings']); ?> coins
                                        </div>
                                    <?php elseif ($bet['status'] === 'lost'): ?>
                                        <span class="badge bg-danger">Lost</span>
                                        <div class="small text-danger">
                                            -<?php echo number_format($bet['bet_amount_qr_coins']); ?> coins
                                        </div>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Pending</span>
                                        <div class="small">
                                            <?php echo number_format($bet['bet_amount_qr_coins']); ?> coins
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="text-center mt-3">
                <a href="my-races.php" class="btn btn-outline-primary btn-sm">
                    View All Activity
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh for live races
setInterval(function() {
    const liveRaces = document.querySelectorAll('.race-status-live');
    if (liveRaces.length > 0) {
        location.reload();
    }
}, 30000); // Refresh every 30 seconds if live races exist
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 