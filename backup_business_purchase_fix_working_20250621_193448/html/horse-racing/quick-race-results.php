<?php
/**
 * Quick Race Results - View past race results and statistics
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';

$user_id = $_SESSION['user_id'] ?? null;

// Get date filter
$selected_date = $_GET['date'] ?? date('Y-m-d');

// Get quick race results for selected date
$stmt = $pdo->prepare("
    SELECT * FROM quick_race_results 
    WHERE race_date = ?
    ORDER BY race_index ASC
");
$stmt->execute([$selected_date]);
$race_results = $stmt->fetchAll();

// Get user's bets for selected date
$user_bets = [];
if ($user_id) {
    $stmt = $pdo->prepare("
        SELECT qrb.*, qrr.winning_horse_name, qrr.race_name
        FROM quick_race_bets qrb
        LEFT JOIN quick_race_results qrr ON qrb.race_date = qrr.race_date AND qrb.race_index = qrr.race_index
        WHERE qrb.user_id = ? AND qrb.race_date = ?
        ORDER BY qrb.race_index ASC
    ");
    $stmt->execute([$user_id, $selected_date]);
    $user_bets = $stmt->fetchAll();
}

// Get user's quick race statistics
$user_stats = ['total_bets' => 0, 'total_won' => 0, 'total_winnings' => 0, 'win_rate' => 0];
if ($user_id) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_bets,
            SUM(CASE WHEN status = 'won' THEN 1 ELSE 0 END) as total_won,
            SUM(CASE WHEN status = 'won' THEN actual_winnings ELSE 0 END) as total_winnings
        FROM quick_race_bets 
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $stats = $stmt->fetch();
    
    $user_stats = [
        'total_bets' => $stats['total_bets'] ?: 0,
        'total_won' => $stats['total_won'] ?: 0,
        'total_winnings' => $stats['total_winnings'] ?: 0,
        'win_rate' => $stats['total_bets'] > 0 ? round(($stats['total_won'] / $stats['total_bets']) * 100, 1) : 0
    ];
}

// Race schedule for reference
$race_schedule = [
    ['time' => '09:35:00', 'name' => 'Morning Sprint', 'description' => 'Start your day with excitement!'],
    ['time' => '12:00:00', 'name' => 'Lunch Rush', 'description' => 'Midday racing action!'],
    ['time' => '18:10:00', 'name' => 'Evening Thunder', 'description' => 'After-work entertainment!'],
    ['time' => '21:05:00', 'name' => 'Night Lightning', 'description' => 'Prime time racing!'],
    ['time' => '02:10:00', 'name' => 'Midnight Express', 'description' => 'Late night thrills!'],
    ['time' => '05:10:00', 'name' => 'Dawn Dash', 'description' => 'Early bird special!']
];

require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
.quick-results-container {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    padding: 2rem 0;
}

.results-card {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(20px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    color: white;
    margin-bottom: 2rem;
}

.race-result-item {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 1rem;
}

.winner-highlight {
    background: linear-gradient(45deg, #ffd700, #ffed4a);
    color: #333;
    border: 2px solid #ffd700;
}

.position-badge {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.2rem;
}

.position-1 { background: linear-gradient(45deg, #ffd700, #ffed4a); color: #333; }
.position-2 { background: linear-gradient(45deg, #c0c0c0, #e8e8e8); color: #333; }
.position-3 { background: linear-gradient(45deg, #cd7f32, #daa520); color: white; }
.position-other { background: rgba(255, 255, 255, 0.2); color: white; }

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 15px;
    padding: 1.5rem;
    text-align: center;
}

.bet-status-won { color: #28a745; font-weight: bold; }
.bet-status-lost { color: #dc3545; font-weight: bold; }
.bet-status-pending { color: #ffc107; font-weight: bold; }
</style>

<div class="quick-results-container">
    <div class="container">
        <div class="text-center mb-4">
            <h1 class="text-white mb-2">📊 Quick Race Results</h1>
            <p class="text-white-50">View past race results and your betting history</p>
            <div class="mt-3">
                <a href="quick-races.php" class="btn btn-warning me-2">
                    <i class="bi bi-lightning-charge"></i> Back to Quick Races
                </a>
                <a href="index.php" class="btn btn-outline-light">
                    <i class="bi bi-house"></i> Horse Racing Home
                </a>
            </div>
        </div>

        <!-- Date Filter -->
        <div class="results-card">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h5 class="mb-0">📅 Select Date</h5>
                    </div>
                    <div class="col-md-6">
                        <form method="get" class="d-flex">
                            <input type="date" name="date" value="<?php echo $selected_date; ?>" 
                                   class="form-control me-2" max="<?php echo date('Y-m-d'); ?>">
                            <button type="submit" class="btn btn-primary">View</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($user_id): ?>
        <!-- User Statistics -->
        <div class="results-card">
            <div class="card-body">
                <h5 class="mb-3">🏆 Your Quick Race Statistics</h5>
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3 class="mb-1"><?php echo $user_stats['total_bets']; ?></h3>
                        <small>Total Bets</small>
                    </div>
                    <div class="stat-card">
                        <h3 class="mb-1"><?php echo $user_stats['total_won']; ?></h3>
                        <small>Races Won</small>
                    </div>
                    <div class="stat-card">
                        <h3 class="mb-1"><?php echo number_format($user_stats['total_winnings']); ?></h3>
                        <small>QR Coins Won</small>
                    </div>
                    <div class="stat-card">
                        <h3 class="mb-1"><?php echo $user_stats['win_rate']; ?>%</h3>
                        <small>Win Rate</small>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Race Results for Selected Date -->
        <div class="results-card">
            <div class="card-body">
                <h5 class="mb-4">🏁 Race Results for <?php echo date('F j, Y', strtotime($selected_date)); ?></h5>
                
                <?php if (empty($race_results)): ?>
                    <div class="text-center py-4">
                        <h6 class="text-white-50">No race results found for this date</h6>
                        <p class="text-white-50">Races may not have been completed yet or no races were scheduled.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($race_results as $result): ?>
                        <?php 
                        $race_data = json_decode($result['race_results'], true);
                        $user_bet = null;
                        foreach ($user_bets as $bet) {
                            if ($bet['race_index'] == $result['race_index']) {
                                $user_bet = $bet;
                                break;
                            }
                        }
                        ?>
                        <div class="race-result-item <?php echo $user_bet && $user_bet['status'] === 'won' ? 'winner-highlight' : ''; ?>">
                            <div class="row align-items-center mb-3">
                                <div class="col-md-8">
                                    <h6 class="mb-1">
                                        <?php echo $result['race_name']; ?>
                                        <small class="text-white-50">
                                            (<?php echo date('g:i A', strtotime($result['race_start_time'])); ?>)
                                        </small>
                                    </h6>
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-success me-2">🏆 Winner</span>
                                        <strong><?php echo $result['winning_horse_name']; ?></strong>
                                        <small class="text-white-50 ms-2">
                                            Jockey: <?php echo $result['winning_jockey_name']; ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="col-md-4 text-end">
                                    <div class="small text-white-50">
                                        <?php echo $result['total_bets']; ?> bets • 
                                        <?php echo number_format($result['total_bet_amount']); ?> QR coins wagered
                                    </div>
                                    <?php if ($user_bet): ?>
                                        <div class="mt-2">
                                            <span class="badge <?php echo $user_bet['status'] === 'won' ? 'bg-success' : 'bg-danger'; ?>">
                                                Your bet: <?php echo strtoupper($user_bet['status']); ?>
                                                <?php if ($user_bet['status'] === 'won'): ?>
                                                    (+<?php echo $user_bet['actual_winnings']; ?> coins)
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Full Race Results -->
                            <div class="row">
                                <div class="col-12">
                                    <h6 class="mb-3">📋 Full Results</h6>
                                    <div class="row">
                                        <?php foreach ($race_data as $horse_result): ?>
                                            <div class="col-md-6 col-lg-4 mb-2">
                                                <div class="d-flex align-items-center">
                                                    <div class="position-badge position-<?php echo $horse_result['position'] <= 3 ? $horse_result['position'] : 'other'; ?> me-3">
                                                        <?php echo $horse_result['position']; ?>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo $horse_result['horse_name']; ?></strong><br>
                                                        <small class="text-white-50">
                                                            <?php echo $horse_result['jockey_name']; ?> • 
                                                            <?php echo $horse_result['finish_time']; ?>s
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Race Schedule Reference -->
        <div class="results-card">
            <div class="card-body">
                <h5 class="mb-3">⏰ Daily Race Schedule</h5>
                <div class="row">
                    <?php foreach ($race_schedule as $index => $race): ?>
                        <div class="col-md-4 mb-3">
                            <div class="stat-card">
                                <h6><?php echo $race['name']; ?></h6>
                                <div class="badge bg-primary mb-2">
                                    <?php echo date('g:i A', strtotime($race['time'])); ?>
                                </div>
                                <p class="small mb-0"><?php echo $race['description']; ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 