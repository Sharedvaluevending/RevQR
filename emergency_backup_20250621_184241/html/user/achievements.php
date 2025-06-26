<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/functions.php';

// Require user role
require_role('user');

$user_id = $_SESSION['user_id'];

// Get comprehensive user stats
$stats = getUserStats($user_id, get_client_ip());
$voting_stats = $stats['voting_stats'];
$spin_stats = $stats['spin_stats'];
$current_streak = $stats['current_streak'];

// Get casino stats
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_casino_plays,
        COUNT(CASE WHEN is_jackpot = 1 THEN 1 END) as jackpot_wins,
        COUNT(CASE WHEN win_amount >= (bet_amount * 3) THEN 1 END) as big_wins,
        SUM(win_amount) as total_winnings,
        MAX(win_amount) as biggest_win,
        SUM(bet_amount) as total_wagered
    FROM casino_plays 
    WHERE user_id = ?
");
$stmt->execute([$user_id]);
$casino_stats = $stmt->fetch();

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h1 class="h3 mb-0">
            <i class="bi bi-trophy-fill me-2 text-warning"></i>Achievements
        </h1>
        <p class="text-muted">Track your progress and unlock rewards for your activity</p>
    </div>
</div>

<!-- Achievement Categories -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="bi bi-check2-square me-2"></i>Voting Achievements
                </h5>
            </div>
            <div class="card-body">
                <!-- Getting Started -->
                <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                    <i class="bi bi-check-circle-fill text-<?php echo $voting_stats['total_votes'] >= 10 ? 'success' : 'muted'; ?> fs-3 me-3"></i>
                    <div class="flex-grow-1">
                        <h6 class="mb-1">Getting Started</h6>
                        <small class="text-muted">Cast 10+ votes</small>
                        <div class="progress mt-1" style="height: 4px;">
                            <div class="progress-bar bg-primary" style="width: <?php echo min(100, ($voting_stats['total_votes'] / 10) * 100); ?>%"></div>
                        </div>
                        <small class="text-muted"><?php echo $voting_stats['total_votes']; ?> / 10</small>
                    </div>
                    <?php if ($voting_stats['total_votes'] >= 10): ?>
                        <span class="badge bg-success">Unlocked</span>
                    <?php endif; ?>
                </div>

                <!-- Active Voter -->
                <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                    <i class="bi bi-award-fill text-<?php echo $voting_stats['total_votes'] >= 50 ? 'success' : 'muted'; ?> fs-3 me-3"></i>
                    <div class="flex-grow-1">
                        <h6 class="mb-1">Active Voter</h6>
                        <small class="text-muted">Cast 50+ votes</small>
                        <div class="progress mt-1" style="height: 4px;">
                            <div class="progress-bar bg-success" style="width: <?php echo min(100, ($voting_stats['total_votes'] / 50) * 100); ?>%"></div>
                        </div>
                        <small class="text-muted"><?php echo $voting_stats['total_votes']; ?> / 50</small>
                    </div>
                    <?php if ($voting_stats['total_votes'] >= 50): ?>
                        <span class="badge bg-success">Unlocked</span>
                    <?php endif; ?>
                </div>

                <!-- Voting Champion -->
                <div class="d-flex align-items-center mb-0">
                    <i class="bi bi-trophy-fill text-<?php echo $voting_stats['total_votes'] >= 100 ? 'warning' : 'muted'; ?> fs-3 me-3"></i>
                    <div class="flex-grow-1">
                        <h6 class="mb-1">Voting Champion</h6>
                        <small class="text-muted">Cast 100+ votes</small>
                        <div class="progress mt-1" style="height: 4px;">
                            <div class="progress-bar bg-warning" style="width: <?php echo min(100, ($voting_stats['total_votes'] / 100) * 100); ?>%"></div>
                        </div>
                        <small class="text-muted"><?php echo $voting_stats['total_votes']; ?> / 100</small>
                    </div>
                    <?php if ($voting_stats['total_votes'] >= 100): ?>
                        <span class="badge bg-warning">Unlocked</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">
                    <i class="bi bi-arrow-repeat me-2"></i>Spinning Achievements
                </h5>
            </div>
            <div class="card-body">
                <!-- Lucky Player -->
                <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                    <i class="bi bi-dice-6-fill text-<?php echo $spin_stats['total_spins'] >= 10 ? 'info' : 'muted'; ?> fs-3 me-3"></i>
                    <div class="flex-grow-1">
                        <h6 class="mb-1">Lucky Player</h6>
                        <small class="text-muted">Complete 10+ spins</small>
                        <div class="progress mt-1" style="height: 4px;">
                            <div class="progress-bar bg-info" style="width: <?php echo min(100, ($spin_stats['total_spins'] / 10) * 100); ?>%"></div>
                        </div>
                        <small class="text-muted"><?php echo $spin_stats['total_spins']; ?> / 10</small>
                    </div>
                    <?php if ($spin_stats['total_spins'] >= 10): ?>
                        <span class="badge bg-info">Unlocked</span>
                    <?php endif; ?>
                </div>

                <!-- Spin Master -->
                <div class="d-flex align-items-center mb-0">
                    <i class="bi bi-stars text-<?php echo $spin_stats['total_spins'] >= 30 ? 'warning' : 'muted'; ?> fs-3 me-3"></i>
                    <div class="flex-grow-1">
                        <h6 class="mb-1">Spin Master</h6>
                        <small class="text-muted">Complete 30+ spins</small>
                        <div class="progress mt-1" style="height: 4px;">
                            <div class="progress-bar bg-warning" style="width: <?php echo min(100, ($spin_stats['total_spins'] / 30) * 100); ?>%"></div>
                        </div>
                        <small class="text-muted"><?php echo $spin_stats['total_spins']; ?> / 30</small>
                    </div>
                    <?php if ($spin_stats['total_spins'] >= 30): ?>
                        <span class="badge bg-warning">Unlocked</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Casino Achievements -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">
                    <i class="bi bi-suit-spade-fill me-2"></i>Casino & Blackjack Achievements
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <!-- Casino Newcomer -->
                        <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                            <i class="bi bi-suit-club-fill text-<?php echo $casino_stats['total_casino_plays'] >= 10 ? 'info' : 'muted'; ?> fs-3 me-3"></i>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">Casino Newcomer</h6>
                                <small class="text-muted">Play 10+ casino games</small>
                                <div class="progress mt-1" style="height: 4px;">
                                    <div class="progress-bar bg-info" style="width: <?php echo min(100, ($casino_stats['total_casino_plays'] / 10) * 100); ?>%"></div>
                                </div>
                                <small class="text-muted"><?php echo $casino_stats['total_casino_plays']; ?> / 10</small>
                            </div>
                            <?php if ($casino_stats['total_casino_plays'] >= 10): ?>
                                <span class="badge bg-info">Unlocked</span>
                            <?php endif; ?>
                        </div>

                        <!-- Casino Regular -->
                        <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                            <i class="bi bi-dice-5-fill text-<?php echo $casino_stats['total_casino_plays'] >= 50 ? 'success' : 'muted'; ?> fs-3 me-3"></i>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">Casino Regular</h6>
                                <small class="text-muted">Play 50+ casino games</small>
                                <div class="progress mt-1" style="height: 4px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo min(100, ($casino_stats['total_casino_plays'] / 50) * 100); ?>%"></div>
                                </div>
                                <small class="text-muted"><?php echo $casino_stats['total_casino_plays']; ?> / 50</small>
                            </div>
                            <?php if ($casino_stats['total_casino_plays'] >= 50): ?>
                                <span class="badge bg-success">Unlocked</span>
                            <?php endif; ?>
                        </div>

                        <!-- High Roller -->
                        <div class="d-flex align-items-center mb-0">
                            <i class="bi bi-gem text-<?php echo $casino_stats['total_casino_plays'] >= 100 ? 'warning' : 'muted'; ?> fs-3 me-3"></i>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">High Roller</h6>
                                <small class="text-muted">Play 100+ casino games</small>
                                <div class="progress mt-1" style="height: 4px;">
                                    <div class="progress-bar bg-warning" style="width: <?php echo min(100, ($casino_stats['total_casino_plays'] / 100) * 100); ?>%"></div>
                                </div>
                                <small class="text-muted"><?php echo $casino_stats['total_casino_plays']; ?> / 100</small>
                            </div>
                            <?php if ($casino_stats['total_casino_plays'] >= 100): ?>
                                <span class="badge bg-warning">Unlocked</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <!-- Lucky Winner -->
                        <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                            <i class="bi bi-star-fill text-<?php echo $casino_stats['jackpot_wins'] >= 1 ? 'success' : 'muted'; ?> fs-3 me-3"></i>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">Lucky Winner</h6>
                                <small class="text-muted">Win a jackpot!</small>
                                <div class="progress mt-1" style="height: 4px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo min(100, ($casino_stats['jackpot_wins'] / 1) * 100); ?>%"></div>
                                </div>
                                <small class="text-muted"><?php echo $casino_stats['jackpot_wins']; ?> / 1</small>
                            </div>
                            <?php if ($casino_stats['jackpot_wins'] >= 1): ?>
                                <span class="badge bg-success">Unlocked</span>
                            <?php endif; ?>
                        </div>

                        <!-- Fortune Finder -->
                        <div class="d-flex align-items-center mb-3 pb-3 border-bottom">
                            <i class="bi bi-cash text-<?php echo $casino_stats['big_wins'] >= 5 ? 'info' : 'muted'; ?> fs-3 me-3"></i>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">Fortune Finder</h6>
                                <small class="text-muted">Win 5+ big wins (3x+ multiplier)</small>
                                <div class="progress mt-1" style="height: 4px;">
                                    <div class="progress-bar bg-info" style="width: <?php echo min(100, ($casino_stats['big_wins'] / 5) * 100); ?>%"></div>
                                </div>
                                <small class="text-muted"><?php echo $casino_stats['big_wins']; ?> / 5</small>
                            </div>
                            <?php if ($casino_stats['big_wins'] >= 5): ?>
                                <span class="badge bg-info">Unlocked</span>
                            <?php endif; ?>
                        </div>

                        <!-- Jackpot Master -->
                        <div class="d-flex align-items-center mb-0">
                            <i class="bi bi-trophy-fill text-<?php echo $casino_stats['jackpot_wins'] >= 5 ? 'warning' : 'muted'; ?> fs-3 me-3"></i>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">Jackpot Master</h6>
                                <small class="text-muted">Win 5+ jackpots</small>
                                <div class="progress mt-1" style="height: 4px;">
                                    <div class="progress-bar bg-warning" style="width: <?php echo min(100, ($casino_stats['jackpot_wins'] / 5) * 100); ?>%"></div>
                                </div>
                                <small class="text-muted"><?php echo $casino_stats['jackpot_wins']; ?> / 5</small>
                            </div>
                            <?php if ($casino_stats['jackpot_wins'] >= 5): ?>
                                <span class="badge bg-warning">Unlocked</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Streak Achievements -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">
                    <i class="bi bi-fire me-2"></i>Streak Achievements
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <!-- Consistent Player -->
                        <div class="d-flex align-items-center mb-3">
                            <i class="bi bi-calendar-check text-<?php echo $current_streak >= 3 ? 'success' : 'muted'; ?> fs-3 me-3"></i>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">Consistent Player</h6>
                                <small class="text-muted">3+ day streak</small>
                                <div class="progress mt-1" style="height: 4px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo min(100, ($current_streak / 3) * 100); ?>%"></div>
                                </div>
                                <small class="text-muted"><?php echo $current_streak; ?> / 3</small>
                            </div>
                            <?php if ($current_streak >= 3): ?>
                                <span class="badge bg-success">Unlocked</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <!-- Weekly Warrior -->
                        <div class="d-flex align-items-center mb-3">
                            <i class="bi bi-fire text-<?php echo $current_streak >= 7 ? 'danger' : 'muted'; ?> fs-3 me-3"></i>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">Weekly Warrior</h6>
                                <small class="text-muted">7+ day streak</small>
                                <div class="progress mt-1" style="height: 4px;">
                                    <div class="progress-bar bg-danger" style="width: <?php echo min(100, ($current_streak / 7) * 100); ?>%"></div>
                                </div>
                                <small class="text-muted"><?php echo $current_streak; ?> / 7</small>
                            </div>
                            <?php if ($current_streak >= 7): ?>
                                <span class="badge bg-danger">Unlocked</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <!-- Monthly Master -->
                        <div class="d-flex align-items-center mb-3">
                            <i class="bi bi-calendar-event-fill text-<?php echo $current_streak >= 30 ? 'warning' : 'muted'; ?> fs-3 me-3"></i>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">Monthly Master</h6>
                                <small class="text-muted">30+ day streak</small>
                                <div class="progress mt-1" style="height: 4px;">
                                    <div class="progress-bar bg-warning" style="width: <?php echo min(100, ($current_streak / 30) * 100); ?>%"></div>
                                </div>
                                <small class="text-muted"><?php echo $current_streak; ?> / 30</small>
                            </div>
                            <?php if ($current_streak >= 30): ?>
                                <span class="badge bg-warning">Unlocked</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Stats Summary -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card bg-dark text-white">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="mb-2">
                            <i class="bi bi-rocket-takeoff me-2"></i>Keep Earning Achievements!
                        </h5>
                        <p class="mb-0 opacity-75">
                            Continue voting, spinning, and playing casino games to unlock more achievements and earn QR coins!
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <div class="d-flex gap-2 justify-content-end">
                            <a href="<?php echo APP_URL; ?>/user/vote.php" class="btn btn-light btn-sm">
                                <i class="bi bi-check2-square me-1"></i>Vote
                            </a>
                            <a href="<?php echo APP_URL; ?>/user/spin.php" class="btn btn-warning btn-sm">
                                <i class="bi bi-arrow-repeat me-1"></i>Spin
                            </a>
                            <a href="<?php echo APP_URL; ?>/casino" class="btn btn-secondary btn-sm">
                                <i class="bi bi-suit-spade me-1"></i>Casino
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.progress {
    transition: all 0.3s ease;
}

.progress-bar {
    transition: width 1s ease-in-out;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Animate progress bars
    const progressBars = document.querySelectorAll('.progress-bar');
    progressBars.forEach(bar => {
        const width = bar.style.width;
        bar.style.width = '0%';
        setTimeout(() => {
            bar.style.width = width;
        }, 300);
    });
});
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 