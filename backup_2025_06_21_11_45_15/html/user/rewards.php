<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/functions.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';

// Require user role
require_role('user');

$user_id = $_SESSION['user_id']; // Use logged-in user ID instead of just IP

// Get QR Coin balance (NEW SYSTEM - consistent with dashboard)
$user_points = QRCoinManager::getBalance($user_id);

// Get comprehensive stats for other metrics
$stats = getUserStats($user_id, get_client_ip());
$voting_stats = $stats['voting_stats'];
$spin_stats = $stats['spin_stats'];
$user_votes = $voting_stats['total_votes'];

// Get user's recent achievements (spins and votes)
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
    LIMIT 10
");
$stmt->execute([$user_id, get_client_ip(), $user_id, get_client_ip()]);
$recent_achievements = $stmt->fetchAll();

// Calculate user level based on total activity
$level_data = calculateUserLevel($user_votes, $user_points, $voting_stats['voting_days'], $spin_stats['spin_days'], $user_id);
$user_level = $level_data['level'];
$next_level_progress = $level_data['progress'];
$points_to_next = $level_data['points_to_next'];

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h1 class="h3 mb-0">Your Rewards</h1>
        <p class="text-muted">Track your QR coins, achievements, and progress</p>
    </div>
</div>

<!-- User Level and Progress -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card gradient-card-primary shadow-lg">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="d-flex align-items-center mb-3">
                            <div class="me-4">
                                <h2 class="text-white mb-0">
                                    <i class="bi bi-trophy-fill text-warning me-2"></i>Level <?php echo $user_level; ?>
                                </h2>
                                <p class="text-white-50 mb-0">Current Level</p>
                            </div>
                            <div class="ms-4">
                                                <h3 class="text-warning mb-0"><?php echo number_format($level_data['total_points']); ?></h3>
                <p class="text-white-50 mb-0">Total QR Coins</p>
                            </div>
                        </div>
                        
                        <?php if ($user_level < 100): ?>
                            <p class="text-white mb-2">Progress to Level <?php echo $user_level + 1; ?></p>
                            <div class="progress mb-2" style="height: 12px;">
                                <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo $next_level_progress; ?>%"></div>
                            </div>
                            <small class="text-white-50">
                                <?php echo number_format($next_level_progress, 1); ?>% complete 
                                • <?php echo number_format($points_to_next); ?> QR coins needed
                            </small>
                        <?php else: ?>
                            <p class="text-warning mb-2">🎉 MAX LEVEL ACHIEVED! 🎉</p>
                            <div class="progress mb-2" style="height: 12px;">
                                <div class="progress-bar bg-warning" role="progressbar" style="width: 100%"></div>
                            </div>
                            <small class="text-white-50">You've reached the highest level possible!</small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 text-center">
                        <div class="text-white">
                            <i class="bi bi-star-fill display-2 text-warning mb-2"></i>
                            <div class="h1 mb-0">Level <?php echo $user_level; ?></div>
                            <small class="text-white-50">of 100</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Points and Votes Stats -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="card bg-warning text-dark">
            <div class="card-body text-center">
                <img src="../img/qrCoin.png" alt="QR Coin" class="mb-2" style="width: 4rem; height: 4rem;">
                <h2 class="mb-0"><?php echo number_format($user_points); ?></h2>
                <p class="mb-0">Total QR Coins</p>
                <small>Earned from spinning</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <i class="bi bi-check2-all display-4 mb-2"></i>
                <h2 class="mb-0"><?php echo number_format($user_votes); ?></h2>
                <p class="mb-0">Total Votes</p>
                <small>Items you've voted on</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <i class="bi bi-calendar-check display-4 mb-2"></i>
                <h2 class="mb-0"><?php echo $voting_stats['voting_days'] + $spin_stats['spin_days']; ?></h2>
                <p class="mb-0">Active Days</p>
                <small>Days with activity</small>
            </div>
        </div>
    </div>
</div>

<!-- Ways to Earn More -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Earn More Rewards</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center p-3 border rounded">
                            <div class="flex-shrink-0">
                                <i class="bi bi-check2-square display-6 text-primary"></i>
                            </div>
                            <div class="ms-3">
                                <h6 class="mb-1">Vote for Items</h6>
                                <p class="text-muted mb-1">Help improve vending selections</p>
                                <a href="<?php echo APP_URL; ?>/user/vote.php" class="btn btn-primary btn-sm">Start Voting</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-center p-3 border rounded">
                            <div class="flex-shrink-0">
                                <i class="bi bi-trophy display-6 text-warning"></i>
                            </div>
                            <div class="ms-3">
                                <h6 class="mb-1">Daily Spin</h6>
                                <p class="text-muted mb-1">Win QR coins and prizes</p>
                                <a href="<?php echo APP_URL; ?>/user/spin.php" class="btn btn-warning btn-sm">Spin Now</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Achievements -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Recent Activity</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_achievements)): ?>
                    <div class="text-center py-4">
                        <img src="../img/qractivity.png" alt="QR Activity" class="mb-3" style="width: 6rem; height: 6rem; opacity: 0.5;">
                        <h5 class="text-muted">No activity yet</h5>
                        <p class="text-muted">Start voting or spinning to see your achievements here!</p>
                        <div class="d-flex gap-2 justify-content-center">
                            <a href="<?php echo APP_URL; ?>/user/vote.php" class="btn btn-primary">Vote Now</a>
                            <a href="<?php echo APP_URL; ?>/user/spin.php" class="btn btn-warning">Spin Now</a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($recent_achievements as $achievement): ?>
                            <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                                <div class="flex-shrink-0">
                                    <?php if ($achievement['type'] === 'spin'): ?>
                                        <i class="bi bi-trophy-fill text-warning fs-4"></i>
                                    <?php else: ?>
                                        <i class="bi bi-check-circle-fill text-success fs-4"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="ms-3">
                                    <div class="fw-medium"><?php echo htmlspecialchars($achievement['achievement']); ?></div>
                                    <small class="text-muted"><?php echo date('M d, Y H:i', strtotime($achievement['created_at'])); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Add some interactive elements for rewards page
document.addEventListener('DOMContentLoaded', function() {
    // Animate progress bar
    const progressBar = document.querySelector('.progress-bar');
    if (progressBar) {
        const width = progressBar.style.width;
        progressBar.style.width = '0%';
        setTimeout(() => {
            progressBar.style.transition = 'width 1s ease-in-out';
            progressBar.style.width = width;
        }, 500);
    }
});
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 