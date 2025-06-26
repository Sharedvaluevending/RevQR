<?php
require_once __DIR__ . '/../core/config.php';
require_once __DIR__ . '/../core/session.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/functions.php';

// Require user role
require_role('user');

// Define the same rewards structure as spin.php (updated to match current system)
$specific_rewards = [
    ['name' => 'Lord Pixel!', 'rarity_level' => 11, 'description' => 'Ultra-rare Lord Pixel avatar unlock + spin again!', 'prize_type' => 'avatar', 'value' => 0],
    ['name' => 'Try Again', 'rarity_level' => 2, 'description' => 'Better luck tomorrow! You got nothing this time.', 'prize_type' => 'nothing', 'value' => 0],
    ['name' => 'Extra Vote', 'rarity_level' => 2, 'description' => 'Get an additional vote for this week', 'prize_type' => 'vote', 'value' => 0],
    ['name' => '50 QR Coins', 'rarity_level' => 3, 'description' => 'Earn 50 reward QR coins', 'prize_type' => 'points', 'value' => 50],
    ['name' => '-20 QR Coins', 'rarity_level' => 5, 'description' => 'Lose 20 reward QR coins', 'prize_type' => 'points', 'value' => -20],
    ['name' => '200 QR Coins', 'rarity_level' => 7, 'description' => 'Earn 200 reward QR coins - Big Win!', 'prize_type' => 'points', 'value' => 200],
    ['name' => 'Lose All Votes', 'rarity_level' => 8, 'description' => 'All your weekly votes are reset - Harsh penalty!', 'prize_type' => 'penalty', 'value' => 0],
    ['name' => '500 QR Coins!', 'rarity_level' => 10, 'description' => 'JACKPOT! Earn 500 reward QR coins', 'prize_type' => 'points', 'value' => 500]
];

// Create a lookup array for easy access
$rewards_lookup = [];
foreach ($specific_rewards as $reward) {
    $rewards_lookup[$reward['name']] = $reward;
}

// Get the latest spin result using user_id (not IP)
$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("
    SELECT sr.*
    FROM spin_results sr
    WHERE sr.user_id = ?
    ORDER BY sr.spin_time DESC
    LIMIT 1
");
$stmt->execute([$user_id]);
$result = $stmt->fetch();

// Add reward information to result
if ($result && isset($rewards_lookup[$result['prize_won']])) {
    $reward_info = $rewards_lookup[$result['prize_won']];
    $result['description'] = $reward_info['description'];
    $result['prize_type'] = $reward_info['prize_type'];
    $result['value'] = $reward_info['value'];
    $result['rarity_level'] = $reward_info['rarity_level'];
}

// Get user's spin history using user_id (not IP)
$stmt = $pdo->prepare("
    SELECT sr.*
    FROM spin_results sr
    WHERE sr.user_id = ?
    ORDER BY sr.spin_time DESC
    LIMIT 10
");
$stmt->execute([$user_id]);
$history = $stmt->fetchAll();

// Add reward information to history
foreach ($history as &$spin) {
    if (isset($rewards_lookup[$spin['prize_won']])) {
        $reward_info = $rewards_lookup[$spin['prize_won']];
        $spin['description'] = $reward_info['description'];
        $spin['prize_type'] = $reward_info['prize_type'];
        $spin['value'] = $reward_info['value'];
        $spin['rarity_level'] = $reward_info['rarity_level'];
    } else {
        // Default values for unknown prizes
        $spin['description'] = 'Unknown prize';
        $spin['prize_type'] = 'unknown';
        $spin['value'] = null;
        $spin['rarity_level'] = 1;
    }
}

require_once __DIR__ . '/../core/includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h1 class="h3 mb-0">Spin Result</h1>
        <p class="text-muted">Your latest prize and spin history</p>
    </div>
</div>

<?php if ($result): ?>
    <!-- Latest Result -->
    <div class="row justify-content-center mb-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body text-center">
                    <div class="mb-4">
                        <i class="bi bi-gift display-1 text-<?php echo $result['is_big_win'] ? 'danger' : 'success'; ?>"></i>
                    </div>
                    
                    <h2 class="card-title mb-3">
                        <?php if ($result['is_big_win']): ?>
                            <span class="badge bg-danger mb-2">Big Win!</span><br>
                        <?php endif; ?>
                        <?php echo htmlspecialchars($result['prize_won']); ?>
                    </h2>
                    
                    <p class="card-text text-muted mb-4">
                        <?php echo htmlspecialchars($result['description']); ?>
                    </p>
                    
                    <div class="row justify-content-center mb-4">
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-2 text-muted">Prize Details</h6>
                                    <ul class="list-unstyled mb-0">
                                        <li>
                                            <i class="bi bi-tag me-2"></i>
                                            Type: <?php echo ucfirst($result['prize_type']); ?>
                                        </li>
                                        <?php if ($result['prize_points'] !== null && $result['prize_points'] != 0): ?>
                                            <li>
                                                <i class="bi bi-<?php echo $result['prize_points'] >= 0 ? 'plus' : 'dash'; ?>-circle me-2"></i>
                                                Value: <?php echo $result['prize_points']; ?> QR Coins
                                            </li>
                                        <?php endif; ?>
                                        <li>
                                            <i class="bi bi-star me-2"></i>
                                            Rarity: 
                                            <?php for ($i = 0; $i < $result['rarity_level']; $i++): ?>
                                                <i class="bi bi-star-fill text-warning"></i>
                                            <?php endfor; ?>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-muted">
                        <small>
                            <i class="bi bi-clock me-1"></i>
                            Won on <?php echo date('F j, Y \a\t g:i A', strtotime($result['spin_time'])); ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Spin History -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Spin History</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Prize</th>
                                    <th>Type</th>
                                    <th>Value</th>
                                    <th>Rarity</th>
                                    <th>Spin Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $spin): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-<?php echo $spin['is_big_win'] ? 'danger' : 'success'; ?>">
                                                <?php echo htmlspecialchars($spin['prize_won']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo ucfirst($spin['prize_type']); ?></td>
                                        <td>
                                            <?php if ($spin['prize_points'] !== null && $spin['prize_points'] != 0): ?>
                                                <?php echo $spin['prize_points']; ?> QR Coins
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php for ($i = 0; $i < $spin['rarity_level']; $i++): ?>
                                                <i class="bi bi-star-fill text-warning"></i>
                                            <?php endfor; ?>
                                        </td>
                                        <td><?php echo date('M d, Y H:i', strtotime($spin['spin_time'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body text-center">
                    <i class="bi bi-emoji-smile display-1 text-muted mb-3"></i>
                    <h3>No Spins Yet</h3>
                    <p class="text-muted">Try your luck with the spin wheel!</p>
                    <a href="<?php echo APP_URL; ?>/user/spin.php" class="btn btn-warning">
                        <i class="bi bi-trophy me-2"></i>Spin Now
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../core/includes/footer.php'; ?> 