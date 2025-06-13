<?php
/**
 * Horse Racing System - Race Details Page
 * Shows detailed information about a specific race
 */

require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/qr_coin_manager.php';

// Require user to be logged in
require_login();

$user_id = $_SESSION['user_id'];
$race_id = $_GET['id'] ?? 0;

if (!$race_id) {
    header('Location: index.php');
    exit;
}

// Get race details with proper error handling
try {
    $stmt = $pdo->prepare("
        SELECT br.*, b.name as business_name, b.logo_url,
               CASE 
                   WHEN br.start_time <= NOW() AND br.end_time >= NOW() THEN 'LIVE'
                   WHEN br.start_time > NOW() THEN 'UPCOMING'
                   ELSE 'FINISHED'
               END as race_status,
               TIMESTAMPDIFF(SECOND, NOW(), br.start_time) as time_to_start,
               TIMESTAMPDIFF(SECOND, NOW(), br.end_time) as time_remaining
        FROM business_races br
        JOIN businesses b ON br.business_id = b.id
        WHERE br.id = ? AND br.status IN ('approved', 'active')
    ");
    $stmt->execute([$race_id]);
    $race = $stmt->fetch();

    if (!$race) {
        header('Location: index.php');
        exit;
    }
} catch (Exception $e) {
    header('Location: index.php');
    exit;
}

// Get race horses
$stmt = $pdo->prepare("
    SELECT rh.*, mi.name as item_name, mi.image_url,
           COALESCE(ija.custom_jockey_name, ja.jockey_name, 'TBD') as jockey_name,
           COALESCE(ija.custom_jockey_avatar_url, ja.jockey_avatar_url, '/horse-racing/assets/img/jockeys/jockey-other.png') as jockey_avatar_url,
           COALESCE(rh.current_odds, 3.0) as current_odds,
           rh.performance_score,
           rh.sales_24h
    FROM race_horses rh
    LEFT JOIN master_items mi ON rh.item_id = mi.id
    LEFT JOIN item_jockey_assignments ija ON rh.item_id = ija.item_id AND ija.business_id = ?
    LEFT JOIN jockey_assignments ja ON rh.item_id = ja.item_id
    WHERE rh.race_id = ?
    ORDER BY rh.position ASC, rh.current_odds ASC
");
$stmt->execute([$race['business_id'], $race_id]);
$horses = $stmt->fetchAll();

// Get race results if finished
$results = [];
if ($race['race_status'] === 'FINISHED') {
    $stmt = $pdo->prepare("
        SELECT rr.*, rh.horse_name, rh.item_id, mi.name as item_name
        FROM race_results rr
        JOIN race_horses rh ON rr.horse_id = rh.id
        LEFT JOIN master_items mi ON rh.item_id = mi.id
        WHERE rr.race_id = ?
        ORDER BY rr.finish_position ASC
    ");
    $stmt->execute([$race_id]);
    $results = $stmt->fetchAll();
}

// Get user's bets on this race
$stmt = $pdo->prepare("
    SELECT rb.*, rh.horse_name
    FROM race_bets rb
    JOIN race_horses rh ON rb.horse_id = rh.id
    WHERE rb.race_id = ? AND rb.user_id = ?
    ORDER BY rb.bet_placed_at DESC
");
$stmt->execute([$race_id, $user_id]);
$user_bets = $stmt->fetchAll();

// Get race statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT rb.user_id) as total_bettors,
        SUM(rb.bet_amount_qr_coins) as total_bets_amount,
        COUNT(rb.id) as total_bets_placed
    FROM race_bets rb
    WHERE rb.race_id = ?
");
$stmt->execute([$race_id]);
$race_stats = $stmt->fetch();

require_once __DIR__ . '/../core/includes/header.php';
?>

<style>
.race-hero {
    background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
    color: white;
    padding: 2rem 0;
    margin-bottom: 2rem;
}

.detail-card {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 16px !important;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3) !important;
    color: #ffffff;
}

.horse-detail-card {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 12px !important;
    transition: all 0.3s ease;
    color: #ffffff;
    margin-bottom: 1rem;
}

.horse-detail-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
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

.performance-bar {
    background: rgba(255, 255, 255, 0.2);
    height: 8px;
    border-radius: 4px;
    overflow: hidden;
}

.performance-fill {
    background: linear-gradient(45deg, #28a745, #20c997);
    height: 100%;
    transition: width 0.3s ease;
}

.bet-history-table {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 12px !important;
    color: #ffffff;
}

.bet-history-table th {
    background: rgba(255, 255, 255, 0.1) !important;
    border: none !important;
    color: #ffffff !important;
    font-weight: 600;
}

.bet-history-table td {
    background: transparent !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    color: #ffffff !important;
}

.results-table {
    background: rgba(255, 255, 255, 0.12) !important;
    backdrop-filter: blur(20px) !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
    border-radius: 12px !important;
    color: #ffffff;
}

.results-table th {
    background: rgba(255, 255, 255, 0.1) !important;
    border: none !important;
    color: #ffffff !important;
    font-weight: 600;
}

.results-table td {
    background: transparent !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    color: #ffffff !important;
}

.finish-position {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    font-weight: bold;
    color: white;
}

.position-1 { background: linear-gradient(45deg, #ffd700, #ffed4a); color: #333; }
.position-2 { background: linear-gradient(45deg, #c0c0c0, #e8e8e8); color: #333; }
.position-3 { background: linear-gradient(45deg, #cd7f32, #d4a574); color: white; }
.position-other { background: #6c757d; }
</style>

<div class="race-hero">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="../">Home</a></li>
                <li class="breadcrumb-item"><a href="index.php">Horse Racing</a></li>
                <li class="breadcrumb-item active" aria-current="page">Race Details</li>
            </ol>
        </nav>
        
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-5 mb-2"><?php echo htmlspecialchars($race['race_name']); ?></h1>
                <p class="lead mb-3">
                    <i class="bi bi-building me-2"></i><?php echo htmlspecialchars($race['business_name']); ?>
                </p>
                <span class="race-status-<?php echo strtolower($race['race_status']); ?>">
                    <?php echo $race['race_status']; ?>
                </span>
            </div>
            <div class="col-md-4 text-end">
                <div class="detail-card p-3">
                    <h6 class="mb-1">Prize Pool</h6>
                    <h3 class="text-warning mb-0"><?php echo number_format($race['prize_pool_qr_coins']); ?> QR Coins</h3>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <div class="row">
        <!-- Race Information -->
        <div class="col-md-8">
            <!-- Race Status & Timing -->
            <div class="detail-card p-4 mb-4">
                <h5 class="mb-3"><i class="bi bi-clock me-2"></i>Race Information</h5>
                <div class="row">
                    <div class="col-md-3">
                        <small class="text-muted">Race Type</small>
                        <div class="fw-bold"><?php echo ucfirst($race['race_type']); ?> Race</div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted">Horses</small>
                        <div class="fw-bold"><?php echo count($horses); ?> Competing</div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted">Duration</small>
                        <div class="fw-bold">
                            <?php 
                            $duration = (strtotime($race['end_time']) - strtotime($race['start_time'])) / 3600;
                            echo number_format($duration, 1); 
                            ?> Hours
                        </div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted">Status</small>
                        <div class="fw-bold">
                            <?php if ($race['race_status'] === 'LIVE'): ?>
                                <span class="text-danger">⏰ <?php echo gmdate("H:i:s", $race['time_remaining']); ?> left</span>
                            <?php elseif ($race['race_status'] === 'UPCOMING'): ?>
                                <span class="text-info">🕐 Starts in <?php echo gmdate("H:i:s", $race['time_to_start']); ?></span>
                            <?php else: ?>
                                <span class="text-success">✅ Completed</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Race Results (if finished) -->
            <?php if ($race['race_status'] === 'FINISHED' && !empty($results)): ?>
                <div class="detail-card p-4 mb-4">
                    <h5 class="mb-3"><i class="bi bi-trophy me-2"></i>Final Results</h5>
                    <div class="table-responsive">
                        <table class="table results-table mb-0">
                            <thead>
                                <tr>
                                    <th>Position</th>
                                    <th>Horse</th>
                                    <th>Item</th>
                                    <th>Sales Performance</th>
                                    <th>Winnings</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $result): ?>
                                    <tr>
                                        <td>
                                            <span class="finish-position position-<?php echo $result['finish_position'] <= 3 ? $result['finish_position'] : 'other'; ?>">
                                                <?php echo $result['finish_position']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($result['horse_name']); ?></strong>
                                        </td>
                                        <td><?php echo htmlspecialchars($result['item_name']); ?></td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo $result['sales_performance']; ?> units</span>
                                        </td>
                                        <td>
                                            <?php if ($result['winnings_qr_coins'] > 0): ?>
                                                <span class="text-warning fw-bold">
                                                    <?php echo number_format($result['winnings_qr_coins']); ?> QR Coins
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Competing Horses -->
            <div class="detail-card p-4 mb-4">
                <h5 class="mb-3"><i class="bi bi-collection me-2"></i>Competing Horses</h5>
                <div class="row">
                    <?php foreach ($horses as $index => $horse): ?>
                        <div class="col-md-6 mb-3">
                            <div class="horse-detail-card p-3">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h6 class="mb-1"><?php echo htmlspecialchars($horse['horse_name']); ?></h6>
                                        <small class="text-muted">
                                            Jockey: <?php echo htmlspecialchars($horse['jockey_name']); ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold text-warning"><?php echo $horse['current_odds']; ?>:1</div>
                                        <small>Position <?php echo $index + 1; ?></small>
                                    </div>
                                </div>
                                
                                <div class="row mb-2">
                                    <div class="col-6">
                                        <small>24h Sales</small>
                                        <div class="fw-bold"><?php echo $horse['sales_24h']; ?> units</div>
                                    </div>
                                    <div class="col-6">
                                        <small>Performance</small>
                                        <div class="fw-bold"><?php echo round($horse['performance_score']); ?>%</div>
                                    </div>
                                </div>
                                
                                <div class="performance-bar">
                                    <div class="performance-fill" 
                                         style="width: <?php echo min(100, $horse['performance_score']); ?>%"></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-md-4">
            <!-- Race Statistics -->
            <div class="detail-card p-4 mb-4">
                <h5 class="mb-3"><i class="bi bi-graph-up me-2"></i>Race Statistics</h5>
                <div class="row text-center">
                    <div class="col-12 mb-3">
                        <h3 class="text-warning mb-1"><?php echo $race_stats['total_bettors'] ?? 0; ?></h3>
                        <small>Active Bettors</small>
                    </div>
                    <div class="col-6">
                        <h4 class="mb-1"><?php echo $race_stats['total_bets_placed'] ?? 0; ?></h4>
                        <small>Total Bets</small>
                    </div>
                    <div class="col-6">
                        <h4 class="mb-1"><?php echo number_format($race_stats['total_bets_amount'] ?? 0); ?></h4>
                        <small>QR Coins Wagered</small>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="detail-card p-4 mb-4">
                <h5 class="mb-3"><i class="bi bi-lightning me-2"></i>Actions</h5>
                <div class="d-grid gap-2">
                    <?php if ($race['race_status'] === 'LIVE'): ?>
                        <a href="race-live.php?id=<?php echo $race['id']; ?>" class="btn btn-danger">
                            <i class="bi bi-eye"></i> Watch Live Race
                        </a>
                    <?php elseif ($race['race_status'] === 'UPCOMING'): ?>
                        <a href="betting.php?race_id=<?php echo $race['id']; ?>" class="btn btn-success">
                            <i class="bi bi-currency-dollar"></i> Place Bets
                        </a>
                    <?php endif; ?>
                    
                    <a href="index.php" class="btn btn-outline-light">
                        <i class="bi bi-arrow-left"></i> Back to Races
                    </a>
                    
                    <a href="leaderboard.php" class="btn btn-outline-warning">
                        <i class="bi bi-trophy"></i> View Leaderboard
                    </a>
                </div>
            </div>

            <!-- Your Bets -->
            <?php if (!empty($user_bets)): ?>
                <div class="detail-card p-4">
                    <h5 class="mb-3"><i class="bi bi-receipt me-2"></i>Your Bets</h5>
                    <div class="table-responsive">
                        <table class="table bet-history-table mb-0">
                            <thead>
                                <tr>
                                    <th>Horse</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($user_bets as $bet): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($bet['horse_name']); ?></td>
                                        <td><?php echo number_format($bet['bet_amount_qr_coins']); ?></td>
                                        <td>
                                            <?php if ($bet['status'] === 'won'): ?>
                                                <span class="badge bg-success">Won</span>
                                            <?php elseif ($bet['status'] === 'lost'): ?>
                                                <span class="badge bg-danger">Lost</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Auto-refresh for live races
<?php if ($race['race_status'] === 'LIVE'): ?>
setInterval(function() {
    location.reload();
}, 30000); // Refresh every 30 seconds for live races
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?>